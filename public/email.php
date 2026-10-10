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
    ];

    $localFile = __DIR__ . '/../secrets.local.php';
    if (is_file($localFile)) {
        $secrets = require $localFile;
        foreach ($config as $key => $value) {
            $secret = $secrets['smtp_' . $key] ?? null;
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

function sendHarvestHubEmail(string $toEmail, string $subject, string $htmlBody, string $textBody): bool {
    $config = smtpConfig();
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
