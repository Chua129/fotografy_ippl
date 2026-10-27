/* responsive.js — Creative Pixel
   Bikin tombol hamburger otomatis di header (tanpa edit HTML tiap halaman).
   Menu tampil/sembunyi lewat class "nav-open" di .site-header. */
(function () {
  function init() {
    var header = document.querySelector('.site-header');
    if (!header) return;
    var inner = header.querySelector('.header-inner');
    var nav = header.querySelector('.main-nav');
    if (!inner || !nav || inner.querySelector('.nav-toggle')) return;

    if (!nav.id) nav.id = 'main-nav';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'nav-toggle';
    btn.setAttribute('aria-label', 'Buka menu');
    btn.setAttribute('aria-expanded', 'false');
    btn.setAttribute('aria-controls', nav.id);
    btn.innerHTML = '<span></span><span></span><span></span>';
    inner.appendChild(btn);

    function setOpen(open) {
      header.classList.toggle('nav-open', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
    }

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      setOpen(!header.classList.contains('nav-open'));
    });

    // Klik di luar header = tutup menu
    document.addEventListener('click', function (e) {
      if (!header.contains(e.target)) setOpen(false);
    });

    // Tekan Esc = tutup menu
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') setOpen(false);
    });

    // Layar dilebarkan lagi (rotasi HP / resize) = reset
    window.addEventListener('resize', function () {
      if (window.innerWidth > 900) setOpen(false);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
