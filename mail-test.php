<?php
/**
 * Links Broadband — mail diagnostic page
 * Open: https://yourdomain.com/mail-test.php?key=TEST_KEY  (key is in config.php)
 * Delete this file once emails are arriving.
 */
declare(strict_types=1);

$config = require __DIR__ . '/config.php';
require __DIR__ . '/lib/mailer.php';

if (empty($config['TEST_KEY']) || !hash_equals((string) $config['TEST_KEY'], (string) ($_GET['key'] ?? ''))) {
    http_response_code(404);
    exit('Not found');
}

header('Content-Type: text/plain; charset=utf-8');

$yes = fn (bool $b) => $b ? 'yes' : 'NO';
echo "Links Broadband - mail test\n===========================\n\n";
echo 'PHP version        : ' . PHP_VERSION . "\n";
echo 'Host (HTTP_HOST)   : ' . ($_SERVER['HTTP_HOST'] ?? '-') . "\n";
echo 'Sender address     : ' . lb_sender_address($config) . "\n";
echo 'Recipient          : ' . $config['LEAD_RECIPIENT'] . "\n";
echo 'mail() available   : ' . $yes(!lb_mail_disabled()) . "\n";
echo 'popen() available  : ' . $yes(function_exists('popen')) . "\n";
echo 'escapeshellcmd()   : ' . $yes(function_exists('escapeshellcmd')) . "\n";
echo 'mbstring loaded    : ' . $yes(extension_loaded('mbstring')) . "\n";
echo 'sendmail_path      : ' . (ini_get('sendmail_path') ?: '-') . "\n";
echo 'sendmail_from      : ' . (ini_get('sendmail_from') ?: '-') . "\n";
echo 'disable_functions  : ' . (ini_get('disable_functions') ?: '-') . "\n";
echo 'storage writable   : ' . $yes(is_writable(__DIR__ . '/storage')) . "\n\n";

$result = lb_send_mail($config, [
    'to'      => $config['LEAD_RECIPIENT'],
    'subject' => 'Links Broadband mail test ' . date('H:i:s'),
    'html'    => '<p>This is a test email from the Links Broadband website. If you can read this, email sending works.</p>',
    'text'    => 'This is a test email from the Links Broadband website. If you can read this, email sending works.',
]);

if ($result['ok']) {
    echo "RESULT: SENT using method \"{$result['method']}\".\n";
    echo "Check {$config['LEAD_RECIPIENT']} (and the Spam folder) in 1-5 minutes.\n";
} else {
    echo "RESULT: FAILED - every method was refused by the server.\n";
    if (str_contains((string) ini_get('sendmail_path'), 'hsendmail')) {
        $from = lb_sender_address($config);
        echo "\nHOSTINGER: the sender address must be a real mailbox on this domain.\n";
        echo "  1. hPanel > Emails > create the mailbox {$from} (or use one you already have).\n";
        echo "  2. Put that exact address in FROM_EMAIL in config.php.\n";
        echo "  3. Reload this page.\n";
    }
}
if ($result['errors']) {
    echo "\nErrors from methods that did not work:\n";
    foreach ($result['errors'] as $method => $err) {
        echo " - {$method}: {$err}\n";
    }
}
