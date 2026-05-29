<?php
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();
define('PAGE_TITLE', 'Blocked Dates');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST['_csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $date = $_POST['blocked_date'] ?? '';
        $reason = sanitize($_POST['reason'] ?? '');
        if ($date) {
            Database::insert("INSERT IGNORE INTO blocked_dates (blocked_date, reason) VALUES (?,?)", [$date, $reason]);
            $_SESSION['flash_message'] = ['text' => 'Date blocked successfully', 'type' => 'success'];
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        Database::update("DELETE FROM blocked_dates WHERE id=?", [$id]);
        $_SESSION['flash_message'] = ['text' => 'Date unblocked', 'type' => 'success'];
    }
    redirect(BASE_URL . '/admin/calendar');
}

$blockedDates = Database::fetchAll("SELECT * FROM blocked_dates ORDER BY blocked_date ASC");
$blockedDatesList = array_column($blockedDates, 'blocked_date');
$csrf = Auth::generateCsrf();

include __DIR__ . '/layout/header.php';
?>

<div class="grid lg:grid-cols-5 gap-5">
  <!-- Calendar -->
  <div class="lg:col-span-3">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <div class="flex items-center justify-between mb-5">
        <h3 class="font-bold text-gray-900">Calendar Overview</h3>
        <div class="flex items-center gap-2">
          <button id="prevMonth" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-500 transition"><i class="fa-solid fa-chevron-left text-xs"></i></button>
          <span id="monthLabel" class="text-sm font-semibold text-gray-700 min-w-32 text-center"></span>
          <button id="nextMonth" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-500 transition"><i class="fa-solid fa-chevron-right text-xs"></i></button>
        </div>
      </div>
      <!-- Calendar Grid -->
      <div id="calendarGrid">
        <div class="grid grid-cols-7 mb-2">
          <?php foreach (['Su','Mo','Tu','We','Th','Fr','Sa'] as $d): ?>
          <div class="text-center text-xs font-semibold text-gray-400 py-1"><?= $d ?></div>
          <?php endforeach; ?>
        </div>
        <div class="grid grid-cols-7 gap-1" id="calendarDays"></div>
      </div>
      <!-- Legend -->
      <div class="flex items-center gap-4 mt-5 pt-4 border-t border-gray-100 text-xs">
        <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded bg-red-500"></div><span class="text-gray-600">Blocked</span></div>
        <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded brand-bg"></div><span class="text-gray-600">Today</span></div>
        <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded bg-gray-100"></div><span class="text-gray-600">Available</span></div>
      </div>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="lg:col-span-2 space-y-4">
    <!-- Add Block Form -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <h3 class="font-bold text-gray-900 mb-4">Block a Date</h3>
      <form method="POST" class="space-y-3" id="blockForm">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="add">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Select Date *</label>
          <input type="date" name="blocked_date" id="blockDateInput" min="<?= date('Y-m-d') ?>" required class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Reason (optional)</label>
          <input type="text" name="reason" placeholder="e.g., Public Holiday, Staff Training..." class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        </div>
        <button type="submit" class="btn-primary w-full py-2.5 rounded-xl text-sm font-semibold">
          <i class="fa-solid fa-ban mr-2"></i>Block Date
        </button>
      </form>
    </div>

    <!-- Blocked Dates List -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="p-5 border-b border-gray-100">
        <h3 class="font-bold text-gray-900">Blocked Dates <span class="text-gray-400 text-sm font-normal">(<?= count($blockedDates) ?>)</span></h3>
      </div>
      <div class="max-h-80 overflow-y-auto scrollbar-thin divide-y divide-gray-50">
        <?php foreach ($blockedDates as $bd): ?>
        <div class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors group">
          <div>
            <div class="text-sm font-semibold text-gray-800"><?= date('D, M j Y', strtotime($bd['blocked_date'])) ?></div>
            <?php if ($bd['reason']): ?>
            <div class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($bd['reason']) ?></div>
            <?php endif; ?>
            <?php if ($bd['blocked_date'] < date('Y-m-d')): ?>
            <div class="text-xs text-amber-600 mt-0.5">Past date</div>
            <?php endif; ?>
          </div>
          <form method="POST" onsubmit="return confirm('Unblock this date?')" class="opacity-0 group-hover:opacity-100 transition-opacity">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $bd['id'] ?>">
            <button type="submit" class="w-7 h-7 rounded-lg hover:bg-red-50 flex items-center justify-center text-gray-300 hover:text-red-500 transition">
              <i class="fa-solid fa-trash-can text-xs"></i>
            </button>
          </form>
        </div>
        <?php endforeach; ?>
        <?php if (empty($blockedDates)): ?>
        <div class="py-12 text-center text-gray-400">
          <i class="fa-solid fa-calendar-check text-3xl mb-2 opacity-30 block"></i>
          <p class="text-sm">No dates blocked</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
const blockedDates = <?= json_encode($blockedDatesList) ?>;
const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#6366f1';
let currentYear = new Date().getFullYear();
let currentMonth = new Date().getMonth();
const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const today = new Date().toISOString().slice(0,10);

function renderCalendar() {
  const label = document.getElementById('monthLabel');
  const daysEl = document.getElementById('calendarDays');
  label.textContent = `${monthNames[currentMonth]} ${currentYear}`;

  const firstDay = new Date(currentYear, currentMonth, 1).getDay();
  const daysInMonth = new Date(currentYear, currentMonth+1, 0).getDate();

  let html = '';
  for (let i = 0; i < firstDay; i++) html += '<div></div>';

  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = `${currentYear}-${String(currentMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const isBlocked = blockedDates.includes(dateStr);
    const isToday = dateStr === today;
    const isPast = dateStr < today;

    let cls = 'aspect-square flex items-center justify-center text-xs font-medium rounded-lg cursor-pointer transition-all ';
    if (isBlocked) cls += 'bg-red-500 text-white font-bold';
    else if (isToday) cls += 'text-white font-bold';
    else if (isPast) cls += 'text-gray-300 cursor-default';
    else cls += 'hover:bg-indigo-50 hover:text-indigo-700 text-gray-700';

    const style = isToday && !isBlocked ? `style="background:${primaryColor}"` : '';
    const click = !isPast ? `onclick="selectDate('${dateStr}')"` : '';

    html += `<div class="${cls}" ${style} ${click} title="${dateStr}">${d}</div>`;
  }
  daysEl.innerHTML = html;
}

function selectDate(dateStr) {
  document.getElementById('blockDateInput').value = dateStr;
  gsap.from('#blockForm', {scale:1.02,duration:0.2,ease:'power2.out'});
}

document.getElementById('prevMonth').addEventListener('click', () => {
  currentMonth--; if (currentMonth < 0) { currentMonth = 11; currentYear--; }
  renderCalendar();
});
document.getElementById('nextMonth').addEventListener('click', () => {
  currentMonth++; if (currentMonth > 11) { currentMonth = 0; currentYear++; }
  renderCalendar();
});

renderCalendar();
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
