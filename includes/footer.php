<?php // includes/footer.php ?>
        </main><!-- /.content -->
    </div><!-- /.main-wrapper -->

</div><!-- /.app-container -->

<!-- Global QR Download Modal -->
<div id="qrDownloadModal" class="modal-backdrop">
    <div class="modal" style="max-width:400px; text-align:center;">
        <div class="modal-header">
            <h3>Download QR Code</h3>
            <button type="button" class="modal-close" onclick="closeModal('qrDownloadModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <div id="qrPrintArea" style="padding: 24px; background: #fff; border: 2px solid var(--primary); border-radius: 12px; display: inline-block;">
                <?php if (!empty($tenant_logo) && file_exists(dirname(__DIR__) . '/' . $tenant_logo)): ?>
                    <img src="<?= BASE_URL ?>/<?= $tenant_logo ?>" alt="Logo" style="max-height: 50px; margin-bottom: 12px;">
                <?php else: ?>
                    <h2 style="color: var(--primary); font-family: var(--font-display); margin: 0 0 12px; font-size: 1.5rem;"><?= htmlspecialchars($_SESSION['tenant_name'] ?? '') ?></h2>
                <?php endif; ?>
                <div style="font-size: 1.1rem; font-weight: 600; color: var(--ink-800); margin-bottom: 16px;">Scan to Register</div>
                <img id="qrCodeImage" src="" alt="QR" crossorigin="anonymous" style="width: 200px; height: 200px; margin: 0 auto; display: block;">
                <div id="qrSourceName" style="margin-top: 16px; font-size: 0.85rem; color: var(--ink-500); text-transform: uppercase; letter-spacing: 1px;"></div>
            </div>
            <p style="margin-top: 16px; font-size: 0.85rem; color: var(--ink-500);">Place this QR code at your desk or share it online to automatically capture leads.</p>
        </div>
        <div class="modal-footer" style="justify-content: center;">
            <button type="button" class="btn btn-primary" onclick="downloadQR()"><i class="bi bi-download"></i> Download PNG</button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function openQrModal(sourceName, tenantId) {
    const publicUrl = window.location.origin + '<?= BASE_URL ?>/public_enquiry.php?tenant_id=' + tenantId + '&source=' + encodeURIComponent(sourceName);
    document.getElementById('qrCodeImage').src = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(publicUrl);
    document.getElementById('qrSourceName').textContent = 'Source: ' + sourceName;
    openModal('qrDownloadModal');
}

function downloadQR() {
    html2canvas(document.getElementById('qrPrintArea'), {scale: 2, useCORS: true, allowTaint: false}).then(canvas => {
        let link = document.createElement('a');
        link.download = 'QR_Code_' + document.getElementById('qrSourceName').textContent.replace('Source: ', '') + '.png';
        link.href = canvas.toDataURL("image/png");
        link.click();
    });
}

// PDF Download Helper
function downloadPDF(elementId, filename) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    // Temporarily adjust scale if needed for better resolution
    const opt = {
        margin:       [0.5, 0.5],
        filename:     filename + '.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
}

// Live clock in topbar
(function () {
    var el = document.getElementById('topbarClock');
    if (!el) return;
    function tick() {
        var now = new Date();
        var h = now.getHours().toString().padStart(2,'0');
        var m = now.getMinutes().toString().padStart(2,'0');
        var days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        el.textContent = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate() + ' · ' + h + ':' + m;
    }
    tick();
    setInterval(tick, 30000);
})();

// Close dropdown when clicking outside
window.onclick = function(event) {
    if (!event.target.closest('.profile-menu')) {
        var dropdowns = document.getElementsByClassName("dropdown-menu");
        for (var i = 0; i < dropdowns.length; i++) {
            var openDropdown = dropdowns[i];
            if (openDropdown.classList.contains('show')) {
                openDropdown.classList.remove('show');
            }
        }
    }
}
</script>
</body>
</html>
