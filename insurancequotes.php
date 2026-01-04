<?php
// insurancequotes.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

function log_quote_error(
    $reason,
    $ex,
    $fullName,
    $email,
    $phone,
    $provider,
    $plan,
    $message
) {
    try {
        $logDir = __DIR__ . '/app_data/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logPath = $logDir . '/InsuranceQuoteErrors.log';

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

// Read POST fields
$fullName = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
$email    = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone    = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$provider = isset($_POST['provider']) ? trim($_POST['provider']) : '';
$plan     = isset($_POST['plan']) ? trim($_POST['plan']) : '';
$message  = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($fullName === '' || $email === '' || $provider === '') {
    // Required fields missing
    header('Location: thankyou_quote_failed.html');
    exit;
}

// Map internal text for provider if needed
$providerText = $provider;
switch ($provider) {
    case 'PacificCross': $providerText = 'Pacific Cross'; break;
    case 'OONA':         $providerText = 'OONA'; break;
    case 'PhilCare':     $providerText = 'PhilCare'; break;
    case 'Medicare':     $providerText = 'Medicare'; break;
    case 'Alpha':        $providerText = 'Alpha Insurance'; break;
    case 'Kaiser':       $providerText = 'Kaiser'; break;
    case 'Other':        $providerText = 'Other / Not Sure'; break;
    default:             $providerText = $provider; break;
}

try {
    // ========== INTERNAL EMAIL ==========
    $body  = "A new insurance quote request was submitted via Health Connect.\n\n";
    $body .= "=== Contact Information ===\n";
    $body .= "Full Name: " . $fullName . "\n";
    $body .= "Email: "     . $email    . "\n";
    $body .= "Mobile: "    . $phone    . "\n\n";

    $body .= "=== Insurance Details ===\n";
    $body .= "Provider: " . $providerText . "\n";
    $body .= "Plan: "     . ($plan === '' ? '(none specified)' : $plan) . "\n\n";

    $body .= "Message:\n";
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

    // From = Kent (same as .NET)
    $mail->setFrom('kent.jensen@gmail.com', 'Health Connect – Quote Request');

    // To = Primoris test inbox
    $mail->addAddress('testonly@primorismanpower.com');

    if ($email !== '') {
        $mail->addReplyTo($email, $fullName);
    }

    $mail->Subject = 'Health Connect – Insurance Quote Request';
    $mail->Body    = $body;
    $mail->isHTML(false);

    $mail->send();

    // ========== CUSTOMER AUTO-CONFIRMATION (HTML) ==========
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

            $cm->Subject = 'We received your Health Connect insurance quote request';

            $safeName     = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            $safeProvText = htmlspecialchars($providerText, ENT_QUOTES, 'UTF-8');
            $safePlan     = htmlspecialchars($plan, ENT_QUOTES, 'UTF-8');
            $safePhone    = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
            $safeMessage  = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

            $html  = "<!DOCTYPE html><html><head><meta charset='utf-8' />";
            $html .= "<title>Health Connect – Quote Request Received</title></head>";
            $html .= "<body style='font-family:Arial,sans-serif;font-size:14px;color:#333;'>";
            $html .= "<div style='max-width:600px;margin:0 auto;border:1px solid #e0e0e0;border-radius:8px;padding:20px;'>";
            $html .= "<h2 style='color:#007bff;margin-top:0;'>Thank you for your request, {$safeName}!</h2>";
            $html .= "<p>We have received your insurance quote request via <strong>Health Connect</strong>.</p>";
            $html .= "<h3 style='font-size:16px;margin-top:20px;'>Summary of your request</h3>";
            $html .= "<ul>";
            $html .= "<li><strong>Provider:</strong> {$safeProvText}</li>";
            if ($plan !== '') {
                $html .= "<li><strong>Plan:</strong> {$safePlan}</li>";
            }
            if ($phone !== '') {
                $html .= "<li><strong>Mobile:</strong> {$safePhone}</li>";
            }
            $html .= "</ul>";
            if ($message !== '') {
                $html .= "<p><strong>Your notes / requirements:</strong><br />" .
                         nl2br($safeMessage) . "</p>";
            }
            $html .= "<p>Our team (or the partner insurance provider) will review your details and contact you with available options and pricing.</p>";
            $html .= "<p style='font-size:12px;color:#888;margin-top:20px;'>";
            $html .= "This email was sent automatically by Health Connect. If you did not submit this request, please ignore this email.";
            $html .= "</p></div></body></html>";

            $cm->isHTML(true);
            $cm->Body = $html;

            $cm->send();
        } catch (\Throwable $exConfirm) {
            log_quote_error(
                'Customer auto-confirmation failed',
                $exConfirm,
                $fullName,
                $email,
                $phone,
                $providerText,
                $plan,
                $message
            );
        }
    }

    // Success
    header('Location: thankyou_quote.html');
    exit;
} catch (\Throwable $ex) {
    log_quote_error(
        'Email send failed',
        $ex,
        $fullName,
        $email,
        $phone,
        $providerText,
        $plan,
        $message
    );
    header('Location: thankyou_quote_failed.html');
    exit;
}