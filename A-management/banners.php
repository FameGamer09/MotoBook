<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
requirePlatform();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? null) && ($_POST['action'] ?? '') === 'upload') {
    $fields = [
        'title' => trim($_POST['title'] ?? ''),
        'caption' => trim($_POST['caption'] ?? ''),
        'hex_color' => trim($_POST['hex_color'] ?? '#06b6d4'),
        'store_id' => (int) ($_POST['store_id'] ?? 0),
    ];
    $file = null;
    if (!empty($_FILES['image']) && is_array($_FILES['image'])) {
        $file = $_FILES['image'];
    }
    $ok = createBanner($fields, $file);
    flash($ok ? 'success' : 'error', $ok ? 'Banner uploaded successfully.' : 'Banner upload failed.');
    header('Location: ' . APP_URL . '/banners.php');
    exit;
}

$banners = fetchBanners();
$stores = fetchStores();

$pageTitle = 'Promo Banners';
include __DIR__ . '/includes/header.php';
?>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2>Client App Banner Slot</h2></div>
        <div class="card-body">
            <div class="alert alert-info">
                Upload approved promotional graphics (GCash-style ads) created for store discounts or partner campaigns. These appear at the top of the customer app.
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="upload">
                <div class="form-group">
                    <label>Banner title</label>
                    <input class="form-control" name="title" required placeholder="Jollibee 20% Off Partner Campaign">
                </div>
                <div class="form-group">
                    <label>Caption / subtext</label>
                    <input class="form-control" name="caption" placeholder="Limited time until end of month…">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Accent color (hex)</label>
                        <input class="form-control" name="hex_color" type="color" value="#06b6d4">
                    </div>
                    <div class="form-group">
                        <label>Link to store (optional)</label>
                        <select class="form-control" name="store_id">
                            <option value="0">Platform-wide banner</option>
                            <?php foreach ($stores as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"><?= e($s['store_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Graphic file (PNG/JPG, 1200x400 recommended)</label>
                    <input class="form-control" name="image" type="file" accept="image/png,image/jpeg,image/webp">
                </div>
                <button class="btn btn-primary" type="submit">Upload to Banner Slot</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                Live Preview Card
            </h2>
        </div>
        <div class="card-body">
            <div id="previewBanner" class="banner-preview" style="background:linear-gradient(135deg,#06b6d4,#0e7490)">
                <strong style="font-size:1.25rem">Your Banner Title Here</strong>
                <span style="opacity:.9;font-size:.85rem">Your caption appears here…</span>
            </div>
            <p class="text-muted" style="margin-top:.75rem">
                Tip: Use the color picker and upload a graphic above. Past uploaded banners are listed below — these are shown in the customer app rotation.
            </p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
            Uploaded Banners Library
        </h2>
    </div>
    <div class="card-body">
        <div class="grid-2">
            <?php foreach ($banners as $b): ?>
                <div class="card" style="margin:0">
                    <div class="banner-preview" style="background:linear-gradient(135deg,<?= e($b['hex_color'] ?? '#06b6d4') ?>,#164e63);border-radius:12px 12px 0 0">
                        <strong style="font-size:1.1rem"><?= e($b['title']) ?></strong>
                        <?php if (!empty($b['caption'])): ?>
                            <span style="opacity:.9;font-size:.8rem"><?= e($b['caption']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($b['image_path'])): ?>
                            <img src="<?= ADMIN_URL ?>/<?= e($b['image_path']) ?>" alt="Banner graphic" style="max-width:100%;border-radius:8px;border:1px solid var(--cyan-200);margin-bottom:.5rem">
                        <?php endif; ?>
                        <p>
                            <?= !empty($b['is_active']) ? '<span class="badge badge-success">ACTIVE</span>' : '<span class="badge badge-muted">INACTIVE</span>' ?>
                            <?= $b['store_name'] ? ' · Store: ' . e($b['store_name']) : ' · Platform-wide' ?>
                        </p>
                        <small class="text-muted">Uploaded <?= formatDateTime($b['created_at']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($banners)): ?>
                <p class="text-muted" style="grid-column:1/-1">No banners uploaded yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const title = document.querySelector('input[name="title"]');
    const caption = document.querySelector('input[name="caption"]');
    const color = document.querySelector('input[name="hex_color"]');
    const preview = document.getElementById('previewBanner');
    function update() {
        preview.style.background = 'linear-gradient(135deg,' + (color?.value || '#06b6d4') + ',#164e63)';
        preview.innerHTML = '<strong style="font-size:1.25rem">' + (title?.value || 'Your Banner Title Here') + '</strong>' +
            '<span style="opacity:.9;font-size:.85rem">' + (caption?.value || 'Your caption appears here…') + '</span>';
    }
    [title, caption, color].forEach(el => el && el.addEventListener('input', update));
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
