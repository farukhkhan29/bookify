<?php
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();
define('PAGE_TITLE', 'Services');

// Ensure columns exist
try { Database::getInstance()->exec("ALTER TABLE services ADD COLUMN icon VARCHAR(100) DEFAULT 'fa-briefcase-medical'"); } catch(Throwable $e) {}
try { Database::getInstance()->exec("ALTER TABLE services ADD COLUMN custom_svg_icon TEXT DEFAULT NULL"); } catch(Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST['_csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $iconType  = $_POST['icon_type'] ?? 'library'; // 'library' or 'svg'
        $faIcon    = sanitize($_POST['icon'] ?? 'fa-briefcase-medical');
        $customSvg = null;

        // Handle SVG upload
        if ($iconType === 'svg_upload' && !empty($_FILES['svg_file']['name'])) {
            $tmpFile = $_FILES['svg_file']['tmp_name'];
            $ext     = strtolower(pathinfo($_FILES['svg_file']['name'], PATHINFO_EXTENSION));
            if ($ext === 'svg' && is_uploaded_file($tmpFile)) {
                $svgContent = file_get_contents($tmpFile);
                // Basic SVG validation — must contain <svg
                if (stripos($svgContent, '<svg') !== false) {
                    // Sanitize: remove scripts
                    $svgContent = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svgContent);
                    $svgContent = preg_replace('/\bon\w+\s*=/i', 'data-removed=', $svgContent);
                    $customSvg = $svgContent;
                    $iconType  = 'svg';
                }
            }
        } elseif ($iconType === 'svg_paste' && !empty(trim($_POST['svg_paste'] ?? ''))) {
            $svgContent = trim($_POST['svg_paste']);
            if (stripos($svgContent, '<svg') !== false) {
                $svgContent = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svgContent);
                $svgContent = preg_replace('/\bon\w+\s*=/i', 'data-removed=', $svgContent);
                $customSvg = $svgContent;
                $iconType  = 'svg';
            }
        }

        // If SVG provided, store SVG and mark icon as 'custom_svg'
        // If not, use FA icon
        $iconValue = ($iconType === 'svg' && $customSvg) ? 'custom_svg' : $faIcon;

        $id = (int)($_POST['id'] ?? 0);
        $data = [
            sanitize($_POST['name'] ?? ''),
            sanitize($_POST['description'] ?? ''),
            (int)($_POST['duration'] ?? 60),
            (float)($_POST['price'] ?? 0),
            sanitize($_POST['color'] ?? '#22c55e'),
            $iconValue,
            isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($id) {
            Database::update(
                "UPDATE services SET name=?,description=?,duration=?,price=?,color=?,icon=?,is_active=?" .
                ($customSvg !== null ? ",custom_svg_icon=?" : "") . " WHERE id=?",
                $customSvg !== null ? [...$data, $customSvg, $id] : [...$data, $id]
            );
        } else {
            Database::insert(
                "INSERT INTO services (name,description,duration,price,color,icon,is_active,custom_svg_icon) VALUES (?,?,?,?,?,?,?,?)",
                [...$data, $customSvg]
            );
        }
        $_SESSION['flash_message'] = ['text'=>'Service saved','type'=>'success'];

    } elseif ($action === 'delete') {
        Database::update("DELETE FROM services WHERE id=?", [(int)($_POST['id'] ?? 0)]);
        $_SESSION['flash_message'] = ['text'=>'Service deleted','type'=>'success'];

    } elseif ($action === 'add_closed_date') {
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $start     = $_POST['start_date'] ?? '';
        $end       = $_POST['end_date']   ?? $start;
        $reason    = sanitize($_POST['reason'] ?? '');
        if ($serviceId && $start) {
            $cur = new DateTime($start);
            $endD = new DateTime($end ?: $start);
            if ($cur > $endD) [$cur,$endD] = [$endD,$cur];
            while ($cur <= $endD) {
                Database::insert("INSERT IGNORE INTO service_closed_dates (service_id,closed_date,reason) VALUES (?,?,?)",
                    [$serviceId, $cur->format('Y-m-d'), $reason]);
                $cur->modify('+1 day');
            }
            $_SESSION['flash_message'] = ['text'=>'Closed date(s) added','type'=>'success'];
        }
    } elseif ($action === 'remove_closed_date') {
        Database::update("DELETE FROM service_closed_dates WHERE id=?", [(int)($_POST['id'] ?? 0)]);
        $_SESSION['flash_message'] = ['text'=>'Closed date removed','type'=>'success'];
    }
    redirect(BASE_URL . '/admin/services');
}

$services    = Database::fetchAll("SELECT * FROM services ORDER BY created_at DESC");
$closedDates = Database::fetchAll("SELECT scd.*, s.name as service_name FROM service_closed_dates scd JOIN services s ON scd.service_id=s.id ORDER BY scd.closed_date DESC");
$csrf = Auth::generateCsrf();

$faIcons = [
  'fa-briefcase-medical','fa-eye','fa-glasses','fa-stethoscope','fa-heart-pulse',
  'fa-tooth','fa-ear','fa-brain','fa-bone','fa-microscope','fa-syringe',
  'fa-hand-holding-medical','fa-notes-medical','fa-pills','fa-user-doctor',
  'fa-scissors','fa-spa','fa-star','fa-crown','fa-gem','fa-leaf','fa-seedling',
  'fa-sun','fa-moon','fa-camera','fa-image','fa-paintbrush','fa-pen','fa-laptop',
  'fa-phone','fa-house','fa-car','fa-dumbbell','fa-person-running','fa-bicycle',
  'fa-heart','fa-shield-halved','fa-fire','fa-bolt','fa-droplet','fa-flask',
];

// Helper to render the service icon in PHP
function renderServiceIcon(array $svc, string $cls = 'text-lg', string $colorStyle = ''): string {
    $color = $colorStyle ?: ('color:' . htmlspecialchars($svc['color']));
    if (($svc['icon'] ?? '') === 'custom_svg' && !empty($svc['custom_svg_icon'])) {
        // Inject color into SVG via currentColor
        $svg = preg_replace('/<svg/i', '<svg style="width:1.25em;height:1.25em;' . $color . ';fill:currentColor"', $svc['custom_svg_icon'], 1);
        return $svg;
    }
    $icon = htmlspecialchars($svc['icon'] ?? 'fa-briefcase-medical');
    return '<i class="fa-solid ' . $icon . ' ' . $cls . '" style="' . $color . '"></i>';
}

include __DIR__ . '/layout/header.php';
?>

<div class="flex items-center justify-between mb-5">
  <div></div>
  <button onclick="openServiceModal()" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm">
    <i class="fa-solid fa-plus text-xs"></i> Add Service
  </button>
</div>

<!-- Services Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
  <?php foreach ($services as $svc): ?>
  <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 card-hover">
    <div class="flex items-start justify-between mb-4">
      <div class="w-12 h-12 rounded-xl flex items-center justify-center shadow-sm flex-shrink-0 overflow-hidden" style="background:<?= htmlspecialchars($svc['color']) ?>20">
        <?= renderServiceIcon($svc) ?>
      </div>
      <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold <?= $svc['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
        <?= $svc['is_active'] ? 'Active' : 'Inactive' ?>
      </span>
    </div>
    <h3 class="font-bold text-gray-900 mb-1 text-sm"><?= htmlspecialchars($svc['name']) ?></h3>
    <p class="text-xs text-gray-400 mb-4 line-clamp-2"><?= htmlspecialchars($svc['description'] ?: 'No description') ?></p>
    <div class="flex items-center gap-4 text-xs mb-4">
      <span class="flex items-center gap-1 text-gray-500"><i class="fa-regular fa-clock text-gray-300"></i><?= $svc['duration'] ?>min</span>
      <span class="flex items-center gap-1 text-gray-500"><i class="fa-solid fa-dollar-sign text-gray-300 text-xs"></i><?= number_format($svc['price'],2) ?></span>
    </div>
    <div class="flex gap-2 pt-3 border-t border-gray-100">
      <button onclick='editService(<?= json_encode(['id'=>$svc['id'],'name'=>$svc['name'],'description'=>$svc['description'],'duration'=>$svc['duration'],'price'=>$svc['price'],'color'=>$svc['color'],'icon'=>$svc['icon'],'is_active'=>$svc['is_active'],'has_svg'=>!empty($svc['custom_svg_icon'])]) ?>)'
        class="flex-1 py-1.5 text-xs font-semibold rounded-lg bg-green-50 text-green-700 hover:bg-green-100 transition flex items-center justify-center gap-1">
        <i class="fa-solid fa-pen text-xs"></i> Edit
      </button>
      <button onclick='toggleClosedDates(<?= $svc['id'] ?>)' class="flex-1 py-1.5 text-xs font-semibold rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 transition flex items-center justify-center gap-1">
        <i class="fa-solid fa-calendar-xmark text-xs"></i> Dates
      </button>
      <form method="POST" onsubmit="return confirm('Delete this service?')" class="inline">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $svc['id'] ?>">
        <button type="submit" class="py-1.5 px-3 text-xs font-semibold rounded-lg bg-red-50 text-red-500 hover:bg-red-100 transition"><i class="fa-solid fa-trash"></i></button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($services)): ?>
  <div class="col-span-full py-20 text-center text-gray-300">
    <i class="fa-solid fa-briefcase-medical text-5xl block mb-3 opacity-30"></i>
    <p class="text-sm">No services yet</p>
  </div>
  <?php endif; ?>
</div>

<!-- Closed Dates Panels -->
<?php foreach ($services as $svc): ?>
<div id="closedDates_<?= $svc['id'] ?>" class="hidden bg-white rounded-2xl shadow-sm border border-gray-100 mb-4">
  <div class="p-5 border-b border-gray-100 flex items-center justify-between">
    <h3 class="font-bold text-gray-900 text-sm">Closed Dates — <?= htmlspecialchars($svc['name']) ?></h3>
    <button onclick="toggleClosedDates(<?= $svc['id'] ?>)" class="text-gray-400 hover:text-gray-600 w-7 h-7 rounded-lg hover:bg-gray-100 flex items-center justify-center"><i class="fa-solid fa-times text-xs"></i></button>
  </div>
  <div class="p-5 grid md:grid-cols-2 gap-5">
    <div>
      <h4 class="text-xs font-bold text-gray-700 mb-3">Add Closed Date(s)</h4>
      <form method="POST" class="space-y-3">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="add_closed_date">
        <input type="hidden" name="service_id" value="<?= $svc['id'] ?>">
        <div class="grid grid-cols-2 gap-2">
          <div><label class="block text-xs text-gray-500 mb-1">Start Date</label><input type="date" name="start_date" required class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus"></div>
          <div><label class="block text-xs text-gray-500 mb-1">End Date</label><input type="date" name="end_date" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus"></div>
        </div>
        <input type="text" name="reason" placeholder="Reason (optional)" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-2"><i class="fa-solid fa-plus text-xs"></i> Add Date(s)</button>
      </form>
    </div>
    <div>
      <h4 class="text-xs font-bold text-gray-700 mb-3">Closed Dates</h4>
      <div class="space-y-2 max-h-48 overflow-y-auto scrollbar-thin">
        <?php $svcClosed = array_filter($closedDates, fn($d) => $d['service_id'] == $svc['id']); ?>
        <?php foreach ($svcClosed as $cd): ?>
        <div class="flex items-center justify-between p-2.5 bg-red-50 rounded-xl">
          <div>
            <div class="text-xs font-bold text-red-800"><?= date('M j, Y', strtotime($cd['closed_date'])) ?></div>
            <?php if ($cd['reason']): ?><div class="text-[10px] text-red-500"><?= htmlspecialchars($cd['reason']) ?></div><?php endif; ?>
          </div>
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="remove_closed_date">
            <input type="hidden" name="id" value="<?= $cd['id'] ?>">
            <button type="submit" class="text-red-300 hover:text-red-600 transition"><i class="fa-solid fa-trash text-xs"></i></button>
          </form>
        </div>
        <?php endforeach; ?>
        <?php if (empty(array_values($svcClosed))): ?><p class="text-xs text-gray-400 text-center py-4">No closed dates</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<!-- ── SERVICE MODAL ── -->
<div id="serviceModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/40">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[92vh] overflow-y-auto">
    <div class="p-5 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white z-10 rounded-t-2xl">
      <h3 class="font-bold text-gray-900" id="modalTitle">Add Service</h3>
      <button onclick="closeModal('serviceModal')" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400"><i class="fa-solid fa-times text-sm"></i></button>
    </div>

    <form method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="serviceId" value="">

      <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Service Name *</label>
        <input type="text" name="name" id="svcName" required class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Description</label>
        <textarea name="description" id="svcDesc" rows="2" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus resize-none"></textarea>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Duration (min)</label>
          <input type="number" name="duration" id="svcDuration" value="60" min="5" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Price ($)</label>
          <input type="number" name="price" id="svcPrice" value="0" step="0.01" min="0" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Color</label>
          <input type="color" name="color" id="svcColor" value="#22c55e" class="w-full h-10 border border-gray-200 rounded-xl px-2 py-1 input-focus cursor-pointer">
        </div>
        <div class="flex items-end pb-1">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="is_active" id="svcActive" value="1" checked class="w-4 h-4 rounded accent-green-500">
            <span class="text-sm font-medium text-gray-700">Active</span>
          </label>
        </div>
      </div>

      <!-- ── ICON SECTION ── -->
      <div>
        <label class="block text-xs font-semibold text-gray-600 mb-2">Service Icon</label>
        <input type="hidden" name="icon" id="svcIcon" value="fa-briefcase-medical">
        <input type="hidden" name="icon_type" id="svcIconType" value="library">

        <!-- Tabs -->
        <div class="flex gap-1 p-1 bg-gray-100 rounded-xl mb-3">
          <button type="button" id="tabLibrary" onclick="switchIconTab('library')"
            class="flex-1 py-1.5 text-xs font-semibold rounded-lg bg-white shadow-sm text-gray-800 transition">
            <i class="fa-solid fa-icons mr-1"></i> Icon Library
          </button>
          <button type="button" id="tabUpload" onclick="switchIconTab('upload')"
            class="flex-1 py-1.5 text-xs font-semibold rounded-lg text-gray-500 hover:text-gray-700 transition">
            <i class="fa-solid fa-upload mr-1"></i> Upload SVG
          </button>
          <button type="button" id="tabPaste" onclick="switchIconTab('paste')"
            class="flex-1 py-1.5 text-xs font-semibold rounded-lg text-gray-500 hover:text-gray-700 transition">
            <i class="fa-solid fa-code mr-1"></i> Paste SVG
          </button>
        </div>

        <!-- Library tab -->
        <div id="iconTabLibrary">
          <div class="grid grid-cols-9 gap-1 p-3 bg-gray-50 rounded-xl border border-gray-200 max-h-44 overflow-y-auto" id="iconGrid">
            <?php foreach ($faIcons as $ico): ?>
            <div onclick="selectFaIcon('<?= $ico ?>')" id="icon_<?= str_replace('-','_',$ico) ?>"
              class="icon-opt aspect-square flex items-center justify-center rounded-lg cursor-pointer text-gray-500 border border-transparent hover:border-green-400 hover:bg-green-50 transition"
              title="<?= $ico ?>">
              <i class="fa-solid <?= $ico ?> text-sm"></i>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Upload SVG tab -->
        <div id="iconTabUpload" class="hidden">
          <div id="svgDropZone"
            class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center cursor-pointer hover:border-green-400 hover:bg-green-50 transition"
            onclick="document.getElementById('svgFileInput').click()"
            ondragover="event.preventDefault();this.classList.add('border-green-400','bg-green-50')"
            ondragleave="this.classList.remove('border-green-400','bg-green-50')"
            ondrop="handleSvgDrop(event)">
            <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-300 block mb-2"></i>
            <p class="text-sm font-semibold text-gray-600">Drop SVG file here</p>
            <p class="text-xs text-gray-400 mt-1">or click to browse · SVG files only</p>
            <input type="file" name="svg_file" id="svgFileInput" accept=".svg,image/svg+xml" class="hidden" onchange="handleSvgFile(this.files[0])">
          </div>
          <div id="svgUploadPreview" class="hidden mt-3 p-3 bg-gray-50 rounded-xl border border-gray-200 flex items-center gap-3">
            <div id="svgUploadThumb" class="w-12 h-12 flex items-center justify-center rounded-xl flex-shrink-0" style="background:#22c55e20"></div>
            <div class="flex-1 min-w-0">
              <div id="svgUploadName" class="text-sm font-semibold text-gray-800 truncate"></div>
              <div class="text-xs text-green-600 mt-0.5">✓ SVG ready to save</div>
            </div>
            <button type="button" onclick="clearSvgUpload()" class="text-gray-400 hover:text-red-500 transition text-xs"><i class="fa-solid fa-times"></i></button>
          </div>
        </div>

        <!-- Paste SVG tab -->
        <div id="iconTabPaste" class="hidden space-y-2">
          <textarea name="svg_paste" id="svgPasteInput" rows="5" placeholder='Paste your SVG code here...&#10;&#10;Example:&#10;&lt;svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"&gt;&#10;  &lt;path d="M12 2L2 7l10 5 10-5-10-5z"/&gt;&#10;&lt;/svg&gt;'
            class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono input-focus resize-none bg-gray-50"
            oninput="previewSvgPaste(this.value)"></textarea>
          <div id="svgPastePreview" class="hidden p-3 bg-gray-50 rounded-xl border border-gray-200 flex items-center gap-3">
            <div id="svgPasteThumb" class="w-12 h-12 flex items-center justify-center rounded-xl flex-shrink-0" style="background:#22c55e20"></div>
            <div class="flex-1">
              <div class="text-sm font-semibold text-gray-800">SVG Preview</div>
              <div id="svgPasteStatus" class="text-xs text-green-600 mt-0.5">✓ Valid SVG</div>
            </div>
          </div>
        </div>

        <!-- Current icon preview (shared) -->
        <div class="mt-3 flex items-center gap-3 px-3 py-2.5 bg-white border border-gray-200 rounded-xl">
          <span class="text-xs text-gray-400 flex-shrink-0">Preview:</span>
          <div class="w-9 h-9 rounded-lg flex items-center justify-center overflow-hidden flex-shrink-0" id="iconPreviewBox" style="background:#22c55e20">
            <i id="iconPreview" class="fa-solid fa-briefcase-medical" style="color:#22c55e"></i>
          </div>
          <span class="text-xs text-gray-500 truncate" id="iconName">fa-briefcase-medical</span>
        </div>
      </div>

      <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
        <button type="button" onclick="closeModal('serviceModal')" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
        <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-semibold">Save Service</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Modal ──────────────────────────────────────────────────────────────────
function openModal(id) {
  const el = document.getElementById(id);
  el.classList.remove('hidden'); el.style.display = 'flex';
  gsap.from('#'+id+' > div', {scale:0.95, opacity:0, duration:0.25, ease:'back.out(1.4)'});
}
function closeModal(id) {
  document.getElementById(id).classList.add('hidden');
  document.getElementById(id).style.display = 'none';
}
function openServiceModal() {
  document.getElementById('modalTitle').textContent = 'Add Service';
  document.getElementById('serviceId').value = '';
  document.getElementById('svcName').value = '';
  document.getElementById('svcDesc').value = '';
  document.getElementById('svcDuration').value = '60';
  document.getElementById('svcPrice').value = '0';
  document.getElementById('svcColor').value = '#22c55e';
  document.getElementById('svcActive').checked = true;
  clearSvgUpload();
  document.getElementById('svgPasteInput').value = '';
  document.getElementById('svgPastePreview').classList.add('hidden');
  switchIconTab('library');
  selectFaIcon('fa-briefcase-medical');
  openModal('serviceModal');
}

// ── Icon Tabs ──────────────────────────────────────────────────────────────
function switchIconTab(tab) {
  ['library','upload','paste'].forEach(t => {
    document.getElementById('iconTab'+t.charAt(0).toUpperCase()+t.slice(1)).classList.toggle('hidden', t !== tab);
    const btn = document.getElementById('tab'+t.charAt(0).toUpperCase()+t.slice(1));
    btn.className = btn.className.replace(/bg-white shadow-sm text-gray-800|text-gray-500 hover:text-gray-700/g, '').trim();
    if (t === tab) {
      btn.classList.add('bg-white','shadow-sm','text-gray-800');
    } else {
      btn.classList.add('text-gray-500','hover:text-gray-700');
    }
  });
  if (tab === 'library')  document.getElementById('svcIconType').value = 'library';
  if (tab === 'upload')   document.getElementById('svcIconType').value = 'svg_upload';
  if (tab === 'paste')    document.getElementById('svcIconType').value = 'svg_paste';
}

// ── FA Icon Picker ─────────────────────────────────────────────────────────
function selectFaIcon(icon) {
  document.getElementById('svcIcon').value = icon;
  document.getElementById('svcIconType').value = 'library';
  // Preview
  const color = document.getElementById('svcColor').value;
  const previewBox = document.getElementById('iconPreviewBox');
  previewBox.style.background = color + '20';
  previewBox.innerHTML = `<i class="fa-solid ${icon}" style="color:${color}"></i>`;
  document.getElementById('iconName').textContent = icon;
  // Highlight
  document.querySelectorAll('.icon-opt').forEach(el => {
    el.classList.remove('border-green-500','bg-green-50','text-green-700');
  });
  const sel = document.getElementById('icon_' + icon.replace(/-/g,'_'));
  if (sel) { sel.classList.add('border-green-500','bg-green-50','text-green-700'); }
}

// ── SVG Upload ─────────────────────────────────────────────────────────────
function handleSvgDrop(e) {
  e.preventDefault();
  document.getElementById('svgDropZone').classList.remove('border-green-400','bg-green-50');
  const file = e.dataTransfer.files[0];
  if (file) {
    // Set the file input (can't set programmatically, so just preview)
    handleSvgFile(file);
    // Put file into the actual input via DataTransfer
    try {
      const dt = new DataTransfer();
      dt.items.add(file);
      document.getElementById('svgFileInput').files = dt.files;
    } catch(e) {}
  }
}

function handleSvgFile(file) {
  if (!file || !file.name.toLowerCase().endsWith('.svg')) {
    alert('Please select an SVG file (.svg)');
    return;
  }
  const reader = new FileReader();
  reader.onload = e => {
    const svg = e.target.result;
    if (svg.toLowerCase().includes('<svg')) {
      showSvgPreview(svg, file.name, 'upload');
      document.getElementById('svcIconType').value = 'svg_upload';
    } else {
      alert('File does not appear to be a valid SVG');
    }
  };
  reader.readAsText(file);
}

function showSvgPreview(svgContent, name, mode) {
  const color = document.getElementById('svcColor').value;
  const styledSvg = svgContent.replace('<svg', `<svg style="width:28px;height:28px;fill:${color};color:${color}" `);
  const thumbId  = mode === 'upload' ? 'svgUploadThumb'   : 'svgPasteThumb';
  const previewId= mode === 'upload' ? 'svgUploadPreview' : 'svgPastePreview';
  const nameId   = mode === 'upload' ? 'svgUploadName'    : null;

  const thumb = document.getElementById(thumbId);
  thumb.innerHTML = styledSvg;
  thumb.style.background = color + '20';
  document.getElementById(previewId).classList.remove('hidden');
  if (nameId) document.getElementById(nameId).textContent = name;

  // Update main preview
  const mainBox = document.getElementById('iconPreviewBox');
  mainBox.style.background = color + '20';
  mainBox.innerHTML = styledSvg.replace('width:28px;height:28px', 'width:22px;height:22px');
  document.getElementById('iconName').textContent = 'Custom SVG';
}

function clearSvgUpload() {
  document.getElementById('svgFileInput').value = '';
  document.getElementById('svgUploadPreview').classList.add('hidden');
  document.getElementById('svgUploadThumb').innerHTML = '';
}

function previewSvgPaste(val) {
  const trimmed = val.trim();
  const preview = document.getElementById('svgPastePreview');
  const status  = document.getElementById('svgPasteStatus');
  if (trimmed.toLowerCase().includes('<svg')) {
    showSvgPreview(trimmed, 'pasted SVG', 'paste');
    document.getElementById('svcIconType').value = 'svg_paste';
    status.className = 'text-xs text-green-600 mt-0.5';
    status.textContent = '✓ Valid SVG';
  } else if (trimmed.length > 10) {
    preview.classList.remove('hidden');
    status.className = 'text-xs text-red-500 mt-0.5';
    status.textContent = '✗ No <svg> tag found';
    document.getElementById('svgPasteThumb').innerHTML = '<i class="fa-solid fa-triangle-exclamation text-red-400"></i>';
  } else {
    preview.classList.add('hidden');
  }
}

// ── Edit Service ───────────────────────────────────────────────────────────
function editService(svc) {
  document.getElementById('modalTitle').textContent = 'Edit Service';
  document.getElementById('serviceId').value = svc.id;
  document.getElementById('svcName').value = svc.name;
  document.getElementById('svcDesc').value = svc.description || '';
  document.getElementById('svcDuration').value = svc.duration;
  document.getElementById('svcPrice').value = svc.price;
  document.getElementById('svcColor').value = svc.color;
  document.getElementById('svcActive').checked = svc.is_active == 1;

  clearSvgUpload();
  document.getElementById('svgPasteInput').value = '';
  document.getElementById('svgPastePreview').classList.add('hidden');

  if (svc.has_svg) {
    // Show that a custom SVG exists
    switchIconTab('upload');
    document.getElementById('svgUploadPreview').classList.remove('hidden');
    document.getElementById('svgUploadThumb').innerHTML = '<i class="fa-solid fa-check text-green-600"></i>';
    document.getElementById('svgUploadName').textContent = 'Custom SVG (saved) — upload new to replace';
    document.getElementById('iconPreviewBox').innerHTML = '<i class="fa-solid fa-image text-gray-400"></i>';
    document.getElementById('iconName').textContent = 'Custom SVG';
    document.getElementById('svcIconType').value = 'svg_upload';
  } else {
    switchIconTab('library');
    selectFaIcon(svc.icon || 'fa-briefcase-medical');
  }
  openModal('serviceModal');
}

function toggleClosedDates(id) {
  const el = document.getElementById('closedDates_'+id);
  el.classList.toggle('hidden');
  if (!el.classList.contains('hidden')) gsap.from(el, {opacity:0, y:-8, duration:0.25});
}

// Update icon color when color picker changes
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('svcColor')?.addEventListener('input', function() {
    const color = this.value;
    document.getElementById('iconPreviewBox').style.background = color + '20';
    // Recolor FA icon if showing
    const ico = document.getElementById('iconPreview');
    if (ico) ico.style.color = color;
    // Recolor SVG thumbs
    ['svgUploadThumb','svgPasteThumb'].forEach(id => {
      const el = document.getElementById(id);
      if (el) {
        el.style.background = color + '20';
        const svg = el.querySelector('svg');
        if (svg) { svg.style.fill = color; svg.style.color = color; }
      }
    });
  });
  selectFaIcon('fa-briefcase-medical');
});
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
