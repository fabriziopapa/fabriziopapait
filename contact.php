<?php
/**
 * contact.php â€” gestore del form contatti per fabriziopapa.it
 * Versione SMTP autenticato (PHPMailer) â€” invio da info@fabriziopapa.it
 *
 * PREREQUISITO: carica PHPMailer nella cartella /phpmailer accanto a questo file.
 *   Scarica l'ultima release da https://github.com/PHPMailer/PHPMailer/releases
 *   Servono solo 3 file dalla cartella src/:
 *     phpmailer/PHPMailer.php
 *     phpmailer/SMTP.php
 *     phpmailer/Exception.php
 *
 * >>> CONFIGURAZIONE <<<
 */
$SMTP_HOST  = 'localhost';
$SMTP_PORT  = 25;                           // 25 = exim locale, niente TLS
$SMTP_USER  = 'info@fabriziopapa.it';


require __DIR__ . '/config.local.php';
$DEST_EMAIL = 'info@fabriziopapa.it';       // dove ricevi i messaggi
$FROM_EMAIL = 'info@fabriziopapa.it';       // mittente (deve coincidere con SMTP_USER)
$FROM_NAME  = 'Sito fabriziopapa.it';
$DEBUG_SMTP = false;                            // true SOLO in fase di test: logga il dialogo SMTP completo
$LOG_FILE   = __DIR__ . '/contact-errors.log';  // log dedicato errori (proteggilo, vedi README in coda)

$FRIENDLY_CAPTCHA_SITEKEY = 'FCMUGJVQ8E5KHCUN'; // â† la tua sitekey
/* ---------------------------------------------------------------- */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function out($ok, $msg, $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'msg' => $msg]);
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(false, 'Metodo non consentito.', 405);
}

// Honeypot: se il campo nascosto "website" Ã¨ compilato, Ã¨ un bot.
// Rispondiamo "ok" senza inviare nulla, cosÃ¬ il bot non impara.
if (!empty($_POST['website'] ?? '')) {
    out(true, 'Grazie, messaggio inviato.');
}

// Raccolta e pulizia input
$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$message = trim($_POST['message'] ?? '');

// Validazione
if ($name === '' || $email === '' || $message === '') {
    out(false, 'Compila tutti i campi.', 422);
}
if (mb_strlen($name) > 100 || mb_strlen($email) > 200 || mb_strlen($message) > 5000) {
    out(false, 'Uno dei campi supera la lunghezza massima.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    out(false, 'Indirizzo email non valido.', 422);
}
// Protezione header injection: nessun CR/LF nei campi che finiscono negli header
foreach ([$name, $email] as $field) {
    if (preg_match('/[\r\n]/', $field)) {
        out(false, 'Input non valido.', 422);
    }
}

// Rate limiting minimale per sessione (anti-flood)
session_start();
$now = time();
if (isset($_SESSION['last_send']) && ($now - $_SESSION['last_send']) < 30) {
    out(false, 'Attendi qualche istante prima di inviare di nuovo.', 429);
}

// --- Verifica anti-bot (Friendly Captcha oppure Turnstile) ---
$fcResponse = $_POST['frc-captcha-response'] ?? '';
$tsToken    = $_POST['cf-turnstile-response'] ?? '';

// Se nessuno dei due token Ã¨ presente â†’ errore
if ($fcResponse === '' && $tsToken === '') {
    out(false, 'Verifica anti-bot mancante. Ricarica la pagina e riprova.', 422);
}

$verified = false;

// Prova prima Friendly Captcha
if ($fcResponse !== '') {
    $ch = curl_init('https://global.frcapi.com/api/v2/captcha/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'response' => $fcResponse,
            'sitekey'  => $FRIENDLY_CAPTCHA_SITEKEY,
        ]),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-Key: ' . $FRIENDLY_CAPTCHA_API_KEY,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $fcRaw  = curl_exec($ch);
    $fcErr  = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($fcRaw !== false) {
        $fcRes = json_decode($fcRaw, true);
        $verified = is_array($fcRes) && !empty($fcRes['success']);
        if (!$verified) {
            $errorInfo = is_array($fcRes) ? ($fcRes['error']['error_code'] ?? 'unknown') : 'invalid json';
            @file_put_contents($LOG_FILE, '[' . date('Y-m-d H:i:s') . '] FriendlyCaptcha respinto da IP '
                . ($_SERVER['REMOTE_ADDR'] ?? 'n/d') . ': ' . $errorInfo . ' (HTTP ' . $httpCode . ")\n", FILE_APPEND | LOCK_EX);
        }
    } else {
        @file_put_contents($LOG_FILE, '[' . date('Y-m-d H:i:s') . '] FriendlyCaptcha curl error da IP '
            . ($_SERVER['REMOTE_ADDR'] ?? 'n/d') . ': ' . $fcErr . "\n", FILE_APPEND | LOCK_EX);
        // Se l'API non risponde, accetta comunque (fail open)
        $verified = true;
    }
}

// Se Friendly Captcha non ha verificato, prova Turnstile
if (!$verified && $tsToken !== '') {
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => $TURNSTILE_SECRET,
            'response' => $tsToken,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $tsRaw  = curl_exec($ch);
    $tsErr  = curl_error($ch);
    curl_close($ch);
    $tsRes = $tsRaw !== false ? json_decode($tsRaw, true) : null;
    $verified = is_array($tsRes) && !empty($tsRes['success']);

    if (!$verified) {
        $codes = is_array($tsRes) ? implode(',', $tsRes['error-codes'] ?? []) : ('curl: ' . $tsErr);
        @file_put_contents($LOG_FILE, '[' . date('Y-m-d H:i:s') . '] Turnstile respinto da IP '
            . ($_SERVER['REMOTE_ADDR'] ?? 'n/d') . ': ' . $codes . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!$verified) {
    out(false, 'Verifica anti-bot non superata. Riprova.', 403);
}
// --- Fine verifica anti-bot combinata ---

// Se Friendly Captcha non ha verificato, prova Turnstile
if (!$verified && $tsToken !== '') {
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => $TURNSTILE_SECRET,
            'response' => $tsToken,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $tsRaw  = curl_exec($ch);
    $tsErr  = curl_error($ch);
    curl_close($ch);
    $tsRes = $tsRaw !== false ? json_decode($tsRaw, true) : null;
    $verified = is_array($tsRes) && !empty($tsRes['success']);

    if (!$verified) {
        $codes = is_array($tsRes) ? implode(',', $tsRes['error-codes'] ?? []) : ('curl: ' . $tsErr);
        @file_put_contents($LOG_FILE, '[' . date('Y-m-d H:i:s') . '] Turnstile respinto da IP '
            . ($_SERVER['REMOTE_ADDR'] ?? 'n/d') . ': ' . $codes . "\n", FILE_APPEND | LOCK_EX);
    }
}

// Se nessuno dei due ha verificato â†’ stop
if (!$verified) {
    out(false, 'Verifica anti-bot non superata. Riprova.', 403);
}
// --- Fine verifica anti-bot combinata ---

// PHPMailer
require __DIR__ . '/phpmailer/Exception.php';
require __DIR__ . '/phpmailer/PHPMailer.php';
require __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

// Logger dedicato: righe con timestamp in contact-errors.log
$logger = function ($msg) use ($LOG_FILE) {
    @file_put_contents($LOG_FILE, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
};

try {
    $mail->isSMTP();
    $mail->setLanguage('it', __DIR__ . '/phpmailer/language/'); // errori PHPMailer in italiano nel log
    if ($DEBUG_SMTP) {
        $mail->SMTPDebug   = SMTP::DEBUG_SERVER; // logga comandi e risposte del server
        $mail->Debugoutput = function ($str) use ($logger) { $logger('SMTP: ' . trim($str)); };
    }
    $mail->Host       = $SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = $SMTP_USER;
    $mail->Password   = $SMTP_PASS;
    $mail->SMTPSecure  = false;              // connessione locale, mai esce dalla macchina
    $mail->SMTPAutoTLS = false;
    $mail->Port        = $SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    // Mittente = casella autenticata (necessario per SPF/DKIM/DMARC)
    $mail->setFrom($FROM_EMAIL, $FROM_NAME);
    $mail->addAddress($DEST_EMAIL);
    // Il visitatore va in Reply-To, MAI come From (sarebbe spoofing)
    $mail->addReplyTo($email, $name);

    $mail->Subject = 'Nuovo messaggio dal sito â€” ' . $name;
    $mail->Body    = "Nome: {$name}\n"
                   . "Email: {$email}\n"
                   . "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'n/d') . "\n"
                   . "Data: " . date('Y-m-d H:i:s') . "\n"
                   . str_repeat('-', 40) . "\n\n"
                   . $message;

    $mail->send();
    $_SESSION['last_send'] = $now;

    // Accoda una riga per il digest giornaliero (file FUORI da public_html)
    $queueLine = json_encode([
        'ts'    => date('Y-m-d H:i:s'),
        'name'  => $name,
        'email' => $email,
        'ip'    => $_SERVER['REMOTE_ADDR'] ?? 'n/d',
        'snip'  => mb_substr($message, 0, 120),
    ], JSON_UNESCAPED_UNICODE);
    @file_put_contents(dirname(__DIR__) . '/contact-queue.jsonl', $queueLine . "\n", FILE_APPEND | LOCK_EX);

    out(true, 'Grazie, messaggio inviato. Ti rispondo presto.');

} catch (Exception $e) {
    // Non esporre dettagli tecnici al client: log interno, risposta generica
    $detail = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
    $logger('ERRORE invio da IP ' . ($_SERVER['REMOTE_ADDR'] ?? 'n/d') . ': ' . $detail);
    error_log('contact.php SMTP error: ' . $detail);
    out(false, 'Invio non riuscito. Riprova piÃ¹ tardi o scrivimi su LinkedIn.', 500);
}

/*
 * PROTEZIONE DEL LOG â€” aggiungi al tuo .htaccess (o crea un blocco apposito):
 *
 *   <Files "contact-errors.log">
 *     Require all denied
 *   </Files>
 *
 * Senza questa regola il log sarebbe scaricabile da chiunque via browser.
 */
