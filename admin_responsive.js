/* admin_responsive.js — Creative Pixel (Panel Admin)
   1. Bikin bar atas + tombol hamburger + overlay otomatis (tanpa edit HTML).
   2. Sidebar jadi drawer di layar <= 1024px (class "admin-nav-open" di <body>).
   3. Bungkus semua <table> di .admin-main supaya bisa digeser ke samping di HP. */
(function () {
  function init() {
    var sidebar = document.querySelector('.admin-sidebar');
    var main = document.querySelector('.admin-main');
    if (!sidebar || !main) return;

    /* ---- 1. Bar atas + overlay ---- */
    if (!document.querySelector('.admin-mobilebar')) {
      if (!sidebar.id) sidebar.id = 'admin-sidebar';

      var bar = document.createElement('div');
      bar.className = 'admin-mobilebar';

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'admin-menu-btn';
      btn.setAttribute('aria-label', 'Buka menu');
      btn.setAttribute('aria-expanded', 'false');
      btn.setAttribute('aria-controls', sidebar.id);
      btn.innerHTML = '<span></span><span></span><span></span>';

      var brand = document.createElement('div');
      brand.className = 'admin-mobilebar-brand';
      brand.innerHTML = 'Creative <span>Pixel</span>';

      // Nama halaman aktif (diambil dari menu sidebar yang "active")
      var page = document.createElement('div');
      page.className = 'admin-mobilebar-page';
      var act = sidebar.querySelector('.sidebar-menu a.active');
      page.textContent = act ? act.textContent.trim() : '';

      bar.appendChild(btn);
      bar.appendChild(brand);
      bar.appendChild(page);

      var overlay = document.createElement('div');
      overlay.className = 'admin-overlay';

      document.body.insertBefore(overlay, document.body.firstChild);
      document.body.insertBefore(bar, document.body.firstChild);

      var setOpen = function (open) {
        document.body.classList.toggle('admin-nav-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
      };

      btn.addEventListener('click', function () {
        setOpen(!document.body.classList.contains('admin-nav-open'));
      });
      overlay.addEventListener('click', function () { setOpen(false); });
      sidebar.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('a')) setOpen(false);
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
      });
      window.addEventListener('resize', function () {
        if (window.innerWidth > 1024) setOpen(false);
      });
    }

    /* ---- 2. Tabel bisa digeser ---- */
    var tables = main.querySelectorAll('table');
    for (var i = 0; i < tables.length; i++) {
      var t = tables[i];
      var p = t.parentElement;
      if (!p) continue;
      if (p.classList.contains('table-scroll') || p.classList.contains('table-wrap')) continue;
      var wrap = document.createElement('div');
      wrap.className = 'table-scroll';
      p.insertBefore(wrap, t);
      wrap.appendChild(t);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
