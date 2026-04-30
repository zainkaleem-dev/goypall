<?php
require_once dirname(__DIR__) . '/init.php';
require_admin_login();

global $db;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload') {
        if (empty($_FILES['screenshots']['tmp_name'][0])) {
            flash('error', 'Please choose at least one image to upload.');
        } else {
            $allowed = ['image/png','image/jpeg','image/jpg','image/gif','image/webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $uploaded = 0;
            $errors = [];
            
            $maxOrder = (int)($db->fetch("SELECT MAX(sort_order) m FROM " . DB_PREFIX . "screenshots")['m'] ?? 0);
            
            foreach ($_FILES['screenshots']['tmp_name'] as $i => $tmp) {
                if (!is_uploaded_file($tmp)) continue;
                
                $name = $_FILES['screenshots']['name'][$i];
                $size = $_FILES['screenshots']['size'][$i];
                
                $mime = $finfo->file($tmp);
                if (!in_array($mime, $allowed)) {
                    $errors[] = "$name: invalid file type";
                    continue;
                }
                if ($size > 5 * 1024 * 1024) {
                    $errors[] = "$name: file too large (max 5 MB)";
                    continue;
                }
                
                $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'png';
                $ext = strtolower(preg_replace('/[^a-z0-9]/i', '', $ext));
                $newName = 'shot_' . time() . '_' . substr(md5($name . microtime()), 0, 8) . '.' . $ext;
                $dest = UPLOADS_PATH . '/screenshots/' . $newName;
                
                if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0755, true);
                
                if (move_uploaded_file($tmp, $dest)) {
                    $maxOrder++;
                    $db->insert('screenshots', [
                        'title' => pathinfo($name, PATHINFO_FILENAME),
                        'caption' => '',
                        'image_url' => '/uploads/screenshots/' . $newName,
                        'sort_order' => $maxOrder,
                        'status' => 'active',
                    ]);
                    $uploaded++;
                } else {
                    $errors[] = "$name: upload failed";
                }
            }
            
            if ($uploaded > 0) {
                admin_log('screenshot_upload', "$uploaded screenshots uploaded");
                flash('success', "Uploaded $uploaded screenshot(s)." . ($errors ? ' Errors: ' . implode(', ', $errors) : ''));
            } else {
                flash('error', 'No screenshots uploaded. ' . implode(', ', $errors));
            }
        }
        redirect('/admin/screenshots.php');
    }
    
    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $caption = trim($_POST['caption'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = ($_POST['status'] ?? 'active') === 'hidden' ? 'hidden' : 'active';
        
        if ($id > 0) {
            $db->query("UPDATE " . DB_PREFIX . "screenshots SET title = ?, caption = ?, sort_order = ?, status = ? WHERE id = ?",
                [$title, $caption, $sortOrder, $status, $id]);
            admin_log('screenshot_update', "ID $id");
            flash('success', 'Screenshot updated.');
        }
        redirect('/admin/screenshots.php');
    }
    
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $shot = $db->fetch("SELECT image_url FROM " . DB_PREFIX . "screenshots WHERE id = ?", [$id]);
        if ($shot) {
            $file = ROOT_PATH . $shot['image_url'];
            if (file_exists($file)) @unlink($file);
            $db->query("DELETE FROM " . DB_PREFIX . "screenshots WHERE id = ?", [$id]);
            admin_log('screenshot_delete', "ID $id");
            flash('success', 'Screenshot deleted.');
        }
        redirect('/admin/screenshots.php');
    }
    
    if ($action === 'reorder') {
        $order = $_POST['order'] ?? [];
        if (is_array($order)) {
            foreach ($order as $sort => $id) {
                $id = (int)$id;
                $sort = (int)$sort;
                if ($id > 0) {
                    $db->query("UPDATE " . DB_PREFIX . "screenshots SET sort_order = ? WHERE id = ?", [$sort, $id]);
                }
            }
            admin_log('screenshot_reorder');
            flash('success', 'Order updated.');
        }
        redirect('/admin/screenshots.php');
    }
}

$screenshots = $db->fetchAll("SELECT * FROM " . DB_PREFIX . "screenshots ORDER BY sort_order ASC, id ASC");

include __DIR__ . '/_header.php';
?>

<div class="page-head">
    <div>
        <h1>🖼️ Screenshots</h1>
        <div class="subtitle">Manage the carousel images shown on your pitch page (<?php echo count($screenshots); ?> total)</div>
    </div>
</div>

<div class="card">
    <h2>📤 Upload Screenshots</h2>
    <p class="muted mb-2">Upload one or more images. Recommended: 1920×1080 or 16:9 ratio for best display in the carousel.</p>
    
    <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="upload">
        
        <div class="form-group">
            <label>Choose Images (multiple allowed)</label>
            <input type="file" name="screenshots[]" multiple accept="image/png,image/jpeg,image/gif,image/webp" required>
            <small>PNG, JPG, GIF, or WebP. Max 5 MB per image.</small>
        </div>
        
        <button type="submit" class="btn btn-primary">📤 Upload</button>
    </form>
</div>

<div class="card">
    <h2>📋 All Screenshots</h2>
    
    <?php if (empty($screenshots)): ?>
        <p class="muted text-center" style="padding:30px;">No screenshots yet. Upload some above to get started.</p>
    <?php else: ?>
        <p class="muted mb-2">💡 Tip: Lower sort numbers appear first. Click "Edit" to change details or hide a screenshot.</p>
        
        <div class="shot-grid">
            <?php foreach ($screenshots as $shot): ?>
            <div class="shot-item">
                <div class="shot-thumb" style="background-image:url('<?php echo escape($shot['image_url']); ?>');" 
                     onclick="window.open('<?php echo escape($shot['image_url']); ?>', '_blank')"></div>
                <div class="shot-info">
                    <div class="shot-title"><?php echo escape($shot['title'] ?: 'Untitled'); ?></div>
                    <div style="display:flex;justify-content:space-between;align-items:center;font-size:11px;color:var(--c-text-muted);margin-top:4px;">
                        <span>Sort: <?php echo (int)$shot['sort_order']; ?></span>
                        <span class="badge badge-<?php echo $shot['status'] === 'active' ? 'success' : 'neutral'; ?>"><?php echo $shot['status']; ?></span>
                    </div>
                    <div class="shot-actions">
                        <button class="btn btn-sm btn-secondary" onclick="openEditModal(<?php echo (int)$shot['id']; ?>, <?php echo htmlspecialchars(json_encode([
                            'title' => $shot['title'],
                            'caption' => $shot['caption'],
                            'sort_order' => $shot['sort_order'],
                            'status' => $shot['status'],
                        ]), ENT_QUOTES, 'UTF-8'); ?>)">✏️ Edit</button>
                        <form method="POST" style="display:inline;flex:1;" onsubmit="return confirmDelete('Delete this screenshot? This cannot be undone.');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$shot['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger" style="width:100%;">🗑</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <h3>Edit Screenshot</h3>
        <form method="POST" id="editForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">
            
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" id="editTitle">
            </div>
            <div class="form-group">
                <label>Caption (shown in carousel and lightbox)</label>
                <textarea name="caption" id="editCaption" rows="2"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" id="editSort" min="0">
                    <small>Lower numbers appear first</small>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="editStatus">
                        <option value="active">Active (shown on site)</option>
                        <option value="hidden">Hidden</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, data) {
    document.getElementById('editId').value = id;
    document.getElementById('editTitle').value = data.title || '';
    document.getElementById('editCaption').value = data.caption || '';
    document.getElementById('editSort').value = data.sort_order || 0;
    document.getElementById('editStatus').value = data.status || 'active';
    openModal('editModal');
}
</script>

<?php include __DIR__ . '/_footer.php'; ?>
