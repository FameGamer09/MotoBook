        </main>
        <footer class="footer"><p>&copy; <?= date('Y') ?> Motobook Management. All rights reserved.</p></footer>
    </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= defined('MB_ASSET_V') ? MB_ASSET_V : gmdate('Ymd-Hi') ?>" defer crossorigin="anonymous"></script>
<script>
window.addEventListener('error', function (ev) {
    try {
        var el = document.getElementById('mbRuntimeErrors') || (function () {
            var c = document.createElement('div');
            c.id = 'mbRuntimeErrors';
            c.style.cssText = 'position:fixed;bottom:16px;left:16px;z-index:2147483647;max-width:420px;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:10px;padding:.75rem 1rem;font-size:.85rem;font-weight:600;box-shadow:0 10px 25px rgba(15,23,42,.18);';
            document.body.appendChild(c);
            return c;
        })();
        var msg = (ev && (ev.message || (ev.error && ev.error.message)) || String(ev));
        el.innerHTML = '<div style="margin-bottom:.25rem;">Motobook JS error (open DevTools F12 → Console for details):</div><div style="white-space:pre-wrap;word-break:break-word;font-weight:500;">' + String(msg).slice(0, 500).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]); }) + '</div>';
    } catch (_) {}
}, true);
</script>
</body>
</html>
