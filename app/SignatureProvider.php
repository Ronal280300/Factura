<?php

/**
 * Firma XAdES-EPES para MensajeReceptor y comprobantes de Hacienda CR.
 *
 * Dos implementaciones:
 *   - NullSignatureProvider: placeholder. Lanza excepcion al firmar.
 *   - XmlSecLibsSignatureProvider: firma con robrichards/xmlseclibs
 *     (requiere `composer require robrichards/xmlseclibs`).
 *
 * IMPORTANTE: La firma que Hacienda exige es XAdES-EPES con una politica
 * especifica. Esta implementacion cubre XML-DSig + atributos XAdES minimos
 * (SigningCertificate, SignaturePolicyIdentifier). Antes de pasar a
 * produccion, validar contra el sandbox de Hacienda.
 */
interface SignatureProvider {
  /**
   * Firma el XML y devuelve el XML con el bloque <Signature> agregado.
   * @throws RuntimeException si la firma falla.
   */
  public function sign(string $xml): string;
}

/** Placeholder: no firma. Util en entornos sin certificado configurado. */
class NullSignatureProvider implements SignatureProvider {
  public function sign(string $xml): string {
    throw new RuntimeException(
      'Firma XAdES no configurada. Instalar robrichards/xmlseclibs via composer '
      . 'y configurar hacienda.cert_path / cert_password en config.php.'
    );
  }
}

/**
 * Implementacion con robrichards/xmlseclibs. Firma XML-DSig enveloped +
 * extensiones XAdES (policy + certificado firmante).
 *
 * Uso:
 *   $p = new XmlSecLibsSignatureProvider('/ruta/cert.p12', 'PIN', 'https://.../resolucion.pdf', $policyDigestB64);
 *   $xmlFirmado = $p->sign($xml);
 */
class XmlSecLibsSignatureProvider implements SignatureProvider {

  private string $certPath;
  private string $certPass;
  private string $policyUrl;
  private string $policyDigest;   // SHA-256 del PDF de la politica (base64)

  public function __construct(string $certPath, string $certPass, string $policyUrl, string $policyDigest) {
    if (!is_file($certPath)) {
      throw new RuntimeException("Certificado no encontrado: {$certPath}");
    }
    $this->certPath     = $certPath;
    $this->certPass     = $certPass;
    $this->policyUrl    = $policyUrl;
    $this->policyDigest = $policyDigest;
  }

  public function sign(string $xml): string {
    // xmlseclibs se auto-carga via composer autoload en el bootstrap de los endpoints.
    if (!class_exists('\\RobRichards\\XMLSecLibs\\XMLSecurityDSig')) {
      throw new RuntimeException(
        'robrichards/xmlseclibs no disponible. Ejecute `composer install` en la raiz del proyecto.'
      );
    }

    // Leer .p12
    $p12 = file_get_contents($this->certPath);
    $certs = [];
    if (!openssl_pkcs12_read($p12, $certs, $this->certPass)) {
      throw new RuntimeException('No se pudo abrir el .p12 (PIN incorrecto o formato invalido).');
    }
    $privateKeyPem = $certs['pkey'];
    $certPem       = $certs['cert'];

    $dsigClass = '\\RobRichards\\XMLSecLibs\\XMLSecurityDSig';
    $keyClass  = '\\RobRichards\\XMLSecLibs\\XMLSecurityKey';

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = false;
    $dom->loadXML($xml);

    $dsig = new $dsigClass('ds');
    $dsig->setCanonicalMethod($dsigClass::EXC_C14N);
    $dsig->addReference(
      $dom,
      $dsigClass::SHA256,
      ['http://www.w3.org/2000/09/xmldsig#enveloped-signature', $dsigClass::EXC_C14N],
      ['force_uri' => true]
    );

    $key = new $keyClass($keyClass::RSA_SHA256, ['type' => 'private']);
    $key->loadKey($privateKeyPem, false);
    $dsig->sign($key);
    $dsig->add509Cert($certPem, true);
    $dsig->appendSignature($dom->documentElement);

    // Inyectar bloque XAdES minimo (Object/QualifyingProperties) con policy.
    // Nota: implementacion simplificada. Produccion: anadir SigningTime,
    // SigningCertificate/CertDigest y SignedDataObjectProperties completos.
    $this->addXadesObject($dom, $certPem);

    return $dom->saveXML();
  }

  private function addXadesObject(DOMDocument $dom, string $certPem): void {
    $dsNs    = 'http://www.w3.org/2000/09/xmldsig#';
    $xadesNs = 'http://uri.etsi.org/01903/v1.3.2#';

    $sig = $dom->getElementsByTagNameNS($dsNs, 'Signature')->item(0);
    if (!$sig) return;

    $objectId = 'xades-' . bin2hex(random_bytes(4));
    $object   = $dom->createElementNS($dsNs, 'ds:Object');
    $qp       = $dom->createElementNS($xadesNs, 'xades:QualifyingProperties');
    $qp->setAttribute('Target', '#' . ($sig->getAttribute('Id') ?: 'Signature'));
    $object->appendChild($qp);

    $sp = $dom->createElementNS($xadesNs, 'xades:SignedProperties');
    $sp->setAttribute('Id', $objectId);
    $qp->appendChild($sp);

    $ssp = $dom->createElementNS($xadesNs, 'xades:SignedSignatureProperties');
    $sp->appendChild($ssp);

    // SigningTime
    $st = $dom->createElementNS($xadesNs, 'xades:SigningTime', gmdate('Y-m-d\TH:i:s\Z'));
    $ssp->appendChild($st);

    // SigningCertificate
    $sc   = $dom->createElementNS($xadesNs, 'xades:SigningCertificate');
    $cert = $dom->createElementNS($xadesNs, 'xades:Cert');
    $sc->appendChild($cert);

    $certDigest = $dom->createElementNS($xadesNs, 'xades:CertDigest');
    $cert->appendChild($certDigest);
    $dm = $dom->createElementNS($dsNs, 'ds:DigestMethod');
    $dm->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
    $certDigest->appendChild($dm);
    $dv = $dom->createElementNS($dsNs, 'ds:DigestValue',
      base64_encode(hash('sha256', $this->pemToDer($certPem), true)));
    $certDigest->appendChild($dv);

    $ssp->appendChild($sc);

    // SignaturePolicyIdentifier
    $spi  = $dom->createElementNS($xadesNs, 'xades:SignaturePolicyIdentifier');
    $spid = $dom->createElementNS($xadesNs, 'xades:SignaturePolicyId');
    $spi->appendChild($spid);

    $sigPolId = $dom->createElementNS($xadesNs, 'xades:SigPolicyId');
    $ident    = $dom->createElementNS($xadesNs, 'xades:Identifier', $this->policyUrl);
    $sigPolId->appendChild($ident);
    $spid->appendChild($sigPolId);

    $sigPolHash = $dom->createElementNS($xadesNs, 'xades:SigPolicyHash');
    $dm2 = $dom->createElementNS($dsNs, 'ds:DigestMethod');
    $dm2->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
    $sigPolHash->appendChild($dm2);
    $dv2 = $dom->createElementNS($dsNs, 'ds:DigestValue', $this->policyDigest ?: '');
    $sigPolHash->appendChild($dv2);
    $spid->appendChild($sigPolHash);

    $ssp->appendChild($spi);

    $sig->appendChild($object);
  }

  private function pemToDer(string $pem): string {
    $body = preg_replace('/-----(BEGIN|END)[^-]+-----|\s+/', '', $pem);
    return base64_decode($body);
  }
}

/** Factory: devuelve el provider apropiado segun la config. */
class SignatureProviderFactory {
  public static function fromConfig(array $hacienda): SignatureProvider {
    $certPath = $hacienda['cert_path'] ?? '';
    if (!$certPath || !is_file($certPath)) {
      return new NullSignatureProvider();
    }
    return new XmlSecLibsSignatureProvider(
      $certPath,
      $hacienda['cert_password'] ?? '',
      $hacienda['xades_policy_url'] ?? '',
      $hacienda['xades_policy_digest'] ?? ''
    );
  }
}
