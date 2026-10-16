<?php
/**
 * Formulario de contacto de hielosdelsur.cl
 * Recibe POST (FormData) desde /contacto y responde JSON {success, message}.
 * Mismo formato que las últimas webs: campo trampa _gotcha, mail() y fetch.
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
date_default_timezone_set('America/Santiago');

// ===================== CONFIGURACIÓN =====================
const NOTIFY_EMAIL = 'codigoraul@gmail.com'; // PRUEBA: solo Raul. Al terminar, cambiar a 'ventas@hielosdelsur.cl, codigoraul@gmail.com' o solo ventas
const DEBUG        = true; // TEMPORAL: muestra la causa del error en la respuesta. Poner en false al terminar las pruebas.
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
$subject = 'Nuevo mensaje de contacto — ' . oneLine($nombre);
$body = "Nuevo mensaje desde el formulario de " . SITE_NAME . "\n"
      . "Fecha: " . date('d-m-Y H:i') . "\n\n"
      . "Nombre:   $nombre\n"
      . "Empresa:  " . ($empresa  ?: '-') . "\n"
      . "Email:    $email\n"
      . "Teléfono: " . ($telefono ?: '-') . "\n\n"
      . "Mensaje:\n$mensaje\n";
$headers = "From: " . SITE_NAME . " <no-reply@{$host}>\r\n"
         . "Reply-To: " . oneLine($nombre) . " <" . oneLine($email) . ">\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n";

$encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
// 1) con remitente de sobre (-f) del dominio del sitio, que es lo que exigen la mayoría de los hostings cPanel
$ok = @mail(NOTIFY_EMAIL, $encSubject, $body, $headers, '-fno-reply@' . $host);
$err = $ok ? '' : (error_get_last()['message'] ?? 'mail() devolvió false');
// 2) si falla, se reintenta sin -f
if (!$ok) {
    $ok = @mail(NOTIFY_EMAIL, $encSubject, $body, $headers);
    if (!$ok) $err .= ' | sin -f: ' . (error_get_last()['message'] ?? 'mail() devolvió false');
}
if (!$ok) {
    error_log('[contacto.php] ' . $err . ' | disable_functions=' . ini_get('disable_functions') . ' | sendmail_path=' . ini_get('sendmail_path'));
    $res = ['success' => false, 'message' => 'No se pudo enviar el mensaje. Intenta nuevamente o escríbenos por WhatsApp.'];
    if (DEBUG) $res['debug'] = $err . ' | disable_functions=' . ini_get('disable_functions') . ' | sendmail_path=' . ini_get('sendmail_path') . ' | php=' . PHP_VERSION;
    http_response_code(500);
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}
out(true, 'Mensaje enviado.');
