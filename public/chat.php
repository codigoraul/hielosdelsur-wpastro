<?php
/**
 * chat.php — Proxy del chat con IA de Chatia (chatbotia.cl) instalado en hielosdelsur.cl.
 *
 * El navegador llama a /chat.php con {message, history, conversationId}.
 * Este archivo agrega las instrucciones del bot y llama a Gemini desde el
 * servidor, así la API key nunca llega al navegador. Además:
 *   - guarda cada mensaje en chat-data/conversaciones/ (historial)
 *   - envía un correo cuando el visitante deja teléfono o email
 *   - cuenta las respuestas del mes y aplica el límite del plan
 *
 * Las instrucciones del bot (lo que sabe) están en chat-prompt.php.
 *
 * La key vive en gemini-key.php (junto a este archivo). Ese archivo NO se sube
 * a git: se sube a mano por FTP. Formatos aceptados en gemini-key.php:
 *   <?php return 'TU_KEY';
 *   <?php define('GEMINI_API_KEY', 'TU_KEY');
 *   <?php $GEMINI_API_KEY = 'TU_KEY';   (o $apiKey / $api_key)
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
date_default_timezone_set('America/Santiago');

// ===================== CONFIGURACIÓN =====================
const GEMINI_MODEL   = 'gemini-3.6-flash';       // modelo principal
const GEMINI_FALLBACK = 'gemini-3.5-flash-lite'; // si el principal falla o demora, se prueba este
const NOTIFY_EMAIL   = 'codigoraul@gmail.com'; // a quién llegan los contactos y avisos (cambiar a ventas@hielosdelsur.cl al entregar)
const SITE_NAME      = 'hielosdelsur.cl';
const MONTHLY_LIMIT  = 1000;  // respuestas de IA al mes (0 = sin límite). Plan Pymes: 1000, Empresas: 2000
const WARN_AT        = 0.8;   // aviso por correo al llegar a este % del límite
const MAX_MSG_LEN    = 500;   // caracteres por mensaje
const MAX_HISTORY    = 8;     // turnos que se envían a la IA
const RATE_LIMIT     = 25;    // mensajes máximos por IP...
const RATE_WINDOW    = 600;   // ...en esta ventana de segundos (10 min)
const WHATSAPP_TXT   = '+56 9 6283 2038'; // WhatsApp de Hielos del Sur
// =========================================================

define('DATA_DIR', __DIR__ . '/chat-data');

function respond($reply, $code = 200) {
    http_response_code($code);
    echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
    exit;
}

// Carpeta de datos protegida (no accesible desde el navegador)
function ensureDataDir() {
    foreach ([DATA_DIR, DATA_DIR . '/conversaciones', DATA_DIR . '/avisados'] as $d) {
        if (!is_dir($d)) @mkdir($d, 0750, true);
    }
    $ht = DATA_DIR . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
    $idx = DATA_DIR . '/index.html';
    if (!is_file($idx)) @file_put_contents($idx, '');
}

function sendMail($subject, $body) {
    $host = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? SITE_NAME);
    $headers = "From: Asistente Hielos del Sur <no-reply@{$host}>\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";
    @mail(NOTIFY_EMAIL, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(null, 405);
}

// Solo peticiones desde el mismo sitio (cuando el navegador envía Origin)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST);
    $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    if ($originHost && strcasecmp($originHost, $host) !== 0) {
        respond(null, 403);
    }
}

// Límite simple de mensajes por IP (anti-abuso)
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = sys_get_temp_dir() . '/hds_rl_' . md5($ip) . '.json';
$now = time();
$hits = [];
if (is_file($rateFile)) {
    $hits = json_decode((string) @file_get_contents($rateFile), true) ?: [];
    $hits = array_values(array_filter($hits, fn($t) => $t > $now - RATE_WINDOW));
}
if (count($hits) >= RATE_LIMIT) {
    respond('Has enviado muchos mensajes seguidos. Espera unos minutos o escríbenos por WhatsApp al ' . WHATSAPP_TXT . '. 🙏');
}
$hits[] = $now;
@file_put_contents($rateFile, json_encode($hits), LOCK_EX);

// Leer la petición
$input = json_decode(file_get_contents('php://input'), true);
$message = trim((string) ($input['message'] ?? ''));
$history = is_array($input['history'] ?? null) ? $input['history'] : [];
$vertical = preg_replace('/[^a-z]/', '', strtolower((string) ($input['vertical'] ?? ''))); // rubro de la landing (ej. abogados)
$convId  = preg_replace('/[^a-zA-Z0-9\-]/', '', (string) ($input['conversationId'] ?? ''));
$convId  = substr($convId !== '' ? $convId : 'sin-id-' . md5($ip . date('Y-m-d')), 0, 64);

if ($message === '') {
    respond(null, 400);
}
if (mb_strlen($message) > MAX_MSG_LEN) {
    $message = mb_substr($message, 0, MAX_MSG_LEN);
}

ensureDataDir();

// Guarda una línea del historial
function logTurn($convId, $role, $text) {
    $line = json_encode([
        't' => date('c'),
        'conv' => $convId,
        'rol' => $role,
        'texto' => $text,
    ], JSON_UNESCAPED_UNICODE) . "\n";
    @file_put_contents(DATA_DIR . '/conversaciones/' . date('Y-m-d') . '.jsonl', $line, FILE_APPEND | LOCK_EX);
}

// Contador mensual de respuestas
$usageFile = DATA_DIR . '/uso-' . date('Y-m') . '.json';
$usage = is_file($usageFile) ? (json_decode((string) @file_get_contents($usageFile), true) ?: []) : [];
$usage += ['respuestas' => 0, 'aviso80' => false, 'avisoLimite' => false];

function saveUsage($file, $usage) {
    @file_put_contents($file, json_encode($usage), LOCK_EX);
}

logTurn($convId, 'visitante', $message);

// ¿El visitante dejó un teléfono o email? -> aviso por correo (una vez por conversación)
$hasPhone = preg_match('/(\+?56\s?)?9[\s\-]?\d{4}[\s\-]?\d{4}|\b\d{8,9}\b/', $message);
$hasEmail = preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $message);
$notifyContact = ($hasPhone || $hasEmail) && !is_file(DATA_DIR . '/avisados/' . $convId);

// Límite del plan alcanzado: no se usa IA, se invita a contactar
if (MONTHLY_LIMIT > 0 && $usage['respuestas'] >= MONTHLY_LIMIT) {
    if (!$usage['avisoLimite']) {
        $usage['avisoLimite'] = true;
        saveUsage($usageFile, $usage);
        sendMail('Chat IA: se alcanzó el límite de respuestas del mes (' . SITE_NAME . ')',
            "El chat de " . SITE_NAME . " llegó a " . MONTHLY_LIMIT . " respuestas este mes.\n"
            . "Desde ahora invita a los visitantes a dejar sus datos o escribir por WhatsApp hasta el próximo mes.");
    }
    $reply = 'Gracias por escribir 😊 En este momento no puedo responder de forma automática. '
           . 'Déjame tu nombre y teléfono aquí y te contactamos, o escríbenos por WhatsApp al ' . WHATSAPP_TXT . '.';
    logTurn($convId, 'bot', $reply);
    if ($notifyContact) {
        @touch(DATA_DIR . '/avisados/' . $convId);
        sendMail('Nuevo contacto desde el chat de ' . SITE_NAME, "Mensaje del visitante:\n\n" . $message);
    }
    respond($reply);
}

// Cargar la API key
$apiKey = '';
$keyFile = __DIR__ . '/gemini-key.php';
if (is_file($keyFile)) {
    $ret = include $keyFile;
    if (is_string($ret) && $ret !== '') {
        $apiKey = $ret;
    } elseif (defined('GEMINI_API_KEY')) {
        $apiKey = constant('GEMINI_API_KEY');
    } elseif (!empty($GEMINI_API_KEY)) {
        $apiKey = $GEMINI_API_KEY;
    } elseif (!empty($apiKey)) {
        // ya quedó definida por el include
    } elseif (!empty($api_key)) {
        $apiKey = $api_key;
    }
}
if ($apiKey === '') {
    respond(null); // el front-end muestra el mensaje de respaldo
}

// Instrucciones del bot
$systemPrompt = @include __DIR__ . '/chat-prompt.php';
if (!is_string($systemPrompt) || $systemPrompt === '') {
    $systemPrompt = 'Eres el asistente virtual de Hielos del Sur. Responde breve en español de Chile.';
}

// Landing por rubro: si existe chat-prompt-<rubro>.php, se agrega como contexto extra
if ($vertical !== '' && is_file(__DIR__ . '/chat-prompt-' . $vertical . '.php')) {
    $extra = @include __DIR__ . '/chat-prompt-' . $vertical . '.php';
    if (is_string($extra) && $extra !== '') {
        $systemPrompt .= "\n\n" . $extra;
    }
}

// Armar la conversación en formato Gemini
$contents = [];
foreach (array_slice($history, -MAX_HISTORY) as $turn) {
    $text = trim((string) ($turn['text'] ?? ''));
    if ($text === '') continue;
    $role = ($turn['role'] ?? '') === 'model' ? 'model' : 'user';
    $contents[] = ['role' => $role, 'parts' => [['text' => mb_substr($text, 0, 2000)]]];
}
// El front-end ya incluye el mensaje actual en el historial; si no, se agrega.
$last = end($contents);
if (!$last || $last['role'] !== 'user' || $last['parts'][0]['text'] !== $message) {
    $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];
}

$payload = [
    'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
    'contents' => $contents,
    'generationConfig' => [
        'temperature' => 0.6,
        'maxOutputTokens' => 1024,
        'thinkingConfig' => ['thinkingBudget' => 0],
    ],
];

function callGemini($apiKey, $payload, $model = GEMINI_MODEL, $timeout = 20) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => $timeout,
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    if ($raw === false || $code !== 200) {
        error_log('[chat-hielosdelsur] Gemini ' . $model . ' fallo http=' . $code . ' curl=' . $cerr . ' resp=' . substr((string) $raw, 0, 200));
        return null;
    }
    $data = json_decode($raw, true);
    $parts = $data['candidates'][0]['content']['parts'] ?? [];
    $text = '';
    foreach ($parts as $p) {
        if (!empty($p['text']) && empty($p['thought'])) $text .= $p['text'];
    }
    $text = trim($text);
    return $text !== '' ? $text : null;
}

// Un reintento interno: la capa gratuita de Gemini a veces falla por sobrecarga
$reply = callGemini($apiKey, $payload);
if ($reply === null) {
    $reply = callGemini($apiKey, $payload, GEMINI_FALLBACK, 15);
}

if ($reply !== null) {
    logTurn($convId, 'bot', $reply);

    // Contador y aviso al 80 %
    $usage['respuestas']++;
    if (MONTHLY_LIMIT > 0 && !$usage['aviso80'] && $usage['respuestas'] >= MONTHLY_LIMIT * WARN_AT) {
        $usage['aviso80'] = true;
        sendMail('Chat IA: ' . $usage['respuestas'] . ' de ' . MONTHLY_LIMIT . ' respuestas usadas (' . SITE_NAME . ')',
            "El chat de " . SITE_NAME . " ya usó " . $usage['respuestas'] . " de " . MONTHLY_LIMIT . " respuestas este mes.");
    }
    saveUsage($usageFile, $usage);
}

// Aviso de contacto con la conversación completa
if ($notifyContact) {
    @touch(DATA_DIR . '/avisados/' . $convId);
    $lines = [];
    foreach ($history as $turn) {
        $who = (($turn['role'] ?? '') === 'model') ? 'Asistente' : 'Visitante';
        $lines[] = $who . ': ' . trim((string) ($turn['text'] ?? ''));
    }
    if (!$history || end($history)['text'] !== $message) $lines[] = 'Visitante: ' . $message;
    if ($reply !== null) $lines[] = 'Asistente: ' . $reply;
    sendMail('Nuevo contacto desde el chat de ' . SITE_NAME,
        "Un visitante dejó sus datos en el chat.\n\nConversación:\n\n" . implode("\n\n", $lines)
        . "\n\n---\nFecha: " . date('d-m-Y H:i'));
}

respond($reply);
