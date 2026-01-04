<?php
// scheduling.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

function log_scheduling_error(
    $reason,
    $ex,
    $fullName,
    $email,
    $phone,
    $service,
    $provider,
    $category,
    $preferredDate,
    $preferredTime,
    $notes
) {
    try {
        $logDir = __DIR__ . '/app_data/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logPath = $logDir . '/SchedulingErrors.log';

        $sb  = "=====================================================\n";
        $sb .= "Timestamp : " . date('Y-m-d H:i:s') . "\n";
        $sb .= "Reason    : " . $reason . "\n\n";

        $sb .= "=== Contact Info ===\n";
        $sb .= "Full Name : " . $fullName . "\n";
        $sb .= "Email     : " . $email . "\n";
        $sb .= "Mobile    : " . $phone . "\n\n";

        $sb .= "=== Appointment Details ===\n";
        $sb .= "Service   : " . $service . "\n";
        $sb .= "Provider  : " . $provider . "\n";
        $sb .= "Category  : " . $category . "\n";
        $sb .= "Pref Date : " . $preferredDate . "\n";
        $sb .= "Pref Time : " . $preferredTime . "\n";
        $sb .= "Notes     : " . ($notes === '' ? '(none)' : $notes) . "\n\n";

        if ($ex instanceof \Throwable || $ex instanceof \Exception) {
            $sb .= "=== Exception ===\n";
            $sb .= $ex->__toString() . "\n\n";
        }

        file_put_contents($logPath, $sb, FILE_APPEND);
    } catch (\Throwable $t) {
        // swallow logging errors
    }
}

// Read POST
$fullName      = isset($_POST['full_name'])       ? trim($_POST['full_name'])       : '';
$email         = isset($_POST['email'])           ? trim($_POST['email'])           : '';
$phone         = isset($_POST['phone'])           ? trim($_POST['phone'])           : '';
$service       = isset($_POST['service'])         ? trim($_POST['service'])         : '';
$provider      = isset($_POST['provider'])        ? trim($_POST['provider'])        : '';
$category      = isset($_POST['category'])        ? trim($_POST['category'])        : '';
$preferredDate = isset($_POST['preferred_date'])  ? trim($_POST['preferred_date'])  : '';
$preferredTime = isset($_POST['preferred_time'])  ? trim($_POST['preferred_time'])  : '';
$notes         = isset($_POST['notes'])           ? trim($_POST['notes'])           : '';

if ($fullName === '' || $email === '' || $service === '') {
    header('Location: thankyou_scheduling_failed.html');
    exit;
}

// Apply same “(not applicable)” for hidden flows as ASP.NET did
$serviceLower = strtolower($service);
$hide =
    $serviceLower === strtolower('Home Health Care - Private Duty Nurse') ||
    $serviceLower === strtolower('Home Health Care - Caregiver') ||
    $serviceLower === strtolower('Diagnostics - Laboratory Test') ||
    $serviceLower === strtolower('Diagnostics - Radiology Procedure');

if ($hide) {
    if ($provider === '') $provider = '(not applicable)';
    if ($category === '') $category = '(not applicable)';
}

try {
    // 1) Send admin notification
    $body  = "A new scheduling request was submitted via Health Connect.\n\n";
    $body .= "=== Contact Information ===\n";
    $body .= "Full Name : " . $fullName . "\n";
    $body .= "Email     : " . $email    . "\n";
    $body .= "Mobile    : " . $phone    . "\n\n";

    $body .= "=== Appointment Details ===\n";
    $body .= "Service   : " . $service . "\n";
    $body .= "Provider  : " . ($provider === '' ? '(not specified)' : $provider) . "\n";
    $body .= "Category  : " . ($category === '' ? '(not specified)' : $category) . "\n";
    $body .= "Pref Date : " . ($preferredDate === '' ? '(not specified)' : $preferredDate) . "\n";
    $body .= "Pref Time : " . ($preferredTime === '' ? '(not specified)' : $preferredTime) . "\n\n";

    $body .= "Notes:\n" . ($notes === '' ? "(none provided)" : $notes) . "\n\n";
    $body .= "Submitted on: " . date('Y-m-d H:i') . "\n";

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'mail.primorismanpower.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'testonly@primorismanpower.com';
    $mail->Password   = 'primoris@2025';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('kent.jensen@gmail.com', 'Health Connect – Scheduling');
    $mail->addAddress('testonly@primorismanpower.com');

    if ($email !== '') {
        $mail->addReplyTo($email, $fullName);
    }

    $mail->Subject = 'Health Connect – Scheduling Request';
    $mail->Body    = $body;
    $mail->isHTML(false);

    $mail->send();

    // 2) Customer confirmation (non-fatal)
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

            $cm->setFrom('kent.jensen@gmail.com', 'Health Connect');
            $cm->addAddress($email, $fullName);

            $safeName   = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            $safeServ   = htmlspecialchars($service, ENT_QUOTES, 'UTF-8');
            $safeDate   = htmlspecialchars($preferredDate, ENT_QUOTES, 'UTF-8');
            $safeTime   = htmlspecialchars($preferredTime, ENT_QUOTES, 'UTF-8');

            $html  = "<!DOCTYPE html><html><head><meta charset='utf-8' />";
            $html .= "<title>Health Connect – Scheduling Request Received</title></head>";
            $html .= "<body style='font-family:Segoe UI,Arial,sans-serif;font-size:14px;color:#333;'>";
            $html .= "<h2 style='color:#007bff;'>Thank you for your scheduling request</h2>";
            $html .= "<p>Dear {$safeName},</p>";
            $html .= "<p>We have received your request for the following service:</p>";
            $html .= "<ul>";
            $html .= "<li><strong>Service:</strong> {$safeServ}</li>";
            if ($preferredDate !== '') {
                $html .= "<li><strong>Preferred Date:</strong> {$safeDate}</li>";
            }
            if ($preferredTime !== '') {
                $html .= "<li><strong>Preferred Time:</strong> {$safeTime}</li>";
            }
            $html .= "</ul>";
            $html .= "<p>A Health Connect representative or partner provider will contact you to confirm your appointment details, availability, and any additional requirements.</p>";
            $html .= "<p style='margin-top:20px;'>Best regards,<br />Health Connect Team</p>";
            $html .= "</body></html>";

            $cm->isHTML(true);
            $cm->Subject = 'Health Connect – We received your scheduling request';
            $cm->Body    = $html;

            $cm->send();
        } catch (\Throwable $exConfirm) {
            log_scheduling_error(
                'Customer confirmation email failed',
                $exConfirm,
                $fullName,
                $email,
                $phone,
                $service,
                $provider,
                $category,
                $preferredDate,
                $preferredTime,
                $notes
            );
        }
    }

    header('Location: thankyou_scheduling.html');
    exit;
} catch (\Throwable $ex) {
    log_scheduling_error(
        'Admin scheduling email send failed',
        $ex,
        $fullName,
        $email,
        $phone,
        $service,
        $provider,
        $category,
        $preferredDate,
        $preferredTime,
        $notes
    );
    header('Location: thankyou_scheduling_failed.html');
    exit;
}