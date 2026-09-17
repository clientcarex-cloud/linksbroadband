# Links Broadband — Website

A one-page website built to get leads, with a PHPMailer lead form that sends to **digicarelynx@gmail.com**.

## Files
```
index.html            Main page (hero form, plans, speed guide, why us, steps, FAQ, contact form, map)
assets/css/style.css  Styles
assets/js/main.js     Plan prices and toggles, plan pop-up, AJAX form sending
assets/img/           Logo, poster, plan flyer (taken from the original images)
send-mail.php         Checks the form and sends the email with PHPMailer using the server's mail() (returns JSON)
config.php            Who receives leads, sender address, options, test key
lib/mailer.php        Tries 4 server mail methods in turn, logs failures to storage/mail-errors.log
mail-test.php         Mail test page: /mail-test.php?key=TEST_KEY (delete once email works)
lib/PHPMailer/        PHPMailer 6.10.0 core (no Composer, no SMTP)
storage/leads.csv     Backup copy of every lead (created on the first form submit, blocked from the web)
```

## Go-live checklist
1. Upload every file to PHP hosting (PHP 8.0 or newer) that allows PHP `mail()`. Almost all cPanel/shared hosting does. `storage/` must be writable.
2. Optional: in `config.php`, set `FROM_EMAIL` to an address on your domain (e.g. `no-reply@yourdomain.com`). If you leave it empty, `no-reply@<your domain>` is used automatically. Never use a @gmail.com address as the sender.
3. Fill in a form on the live site and check digicarelynx@gmail.com. Look in Spam the first time and mark it "Not spam".
4. For the best inbox delivery, make sure your domain's SPF record includes your hosting server (cPanel → Email Deliverability fixes this in one click).

> On hosting that is not Apache (e.g. Nginx), block web access to `config.php`, `storage/` and `lib/` yourself. The `.htaccess` files only work on Apache/LiteSpeed.

## If emails don't arrive
1. Open `https://yourdomain.com/mail-test.php?key=<TEST_KEY from config.php>`. It shows the server's mail settings, sends a test email and lists any errors.
2. Failures are also written to `storage/mail-errors.log`.
3. Email can't be sent from a local PC setup (XAMPP/WAMP/MAMP, `php -S`). Test on the live hosting.

## What each lead email includes
Name, mobile, email, area, connection type, plan, billing period, message, which form was used and the time (IST), plus one-tap **Call** and **WhatsApp** buttons. If the customer gives an email address, they also get an automatic thank-you email (`SEND_AUTOREPLY`).

## Spam protection
A hidden trap field, a minimum time to fill the form, and a 30-second limit between submissions from the same visitor.

## Updating plans and prices
Change the `PLANS` object at the top of `assets/js/main.js`. The plan cards and every plan drop-down update automatically.

## Run locally
```bash
php -S localhost:8090
```
