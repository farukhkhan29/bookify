<?php
class WhatsApp
{
    public static function sendBookingAlert(array $appt, array $serviceNames = []): array
    {
        $provider = getSetting('wa_provider', '');
        if (!$provider || $provider === 'disabled') {
            return ['success' => false, 'error' => 'WhatsApp notifications disabled'];
        }
        $message = self::buildMessage($appt, $serviceNames);
        if ($provider === 'twilio')     return self::sendViaTwilio($message);
        if ($provider === 'callmebot')  return self::sendViaCallMeBot($message);
        return ['success' => false, 'error' => 'Unknown provider: ' . $provider];
    }

    private static function buildMessage(array $appt, array $serviceNames = []): string
    {
        $siteName = getSetting('site_name', 'BookFlow');
        $adminUrl = rtrim(BASE_URL, '/') . '/admin/appointments';
        $services = !empty($serviceNames) ? implode(', ', $serviceNames) : ($appt['service_name'] ?? 'N/A');
        $date     = !empty($appt['appointment_date']) ? date('D, M j Y', strtotime($appt['appointment_date'])) : 'N/A';
        $time     = !empty($appt['appointment_time']) ? date('g:i A',    strtotime($appt['appointment_time'])) : 'N/A';

        return "📅 *New Appointment — {$siteName}*\n\n"
             . "👤 *Customer:* " . ($appt['customer_name']  ?? 'N/A') . "\n"
             . "📧 *Email:* "    . ($appt['customer_email'] ?? 'N/A') . "\n"
             . "📞 *Phone:* "    . ($appt['customer_phone'] ?? 'N/A') . "\n"
             . "💼 *Service(s):* {$services}\n"
             . "📆 *Date:* {$date}\n"
             . "🕐 *Time:* {$time}\n"
             . "🔖 *Ref:* "      . ($appt['booking_ref']   ?? 'N/A') . "\n\n"
             . "🔗 View: {$adminUrl}?view=" . ($appt['id'] ?? '');
    }

    private static function sendViaTwilio(string $message): array
    {
        $accountSid = trim(getSetting('wa_twilio_sid',   ''));
        $authToken  = trim(getSetting('wa_twilio_token', ''));
        $fromRaw    = trim(getSetting('wa_twilio_from',  ''));
        $toRaw      = trim(getSetting('wa_owner_number', ''));

        // Validate all fields present
        $missing = [];
        if (!$accountSid) $missing[] = 'Account SID';
        if (!$authToken)  $missing[] = 'Auth Token';
        if (!$fromRaw)    $missing[] = 'From Number';
        if (!$toRaw)      $missing[] = 'Owner WhatsApp Number';
        if ($missing) {
            return ['success' => false, 'error' => 'Missing: ' . implode(', ', $missing)];
        }

        // Ensure + prefix on numbers, then add whatsapp: prefix
        $fromNum = $fromRaw;
        if (strpos($fromNum, 'whatsapp:') !== 0) {
            if (strpos($fromNum, '+') !== 0) $fromNum = '+' . $fromNum;
            $fromNum = 'whatsapp:' . $fromNum;
        }
        $toNum = $toRaw;
        if (strpos($toNum, 'whatsapp:') !== 0) {
            if (strpos($toNum, '+') !== 0) $toNum = '+' . $toNum;
            $toNum = 'whatsapp:' . $toNum;
        }

        $url  = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
        $data = http_build_query(['From' => $fromNum, 'To' => $toNum, 'Body' => $message]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_USERPWD        => "{$accountSid}:{$authToken}",
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['success' => false, 'error' => 'cURL error: ' . $curlErr];
        }

        $body = json_decode($response, true) ?? [];

        if ($httpCode >= 200 && $httpCode < 300 && !empty($body['sid'])) {
            return [
                'success' => true,
                'sid'     => $body['sid'],
                'status'  => $body['status'] ?? 'queued',
                'to'      => $toNum,
                'from'    => $fromNum,
            ];
        }

        // Return full Twilio error for debugging
        $twilioMsg = $body['message'] ?? '';
        $twilioCode = $body['code']  ?? '';
        $detail = "HTTP {$httpCode}";
        if ($twilioCode) $detail .= " | Twilio code {$twilioCode}";
        if ($twilioMsg)  $detail .= ": {$twilioMsg}";
        if ($twilioCode == 63007 || $twilioCode == 63016) {
            $detail .= " → Your WhatsApp number has NOT joined the sandbox. Send 'join <sandbox-word>' to +14155238886 on WhatsApp first.";
        }
        if ($twilioCode == 20003) {
            $detail .= " → Authentication failed. Check your Account SID and Auth Token.";
        }
        if ($twilioCode == 21211 || $twilioCode == 21614) {
            $detail .= " → Invalid phone number format. Use full international format e.g. +923001234567";
        }

        error_log("Twilio WhatsApp failed [{$httpCode}]: " . $response);
        return ['success' => false, 'error' => $detail, 'raw' => $body];
    }

    private static function sendViaCallMeBot(string $message): array
    {
        $phone  = trim(getSetting('wa_owner_number',  ''));
        $apiKey = trim(getSetting('wa_callmebot_key', ''));

        if (!$phone || !$apiKey) {
            return ['success' => false, 'error' => 'CallMeBot: phone number or API key is empty'];
        }

        $phone = ltrim($phone, '+'); // CallMeBot needs no + prefix

        $url = 'https://api.callmebot.com/whatsapp.php?' . http_build_query([
            'phone'  => $phone,
            'text'   => $message,
            'apikey' => $apiKey,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) return ['success' => false, 'error' => 'cURL: ' . $curlErr];
        $clean = trim(strip_tags($response));
        if ($httpCode === 200 && stripos($response, 'Message Sent') !== false) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => $clean ?: "HTTP {$httpCode}"];
    }

    public static function sendTest(): array
    {
        return self::sendBookingAlert([
            'id'               => null,
            'booking_ref'      => 'BF-TEST-' . strtoupper(substr(md5(time()), 0, 6)),
            'customer_name'    => 'Test Customer',
            'customer_email'   => 'test@example.com',
            'customer_phone'   => getSetting('wa_owner_number', '+923001234567'),
            'service_name'     => 'Eye Exam',
            'appointment_date' => date('Y-m-d'),
            'appointment_time' => '14:00:00',
        ], ['Eye Exam', 'Contact Lenses']);
    }

    /** Returns current config for display in settings debug panel */
    public static function getDebugInfo(): array
    {
        $sid    = getSetting('wa_twilio_sid',   '');
        $token  = getSetting('wa_twilio_token', '');
        $from   = getSetting('wa_twilio_from',  '');
        $to     = getSetting('wa_owner_number', '');
        return [
            'provider'    => getSetting('wa_provider', 'disabled'),
            'sid_set'     => !empty($sid),
            'sid_preview' => $sid ? substr($sid, 0, 6) . '...' . substr($sid, -4) : '(empty)',
            'token_set'   => !empty($token),
            'from'        => $from ?: '(empty)',
            'to'          => $to   ?: '(empty)',
            'from_built'  => $from ? 'whatsapp:' . (strpos($from, '+') === 0 ? $from : '+' . $from) : '(empty)',
            'to_built'    => $to   ? 'whatsapp:' . (strpos($to,   '+') === 0 ? $to   : '+' . $to)   : '(empty)',
        ];
    }
}
