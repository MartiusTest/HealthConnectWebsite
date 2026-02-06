<?php
use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

function log_yakap_error($reason, $ex, $fullName, $email, $phone, $philhealthNo, $hasPhilsysId, $notes)
{
    try {
        $logDir = __DIR__ . '/app_data/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logPath = $logDir . '/YakapErrors.log';

        $sb  = "=====================================================\n";
        $sb .= "Timestamp : " . date('Y-m-d H:i:s') . "\n";
        $sb .= "Reason    : " . $reason . "\n\n";
        $sb .= "=== Contact Info ===\n";
        $sb .= "Full Name : " . $fullName . "\n";
        $sb .= "Email     : " . $email . "\n";
        $sb .= "Mobile    : " . $phone . "\n\n";
        $sb .= "=== YAKAP Details ===\n";
        $sb .= "PhilHealth No : " . $philhealthNo . "\n";
        $sb .= "Has Philsys ID: " . $hasPhilsysId . "\n";
        $sb .= "Notes         : " . ($notes === '' ? '(none)' : $notes) . "\n\n";

        if ($ex instanceof \Throwable || $ex instanceof \Exception) {
            $sb .= "=== Exception ===\n";
            $sb .= $ex->__toString() . "\n\n";
        }

        file_put_contents($logPath, $sb, FILE_APPEND);
    } catch (\Throwable $t) {
        // swallow logging errors
    }
}

$fullName    = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
$email       = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone       = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$philhealthNo = isset($_POST['philhealth_no']) ? trim($_POST['philhealth_no']) : '';
$hasPhilsysId = isset($_POST['has_philsys_id']) ? trim($_POST['has_philsys_id']) : '';
$notes       = isset($_POST['notes']) ? trim($_POST['notes']) : '';

if ($fullName === '' || $philhealthNo === '' || ($hasPhilsysId !== 'Yes' && $hasPhilsysId !== 'No')) {
    header('Location: thankyou_yakap_failed.html');
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: thankyou_yakap_failed.html');
    exit;
}

try {
    $body  = "A new PhilHealth YAKAP registration was submitted via Health Connect.\n\n";
    $body .= "=== Contact Information ===\n";
    $body .= "Full Name      : " . $fullName . "\n";
    $body .= "Email          : " . $email . "\n";
    $body .= "Mobile         : " . ($phone === '' ? '(not specified)' : $phone) . "\n\n";
    $body .= "=== YAKAP Details ===\n";
    $body .= "PhilHealth No. : " . $philhealthNo . "\n";
    $body .= "Philsys/Nat ID : " . $hasPhilsysId . "\n";
    $body .= "Notes          : " . ($notes === '' ? '(none provided)' : $notes) . "\n\n";
    $body .= "Submitted on: " . date('Y-m-d H:i') . "\n";

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'mail.healthconnect.com.ph';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'support@healthconnect.com.ph';
    $mail->Password   = 'h3@l+hc0nn3ct@2026';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('support@healthconnect.com.ph', 'Health Connect - YAKAP');
    $mail->addAddress('support@healthconnect.com.ph');

    if ($email !== '') {
        $mail->addReplyTo($email, $fullName);
    }

    $mail->Subject = 'Health Connect - PhilHealth YAKAP Registration';
    $mail->Body    = $body;
    $mail->isHTML(false);
    $mail->send();

    if ($email !== '') {
        try {
            $cm = new PHPMailer(true);
            $cm->isSMTP();
            $cm->Host       = 'mail.healthconnect.com.ph';
            $cm->SMTPAuth   = true;
            $cm->Username   = 'support@healthconnect.com.ph';
            $cm->Password   = 'h3@l+hc0nn3ct@2026';
            $cm->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $cm->Port       = 587;

            $cm->setFrom('support@healthconnect.com.ph', 'Health Connect');
            $cm->addAddress($email, $fullName);

            $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            $safePhilHealthNo = htmlspecialchars($philhealthNo, ENT_QUOTES, 'UTF-8');

            $html  = "<!DOCTYPE html><html><head><meta charset='utf-8' />";
            $html .= "<title>Health Connect - YAKAP Registration Received</title></head>";
            $html .= "<body style='font-family:Segoe UI,Arial,sans-serif;font-size:14px;color:#333;'>";
            $html .= "<h2 style='color:#007bff;'>Thank you for your PhilHealth YAKAP registration</h2>";
            $html .= "<p>Dear {$safeName},</p>";
            $html .= "<p>We have received your PhilHealth YAKAP registration request.</p>";
            $html .= "<ul>";
            $html .= "<li><strong>PhilHealth No.:</strong> {$safePhilHealthNo}</li>";
            $html .= "</ul>";
            $html .= "<p>A Health Connect representative will contact you to confirm your details and next steps.</p>";
            $html .= "<p style='margin-top:20px;'>Best regards,<br />Health Connect Team</p>";
            $html .= "</body></html>";

            $cm->isHTML(true);
            $cm->Subject = 'Health Connect - We received your PhilHealth YAKAP registration';
            $cm->Body    = $html;
            $cm->send();
        } catch (\Throwable $exConfirm) {
            log_yakap_error('Customer confirmation email failed', $exConfirm, $fullName, $email, $phone, $philhealthNo, $hasPhilsysId, $notes);
        }
    }

    header('Location: thankyou_yakap.html');
    exit;
} catch (\Throwable $ex) {
    log_yakap_error('Admin YAKAP email send failed', $ex, $fullName, $email, $phone, $philhealthNo, $hasPhilsysId, $notes);
    header('Location: thankyou_yakap_failed.html');
    exit;
}
