<?php
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();
define('PAGE_TITLE', 'Appointments');

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST['_csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'update_status' && $id) {
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['pending','confirmed','cancelled','completed'])) {
            Database::update("UPDATE appointments SET status = ? WHERE id = ?", [$status, $id]);
            $_SESSION['flash_message'] = ['text' => 'Status updated successfully', 'type' => 'success'];
        }
    } elseif ($action === 'delete' && $id) {
        Database::update("DELETE FROM appointments WHERE id = ?", [$id]);
        $_SESSION['flash_message'] = ['text' => 'Appointment deleted', 'type' => 'success'];
    }
    redirect(BASE_URL . '/admin/appointments');
}

// Filters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];
if ($status) { $where[] = 'a.status = ?'; $params[] = $status; }
if ($search) { $where[] = '(a.customer_name LIKE ? OR a.customer_email LIKE ? OR a.booking_ref LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($dateFrom) { $where[] = 'a.appointment_date >= ?'; $params[] = $dateFrom; }
if ($dateTo) { $where[] = 'a.appointment_date <= ?'; $params[] = $dateTo; }
$whereStr = implode(' AND ', $where);

$total = Database::fetchOne("SELECT COUNT(*) as c FROM appointments a WHERE $whereStr", $params)['c'];
$appointments = Database::fetchAll(
    "SELECT a.*, s.name as service_name, s.price FROM appointments a LEFT JOIN services s ON a.service_id = s.id WHERE $whereStr ORDER BY a.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);
$services = Database::fetchAll("SELECT * FROM services WHERE is_active = 1");
$csrf = Auth::generateCsrf();

// View single appointment
$viewId = (int)($_GET['view'] ?? 0);
$viewAppt = null;
if ($viewId) {
    $viewAppt = Database::fetchOne("SELECT a.*, s.name as service_name, s.price, s.duration FROM appointments a LEFT JOIN services s ON a.service_id = s.id WHERE a.id = ?", [$viewId]);
}

include __DIR__ . '/layout/header.php';
?>

<?php if ($viewAppt): ?>
<!-- Detail Modal (auto-show) -->
<script>
document.addEventListener('DOMContentLoaded', () => { openModal('apptModal'); });
</script>
<div id="apptModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-overlay bg-black/40 hidden">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg fade-in-up">
    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
      <div>
        <h3 class="font-bold text-gray-900">Appointment Detail</h3>
        <p class="text-xs text-gray-400 font-mono mt-0.5"><?= htmlspecialchars($viewAppt['booking_ref']) ?></p>
      </div>
      <a href="appointments.php" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400">
        <i class="fa-solid fa-times"></i>
      </a>
    </div>
    <div class="p-6 space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <div><div class="text-xs text-gray-400 mb-1">Customer</div><div class="font-semibold text-gray-800"><?= htmlspecialchars($viewAppt['customer_name']) ?></div></div>
        <div><div class="text-xs text-gray-400 mb-1">Email</div><div class="text-sm text-gray-700"><?= htmlspecialchars($viewAppt['customer_email']) ?></div></div>
        <div><div class="text-xs text-gray-400 mb-1">Phone</div><div class="text-sm text-gray-700"><?= htmlspecialchars($viewAppt['customer_phone'] ?: '—') ?></div></div>
        <div><div class="text-xs text-gray-400 mb-1">Service</div><div class="text-sm text-gray-700"><?= htmlspecialchars($viewAppt['service_name'] ?: '—') ?></div></div>
        <div><div class="text-xs text-gray-400 mb-1">Date</div><div class="font-semibold text-gray-800"><?= date('F j, Y', strtotime($viewAppt['appointment_date'])) ?></div></div>
        <div><div class="text-xs text-gray-400 mb-1">Time</div><div class="font-semibold text-gray-800"><?= date('g:i A', strtotime($viewAppt['appointment_time'])) ?></div></div>
        <div><div class="text-xs text-gray-400 mb-1">Status</div><span class="badge-<?= $viewAppt['status'] ?> text-xs px-2.5 py-1 rounded-full font-semibold"><?= ucfirst($viewAppt['status']) ?></span></div>
        <div><div class="text-xs text-gray-400 mb-1">Price</div><div class="font-semibold text-gray-800">$<?= number_format($viewAppt['price'] ?? 0, 2) ?></div></div>
      </div>
      <?php if ($viewAppt['notes']): ?>
      <div><div class="text-xs text-gray-400 mb-1">Notes</div><div class="bg-gray-50 rounded-xl p-3 text-sm text-gray-700"><?= nl2br(htmlspecialchars($viewAppt['notes'])) ?></div></div>
      <?php endif; ?>
      <?php if ($viewAppt['form_data']): ?>
      <?php $fd = json_decode($viewAppt['form_data'], true); if ($fd): ?>
      <div>
        <div class="text-xs text-gray-400 mb-2">Custom Fields</div>
        <div class="bg-gray-50 rounded-xl p-3 space-y-2">
          <?php foreach ($fd as $k => $v): ?>
          <div class="flex items-start gap-2 text-sm"><span class="text-gray-500 capitalize"><?= htmlspecialchars(str_replace('_',' ',$k)) ?>:</span><span class="text-gray-800 font-medium"><?= htmlspecialchars(is_array($v) ? implode(', ',$v) : $v) ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; endif; ?>
      <!-- Status Update -->
      <form method="POST" class="pt-2 border-t border-gray-100 flex gap-3">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" value="<?= $viewAppt['id'] ?>">
        <select name="status" class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
          <?php foreach (['pending','confirmed','cancelled','completed'] as $s): ?>
          <option value="<?= $s ?>" <?= $viewAppt['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-sm font-semibold">Update</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 mb-5">
  <form method="GET" class="flex flex-wrap items-end gap-3">
    <div class="flex-1 min-w-44">
      <label class="text-xs font-semibold text-gray-500 block mb-1.5">Search</label>
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name, email or ref..." class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
    </div>
    <div>
      <label class="text-xs font-semibold text-gray-500 block mb-1.5">Status</label>
      <select name="status" class="border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        <option value="">All Status</option>
        <?php foreach (['pending','confirmed','cancelled','completed'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="text-xs font-semibold text-gray-500 block mb-1.5">From Date</label>
      <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
    </div>
    <div>
      <label class="text-xs font-semibold text-gray-500 block mb-1.5">To Date</label>
      <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
    </div>
    <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-sm font-semibold">Filter</button>
    <a href="appointments.php" class="px-5 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition">Reset</a>
  </form>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="p-5 border-b border-gray-100 flex items-center justify-between">
    <div class="text-sm text-gray-500"><?= $total ?> appointment<?= $total != 1 ? 's' : '' ?> found</div>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-gray-100 bg-gray-50/50">
          <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Ref</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Customer</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Service</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Date & Time</th>
          <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Status</th>
          <th class="text-right px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php foreach ($appointments as $appt): ?>
        <tr class="table-row">
          <td class="px-5 py-3.5 font-mono text-xs font-semibold text-gray-500"><?= htmlspecialchars($appt['booking_ref']) ?></td>
          <td class="px-5 py-3.5">
            <div class="font-medium text-gray-800"><?= htmlspecialchars($appt['customer_name']) ?></div>
            <div class="text-xs text-gray-400"><?= htmlspecialchars($appt['customer_email']) ?></div>
          </td>
          <td class="px-5 py-3.5 text-gray-600"><?= htmlspecialchars($appt['service_name'] ?? '—') ?></td>
          <td class="px-5 py-3.5">
            <div><?= date('M j, Y', strtotime($appt['appointment_date'])) ?></div>
            <div class="text-xs text-gray-400"><?= date('g:i A', strtotime($appt['appointment_time'])) ?></div>
          </td>
          <td class="px-5 py-3.5">
            <span class="badge-<?= $appt['status'] ?> text-xs px-2.5 py-1 rounded-full font-semibold"><?= ucfirst($appt['status']) ?></span>
          </td>
          <td class="px-5 py-3.5 text-right">
            <div class="flex items-center justify-end gap-2">
              <a href="?view=<?= $appt['id'] ?>" class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 flex items-center justify-center transition" title="View">
                <i class="fa-solid fa-eye text-xs"></i>
              </a>
              <form method="POST" class="inline" onsubmit="return confirm('Delete this appointment?')">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $appt['id'] ?>">
                <button type="submit" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 flex items-center justify-center transition" title="Delete">
                  <i class="fa-solid fa-trash text-xs"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($appointments)): ?>
        <tr><td colspan="6" class="px-5 py-16 text-center text-gray-400"><i class="fa-solid fa-inbox text-4xl mb-3 opacity-30 block"></i><p class="text-sm">No appointments found</p></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($total > $perPage): ?>
  <div class="p-5 border-t border-gray-100 flex items-center justify-between">
    <div class="text-sm text-gray-400">Showing <?= min($offset+1, $total) ?>–<?= min($offset+$perPage, $total) ?> of <?= $total ?></div>
    <div class="flex gap-1.5">
      <?php $totalPages = ceil($total/$perPage); for ($p=1; $p<=$totalPages; $p++): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$p])) ?>"
         class="w-8 h-8 rounded-lg flex items-center justify-center text-sm <?= $page==$p ? 'btn-primary font-bold' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?> transition">
        <?= $p ?>
      </a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
function openModal(id) {
  document.getElementById(id).classList.remove('hidden');
  gsap.from('#'+id+' > div', { scale: 0.95, opacity: 0, duration: 0.3, ease: 'back.out(1.4)' });
}
</script>
<?php include __DIR__ . '/layout/footer.php'; ?>
