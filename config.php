<?php
/**
 * Links Broadband — mail configuration
 * ------------------------------------------------------------
 * Emails are sent through the hosting server's own mail function
 * (PHPMailer using PHP mail() / sendmail). No SMTP login is needed.
 *
 * FROM_EMAIL: use an address on your website's domain, for example
 * no-reply@linksbroadband.in. If it is left empty, "no-reply@<your domain>"
 * is used automatically. Do NOT use a @gmail.com address here, because
 * Gmail marks mail as spam (or rejects it) when a @gmail.com sender is
 * sent from another server.
 */

return [
    // Where new leads are delivered
    'LEAD_RECIPIENT'      => 'digicarelynx@gmail.com',
    'LEAD_RECIPIENT_NAME' => 'Links Broadband',

    // Hostinger: FROM_EMAIL must be a mailbox that exists in hPanel > Emails
    // "From" identity (leave FROM_EMAIL empty to use no-reply@<your domain>)
    'FROM_EMAIL' => 'no-reply@linksbroadband.com',
    'FROM_NAME'  => 'Links Broadband Website',

    // Password of the FROM_EMAIL mailbox (hPanel > Emails > mailbox > Change password).
    // Hostinger's server mail() refuses mail on this account, so the site sends
    // through this mailbox instead. It is only used on the server and never shown.
    'MAILBOX_PASSWORD' => '',
    'MAILBOX_HOST'     => 'smtp.hostinger.com',
    'MAILBOX_PORT'     => 465,

    // Send a thank-you email to the customer when they give an email address
    'SEND_AUTOREPLY' => true,

    // Keep a CSV backup of every lead in /storage/leads.csv
    'SAVE_LEADS_CSV' => true,

    // Secret key for the mail test page: /mail-test.php?key=THIS_VALUE
    // Delete mail-test.php once email is working.
    'TEST_KEY' => '48132a0a6187',

    // Minimum seconds between two submissions from the same visitor
    'RATE_LIMIT_SECONDS' => 30,
];
