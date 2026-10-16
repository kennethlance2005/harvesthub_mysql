<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function smtpConfig(): array {
    $config = [
        'host' => getenv('SMTP_HOST') ?: '',
        'port' => getenv('SMTP_PORT') ?: '',
        'username' => getenv('SMTP_USERNAME') ?: '',
        'password' => getenv('SMTP_PASSWORD') ?: '',
        'from_email' => getenv('SMTP_FROM_EMAIL') ?: '',
        'from_name' => getenv('SMTP_FROM_NAME') ?: 'HarvestHub',
        'secure' => getenv('SMTP_SECURE') ?: 'tls',
        'brevo_api_key' => getenv('BREVO_API_KEY') ?: '',
    ];

    // secrets.local.php (private, not in Git) wins; otherwise the group's shared
    // settings in secrets.local.example.php are used, so a fresh pull works as-is.
    $localFile = __DIR__ . '/../secrets.local.php';
    if (!is_file($localFile)) {
        $localFile = __DIR__ . '/../secrets.local.example.php';
    }
    if (is_file($localFile)) {
        $secrets = require $localFile;
        foreach ($config as $key => $value) {
            $secret = $secrets[$key === 'brevo_api_key' ? $key : 'smtp_' . $key] ?? null;
            if ($value === '' && is_string($secret) && $secret !== '') {
                $config[$key] = $secret;
            }
        }
    }

    if ($config['port'] === '') {
        $config['port'] = '587';
    }
    return $config;
}

/**
 * Sends through Brevo's web API. It uses the normal HTTPS port, so it works on
 * networks and hosts that block email ports (school Wi-Fi, hotspots, Render).
 */
function sendViaBrevo(array $config, string $toEmail, string $subject, string $htmlBody, string $textBody): bool {
    $payload = json_encode([
        'sender' => ['name' => $config['from_name'], 'email' => $config['from_email']],
        'to' => [['email' => $toEmail]],
        'subject' => $subject,
        'htmlContent' => $htmlBody,
        'textContent' => $textBody,
    ]);
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "api-key: {$config['brevo_api_key']}\r\nContent-Type: application/json\r\nAccept: application/json\r\n",
        'content' => $payload,
        'timeout' => 10,
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents('https://api.brevo.com/v3/smtp/email', false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) $status = (int) $match[1];
    }
    if ($status >= 200 && $status < 300) return true;
    error_log("HarvestHub: Brevo email delivery failed (HTTP $status): " . substr((string) $response, 0, 300));
    return false;
}

function sendHarvestHubEmail(string $toEmail, string $subject, string $htmlBody, string $textBody): bool {
    $config = smtpConfig();
    // Brevo first when a key is set; Gmail SMTP below stays as the fallback.
    if ($config['brevo_api_key'] !== '' && filter_var($config['from_email'], FILTER_VALIDATE_EMAIL)
        && sendViaBrevo($config, $toEmail, $subject, $htmlBody, $textBody)) {
        return true;
    }
    foreach (['host', 'username', 'password', 'from_email'] as $required) {
        if ($config[$required] === '') {
            error_log('HarvestHub: SMTP is not fully configured; email was not sent.');
            return false;
        }
    }
    if (!ctype_digit((string) $config['port']) || (int) $config['port'] < 1 || (int) $config['port'] > 65535
        || !filter_var($config['from_email'], FILTER_VALIDATE_EMAIL)
        || !in_array($config['secure'], ['tls', 'ssl'], true)) {
        error_log('HarvestHub: SMTP configuration is invalid; email was not sent.');
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        // Give up quickly when a network blocks email, instead of PHPMailer's
        // five-minute default that leaves the page hanging.
        $mail->Timeout = 10;
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->Port = (int) $config['port'];
        if ($config['secure'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        $mail->send();
        return true;
    } catch (Exception) {
        error_log('HarvestHub: SMTP email delivery failed: ' . $mail->ErrorInfo);
        return false;
    }
}
