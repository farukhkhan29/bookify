<?php
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();
define('PAGE_TITLE', 'Embed & Share');
$baseUrl = BASE_URL;
include __DIR__ . '/layout/header.php';
?>

<div class="max-w-2xl space-y-5">
  <div class="bg-indigo-50 rounded-2xl p-5 border border-indigo-100">
    <div class="flex items-start gap-3">
      <div class="w-10 h-10 rounded-xl brand-bg flex items-center justify-center flex-shrink-0 shadow-sm">
        <i class="fa-solid fa-code text-white text-sm"></i>
      </div>
      <div>
        <h3 class="font-bold text-gray-900">Embed Your Booking Form</h3>
        <p class="text-sm text-gray-600 mt-1">Copy and paste any of the following codes into your website to embed the booking form.</p>
      </div>
    </div>
  </div>

  <!-- Direct Link -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <h3 class="font-semibold text-gray-900 mb-3">Direct Link</h3>
    <div class="flex gap-2">
      <code class="flex-1 bg-gray-50 rounded-xl px-4 py-2.5 text-sm text-gray-700 border border-gray-200 font-mono overflow-x-auto"><?= $baseUrl ?>/book</code>
      <button onclick="copyCode('directLink')" class="btn-primary px-4 py-2 rounded-xl text-sm font-semibold flex-shrink-0">
        <i class="fa-solid fa-copy mr-1.5"></i>Copy
      </button>
    </div>
    <input type="hidden" id="directLink" value="<?= $baseUrl ?>/book">
    <p class="text-xs text-gray-400 mt-2">Share this URL directly or use as a standalone page.</p>
  </div>

  <!-- iFrame Embed -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <h3 class="font-semibold text-gray-900 mb-1">iFrame Embed</h3>
    <p class="text-xs text-gray-500 mb-3">Embed the form directly in any page using an iFrame.</p>
    <pre class="bg-gray-900 text-green-400 rounded-xl p-4 text-xs overflow-x-auto font-mono leading-relaxed" id="iframeCode">&lt;iframe
  src="<?= $baseUrl ?>/book"
  width="100%"
  height="700"
  frameborder="0"
  scrolling="auto"
  style="border:none;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.08)"&gt;
&lt;/iframe&gt;</pre>
    <button onclick="copyCode('iframeCode')" class="mt-3 btn-primary px-4 py-2 rounded-xl text-sm font-semibold">
      <i class="fa-solid fa-copy mr-1.5"></i>Copy iFrame Code
    </button>
  </div>

  <!-- JavaScript Embed -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <h3 class="font-semibold text-gray-900 mb-1">JavaScript Embed</h3>
    <p class="text-xs text-gray-500 mb-3">A smarter embed that auto-resizes and loads inline without page reload.</p>
    <pre class="bg-gray-900 text-green-400 rounded-xl p-4 text-xs overflow-x-auto font-mono leading-relaxed" id="jsCode">&lt;div id="bookflow-widget"&gt;&lt;/div&gt;
&lt;script&gt;
(function() {
  var script = document.createElement('script');
  script.src = "<?= $baseUrl ?>/embed/widget.js";
  script.setAttribute('data-container', 'bookflow-widget');
  script.setAttribute('data-url', '<?= $baseUrl ?>/book');
  document.head.appendChild(script);
})();
&lt;/script&gt;</pre>
    <button onclick="copyCode('jsCode')" class="mt-3 btn-primary px-4 py-2 rounded-xl text-sm font-semibold">
      <i class="fa-solid fa-copy mr-1.5"></i>Copy JS Embed Code
    </button>
  </div>

  <!-- React Embed -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <h3 class="font-semibold text-gray-900 mb-1">React / Next.js Component</h3>
    <p class="text-xs text-gray-500 mb-3">Drop this component into your React application.</p>
    <pre class="bg-gray-900 text-blue-300 rounded-xl p-4 text-xs overflow-x-auto font-mono leading-relaxed" id="reactCode">export function BookingWidget() {
  return (
    &lt;iframe
      src="<?= $baseUrl ?>/book"
      width="100%"
      height={700}
      style={{ border: 'none', borderRadius: 12 }}
      title="Booking Form"
    /&gt;
  );
}</pre>
    <button onclick="copyCode('reactCode')" class="mt-3 btn-primary px-4 py-2 rounded-xl text-sm font-semibold">
      <i class="fa-solid fa-copy mr-1.5"></i>Copy React Code
    </button>
  </div>
</div>

<script>
function copyCode(id) {
  const el = document.getElementById(id);
  const text = el.value || el.textContent;
  navigator.clipboard.writeText(text.trim()).then(() => {
    showToast('Code copied to clipboard!', 'success');
  }).catch(() => {
    // Fallback
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    showToast('Code copied!', 'success');
  });
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
