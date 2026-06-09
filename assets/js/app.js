/* assets/js/app.js — InstCRM Core Interactions */

// ── Alert auto-dismiss ─────────────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity .5s ease, transform .4s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-6px)';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Smooth sidebar active indicator
    const activeLink = document.querySelector('.sidebar-menu li.active a');
    if (activeLink) {
        activeLink.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
});

// ── Modal helpers ──────────────────────────────────────────────────────────
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // Re-trigger animation
    const inner = modal.querySelector('.modal');
    if (inner) {
        inner.style.animation = 'none';
        inner.offsetHeight; // reflow
        inner.style.animation = '';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.style.display = 'none';
    document.body.style.overflow = '';
}

// Close modal on backdrop click
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-backdrop')) {
        e.target.style.display = 'none';
        document.body.style.overflow = '';
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop[style*="flex"]').forEach(el => {
            el.style.display = 'none';
            document.body.style.overflow = '';
        });
    }
});

// ── Print receipt helper ───────────────────────────────────────────────────
function printReceipt(divId) {
    const el = document.getElementById(divId);
    if (!el) return;
    const printContents = el.innerHTML;
    const w = window.open('', '_blank', 'width=600,height=700');
    w.document.write(`
        <!DOCTYPE html><html>
        <head>
            <title>Receipt – InstCRM</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
            <style>
                body { font-family: 'Inter', sans-serif; padding: 32px; font-size: 13px; color: #111; }
                table { width: 100%; border-collapse: collapse; }
                td { padding: 8px 0; }
                @page { margin: 16mm; }
            </style>
        </head>
        <body>${printContents}</body>
        </html>`);
    w.document.close();
    w.focus();
    setTimeout(() => { w.print(); w.close(); }, 300);
}
