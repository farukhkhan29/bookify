<?php
// includes/mailer.php - Mailgun Email Service

require_once __DIR__ . '/config.php';

class Mailer {
    private static function getConfig(): array {
        return [
            'api_key' => getSetting('mailgun_api_key'),
            'domain'  => getSetting('mailgun_domain'),
            'from_email' => getSetting('from_email', 'noreply@bookflow.com'),
            'from_name'  => getSetting('from_name', 'BookFlow'),
        ];
    }

    public static function send(string $to, string $subject, string $htmlBody, string $textBody = ''): array {
        $config = self::getConfig();

        if (empty($config['api_key']) || empty($config['domain'])) {
            return ['success' => false, 'error' => 'Mailgun not configured'];
        }

        $url = "https://api.mailgun.net/v3/{$config['domain']}/messages";

        $data = [
            'from'    => "{$config['from_name']} <{$config['from_email']}>",
            'to'      => $to,
            'subject' => $subject,
            'html'    => $htmlBody,
            'text'    => $textBody ?: strip_tags($htmlBody),
        ];

        // Add to_email as BCC for admin copy
        $adminEmail = getSetting('to_email');
        if ($adminEmail && $adminEmail !== $to) {
            $data['bcc'] = $adminEmail;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_USERPWD        => "api:{$config['api_key']}",
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        $result = json_decode($response, true);
        return [
            'success' => $httpCode === 200,
            'response' => $result,
            'http_code' => $httpCode,
        ];
    }

    public static function sendBookingConfirmation(array $appointment): array {
        $serviceName = $appointment['service_name'] ?? 'Appointment';
        $subject = "Booking Confirmed - {$appointment['booking_ref']}";

        $html = self::buildConfirmationEmail($appointment);
        $result = self::send($appointment['customer_email'], $subject, $html);

        // Log — use NULL for appointment_id when 0 (test emails) to avoid FK constraint
        $apptIdForLog = !empty($appointment['id']) ? (int)$appointment['id'] : null;
        try {
            Database::insert(
                "INSERT INTO email_logs (appointment_id, to_email, subject, status, error_message) VALUES (?, ?, ?, ?, ?)",
                [
                    $apptIdForLog,
                    $appointment['customer_email'],
                    $subject,
                    $result['success'] ? 'sent' : 'failed',
                    $result['error'] ?? null,
                ]
            );
        } catch (Throwable $logErr) {
            // Never let log failure break the email flow
            error_log('email_logs insert failed: ' . $logErr->getMessage());
        }

        // Notify admin
        $adminEmail = getSetting('to_email');
        if ($adminEmail) {
            $adminSubject = "New Booking: {$appointment['booking_ref']} - {$appointment['customer_name']}";
            $adminHtml = self::buildAdminNotificationEmail($appointment);
            self::send($adminEmail, $adminSubject, $adminHtml);
        }

        return $result;
    }

    private static function buildConfirmationEmail(array $appt): string {
        $siteName = getSetting('site_name', 'BookFlow');
        $color = getSetting('dashboard_primary_color', '#6366f1');
        $date = date('F j, Y', strtotime($appt['appointment_date']));
        $time = date('g:i A', strtotime($appt['appointment_time']));

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;font-family:'Segoe UI',Arial,sans-serif;background:#f8fafc;">
  <div style="max-width:600px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    <div style="background:{$color};padding:40px 32px;text-align:center;">
      <h1 style="color:#fff;margin:0;font-size:28px;font-weight:700;">{$siteName}</h1>
      <p style="color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:16px;">Booking Confirmed!</p>
    </div>
    <div style="padding:40px 32px;">
      <p style="color:#374151;font-size:16px;margin:0 0 24px;">Hi <strong>{$appt['customer_name']}</strong>,</p>
      <p style="color:#6b7280;font-size:15px;line-height:1.6;margin:0 0 32px;">Your appointment has been confirmed. Here are your booking details:</p>
      
      <div style="background:#f8fafc;border-radius:12px;padding:24px;margin:0 0 24px;">
        <table style="width:100%;border-collapse:collapse;">
          <tr><td style="padding:8px 0;color:#9ca3af;font-size:14px;">Booking Ref</td><td style="padding:8px 0;color:#111827;font-weight:600;text-align:right;">{$appt['booking_ref']}</td></tr>
          <tr><td style="padding:8px 0;color:#9ca3af;font-size:14px;">Service</td><td style="padding:8px 0;color:#111827;font-weight:600;text-align:right;">{$appt['service_name']}</td></tr>
          <tr><td style="padding:8px 0;color:#9ca3af;font-size:14px;">Date</td><td style="padding:8px 0;color:#111827;font-weight:600;text-align:right;">{$date}</td></tr>
          <tr><td style="padding:8px 0;color:#9ca3af;font-size:14px;">Time</td><td style="padding:8px 0;color:#111827;font-weight:600;text-align:right;">{$time}</td></tr>
        </table>
      </div>
      
      <p style="color:#6b7280;font-size:14px;line-height:1.6;">If you need to make changes, please contact us as soon as possible.</p>
    </div>
    <div style="background:#f8fafc;padding:24px 32px;text-align:center;border-top:1px solid #e5e7eb;">
      <p style="color:#9ca3af;font-size:13px;margin:0;">© {$siteName}. All rights reserved.</p>
    </div>
  </div>
</body>
</html>
HTML;
    }

    private static function buildAdminNotificationEmail(array $appt): string {
        $siteName = getSetting('site_name', 'BookFlow');
        $color = getSetting('dashboard_primary_color', '#6366f1');
        $date = date('F j, Y', strtotime($appt['appointment_date']));
        $time = date('g:i A', strtotime($appt['appointment_time']));

        return <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family:'Segoe UI',Arial,sans-serif;background:#f8fafc;margin:0;padding:0;">
<div style="max-width:600px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
  <div style="background:{$color};padding:32px;text-align:center;">
    <h2 style="color:#fff;margin:0;">New Booking Received</h2>
  </div>
  <div style="padding:32px;">
    <table style="width:100%;border-collapse:collapse;">
      <tr><td style="padding:10px 0;color:#6b7280;font-size:14px;border-bottom:1px solid #f3f4f6;">Reference</td><td style="padding:10px 0;font-weight:600;text-align:right;border-bottom:1px solid #f3f4f6;">{$appt['booking_ref']}</td></tr>
      <tr><td style="padding:10px 0;color:#6b7280;font-size:14px;border-bottom:1px solid #f3f4f6;">Customer</td><td style="padding:10px 0;font-weight:600;text-align:right;border-bottom:1px solid #f3f4f6;">{$appt['customer_name']}</td></tr>
      <tr><td style="padding:10px 0;color:#6b7280;font-size:14px;border-bottom:1px solid #f3f4f6;">Email</td><td style="padding:10px 0;font-weight:600;text-align:right;border-bottom:1px solid #f3f4f6;">{$appt['customer_email']}</td></tr>
      <tr><td style="padding:10px 0;color:#6b7280;font-size:14px;border-bottom:1px solid #f3f4f6;">Phone</td><td style="padding:10px 0;font-weight:600;text-align:right;border-bottom:1px solid #f3f4f6;">{$appt['customer_phone']}</td></tr>
      <tr><td style="padding:10px 0;color:#6b7280;font-size:14px;border-bottom:1px solid #f3f4f6;">Service</td><td style="padding:10px 0;font-weight:600;text-align:right;border-bottom:1px solid #f3f4f6;">{$appt['service_name']}</td></tr>
      <tr><td style="padding:10px 0;color:#6b7280;font-size:14px;">Date &amp; Time</td><td style="padding:10px 0;font-weight:600;text-align:right;">{$date} at {$time}</td></tr>
    </table>
  </div>
</div>
</body>
</html>
HTML;
    }
}
