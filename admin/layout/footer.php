
    </div><!-- End pageContent -->
  </main>
</div>

<script>
// Toast notification system
function showToast(message, type = 'success') {
  const toaster = document.getElementById('toaster');
  const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle', warning: 'fa-exclamation-triangle' };
  const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500', warning: 'bg-yellow-500' };
  const toast = document.createElement('div');
  toast.className = `toast flex items-center gap-3 ${colors[type]} text-white px-5 py-3 rounded-xl shadow-xl mb-3 min-w-72 max-w-sm`;
  toast.innerHTML = `<i class="fa-solid ${icons[type]} text-sm flex-shrink-0"></i><span class="text-sm font-medium flex-1">${message}</span><button onclick="this.parentElement.remove()" class="opacity-70 hover:opacity-100"><i class="fa-solid fa-times text-xs"></i></button>`;
  toaster.prepend(toast);
  setTimeout(() => { toast.style.animation = 'none'; toast.style.opacity = '0'; toast.style.transform = 'translateX(100px)'; toast.style.transition = 'all 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
}

// User menu toggle
function toggleUserMenu() {
  const menu = document.getElementById('userMenu');
  menu.classList.toggle('hidden');
}
document.addEventListener('click', (e) => {
  const menu = document.getElementById('userMenu');
  if (menu && !menu.parentElement.contains(e.target)) menu.classList.add('hidden');
});

// GSAP page animations
gsap.from('#pageContent > *', {
  opacity: 0, y: 20, duration: 0.4, stagger: 0.05, ease: 'power2.out',
  clearProps: 'all'
});

// Flash messages from PHP session
<?php if (!empty($_SESSION['flash_message'])): ?>
showToast(<?= json_encode($_SESSION['flash_message']['text']) ?>, <?= json_encode($_SESSION['flash_message']['type']) ?>);
<?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>
</script>
</body>
</html>
