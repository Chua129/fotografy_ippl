<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$nama_user = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Klien';

// Query semua paket aktif dari database untuk etalase
$res_paket = $conn->query("SELECT * FROM paket WHERE status = 'aktif' ORDER BY id ASC");
$pakets_by_cat = [
    'wisuda'     => [],
    'portrait'   => [],
    'prewedding' => [],
    'family'     => []
];
$total_aktif = 0;

if ($res_paket) {
    while ($row = $res_paket->fetch_assoc()) {
        $cat = $row['kategori'];
        if (!isset($pakets_by_cat[$cat])) {
            $pakets_by_cat[$cat] = [];
        }
        $pakets_by_cat[$cat][] = $row;
        $total_aktif++;
    }
}

// Helper formatting durasi ringkas
function getCompactDuration($dur) {
    if (stripos($dur, '45 Menit') !== false) return '45 Menit';
    if (stripos($dur, '1.5 Jam') !== false || stripos($dur, '90 Menit') !== false) return '90 Menit';
    if (stripos($dur, '1 Jam') !== false || stripos($dur, '60 Menit') !== false) return '60 Menit';
    if (stripos($dur, '2.5 Jam') !== false || stripos($dur, '150 Menit') !== false) return '150 Menit';
    if (stripos($dur, '2 Jam') !== false || stripos($dur, '120 Menit') !== false) return '120 Menit';
    if (stripos($dur, '3 Jam') !== false || stripos($dur, '180 Menit') !== false) return '180 Menit';
    return htmlspecialchars($dur);
}

// Helper formatting lokasi studio ringkas
function getCompactStudio($lok) {
    if (stripos($lok, 'Glasshouse') !== false) return 'Indoor + Glasshouse';
    if (stripos($lok, 'Garden') !== false && stripos($lok, 'Studio A') !== false) return 'Studio A & Garden';
    if (stripos($lok, 'Cyclorama') !== false) return 'Studio B Cyclorama';
    if (stripos($lok, 'Studio A') !== false) return 'Studio A';
    if (stripos($lok, 'Studio B') !== false) return 'Studio B';
    if (strlen($lok) > 22) return substr($lok, 0, 20).'…';
    return htmlspecialchars($lok);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Etalase Paket - Creative Pixel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<style>
/* ── Reset & Utility ── */
.profile-trigger:hover, .logout-link:hover { opacity: 0.8; text-decoration: none; }
.hero { padding-top: 56px; }
.hero-grid { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 64px; align-items: center; margin-bottom: 56px; }
.hero-copy h1 { font-family: var(--font-serif); font-weight: 500; font-size: 2.9rem; line-height: 1.15; margin: 0 0 22px; }
.hero-desc { color: var(--ink-soft); font-size: 1rem; max-width: 460px; }
.hero-media { position: relative; }
.hero-media img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: var(--radius-lg);  }
.hero-caption { position: absolute; left: 20px; right: 20px; bottom: 20px; background: rgba(255, 255, 255, 0.92); border-radius: var(--radius-md); padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; font-weight: 600; backdrop-filter: blur(6px); }

.stats-bar { display: grid; grid-template-columns: repeat(4, 1fr); border: 1px solid var(--line); border-radius: var(--radius-lg); background: #fff; }
.stat { padding: 26px 28px; border-right: 1px solid var(--line); }
.stat:last-child { border-right: none; }
.stat-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ink-faint); margin: 0 0 10px; }
.stat-value { font-family: var(--font-serif); font-size: 1.1rem; margin: 0 0 8px; }
.stat-note { font-size: 0.78rem; color: var(--ink-soft); margin: 0; }
.stat-occupancy-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 10px; }
.progress-track { height: 6px; border-radius: 4px; background: var(--bg-alt); overflow: hidden; margin-bottom: 10px; }
.progress-fill { height: 100%; background: var(--accent); border-radius: 4px; }

/* ── Filter ── */
.filter-row { display: flex; align-items: flex-start; flex-direction: column; gap: 20px; }
.filter-pills { display: flex; gap: 10px; flex-wrap: wrap; }
.pill { padding: 9px 16px; border-radius: 999px; border: 1px solid var(--line); background: #F3F1EC; color: var(--ink); font-size: 0.85rem; cursor: pointer; transition: 0.2s; }
.pill-active { background: var(--dark) !important; border-color: var(--dark); color: #fff !important; }
.quota-filter { display: flex; align-items: center; gap: 16px; font-size: 0.85rem; color: var(--ink-soft); }

/* ================================================================
   KATALOG SECTION & CARD STYLING
================================================================ */
.catalog { padding: 40px 0 80px; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.category-wrapper { animation: fadeIn 0.4s; }

/* ── Category Section Header ── */
.cat-section-head {
    display: flex; justify-content: space-between; align-items: flex-end;
    margin: 56px 0 24px; padding: 20px 0 16px;
    border-top: 2px solid var(--line);
}
.cat-head-left { border-left: 3px solid var(--ink); padding-left: 14px; }
.cat-kicker { font-size: 0.72rem; color: var(--ink-faint); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 5px; }
.cat-title { font-family: var(--font-serif); font-size: 1.8rem; font-weight: 500; margin: 0; color: var(--ink); line-height: 1.2; }
.cat-loc-tag { font-size: 0.78rem; color: var(--ink-soft); border: 1px solid var(--line); border-radius: 6px; padding: 7px 16px; background: #fff; white-space: nowrap; }

/* ── Grids ── */
.pkg-grid-standard { display: grid; gap: 24px; }
.pkg-grid-3 { grid-template-columns: repeat(3, 1fr); }
.pkg-grid-4 { grid-template-columns: repeat(4, 1fr); }

/* Prewedding 2-column Grid */
.pkg-grid-prewedding {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 28px;
}

/* ── Base Card ── */
.pkg-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    transition: box-shadow 0.2s, transform 0.2s;
}
.pkg-card:hover {
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    transform: translateY(-3px);
}

/* ── Card Image Container ── */
.pkg-img-wrap {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 10;
    overflow: hidden;
    background: #1B1A17;
    flex-shrink: 0;
}
.pkg-img-wrap img {
    width: 100%; height: 100%;
    object-fit: cover; display: block;
    transition: transform 0.4s ease;
}
.pkg-card:hover .pkg-img-wrap img { transform: scale(1.04); }

/* Gradient bottom overlay */
.pkg-img-wrap::after {
    content: '';
    position: absolute; inset: 0; pointer-events: none;
    background: linear-gradient(to bottom, rgba(0,0,0,0) 35%, rgba(0,0,0,0.75) 100%);
}

/* Top-Left Badge */
.img-badge-tl {
    position: absolute; top: 12px; left: 12px; z-index: 2;
    background: rgba(255,255,255,0.95); backdrop-filter: blur(4px);
    color: var(--ink); font-size: 0.68rem; font-weight: 700;
    padding: 5px 12px; border-radius: 999px;
    display: inline-flex; align-items: center; gap: 5px;
    white-space: nowrap; box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}

/* Top-Right Quota Badge */
.img-badge-tr {
    position: absolute; top: 12px; right: 12px; z-index: 2;
    font-size: 0.68rem; font-weight: 700;
    padding: 5px 12px; border-radius: 999px;
    display: inline-flex; align-items: center; gap: 6px;
    white-space: nowrap;
}
.quota-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
.badge-avail { background: #E8F5E9; color: #1B5E20; border: 1px solid rgba(27,94,32,0.25); }
.badge-avail .quota-dot { background: #2E7D32; }
.badge-limit { background: #FFF8E1; color: #B78103; border: 1px solid rgba(183,129,3,0.3); }
.badge-limit .quota-dot { background: #F57F17; }
.badge-sold  { background: #FFEBEE; color: #C62828; border: 1px solid rgba(198,40,40,0.25); }
.badge-sold .quota-dot  { background: #D32F2F; }

/* Bottom Strip on Image */
.img-bottom-bar {
    position: absolute; bottom: 0; left: 0; right: 0; z-index: 2;
    padding: 10px 14px;
    display: flex; justify-content: space-between; align-items: center; gap: 8px;
}
.img-bottom-left {
    display: flex; align-items: center; gap: 6px;
    font-size: 0.7rem; font-weight: 600; color: rgba(255,255,255,0.95);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.img-bottom-sep { opacity: 0.45; }
.img-bottom-right {
    font-size: 0.67rem; font-weight: 700; color: rgba(255,255,255,0.95);
    background: rgba(0,0,0,0.45); backdrop-filter: blur(4px);
    padding: 3px 9px; border-radius: 4px; white-space: nowrap; flex-shrink: 0;
}

/* ── Standard Card Body (Wisuda, Portrait, Family) ── */
.pkg-body-standard {
    padding: 20px 20px 16px 20px;
    display: flex; flex-direction: column; flex: 1;
}
.pkg-cat-label {
    font-size: 0.65rem; font-weight: 800; color: var(--accent);
    text-transform: uppercase; letter-spacing: 0.06em;
    margin: 0 0 8px 0;
}
.pkg-title-standard {
    font-family: var(--font-serif); font-size: 1.25rem; font-weight: 500;
    color: var(--ink); margin: 0 0 9px 0; line-height: 1.3;
}
.pkg-desc-standard {
    font-size: 0.81rem; color: var(--ink-soft); margin: 0 0 16px 0;
    line-height: 1.55;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}

/* Features List 1-Column */
.pkg-features-standard {
    list-style: none; padding: 0; margin: 0;
    display: flex; flex-direction: column; gap: 7px;
}
.pkg-features-standard li {
    font-size: 0.8rem; color: var(--ink);
    display: flex; align-items: center; gap: 8px; line-height: 1.4;
}
.pkg-features-standard li::before {
    content: ''; display: inline-block; flex-shrink: 0;
    width: 16px; height: 16px;
    background: var(--green-bg) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%232E7D4F' stroke-width='3.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'%3E%3C/polyline%3E%3C/svg%3E") no-repeat center;
    border-radius: 50%;
}

/* Standard Footer Box */
.pkg-footer-standard {
    background: #F7F6F3;
    padding: 18px 20px 20px 20px;
    margin-top: auto;
    border-top: 1px solid var(--line);
    display: flex; flex-direction: column;
}
.pkg-footer-standard-top {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 4px;
}
.pkg-invest-label {
    font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; color: var(--ink-faint); margin: 0;
}
.pkg-dp-badge {
    background: #F4EAE0; color: #8C532B;
    font-size: 0.72rem; font-weight: 700;
    padding: 3px 8px; border-radius: 4px;
}
.pkg-price-standard {
    font-family: var(--font-serif); font-size: 1.4rem; font-weight: 500;
    color: var(--ink); margin: 2px 0 14px 0; line-height: 1.2;
}
.btn-booking-standard {
    display: block; width: 100%; padding: 12px;
    background: #111; color: #fff; text-align: center;
    border-radius: 6px; font-weight: 700; font-size: 0.88rem;
    text-decoration: none; border: none; cursor: pointer;
    transition: background 0.15s;
}
.btn-booking-standard:hover { background: #333; color: #fff; }
.btn-booking-standard.disabled {
    background: #D5D3CE; color: #999; cursor: not-allowed; pointer-events: none;
}

/* ── Portrait Card Specific Styles ── */
.pkg-footer-portrait {
    background: #F7F6F3;
    padding: 16px 18px 18px 18px;
    margin-top: auto;
    border-top: 1px solid var(--line);
    display: flex;
    flex-direction: column;
}
.pkg-price-portrait-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 12px;
}
.pkg-price-amount-portrait {
    font-family: var(--font-serif);
    font-size: 1.35rem;
    font-weight: 500;
    color: var(--ink);
    line-height: 1.2;
}
.pkg-price-dp-portrait {
    font-size: 0.76rem;
    font-weight: 700;
    color: #8C532B;
}
.img-badge-tl.badge-dark {
    background: #000;
    color: #fff;
}
.img-badge-tl.badge-amber {
    background: #78350F;
    color: #fff;
}
.img-badge-tl.badge-green { background: #166534; color: #fff; }
.img-badge-tl.badge-blue  { background: #1D4ED8; color: #fff; }
.img-badge-tl.badge-red   { background: #B91C1C; color: #fff; }

/* ================================================================
   PREWEDDING & PASANGAN — KHUSUS LAYOUT 2-KOLOM LAPANG & RAPI
================================================================ */
.pkg-body-prewedding {
    padding: 24px 24px 20px 24px;
    display: flex; flex-direction: column; flex: 1;
}
.pkg-title-prewedding {
    font-family: var(--font-serif); font-size: 1.4rem; font-weight: 500;
    color: var(--ink); margin: 0 0 10px 0; line-height: 1.3;
}
.pkg-desc-prewedding {
    font-size: 0.84rem; color: var(--ink-soft); line-height: 1.6;
    margin: 0 0 18px 0;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}

/* Features List 2-Column */
.pkg-features-2col {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 10px 20px; list-style: none; padding: 0; margin: 0;
}
.pkg-features-2col li {
    font-size: 0.8rem; color: var(--ink);
    display: flex; align-items: center; gap: 8px; line-height: 1.4;
}
.pkg-features-2col li::before {
    content: ''; display: inline-block; flex-shrink: 0;
    width: 17px; height: 17px;
    background: var(--green-bg) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%232E7D4F' stroke-width='3.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'%3E%3C/polyline%3E%3C/svg%3E") no-repeat center;
    border-radius: 50%;
}

/* Prewedding Footer Box */
.pkg-footer-prewedding {
    background: #F7F6F3;
    padding: 20px 24px;
    margin-top: auto;
    border-top: 1px solid var(--line);
    display: flex; justify-content: space-between; align-items: center;
    gap: 16px;
}
.pkg-footer-prewedding-left {
    display: flex; flex-direction: column; gap: 3px;
}
.pkg-price-prewedding-row {
    display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap;
}
.pkg-price-amount {
    font-family: var(--font-serif); font-size: 1.45rem; font-weight: 500;
    color: var(--ink); line-height: 1.2;
}
.pkg-price-dp {
    font-size: 0.82rem; font-weight: 700; color: var(--accent);
}
.btn-booking-prewedding {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 12px 28px; background: #111; color: #fff;
    border-radius: 8px; font-weight: 700; font-size: 0.88rem;
    text-decoration: none; white-space: nowrap; border: none; cursor: pointer;
    transition: background 0.15s;
}
.btn-booking-prewedding:hover { background: #333; color: #fff; }
.btn-booking-prewedding.disabled {
    background: #D5D3CE; color: #999; cursor: not-allowed; pointer-events: none;
}

/* Soldout Dim Overlay */
.pkg-card.soldout .pkg-img-wrap::before {
    content: ''; position: absolute; inset: 0; z-index: 1;
    background: rgba(0,0,0,0.25);
}

/* ── Steps Section ── */
.steps-section { background: var(--bg-alt); padding: 80px 0; }
.steps-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 36px; margin-top: 48px; }
.step { text-align: center; padding: 0 16px; }
.step-number { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: var(--dark); color: #fff; font-family: var(--font-serif); font-size: 1.05rem; margin-bottom: 18px; }

/* ── Responsive ── */
@media (max-width: 1200px) {
    .pkg-grid-4 { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 900px) {
    .pkg-grid-3, .pkg-grid-4, .pkg-grid-prewedding { grid-template-columns: 1fr; }
    .pkg-features-2col { grid-template-columns: 1fr; }
    .pkg-footer-prewedding { flex-direction: column; align-items: stretch; gap: 14px; }
    .btn-booking-prewedding { width: 100%; }
    .hero-grid { grid-template-columns: 1fr; }
    .hero-media { order: -1; }
    .steps-grid { grid-template-columns: 1fr; }
    .stats-bar { grid-template-columns: repeat(2, 1fr); }
    .stat:nth-child(2) { border-right: none; }
    .stat { border-bottom: 1px solid var(--line); }
    .header-inner { flex-wrap: wrap; }
}
</style>
<!-- Responsive layout (harus paling akhir supaya menimpa CSS lama) -->
<link rel="stylesheet" href="responsive.css?v=<?= time(); ?>">
<script src="responsive.js?v=<?= time(); ?>" defer></script>
</head>
<body>

<!-- ===== HEADER ===== -->
<header class="site-header">
  <div class="container header-inner">
    <a href="index.php" class="logo">CREATIVE<span>PIXEL</span></a>
    <nav class="main-nav">
      <a href="index.php" class="nav-pill-active">Etalase Paket</a>
      <a href="pemesanan.php">Cek Status Reservasi</a>
      <a href="tiket.php">Lihat Tiket Reservasi</a>
    </nav>
    <div class="header-actions">
      <div class="user-chip">
        <a href="profil.php" class="user-chip-link">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user); ?>&background=F3F1EC&color=1B1A17" alt="Avatar">
            <div class="user-chip-text">
              <strong><?= htmlspecialchars($nama_user); ?></strong>
              <span>Studio Account</span>
            </div>
        </a>
        <a href="logout.php" class="logout-link">Keluar</a>
      </div>
    </div>
  </div>
</header>

<!-- ===== HERO ===== -->
<section class="hero container">
  <div class="hero-grid">
    <div class="hero-copy">
      <p class="eyebrow eyebrow-dot">CONTEMPORARY FINE ART &amp; COMMERCIAL PHOTOGRAPHY</p>
      <h1>Abadikan Momen Berharga dengan Sentuhan Editorial Eksklusif.</h1>
      <p class="hero-desc">Pilih paket fotografi studio &amp; outdoor terbaik. Reservasi langsung dengan pemantauan kuota slot real-time per sesi agar kualitas pengalaman tetap intim, personal, dan bebas antrean.</p>
    </div>
    <div class="hero-media">
      <img src="jpg/CP 2.jpg" alt="Editorial Shoot">
      <div class="hero-caption">
        <span>Studio A • Editorial Daylight</span>
        <span style="background: rgba(0,0,0,0.4); color: #fff; padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 500;">ISO 100 • 50mm</span>
      </div>
    </div>
  </div>
</section>

<!-- ===== FILTER ===== -->
<section class="filter-section container">
  <div class="filter-row" style="margin-top: 24px;">
    <div class="filter-pills">
      <button class="pill pill-active" onclick="filterCategory('all', this)">Semua Sesi (<?= $total_aktif; ?>)</button>
      <button class="pill" onclick="filterCategory('cat-wisuda', this)">Wisuda &amp; Akademik (<?= count($pakets_by_cat['wisuda'] ?? []); ?>)</button>
      <button class="pill" onclick="filterCategory('cat-portrait', this)">Editorial Portrait (<?= count($pakets_by_cat['portrait'] ?? []); ?>)</button>
      <button class="pill" onclick="filterCategory('cat-prewedding', this)">Prewedding &amp; Pasangan (<?= count($pakets_by_cat['prewedding'] ?? []); ?>)</button>
      <button class="pill" onclick="filterCategory('cat-family', this)">Family &amp; Group (<?= count($pakets_by_cat['family'] ?? []); ?>)</button>
    </div>
    <div class="quota-filter" style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
      <span style="font-size: 0.8rem; color: var(--ink-faint); font-weight: 600;">Filter Kuota:</span>
      <label style="color: var(--green); font-weight: 600; font-size: 0.8rem; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
        <input type="checkbox" id="chk-quota-available" checked style="accent-color: var(--green);" onchange="applyAllFilters()"> Kuota Tersedia
      </label>
      <label style="color: var(--amber); font-weight: 600; font-size: 0.8rem; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
        <input type="checkbox" id="chk-quota-limited" checked style="accent-color: var(--amber);" onchange="applyAllFilters()"> Slot Terbatas
      </label>
    </div>
  </div>
</section>

<!-- ===== KATALOG PAKET ===== -->
<section class="catalog container">
  <div style="margin-bottom:40px;">
    <p class="eyebrow eyebrow-dot">KATALOG RESMI MUSIM 2026 • STUDIO &amp; ON-LOCATION</p>
    <h2 class="section-title">Koleksi Paket Sesi Fotografi</h2>
    <p class="section-desc">Setiap paket dirancang dengan pengarahan pose editorial berstandar mode, pencahayaan eksklusif daylight &amp; strobe terkalibrasi, serta fasilitas preview instan di monitor studio.</p>
  </div>

<?php
/* ================================================================
   HELPER 1: Render Kartu Standar (Wisuda, Portrait, Family)
================================================================ */
// Warna background Highlight Badge sesuai pilihan admin (kolom highlight_warna di admin_edit_paket.php):
// '' = putih (bawaan), 'hitam', 'coklat'
function highlightBadgeClass(array $p) {
    if (array_key_exists('highlight_warna', $p)) {
        $map = ['hitam' => 'badge-dark', 'coklat' => 'badge-amber'];
        return $map[$p['highlight_warna']] ?? '';
    }
    // Kolom warna belum dibuat (halaman edit paket belum pernah dibuka): pakai aturan lama berdasarkan teks
    $lama = ['Best Seller' => 'badge-dark', 'Haute Couture' => 'badge-amber'];
    return $lama[$p['highlight_badge'] ?? ''] ?? '';
}

function renderPkgCardStandard(array $p): void {
    $sisa    = max(0, (int)$p['kuota_total'] - (int)$p['kuota_terpakai']);
    $q_cls   = $sisa <= 0 ? 'soldout' : ($sisa <= 3 ? 'limited' : 'available');
    $dp      = (int)($p['dp_minimal'] > 0 ? $p['dp_minimal'] : round($p['harga'] * 0.3));
    $feats   = array_slice(array_filter(array_map('trim', explode('|', $p['fitur']))), 0, 4);
    $booking = "jadwal.php?id={$p['id']}&paket=".urlencode($p['nama_paket'])."&harga={$p['harga']}&studio=".urlencode($p['lokasi_studio']);

    if ($sisa <= 0) {
        $badge_cls = 'badge-sold'; $badge_txt = 'Kuota Habis (0 Slot Tersedia)';
    } elseif ($sisa <= 3) {
        $badge_cls = 'badge-limit'; $badge_txt = "Slot Terbatas ({$sisa} Slot)";
    } else {
        $badge_cls = 'badge-avail'; $badge_txt = "Kuota Tersedia ({$sisa} Slot)";
    }

    $dur = getCompactDuration($p['durasi'] ?? '60 Menit');
    $lok = getCompactStudio($p['lokasi_studio'] ?? 'Studio A');
    $sub = htmlspecialchars($p['sub_tag'] ?? '');
    $hb = htmlspecialchars($p['highlight_badge'] ?? '');        // badge pilihan admin
    $tl_text = ($hb !== '') ? $hb : $sub;                           // tanpa badge pilihan: tampilan lama (Sub-Tag)
    $tl_cls = ($hb !== '') ? highlightBadgeClass($p) : '';
    ?>
    <div class="pkg-card <?= $q_cls; ?>"
         data-quota="<?= $q_cls; ?>"
         data-price="<?= (int)$p['harga']; ?>"
         data-studio="<?= htmlspecialchars(strtolower($p['lokasi_studio'])); ?>"
         data-name="<?= htmlspecialchars(strtolower($p['nama_paket'].' '.$p['sub_tag'].' '.($p['highlight_badge'] ?? '').' '.$p['deskripsi'])); ?>">

      <!-- Image -->
      <div class="pkg-img-wrap">
        <?php if (!empty($tl_text)): ?>
        <span class="img-badge-tl <?= $tl_cls; ?>">
          <?php if ($hb === ''): ?><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg><?php endif; ?>
          <?= $tl_text; ?>
        </span>
        <?php endif; ?>
        <span class="img-badge-tr <?= $badge_cls; ?>">
          <span class="quota-dot"></span><?= $badge_txt; ?>
        </span>
        <img src="<?= htmlspecialchars($p['gambar']); ?>" alt="<?= htmlspecialchars($p['nama_paket']); ?>" onerror="this.src='jpg/studio.jpg'">
        <div class="img-bottom-bar">
          <div class="img-bottom-left">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <?= $dur; ?>
            <span class="img-bottom-sep">•</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <?= $lok; ?>
          </div>
          <?php if (!empty($sub)): ?>
          <span class="img-bottom-right"><?= $sub; ?></span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Body -->
      <div class="pkg-body-standard">
        <p class="pkg-cat-label"><?= htmlspecialchars($p['kategori_label']); ?></p>
        <h4 class="pkg-title-standard"><?= htmlspecialchars($p['nama_paket']); ?></h4>
        <p class="pkg-desc-standard"><?= htmlspecialchars($p['deskripsi']); ?></p>

        <?php if (!empty($feats)): ?>
        <ul class="pkg-features-standard">
          <?php foreach ($feats as $f): ?>
          <li><?= htmlspecialchars($f); ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>

      <!-- Footer Box -->
      <div class="pkg-footer-standard">
        <div class="pkg-footer-standard-top">
          <span class="pkg-invest-label">INVESTASI TOTAL</span>
          <span class="pkg-dp-badge">DP Rp <?= number_format($dp, 0, ',', '.'); ?></span>
        </div>
        <div class="pkg-price-standard">Rp <?= number_format($p['harga'], 0, ',', '.'); ?></div>
        <?php if ($sisa <= 0): ?>
          <button class="btn-booking-standard disabled" disabled>Kuota Habis</button>
        <?php else: ?>
          <a href="<?= $booking; ?>" class="btn-booking-standard">Booking Sekarang</a>
        <?php endif; ?>
      </div>

    </div>
    <?php
}

/* ================================================================
   HELPER 1B: Render Kartu Editorial Portrait (Sesuai Gambar Referensi)
================================================================ */
function renderPkgCardPortrait(array $p): void {
    $sisa    = max(0, (int)$p['kuota_total'] - (int)$p['kuota_terpakai']);
    $q_cls   = $sisa <= 0 ? 'soldout' : ($sisa <= 3 ? 'limited' : 'available');
    $dp      = (int)($p['dp_minimal'] > 0 ? $p['dp_minimal'] : round($p['harga'] * 0.3));
    $feats   = array_slice(array_filter(array_map('trim', explode('|', $p['fitur']))), 0, 4);
    $booking = "jadwal.php?id={$p['id']}&paket=".urlencode($p['nama_paket'])."&harga={$p['harga']}&studio=".urlencode($p['lokasi_studio']);

    if ($sisa <= 0) {
        $badge_cls = 'badge-sold'; $badge_txt = 'Kuota Habis (0 Slot Tersedia)';
    } elseif ($sisa <= 3) {
        $badge_cls = 'badge-limit'; $badge_txt = "Slot Terbatas ({$sisa} Slot)";
    } else {
        $badge_cls = 'badge-avail'; $badge_txt = "Kuota Tersedia ({$sisa} Slot)";
    }

    $dur = getCompactDuration($p['durasi'] ?? '60 Menit');
    $lok = htmlspecialchars($p['lokasi_studio'] ?? 'Studio A');
    $sub = htmlspecialchars($p['sub_tag'] ?? '');
    $hb = htmlspecialchars($p['highlight_badge'] ?? '');        // badge pilihan admin
    $tl_text = ($hb !== '') ? $hb : $sub;                       // tanpa badge pilihan: tampilan lama (Sub-Tag)

    $tl_extra_cls = '';
    if ($hb !== '') {
        $tl_extra_cls = highlightBadgeClass($p);
    } elseif (stripos($sub, 'Best Seller') !== false) {         // data lama sebelum badge dipisah
        $tl_extra_cls = 'badge-dark';
    } elseif (stripos($sub, 'Haute Couture') !== false) {
        $tl_extra_cls = 'badge-amber';
    }
    ?>
    <div class="pkg-card <?= $q_cls; ?>"
         data-quota="<?= $q_cls; ?>"
         data-price="<?= (int)$p['harga']; ?>"
         data-studio="<?= htmlspecialchars(strtolower($p['lokasi_studio'])); ?>"
         data-name="<?= htmlspecialchars(strtolower($p['nama_paket'].' '.$p['sub_tag'].' '.($p['highlight_badge'] ?? '').' '.$p['deskripsi'])); ?>">

      <!-- Image -->
      <div class="pkg-img-wrap">
        <?php if (!empty($tl_text)): ?>
        <span class="img-badge-tl <?= $tl_extra_cls; ?>">
          <?= $tl_text; ?>
        </span>
        <?php endif; ?>
        <span class="img-badge-tr <?= $badge_cls; ?>">
          <span class="quota-dot"></span><?= $badge_txt; ?>
        </span>
        <img src="<?= htmlspecialchars($p['gambar']); ?>" alt="<?= htmlspecialchars($p['nama_paket']); ?>" onerror="this.src='jpg/studio.jpg'">
        <div class="img-bottom-bar">
          <div class="img-bottom-left" style="width:100%;">
            <span><?= $dur; ?> &nbsp;•&nbsp; <?= $lok; ?></span>
          </div>
        </div>
      </div>

      <!-- Body -->
      <div class="pkg-body-standard">
        <p class="pkg-cat-label"><?= htmlspecialchars($p['kategori_label']); ?><?php if ($hb !== '' && $sub !== ''): ?> &nbsp;•&nbsp; <?= $sub; ?><?php endif; ?></p>
        <h4 class="pkg-title-standard"><?= htmlspecialchars($p['nama_paket']); ?></h4>
        <p class="pkg-desc-standard"><?= htmlspecialchars($p['deskripsi']); ?></p>

        <?php if (!empty($feats)): ?>
        <ul class="pkg-features-standard">
          <?php foreach ($feats as $f): ?>
          <li><?= htmlspecialchars($f); ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>

      <!-- Footer Box (Price di Kiri, DP di Kanan, Button di Bawah) -->
      <div class="pkg-footer-portrait">
        <div class="pkg-price-portrait-row">
          <span class="pkg-price-amount-portrait">Rp <?= number_format($p['harga'], 0, ',', '.'); ?></span>
          <span class="pkg-price-dp-portrait">DP Rp <?= number_format($dp, 0, ',', '.'); ?></span>
        </div>
        <a href="<?= $booking; ?>" class="btn-booking-standard">Booking Sekarang</a>
      </div>

    </div>
    <?php
}

/* ================================================================
   HELPER 2: Render Kartu Prewedding & Pasangan (Layout 2-Kolom Rapih)
================================================================ */
function renderPkgCardPrewedding(array $p): void {
    $sisa    = max(0, (int)$p['kuota_total'] - (int)$p['kuota_terpakai']);
    $q_cls   = $sisa <= 0 ? 'soldout' : ($sisa <= 3 ? 'limited' : 'available');
    $dp      = (int)($p['dp_minimal'] > 0 ? $p['dp_minimal'] : round($p['harga'] * 0.3));
    $feats   = array_slice(array_filter(array_map('trim', explode('|', $p['fitur']))), 0, 4);
    $booking = "jadwal.php?id={$p['id']}&paket=".urlencode($p['nama_paket'])."&harga={$p['harga']}&studio=".urlencode($p['lokasi_studio']);

    if ($sisa <= 0) {
        $badge_cls = 'badge-sold'; $badge_txt = 'Kuota Habis (0 Slot Tersedia)';
    } elseif ($sisa <= 3) {
        $badge_cls = 'badge-limit'; $badge_txt = "Slot Terbatas ({$sisa} Slot)";
    } else {
        $badge_cls = 'badge-avail'; $badge_txt = "Kuota Tersedia ({$sisa} Slot)";
    }

    $dur = getCompactDuration($p['durasi'] ?? '90 Menit');
    $lok = getCompactStudio($p['lokasi_studio'] ?? 'Studio A & Garden');
    $sub = htmlspecialchars($p['sub_tag'] ?? 'Studio Intimate');
    $hb = htmlspecialchars($p['highlight_badge'] ?? '');        // badge pilihan admin
    $tl_text = ($hb !== '') ? $hb : $sub;                           // tanpa badge pilihan: tampilan lama (Sub-Tag)
    $tl_cls = ($hb !== '') ? highlightBadgeClass($p) : '';
    ?>
    <div class="pkg-card <?= $q_cls; ?>"
         data-quota="<?= $q_cls; ?>"
         data-price="<?= (int)$p['harga']; ?>"
         data-studio="<?= htmlspecialchars(strtolower($p['lokasi_studio'])); ?>"
         data-name="<?= htmlspecialchars(strtolower($p['nama_paket'].' '.$p['sub_tag'].' '.($p['highlight_badge'] ?? '').' '.$p['deskripsi'])); ?>">

      <!-- Image (Full Card Width) -->
      <div class="pkg-img-wrap">
        <?php if (!empty($tl_text)): ?>
        <span class="img-badge-tl <?= $tl_cls; ?>">
          <?php if ($hb === ''): ?><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg><?php endif; ?>
          <?= $tl_text; ?>
        </span>
        <?php endif; ?>
        <span class="img-badge-tr <?= $badge_cls; ?>">
          <span class="quota-dot"></span><?= $badge_txt; ?>
        </span>
        <img src="<?= htmlspecialchars($p['gambar']); ?>" alt="<?= htmlspecialchars($p['nama_paket']); ?>" onerror="this.src='jpg/studio.jpg'">
        <div class="img-bottom-bar">
          <div class="img-bottom-left">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <?= $dur; ?>
            <span class="img-bottom-sep">•</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <?= $lok; ?>
          </div>
          <span class="img-bottom-right">Kanvas Jati 30x45cm</span>
        </div>
      </div>

      <!-- Body Prewedding -->
      <div class="pkg-body-prewedding">
        <p class="pkg-cat-label">• PREWEDDING &amp; PASANGAN<?php if ($hb !== '' && $sub !== ''): ?> &nbsp;•&nbsp; <?= $sub; ?><?php endif; ?></p>
        <h3 class="pkg-title-prewedding"><?= htmlspecialchars($p['nama_paket']); ?></h3>
        <p class="pkg-desc-prewedding"><?= htmlspecialchars($p['deskripsi']); ?></p>

        <!-- 2 Kolom Fitur -->
        <?php if (!empty($feats)): ?>
        <ul class="pkg-features-2col">
          <?php foreach ($feats as $f): ?>
          <li><?= htmlspecialchars($f); ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>

      <!-- Footer Prewedding (Price di Kiri, Button di Kanan) -->
      <div class="pkg-footer-prewedding">
        <div class="pkg-footer-prewedding-left">
          <span class="pkg-invest-label">INVESTASI TOTAL SESI</span>
          <div class="pkg-price-prewedding-row">
            <span class="pkg-price-amount">Rp <?= number_format($p['harga'], 0, ',', '.'); ?></span>
            <span class="pkg-price-dp">(DP Rp <?= number_format($dp, 0, ',', '.'); ?>)</span>
          </div>
        </div>
        <div>
          <?php if ($sisa <= 0): ?>
            <button class="btn-booking-prewedding disabled" disabled>Kuota Habis</button>
          <?php else: ?>
            <a href="<?= $booking; ?>" class="btn-booking-prewedding">Booking Sekarang</a>
          <?php endif; ?>
        </div>
      </div>

    </div>
    <?php
}
?>

  <!-- ===== KATEGORI 01: WISUDA & AKADEMIK ===== -->
  <div id="cat-wisuda" class="category-wrapper">
    <div class="cat-section-head">
      <div class="cat-head-left">
        <p class="cat-kicker">Kategori 01 &nbsp;•&nbsp; <?= count($pakets_by_cat['wisuda'] ?? []); ?> Pilihan Paket Tersedia</p>
        <h3 class="cat-title">Wisuda &amp; Akademik</h3>
      </div>
      <span class="cat-loc-tag">Studio A &amp; B Cyclorama</span>
    </div>
    <div class="pkg-grid-standard pkg-grid-3">
      <?php foreach (($pakets_by_cat['wisuda'] ?? []) as $p): renderPkgCardStandard($p); endforeach; ?>
    </div>
  </div>

  <!-- ===== KATEGORI 02: EDITORIAL PORTRAIT ===== -->
  <div id="cat-portrait" class="category-wrapper" style="margin-top:56px;">
    <div class="cat-section-head">
      <div class="cat-head-left">
        <p class="cat-kicker">Kategori 02 &nbsp;•&nbsp; <?= count($pakets_by_cat['portrait'] ?? []); ?> Pilihan Paket Tersedia</p>
        <h3 class="cat-title">Editorial Portrait</h3>
      </div>
      <span class="cat-loc-tag">Personal Branding &amp; High Fashion</span>
    </div>
    <div class="pkg-grid-standard pkg-grid-4">
      <?php foreach (($pakets_by_cat['portrait'] ?? []) as $p): renderPkgCardPortrait($p); endforeach; ?>
    </div>
  </div>

  <!-- ===== KATEGORI 03: PREWEDDING & PASANGAN (2-KOLOM RAPIH) ===== -->
  <div id="cat-prewedding" class="category-wrapper" style="margin-top:56px;">
    <div class="cat-section-head">
      <div class="cat-head-left">
        <p class="cat-kicker">Kategori 03 &nbsp;•&nbsp; <?= count($pakets_by_cat['prewedding'] ?? []); ?> Pilihan Paket Tersedia</p>
        <h3 class="cat-title">Prewedding &amp; Pasangan</h3>
      </div>
      <span class="cat-loc-tag">Intimate &amp; Botanical Glasshouse</span>
    </div>
    <div class="pkg-grid-prewedding">
      <?php foreach (($pakets_by_cat['prewedding'] ?? []) as $p): renderPkgCardPrewedding($p); endforeach; ?>
    </div>
  </div>

  <!-- ===== KATEGORI 04: FAMILY & GROUP ===== -->
  <div id="cat-family" class="category-wrapper" style="margin-top:56px;">
    <div class="cat-section-head">
      <div class="cat-head-left">
        <p class="cat-kicker">Kategori 04 &nbsp;•&nbsp; <?= count($pakets_by_cat['family'] ?? []); ?> Pilihan Paket Tersedia</p>
        <h3 class="cat-title">Family &amp; Group</h3>
      </div>
      <span class="cat-loc-tag">Keluarga, Reuni, Maternity &amp; Komersial</span>
    </div>
    <div class="pkg-grid-standard pkg-grid-3">
      <?php foreach (($pakets_by_cat['family'] ?? []) as $p): renderPkgCardStandard($p); endforeach; ?>
    </div>
  </div>

  <!-- State Jika Kosong -->
  <div id="no-results-msg" style="display:none; text-align:center; padding:60px 20px; background:#fff; border:1px dashed var(--line); border-radius:12px; margin-top:24px;">
    <p style="font-family:var(--font-serif); font-size:1.4rem; color:var(--ink); margin:0 0 8px;">Tidak Ada Paket yang Sesuai</p>
    <p style="font-size:0.85rem; color:var(--ink-soft); max-width:480px; margin:0 auto 20px;">Tidak ditemukan paket fotografi yang cocok dengan filter yang Anda pilih.</p>
    <button type="button" onclick="resetAllFilters()" class="btn btn-dark" style="display:inline-block; padding:10px 24px; font-size:0.85rem; cursor:pointer;">Reset Filter &amp; Tampilkan Semua</button>
  </div>

</section>

<!-- ===== 3 LANGKAH RESERVASI ===== -->
<section class="steps-section">
  <div class="container">
    <p class="eyebrow eyebrow-center" style="display:block; text-align:center;">SOP RESERVASI TERSTRUKTUR</p>
    <h2 class="section-title section-title-center" style="text-align:center; max-width:none; margin-left:auto; margin-right:auto;">3 Langkah Simpel Reservasi Studio</h2>
    <div class="steps-grid">
      <div class="step">
        <span class="step-number">1</span>
        <h4>Pilih Paket &amp; Kunci Kuota Slot</h4>
        <p>Tentukan paket yang sesuai kebutuhan Anda, pilih tanggal dan jam luang di kalender studio secara instan. Slot dikunci otomatis 15 Menit saat checkout.</p>
      </div>
      <div class="step">
        <span class="step-number">2</span>
        <h4>Bayar DP 30% via QRIS Instan</h4>
        <p>Cukup bayar down-payment pengunci tanggal melalui QRIS semua bank &amp; e-wallet atau Virtual Account resmi. Verifikasi otomatis tanpa perlu upload struk.</p>
      </div>
      <div class="step">
        <span class="step-number">3</span>
        <h4>Terima E-Voucher &amp; Brief Foto</h4>
        <p>Konfirmasi resmi langsung dikirim ke WhatsApp &amp; Email beserta panduan styling pakaian dan akses parkir studio.</p>
      </div>
    </div>
  </div>
</section>

<!-- ===== FOOTER ===== -->
<footer class="site-footer" style="background:transparent;color:var(--ink-soft);border-top:1px solid var(--line);">
  <div class="container footer-grid" style="border-bottom:1px solid var(--line); padding-bottom: 32px;">
    <div class="footer-brand">
      <p class="logo" style="color:var(--ink);">CREATIVE<span>PIXEL</span></p>
      <p>Jl. Marpoyan No. 42, Bukit Raya,<br>Pekanbaru, Riau</p>
      <p style="text-transform:uppercase;font-size:0.72rem;letter-spacing:0.04em;color:var(--ink-faint);margin-bottom:6px; margin-top:20px;">Jam Operasional:</p>
      <p style="color:var(--ink);font-weight:600;">Setiap Hari: 09.00 – 21.00 WIB</p>
    </div>
    <div class="footer-col">
      <h5 style="color:var(--ink);">Kategori Layanan</h5>
      <ul>
        <li>Studio Portrait Editorial</li>
        <li>Prewedding &amp; Fine Art Wedding</li>
        <li>Wisuda &amp; Graduation Commemorative</li>
        <li>Komersial &amp; Katalog Produk</li>
        <li>Family &amp; Maternity Sessions</li>
      </ul>
    </div>
    <div class="footer-col">
      <h5 style="color:var(--ink);">Kebijakan &amp; Akses Klien</h5>
      <ul>
        <li>Masuk Portal Klien</li>
        <li>Cek Status Reservasi</li>
        <li><strong style="color:var(--ink);">Reschedule Gratis</strong><br>Maksimal H-3 sebelum sesi</li>
        <li><strong style="color:var(--ink);">DP 30% Pengunci Slot</strong><br>Langsung mengamankan jadwal kalender</li>
      </ul>
    </div>
    <div class="footer-col">
      <h5 style="color:var(--ink);">Bantuan &amp; WhatsApp Concierge</h5>
      <p>Perlu konsultasi moodboard, jadwal darurat, atau wardrobe catalog?</p>
      <p class="online-status"><i class="dot dot-green"></i> <span style="font-size: 0.8rem; color: var(--green); font-weight:600;">Online • Respons Instan</span></p>
      <p class="phone" style="color:var(--ink); font-size:1.1rem; margin-top: 12px;">+62 821-3361-4138</p>
    </div>
  </div>
  <div class="container footer-bottom" style="padding-top:20px;">
    <p>© 2026 Creative Pixel (Creative Pixel Frame Studio). Hak Cipta Dilindungi.</p>
    <p>Portal Masuk Klien &nbsp;·&nbsp; Contemporary Editorial Photography</p>
  </div>
</footer>

<!-- SCRIPT FILTER -->
<script>
let currentCategory = 'all';

function filterCategory(catId, btnElement) {
    document.querySelectorAll('.filter-pills .pill').forEach(p => {
        p.classList.remove('pill-active');
        p.style.background = '#F3F1EC';
        p.style.color = 'var(--ink)';
    });
    btnElement.classList.add('pill-active');
    btnElement.style.background = 'var(--dark)';
    btnElement.style.color = '#fff';
    currentCategory = catId;
    applyAllFilters();
}

function applyAllFilters() {
    const showAvail  = document.getElementById('chk-quota-available')?.checked ?? true;
    const showLimit  = document.getElementById('chk-quota-limited')?.checked ?? true;
    const showSold   = true;

    let totalVisible = 0;
    document.querySelectorAll('.category-wrapper').forEach(cat => {
        if (currentCategory !== 'all' && cat.id !== currentCategory) {
            cat.style.display = 'none'; return;
        }
        let visInCat = 0;
        cat.querySelectorAll('.pkg-card').forEach(card => {
            const q = card.dataset.quota || 'available';

            const qOk = (q === 'available' && showAvail) ||
                        (q === 'limited'   && showLimit)  ||
                        (q === 'soldout'   && showSold);

            if (qOk) {
                card.style.display = ''; visInCat++; totalVisible++;
            } else {
                card.style.display = 'none';
            }
        });
        cat.style.display = visInCat > 0 ? 'block' : 'none';
    });

    const noMsg = document.getElementById('no-results-msg');
    if (noMsg) noMsg.style.display = totalVisible === 0 ? 'block' : 'none';
}

function resetAllFilters() {
    ['chk-quota-available','chk-quota-limited'].forEach(id => {
        const el = document.getElementById(id); if (el) el.checked = true;
    });
    const fp = document.querySelector('.filter-pills .pill');
    if (fp) filterCategory('all', fp); else applyAllFilters();
}

document.addEventListener('DOMContentLoaded', () => applyAllFilters());
</script>
</body>
</html>