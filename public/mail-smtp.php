<?php
/**
 * mail-smtp.php — Envío de correo por SMTP autenticado (reemplaza a mail(), desactivado en el hosting).
 * Uso: hds_mail('a@x.cl, b@y.cl', 'Asunto', "Cuerpo", 'Nombre <responder@a.cl>' (el último es opcional));
 * Devuelve true/false; el motivo del fallo queda en $GLOBALS['HDS_MAIL_ERR'].
 * Las credenciales viven en smtp-config.php (no se sube a git; se completa en el servidor).
 */
if (!function_exists('hds_mail')) {
function hds_smtp_read($fp) {
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    return $data;
}
function hds_smtp_cmd($fp, $cmd, $expect) {
    if ($cmd !== null) fwrite($fp, $cmd . "\r\n");
    $r = hds_smtp_read($fp);
    if (strpos($r, (string) $expect) !== 0) throw new Exception(trim($r) ?: 'sin respuesta del servidor SMTP');
    return $r;
}
function hds_mail($to, $subject, $body, $replyTo = '', $html = '') {
    $GLOBALS['HDS_MAIL_ERR'] = '';
    $cfgFile = null;
    foreach ([__DIR__ . '/../smtp-config.php', __DIR__ . '/smtp-config.php'] as $f) { if (is_file($f)) { $cfgFile = $f; break; } }
    $cfg = $cfgFile ? include $cfgFile : null;
    if (!is_array($cfg) || empty($cfg['user']) || empty($cfg['pass']) || $cfg['pass'] === 'PON_AQUI_LA_CONTRASENA') {
        $GLOBALS['HDS_MAIL_ERR'] = 'smtp-config.php no existe o falta la contraseña';
        return false;
    }
    $host = $cfg['host'] ?? 'localhost';
    $port = (int) ($cfg['port'] ?? 465);
    $fromName = $cfg['from_name'] ?? 'hielosdelsur.cl';
    $rcpts = array_values(array_filter(array_map('trim', explode(',', $to))));
    $fp = null;
    try {
        $ctx = stream_context_create(['ssl' => [
            'verify_peer' => $cfg['verify'] ?? true,
            'verify_peer_name' => $cfg['verify'] ?? true,
        ]]);
        $proto = $port === 465 ? 'ssl' : 'tcp';
        $fp = @stream_socket_client("$proto://$host:$port", $en, $es, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) throw new Exception("no se pudo conectar a $host:$port ($es)");
        stream_set_timeout($fp, 15);
        hds_smtp_cmd($fp, null, 220);
        $ehlo = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost') ?: 'localhost';
        hds_smtp_cmd($fp, "EHLO $ehlo", 250);
        if ($port === 587) {
            hds_smtp_cmd($fp, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new Exception('falló STARTTLS');
            hds_smtp_cmd($fp, "EHLO $ehlo", 250);
        }
        hds_smtp_cmd($fp, 'AUTH LOGIN', 334);
        hds_smtp_cmd($fp, base64_encode($cfg['user']), 334);
        hds_smtp_cmd($fp, base64_encode($cfg['pass']), 235);
        hds_smtp_cmd($fp, 'MAIL FROM:<' . $cfg['user'] . '>', 250);
        foreach ($rcpts as $r) {
            $addr = preg_match('/<([^>]+)>/', $r, $m) ? $m[1] : $r;
            hds_smtp_cmd($fp, 'RCPT TO:<' . $addr . '>', 250);
        }
        hds_smtp_cmd($fp, 'DATA', 354);
        $oneLine = function ($v) { return preg_replace('/[\r\n]+/', ' ', $v); };
        $h  = 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $cfg['user'] . ">\r\n";
        $h .= 'To: ' . $oneLine($to) . "\r\n";
        if ($replyTo !== '') $h .= 'Reply-To: ' . $oneLine($replyTo) . "\r\n";
        $h .= 'Subject: =?UTF-8?B?' . base64_encode($oneLine($subject)) . "?=\r\n";
        $h .= 'Date: ' . date('r') . "\r\n";
        $h .= 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . (substr(strrchr($cfg['user'], '@'), 1) ?: 'localhost') . ">\r\n";
        $b64 = function ($t) { return chunk_split(base64_encode($t), 76, "\r\n"); };
        if ($html !== '') {
            $bd = 'hds' . bin2hex(random_bytes(8));
            $h .= "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$bd\"\r\n\r\n";
            $h .= "--$bd\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64($body);
            $h .= "--$bd\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64($html);
            $h .= "--$bd--\r\n";
        } else {
            $h .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64($body);
        }
        fwrite($fp, $h . "\r\n.\r\n");
        $r = hds_smtp_read($fp);
        if (strpos($r, '250') !== 0) throw new Exception(trim($r));
        @fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        $GLOBALS['HDS_MAIL_ERR'] = $e->getMessage();
        if (is_resource($fp)) @fclose($fp);
        return false;
    }
}
}
