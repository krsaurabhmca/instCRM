<?php // includes/footer.php ?>
        </main><!-- /.content -->
    </div><!-- /.main-wrapper -->

</div><!-- /.app-container -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script>
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
