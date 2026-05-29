<?php
/**
 * AJAX Settings Save Handler
 * Completely independent from settings.php — no redirects, pure JSON responses.
 */
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();

header('Content-Type: application/json');

$section = $_POST['section'] ?? '';
$csrf    = $_POST['_csrf']   ?? '';

if (!Auth::verifyCsrf($csrf)) {
    echo json_encode(['ok' => false, 'msg' => 'Security token expired. Please refresh the page.']);
    exit;
}

try {
    switch ($section) {

        case 'general':
            setSetting('site_name', sanitize($_POST['site_name'] ?? ''));
            setSetting('timezone',  sanitize($_POST['timezone']  ?? ''));
            // redirect_url — only store if it's a real https:// URL
            $ru = trim($_POST['redirect_url'] ?? '');
            if ($ru && (strpos($ru, 'http') !== 0 || !filter_var($ru, FILTER_VALIDATE_URL))) {
                $ru = ''; // reject invalid value silently
            }
            setSetting('redirect_url', $ru);
            if (!empty($_FILES['site_logo']['name'])) {
                $ext = strtolower(pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','svg','webp'])) {
                    $dir = APP_PATH . '/assets/img/';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    $fn = 'logo_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $dir . $fn)) {
                        setSetting('site_logo', 'assets/img/' . $fn);
                    }
                }
            }
            echo json_encode(['ok' => true, 'msg' => 'General settings saved']);
            break;

        case 'branding':
            setSetting('dashboard_primary_color',   sanitize($_POST['dashboard_primary_color']   ?? '#22c55e'));
            setSetting('dashboard_secondary_color', sanitize($_POST['dashboard_secondary_color'] ?? '#16a34a'));
            setSetting('dashboard_sidebar_color',   sanitize($_POST['dashboard_sidebar_color']   ?? '#14532d'));
            setSetting('dashboard_font',            sanitize($_POST['dashboard_font']            ?? 'DM Sans'));
            echo json_encode(['ok' => true, 'msg' => 'Branding saved — refresh to see changes']);
            break;

        case 'email':
            setSetting('from_email', sanitize($_POST['from_email'] ?? ''));
            setSetting('from_name',  sanitize($_POST['from_name']  ?? ''));
            setSetting('to_email',   sanitize($_POST['to_email']   ?? ''));
            echo json_encode(['ok' => true, 'msg' => 'Email settings saved']);
            break;

        case 'mailgun':
            setSetting('mailgun_api_key', sanitize($_POST['mailgun_api_key'] ?? ''));
            setSetting('mailgun_domain',  sanitize($_POST['mailgun_domain']  ?? ''));
            echo json_encode(['ok' => true, 'msg' => 'Mailgun settings saved']);
            break;

        case 'whatsapp':
            setSetting('wa_provider',          sanitize($_POST['wa_provider']          ?? 'disabled'));
            setSetting('wa_owner_number',       sanitize($_POST['wa_owner_number']      ?? ''));
            setSetting('wa_twilio_sid',         sanitize($_POST['wa_twilio_sid']        ?? ''));
            setSetting('wa_twilio_token',       sanitize($_POST['wa_twilio_token']      ?? ''));
            setSetting('wa_twilio_from',        sanitize($_POST['wa_twilio_from']       ?? ''));
            setSetting('wa_callmebot_key',      sanitize($_POST['wa_callmebot_key']     ?? ''));
            setSetting('wa_twilio_sandbox_word',sanitize($_POST['wa_twilio_sandbox_word']?? ''));
            echo json_encode(['ok' => true, 'msg' => 'WhatsApp settings saved']);
            break;

        case 'account':
            $user     = Auth::currentUser();
            $name     = sanitize($_POST['name']  ?? '');
            $email    = sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            if ($password && strlen($password) < 8) {
                echo json_encode(['ok' => false, 'msg' => 'Password must be at least 8 characters']);
                exit;
            }
            $sql    = 'UPDATE users SET name=?, email=?';
            $params = [$name, $email];
            if ($password) {
                $sql    .= ', password=?';
                $params[] = Auth::hashPassword($password);
            }
            $params[] = $user['id'];
            Database::update($sql . ' WHERE id=?', $params);
            echo json_encode(['ok' => true, 'msg' => 'Account updated']);
            break;

        case 'test_email':
            require_once __DIR__ . '/../includes/mailer.php';
            $result = Mailer::sendBookingConfirmation([
                'id'               => null,
                'booking_ref'      => 'BF-TEST001',
                'customer_name'    => 'Test Customer',
                'customer_email'   => getSetting('to_email'),
                'customer_phone'   => '+1234567890',
                'service_name'     => 'Test Service',
                'appointment_date' => date('Y-m-d'),
                'appointment_time' => date('H:i:s'),
            ]);
            echo json_encode([
                'ok'  => $result['success'],
                'msg' => $result['success'] ? 'Test email sent!' : 'Email failed: ' . ($result['error'] ?? 'Unknown'),
            ]);
            break;

        case 'test_whatsapp':
            require_once __DIR__ . '/../includes/whatsapp.php';
            $result = WhatsApp::sendTest();
            echo json_encode([
                'ok'  => $result['success'],
                'msg' => $result['success'] ? '✅ WhatsApp message sent!' : '❌ Failed: ' . ($result['error'] ?? 'Unknown'),
            ]);
            break;

        default:
            echo json_encode(['ok' => false, 'msg' => 'Unknown section: ' . $section]);
    }

} catch (Throwable $e) {
    error_log('ajax-settings error [' . $section . ']: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}
