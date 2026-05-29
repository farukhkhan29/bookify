<?php
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();
define('PAGE_TITLE', 'Time Slots');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST['_csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_slot') {
        $serviceId = (int)($_POST['service_id'] ?? 0) ?: null;
        $date = $_POST['slot_date'] ?? '';
        $times = $_POST['slot_times'] ?? [];
        $maxBookings = (int)($_POST['max_bookings'] ?? 1);
        $duration = (int)($_POST['slot_duration'] ?? 60);
        // Allow batch adding multiple times
        if (!is_array($times)) $times = [$times];
        foreach ($times as $time) {
            if ($date && $time) {
                $endTime = date('H:i:s', strtotime($time) + $duration * 60);
                Database::insert(
                    "INSERT IGNORE INTO time_slots (service_id, slot_date, start_time, end_time, max_bookings) VALUES (?,?,?,?,?)",
                    [$serviceId, $date, $time, $endTime, $maxBookings]
                );
            }
        }
        $_SESSION['flash_message'] = ['text'=>'Time slot(s) added','type'=>'success'];
    } elseif ($action === 'delete_slot') {
        $id = (int)($_POST['id'] ?? 0);
        Database::update("DELETE FROM time_slots WHERE id=?", [$id]);
        $_SESSION['flash_message'] = ['text'=>'Slot deleted','type'=>'success'];
    } elseif ($action === 'toggle_slot') {
        $id = (int)($_POST['id'] ?? 0);
        Database::update("UPDATE time_slots SET is_active = 1 - is_active WHERE id=?", [$id]);
    }
    redirect(BASE_URL . '/admin/slots' . (!empty($_GET['date']) ? '?date='.$_GET['date'] : ''));
}

$filterDate = $_GET['date'] ?? date('Y-m-d');
$services = Database::fetchAll("SELECT * FROM services WHERE is_active=1 ORDER BY name");
$slots = Database::fetchAll(
    "SELECT ts.*, s.name as service_name, (SELECT COUNT(*) FROM appointments a WHERE a.time_slot_id=ts.id AND a.status != 'cancelled') as booked_count FROM time_slots ts LEFT JOIN services s ON ts.service_id=s.id WHERE ts.slot_date=? ORDER BY ts.start_time",
    [$filterDate]
);
$csrf = Auth::generateCsrf();
include __DIR__ . '/layout/header.php';
?>

<div class="grid lg:grid-cols-5 gap-5">
  <!-- Add Slot Form -->
  <div class="lg:col-span-2">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-4">
      <h3 class="font-bold text-gray-900 mb-5">Add Time Slots</h3>
      <form method="POST" class="space-y-4" id="slotForm">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="add_slot">

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Date *</label>
          <input type="date" name="slot_date" id="slotDate" value="<?= htmlspecialchars($filterDate) ?>" min="<?= date('Y-m-d') ?>" required class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Service (optional)</label>
          <select name="service_id" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
            <option value="">All Services</option>
            <?php foreach ($services as $svc): ?>
            <option value="<?= $svc['id'] ?>"><?= htmlspecialchars($svc['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Max Bookings per Slot</label>
          <input type="number" name="max_bookings" value="1" min="1" max="99" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Slot Duration (minutes)</label>
          <select name="slot_duration" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
            <option value="15">15 min</option>
            <option value="30">30 min</option>
            <option value="45">45 min</option>
            <option value="60" selected>60 min</option>
            <option value="90">90 min</option>
            <option value="120">120 min</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-2">Select Times</label>
          <div class="grid grid-cols-3 gap-1.5 max-h-56 overflow-y-auto p-1" id="timeGrid">
            <?php
            $timeSlots = [];
            for ($h = 7; $h <= 20; $h++) {
                for ($m = 0; $m < 60; $m += 30) {
                    $timeSlots[] = sprintf('%02d:%02d', $h, $m);
                }
            }
            foreach ($timeSlots as $t): ?>
            <label class="flex items-center justify-center p-2 border border-gray-200 rounded-lg text-xs font-medium cursor-pointer hover:border-indigo-400 hover:bg-indigo-50 transition has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-100 has-[:checked]:text-indigo-700">
              <input type="checkbox" name="slot_times[]" value="<?= $t ?>" class="sr-only">
              <?= date('g:i A', strtotime($t)) ?>
            </label>
            <?php endforeach; ?>
          </div>
          <div class="flex gap-2 mt-2">
            <button type="button" onclick="selectAll(true)" class="text-xs text-indigo-600 hover:underline">Select All</button>
            <span class="text-gray-300">|</span>
            <button type="button" onclick="selectAll(false)" class="text-xs text-gray-500 hover:underline">Clear</button>
          </div>
        </div>

        <!-- Quick Generate -->
        <div class="pt-3 border-t border-gray-100">
          <label class="block text-xs font-semibold text-gray-600 mb-2">Quick Generate</label>
          <div class="grid grid-cols-2 gap-2 text-xs">
            <input type="time" id="genFrom" value="09:00" class="border border-gray-200 rounded-lg px-2 py-1.5 input-focus">
            <input type="time" id="genTo" value="17:00" class="border border-gray-200 rounded-lg px-2 py-1.5 input-focus">
          </div>
          <div class="flex gap-2 mt-2">
            <select id="genInterval" class="flex-1 border border-gray-200 rounded-lg px-2 py-1.5 text-xs input-focus">
              <option value="30">Every 30 min</option>
              <option value="60">Every 60 min</option>
              <option value="15">Every 15 min</option>
              <option value="45">Every 45 min</option>
            </select>
            <button type="button" onclick="generateSlots()" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs font-semibold hover:bg-gray-200 transition">Generate</button>
          </div>
        </div>

        <button type="submit" class="btn-primary w-full py-2.5 rounded-xl text-sm font-semibold">
          <i class="fa-solid fa-plus mr-2"></i>Add Selected Slots
        </button>
      </form>
    </div>
  </div>

  <!-- Slots List -->
  <div class="lg:col-span-3">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="p-5 border-b border-gray-100 flex items-center justify-between">
        <div>
          <h3 class="font-bold text-gray-900">Slots for <?= date('F j, Y', strtotime($filterDate)) ?></h3>
          <p class="text-xs text-gray-400 mt-0.5"><?= count($slots) ?> slots configured</p>
        </div>
        <div class="flex items-center gap-2">
          <input type="date" id="dateFilter" value="<?= htmlspecialchars($filterDate) ?>" class="border border-gray-200 rounded-xl px-3 py-1.5 text-sm input-focus">
        </div>
      </div>
      <div class="divide-y divide-gray-50">
        <?php foreach ($slots as $slot): ?>
        <?php
        $bookedCount = (int)$slot['booked_count'];
        $maxBookings = (int)$slot['max_bookings'];
        $isFull = $bookedCount >= $maxBookings;
        $pct = $maxBookings > 0 ? min(100, round($bookedCount/$maxBookings*100)) : 0;
        ?>
        <div class="flex items-center gap-4 px-5 py-3.5 <?= !$slot['is_active'] ? 'opacity-50' : '' ?>">
          <div class="w-20 text-center flex-shrink-0">
            <div class="text-sm font-bold text-gray-800"><?= date('g:i A', strtotime($slot['start_time'])) ?></div>
            <div class="text-xs text-gray-400">→ <?= date('g:i A', strtotime($slot['end_time'])) ?></div>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-gray-800"><?= htmlspecialchars($slot['service_name'] ?? 'All Services') ?></div>
            <div class="flex items-center gap-2 mt-1">
              <div class="flex-1 bg-gray-100 rounded-full h-1.5">
                <div class="rounded-full h-1.5 <?= $isFull ? 'bg-red-500' : 'bg-green-500' ?>" style="width:<?= $pct ?>%"></div>
              </div>
              <span class="text-xs text-gray-500"><?= $bookedCount ?>/<?= $maxBookings ?></span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-xs px-2 py-0.5 rounded-full <?= $isFull ? 'bg-red-100 text-red-700' : ($slot['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500') ?>">
              <?= $isFull ? 'Full' : ($slot['is_active'] ? 'Open' : 'Disabled') ?>
            </span>
            <form method="POST" class="inline">
              <input type="hidden" name="_csrf" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="toggle_slot">
              <input type="hidden" name="id" value="<?= $slot['id'] ?>">
              <button type="submit" class="w-7 h-7 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 transition" title="Toggle">
                <i class="fa-solid <?= $slot['is_active'] ? 'fa-toggle-on text-green-500' : 'fa-toggle-off' ?> text-sm"></i>
              </button>
            </form>
            <form method="POST" class="inline" onsubmit="return confirm('Delete slot?')">
              <input type="hidden" name="_csrf" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="delete_slot">
              <input type="hidden" name="id" value="<?= $slot['id'] ?>">
              <button type="submit" class="w-7 h-7 rounded-lg hover:bg-red-50 flex items-center justify-center text-gray-300 hover:text-red-500 transition">
                <i class="fa-solid fa-trash text-xs"></i>
              </button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($slots)): ?>
        <div class="py-16 text-center text-gray-400">
          <i class="fa-regular fa-clock text-4xl mb-3 opacity-30 block"></i>
          <p class="text-sm">No slots for this date</p>
          <p class="text-xs mt-1">Use the form to add time slots</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('dateFilter').addEventListener('change', function() {
  window.location.href = '?date=' + this.value;
});

function selectAll(state) {
  document.querySelectorAll('#timeGrid input[type="checkbox"]').forEach(cb => cb.checked = state);
}

function generateSlots() {
  const from = document.getElementById('genFrom').value;
  const to = document.getElementById('genTo').value;
  const interval = parseInt(document.getElementById('genInterval').value);
  if (!from || !to) return;

  // Convert to minutes
  const fromMin = parseInt(from.split(':')[0])*60 + parseInt(from.split(':')[1]);
  const toMin = parseInt(to.split(':')[0])*60 + parseInt(to.split(':')[1]);

  // Uncheck all first
  selectAll(false);

  // Check matching times
  for (let m = fromMin; m <= toMin; m += interval) {
    const h = Math.floor(m/60).toString().padStart(2,'0');
    const min = (m%60).toString().padStart(2,'0');
    const val = `${h}:${min}`;
    const cb = document.querySelector(`#timeGrid input[value="${val}"]`);
    if (cb) cb.checked = true;
  }
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
