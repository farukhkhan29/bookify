<?php
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();
define('PAGE_TITLE', 'Dashboard');

$totalAppointments   = Database::fetchOne("SELECT COUNT(*) as c FROM appointments")['c'];
$todayAppointments   = Database::fetchOne("SELECT COUNT(*) as c FROM appointments WHERE appointment_date = CURDATE()")['c'];
$pendingAppointments = Database::fetchOne("SELECT COUNT(*) as c FROM appointments WHERE status = 'pending'")['c'];
$monthRevenue        = Database::fetchOne("SELECT COALESCE(SUM(s.price),0) as rev FROM appointments a JOIN services s ON a.service_id=s.id WHERE MONTH(a.appointment_date)=MONTH(CURDATE()) AND YEAR(a.appointment_date)=YEAR(CURDATE()) AND a.status!='cancelled'")['rev'];
$recentAppointments  = Database::fetchAll("SELECT a.*, s.name as service_name FROM appointments a LEFT JOIN services s ON a.service_id=s.id ORDER BY a.created_at DESC LIMIT 8");
$chartData           = Database::fetchAll("SELECT DATE_FORMAT(appointment_date,'%b') as month, COUNT(*) as count FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(appointment_date,'%Y-%m') ORDER BY appointment_date");
$todayAppts          = Database::fetchAll("SELECT a.*, s.name as service_name FROM appointments a LEFT JOIN services s ON a.service_id=s.id WHERE a.appointment_date=CURDATE() ORDER BY a.appointment_time");
$totalServices       = Database::fetchOne("SELECT COUNT(*) as c FROM services WHERE is_active=1")['c'];

include __DIR__ . '/layout/header.php';
?>

<div class="dashboard-grid">

  <!-- ── STAT CARDS ─────────────────────────────────────────── -->
  <div class="col-span-full grid grid-cols-2 lg:grid-cols-4 gap-4">
    <?php
    $stats = [
      ['label'=>'Total Bookings','value'=>$totalAppointments,'icon'=>'fa-calendar-check','color'=>'#22c55e','bg'=>'rgba(34,197,94,0.12)','delta'=>'+12%'],
      ['label'=>"Today's",'value'=>$todayAppointments,'icon'=>'fa-clock','color'=>'#3b82f6','bg'=>'rgba(59,130,246,0.12)','delta'=>date('M j')],
      ['label'=>'Pending','value'=>$pendingAppointments,'icon'=>'fa-hourglass-half','color'=>'#f59e0b','bg'=>'rgba(245,158,11,0.12)','delta'=>'Review needed'],
      ['label'=>'Revenue','value'=>'$'.number_format($monthRevenue,0),'icon'=>'fa-dollar-sign','color'=>'#6366f1','bg'=>'rgba(99,102,241,0.12)','delta'=>date('M Y')],
    ];
    foreach ($stats as $i => $s): ?>
    <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm flex flex-col gap-3 hover:shadow-md transition-shadow" style="animation-delay:<?= $i*0.08 ?>s">
      <div class="flex items-center justify-between">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:<?= $s['bg'] ?>">
          <i class="fa-solid <?= $s['icon'] ?> text-sm" style="color:<?= $s['color'] ?>"></i>
        </div>
        <span class="text-xs font-medium px-2 py-0.5 rounded-full" style="background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>"><?= $s['delta'] ?></span>
      </div>
      <div>
        <div class="text-2xl font-black text-gray-900 stat-num"><?= $s['value'] ?></div>
        <div class="text-xs text-gray-500 font-medium mt-0.5"><?= $s['label'] ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ── CHART ──────────────────────────────────────────────── -->
  <div class="col-span-full lg:col-span-4 bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h3 class="font-bold text-gray-900 text-sm">Booking Trends</h3>
        <p class="text-xs text-gray-400 mt-0.5">Last 6 months</p>
      </div>
      <div class="flex gap-2">
        <div class="w-2.5 h-2.5 rounded-full bg-green-500 mt-0.5"></div>
        <span class="text-xs text-gray-500">Appointments</span>
      </div>
    </div>
    <canvas id="bookingChart" height="130"></canvas>
  </div>

  <!-- ── TODAY'S SCHEDULE ───────────────────────────────────── -->
  <div class="col-span-full lg:col-span-2 bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
    <div class="flex items-center justify-between mb-5">
      <div>
        <h3 class="font-bold text-gray-900 text-sm">Today</h3>
        <p class="text-xs text-gray-400 mt-0.5"><?= date('D, M j') ?> · <?= count($todayAppts) ?> appt<?= count($todayAppts)!=1?'s':'' ?></p>
      </div>
      <a href="<?= BASE_URL ?>/admin/appointments" class="text-xs font-semibold text-green-600 hover:underline">View all →</a>
    </div>
    <div class="space-y-2.5 max-h-64 overflow-y-auto pr-1">
      <?php if (empty($todayAppts)): ?>
      <div class="py-10 text-center text-gray-300">
        <i class="fa-regular fa-calendar-xmark text-3xl block mb-2"></i>
        <p class="text-xs">No appointments today</p>
      </div>
      <?php else: ?>
      <?php foreach ($todayAppts as $a): ?>
      <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 hover:bg-green-50 transition-colors group">
        <div class="w-10 text-center flex-shrink-0">
          <div class="text-xs font-bold text-gray-800"><?= date('g:i', strtotime($a['appointment_time'])) ?></div>
          <div class="text-[10px] text-gray-400"><?= date('A', strtotime($a['appointment_time'])) ?></div>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($a['customer_name']) ?></div>
          <div class="text-xs text-gray-400 truncate"><?= htmlspecialchars($a['service_name'] ?? 'N/A') ?></div>
        </div>
        <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold flex-shrink-0 badge-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── RECENT APPOINTMENTS TABLE ─────────────────────────── -->
  <div class="col-span-full bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-gray-100 flex items-center justify-between">
      <div>
        <h3 class="font-bold text-gray-900 text-sm">Recent Bookings</h3>
        <p class="text-xs text-gray-400 mt-0.5">Latest activity</p>
      </div>
      <a href="<?= BASE_URL ?>/admin/appointments" class="text-xs font-semibold text-green-600 hover:underline">All bookings →</a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm min-w-[500px]">
        <thead>
          <tr class="border-b border-gray-50">
            <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Customer</th>
            <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wider hidden sm:table-cell">Service</th>
            <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Date</th>
            <th class="text-left px-5 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Status</th>
            <th class="text-right px-5 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Ref</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php foreach ($recentAppointments as $a): ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0" style="background:var(--primary)">
                  <?= strtoupper(substr($a['customer_name'],0,1)) ?>
                </div>
                <div>
                  <div class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($a['customer_name']) ?></div>
                  <div class="text-xs text-gray-400 hidden sm:block"><?= htmlspecialchars($a['customer_email']) ?></div>
                </div>
              </div>
            </td>
            <td class="px-5 py-3.5 text-xs text-gray-600 hidden sm:table-cell"><?= htmlspecialchars($a['service_name'] ?? '—') ?></td>
            <td class="px-5 py-3.5">
              <div class="text-xs font-medium text-gray-700"><?= date('M j, Y', strtotime($a['appointment_date'])) ?></div>
              <div class="text-xs text-gray-400"><?= date('g:i A', strtotime($a['appointment_time'])) ?></div>
            </td>
            <td class="px-5 py-3.5">
              <span class="badge-<?= $a['status'] ?> text-[10px] px-2.5 py-1 rounded-full font-semibold"><?= ucfirst($a['status']) ?></span>
            </td>
            <td class="px-5 py-3.5 text-right">
              <span class="font-mono text-[10px] text-gray-400"><?= htmlspecialchars($a['booking_ref']) ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recentAppointments)): ?>
          <tr><td colspan="5" class="px-5 py-14 text-center text-gray-300 text-sm">No bookings yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartData = <?= json_encode($chartData) ?>;
const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#22c55e';
const ctx = document.getElementById('bookingChart').getContext('2d');
const grad = ctx.createLinearGradient(0,0,0,160);
grad.addColorStop(0, '#22c55e33');
grad.addColorStop(1, '#22c55e00');
new Chart(ctx, {
  type: 'line',
  data: {
    labels: chartData.length ? chartData.map(d=>d.month) : ['No data'],
    datasets: [{
      data: chartData.length ? chartData.map(d=>parseInt(d.count)) : [0],
      borderColor: '#22c55e', backgroundColor: grad,
      borderWidth: 2, fill: true, tension: 0.45,
      pointBackgroundColor: '#22c55e', pointRadius: 4, pointHoverRadius: 7,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: true,
    plugins: { legend: {display:false}, tooltip: {
      mode:'index', intersect:false,
      backgroundColor:'#111827', padding:10, cornerRadius:8,
      titleFont:{size:11}, bodyFont:{size:12}
    }},
    scales: {
      x: { grid:{display:false}, ticks:{font:{size:11},color:'#9ca3af'} },
      y: { grid:{color:'#f9fafb'}, ticks:{font:{size:11},color:'#9ca3af',stepSize:1}, beginAtZero:true }
    }
  }
});

// Animate counters
document.querySelectorAll('.stat-num').forEach(el => {
  const raw = el.textContent.replace(/[^0-9]/g,'');
  if (!raw) return;
  const prefix = el.textContent.startsWith('$') ? '$' : '';
  const end = parseInt(raw), duration = 900;
  let start = 0;
  const step = () => {
    start = Math.min(start + end / (duration/16), end);
    el.textContent = prefix + Math.floor(start).toLocaleString();
    if (start < end) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
});
</script>

<style>
.dashboard-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 1.25rem; }
@media (max-width: 1024px) { .dashboard-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  { .dashboard-grid { grid-template-columns: 1fr; } }
.dashboard-grid > .col-span-full { grid-column: 1 / -1; }
.dashboard-grid > .lg\:col-span-4 { grid-column: span 4; }
.dashboard-grid > .lg\:col-span-2 { grid-column: span 2; }
@media (max-width: 1024px) {
  .dashboard-grid > .lg\:col-span-4,
  .dashboard-grid > .lg\:col-span-2 { grid-column: 1 / -1; }
}
</style>

<?php include __DIR__ . '/layout/footer.php'; ?>
