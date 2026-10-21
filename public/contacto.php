<?php
/**
 * Formulario de contacto de hielosdelsur.cl
 * Recibe POST (FormData) desde /contacto y responde JSON {success, message}.
 * Mismo formato que las últimas webs: campo trampa _gotcha, mail() y fetch.
 */
require_once __DIR__ . '/mail-smtp.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
date_default_timezone_set('America/Santiago');

// ===================== CONFIGURACIÓN =====================
const NOTIFY_EMAIL = 'ventas@hielosdelsur.cl, codigoraul@gmail.com'; // por ahora llega a ventas y a Raul; luego dejar solo ventas@hielosdelsur.cl
const DEBUG        = false; // true muestra la causa del error en la respuesta (solo para pruebas)
const SITE_NAME    = 'hielosdelsur.cl';
const RATE_LIMIT   = 5;    // envíos máximos por IP...
const RATE_WINDOW  = 600;  // ...en esta ventana de segundos (10 min)
// =========================================================

function out($success, $message = '', $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
function clean($v, $max) {
    $v = trim((string) $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v);
    return mb_substr($v, 0, $max);
}
function oneLine($v) { return preg_replace('/[\r\n]+/', ' ', $v); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, 'Método no permitido.', 405);

// Solo desde el mismo sitio
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $oh = parse_url($origin, PHP_URL_HOST);
    $h  = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    if ($oh && strcasecmp($oh, $h) !== 0) out(false, 'Solicitud no permitida.', 403);
}

// Campo trampa: los bots lo llenan. Se responde "éxito" sin enviar nada.
if (!empty($_POST['_gotcha'])) out(true, 'Mensaje enviado.');

// Límite por IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$rf = sys_get_temp_dir() . '/hds_contacto_' . md5($ip) . '.json';
$now = time();
$hits = is_file($rf) ? (json_decode((string) @file_get_contents($rf), true) ?: []) : [];
$hits = array_values(array_filter($hits, fn($t) => $t > $now - RATE_WINDOW));
if (count($hits) >= RATE_LIMIT) out(false, 'Has enviado muchos mensajes seguidos. Intenta nuevamente en unos minutos o escríbenos por WhatsApp.', 429);

$nombre   = clean($_POST['nombre']   ?? '', 120);
$empresa  = clean($_POST['empresa']  ?? '', 120);
$email    = clean($_POST['email']    ?? '', 160);
$telefono = clean($_POST['telefono'] ?? '', 40);
$mensaje  = clean($_POST['mensaje']  ?? '', 4000);

if ($nombre === '' || $email === '' || $mensaje === '') out(false, 'Completa nombre, email y mensaje.', 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(false, 'El email no es válido.', 422);

$hits[] = $now;
@file_put_contents($rf, json_encode($hits), LOCK_EX);

$host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? SITE_NAME);
$host = preg_replace('/[^a-z0-9.\-]/i', '', $host);
$host = preg_replace('/^www\./', '', $host);
$subject = 'Formulario de contacto — ' . oneLine($nombre);
$body = "Nuevo mensaje desde el formulario de " . SITE_NAME . "\n"
      . "Fecha: " . date('d-m-Y H:i') . "\n\n"
      . "Nombre:   $nombre\n"
      . "Empresa:  " . ($empresa  ?: '-') . "\n"
      . "Email:    $email\n"
      . "Teléfono: " . ($telefono ?: '-') . "\n\n"
      . "Mensaje:\n$mensaje\n";
$e = function ($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$fila = function ($label, $valor) use ($e) {
    return '<tr><td style="padding:10px 0;border-bottom:1px solid #dde6ee;width:110px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#1f5a8a;vertical-align:top">' . $label
         . '</td><td style="padding:10px 0;border-bottom:1px solid #dde6ee;font-size:15px;color:#2b2622;vertical-align:top">' . $valor . '</td></tr>';
};
$html = '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
  . '<body style="margin:0;padding:0;background:#f3f1ee;font-family:Arial,Helvetica,sans-serif">'
  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f1ee;padding:24px 12px"><tr><td align="center">'
  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden">'
  . '<tr><td style="background:#3a3d42;padding:22px 28px;color:#ffffff"><div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#ffffff"><a href="https://' . $e(SITE_NAME) . '" style="color:#ffffff;text-decoration:none">' . $e(SITE_NAME) . '</a> &middot; Sitio web</div>'
  . '<div style="font-size:21px;font-weight:bold;margin-top:4px">Formulario de contacto</div></td></tr>'
  . '<tr><td style="padding:24px 28px 8px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
  . $fila('Nombre', $e($nombre))
  . $fila('Empresa', $empresa !== '' ? $e($empresa) : '&mdash;')
  . $fila('Email', '<a href="mailto:' . $e($email) . '" style="color:#1f5a8a">' . $e($email) . '</a>')
  . $fila('Teléfono', $telefono !== '' ? '<a href="tel:' . $e(preg_replace('/[^0-9+]/', '', $telefono)) . '" style="color:#1f5a8a">' . $e($telefono) . '</a>' : '&mdash;')
  . '</table></td></tr>'
  . '<tr><td style="padding:8px 28px 4px"><div style="font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#1f5a8a;margin-bottom:8px">Mensaje</div>'
  . '<div style="background:#f1f6fa;border-left:3px solid #1f5a8a;padding:14px 16px;font-size:15px;line-height:1.55;color:#2b2622;white-space:pre-wrap">' . $e($mensaje) . '</div></td></tr>'
  . '<tr><td style="padding:22px 28px 28px"><a href="mailto:' . $e($email) . '?subject=' . rawurlencode('Re: tu consulta en ' . SITE_NAME) . '" style="display:inline-block;background:#1f5a8a;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:6px;font-size:14px;font-weight:bold">Responder a ' . $e($nombre) . '</a></td></tr>'
  . '<tr><td style="background:#faf8f5;padding:14px 28px;font-size:12px;color:#8a8178">Recibido el ' . date('d-m-Y \a \l\a\s H:i') . ' desde el formulario de ' . $e(SITE_NAME) . '. También puedes responder directamente a este correo.</td></tr>'
  . '</table></td></tr></table></body></html>';
$ok = hds_mail(NOTIFY_EMAIL, $subject, $body, oneLine($nombre) . ' <' . oneLine($email) . '>', $html);
$err = $ok ? '' : ($GLOBALS['HDS_MAIL_ERR'] ?: 'falló el envío SMTP');
if (!$ok) {
    error_log('[contacto.php] ' . $err);
    $res = ['success' => false, 'message' => 'No se pudo enviar el mensaje. Intenta nuevamente o escríbenos por WhatsApp.'];
    if (DEBUG) $res['debug'] = $err . ' | php=' . PHP_VERSION;
    http_response_code(500);
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}
out(true, 'Mensaje enviado.');
