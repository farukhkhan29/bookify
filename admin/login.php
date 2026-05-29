<?php
require_once __DIR__ . '/../includes/auth.php';

if (Auth::isLoggedIn()) redirect(BASE_URL . '/admin');

$error = '';
$siteName = getSetting('site_name', 'BookFlow');
$primaryColor = getSetting('dashboard_primary_color', '#6366f1');
$logo = getSetting('site_logo', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['_csrf'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (Auth::login($email, $password)) {
            redirect(BASE_URL . '/admin');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
$csrf = Auth::generateCsrf();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
<style>
  * { font-family: 'Plus Jakarta Sans', sans-serif; }
  :root { --primary: <?= $primaryColor ?>; }
  body { background: linear-gradient(135deg, #f0f4ff 0%, #fafafa 50%, #f5f0ff 100%); min-height: 100vh; }
  .login-card { backdrop-filter: blur(20px); background: rgba(255,255,255,0.9); }
  .input-field { border: 1.5px solid #e5e7eb; transition: all 0.2s; }
  .input-field:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.12); outline: none; }
  .btn-login { background: var(--primary); transition: all 0.2s; }
  .btn-login:hover { filter: brightness(1.1); transform: translateY(-1px); }
  .orb { position: absolute; border-radius: 50%; filter: blur(60px); opacity: 0.15; pointer-events: none; }
  .orb-1 { width: 300px; height: 300px; background: var(--primary); top: -100px; right: -100px; }
  .orb-2 { width: 250px; height: 250px; background: #8b5cf6; bottom: -80px; left: -80px; }
  .eye-toggle { cursor: pointer; transition: color 0.2s; }
  .eye-toggle:hover { color: var(--primary); }
</style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 relative overflow-hidden">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>

  <div class="login-card w-full max-w-md rounded-2xl shadow-2xl border border-white/60 p-8 relative z-10" id="loginCard">
    <!-- Logo -->
    <div class="text-center mb-8">
      <?php if ($logo): ?>
        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($logo) ?>" alt="Logo" class="h-14 w-auto mx-auto mb-4 object-contain">
      <?php else: ?>
        <div class="w-16 h-16 rounded-2xl mx-auto mb-4 flex items-center justify-center shadow-lg" style="background:var(--primary);">
          <i class="fa-solid fa-calendar-days text-white text-2xl"></i>
        </div>
      <?php endif; ?>
      <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($siteName) ?></h1>
      <p class="text-gray-500 text-sm mt-1">Sign in to manage your bookings</p>
    </div>

    <?php if ($error): ?>
    <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 text-sm">
      <i class="fa-solid fa-exclamation-circle"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">

      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Email Address</label>
        <div class="relative">
          <i class="fa-solid fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
          <input type="email" name="email" required
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            class="input-field w-full pl-10 pr-4 py-3 rounded-xl text-sm text-gray-800"
            placeholder="admin@bookflow.com">
        </div>
      </div>

      <div class="mb-6">
        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Password</label>
        <div class="relative">
          <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
          <input type="password" name="password" id="passwordField" required
            class="input-field w-full pl-10 pr-12 py-3 rounded-xl text-sm text-gray-800"
            placeholder="Enter your password">
          <button type="button" class="eye-toggle absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400" onclick="togglePassword()">
            <i class="fa-solid fa-eye text-sm" id="eyeIcon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login w-full py-3 rounded-xl text-white font-semibold text-sm shadow-lg">
        <i class="fa-solid fa-right-to-bracket mr-2"></i>
        Sign In to Dashboard
      </button>
    </form>

    <p class="text-center text-xs text-gray-400 mt-6">
      Default: admin@bookflow.com / admin123
    </p>
  </div>

  <script>
  function togglePassword() {
    const field = document.getElementById('passwordField');
    const icon = document.getElementById('eyeIcon');
    if (field.type === 'password') { field.type = 'text'; icon.className = 'fa-solid fa-eye-slash text-sm'; }
    else { field.type = 'password'; icon.className = 'fa-solid fa-eye text-sm'; }
  }
  gsap.from('#loginCard', { opacity: 0, y: 30, duration: 0.6, ease: 'power3.out' });
  gsap.from('#loginCard > *', { opacity: 0, y: 15, duration: 0.4, stagger: 0.08, delay: 0.2, ease: 'power2.out' });
  </script>
</body>
</html>
