/**
 * BookFlow Widget Loader
 * Embeds the booking form via JavaScript on any website.
 *
 * Usage:
 *   <div id="bookflow-widget"></div>
 *   <script src="https://yourdomain.com/embed/widget.js"
 *           data-target="bookflow-widget"
 *           data-service="1"
 *           data-theme="minimal"
 *           data-primary-color="#6366f1"></script>
 */
(function () {
    'use strict';

    var BOOKFLOW_BASE = (function () {
        var scripts = document.getElementsByTagName('script');
        var current = scripts[scripts.length - 1];
        var src = current.src;
        return src.replace(/\/embed\/widget\.js.*$/, '');
    })();

    function getCurrentScript() {
        var scripts = document.getElementsByTagName('script');
        return scripts[scripts.length - 1];
    }

    function getAttr(el, name, fallback) {
        var val = el.getAttribute('data-' + name);
        return val !== null ? val : fallback;
    }

    function buildIframe(options) {
        var params = new URLSearchParams();
        if (options.service)      params.set('service', options.service);
        if (options.theme)        params.set('theme', options.theme);
        if (options.primaryColor) params.set('primary_color', options.primaryColor);
        if (options.redirect)     params.set('redirect', options.redirect);

        var iframe = document.createElement('iframe');
        iframe.src = BOOKFLOW_BASE + '/embed/booking-form.php?' + params.toString();
        iframe.style.cssText = [
            'width:100%',
            'min-height:' + (options.height || '680') + 'px',
            'border:none',
            'border-radius:' + (options.radius || '12') + 'px',
            'box-shadow:0 4px 32px rgba(0,0,0,0.10)',
            'background:transparent',
            'display:block',
        ].join(';');
        iframe.setAttribute('allowtransparency', 'true');
        iframe.setAttribute('scrolling', 'auto');
        iframe.setAttribute('title', 'Book an Appointment');
        iframe.setAttribute('loading', 'lazy');

        // Auto-resize via postMessage
        window.addEventListener('message', function (e) {
            if (e.origin !== BOOKFLOW_BASE) return;
            if (e.data && e.data.type === 'bookflow:resize') {
                iframe.style.minHeight = (e.data.height + 40) + 'px';
            }
            if (e.data && e.data.type === 'bookflow:booked') {
                var event = new CustomEvent('bookflow:booked', { detail: e.data.booking });
                document.dispatchEvent(event);
            }
        });

        return iframe;
    }

    function buildStyles(options) {
        var primary = options.primaryColor || '#6366f1';
        var style = document.createElement('style');
        style.textContent = [
            '#bookflow-modal-overlay {',
            '  position:fixed;top:0;left:0;width:100%;height:100%;',
            '  background:rgba(0,0,0,0.55);z-index:99998;',
            '  display:flex;align-items:center;justify-content:center;',
            '  opacity:0;transition:opacity .25s ease;',
            '}',
            '#bookflow-modal-overlay.visible { opacity:1; }',
            '#bookflow-modal-box {',
            '  background:#fff;border-radius:16px;width:min(96vw,680px);',
            '  max-height:90vh;overflow:hidden;position:relative;',
            '  transform:scale(.93) translateY(20px);transition:transform .28s cubic-bezier(.34,1.56,.64,1);',
            '}',
            '#bookflow-modal-overlay.visible #bookflow-modal-box { transform:scale(1) translateY(0); }',
            '#bookflow-modal-close {',
            '  position:absolute;top:12px;right:14px;z-index:10;',
            '  background:' + primary + ';color:#fff;border:none;border-radius:50%;',
            '  width:32px;height:32px;font-size:18px;line-height:32px;text-align:center;',
            '  cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.18);',
            '}',
            '.bookflow-trigger {',
            '  display:inline-block;cursor:pointer;',
            '  background:' + primary + ';color:#fff;',
            '  padding:12px 28px;border-radius:8px;font-size:16px;',
            '  font-family:inherit;border:none;font-weight:600;',
            '  box-shadow:0 2px 12px rgba(0,0,0,.12);',
            '  transition:opacity .18s;',
            '}',
            '.bookflow-trigger:hover { opacity:.85; }',
        ].join('\n');
        return style;
    }

    function initWidget() {
        var script = getCurrentScript();
        var targetId = getAttr(script, 'target', 'bookflow-widget');
        var mode     = getAttr(script, 'mode', 'inline'); // inline | modal | button
        var service  = getAttr(script, 'service', '');
        var theme    = getAttr(script, 'theme', 'minimal');
        var primary  = getAttr(script, 'primary-color', '#6366f1');
        var height   = getAttr(script, 'height', '680');
        var radius   = getAttr(script, 'radius', '12');
        var redirect = getAttr(script, 'redirect', '');
        var btnText  = getAttr(script, 'button-text', 'Book an Appointment');

        var options = { service: service, theme: theme, primaryColor: primary, height: height, radius: radius, redirect: redirect };

        if (mode === 'inline') {
            var container = document.getElementById(targetId);
            if (!container) {
                console.warn('[BookFlow] Target element #' + targetId + ' not found.');
                return;
            }
            container.appendChild(buildIframe(options));

        } else if (mode === 'modal') {
            document.head.appendChild(buildStyles(options));

            // Build modal
            var overlay = document.createElement('div');
            overlay.id = 'bookflow-modal-overlay';
            var box = document.createElement('div');
            box.id = 'bookflow-modal-box';
            var closeBtn = document.createElement('button');
            closeBtn.id = 'bookflow-modal-close';
            closeBtn.innerHTML = '&times;';
            closeBtn.setAttribute('aria-label', 'Close booking form');
            closeBtn.addEventListener('click', closeModal);
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeModal();
            });
            var iframe = buildIframe(options);
            iframe.style.borderRadius = '0';
            iframe.style.boxShadow = 'none';
            iframe.style.minHeight = height + 'px';
            iframe.style.maxHeight = '88vh';
            box.appendChild(closeBtn);
            box.appendChild(iframe);
            overlay.appendChild(box);
            document.body.appendChild(overlay);

            function openModal() {
                overlay.style.display = 'flex';
                requestAnimationFrame(function () { overlay.classList.add('visible'); });
                document.body.style.overflow = 'hidden';
            }
            function closeModal() {
                overlay.classList.remove('visible');
                document.body.style.overflow = '';
                setTimeout(function () { overlay.style.display = 'none'; }, 280);
            }

            // Trigger button
            var trigger = document.getElementById(targetId);
            if (trigger) {
                trigger.classList.add('bookflow-trigger');
                trigger.addEventListener('click', openModal);
            } else {
                // Create floating button
                var btn = document.createElement('button');
                btn.className = 'bookflow-trigger';
                btn.textContent = btnText;
                btn.addEventListener('click', openModal);
                document.body.appendChild(btn);
            }

            // Global open/close API
            window.BookFlow = { open: openModal, close: closeModal };

        } else if (mode === 'button') {
            // Just a styled button that opens the form in a new tab
            document.head.appendChild(buildStyles(options));
            var btnEl = document.getElementById(targetId);
            if (btnEl) {
                btnEl.classList.add('bookflow-trigger');
                var params2 = new URLSearchParams();
                if (service) params2.set('service', service);
                if (theme)   params2.set('theme', theme);
                var url = BOOKFLOW_BASE + '/embed/booking-form.php?' + params2.toString();
                btnEl.addEventListener('click', function () { window.open(url, '_blank'); });
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWidget);
    } else {
        initWidget();
    }

})();
