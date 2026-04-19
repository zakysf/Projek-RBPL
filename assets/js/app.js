// ============================================================
// Tea Spa — Global JavaScript
// ============================================================

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('open');
}

// Auto-dismiss flash messages after 4 seconds
document.addEventListener('DOMContentLoaded', function () {
    const flash = document.getElementById('flashMsg');
    if (flash) {
        setTimeout(() => flash.remove(), 4000);
    }

    // Confirm delete buttons
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm || 'Yakin?')) {
                e.preventDefault();
            }
        });
    });
});

// Calculate reservation end time
function calcEndTime(startTimeId, durationId, endTimeId) {
    const start    = document.getElementById(startTimeId)?.value;
    const duration = parseInt(document.getElementById(durationId)?.value || 0);
    const endEl    = document.getElementById(endTimeId);

    if (!start || !duration || !endEl) return;

    const [h, m] = start.split(':').map(Number);
    const endMin = h * 60 + m + duration;
    const eh = String(Math.floor(endMin / 60) % 24).padStart(2, '0');
    const em = String(endMin % 60).padStart(2, '0');
    endEl.value = eh + ':' + em;
}
