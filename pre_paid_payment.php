<?php
// pre_paid_payment.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

function log_pre_paid_error(
    $reason,
    $ex,
    $fullName,
    $email,
    $phone,
    $provider,
    $plan,
    $insuredPerson,
    $birthdate,
    $age,
    $message,
    $receiptName
) {
    try {
        $logDir = __DIR__ . '/app_data/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logPath = $logDir . '/PrePaidPaymentErrors.log';

        $sb  = "=====================================================\n";
        $sb .= "Timestamp : " . date('Y-m-d H:i:s') . "\n";
        $sb .= "Reason    : " . $reason . "\n\n";

        $sb .= "=== Contact Info ===\n";
        $sb .= "Full Name : " . $fullName . "\n";
        $sb .= "Email     : " . $email . "\n";
        $sb .= "Mobile    : " . $phone . "\n\n";

        $sb .= "=== Insurance Details ===\n";
        $sb .= "Provider  : " . $provider . "\n";
        $sb .= "Plan      : " . $plan . "\n";
        $sb .= "Insured   : " . $insuredPerson . "\n";
        $sb .= "Birthdate : " . $birthdate . "\n";
        $sb .= "Age       : " . $age . "\n";
        $sb .= "Receipt   : " . $receiptName . "\n";
        $sb .= "Message   : " . (trim($message) === '' ? '(none)' : $message) . "\n\n";

        if ($ex instanceof \Throwable || $ex instanceof \Exception) {
            $sb .= "=== Exception ===\n";
            $sb .= $ex->__toString() . "\n\n";
        }

        file_put_contents($logPath, $sb, FILE_APPEND);
    } catch (\Throwable $t) {
        // swallow logging errors
    }
}

$fullName = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
$email    = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone    = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$provider = isset($_POST['provider']) ? trim($_POST['provider']) : '';
$plan     = isset($_POST['plan']) ? trim($_POST['plan']) : '';
$insuredPerson = isset($_POST['insured_person']) ? trim($_POST['insured_person']) : '';
$birthdate = isset($_POST['birthdate']) ? trim($_POST['birthdate']) : '';
$age = isset($_POST['age']) ? trim($_POST['age']) : '';
$message  = isset($_POST['message']) ? trim($_POST['message']) : '';

$receiptName = '';
$receiptTmp = '';
$receiptError = null;

if (isset($_FILES['receipt_attachment']) && is_array($_FILES['receipt_attachment'])) {
    $receiptName = isset($_FILES['receipt_attachment']['name']) ? $_FILES['receipt_attachment']['name'] : '';
    $receiptTmp = isset($_FILES['receipt_attachment']['tmp_name']) ? $_FILES['receipt_attachment']['tmp_name'] : '';
    $receiptError = isset($_FILES['receipt_attachment']['error']) ? $_FILES['receipt_attachment']['error'] : UPLOAD_ERR_NO_FILE;
} else {
    $receiptError = UPLOAD_ERR_NO_FILE;
}

if ($fullName === '' || $email === '' || $provider === '' || $receiptError !== UPLOAD_ERR_OK) {
    header('Location: thankyou_pre_paid_failed.html');
    exit;
}

try {
    $body  = "A new pre-paid plan payment receipt was submitted via Health Connect.\n\n";
    $body .= "=== Contact Information ===\n";
    $body .= "Full Name: " . $fullName . "\n";
    $body .= "Email: "     . $email    . "\n";
    $body .= "Mobile: "    . $phone    . "\n\n";

    $body .= "=== Plan Details ===\n";
    $body .= "Provider: " . $provider . "\n";
    $body .= "Plan: " . ($plan === '' ? '(not specified)' : $plan) . "\n";
    $body .= "Person to be Insured: " . ($insuredPerson === '' ? '(not specified)' : $insuredPerson) . "\n";
    $body .= "Birthdate: " . ($birthdate === '' ? '(not specified)' : $birthdate) . "\n";
    $body .= "Age: " . ($age === '' ? '(not specified)' : $age) . "\n\n";

    $body .= "Receipt File: " . ($receiptName === '' ? '(not provided)' : $receiptName) . "\n\n";

    $body .= "Remarks:\n";
    $body .= ($message === '' ? "(none provided)" : $message) . "\n\n";

    $body .= "Submitted on: " . date('Y-m-d H:i') . "\n";

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'mail.primorismanpower.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'testonly@primorismanpower.com';
    $mail->Password   = 'primoris@2025';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('kent.jensen@gmail.com', 'Health Connect – Pre-Paid Payment');
    $mail->addAddress('testonly@primorismanpower.com');

    if ($email !== '') {
        $mail->addReplyTo($email, $fullName);
    }

    if ($receiptError === UPLOAD_ERR_OK && $receiptTmp !== '') {
        $safeReceiptName = $receiptName !== '' ? $receiptName : 'payment-receipt';
        $mail->addAttachment($receiptTmp, $safeReceiptName);
    }

    $mail->Subject = 'Health Connect – Pre-Paid Payment Receipt';
    $mail->Body    = $body;
    $mail->isHTML(false);

    $mail->send();

    if ($email !== '') {
        try {
            $cm = new PHPMailer(true);
            $cm->isSMTP();
            $cm->Host       = 'mail.primorismanpower.com';
            $cm->SMTPAuth   = true;
            $cm->Username   = 'testonly@primorismanpower.com';
            $cm->Password   = 'primoris@2025';
            $cm->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $cm->Port       = 587;

            $cm->setFrom('testonly@primorismanpower.com', 'Health Connect');
            $cm->addAddress($email, $fullName);
            $cm->addReplyTo('testonly@primorismanpower.com', 'Health Connect');

            $cm->Subject = 'We received your Health Connect payment receipt';

            $safeName     = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            $safeProvider = htmlspecialchars($provider, ENT_QUOTES, 'UTF-8');
            $safePlan     = htmlspecialchars($plan, ENT_QUOTES, 'UTF-8');
            $safePhone    = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
            $safeInsured  = htmlspecialchars($insuredPerson, ENT_QUOTES, 'UTF-8');
            $safeBirthdate = htmlspecialchars($birthdate, ENT_QUOTES, 'UTF-8');
            $safeAge      = htmlspecialchars($age, ENT_QUOTES, 'UTF-8');
            $safeMessage  = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

            $html  = "<!DOCTYPE html><html><head><meta charset='utf-8' />";
            $html .= "<title>Health Connect – Payment Receipt Received</title></head>";
            $html .= "<body style='font-family:Arial,sans-serif;font-size:14px;color:#333;'>";
            $html .= "<div style='max-width:600px;margin:0 auto;border:1px solid #e0e0e0;border-radius:8px;padding:20px;'>";
            $html .= "<h2 style='color:#007bff;margin-top:0;'>Thank you, {$safeName}!</h2>";
            $html .= "<p>We have received your pre-paid plan payment receipt via <strong>Health Connect</strong>.</p>";
            $html .= "<h3 style='font-size:16px;margin-top:20px;'>Summary of your submission</h3>";
            $html .= "<ul>";
            $html .= "<li><strong>Provider:</strong> {$safeProvider}</li>";
            if ($plan !== '') {
                $html .= "<li><strong>Plan:</strong> {$safePlan}</li>";
            }
            if ($insuredPerson !== '') {
                $html .= "<li><strong>Person to be insured:</strong> {$safeInsured}</li>";
            }
            if ($birthdate !== '') {
                $html .= "<li><strong>Birthdate:</strong> {$safeBirthdate}</li>";
            }
            if ($age !== '') {
                $html .= "<li><strong>Age:</strong> {$safeAge}</li>";
            }
            if ($phone !== '') {
                $html .= "<li><strong>Mobile:</strong> {$safePhone}</li>";
            }
            $html .= "</ul>";
            if ($message !== '') {
                $html .= "<p><strong>Your notes:</strong><br />" . nl2br($safeMessage) . "</p>";
            }
            $html .= "<p>Our team will review your receipt and confirm your payment shortly.</p>";
            $html .= "<p style='font-size:12px;color:#888;margin-top:20px;'>";
            $html .= "This email was sent automatically by Health Connect. If you did not submit this receipt, please ignore this email.";
            $html .= "</p></div></body></html>";

            $cm->isHTML(true);
            $cm->Body = $html;

            $cm->send();
        } catch (\Throwable $exConfirm) {
            log_pre_paid_error(
                'Customer auto-confirmation failed',
                $exConfirm,
                $fullName,
                $email,
                $phone,
                $provider,
                $plan,
                $insuredPerson,
                $birthdate,
                $age,
                $message,
                $receiptName
            );
        }
    }

    header('Location: thankyou_pre_paid.html');
    exit;
} catch (\Throwable $ex) {
    log_pre_paid_error(
        'Email send failed',
        $ex,
        $fullName,
        $email,
        $phone,
        $provider,
        $plan,
        $insuredPerson,
        $birthdate,
        $age,
        $message,
        $receiptName
    );
    header('Location: thankyou_pre_paid_failed.html');
    exit;
}
