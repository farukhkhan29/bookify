<?php
// admin/layout/header.php
if (!defined('PAGE_TITLE')) define('PAGE_TITLE', 'Dashboard');
$user = Auth::currentUser();
$siteName = getSetting('site_name', 'BookFlow');
$logo = getSetting('site_logo', '');
$primaryColor = getSetting('dashboard_primary_color', '#6366f1');
$secondaryColor = getSetting('dashboard_secondary_color', '#8b5cf6');
$sidebarColor = getSetting('dashboard_sidebar_color', '#0f172a');

// Convert hex to RGB for CSS variables
function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    return implode(', ', [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))]);
}
$primaryRgb = hexToRgb($primaryColor);
$secondaryRgb = hexToRgb($secondaryColor);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= PAGE_TITLE ?> — <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#f0f0ff',
          100: '#e0e0ff',
          500: '<?= $primaryColor ?>',
          600: '<?= $primaryColor ?>',
          700: '<?= $primaryColor ?>',
        }
      },
      fontFamily: {
        sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'],
      }
    }
  }
}
</script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
<style>
  :root {
    --primary: <?= $primaryColor ?>;
    --primary-rgb: <?= $primaryRgb ?>;
    --secondary: <?= $secondaryColor ?>;
    --sidebar: <?= $sidebarColor ?>;
  }
  * { font-family: 'Plus Jakarta Sans', sans-serif; }
  .sidebar-bg { background: var(--sidebar); }
  .brand-gradient { background: linear-gradient(135deg, var(--primary), var(--secondary)); }
  .brand-color { color: var(--primary); }
  .brand-bg { background: var(--primary); }
  .brand-border { border-color: var(--primary); }
  .nav-item { transition: all 0.2s ease; border-radius: 10px; }
  .nav-item:hover, .nav-item.active {
    background: rgba(var(--primary-rgb), 0.15);
    color: var(--primary) !important;
  }
  .nav-item.active { border-left: 3px solid var(--primary); }
  .nav-item i { width: 20px; }
  .card-hover { transition: transform 0.2s ease, box-shadow 0.2s ease; }
  .card-hover:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(0,0,0,0.12); }
  .stat-card { position: relative; overflow: hidden; }
  .stat-card::before {
    content: '';
    position: absolute;
    top: -30px; right: -30px;
    width: 100px; height: 100px;
    border-radius: 50%;
    background: rgba(var(--primary-rgb), 0.08);
  }
  .glass { backdrop-filter: blur(12px); background: rgba(255,255,255,0.7); }
  .scrollbar-thin::-webkit-scrollbar { width: 4px; }
  .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
  .scrollbar-thin::-webkit-scrollbar-thumb { background: rgba(var(--primary-rgb), 0.3); border-radius: 2px; }
  .table-row { transition: background 0.15s; }
  .table-row:hover { background: rgba(var(--primary-rgb), 0.04); }
  .badge-pending { background: #fef3c7; color: #92400e; }
  .badge-confirmed { background: #d1fae5; color: #065f46; }
  .badge-cancelled { background: #fee2e2; color: #991b1b; }
  .badge-completed { background: #e0e7ff; color: #3730a3; }
  .modal-overlay { backdrop-filter: blur(4px); }
  .input-focus:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15); outline: none; }
  .btn-primary { background: var(--primary); color: #fff; transition: all 0.2s; }
  .btn-primary:hover { filter: brightness(1.1); transform: translateY(-1px); }
  .pulse-dot::after { content: ''; display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981; margin-left: 6px; animation: pulse-anim 2s infinite; }
  @keyframes pulse-anim { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
  .sidebar-link { display: flex; align-items: center; gap: 12px; padding: 10px 14px; color: rgba(255,255,255,0.6); font-size: 14px; font-weight: 500; border-radius: 10px; text-decoration: none; transition: all 0.2s; border-left: 3px solid transparent; }
  .sidebar-link:hover { color: #fff; background: rgba(255,255,255,0.08); }
  .sidebar-link.active { color: var(--primary); background: rgba(var(--primary-rgb), 0.15); border-left-color: var(--primary); }
  .sidebar-section { font-size: 10px; text-transform: uppercase; letter-spacing: 1.2px; color: rgba(255,255,255,0.3); padding: 16px 14px 6px; font-weight: 600; }
  @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
  .fade-in-up { animation: fadeInUp 0.4s ease forwards; }
  @keyframes slideInLeft { from { opacity: 0; transform: translateX(-20px); } to { opacity: 1; transform: translateX(0); } }
  .slide-in-left { animation: slideInLeft 0.3s ease forwards; }
  .dropdown-menu { transform-origin: top right; }
  .toaster { position: fixed; top: 20px; right: 20px; z-index: 9999; }
  .toast { animation: slideInRight 0.3s ease; }
  @keyframes slideInRight { from { transform: translateX(100px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
</style>
</head>
<body class="bg-gray-50 text-gray-800">

<!-- Toast Container -->
<div class="toaster" id="toaster"></div>

<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$nav = [
  ['href' => BASE_URL.'/admin',              'icon' => 'fa-gauge-high',      'label' => 'Dashboard',     'id' => 'index'],
  ['href' => BASE_URL.'/admin/appointments', 'icon' => 'fa-calendar-check',  'label' => 'Appointments',  'id' => 'appointments'],
  ['href' => BASE_URL.'/admin/services',     'icon' => 'fa-briefcase-medical','label' => 'Services',     'id' => 'services'],
  ['href' => BASE_URL.'/admin/slots',        'icon' => 'fa-clock',            'label' => 'Time Slots',   'id' => 'slots'],
  ['href' => BASE_URL.'/admin/calendar',     'icon' => 'fa-calendar-xmark',  'label' => 'Blocked Dates', 'id' => 'calendar'],
  ['href' => BASE_URL.'/admin/form-builder', 'icon' => 'fa-wand-magic-sparkles','label' => 'Form Builder','id' => 'form-builder'],
  ['href' => BASE_URL.'/admin/embed',        'icon' => 'fa-code',             'label' => 'Embed & Share', 'id' => 'embed'],
  ['href' => BASE_URL.'/admin/settings',     'icon' => 'fa-gear',             'label' => 'Settings',     'id' => 'settings'],
];
?>

<div class="flex h-screen overflow-hidden">
  <!-- Sidebar -->
  <aside id="sidebar" class="sidebar-bg w-64 flex-shrink-0 flex flex-col h-screen overflow-y-auto scrollbar-thin slide-in-left" style="z-index: 100;">
    <!-- Logo -->
    <div class="p-5 border-b border-white/10">
      <a href="<?= BASE_URL ?>/admin" class="flex items-center gap-3">
        <?php if ($logo): ?>
          <img src="<?= BASE_URL ?>/<?= $logo ?>" alt="Logo" class="h-9 w-auto object-contain">
        <?php else: ?>
          <div class="w-9 h-9 rounded-xl brand-gradient flex items-center justify-center shadow-lg">
            <i class="fa-solid fa-calendar-days text-white text-sm"></i>
          </div>
        <?php endif; ?>
        <span class="text-white font-bold text-lg"><?= htmlspecialchars($siteName) ?></span>
      </a>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 p-3 space-y-0.5">
      <div class="sidebar-section">Main Menu</div>
      <?php foreach (array_slice($nav, 0, 5) as $item): ?>
        <a href="<?= $item['href'] ?>" class="sidebar-link <?= $currentPage === $item['id'] ? 'active' : '' ?>">
          <i class="fa-solid <?= $item['icon'] ?> text-sm"></i>
          <?= $item['label'] ?>
        </a>
      <?php endforeach; ?>

      <div class="sidebar-section">Configuration</div>
      <?php foreach (array_slice($nav, 5) as $item): ?>
        <a href="<?= $item['href'] ?>" class="sidebar-link <?= $currentPage === $item['id'] ? 'active' : '' ?>">
          <i class="fa-solid <?= $item['icon'] ?> text-sm"></i>
          <?= $item['label'] ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <!-- User Footer -->
    <div class="p-3 border-t border-white/10">
      <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/5 transition-colors cursor-pointer group relative" onclick="toggleUserMenu()">
        <div class="w-9 h-9 rounded-xl brand-gradient flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
          <?= strtoupper(substr($user['name'], 0, 1)) ?>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-white text-sm font-medium truncate"><?= htmlspecialchars($user['name']) ?></p>
          <p class="text-white/40 text-xs truncate"><?= htmlspecialchars($user['email']) ?></p>
        </div>
        <i class="fa-solid fa-chevron-up text-white/30 text-xs"></i>

        <!-- User Dropdown -->
        <div id="userMenu" class="hidden absolute bottom-full left-0 right-0 mb-2 bg-white rounded-xl shadow-xl border border-gray-100 p-2 z-50">
          <a href="<?= BASE_URL ?>/admin/settings#account" class="flex items-center gap-3 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg">
            <i class="fa-solid fa-user text-gray-400 w-4"></i> Account Settings
          </a>
          <hr class="my-1 border-gray-100">
          <a href="<?= BASE_URL ?>/admin/logout" class="flex items-center gap-3 px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg">
            <i class="fa-solid fa-right-from-bracket w-4"></i> Sign Out
          </a>
        </div>
      </div>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 flex flex-col overflow-hidden">
    <!-- Top Bar -->
    <header class="bg-white border-b border-gray-100 px-6 py-4 flex items-center justify-between flex-shrink-0">
      <div>
        <h1 class="text-xl font-bold text-gray-900"><?= PAGE_TITLE ?></h1>
        <p class="text-xs text-gray-400 mt-0.5"><?= date('l, F j, Y') ?></p>
      </div>
      <div class="flex items-center gap-3">
        <div class="text-xs text-gray-400 pulse-dot">Live</div>
        <a href="<?= BASE_URL ?>/book" target="_blank" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-xl btn-primary shadow-sm">
          <i class="fa-solid fa-external-link-alt text-xs"></i>
          View Form
        </a>
      </div>
    </header>

    <!-- Page Content -->
    <div class="flex-1 overflow-y-auto p-6 scrollbar-thin" id="pageContent">
