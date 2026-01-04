<?php
// sendmail.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Adjust path if PHPMailer is in another folder
require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

// Grab fields from POST
$name    = isset($_POST['txtName'])    ? trim($_POST['txtName'])    : '';
$email   = isset($_POST['txtEmail'])   ? trim($_POST['txtEmail'])   : '';
$phone   = isset($_POST['txtPhone'])   ? trim($_POST['txtPhone'])   : '';
$message = isset($_POST['txtMessage']) ? trim($_POST['txtMessage']) : '';

// Basic validation
if ($name === '' || $email === '' || $message === '') {
    header('Location: thankyou_contact_failed.html?reason=missing');
    exit();
}

// Build message for Health Connect
$body  = "A new contact inquiry was submitted via Health Connect.\n\n";
$body .= "=== Contact Information ===\n";
$body .= "Full Name: {$name}\n";
$body .= "Email: {$email}\n";
$body .= "Mobile: {$phone}\n\n";
$body .= "=== Message ===\n{$message}\n\n";
$body .= "Submitted on: " . date('Y-m-d H:i') . "\n";

try {
    // 1) Send to Health Connect
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'mail.primorismanpower.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'testonly@primorismanpower.com';
    $mail->Password   = 'primoris@2025';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('kent.jensen@gmail.com', 'Health Connect – Contact Form');
    $mail->addAddress('testonly@primorismanpower.com');
    $mail->addReplyTo($email, $name);

    $mail->Subject = 'Health Connect – Contact Inquiry';
    $mail->Body    = $body;
    $mail->isHTML(false);

    $mail->send();

    // 2) Auto-confirmation email to the customer (best-effort)
    try {
        $auto = new PHPMailer(true);
        $auto->isSMTP();
        $auto->Host       = 'mail.primorismanpower.com';
        $auto->SMTPAuth   = true;
        $auto->Username   = 'testonly@primorismanpower.com';
        $auto->Password   = 'primoris@2025';
        $auto->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $auto->Port       = 587;

        $auto->setFrom('support@healthconnect.ph', 'Health Connect Support');
        $auto->addAddress($email, $name);
        $auto->Subject = 'We have received your message – Health Connect';

        $autoBody  = "Hi {$name},\n\n";
        $autoBody .= "Thank you for contacting Health Connect!\n";
        $autoBody .= "We have received your message and our team will respond as soon as possible.\n\n";
        $autoBody .= "Regards,\nHealth Connect Team\n";

        $auto->Body    = $autoBody;
        $auto->isHTML(false);
        $auto->send();
    } catch (Exception $ex2) {
        // Ignore auto-confirmation failure
    }

    // Success
    header('Location: thankyou_contact.html');
    exit();

} catch (Exception $ex) {
    // Optional: log to a file on the server
    $logDir = __DIR__ . '/app_data/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $logFile = $logDir . '/ContactFormErrors.log';

    $log  = "=====================================================\n";
    $log .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    $log .= "Reason: SMTP failure\n";
    $log .= "Name: {$name}\n";
    $log .= "Email: {$email}\n";
    $log .= "Phone: {$phone}\n";
    $log .= "Message: {$message}\n\n";
    $log .= "Exception:\n" . $ex->getMessage() . "\n\n";

    @file_put_contents($logFile, $log, FILE_APPEND);

    header('Location: thankyou_contact_failed.html?reason=smtp');
    exit();
}