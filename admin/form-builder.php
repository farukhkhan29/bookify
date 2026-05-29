<?php
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();
define('PAGE_TITLE', 'Form Builder');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST['_csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_field') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            sanitize($_POST['field_name'] ?? ''),
            sanitize($_POST['field_label'] ?? ''),
            $_POST['field_type'] ?? 'text',
            sanitize($_POST['field_options'] ?? ''),
            isset($_POST['is_required']) ? 1 : 0,
            sanitize($_POST['placeholder'] ?? ''),
            (int)($_POST['sort_order'] ?? 0),
            isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($id) {
            Database::update("UPDATE form_fields SET field_name=?,field_label=?,field_type=?,field_options=?,is_required=?,placeholder=?,sort_order=?,is_active=? WHERE id=?", [...$data,$id]);
        } else {
            Database::insert("INSERT INTO form_fields (field_name,field_label,field_type,field_options,is_required,placeholder,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?)", $data);
        }
        $_SESSION['flash_message'] = ['text'=>'Field saved','type'=>'success'];
    } elseif ($action === 'delete_field') {
        Database::update("DELETE FROM form_fields WHERE id=?", [(int)($_POST['id']??0)]);
        $_SESSION['flash_message'] = ['text'=>'Field deleted','type'=>'success'];
    } elseif ($action === 'save_style') {
        $fields = ['primary_color','button_color','button_text_color','button_border_radius','field_border_radius','field_padding','font_family','background_color','text_color'];
        foreach ($fields as $f) {
            $val = sanitize($_POST[$f] ?? '');
            if ($val) Database::update("UPDATE form_styles SET $f=? WHERE id=1", [$val]);
        }
        $_SESSION['flash_message'] = ['text'=>'Form style saved','type'=>'success'];
    } elseif ($action === 'apply_template') {
        $tplId = (int)($_POST['template_id']??0);
        setSetting('active_template_id', (string)$tplId);
        $_SESSION['flash_message'] = ['text'=>'Template applied','type'=>'success'];
    } elseif ($action === 'reorder_fields') {
        $order = $_POST['order'] ?? [];
        foreach ($order as $idx => $fieldId) {
            Database::update("UPDATE form_fields SET sort_order=? WHERE id=?", [(int)$idx, (int)$fieldId]);
        }
        jsonResponse(['success' => true]);
    }
    redirect(BASE_URL . '/admin/form-builder');
}

$fields = Database::fetchAll("SELECT * FROM form_fields ORDER BY sort_order, id");
$style = Database::fetchOne("SELECT * FROM form_styles WHERE id=1");
if (!$style) $style = [];
$templates = Database::fetchAll("SELECT * FROM form_templates ORDER BY id");
$activeTemplateId = (int)getSetting('active_template_id', '1');
$csrf = Auth::generateCsrf();
$editField = null;
if (!empty($_GET['edit_field'])) {
    $editField = Database::fetchOne("SELECT * FROM form_fields WHERE id=?", [(int)$_GET['edit_field']]);
}

include __DIR__ . '/layout/header.php';
?>

<div class="grid xl:grid-cols-5 gap-5">
  <!-- Fields List -->
  <div class="xl:col-span-3 space-y-4">
    <!-- Fields -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="p-5 border-b border-gray-100 flex items-center justify-between">
        <div>
          <h3 class="font-bold text-gray-900">Form Fields</h3>
          <p class="text-xs text-gray-400 mt-0.5">Drag to reorder. These appear on the booking form.</p>
        </div>
        <button onclick="openModal('fieldModal')" class="btn-primary flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-semibold">
          <i class="fa-solid fa-plus text-xs"></i>Add Field
        </button>
      </div>
      <div id="fieldsList" class="divide-y divide-gray-50">
        <?php foreach ($fields as $field): ?>
        <div class="flex items-center gap-3 px-5 py-3.5 hover:bg-gray-50 transition-colors group" data-id="<?= $field['id'] ?>">
          <div class="text-gray-300 cursor-grab drag-handle"><i class="fa-solid fa-grip-vertical text-sm"></i></div>
          <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-<?= ['text'=>'font','email'=>'at','tel'=>'phone','textarea'=>'align-left','select'=>'chevron-down','checkbox'=>'check-square','radio'=>'dot-circle','date'=>'calendar','number'=>'hashtag'][$field['field_type']] ?? 'font' ?> text-xs brand-color"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($field['field_label']) ?></div>
            <div class="text-xs text-gray-400"><?= $field['field_type'] ?> <?= $field['is_required'] ? '· Required' : '' ?> <?= !$field['is_active'] ? '· Hidden' : '' ?></div>
          </div>
          <div class="flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
            <button onclick='editFieldFn(<?= json_encode($field) ?>)' class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 flex items-center justify-center transition">
              <i class="fa-solid fa-edit text-xs"></i>
            </button>
            <form method="POST" class="inline" onsubmit="return confirm('Delete field?')">
              <input type="hidden" name="_csrf" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="delete_field">
              <input type="hidden" name="id" value="<?= $field['id'] ?>">
              <button type="submit" class="w-7 h-7 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 flex items-center justify-center transition">
                <i class="fa-solid fa-trash text-xs"></i>
              </button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Templates -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <h3 class="font-bold text-gray-900 mb-4">Form Templates</h3>
      <div class="grid grid-cols-3 gap-3">
        <?php foreach ($templates as $tpl): ?>
        <div class="border-2 rounded-xl p-3 cursor-pointer transition-all hover:shadow-md <?= $tpl['id'] == $activeTemplateId ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200' ?>" onclick="applyTemplate(<?= $tpl['id'] ?>)">
          <!-- Template Preview -->
          <div class="h-20 rounded-lg mb-2 overflow-hidden relative <?= ['1'=>'bg-gradient-to-br from-slate-50 to-gray-100','2'=>'bg-gradient-to-br from-indigo-500 to-purple-600','3'=>'bg-gradient-to-br from-blue-50 to-indigo-50'][$tpl['id']] ?? 'bg-gray-50' ?>">
            <?php if ($tpl['id'] == 2): ?>
            <div class="absolute inset-2 bg-white/20 rounded-lg flex flex-col gap-1 p-1.5">
              <div class="h-2 w-full bg-white/40 rounded"></div>
              <div class="h-2 w-3/4 bg-white/40 rounded"></div>
              <div class="h-4 w-full bg-white rounded mt-auto flex items-center justify-center"><div class="w-8 h-1.5 bg-indigo-400 rounded"></div></div>
            </div>
            <?php else: ?>
            <div class="absolute inset-2 bg-white rounded-lg flex flex-col gap-1 p-1.5 shadow-sm">
              <div class="h-2 w-full bg-gray-100 rounded"></div>
              <div class="h-2 w-3/4 bg-gray-100 rounded"></div>
              <div class="h-4 w-full <?= $tpl['id']==3 ? 'bg-blue-500' : 'bg-indigo-500' ?> rounded mt-auto"></div>
            </div>
            <?php endif; ?>
          </div>
          <div class="text-xs font-semibold text-gray-800"><?= htmlspecialchars($tpl['name']) ?></div>
          <div class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($tpl['description']) ?></div>
          <?php if ($tpl['id'] == $activeTemplateId): ?>
          <div class="mt-1 text-xs brand-color font-semibold flex items-center gap-1"><i class="fa-solid fa-check"></i>Active</div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <form method="POST" id="templateForm">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="apply_template">
        <input type="hidden" name="template_id" id="templateId" value="">
      </form>
    </div>
  </div>

  <!-- Style Settings -->
  <div class="xl:col-span-2">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sticky top-4">
      <h3 class="font-bold text-gray-900 mb-4">Form Style Settings</h3>
      <form method="POST" class="space-y-4" id="styleForm">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="save_style">

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Primary Color</label>
            <div class="relative">
              <input type="color" name="primary_color" value="<?= htmlspecialchars($style['primary_color'] ?? '#6366f1') ?>" class="w-full h-9 border border-gray-200 rounded-xl px-2 input-focus cursor-pointer" oninput="updatePreview()">
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Button Color</label>
            <input type="color" name="button_color" value="<?= htmlspecialchars($style['button_color'] ?? '#6366f1') ?>" class="w-full h-9 border border-gray-200 rounded-xl px-2 input-focus cursor-pointer" oninput="updatePreview()">
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Button Text Color</label>
            <input type="color" name="button_text_color" value="<?= htmlspecialchars($style['button_text_color'] ?? '#ffffff') ?>" class="w-full h-9 border border-gray-200 rounded-xl px-2 input-focus cursor-pointer" oninput="updatePreview()">
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Background Color</label>
            <input type="color" name="background_color" value="<?= htmlspecialchars($style['background_color'] ?? '#ffffff') ?>" class="w-full h-9 border border-gray-200 rounded-xl px-2 input-focus cursor-pointer" oninput="updatePreview()">
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Button Border Radius</label>
          <div class="flex items-center gap-3">
            <input type="range" name="button_border_radius" id="btnRadius" min="0" max="30" value="<?= (int)($style['button_border_radius'] ?? '8') ?>" class="flex-1" oninput="document.getElementById('btnRadiusVal').textContent=this.value+'px'; updatePreview()">
            <span id="btnRadiusVal" class="text-xs font-mono text-gray-600 w-10"><?= $style['button_border_radius'] ?? '8px' ?></span>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Field Border Radius</label>
          <div class="flex items-center gap-3">
            <input type="range" name="field_border_radius" id="fieldRadius" min="0" max="20" value="<?= (int)($style['field_border_radius'] ?? '6') ?>" class="flex-1" oninput="document.getElementById('fieldRadiusVal').textContent=this.value+'px'; updatePreview()">
            <span id="fieldRadiusVal" class="text-xs font-mono text-gray-600 w-10"><?= $style['field_border_radius'] ?? '6px' ?></span>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Font Family</label>
          <select name="font_family" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus" oninput="updatePreview()">
            <?php foreach (['Inter','Plus Jakarta Sans','Poppins','DM Sans','Nunito','Lato','Roboto','Open Sans'] as $font): ?>
            <option <?= ($style['font_family']??'Inter')===$font?'selected':'' ?>><?= $font ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Live Preview -->
        <div class="border border-gray-200 rounded-xl p-4" id="formPreview">
          <div class="text-xs font-semibold text-gray-500 mb-3">Live Preview</div>
          <div class="space-y-2.5">
            <input type="text" placeholder="Your name" class="w-full px-3 py-2 border border-gray-200 text-sm transition-all duration-200" id="previewInput" style="border-radius:6px">
            <input type="email" placeholder="Email address" class="w-full px-3 py-2 border border-gray-200 text-sm" id="previewInput2" style="border-radius:6px">
            <button type="button" class="w-full py-2.5 text-sm font-semibold text-white transition-all duration-200" id="previewBtn" style="background:#6366f1;border-radius:8px">Book Appointment</button>
          </div>
        </div>

        <button type="submit" class="btn-primary w-full py-2.5 rounded-xl text-sm font-semibold">
          <i class="fa-solid fa-save mr-2"></i>Save Styles
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Field Modal -->
<div id="fieldModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 modal-overlay bg-black/40">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
    <div class="p-5 border-b border-gray-100 flex items-center justify-between">
      <h3 class="font-bold text-gray-900" id="fieldModalTitle">Add Field</h3>
      <button onclick="closeModal('fieldModal')" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400"><i class="fa-solid fa-times"></i></button>
    </div>
    <form method="POST" class="p-5 space-y-4">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="save_field">
      <input type="hidden" name="id" id="fieldId" value="">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Field Name *</label>
          <input type="text" name="field_name" id="fName" required placeholder="e.g. customer_age" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Label *</label>
          <input type="text" name="field_label" id="fLabel" required placeholder="e.g. Your Age" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Type *</label>
          <select name="field_type" id="fType" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus" onchange="toggleOptions()">
            <?php foreach (['text','email','tel','textarea','select','checkbox','radio','date','number'] as $t): ?>
            <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Sort Order</label>
          <input type="number" name="sort_order" id="fOrder" value="0" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Placeholder</label>
          <input type="text" name="placeholder" id="fPlaceholder" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        </div>
        <div class="col-span-2 hidden" id="optionsRow">
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Options (one per line)</label>
          <textarea name="field_options" id="fOptions" rows="3" placeholder="Option 1&#10;Option 2&#10;Option 3" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus resize-none"></textarea>
        </div>
        <div class="flex items-center gap-4">
          <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="is_required" id="fRequired" class="w-4 h-4 rounded"><span class="text-sm font-medium text-gray-700">Required</span></label>
        </div>
        <div class="flex items-center gap-4">
          <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="is_active" id="fActive" value="1" checked class="w-4 h-4 rounded"><span class="text-sm font-medium text-gray-700">Active</span></label>
        </div>
      </div>
      <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
        <button type="button" onclick="closeModal('fieldModal')" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition">Cancel</button>
        <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-sm font-semibold">Save Field</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
function openModal(id) { document.getElementById(id).classList.remove('hidden'); gsap.from('#'+id+' > div', {scale:0.95,opacity:0,duration:0.3,ease:'back.out(1.4)'}); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
function toggleOptions() {
  const t = document.getElementById('fType').value;
  document.getElementById('optionsRow').classList.toggle('hidden', !['select','checkbox','radio'].includes(t));
}
function editFieldFn(f) {
  document.getElementById('fieldModalTitle').textContent = 'Edit Field';
  document.getElementById('fieldId').value = f.id;
  document.getElementById('fName').value = f.field_name;
  document.getElementById('fLabel').value = f.field_label;
  document.getElementById('fType').value = f.field_type;
  document.getElementById('fOptions').value = f.field_options || '';
  document.getElementById('fRequired').checked = f.is_required == 1;
  document.getElementById('fActive').checked = f.is_active == 1;
  document.getElementById('fPlaceholder').value = f.placeholder || '';
  document.getElementById('fOrder').value = f.sort_order;
  toggleOptions();
  openModal('fieldModal');
}
function applyTemplate(id) {
  document.getElementById('templateId').value = id;
  document.getElementById('templateForm').submit();
}
function updatePreview() {
  const form = document.getElementById('styleForm');
  const btnColor = form.querySelector('[name=button_color]').value;
  const btnRadius = form.querySelector('[name=button_border_radius]').value + 'px';
  const fieldRadius = form.querySelector('[name=field_border_radius]').value + 'px';
  const btnTextColor = form.querySelector('[name=button_text_color]').value;
  const font = form.querySelector('[name=font_family]').value;

  document.getElementById('previewBtn').style.background = btnColor;
  document.getElementById('previewBtn').style.borderRadius = btnRadius;
  document.getElementById('previewBtn').style.color = btnTextColor;
  document.getElementById('previewInput').style.borderRadius = fieldRadius;
  document.getElementById('previewInput2').style.borderRadius = fieldRadius;
  document.getElementById('formPreview').style.fontFamily = font;
}
updatePreview();

// Drag-to-reorder
new Sortable(document.getElementById('fieldsList'), {
  handle: '.drag-handle',
  animation: 150,
  onEnd: function() {
    const ids = [...document.querySelectorAll('#fieldsList [data-id]')].map(el => el.dataset.id);
    fetch('', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({_csrf: '<?= $csrf ?>', action:'reorder_fields', ...Object.fromEntries(ids.map((id,i)=>['order['+i+']',id]))})
    });
  }
});

<?php if ($editField): ?>editFieldFn(<?= json_encode($editField) ?>);<?php endif; ?>
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
