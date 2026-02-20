<?php

class ImapClient {
  private $cfg;
  private $conn;

  public function __construct(array $imapCfg) {
    $this->cfg = $imapCfg;
  }

  public function connect(): void {
    $mailbox = sprintf("{%s:%d%s}%s",
      $this->cfg['host'],
      $this->cfg['port'],
      $this->cfg['flags'],
      $this->cfg['mailbox'] ?? 'INBOX'
    );

    // OP_READONLY para evitar marcas en el buzón
    $this->conn = @imap_open($mailbox, $this->cfg['username'], $this->cfg['password'], OP_READONLY);

    if (!$this->conn) {
      throw new Exception('IMAP connect error: ' . imap_last_error());
    }
  }

  public function close(): void {
    if ($this->conn) imap_close($this->conn);
  }

  public function searchByReceivedDateRange(DateTime $from, DateTime $to): array {
    // IMAP usa formato "01-Jan-2026"
    $since = $from->format('d-M-Y');
    $before = $to->format('d-M-Y'); // ojo: BEFORE es exclusivo; nosotros le daremos to+1 día desde afuera.

    $criteria = 'SINCE "' . $since . '" BEFORE "' . $before . '"';
    $uids = imap_search($this->conn, $criteria, SE_UID);

    if (!$uids) return [];
    return $uids;
  }

  public function getHeaderInfoByUid(int $uid): array {
    $msgno = imap_msgno($this->conn, $uid);
    $header = imap_headerinfo($this->conn, $msgno);

    $from = '';
    if (!empty($header->from) && isset($header->from[0])) {
      $fromObj = $header->from[0];
      $from = ($fromObj->mailbox ?? '') . '@' . ($fromObj->host ?? '');
    }

    $subject = isset($header->subject) ? imap_utf8($header->subject) : '';
    $date = isset($header->date) ? date('Y-m-d H:i:s', strtotime($header->date)) : null;

    return [
      'from_email' => $from,
      'subject' => $subject,
      'email_date' => $date,
    ];
  }

  public function fetchXmlAttachmentsByUid(int $uid): array {
    // Retorna array de ['filename'=>..., 'content'=>...]
    $msgno = imap_msgno($this->conn, $uid);
    $structure = imap_fetchstructure($this->conn, $msgno);
    if (!$structure) return [];

    $attachments = [];
    $this->walkParts($uid, $msgno, $structure, '', $attachments);

    // Filtra solo XML por nombre o mime
    return array_values(array_filter($attachments, function($a){
      $fn = strtolower($a['filename'] ?? '');
      if (str_ends_with($fn, '.xml')) return true;
      // algunos vienen como application/xml sin extensión
      $ct = strtolower($a['content_type'] ?? '');
      return str_contains($ct, 'xml');
    }));
  }

  private function walkParts(int $uid, int $msgno, $part, string $partNum, array &$attachments): void {
    $isMultipart = isset($part->parts) && is_array($part->parts);

    if ($isMultipart) {
      foreach ($part->parts as $idx => $subPart) {
        $newNum = $partNum === '' ? (string)($idx+1) : ($partNum . '.' . ($idx+1));
        $this->walkParts($uid, $msgno, $subPart, $newNum, $attachments);
      }
      return;
    }

    $params = [];
    if (!empty($part->parameters)) {
      foreach ($part->parameters as $p) $params[strtolower($p->attribute)] = $p->value;
    }
    if (!empty($part->dparameters)) {
      foreach ($part->dparameters as $p) $params[strtolower($p->attribute)] = $p->value;
    }

    $filename = $params['filename'] ?? $params['name'] ?? null;

    $ctypePrimary = $part->type ?? null;
    $subtype = $part->subtype ?? '';
    $contentType = '';
    if ($ctypePrimary !== null) {
      $map = [0=>'text',1=>'multipart',2=>'message',3=>'application',4=>'audio',5=>'image',6=>'video',7=>'other'];
      $contentType = ($map[$ctypePrimary] ?? 'other') . '/' . strtolower($subtype);
    }

    // traer body
    $body = imap_fetchbody($this->conn, $msgno, $partNum ?: 1);
    if ($part->encoding == 3) $body = base64_decode($body);
    elseif ($part->encoding == 4) $body = quoted_printable_decode($body);

    if ($filename || str_contains($contentType, 'xml')) {
      $attachments[] = [
        'filename' => $filename ?: ('attachment_' . $uid . '_' . ($partNum ?: '1') . '.xml'),
        'content' => $body,
        'content_type' => $contentType,
      ];
    }
  }
}