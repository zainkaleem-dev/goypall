<?php
require_once dirname(__DIR__) . '/init.php';
require_admin_login();

global $db;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $slug = strtolower(trim($_POST['slug'] ?? ''));
        $slug = preg_replace('/[^a-z0-9-]+/i', '-', $slug);
        $slug = trim($slug, '-');
        $title = trim($_POST['title'] ?? '');
        $content = (string)($_POST['content'] ?? '');
        $metaDesc = trim($_POST['meta_description'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $showInFooter = !empty($_POST['show_in_footer']) ? 1 : 0;
        $status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
        
        if (!$slug || !$title || !$content) {
            flash('error', 'Slug, title, and content are required.');
        } else {
            // Check slug uniqueness
            $sql = "SELECT id FROM " . DB_PREFIX . "pages WHERE slug = ?" . ($id > 0 ? " AND id != ?" : "");
            $params = [$slug];
            if ($id > 0) $params[] = $id;
            $existing = $db->fetch($sql, $params);
            
            if ($existing) {
                flash('error', 'A page with that slug already exists.');
            } elseif ($action === 'create') {
                $db->insert('pages', [
                    'slug' => $slug,
                    'title' => $title,
                    'content' => $content,
                    'meta_description' => $metaDesc,
                    'sort_order' => $sortOrder,
                    'show_in_footer' => $showInFooter,
                    'status' => $status,
                ]);
                admin_log('page_create', $slug);
                flash('success', 'Page created.');
            } else {
                $db->query("UPDATE " . DB_PREFIX . "pages SET slug=?, title=?, content=?, meta_description=?, sort_order=?, show_in_footer=?, status=? WHERE id = ?",
                    [$slug, $title, $content, $metaDesc, $sortOrder, $showInFooter, $status, $id]);
                admin_log('page_update', "$slug (ID $id)");
                flash('success', 'Page updated.');
            }
            redirect('/admin/pages.php?edit=' . ($id ?: $db->pdo()->lastInsertId()));
        }
    }
    
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Don't allow deletion of essential pages
            $page = $db->fetch("SELECT slug FROM " . DB_PREFIX . "pages WHERE id = ?", [$id]);
            if ($page && in_array($page['slug'], ['privacy','terms','refund','full-description'])) {
                flash('error', 'You cannot delete essential pages (Privacy, Terms, Refund, Full Description). Edit them instead.');
            } else {
                $db->query("DELETE FROM " . DB_PREFIX . "pages WHERE id = ?", [$id]);
                admin_log('page_delete', "ID $id");
                flash('success', 'Page deleted.');
            }
        }
        redirect('/admin/pages.php');
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = $editId > 0 ? $db->fetch("SELECT * FROM " . DB_PREFIX . "pages WHERE id = ?", [$editId]) : null;
$creating = isset($_GET['new']);

$pages = $db->fetchAll("SELECT * FROM " . DB_PREFIX . "pages ORDER BY sort_order ASC, id ASC");

include __DIR__ . '/_header.php';
?>

<div class="page-head">
    <div>
        <h1>📄 Pages</h1>
        <div class="subtitle">Edit static pages: Privacy, Terms, Refund, Full Description, etc.</div>
    </div>
    <div>
        <?php if (!$editing && !$creating): ?>
        <a href="/admin/pages.php?new=1" class="btn btn-primary">+ New Page</a>
        <?php else: ?>
        <a href="/admin/pages.php" class="btn btn-secondary">← Back to all pages</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($editing || $creating): ?>
<div class="card">
    <h2><?php echo $editing ? '✏️ Edit Page: ' . escape($editing['title']) : '➕ Create New Page'; ?></h2>
    
    <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
        <?php if ($editing): ?>
        <input type="hidden" name="id" value="<?php echo (int)$editing['id']; ?>">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>Page Title *</label>
                <input type="text" name="title" required value="<?php echo escape($editing['title'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>URL Slug *</label>
                <input type="text" name="slug" required value="<?php echo escape($editing['slug'] ?? ''); ?>" placeholder="e.g. privacy" pattern="[a-z0-9-]+">
                <small>Lowercase letters, numbers, and dashes only. URL: <code>/page/{slug}</code></small>
            </div>
        </div>
        
        <div class="form-group">
            <label>Page Content * (HTML allowed)</label>
            <textarea name="content" rows="22" required style="font-family:'SF Mono',Consolas,monospace;font-size:13px;"><?php echo escape($editing['content'] ?? ''); ?></textarea>
            <small>You can use HTML tags: &lt;h2&gt;, &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;ol&gt;, &lt;li&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;a href=""&gt;, &lt;blockquote&gt;, &lt;table&gt;, &lt;br&gt;</small>
        </div>
        
        <div class="form-group">
            <label>Meta Description (SEO)</label>
            <textarea name="meta_description" rows="2" maxlength="300"><?php echo escape($editing['meta_description'] ?? ''); ?></textarea>
            <small>Shown in search results. Keep under 160 characters.</small>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Sort Order</label>
                <input type="number" name="sort_order" min="0" value="<?php echo (int)($editing['sort_order'] ?? 0); ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="published" <?php echo ($editing['status'] ?? 'published') === 'published' ? 'selected' : ''; ?>>Published</option>
                    <option value="draft" <?php echo ($editing['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="show_in_footer" value="1" <?php echo !empty($editing['show_in_footer']) ? 'checked' : ''; ?>>
                Show in footer "Legal" links
            </label>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Save Page</button>
            <?php if ($editing): ?>
            <a href="/page/<?php echo escape($editing['slug']); ?>" target="_blank" class="btn btn-secondary">👁 View Live ↗</a>
            <?php endif; ?>
            <a href="/admin/pages.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php else: ?>

<div class="card">
    <h2>📋 All Pages (<?php echo count($pages); ?>)</h2>
    
    <?php if (empty($pages)): ?>
        <p class="muted text-center" style="padding:30px;">No pages yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Slug / URL</th>
                        <th>Status</th>
                        <th>Footer</th>
                        <th>Sort</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                    <tr>
                        <td><strong><?php echo escape($page['title']); ?></strong></td>
                        <td>
                            <code>/page/<?php echo escape($page['slug']); ?></code>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $page['status'] === 'published' ? 'success' : 'warning'; ?>">
                                <?php echo escape($page['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $page['show_in_footer'] ? '✓' : '—'; ?></td>
                        <td><?php echo (int)$page['sort_order']; ?></td>
                        <td class="actions">
                            <a href="/admin/pages.php?edit=<?php echo (int)$page['id']; ?>" class="btn btn-sm btn-secondary">✏️ Edit</a>
                            <a href="/page/<?php echo escape($page['slug']); ?>" target="_blank" class="btn btn-sm btn-secondary">👁</a>
                            <?php if (!in_array($page['slug'], ['privacy','terms','refund','full-description'])): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Delete this page?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int)$page['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
