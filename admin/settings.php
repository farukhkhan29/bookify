<?php
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();
define('PAGE_TITLE', 'Settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST['_csrf'] ?? '')) {
    $section = $_POST['section'] ?? '';
    try {
        if ($section === 'general') {
            $keys = ['site_name','timezone'];
            foreach ($keys as $k) { setSetting($k, sanitize($_POST[$k] ?? '')); }
            $redirectVal = trim($_POST['redirect_url'] ?? '');
            setSetting('redirect_url', $redirectVal);
            if (!empty($_FILES['site_logo']['name'])) {
                $ext = strtolower(pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','svg','webp'])) {
                    $dir = APP_PATH . '/assets/img/';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    $filename = 'logo_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $dir . $filename)) {
                        setSetting('site_logo', 'assets/img/' . $filename);
                    }
                }
            }
            $_SESSION['flash_message'] = ['text'=>'General settings saved','type'=>'success'];

        } elseif ($section === 'branding') {
            setSetting('dashboard_primary_color', sanitize($_POST['dashboard_primary_color'] ?? '#22c55e'));
            setSetting('dashboard_secondary_color', sanitize($_POST['dashboard_secondary_color'] ?? '#16a34a'));
            setSetting('dashboard_sidebar_color', sanitize($_POST['dashboard_sidebar_color'] ?? '#14532d'));
            setSetting('dashboard_font', sanitize($_POST['dashboard_font'] ?? 'DM Sans'));
            $_SESSION['flash_message'] = ['text'=>'Branding settings saved. Refresh to see changes.','type'=>'success'];

        } elseif ($section === 'email') {
            $keys = ['from_email','from_name','to_email'];
            foreach ($keys as $k) { setSetting($k, sanitize($_POST[$k] ?? '')); }
            $_SESSION['flash_message'] = ['text'=>'Email settings saved','type'=>'success'];

        } elseif ($section === 'mailgun') {
            setSetting('mailgun_api_key', sanitize($_POST['mailgun_api_key'] ?? ''));
            setSetting('mailgun_domain', sanitize($_POST['mailgun_domain'] ?? ''));
            $_SESSION['flash_message'] = ['text'=>'Mailgun settings saved','type'=>'success'];

        } elseif ($section === 'whatsapp') {
            setSetting('wa_provider',      sanitize($_POST['wa_provider']      ?? 'disabled'));
            setSetting('wa_owner_number',  sanitize($_POST['wa_owner_number']  ?? ''));
            setSetting('wa_twilio_sid',    sanitize($_POST['wa_twilio_sid']    ?? ''));
            setSetting('wa_twilio_token',  sanitize($_POST['wa_twilio_token']  ?? ''));
            setSetting('wa_twilio_from',   sanitize($_POST['wa_twilio_from']   ?? ''));
            setSetting('wa_callmebot_key', sanitize($_POST['wa_callmebot_key'] ?? ''));
            $_SESSION['flash_message'] = ['text'=>'WhatsApp settings saved','type'=>'success'];

        } elseif ($section === 'test_whatsapp') {
            require_once __DIR__ . '/../includes/whatsapp.php';
            $result = WhatsApp::sendTest();
            $_SESSION['flash_message'] = $result['success']
                ? ['text'=>'✅ Test WhatsApp sent!','type'=>'success']
                : ['text'=>'❌ WhatsApp failed: ' . ($result['error'] ?? 'Unknown'),'type'=>'error'];

        } elseif ($section === 'test_email') {
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
            $_SESSION['flash_message'] = $result['success']
                ? ['text'=>'Test email sent successfully!','type'=>'success']
                : ['text'=>'Email failed: ' . ($result['error'] ?? 'Unknown error'),'type'=>'error'];

        } elseif ($section === 'account') {
            $user = Auth::currentUser();
            $name     = sanitize($_POST['name'] ?? '');
            $email    = sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $updates  = ['name=?','email=?'];
            $params   = [$name, $email];
            if ($password) {
                if (strlen($password) < 8) {
                    $_SESSION['flash_message'] = ['text'=>'Password must be at least 8 characters','type'=>'error'];
                    redirect(BASE_URL . '/admin/settings#account');
                }
                $updates[] = 'password=?';
                $params[]  = Auth::hashPassword($password);
            }
            $params[] = $user['id'];
            Database::update('UPDATE users SET ' . implode(',', $updates) . ' WHERE id=?', $params);
            $_SESSION['user_name']  = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['flash_message'] = ['text'=>'Account updated','type'=>'success'];
        }
    } catch (Throwable $e) {
        error_log('Settings save error [' . $section . ']: ' . $e->getMessage());
        $_SESSION['flash_message'] = ['text'=>'Save failed: ' . $e->getMessage(),'type'=>'error'];
    }
    redirect(BASE_URL . '/admin/settings');
}

$user = Auth::currentUser();
$csrf = Auth::generateCsrf();
include __DIR__ . '/layout/header.php';
?>

<div class="max-w-3xl space-y-5">

  <!-- General Settings -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" id="general">
    <div class="p-5 border-b border-gray-100">
      <h3 class="font-bold text-gray-900">General Settings</h3>
      <p class="text-xs text-gray-400 mt-0.5">Basic site configuration</p>
    </div>
    <form id="form-general" method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <input type="hidden" name="section" value="general">
      <div class="grid sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Site Name</label>
          <input type="text" name="site_name" value="<?= htmlspecialchars(getSetting('site_name','BookFlow')) ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Logo Upload</label>
          <input type="file" name="site_logo" accept="image/*" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
          <?php $logo = getSetting('site_logo'); if ($logo): ?>
          <img src="<?= BASE_URL ?>/<?= htmlspecialchars($logo) ?>" alt="Logo" class="mt-2 h-10 object-contain">
          <?php endif; ?>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Timezone</label>
          <select name="timezone" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
            <?php foreach (['UTC','America/New_York','America/Chicago','America/Denver','America/Los_Angeles','Europe/London','Europe/Paris','Asia/Dubai','Asia/Karachi','Asia/Tokyo','Australia/Sydney'] as $tz): ?>
            <option <?= getSetting('timezone','UTC')===$tz?'selected':'' ?>><?= $tz ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="sm:col-span-2">
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Redirect URL After Booking</label>
          <input type="url" name="redirect_url" value="<?= htmlspecialchars(getSetting('redirect_url')) ?>" placeholder="https://yoursite.com/thank-you" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
          <p class="text-xs text-gray-400 mt-1">Leave empty to show a success message instead of redirecting.</p>
        </div>
      </div>
      <div class="flex justify-end pt-2 border-t border-gray-100">
        <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-sm font-semibold">Save Settings</button>
      </div>
    </form>
  </div>

  <!-- Branding -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" id="branding">
    <div class="p-5 border-b border-gray-100">
      <h3 class="font-bold text-gray-900">Dashboard Branding</h3>
      <p class="text-xs text-gray-400 mt-0.5">Colors, fonts, and visual identity</p>
    </div>
    <form id="form-branding" method="POST" class="p-5 space-y-5">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <input type="hidden" name="section" value="branding">
      <div>
        <label class="block text-xs font-bold text-gray-500 mb-3 uppercase tracking-wider">Colors</label>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Primary</label>
            <input type="color" name="dashboard_primary_color" id="primaryColorInput" value="<?= htmlspecialchars(getSetting('dashboard_primary_color','#22c55e')) ?>" class="w-full h-10 border border-gray-200 rounded-xl px-2 py-1 input-focus cursor-pointer">
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Secondary</label>
            <input type="color" name="dashboard_secondary_color" id="secondaryColorInput" value="<?= htmlspecialchars(getSetting('dashboard_secondary_color','#16a34a')) ?>" class="w-full h-10 border border-gray-200 rounded-xl px-2 py-1 input-focus cursor-pointer">
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Sidebar</label>
            <input type="color" name="dashboard_sidebar_color" id="sidebarColorInput" value="<?= htmlspecialchars(getSetting('dashboard_sidebar_color','#14532d')) ?>" class="w-full h-10 border border-gray-200 rounded-xl px-2 py-1 input-focus cursor-pointer">
          </div>
        </div>
        <div class="mt-3">
          <p class="text-xs text-gray-400 mb-2">Quick presets</p>
          <div class="flex flex-wrap gap-2">
            <?php foreach ([
              ['Forest Green','#22c55e','#16a34a','#14532d'],['Ocean Blue','#3b82f6','#2563eb','#1e3a5f'],
              ['Royal Purple','#8b5cf6','#7c3aed','#2e1065'],['Sunset','#f97316','#ea580c','#431407'],
              ['Rose','#f43f5e','#e11d48','#4c0519'],['Slate','#64748b','#475569','#0f172a'],
              ['Teal','#14b8a6','#0d9488','#134e4a'],['Amber','#f59e0b','#d97706','#451a03'],
            ] as [$name,$p,$s,$sb]): ?>
            <button type="button" onclick="applyPreset('<?= $p ?>','<?= $s ?>','<?= $sb ?>')"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 hover:border-gray-400 text-xs font-medium text-gray-600 hover:text-gray-900 transition">
              <div class="w-3 h-3 rounded-full" style="background:<?= $p ?>"></div><?= $name ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold text-gray-500 mb-3 uppercase tracking-wider">Typography</label>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Dashboard Font</label>
            <select name="dashboard_font" id="systemFontSelect" onchange="previewFont(this.value)" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
              <?php foreach (['DM Sans','Plus Jakarta Sans','Nunito','Poppins','Outfit','Raleway','Lexend','Sora','Figtree','Space Grotesk','Urbanist','Quicksand','Bricolage Grotesque','Be Vietnam Pro','IBM Plex Sans','Manrope','Work Sans','Geist'] as $f): ?>
              <option value="<?= $f ?>" <?= getSetting('dashboard_font','DM Sans')===$f?'selected':'' ?>><?= $f ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Search Google Fonts</label>
            <div class="flex gap-2">
              <input type="text" id="googleFontSearch" placeholder="e.g. Crimson Pro, Lora..." class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
              <button type="button" onclick="searchGoogleFont()" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">
                <i class="fa-solid fa-magnifying-glass text-xs"></i>
              </button>
            </div>
            <div id="fontSearchResults" class="hidden mt-2 bg-white border border-gray-200 rounded-xl shadow-sm max-h-36 overflow-y-auto text-sm"></div>
          </div>
        </div>
        <div class="mt-3 p-4 bg-gray-50 rounded-xl border border-gray-100">
          <div class="text-[10px] text-gray-400 mb-1 uppercase tracking-wider">Preview</div>
          <div id="fontPreview" class="text-xl font-bold text-gray-900"><?= htmlspecialchars(getSetting('site_name','BookFlow')) ?></div>
          <div id="fontPreviewSub" class="text-sm text-gray-500 mt-0.5">The quick brown fox jumps over the lazy dog · 0123456789</div>
        </div>
      </div>

      <div class="flex justify-end pt-2 border-t border-gray-100">
        <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-sm font-semibold">Save Branding</button>
      </div>
    </form>
  </div>

  <script>
  function applyPreset(p,s,sb) {
    document.getElementById('primaryColorInput').value = p;
    document.getElementById('secondaryColorInput').value = s;
    document.getElementById('sidebarColorInput').value = sb;
  }
  function previewFont(name) {
    const url = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(name)}:wght@400;700&display=swap`;
    const existing = document.getElementById('previewFontLink');
    if (existing) existing.remove();
    const link = document.createElement('link');
    link.id = 'previewFontLink'; link.rel = 'stylesheet'; link.href = url;
    document.head.appendChild(link);
    document.getElementById('fontPreview').style.fontFamily = `'${name}', sans-serif`;
    document.getElementById('fontPreviewSub').style.fontFamily = `'${name}', sans-serif`;
    // Also update the select if it exists
    const sel = document.getElementById('systemFontSelect');
    const opt = [...sel.options].find(o => o.value === name);
    if (!opt) {
      const newOpt = document.createElement('option');
      newOpt.value = name; newOpt.text = name + ' (Google)';
      sel.appendChild(newOpt);
    }
    sel.value = name;
  }
  async function searchGoogleFont() {
    const q = document.getElementById('googleFontSearch').value.trim();
    if (!q) return;
    const resultsEl = document.getElementById('fontSearchResults');
    resultsEl.classList.remove('hidden');
    resultsEl.innerHTML = '<div class="p-3 text-xs text-gray-400 text-center"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Searching...</div>';
    try {
      const r = await fetch(`https://www.googleapis.com/webfonts/v1/webfonts?key=AIzaSyD7y0N3JH4C0aXaKqE3lJjhOvGKiJaSdTk&sort=popularity&fields=items(family,variants)&query=${encodeURIComponent(q)}`);
      const data = await r.json();
      const fonts = (data.items || []).slice(0,8);
      if (!fonts.length) { resultsEl.innerHTML = '<div class="p-3 text-xs text-gray-400 text-center">No fonts found</div>'; return; }
      resultsEl.innerHTML = fonts.map(f =>
        `<div onclick="previewFont('${f.family}')" class="px-4 py-2.5 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-0 flex items-center justify-between group">
          <span class="text-sm font-medium text-gray-800">${f.family}</span>
          <span class="text-xs text-green-600 opacity-0 group-hover:opacity-100 font-semibold">Apply →</span>
        </div>`).join('');
    } catch(e) {
      resultsEl.innerHTML = '<div class="p-3 text-xs text-red-500 text-center">Search failed. Check API key or try manually typing the font name.</div>';
      // Fallback — just try applying the query directly
      const applyLink = `<div onclick="previewFont('${q}')" class="px-4 py-2 hover:bg-gray-50 cursor-pointer text-sm text-gray-700">Apply "${q}" directly</div>`;
      resultsEl.innerHTML += applyLink;
    }
  }
  document.getElementById('googleFontSearch')?.addEventListener('keydown', e => { if(e.key==='Enter') { e.preventDefault(); searchGoogleFont(); }});
  // Init preview with current font
  previewFont('<?= htmlspecialchars(getSetting('dashboard_font','DM Sans')) ?>');
  </script>

  <!-- Email Settings -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" id="email">
    <div class="p-5 border-b border-gray-100">
      <h3 class="font-bold text-gray-900">Email Settings</h3>
      <p class="text-xs text-gray-400 mt-0.5">Configure sender and recipient emails</p>
    </div>
    <form id="form-email" method="POST" class="p-5 space-y-4">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <input type="hidden" name="section" value="email">
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">From Name</label>
          <input type="text" name="from_name" value="<?= htmlspecialchars(getSetting('from_name','BookFlow')) ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">From Email</label>
          <input type="email" name="from_email" value="<?= htmlspecialchars(getSetting('from_email')) ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        </div>
        <div class="sm:col-span-2">
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Admin Notification Email (To)</label>
          <input type="email" name="to_email" value="<?= htmlspecialchars(getSetting('to_email')) ?>" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
          <p class="text-xs text-gray-400 mt-1">All new bookings will be sent to this email address.</p>
        </div>
      </div>
      <div class="flex justify-end pt-2 border-t border-gray-100">
        <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-sm font-semibold">Save Email Settings</button>
      </div>
    </form>
  </div>

  <!-- Mailgun -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" id="mailgun">
    <div class="p-5 border-b border-gray-100 flex items-center justify-between">
      <div>
        <h3 class="font-bold text-gray-900">Mailgun API Settings</h3>
        <p class="text-xs text-gray-400 mt-0.5">Connect your Mailgun account for email delivery</p>
      </div>
      <div class="flex items-center gap-2">
        <?php $mgKey = getSetting('mailgun_api_key'); ?>
        <span class="text-xs px-2.5 py-1 rounded-full font-semibold <?= $mgKey ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
          <?= $mgKey ? '✓ Configured' : 'Not configured' ?>
        </span>
      </div>
    </div>
    <form id="form-mailgun" method="POST" class="p-5 space-y-4">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <input type="hidden" name="section" value="mailgun">
      <div class="grid sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Mailgun API Key</label>
          <input type="password" name="mailgun_api_key" value="<?= htmlspecialchars(getSetting('mailgun_api_key')) ?>" placeholder="key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus font-mono">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Mailgun Domain</label>
          <input type="text" name="mailgun_domain" value="<?= htmlspecialchars(getSetting('mailgun_domain')) ?>" placeholder="mg.yourdomain.com" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        </div>
      </div>
      <div class="flex items-center justify-between pt-2 border-t border-gray-100">
        <form id="form-test-email" method="POST" class="inline">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <input type="hidden" name="section" value="test_email">
          <button type="submit" class="text-sm font-semibold text-indigo-600 hover:underline">Send Test Email →</button>
        </form>
        <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-sm font-semibold">Save Mailgun Config</button>
      </div>
    </form>
  </div>

  <!-- Account -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" id="account">
    <div class="p-5 border-b border-gray-100">
      <h3 class="font-bold text-gray-900">Account Settings</h3>
      <p class="text-xs text-gray-400 mt-0.5">Update your login credentials</p>
    </div>
    <form id="form-account" method="POST" class="p-5 space-y-4">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <input type="hidden" name="section" value="account">
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Full Name</label>
          <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">Email Address</label>
          <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        </div>
        <div class="sm:col-span-2">
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">New Password <span class="text-gray-400 font-normal">(leave blank to keep current)</span></label>
          <input type="password" name="password" placeholder="Min 8 characters" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus">
        </div>
      </div>
      <div class="flex justify-end pt-2 border-t border-gray-100">
        <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-sm font-semibold">Update Account</button>
      </div>
    </form>
  </div>

  <!-- ── WHATSAPP ──────────────────────────────────────────── -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" id="whatsapp">
    <div class="p-5 border-b border-gray-100 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:#25d36620">
          <i class="fa-brands fa-whatsapp text-xl" style="color:#25d366"></i>
        </div>
        <div>
          <h3 class="font-bold text-gray-900">WhatsApp Notifications</h3>
          <p class="text-xs text-gray-400 mt-0.5">Auto-message shop owner on new bookings</p>
        </div>
      </div>
      <?php $waProvider = getSetting('wa_provider','disabled'); ?>
      <span class="text-xs px-2.5 py-1 rounded-full font-semibold <?= $waProvider !== 'disabled' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
        <?= $waProvider !== 'disabled' ? 'Active' : 'Disabled' ?>
      </span>
    </div>
    <form id="form-whatsapp" method="POST" class="p-5 space-y-5">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <input type="hidden" name="section" value="whatsapp">

      <!-- Provider selector -->
      <div>
        <label class="block text-xs font-bold text-gray-500 mb-3 uppercase tracking-wider">Provider</label>
        <div class="grid sm:grid-cols-3 gap-3">
          <?php foreach ([
            ['disabled', 'Disabled',   'fa-ban',        '#6b7280', 'Turn off notifications'],
            ['callmebot','CallMeBot',  'fa-whatsapp',   '#25d366', 'Free · Personal use'],
            ['twilio',   'Twilio',     'fa-plug-circle-check','#f22f46','Paid · Production ready'],
          ] as [$val,$label,$icon,$color,$sub]): ?>
          <label class="provider-opt cursor-pointer border-2 rounded-xl p-3 flex items-center gap-3 transition <?= $waProvider===$val ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-gray-300' ?>">
            <input type="radio" name="wa_provider" value="<?= $val ?>" <?= $waProvider===$val?'checked':'' ?> class="sr-only" onchange="switchProvider('<?= $val ?>')">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:<?= $color ?>20">
              <i class="fa-<?= $icon==='fa-whatsapp'?'brands':'solid' ?> <?= $icon ?>" style="color:<?= $color ?>; font-size:14px"></i>
            </div>
            <div>
              <div class="text-sm font-bold text-gray-800"><?= $label ?></div>
              <div class="text-[10px] text-gray-400"><?= $sub ?></div>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Owner number (shared) -->
      <div id="waNumberRow" class="<?= $waProvider==='disabled'?'hidden':'' ?>">
        <label class="block text-xs font-semibold text-gray-600 mb-1.5">
          Shop Owner WhatsApp Number <span class="text-red-400">*</span>
        </label>
        <input type="text" name="wa_owner_number" value="<?= htmlspecialchars(getSetting('wa_owner_number','')) ?>"
          placeholder="+923001234567  (include country code, with + prefix)"
          class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus font-mono">
        <p class="text-xs text-gray-400 mt-1">Include country code. Example: +923001234567 for Pakistan</p>
      </div>

      <!-- CallMeBot fields -->
      <div id="callmebotFields" class="<?= $waProvider==='callmebot'?'':'hidden' ?> space-y-4 p-4 bg-green-50 rounded-xl border border-green-100">
        <div class="flex items-start gap-3">
          <i class="fa-brands fa-whatsapp text-2xl mt-0.5" style="color:#25d366"></i>
          <div>
            <h4 class="font-bold text-gray-800 text-sm">CallMeBot Setup (Free)</h4>
            <p class="text-xs text-gray-500 mt-1 leading-relaxed">Send a WhatsApp message to <strong>+34 644 52 74 69</strong> with the text:<br>
            <code class="bg-white px-2 py-0.5 rounded border border-green-200 text-xs font-mono">I allow callmebot to send me messages</code><br>
            You'll receive your API key within seconds.</p>
          </div>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">CallMeBot API Key</label>
          <input type="text" name="wa_callmebot_key" value="<?= htmlspecialchars(getSetting('wa_callmebot_key','')) ?>"
            placeholder="e.g. 1234567"
            class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus font-mono">
        </div>
      </div>

      <!-- Twilio fields -->
      <div id="twilioFields" class="<?= $waProvider==='twilio'?'':'hidden' ?> space-y-4">

        <!-- ⚠️ Sandbox join instruction — most important step -->
        <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl">
          <div class="flex items-start gap-3">
            <i class="fa-brands fa-whatsapp text-2xl text-amber-600 mt-0.5 flex-shrink-0"></i>
            <div>
              <h4 class="font-bold text-amber-800 text-sm mb-2">⚠️ Sandbox Opt-in Required First</h4>
              <p class="text-xs text-amber-700 leading-relaxed mb-3">
                Before you can receive any message, your WhatsApp number must join the Twilio sandbox.<br>
                <strong>Open WhatsApp on your phone and send this message to +1 415 523 8886:</strong>
              </p>
              <div class="flex items-center gap-2 flex-wrap">
                <code id="sandboxCode" class="bg-white border border-amber-300 rounded-lg px-3 py-2 text-sm font-mono font-bold text-amber-900 select-all">
                  <?= htmlspecialchars(getSetting('wa_twilio_sandbox_word', 'join <your-sandbox-word>')) ?>
                </code>
                <span class="text-xs text-amber-600">(find your word in Twilio Console → Messaging → Try it out → Send a WhatsApp message)</span>
              </div>
              <div class="mt-3">
                <label class="block text-xs font-semibold text-amber-700 mb-1">Your Sandbox Join Word (to save for reference)</label>
                <input type="text" name="wa_twilio_sandbox_word" value="<?= htmlspecialchars(getSetting('wa_twilio_sandbox_word','')) ?>"
                  placeholder="e.g.  join bright-tiger"
                  class="w-full border border-amber-300 bg-white rounded-xl px-3 py-2 text-sm font-mono">
              </div>
            </div>
          </div>
        </div>

        <!-- Credentials -->
        <div class="p-4 bg-gray-50 rounded-xl border border-gray-200 space-y-3">
          <h4 class="font-bold text-gray-800 text-sm">Twilio Credentials</h4>
          <p class="text-xs text-gray-500">Find these in your <a href="https://console.twilio.com" target="_blank" class="text-blue-500 underline">Twilio Console</a> dashboard homepage.</p>
          <div class="grid sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1.5">Account SID</label>
              <input type="text" name="wa_twilio_sid" value="<?= htmlspecialchars(getSetting('wa_twilio_sid','')) ?>"
                placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus font-mono bg-white">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1.5">Auth Token</label>
              <div class="relative">
                <input type="password" name="wa_twilio_token" id="twilioToken" value="<?= htmlspecialchars(getSetting('wa_twilio_token','')) ?>"
                  placeholder="Your auth token"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 pr-10 text-sm input-focus font-mono bg-white">
                <button type="button" onclick="togglePwd('twilioToken',this)" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                  <i class="fa-solid fa-eye text-xs"></i>
                </button>
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1.5">From Number (Sandbox)</label>
              <input type="text" name="wa_twilio_from" value="<?= htmlspecialchars(getSetting('wa_twilio_from','+14155238886')) ?>"
                placeholder="+14155238886"
                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus font-mono bg-white">
              <p class="text-[10px] text-gray-400 mt-1">Sandbox from number is always +14155238886</p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1.5">Your WhatsApp Number (To)</label>
              <input type="text" name="wa_owner_number" id="waOwnerNumberTwilio"
                value="<?= htmlspecialchars(getSetting('wa_owner_number','')) ?>"
                placeholder="+923001234567"
                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm input-focus font-mono bg-white">
              <p class="text-[10px] text-gray-400 mt-1">Must include + and country code</p>
            </div>
          </div>
        </div>

        <!-- Debug info panel -->
        <?php
        require_once __DIR__ . '/../includes/whatsapp.php';
        $waDebug = WhatsApp::getDebugInfo();
        if ($waProvider === 'twilio'): ?>
        <div class="p-3 bg-gray-900 rounded-xl text-xs font-mono space-y-1">
          <div class="text-gray-400 mb-2 uppercase tracking-wider text-[10px]">Config Check</div>
          <div class="flex justify-between"><span class="text-gray-500">Account SID</span><span class="<?= $waDebug['sid_set']?'text-green-400':'text-red-400' ?>"><?= $waDebug['sid_preview'] ?></span></div>
          <div class="flex justify-between"><span class="text-gray-500">Auth Token</span><span class="<?= $waDebug['token_set']?'text-green-400':'text-red-400' ?>"><?= $waDebug['token_set']?'Set ✓':'Not set ✗' ?></span></div>
          <div class="flex justify-between"><span class="text-gray-500">From</span><span class="text-blue-400"><?= htmlspecialchars($waDebug['from_built']) ?></span></div>
          <div class="flex justify-between"><span class="text-gray-500">To</span><span class="text-blue-400"><?= htmlspecialchars($waDebug['to_built']) ?></span></div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Message preview -->
      <div id="waMessagePreview" class="<?= $waProvider==='disabled'?'hidden':'' ?> p-4 bg-gray-900 rounded-xl">
        <div class="text-xs text-gray-400 mb-2 uppercase tracking-wider">Message Preview</div>
        <pre class="text-xs text-green-400 whitespace-pre-wrap leading-relaxed font-mono">📅 *New Appointment — <?= htmlspecialchars(getSetting('site_name','BookFlow')) ?>*

👤 *Customer:* John Smith
📧 *Email:* john@example.com
📞 *Phone:* +923001234567
💼 *Service(s):* Eye Exam, Contact Lenses
📆 *Date:* Wed, May 6 2026
🕐 *Time:* 2:30 PM
🔖 *Ref:* BF-72E3BEB5

🔗 View: <?= BASE_URL ?>/admin/appointments?view=1</pre>
      </div>

      <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
        <!-- Test button -->
        <form id="form-test-wa" method="POST" class="inline">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <input type="hidden" name="section" value="test_whatsapp">
          <button type="submit" id="waTestBtn"
            class="<?= $waProvider==='disabled'?'hidden':'' ?> flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition">
            <i class="fa-brands fa-whatsapp" style="color:#25d366"></i> Send Test Message
          </button>
        </form>
        <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-sm font-semibold ml-auto">
          Save WhatsApp Settings
        </button>
      </div>
    </form>
  </div>

  <script>
  function switchProvider(val) {
    // Update card highlight
    document.querySelectorAll('.provider-opt').forEach(el => {
      const radio = el.querySelector('input[type=radio]');
      el.classList.toggle('border-green-500', radio.value === val);
      el.classList.toggle('bg-green-50', radio.value === val);
      el.classList.toggle('border-gray-200', radio.value !== val);
    });
    document.getElementById('callmebotFields').classList.toggle('hidden', val !== 'callmebot');
    document.getElementById('twilioFields').classList.toggle('hidden', val !== 'twilio');
    document.getElementById('waNumberRow').classList.toggle('hidden', val === 'disabled');
    document.getElementById('waMessagePreview').classList.toggle('hidden', val === 'disabled');
    document.getElementById('waTestBtn').classList.toggle('hidden', val === 'disabled');
  }
  </script>

</div>

<script>
function togglePwd(inputId, btn) {
  const inp = document.getElementById(inputId);
  const showing = inp.type === 'text';
  inp.type = showing ? 'password' : 'text';
  btn.querySelector('i').className = showing ? 'fa-solid fa-eye text-xs' : 'fa-solid fa-eye-slash text-xs';
}

// ── AJAX Settings Save with Toast Notifications ──────────────────────────
const AJAX_URL = '<?= BASE_URL ?>/admin/ajax-settings';

function showToast(msg, type = 'success') {
  const container = document.getElementById('toast') || (() => {
    const d = document.createElement('div');
    d.id = 'toast';
    d.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
    document.body.appendChild(d);
    return d;
  })();
  const t = document.createElement('div');
  t.style.cssText = `display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:14px;
    font-size:13px;font-weight:600;box-shadow:0 8px 32px rgba(0,0,0,0.15);min-width:260px;max-width:380px;
    animation:slideInUp 0.3s ease;
    background:${type==='success'?'#111827':'#dc2626'};color:#fff;`;
  t.innerHTML = `<i class="fa-solid ${type==='success'?'fa-circle-check':'fa-circle-exclamation'}" style="font-size:16px;flex-shrink:0"></i><span>${msg}</span>`;
  container.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(20px)'; t.style.transition = 'all 0.3s'; }, 3500);
  setTimeout(() => t.remove(), 3900);
}

async function saveSection(form, section) {
  const btn = form.querySelector('[type=submit]');
  const origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

  try {
    const fd = new FormData(form);
    fd.set('section', section);

    const res  = await fetch(AJAX_URL, { method: 'POST', body: fd });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); }
    catch(e) { throw new Error('Server returned: ' + text.slice(0, 200)); }

    showToast(data.msg || (data.ok ? 'Saved!' : 'Error'), data.ok ? 'success' : 'error');
  } catch(e) {
    showToast('Failed: ' + e.message, 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = origHtml;
  }
  return false;
}

// ── Wire up all settings forms ────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const sectionForms = {
    'form-general':    'general',
    'form-branding':   'branding',
    'form-email':      'email',
    'form-mailgun':    'mailgun',
    'form-whatsapp':   'whatsapp',
    'form-account':    'account',
    'form-test-email': 'test_email',
    'form-test-wa':    'test_whatsapp',
  };
  Object.entries(sectionForms).forEach(([id, section]) => {
    const form = document.getElementById(id);
    if (form) {
      form.addEventListener('submit', e => {
        e.preventDefault();
        saveSection(form, section);
      });
    }
  });
});
</script>

<style>
@keyframes slideInUp {
  from { opacity:0; transform:translateY(16px); }
  to   { opacity:1; transform:translateY(0); }
}
</style>

<?php include __DIR__ . '/layout/footer.php'; ?>
