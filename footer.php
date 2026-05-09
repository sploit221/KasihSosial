<!-- footer.php -->
<footer class="text-center py-4 mt-5" style="font-size:.8rem;color:#9ca3af;">
  &copy; 2026 KasihSosial &mdash; Saling Berbagi, Saling Peduli
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // ── 1. Jam Real-time Global ────────────────────────────────
  const days   = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
  const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
  
    if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.register('/sw.js').then(function(reg) {
      console.log('ServiceWorker registered');
    });
  });
}

  (function tick() {
    const n = new Date();
    const clockEl = document.getElementById('live-clock');
    const dateEl  = document.getElementById('live-date');
    
    if (clockEl) {
      clockEl.textContent = [n.getHours(), n.getMinutes(), n.getSeconds()]
        .map(v => String(v).padStart(2, '0')).join(':');
    }
    if (dateEl) {
      dateEl.textContent = days[n.getDay()] + ', ' + n.getDate() + ' ' + months[n.getMonth()] + ' ' + n.getFullYear();
    }
    setTimeout(tick, 1000);
  })();

  // ── 2. Auto-dismiss Flash Alerts ───────────────────────────
  document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => {
        if (typeof bootstrap !== 'undefined') {
            const alert = bootstrap.Alert.getOrCreateInstance(el);
            alert.close();
        } else {
            el.remove();
        }
    }, 4000);
  });
</script>