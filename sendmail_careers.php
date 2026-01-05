<?php
// sendmail_careers.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

function log_career_error(
    $reason,
    $ex,
    $name,
    $email,
    $phone,
    $position,
    $experience,
    $availability,
    $portfolio,
    $message
) {
    try {
        $logDir = __DIR__ . '/app_data/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logPath = $logDir . '/CareerFormErrors.log';

        $sb  = "=====================================================\n";
        $sb .= "Timestamp : " . date('Y-m-d H:i:s') . "\n";
        $sb .= "Reason    : " . $reason . "\n\n";
        $sb .= "=== Applicant Information ===\n";
        $sb .= "Full Name : " . $name . "\n";
        $sb .= "Email     : " . $email . "\n";
        $sb .= "Mobile    : " . $phone . "\n";
        $sb .= "Position  : " . $position . "\n";
        $sb .= "Experience: " . ($experience === '' ? '(not specified)' : $experience) . "\n";
        $sb .= "Availability: " . ($availability === '' ? '(not specified)' : $availability) . "\n";
        $sb .= "Portfolio : " . ($portfolio === '' ? '(not specified)' : $portfolio) . "\n";
        $sb .= "Message   : " . ($message === '' ? '(none)' : $message) . "\n\n";

        if ($ex instanceof \Throwable || $ex instanceof \Exception) {
            $sb .= "=== Exception ===\n";
            $sb .= $ex->__toString() . "\n\n";
        }

        file_put_contents($logPath, $sb, FILE_APPEND);
    } catch (\Throwable $t) {
        // swallow logging errors
    }
}

// Grab fields from POST
$name         = isset($_POST['txtName']) ? trim($_POST['txtName']) : '';
$email        = isset($_POST['txtEmail']) ? trim($_POST['txtEmail']) : '';
$phone        = isset($_POST['txtPhone']) ? trim($_POST['txtPhone']) : '';
$position     = isset($_POST['txtPosition']) ? trim($_POST['txtPosition']) : '';
$experience   = isset($_POST['txtExperience']) ? trim($_POST['txtExperience']) : '';
$availability = isset($_POST['txtAvailability']) ? trim($_POST['txtAvailability']) : '';
$portfolio    = isset($_POST['txtPortfolio']) ? trim($_POST['txtPortfolio']) : '';
$message      = isset($_POST['txtMessage']) ? trim($_POST['txtMessage']) : '';

// Basic validation
if ($name === '' || $email === '' || $position === '' || $message === '') {
    header('Location: thankyou_career_failed.html?reason=missing');
    exit;
}

try {
    // ========== INTERNAL EMAIL ==========
    $body  = "A new career application was submitted via Health Connect.\n\n";
    $body .= "=== Applicant Information ===\n";
    $body .= "Full Name: " . $name . "\n";
    $body .= "Email: " . $email . "\n";
    $body .= "Mobile: " . $phone . "\n";
    $body .= "Position: " . $position . "\n";
    $body .= "Experience: " . ($experience === '' ? '(not specified)' : $experience) . "\n";
    $body .= "Availability: " . ($availability === '' ? '(not specified)' : $availability) . "\n";
    $body .= "Portfolio/LinkedIn: " . ($portfolio === '' ? '(not specified)' : $portfolio) . "\n\n";
    $body .= "=== Message ===\n";
    $body .= ($message === '' ? '(none provided)' : $message) . "\n\n";
    $body .= "Submitted on: " . date('Y-m-d H:i') . "\n";

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'mail.primorismanpower.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'testonly@primorismanpower.com';
    $mail->Password   = 'primoris@2025';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // From = Kent (same as insurance quotes)
    $mail->setFrom('kent.jensen@gmail.com', 'Health Connect – Careers Form');
    $mail->addAddress('testonly@primorismanpower.com');
    if ($email !== '') {
        $mail->addReplyTo($email, $name);
    }

    $mail->Subject = 'Health Connect – Career Application';
    $mail->Body    = $body;
    $mail->isHTML(false);
    $mail->send();

    // ========== APPLICANT AUTO-CONFIRMATION (HTML) ==========
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
            $cm->addAddress($email, $name);
            $cm->addReplyTo('testonly@primorismanpower.com', 'Health Connect');
            $cm->Subject = 'We received your Health Connect career application';

            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $safePosition = htmlspecialchars($position, ENT_QUOTES, 'UTF-8');
            $safePhone = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
            $safeExperience = htmlspecialchars($experience, ENT_QUOTES, 'UTF-8');
            $safeAvailability = htmlspecialchars($availability, ENT_QUOTES, 'UTF-8');
            $safePortfolio = htmlspecialchars($portfolio, ENT_QUOTES, 'UTF-8');
            $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

            $html  = "<!DOCTYPE html><html><head><meta charset='utf-8' />";
            $html .= "<title>Health Connect – Application Received</title></head>";
            $html .= "<body style='font-family:Arial,sans-serif;font-size:14px;color:#333;'>";
            $html .= "<div style='max-width:600px;margin:0 auto;border:1px solid #e0e0e0;border-radius:8px;padding:20px;'>";
            $html .= "<h2 style='color:#007bff;margin-top:0;'>Thank you for applying, {$safeName}!</h2>";
            $html .= "<p>We have received your career application via <strong>Health Connect</strong>.</p>";
            $html .= "<h3 style='font-size:16px;margin-top:20px;'>Summary of your application</h3>";
            $html .= "<ul>";
            $html .= "<li><strong>Position:</strong> {$safePosition}</li>";
            if ($experience !== '') {
                $html .= "<li><strong>Experience:</strong> {$safeExperience}</li>";
            }
            if ($availability !== '') {
                $html .= "<li><strong>Availability:</strong> {$safeAvailability}</li>";
            }
            if ($phone !== '') {
                $html .= "<li><strong>Mobile:</strong> {$safePhone}</li>";
            }
            if ($portfolio !== '') {
                $html .= "<li><strong>Portfolio/LinkedIn:</strong> {$safePortfolio}</li>";
            }
            $html .= "</ul>";
            if ($message !== '') {
                $html .= "<p><strong>Your message:</strong><br />" . nl2br($safeMessage) . "</p>";
            }
            $html .= "<p>Our recruitment team will review your application and contact you about next steps.</p>";
            $html .= "<p style='font-size:12px;color:#888;margin-top:20px;'>";
            $html .= "This email was sent automatically by Health Connect. If you did not submit this application, please ignore this email.";
            $html .= "</p></div></body></html>";

            $cm->isHTML(true);
            $cm->Body = $html;
            $cm->send();
        } catch (\Throwable $exConfirm) {
            log_career_error(
                'Applicant auto-confirmation failed',
                $exConfirm,
                $name,
                $email,
                $phone,
                $position,
                $experience,
                $availability,
                $portfolio,
                $message
            );
        }
    }

    header('Location: thankyou_career.html');
    exit;
} catch (\Throwable $ex) {
    log_career_error(
        'Email send failed',
        $ex,
        $name,
        $email,
        $phone,
        $position,
        $experience,
        $availability,
        $portfolio,
        $message
    );
    header('Location: thankyou_career_failed.html?reason=smtp');
    exit;
}
