<?php
/**
 * Links Broadband — server mail helper (PHPMailer, no SMTP)
 *
 * Hosting providers set up PHP mail differently, so this tries several
 * methods in order and stops at the first one that works:
 *   1. PHPMailer mail() with an envelope sender (-f)
 *   2. PHPMailer mail() without -f  (some hosts reject -f)
 *   3. PHPMailer sendmail binary     (when mail() is disabled or broken)
 *   4. Plain PHP mail() with basic headers
 * Every failure is written to storage/mail-errors.log.
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';

// Small fallbacks for hosts without the mbstring extension
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $length = null, ?string $enc = null): string
    {
        return $length === null ? substr($s, $start) : substr($s, $start, $length);
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s, ?string $enc = null): int
    {
        return strlen($s);
    }
}
if (!function_exists('mb_encode_mimeheader')) {
    function mb_encode_mimeheader(string $s, ?string $charset = null): string
    {
        return '=?UTF-8?B?' . base64_encode($s) . '?=';
    }
}

function lb_sender_address(array $config): string
{
    if (!empty($config['FROM_EMAIL']) && filter_var($config['FROM_EMAIL'], FILTER_VALIDATE_EMAIL)) {
        return $config['FROM_EMAIL'];
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
    $host = strtolower(preg_replace('/:\d+$/', '', $host) ?? '');
    $host = preg_replace('/^www\./', '', $host) ?? '';
    if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}$/', $host)) {
        $host = (string) (gethostname() ?: 'localhost.localdomain');
        if (!str_contains($host, '.')) {
            $host .= '.localdomain';
        }
    }
    return 'no-reply@' . $host;
}

function lb_log(string $line): void
{
    $dir = dirname(__DIR__) . '/storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $stamp = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
    @file_put_contents($dir . '/mail-errors.log', "[{$stamp}] {$line}\n", FILE_APPEND);
    error_log('[Links Broadband] ' . $line);
}

function lb_mail_disabled(): bool
{
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    return !function_exists('mail') || in_array('mail', $disabled, true);
}

/**
 * @param array{to:string,to_name?:string,subject:string,html:string,text:string,reply_to?:string,reply_name?:string} $msg
 * @return array{ok:bool,method:string,errors:array<string,string>}
 */
function lb_send_mail(array $config, array $msg): array
{
    $from     = lb_sender_address($config);
    $fromName = $config['FROM_NAME'] ?? 'Website';
    $errors   = [];

    $build = function (string $mode, bool $useEnvelope) use ($from, $fromName, $msg): PHPMailer {
        $mail = new PHPMailer(true);
        if ($mode === 'sendmail') {
            $mail->isSendmail();
        } else {
            $mail->isMail();
        }
        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->setFrom($from, $fromName, $useEnvelope);
        if (!$useEnvelope) {
            $mail->Sender = '';
        }
        $mail->addAddress($msg['to'], $msg['to_name'] ?? '');
        if (!empty($msg['reply_to'])) {
            $mail->addReplyTo($msg['reply_to'], $msg['reply_name'] ?? '');
        }
        $mail->isHTML(true);
        $mail->Subject = $msg['subject'];
        $mail->Body    = $msg['html'];
        $mail->AltBody = $msg['text'];
        return $mail;
    };

    $mailOff = lb_mail_disabled();
    $attempts = [];
    if (!$mailOff) {
        $attempts['phpmailer-mail-with-f'] = fn () => $build('mail', true);
        $attempts['phpmailer-mail']        = fn () => $build('mail', false);
    } else {
        $errors['mail()'] = 'PHP mail() is disabled on this server (disable_functions).';
    }
    if (function_exists('popen')) {
        $attempts['phpmailer-sendmail'] = fn () => $build('sendmail', false);
    }

    foreach ($attempts as $method => $factory) {
        $mailer = null;
        try {
            $mailer = $factory();
            $mailer->send();
            return ['ok' => true, 'method' => $method, 'errors' => $errors];
        } catch (MailerException $ex) {
            $errors[$method] = ($mailer && $mailer->ErrorInfo) ? $mailer->ErrorInfo : $ex->getMessage();
        } catch (\Throwable $ex) {
            $errors[$method] = get_class($ex) . ': ' . $ex->getMessage();
        }
    }

    // Last resort: plain mail() with minimal headers
    if (!$mailOff) {
        $boundary = 'b' . bin2hex(random_bytes(8));
        $headers  = [
            'MIME-Version: 1.0',
            'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . " <{$from}>",
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        if (!empty($msg['reply_to'])) {
            $headers[] = 'Reply-To: ' . $msg['reply_to'];
        }
        $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($msg['text']))
            . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($msg['html']))
            . "--{$boundary}--";
        $lastError = null;
        set_error_handler(function (int $no, string $str) use (&$lastError) { $lastError = $str; return true; });
        $ok = mail($msg['to'], mb_encode_mimeheader($msg['subject'], 'UTF-8'), $body, implode("\r\n", $headers));
        restore_error_handler();
        if ($ok) {
            return ['ok' => true, 'method' => 'native-mail', 'errors' => $errors];
        }
        $errors['native-mail'] = $lastError ?: 'mail() returned false (the server mail program refused the message).';
    }

    lb_log('Sending to ' . $msg['to'] . ' failed (from ' . $from . '). ' . json_encode($errors, JSON_UNESCAPED_SLASHES));
    return ['ok' => false, 'method' => '', 'errors' => $errors];
}
