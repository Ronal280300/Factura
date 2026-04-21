<?php

/**
 * Cliente HTTP contra la API de Comprobantes Electronicos de Hacienda Costa Rica.
 *
 *  1. getToken()   — obtiene JWT via OAuth password grant contra el IDP.
 *  2. upload()     — envia un XML firmado (FE emitida o MensajeReceptor).
 *  3. status()     — consulta el estado de un comprobante por clave+consecutivo.
 *
 * No hace manipulacion de XML ni firma. Solo HTTP + gestion de token (cacheado).
 */
class HaciendaClient {

  private array $cfg;
  private string $env;
  private ?array $tokenCache = null;   // ['access_token'=>..., 'expires_at'=>ts]

  public function __construct(array $haciendaCfg) {
    $this->cfg = $haciendaCfg;
    $this->env = $haciendaCfg['env'] ?? 'sandbox';
  }

  private function idpUrl(): string    { return $this->cfg['idp_url'][$this->env]; }
  private function apiUrl(): string    { return $this->cfg['api_url'][$this->env]; }
  private function clientId(): string  { return $this->cfg['client_id'][$this->env]; }

  /**
   * Obtiene un token JWT. Cachea en memoria durante expires_in-30s.
   * @throws RuntimeException si faltan credenciales o el IDP responde error.
   */
  public function getToken(): string {
    if ($this->tokenCache && $this->tokenCache['expires_at'] > time() + 30) {
      return $this->tokenCache['access_token'];
    }

    $user = $this->cfg['username'] ?? '';
    $pass = $this->cfg['password'] ?? '';
    if ($user === '' || $pass === '') {
      throw new RuntimeException('Hacienda: credenciales ATV/TRIBU-CR no configuradas (config.php > hacienda.username/password).');
    }

    $body = http_build_query([
      'grant_type' => 'password',
      'client_id'  => $this->clientId(),
      'username'   => $user,
      'password'   => $pass,
    ]);

    [$code, $resp] = $this->httpPost($this->idpUrl(), $body, [
      'Content-Type: application/x-www-form-urlencoded',
    ]);

    if ($code !== 200) {
      throw new RuntimeException("Hacienda IDP auth fallo HTTP {$code}: {$resp}");
    }

    $json = json_decode($resp, true);
    if (!isset($json['access_token'])) {
      throw new RuntimeException('Hacienda IDP: respuesta sin access_token — ' . $resp);
    }

    $this->tokenCache = [
      'access_token' => $json['access_token'],
      'expires_at'   => time() + (int)($json['expires_in'] ?? 300),
    ];

    return $this->tokenCache['access_token'];
  }

  /**
   * Sube un comprobante (FE o MensajeReceptor) ya firmado.
   *
   * @param string $clave        Clave de 50 digitos (o consecutivo_receptor para MR).
   * @param string $fecha        ISO-8601 (p. ej. 2026-04-21T10:00:00-06:00).
   * @param array  $emisor       ['tipoIdentificacion'=>'02','numeroIdentificacion'=>'...']
   * @param array  $receptor     idem
   * @param string $xmlFirmado   XML ya firmado XAdES-EPES (UTF-8, XML completo).
   *
   * Devuelve [httpCode, bodyRaw, locationHeader|null].
   */
  public function upload(string $clave, string $fecha, array $emisor, array $receptor, string $xmlFirmado): array {
    $token = $this->getToken();

    $payload = [
      'clave'      => $clave,
      'fecha'      => $fecha,
      'emisor'     => $emisor,
      'receptor'   => $receptor,
      'comprobanteXml' => base64_encode($xmlFirmado),
    ];

    [$code, $resp, $headers] = $this->httpPostJson(
      $this->apiUrl() . '/recepcion',
      json_encode($payload, JSON_UNESCAPED_UNICODE),
      ['Authorization: Bearer ' . $token]
    );

    $location = $this->headerValue($headers, 'Location');
    return [$code, $resp, $location];
  }

  /**
   * Consulta el estado de un comprobante ya enviado.
   * Devuelve el JSON de Hacienda con 'ind-estado', 'respuesta-xml' (B64), 'fecha', etc.
   */
  public function status(string $clave): array {
    $token = $this->getToken();
    [$code, $resp] = $this->httpGet(
      $this->apiUrl() . '/recepcion/' . rawurlencode($clave),
      ['Authorization: Bearer ' . $token]
    );

    if ($code !== 200) {
      throw new RuntimeException("Hacienda status HTTP {$code}: {$resp}");
    }
    return json_decode($resp, true) ?: [];
  }

  // ── HTTP helpers ────────────────────────────────────────────────────────────

  private function httpPost(string $url, string $body, array $headers): array {
    return $this->curlExec($url, 'POST', $body, $headers);
  }

  private function httpPostJson(string $url, string $body, array $headers): array {
    $headers[] = 'Content-Type: application/json';
    return $this->curlExec($url, 'POST', $body, $headers);
  }

  private function httpGet(string $url, array $headers): array {
    return $this->curlExec($url, 'GET', null, $headers);
  }

  private function curlExec(string $url, string $method, ?string $body, array $headers): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_CUSTOMREQUEST  => $method,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => true,
      CURLOPT_HTTPHEADER     => $headers,
      CURLOPT_TIMEOUT        => 30,
      CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

    $raw = curl_exec($ch);
    if ($raw === false) {
      $err = curl_error($ch);
      curl_close($ch);
      throw new RuntimeException("cURL error: {$err}");
    }

    $code        = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headersText = substr($raw, 0, $headerSize);
    $bodyText    = substr($raw, $headerSize);
    curl_close($ch);

    return [(int)$code, $bodyText, $this->parseHeaders($headersText)];
  }

  private function parseHeaders(string $text): array {
    $out = [];
    foreach (preg_split("/\r\n|\n|\r/", $text) as $line) {
      if (strpos($line, ':') === false) continue;
      [$k, $v] = explode(':', $line, 2);
      $out[strtolower(trim($k))] = trim($v);
    }
    return $out;
  }

  private function headerValue(array $headers, string $name): ?string {
    return $headers[strtolower($name)] ?? null;
  }
}
