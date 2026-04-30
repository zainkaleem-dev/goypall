/* Admin JavaScript */

// Tabs
function showTab(tabId, btn) {
    var tabs = btn.parentElement.querySelectorAll('.tab-btn');
    tabs.forEach(function(t){ t.classList.remove('active'); });
    btn.classList.add('active');
    
    var panes = document.querySelectorAll('.tab-pane');
    panes.forEach(function(p){ p.classList.remove('active'); });
    var pane = document.getElementById(tabId);
    if (pane) pane.classList.add('active');
}

// Confirm delete
function confirmDelete(msg) {
    return confirm(msg || 'Are you sure you want to delete this? This cannot be undone.');
}

// Modal
function openModal(id) {
    var m = document.getElementById(id);
    if (m) m.classList.add('open');
}
function closeModal(id) {
    var m = document.getElementById(id);
    if (m) m.classList.remove('open');
}

// Close modal on backdrop click
document.addEventListener('click', function(e){
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});

// ESC closes modals
document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(function(m){
            m.classList.remove('open');
        });
    }
});

// Image preview helper for file inputs
function previewImage(input, previewId) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    var preview = document.getElementById(previewId);
    if (!preview) return;
    reader.onload = function(e){ preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
}
