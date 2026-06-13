<?php
declare(strict_types=1);

/**
 * Mailer - PHPMailer wrapper with graceful fallback.
 * When SMTP is disabled or PHPMailer is missing, emails are appended to
 * logs/mail.log so the platform keeps working in development.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        $body = self::wrap($subject, $htmlBody);

        if (SMTP_ENABLED && self::loadPhpMailer()) {
            try {
                $mailerClass = 'PHPMailer\\PHPMailer\\PHPMailer';
                $mail = new $mailerClass(true);
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->SMTPSecure = 'tls';
                $mail->Port       = SMTP_PORT;
                $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
                $mail->addAddress($to);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $body;
                $mail->send();
                return true;
            } catch (Throwable $e) {
                self::logMail($to, $subject, 'SMTP ERROR: ' . $e->getMessage());
                return false;
            }
        }

        self::logMail($to, $subject, strip_tags($htmlBody));
        return true;
    }

    private static function loadPhpMailer(): bool
    {
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) return true;
        $composer = ROOT_PATH . '/vendor/autoload.php';
        if (is_file($composer)) { require_once $composer; }
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) return true;
        // Manual install: assets/vendors/phpmailer/src/*
        $src = ROOT_PATH . '/assets/vendors/phpmailer/src';
        foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $f) {
            if (is_file("$src/$f")) require_once "$src/$f";
        }
        return class_exists('PHPMailer\\PHPMailer\\PHPMailer');
    }

    private static function wrap(string $title, string $content): string
    {
        return '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;margin:auto;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden">'
            . '<div style="background:linear-gradient(135deg,#0f172a,#1e3a8a);padding:22px 28px;color:#fff">'
            . '<h2 style="margin:0">' . APP_NAME . '</h2><small>' . APP_TAGLINE . '</small></div>'
            . '<div style="padding:24px 28px;color:#1e293b">' . $content . '</div>'
            . '<div style="padding:14px 28px;background:#f8fafc;color:#64748b;font-size:12px">'
            . 'This is an educational banking simulation. No real money is involved.</div></div>';
    }

    private static function logMail(string $to, string $subject, string $body): void
    {
        if (!is_dir(LOG_PATH)) mkdir(LOG_PATH, 0775, true);
        $line = sprintf("[%s] TO: %s | SUBJECT: %s\n%s\n%s\n",
            date('Y-m-d H:i:s'), $to, $subject, $body, str_repeat('-', 60));
        file_put_contents(LOG_PATH . '/mail.log', $line, FILE_APPEND | LOCK_EX);
    }
}
