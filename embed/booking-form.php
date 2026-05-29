
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mailer.php';

$isEmbed = !empty($_GET['embed']) || !empty($_SERVER['HTTP_REFERER']);
$templateId = (int)getSetting('active_template_id', '1');
$style = Database::fetchOne("SELECT * FROM form_styles WHERE id=1");
$siteName = getSetting('site_name', 'BookFlow');
$redirectUrl = getSetting('redirect_url', '');

// Get services, blocked dates
$services = Database::fetchAll("SELECT * FROM services WHERE is_active=1 ORDER BY name");
$blockedDates = array_column(Database::fetchAll("SELECT blocked_date FROM blocked_dates"), 'blocked_date');
$fields = Database::fetchAll("SELECT * FROM form_fields WHERE is_active=1 ORDER BY sort_order, id");

// Style vars
$primaryColor = $style['primary_color'] ?? '#6366f1';
$buttonColor = $style['button_color'] ?? '#6366f1';
$buttonTextColor = $style['button_text_color'] ?? '#ffffff';
$buttonRadius = $style['button_border_radius'] ?? '8px';
$fieldRadius = $style['field_border_radius'] ?? '6px';
$fieldPadding = $style['field_padding'] ?? '12px 16px';
$fontFamily = $style['font_family'] ?? 'Inter';
$bgColor = $style['background_color'] ?? '#ffffff';
$textColor = $style['text_color'] ?? '#1f2937';

// Template classes
$templates = [
    1 => ['wrapper' => 'shadow-lg', 'header' => '', 'field_class' => ''],
    2 => ['wrapper' => 'shadow-2xl bg-gradient-to-br from-indigo-500 to-purple-600', 'header' => 'text-white', 'field_class' => ''],
    3 => ['wrapper' => 'shadow-sm border-2 border-gray-200', 'header' => '', 'field_class' => ''],
];
$tpl = $templates[$templateId] ?? $templates[1];

$error = '';
$success = false;
$booking = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceId = (int)($_POST['service_id'] ?? 0) ?: null;
    $slotId = (int)($_POST['slot_id'] ?? 0) ?: null;
    $date = $_POST['appointment_date'] ?? '';
    $time = $_POST['appointment_time'] ?? '';
    $name = sanitize($_POST['customer_name'] ?? '');
    $email = trim($_POST['customer_email'] ?? '');
    $phone = sanitize($_POST['customer_phone'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    // Validate
    if (!$name || !$email || !$date || !$time) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (in_array($date, $blockedDates)) {
        $error = 'The selected date is not available. Please choose another date.';
    } else {
        // Check slot availability
        if ($slotId) {
            $slot = Database::fetchOne("SELECT * FROM time_slots WHERE id=? AND is_active=1", [$slotId]);
            if (!$slot) { $error = 'Selected time slot is no longer available.'; }
            else {
                $bookedCount = Database::fetchOne("SELECT COUNT(*) as c FROM appointments WHERE time_slot_id=? AND status!='cancelled'", [$slotId])['c'];
                if ($bookedCount >= $slot['max_bookings']) $error = 'This slot is fully booked. Please select another.';
            }
        }

        if (!$error) {
            // Collect custom field data
            $formData = [];
            foreach ($fields as $f) {
                $key = $f['field_name'];
                if (isset($_POST[$key])) {
                    $val = is_array($_POST[$key]) ? $_POST[$key] : sanitize($_POST[$key]);
                    if ($f['is_required'] && empty($val)) {
                        $error = htmlspecialchars($f['field_label']) . ' is required.';
                        break;
                    }
                    $formData[$key] = $val;
                }
            }

            if (!$error) {
                $ref = generateRef();
                $id = Database::insert(
                    "INSERT INTO appointments (booking_ref,service_id,time_slot_id,appointment_date,appointment_time,customer_name,customer_email,customer_phone,notes,form_data) VALUES (?,?,?,?,?,?,?,?,?,?)",
                    [$ref, $serviceId, $slotId ?: null, $date, $time, $name, $email, $phone, $notes, json_encode($formData)]
                );

                // Get service name
                $serviceName = '';
                if ($serviceId) {
                    $svc = Database::fetchOne("SELECT name FROM services WHERE id=?", [$serviceId]);
                    $serviceName = $svc['name'] ?? '';
                }

                $booking = compact('id','ref','name','email','phone','date','time','serviceName');

                // Send email
                Mailer::sendBookingConfirmation([
                    'id' => $id,
                    'booking_ref' => $ref,
                    'customer_name' => $name,
                    'customer_email' => $email,
                    'customer_phone' => $phone,
                    'service_name' => $serviceName,
                    'appointment_date' => $date,
                    'appointment_time' => $time,
                ]);

                $success = true;

                if ($redirectUrl) {
                    // Break out of iframe — redirect the TOP window, not just the iframe
                    $fullRedirect = $redirectUrl . (strpos($redirectUrl,'?')===false ? '?' : '&') . 'ref=' . urlencode($ref);
                    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
                    echo '<script>';
                    echo 'try { window.top.location.href = ' . json_encode($fullRedirect) . '; }';
                    echo 'catch(e) { window.location.href = ' . json_encode($fullRedirect) . '; }';
                    echo '</script>';
                    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($fullRedirect) . '"></noscript>';
                    echo '</body></html>';
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($siteName) ?> — Book an Appointment</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=<?= urlencode($fontFamily) ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
<style>
  * { font-family: '<?= $fontFamily ?>', sans-serif; box-sizing: border-box; }
  :root {
    --primary: <?= $primaryColor ?>;
    --btn-color: <?= $buttonColor ?>;
    --btn-text: <?= $buttonTextColor ?>;
    --btn-radius: <?= $buttonRadius ?>;
    --field-radius: <?= $fieldRadius ?>;
    --field-padding: <?= $fieldPadding ?>;
    --bg: <?= $bgColor ?>;
    --text: <?= $textColor ?>;
  }
  body { background: #f1f5f9; color: var(--text); margin: 0; padding: 0; }
  <?php if ($templateId == 2): ?>
  body { background: transparent; }
  .form-wrapper { background: transparent !important; }
  .step-card { background: rgba(255,255,255,0.15) !important; backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.3) !important; }
  .field-input { background: rgba(255,255,255,0.2) !important; border-color: rgba(255,255,255,0.4) !important; color: #fff !important; }
  .field-input::placeholder { color: rgba(255,255,255,0.6) !important; }
  .form-label { color: rgba(255,255,255,0.9) !important; }
  .section-title { color: #fff !important; }
  <?php endif; ?>
  .field-input { border: 1.5px solid #e5e7eb; border-radius: var(--field-radius); padding: var(--field-padding); width: 100%; font-size: 14px; transition: border-color 0.2s, box-shadow 0.2s; outline: none; }
  .field-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
  .btn-submit { background: var(--btn-color); color: var(--btn-text); border-radius: var(--btn-radius); padding: 14px 24px; font-size: 15px; font-weight: 600; width: 100%; border: none; cursor: pointer; transition: all 0.2s; }
  .btn-submit:hover { filter: brightness(1.08); transform: translateY(-1px); }
  .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
  .service-card { border: 2px solid #e5e7eb; border-radius: 12px; padding: 14px; cursor: pointer; transition: all 0.2s; }
  .service-card:hover { border-color: var(--primary); background: rgba(99,102,241,0.04); }
  .service-card.selected { border-color: var(--primary); background: rgba(99,102,241,0.08); }
  .time-slot { border: 1.5px solid #e5e7eb; border-radius: 8px; padding: 8px 12px; cursor: pointer; text-align: center; font-size: 13px; font-weight: 500; transition: all 0.2s; }
  .time-slot:hover:not(.disabled) { border-color: var(--primary); background: rgba(99,102,241,0.06); }
  .time-slot.selected { border-color: var(--primary); background: var(--primary); color: #fff; }
  .time-slot.disabled { opacity: 0.4; cursor: not-allowed; text-decoration: line-through; }
  .step-indicator { display: flex; align-items: center; gap: 8px; margin-bottom: 24px; }
  .step-dot { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-center; font-size: 12px; font-weight: 700; transition: all 0.3s; flex-shrink: 0; }
  .step-dot.active { background: var(--primary); color: #fff; box-shadow: 0 4px 12px rgba(99,102,241,0.35); }
  .step-dot.done { background: #10b981; color: #fff; }
  .step-dot.pending { background: #e5e7eb; color: #9ca3af; }
  .step-line { flex: 1; height: 2px; background: #e5e7eb; border-radius: 1px; }
  .step-line.done { background: var(--primary); }
  .calendar-grid { display: grid; grid-template-columns: repeat(7,1fr); gap: 4px; }
  .cal-day { aspect-ratio: 1; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 500; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
  .cal-day:hover:not(.disabled):not(.other-month) { background: rgba(99,102,241,0.1); color: var(--primary); }
  .cal-day.selected { background: var(--primary); color: #fff; font-weight: 700; }
  .cal-day.today:not(.selected) { border: 2px solid var(--primary); color: var(--primary); font-weight: 700; }
  .cal-day.disabled { opacity: 0.3; cursor: not-allowed; text-decoration: line-through; }
  .cal-day.other-month { opacity: 0.3; }
  .cal-day.has-slots { position: relative; }
  .cal-day.has-slots::after { content: ''; position: absolute; bottom: 3px; left: 50%; transform: translateX(-50%); width: 4px; height: 4px; border-radius: 50%; background: #10b981; }
  @keyframes fadeSlide { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
  .step-content { animation: fadeSlide 0.3s ease; }
  .success-icon { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #059669); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; animation: bounce 0.5s ease; }
  @keyframes bounce { 0% { transform: scale(0); } 60% { transform: scale(1.1); } 100% { transform: scale(1); } }
</style>
</head>
<body>
<div class="min-h-screen py-8 px-4 flex items-start justify-center <?= $templateId == 2 ? 'bg-gradient-to-br from-indigo-500 to-purple-700' : 'bg-slate-50' ?>">
<div class="w-full max-w-xl form-wrapper">

<?php if ($success && $booking): ?>
<!-- Success State -->
<div class="bg-white rounded-2xl p-8 shadow-xl text-center">
  <div class="success-icon"><i class="fa-solid fa-check text-white text-2xl"></i></div>
  <h2 class="text-2xl font-bold text-gray-900 mb-2">Booking Confirmed!</h2>
  <p class="text-gray-500 mb-6">You'll receive a confirmation email shortly.</p>
  <div class="bg-gray-50 rounded-xl p-5 text-left space-y-3 mb-6">
    <div class="flex items-center justify-between text-sm">
      <span class="text-gray-500">Reference</span>
      <span class="font-mono font-bold text-gray-800"><?= htmlspecialchars($booking['ref']) ?></span>
    </div>
    <div class="flex items-center justify-between text-sm">
      <span class="text-gray-500">Name</span>
      <span class="font-semibold text-gray-800"><?= htmlspecialchars($booking['name']) ?></span>
    </div>
    <div class="flex items-center justify-between text-sm">
      <span class="text-gray-500">Date</span>
      <span class="font-semibold text-gray-800"><?= date('F j, Y', strtotime($booking['date'])) ?></span>
    </div>
    <div class="flex items-center justify-between text-sm">
      <span class="text-gray-500">Time</span>
      <span class="font-semibold text-gray-800"><?= date('g:i A', strtotime($booking['time'])) ?></span>
    </div>
  </div>
  <button onclick="location.reload()" class="btn-submit">Book Another Appointment</button>
</div>
<script>
// Notify parent window of successful booking (works even without a redirect URL)
(function() {
  var bookingData = {
    type: 'bookflow:booked',
    ref:  '<?= htmlspecialchars($booking["ref"]) ?>',
    name: '<?= htmlspecialchars(addslashes($booking["name"])) ?>',
    date: '<?= htmlspecialchars($booking["date"]) ?>',
    time: '<?= htmlspecialchars($booking["time"]) ?>'
  };
  try { window.parent.postMessage(bookingData, '*'); } catch(e) {}
  try { window.top.postMessage(bookingData, '*'); } catch(e) {}

  // If parent set a redirect via data attribute on the iframe, honour it
  try {
    var iframeEl = window.parent.document.querySelector('iframe[src*="booking-form"], iframe[src*="/book"]');
    var parentRedirect = iframeEl ? (iframeEl.getAttribute('data-redirect') || iframeEl.getAttribute('data-success-url')) : null;
    if (parentRedirect) {
      var sep = parentRedirect.indexOf('?') === -1 ? '?' : '&';
      window.top.location.href = parentRedirect + sep + 'ref=' + encodeURIComponent(bookingData.ref);
    }
  } catch(e) {}
})();
</script>

<?php else: ?>
<!-- Booking Form -->
<div class="bg-white rounded-2xl shadow-xl overflow-hidden <?= $tpl['wrapper'] ?? '' ?>">
  <!-- Header -->
  <div class="px-7 pt-7 pb-5 <?= $templateId == 2 ? 'bg-gradient-to-br from-indigo-500 to-purple-600' : '' ?>">
    <h1 class="text-2xl font-bold <?= $templateId == 2 ? 'text-white' : 'text-gray-900' ?>"><?= htmlspecialchars($siteName) ?></h1>
    <p class="text-sm <?= $templateId == 2 ? 'text-white/70' : 'text-gray-500' ?> mt-1">Book your appointment below</p>
  </div>

  <?php if ($error): ?>
  <div class="mx-7 mt-4 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
    <i class="fa-solid fa-exclamation-circle flex-shrink-0"></i><?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <form method="POST" id="bookingForm" class="px-7 pb-7">
    <!-- Step indicator -->
    <div class="step-indicator mt-5">
      <?php $steps = ['Service','Date & Time','Your Info']; foreach ($steps as $i => $s): ?>
      <?php if ($i > 0): ?><div class="step-line" id="line-<?= $i ?>"></div><?php endif; ?>
      <div class="step-dot <?= $i===0?'active':'pending' ?>" id="dot-<?= $i ?>">
        <span id="dot-icon-<?= $i ?>"><?= $i+1 ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Step 1: Service -->
    <div id="step-0" class="step-content">
      <h3 class="font-bold text-gray-900 mb-4 section-title">Select a Service</h3>
      <div class="space-y-3" id="serviceList">
        <?php foreach ($services as $svc): ?>
        <label class="service-card block">
          <input type="radio" name="service_id" value="<?= $svc['id'] ?>" class="sr-only" onchange="selectService(<?= $svc['id'] ?>, '<?= addslashes($svc['name']) ?>')">
          <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background:<?= $svc['color'] ?>20">
              <i class="fa-solid fa-briefcase-medical" style="color:<?= $svc['color'] ?>"></i>
            </div>
            <div class="flex-1">
              <div class="font-semibold text-gray-800"><?= htmlspecialchars($svc['name']) ?></div>
              <?php if ($svc['description']): ?>
              <div class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($svc['description']) ?></div>
              <?php endif; ?>
            </div>
            <div class="text-right flex-shrink-0">
              <div class="font-bold text-gray-800">$<?= number_format($svc['price'],0) ?></div>
              <div class="text-xs text-gray-400"><?= $svc['duration'] ?>min</div>
            </div>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
      <button type="button" onclick="goStep(1)" class="btn-submit mt-5">Continue →</button>
    </div>

    <!-- Step 2: Date & Time -->
    <div id="step-1" class="step-content hidden">
      <h3 class="font-bold text-gray-900 mb-4 section-title">Pick a Date & Time</h3>

      <!-- Calendar -->
      <div class="mb-4">
        <div class="flex items-center justify-between mb-3">
          <button type="button" id="prevMonth" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-500">
            <i class="fa-solid fa-chevron-left text-xs"></i>
          </button>
          <span id="calMonthLabel" class="text-sm font-bold text-gray-800"></span>
          <button type="button" id="nextMonth" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-500">
            <i class="fa-solid fa-chevron-right text-xs"></i>
          </button>
        </div>
        <div class="calendar-grid mb-1">
          <?php foreach (['S','M','T','W','T','F','S'] as $d): ?>
          <div class="text-center text-xs font-semibold text-gray-400 py-1"><?= $d ?></div>
          <?php endforeach; ?>
        </div>
        <div class="calendar-grid" id="calDays"></div>
        <input type="hidden" name="appointment_date" id="appointmentDate">
      </div>

      <!-- Time Slots -->
      <div id="timeSlotsSection" class="hidden">
        <h4 class="text-sm font-semibold text-gray-700 mb-2">Available Times</h4>
        <div class="grid grid-cols-4 gap-2" id="timeSlots"></div>
        <input type="hidden" name="appointment_time" id="appointmentTime">
        <input type="hidden" name="slot_id" id="slotId">
      </div>

      <div id="noSlots" class="hidden text-center py-6 text-gray-400 text-sm">No available slots for this date.</div>

      <div class="flex gap-3 mt-5">
        <button type="button" onclick="goStep(0)" class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition">← Back</button>
        <button type="button" onclick="goStep(2)" id="step2Next" class="flex-1 btn-submit" disabled>Continue →</button>
      </div>
    </div>

    <!-- Step 3: Customer Info -->
    <div id="step-2" class="step-content hidden">
      <h3 class="font-bold text-gray-900 mb-4 section-title">Your Information</h3>
      <div class="space-y-4">
        <?php
        $coreFields = [
          ['name'=>'customer_name','label'=>'Full Name','type'=>'text','required'=>true,'placeholder'=>'Enter your full name'],
          ['name'=>'customer_email','label'=>'Email Address','type'=>'email','required'=>true,'placeholder'=>'Enter your email'],
          ['name'=>'customer_phone','label'=>'Phone Number','type'=>'tel','required'=>false,'placeholder'=>'Enter your phone number'],
        ];
        foreach ($coreFields as $f):
        ?>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5 form-label">
            <?= $f['label'] ?><?= $f['required'] ? ' <span class="text-red-500">*</span>' : '' ?>
          </label>
          <input type="<?= $f['type'] ?>" name="<?= $f['name'] ?>" placeholder="<?= $f['placeholder'] ?>" <?= $f['required'] ? 'required' : '' ?>
            value="<?= htmlspecialchars($_POST[$f['name']] ?? '') ?>"
            class="field-input">
        </div>
        <?php endforeach; ?>

        <!-- Custom Fields -->
        <?php foreach ($fields as $field):
          if (in_array($field['field_name'], ['customer_name','customer_email','customer_phone','notes'])) continue;
        ?>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5 form-label">
            <?= htmlspecialchars($field['field_label']) ?><?= $field['is_required'] ? ' <span class="text-red-500">*</span>' : '' ?>
          </label>
          <?php
          $fieldName = htmlspecialchars($field['field_name']);
          $placeholder = htmlspecialchars($field['placeholder'] ?? '');
          $required = $field['is_required'] ? 'required' : '';
          $val = htmlspecialchars($_POST[$field['field_name']] ?? '');
          $opts = $field['field_options'] ? explode("\n", trim($field['field_options'])) : [];

          switch ($field['field_type']):
            case 'textarea': echo "<textarea name=\"$fieldName\" placeholder=\"$placeholder\" $required rows=\"3\" class=\"field-input resize-none\">$val</textarea>"; break;
            case 'select':
              echo "<select name=\"$fieldName\" $required class=\"field-input\"><option value=\"\">Select...</option>";
              foreach ($opts as $o) { $o = trim($o); echo "<option value=\"$o\" " . ($_POST[$field['field_name']] ?? '' === $o ? 'selected' : '') . ">$o</option>"; }
              echo "</select>"; break;
            case 'checkbox':
              echo "<div class=\"space-y-2\">";
              foreach ($opts as $o) { $o = trim($o); echo "<label class=\"flex items-center gap-2 cursor-pointer\"><input type=\"checkbox\" name=\"{$fieldName}[]\" value=\"$o\" class=\"w-4 h-4 rounded\"><span class=\"text-sm text-gray-700\">$o</span></label>"; }
              echo "</div>"; break;
            case 'radio':
              echo "<div class=\"space-y-2\">";
              foreach ($opts as $o) { $o = trim($o); echo "<label class=\"flex items-center gap-2 cursor-pointer\"><input type=\"radio\" name=\"$fieldName\" value=\"$o\" $required class=\"w-4 h-4\"><span class=\"text-sm text-gray-700\">$o</span></label>"; }
              echo "</div>"; break;
            default: echo "<input type=\"{$field['field_type']}\" name=\"$fieldName\" placeholder=\"$placeholder\" $required value=\"$val\" class=\"field-input\">"; break;
          endswitch;
          ?>
        </div>
        <?php endforeach; ?>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5 form-label">Additional Notes</label>
          <textarea name="notes" placeholder="Any special requests..." rows="3" class="field-input resize-none"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="flex gap-3 mt-5">
        <button type="button" onclick="goStep(1)" class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition">← Back</button>
        <button type="submit" class="flex-1 btn-submit" id="submitBtn">
          <i class="fa-solid fa-calendar-check mr-2"></i>Confirm Booking
        </button>
      </div>
    </div>
  </form>
</div>
<?php endif; ?>
</div>
</body>

<script>
const blockedDates = <?= json_encode($blockedDates) ?>;
let selectedService = null;
let selectedDate = null;
let selectedTime = null;
let currentYear = new Date().getFullYear();
let currentMonth = new Date().getMonth();
const today = new Date().toISOString().slice(0,10);
const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];

function goStep(n) {
  // Validate step 0
  if (n === 1 && !document.querySelector('input[name="service_id"]:checked')) {
    alert('Please select a service to continue.');
    return;
  }
  // Validate step 1
  if (n === 2 && (!selectedDate || !selectedTime)) {
    alert('Please select a date and time to continue.');
    return;
  }
  [0,1,2].forEach(i => {
    document.getElementById('step-'+i).classList.toggle('hidden', i !== n);
    const dot = document.getElementById('dot-'+i);
    const icon = document.getElementById('dot-icon-'+i);
    if (i < n) { dot.className = 'step-dot done'; icon.innerHTML = '<i class="fa-solid fa-check text-xs"></i>'; }
    else if (i === n) { dot.className = 'step-dot active'; icon.textContent = i+1; }
    else { dot.className = 'step-dot pending'; icon.textContent = i+1; }
    if (i < 3) {
      const line = document.getElementById('line-'+i);
      if (line) line.classList.toggle('done', i < n);
    }
  });
  if (n === 1) renderCalendar();
}

function selectService(id, name) {
  selectedService = id;
  document.querySelectorAll('.service-card').forEach(c => c.classList.remove('selected'));
  const checked = document.querySelector('input[name="service_id"]:checked');
  if (checked) checked.closest('.service-card').classList.add('selected');
}

function renderCalendar() {
  const label = document.getElementById('calMonthLabel');
  const daysEl = document.getElementById('calDays');
  label.textContent = months[currentMonth] + ' ' + currentYear;

  const first = new Date(currentYear, currentMonth, 1).getDay();
  const daysInMonth = new Date(currentYear, currentMonth+1, 0).getDate();
  let html = '';
  for (let i = 0; i < first; i++) html += '<div class="cal-day other-month"></div>';

  for (let d = 1; d <= daysInMonth; d++) {
    const ds = `${currentYear}-${String(currentMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const isBlocked = blockedDates.includes(ds);
    const isPast = ds < today;
    const isToday = ds === today;
    const isSel = ds === selectedDate;
    let cls = 'cal-day ';
    if (isSel) cls += 'selected';
    else if (isBlocked || isPast) cls += 'disabled';
    else if (isToday) cls += 'today';
    html += `<div class="${cls}" ${!isBlocked && !isPast ? `onclick="selectDate('${ds}')"` : ''}>${d}</div>`;
  }
  daysEl.innerHTML = html;
}

function selectDate(dateStr) {
  selectedDate = dateStr;
  selectedTime = null;
  document.getElementById('appointmentDate').value = dateStr;
  document.getElementById('appointmentTime').value = '';
  document.getElementById('slotId').value = '';
  document.getElementById('step2Next').disabled = true;
  renderCalendar();
  loadTimeSlots(dateStr);
}

function loadTimeSlots(date) {
  const serviceId = document.querySelector('input[name="service_id"]:checked')?.value || '';
  fetch(`<?= BASE_URL ?>/api/slots?date=${date}&service_id=${serviceId}`)
    .then(r => r.json())
    .then(data => {
      const slotsEl = document.getElementById('timeSlots');
      const section = document.getElementById('timeSlotsSection');
      const noSlots = document.getElementById('noSlots');

      if (!data.length) { section.classList.add('hidden'); noSlots.classList.remove('hidden'); return; }
      section.classList.remove('hidden');
      noSlots.classList.add('hidden');

      slotsEl.innerHTML = data.map(s => {
        const isFull = s.booked >= s.max_bookings;
        return `<div class="time-slot ${isFull?'disabled':''}" ${!isFull?`onclick="selectTime('${s.time}',${s.id})"`:''}>
          ${formatTime(s.time)}
          ${isFull ? '<br><span style="font-size:10px;opacity:0.7">Full</span>' : ''}
        </div>`;
      }).join('');
    });
}

function selectTime(time, slotId) {
  selectedTime = time;
  document.getElementById('appointmentTime').value = time;
  document.getElementById('slotId').value = slotId;
  document.querySelectorAll('.time-slot').forEach(el => el.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  document.getElementById('step2Next').disabled = false;
}

function formatTime(t) {
  const [h,m] = t.split(':');
  const hh = parseInt(h);
  return `${hh%12||12}:${m} ${hh<12?'AM':'PM'}`;
}

document.getElementById('prevMonth').addEventListener('click', () => {
  currentMonth--; if (currentMonth < 0) { currentMonth = 11; currentYear--; }
  renderCalendar();
});
document.getElementById('nextMonth').addEventListener('click', () => {
  currentMonth++; if (currentMonth > 11) { currentMonth = 0; currentYear++; }
  renderCalendar();
});

document.getElementById('bookingForm')?.addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Booking...';
});

// Animate on load
gsap.from('.step-content > *', { opacity: 0, y: 15, duration: 0.4, stagger: 0.06, ease: 'power2.out' });
</script>
</html>
