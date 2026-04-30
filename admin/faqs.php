<?php
require_once dirname(__DIR__) . '/init.php';
require_admin_login();

global $db;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $category = trim($_POST['category'] ?? 'General') ?: 'General';
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = ($_POST['status'] ?? 'active') === 'hidden' ? 'hidden' : 'active';
        
        if (!$question || !$answer) {
            flash('error', 'Question and answer are required.');
        } else {
            if ($action === 'create') {
                $db->insert('faqs', [
                    'question' => $question,
                    'answer' => $answer,
                    'category' => $category,
                    'sort_order' => $sortOrder,
                    'status' => $status,
                ]);
                admin_log('faq_create', $question);
                flash('success', 'FAQ added.');
            } else {
                $db->query("UPDATE " . DB_PREFIX . "faqs SET question = ?, answer = ?, category = ?, sort_order = ?, status = ? WHERE id = ?",
                    [$question, $answer, $category, $sortOrder, $status, $id]);
                admin_log('faq_update', "ID $id");
                flash('success', 'FAQ updated.');
            }
        }
        redirect('/admin/faqs.php');
    }
    
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->query("DELETE FROM " . DB_PREFIX . "faqs WHERE id = ?", [$id]);
            admin_log('faq_delete', "ID $id");
            flash('success', 'FAQ deleted.');
        }
        redirect('/admin/faqs.php');
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    $editing = $db->fetch("SELECT * FROM " . DB_PREFIX . "faqs WHERE id = ?", [$editId]);
}

$faqs = $db->fetchAll("SELECT * FROM " . DB_PREFIX . "faqs ORDER BY category ASC, sort_order ASC, id ASC");
$categories = $db->fetchAll("SELECT DISTINCT category FROM " . DB_PREFIX . "faqs ORDER BY category");

include __DIR__ . '/_header.php';
?>

<div class="page-head">
    <div>
        <h1>❓ FAQ Manager</h1>
        <div class="subtitle">Add and manage frequently asked questions (<?php echo count($faqs); ?> total)</div>
    </div>
    <?php if ($editing): ?>
    <a href="/admin/faqs.php" class="btn btn-secondary">← Cancel Edit</a>
    <?php endif; ?>
</div>

<div class="card">
    <h2><?php echo $editing ? '✏️ Edit FAQ #' . $editing['id'] : '➕ Add New FAQ'; ?></h2>
    
    <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
        <?php if ($editing): ?>
        <input type="hidden" name="id" value="<?php echo (int)$editing['id']; ?>">
        <?php endif; ?>
        
        <div class="form-group">
            <label>Question *</label>
            <input type="text" name="question" required value="<?php echo escape($editing['question'] ?? ''); ?>" 
                   placeholder="e.g. What's included with the script?">
        </div>
        
        <div class="form-group">
            <label>Answer * (HTML allowed: &lt;p&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;strong&gt;, &lt;a&gt;)</label>
            <textarea name="answer" rows="6" required placeholder="Detailed answer..."><?php echo escape($editing['answer'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Category</label>
                <input type="text" name="category" list="categories" value="<?php echo escape($editing['category'] ?? 'General'); ?>" placeholder="General">
                <datalist id="categories">
                    <option value="General">
                    <option value="Installation">
                    <option value="Pricing &amp; Refunds">
                    <option value="Features">
                    <option value="Support">
                    <option value="Customization">
                    <option value="Hosting &amp; Requirements">
                </datalist>
                <small>Used to group FAQs on the public page.</small>
            </div>
            <div class="form-group">
                <label>Sort Order</label>
                <input type="number" name="sort_order" min="0" value="<?php echo (int)($editing['sort_order'] ?? 0); ?>">
                <small>Lower numbers appear first within category.</small>
            </div>
        </div>
        
        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="active" <?php echo ($editing['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active (shown on site)</option>
                <option value="hidden" <?php echo ($editing['status'] ?? '') === 'hidden' ? 'selected' : ''; ?>>Hidden</option>
            </select>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?php echo $editing ? '💾 Save Changes' : '➕ Add FAQ'; ?>
            </button>
            <?php if ($editing): ?>
            <a href="/admin/faqs.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <h2>📋 All FAQs (<?php echo count($faqs); ?>)</h2>
    
    <?php if (empty($faqs)): ?>
        <p class="muted text-center" style="padding:30px;">No FAQs yet. Add some using the form above.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Category</th>
                        <th>Question</th>
                        <th>Sort</th>
                        <th>Status</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($faqs as $faq): ?>
                    <tr>
                        <td><?php echo (int)$faq['id']; ?></td>
                        <td><span class="badge badge-info"><?php echo escape($faq['category']); ?></span></td>
                        <td style="max-width:400px;">
                            <strong><?php echo escape($faq['question']); ?></strong>
                            <div style="color:var(--c-text-muted);font-size:12px;margin-top:4px;">
                                <?php echo escape(substr(strip_tags($faq['answer']), 0, 100)) . (strlen(strip_tags($faq['answer'])) > 100 ? '…' : ''); ?>
                            </div>
                        </td>
                        <td><?php echo (int)$faq['sort_order']; ?></td>
                        <td><span class="badge badge-<?php echo $faq['status'] === 'active' ? 'success' : 'neutral'; ?>"><?php echo $faq['status']; ?></span></td>
                        <td class="actions">
                            <a href="/admin/faqs.php?edit=<?php echo (int)$faq['id']; ?>" class="btn btn-sm btn-secondary">✏️ Edit</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Delete this FAQ?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int)$faq['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
