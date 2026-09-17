<?php
/**
 * Links Broadband — lead form handler (PHPMailer)
 * Accepts POST from the website forms and returns JSON.
 */

declare(strict_types=1);

require __DIR__ . '/lib/mailer.php';

$config = require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond(bool $ok, string $message, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

function clean(string $key, int $max = 200): string
{
    $value = isset($_POST[$key]) ? (string) $_POST[$key] : '';
    $value = trim(strip_tags($value));
    $value = preg_replace('/[\r\n]+/', ' ', $value) ?? '';   // no header injection
    return mb_substr($value, 0, $max);
}

function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed.', 405);
}

session_start();

// ---- Spam protection -------------------------------------------------------
// 1. Honeypot field must stay empty
if (!empty($_POST['website'])) {
    respond(true, 'Thank you! Our team will call you shortly.');
}
// 2. Humans take at least 3 seconds to fill the form
$startedAt = (int) ($_POST['started_at'] ?? 0);
if ($startedAt > 0 && (time() * 1000 - $startedAt) < 3000) {
    respond(false, 'That was quick! Please review your details and try again.', 429);
}
// 3. Per-session rate limit
$last = $_SESSION['last_lead_at'] ?? 0;
if (time() - $last < (int) $config['RATE_LIMIT_SECONDS']) {
    respond(false, 'We already received your request. Please wait a moment before sending another.', 429);
}

// ---- Collect & validate ----------------------------------------------------
$name     = clean('name', 80);
$phone    = preg_replace('/[^\d+]/', '', clean('phone', 20)) ?? '';
$email    = clean('email', 120);
$area     = clean('area', 120);
$plan     = clean('plan', 80);
$duration = clean('duration', 40);
$type     = clean('connection_type', 40);
$message  = isset($_POST['message']) ? mb_substr(trim(strip_tags((string) $_POST['message'])), 0, 1000) : '';
$source   = clean('form_source', 60) ?: 'Website';

$errors = [];
if (mb_strlen($name) < 2) {
    $errors[] = 'Please enter your name.';
}
$digits = preg_replace('/\D/', '', $phone) ?? '';
if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
    $digits = substr($digits, 2);
}
if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
    $digits = substr($digits, 1);
}
if (!preg_match('/^[6-9]\d{9}$/', $digits)) {
    $errors[] = 'Please enter a valid 10-digit mobile number.';
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address or leave it blank.';
}
if ($errors) {
    respond(false, implode(' ', $errors), 422);
}

$phoneDisplay = '+91 ' . substr($digits, 0, 5) . ' ' . substr($digits, 5);
$submittedAt  = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('d M Y, h:i A');
$ip           = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// ---- CSV backup (so no lead is lost even if mail fails) --------------------
if (!empty($config['SAVE_LEADS_CSV'])) {
    $dir = __DIR__ . '/storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file  = $dir . '/leads.csv';
    $isNew = !file_exists($file);
    if ($fh = @fopen($file, 'a')) {
        if ($isNew) {
            fputcsv($fh, ['Date', 'Name', 'Phone', 'Email', 'Area', 'Type', 'Plan', 'Duration', 'Message', 'Source', 'IP'], ',', '"', '\\');
        }
        fputcsv($fh, [$submittedAt, $name, $phoneDisplay, $email, $area, $type, $plan, $duration, $message, $source, $ip], ',', '"', '\\');
        fclose($fh);
    }
}

// ---- Build email -----------------------------------------------------------
$rows = [
    'Name'            => $name,
    'Mobile'          => $phoneDisplay,
    'Email'           => $email ?: '—',
    'Area / Address'  => $area ?: '—',
    'Connection Type' => $type ?: '—',
    'Plan'            => $plan ?: 'Not selected',
    'Billing'         => $duration ?: '—',
    'Message'         => $message ?: '—',
    'Form'            => $source,
    'Submitted'       => $submittedAt . ' IST',
];

$tableRows = '';
foreach ($rows as $label => $value) {
    $tableRows .= '<tr><td style="padding:10px 14px;background:#f3f8fb;font-weight:600;color:#0b3d5c;width:150px;border-bottom:1px solid #e3edf3">'
        . e($label) . '</td><td style="padding:10px 14px;color:#1a2a33;border-bottom:1px solid #e3edf3">'
        . nl2br(e($value)) . '</td></tr>';
}

$safeName = e($name);
$waLink  = 'https://wa.me/91' . $digits;
$telLink = 'tel:+91' . $digits;

$html = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;background:#eef4f7;padding:24px">
  <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #dce8ee">
    <div style="background:linear-gradient(120deg,#0a8fd0,#6dbb1f);padding:22px 26px;color:#fff">
      <div style="font-size:13px;letter-spacing:1px;text-transform:uppercase;opacity:.9">New website lead</div>
      <div style="font-size:22px;font-weight:700;margin-top:4px">{$safeName} wants a connection</div>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:14px">{$tableRows}</table>
    <div style="padding:20px 26px">
      <a href="{$telLink}" style="display:inline-block;background:#0a8fd0;color:#fff;text-decoration:none;padding:11px 18px;border-radius:8px;font-weight:700;margin-right:8px">Call customer</a>
      <a href="{$waLink}" style="display:inline-block;background:#25d366;color:#fff;text-decoration:none;padding:11px 18px;border-radius:8px;font-weight:700">WhatsApp</a>
    </div>
  </div>
</div>
HTML;

$text = "New website lead — Links Broadband\n\n";
foreach ($rows as $label => $value) {
    $text .= str_pad($label . ':', 18) . $value . "\n";
}

// ---- Send (server mail via PHPMailer, see lib/mailer.php) ------------------
$result = lb_send_mail($config, [
    'to'         => $config['LEAD_RECIPIENT'],
    'to_name'    => $config['LEAD_RECIPIENT_NAME'],
    'bcc'        => $config['LEAD_BCC'] ?? [],
    'subject'    => 'New Lead: ' . $name . ' (' . $phoneDisplay . ')' . ($plan ? ' - ' . $plan : ''),
    'html'       => $html,
    'text'       => $text,
    'reply_to'   => $email,
    'reply_name' => $name,
]);

if (!$result['ok']) {
    respond(false, 'We could not send your request right now. Please call or WhatsApp us on 93930 50511.', 500);
}

$_SESSION['last_lead_at'] = time();

// ---- Optional auto-reply to the customer -----------------------------------
if (!empty($config['SEND_AUTOREPLY']) && $email !== '') {
    $planLine = $plan ? '<p style="margin:0 0 12px">Plan you are interested in: <strong>' . e($plan) . ($duration ? ' - ' . e($duration) : '') . '</strong></p>' : '';
    lb_send_mail($config, [
        'to'      => $email,
        'to_name' => $name,
        'subject' => 'Thanks for choosing Links Broadband!',
        'html'    => '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#1a2a33">'
            . '<div style="background:#0a8fd0;background:linear-gradient(120deg,#0a8fd0,#6dbb1f);color:#fff;padding:22px 26px;border-radius:12px 12px 0 0"><div style="font-size:22px;font-weight:700">Links Broadband</div><div style="opacity:.9">Connects Together...</div></div>'
            . '<div style="border:1px solid #dce8ee;border-top:0;padding:22px 26px;border-radius:0 0 12px 12px">'
            . '<p style="margin:0 0 12px">Hi ' . e($name) . ',</p>'
            . '<p style="margin:0 0 12px">Thank you for your interest in Links Broadband. We have received your request and our team will call you on <strong>' . e($phoneDisplay) . '</strong> shortly.</p>'
            . $planLine
            . '<p style="margin:0 0 12px">Please keep these documents ready for installation: Address Proof, ID Proof and a Passport Size Photo.</p>'
            . '<p style="margin:0">Need us sooner? Call or WhatsApp <a href="tel:+919393050511">93930 50511</a>.</p>'
            . '</div></div>',
        'text'    => "Hi {$name},\n\nThank you for your interest in Links Broadband. Our team will call you on {$phoneDisplay} shortly.\n\nCall / WhatsApp: 93930 50511",
    ]);
}

respond(true, 'Thank you, ' . $name . '! Our team will call you shortly.');
