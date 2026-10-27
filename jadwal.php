<?php
session_start();
require_once 'koneksi.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$nama_user = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Klien';
$user_id = (int)$_SESSION['user_id'];

// Logika slot harian (sama dengan yang dipakai admin_jadwal.php)
require_once 'slot_helper.php';
slot_pastikan_tabel($conn);

// Endpoint ketersediaan per bulan untuk kalender (JSON)
if (isset($_GET['api']) && $_GET['api'] === 'slot') {
    $b = isset($_GET['bulan']) ? max(1, min(12, (int)$_GET['bulan'])) : (int)date('n');
    $t = isset($_GET['tahun']) ? max(2020, min(2100, (int)$_GET['tahun'])) : (int)date('Y');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(slot_json_bulan($conn, $user_id, $b, $t));
    exit;
}

// Pesan error dari checkout.php (misal slot baru penuh atau jam sudah dipesan akun ini)
$err_msg = isset($_GET['err']) ? trim($_GET['err']) : '';

// Jika belum memilih paket dari etalase, arahkan kembali ke etalase paket
if (!isset($_GET['paket']) && !isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$paket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db_pkt = null;

if ($paket_id > 0) {
    $stmt_p = $conn->prepare("SELECT * FROM paket WHERE id = ?");
    $stmt_p->bind_param("i", $paket_id);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();
    if ($res_p->num_rows > 0) {
        $db_pkt = $res_p->fetch_assoc();
    }
    $stmt_p->close();
} elseif (isset($_GET['paket'])) {
    $p_name = trim($_GET['paket']);
    $stmt_p = $conn->prepare("SELECT * FROM paket WHERE nama_paket = ? LIMIT 1");
    $stmt_p->bind_param("s", $p_name);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();
    if ($res_p->num_rows > 0) {
        $db_pkt = $res_p->fetch_assoc();
    }
    $stmt_p->close();
}

if ($db_pkt) {
    $paket_nama = htmlspecialchars($db_pkt['nama_paket']);
    $harga_paket = (int)$db_pkt['harga'];
    $dp_paket = (int)($db_pkt['dp_minimal'] > 0 ? $db_pkt['dp_minimal'] : round($harga_paket * 0.3));
    $lokasi_studio = htmlspecialchars($db_pkt['lokasi_studio']);
    $durasi_sesi = htmlspecialchars($db_pkt['durasi']);
} else {
    $paket_nama = htmlspecialchars($_GET['paket'] ?? 'Paket Fotografi Studio');
    $harga_paket = isset($_GET['harga']) ? (int)$_GET['harga'] : 3500000;
    $dp_paket = (int)round($harga_paket * 0.3);
    $lokasi_studio = isset($_GET['studio']) ? htmlspecialchars($_GET['studio']) : 'Studio A & Garden Lumina Senopati';
    $durasi_sesi = '2 Jam Sesi Foto';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Jadwal & Kuota - Creative Pixel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<style>
/* =========================================
   GAYA KHUSUS HALAMAN JADWAL & KUOTA
========================================= */
.booking-wrap { padding: 40px 0 80px; }

/* Package Header */
.package-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; flex-wrap: wrap; gap: 24px; }
.ph-tags { display: flex; gap: 10px; margin-bottom: 16px; }
.badge-soft { background: #F4E7DC; color: var(--accent); font-size: 0.7rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 6px; }
.badge-outline { border: 1px solid var(--line); color: var(--ink-soft); font-size: 0.7rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; }
.ph-title { font-family: var(--font-serif); font-size: 2.4rem; font-weight: 500; margin: 0 0 16px; color: var(--ink); }
.ph-meta { display: flex; gap: 24px; font-size: 0.85rem; color: var(--ink-soft); align-items: center; flex-wrap: wrap; }
.ph-meta-item { display: flex; align-items: center; gap: 8px; }

.price-box { background: #FAF9F6; border: 1px solid var(--line); border-radius: var(--radius-md); padding: 24px 32px; text-align: right; min-width: 300px; }
.price-box-label { font-size: 0.7rem; color: var(--ink-faint); font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; margin: 0 0 8px; }
.price-box-value { font-family: var(--font-serif); font-size: 2rem; color: var(--ink); margin: 0 0 8px; }
.price-box-value span { font-size: 1rem; font-family: var(--font-sans); color: var(--ink-soft); }
.price-box-note { font-size: 0.75rem; color: var(--accent); font-weight: 600; margin: 0; display: flex; align-items: center; justify-content: flex-end; gap: 6px; }

/* Layout Grid */
.booking-grid { display: grid; grid-template-columns: 1.6fr 1fr; gap: 40px; align-items: start; }
.card-section { background: #fff; border: 1px solid var(--line); border-radius: var(--radius-lg); padding: 32px; margin-bottom: 24px; }
.step-kicker { font-size: 0.7rem; font-weight: 700; color: var(--ink-faint); text-transform: uppercase; letter-spacing: 0.06em; margin: 0 0 12px; }
.step-title { font-family: var(--font-serif); font-size: 1.6rem; font-weight: 500; margin: 0 0 24px; display: flex; justify-content: space-between; align-items: center; }

/* Calendar */
.cal-nav { display: flex; align-items: center; background: var(--bg-alt); border-radius: 999px; padding: 4px; }
.cal-nav button { background: none; border: none; padding: 6px 12px; cursor: pointer; color: var(--ink-soft); }
.cal-nav span { font-size: 0.85rem; font-weight: 600; padding: 0 16px; }
.cal-legend { display: flex; flex-wrap: wrap; gap: 16px; background: var(--bg-alt); padding: 12px 20px; border-radius: var(--radius-sm); margin-bottom: 24px; font-size: 0.75rem; font-weight: 600; color: var(--ink-soft); align-items: center; }
.legend-item { display: flex; align-items: center; gap: 6px; }
.dot-green { background: var(--green); } .dot-amber { background: var(--amber); } .dot-red { background: var(--red); }
.cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; text-align: center; }
.cal-day-head { font-size: 0.75rem; font-weight: 600; color: var(--ink-faint); margin-bottom: 12px; text-transform: uppercase; }
.cal-day { aspect-ratio: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; border-radius: var(--radius-md); font-size: 0.95rem; font-weight: 500; cursor: pointer; transition: 0.2s; border: 1px solid transparent; }
.cal-day:hover:not(.cal-empty):not(.cal-blocked) { border-color: var(--line); }
.cal-empty { color: #E4E1D8; cursor: default; }
.cal-day .dot { margin-top: 4px; width: 4px; height: 4px; margin-right: 0; }
.cal-active { background: var(--dark); color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.cal-active .dot { background: var(--accent); }

/* Time Slots */
.time-slot-list { display: flex; flex-direction: column; gap: 16px; }
.time-slot { display: flex; align-items: center; padding: 20px; border: 1px solid var(--line); border-radius: var(--radius-md); cursor: pointer; transition: 0.2s; background: #fff; }
.time-slot:hover:not(.slot-disabled) { border-color: var(--dark); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.slot-disabled { background: var(--bg-alt); cursor: not-allowed; opacity: 0.6; }
.slot-radio { margin-right: 16px; width: 18px; height: 18px; accent-color: var(--dark); }
.slot-info { flex: 1; }
.slot-time-row { display: flex; align-items: center; gap: 12px; margin-bottom: 4px; }
.slot-time { font-size: 1.05rem; font-weight: 600; color: var(--ink); margin: 0; }
.slot-tag { font-size: 0.65rem; font-weight: 700; padding: 4px 8px; border-radius: 4px; text-transform: uppercase; }
.tag-amber { background: var(--amber-bg); color: var(--amber); }
.tag-green { background: var(--green-bg); color: var(--green); }
.tag-red { background: var(--red-bg); color: var(--red); }
.slot-desc { font-size: 0.8rem; color: var(--ink-soft); margin: 0; }

/* Previews */
.preview-title { font-size: 0.7rem; font-weight: 700; color: var(--ink-faint); text-transform: uppercase; letter-spacing: 0.05em; margin: 32px 0 12px; }
.preview-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.preview-img { position: relative; border-radius: var(--radius-sm); overflow: hidden; aspect-ratio: 16/9; }
.preview-img img { width: 100%; height: 100%; object-fit: cover; }
.preview-label { position: absolute; bottom: 8px; left: 8px; background: rgba(0,0,0,0.6); color: #fff; font-size: 0.65rem; padding: 4px 8px; border-radius: 4px; backdrop-filter: blur(4px); }

/* Sidebar Addons */
.sidebar-section { background: #fff; border: 1px solid var(--line); border-radius: var(--radius-lg); padding: 32px; margin-bottom: 24px; }
.sidebar-title { display: flex; justify-content: space-between; align-items: center; font-size: 1.1rem; font-weight: 600; margin: 0 0 20px; border-bottom: 1px solid var(--line); padding-bottom: 16px; }
.sidebar-title span { font-size: 0.7rem; color: var(--ink-faint); font-weight: 600; text-transform: uppercase; background: var(--bg-alt); padding: 4px 8px; border-radius: 4px; }
.addon-list { display: flex; flex-direction: column; gap: 16px; }
.addon-item { display: flex; align-items: flex-start; gap: 12px; }
.addon-item input { width: 18px; height: 18px; margin-top: 2px; accent-color: var(--dark); }
.addon-content { flex: 1; }
.addon-head { display: flex; justify-content: space-between; margin-bottom: 4px; }
.addon-name { font-size: 0.9rem; font-weight: 600; color: var(--ink); margin: 0; }
.addon-price { font-size: 0.9rem; font-weight: 700; color: var(--ink); margin: 0; }
.addon-desc { font-size: 0.75rem; color: var(--ink-soft); margin: 0; line-height: 1.4; }

/* Summary Box */
.summary-box { border: 2px dashed #3BA5D1; border-radius: var(--radius-lg); padding: 32px; background: #FAFCFD; position: relative; }
.summary-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed var(--line); padding-bottom: 16px; margin-bottom: 16px; }
.summary-header h4 { font-size: 0.8rem; font-weight: 700; color: var(--ink-soft); text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }
.lock-tag { font-size: 0.7rem; color: #3BA5D1; font-weight: 600; display: flex; align-items: center; gap: 4px; background: #EAF5FA; padding: 4px 10px; border-radius: 999px; }
.summary-detail-list { font-size: 0.85rem; color: var(--ink-soft); margin-bottom: 24px; display: flex; flex-direction: column; gap: 10px; }
.summary-row { display: flex; justify-content: space-between; }
.summary-val { font-weight: 600; color: var(--ink); text-align: right; }
.summary-val-accent { color: #3BA5D1; }

.price-breakdown { border-top: 1px dashed var(--line); border-bottom: 1px dashed var(--line); padding: 16px 0; margin-bottom: 16px; font-family: monospace; font-size: 0.85rem; color: var(--ink-soft); display: flex; flex-direction: column; gap: 8px; }
.price-breakdown .row { display: flex; justify-content: space-between; }

.total-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.total-label { font-size: 1.1rem; font-weight: 600; color: var(--ink); }
.total-value { font-family: var(--font-serif); font-size: 1.6rem; font-weight: 500; color: var(--ink); }

.dp-box { background: #FFF4E5; border: 1px dashed var(--amber); border-radius: var(--radius-sm); padding: 16px; margin-bottom: 24px; }
.dp-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.dp-label { font-size: 0.8rem; font-weight: 600; color: var(--ink); }
.dp-val { font-family: var(--font-serif); font-size: 1.2rem; font-weight: 500; color: var(--accent); }
.dp-note { font-size: 0.7rem; color: var(--ink-soft); margin: 0; line-height: 1.4; }

.btn-checkout { background: var(--accent); color: #fff; width: 100%; padding: 16px; border-radius: var(--radius-sm); font-weight: 600; font-size: 0.95rem; border: none; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 8px; margin-bottom: 16px; }
.btn-checkout:hover { opacity: 0.9; }

.guarantee-note { display: flex; gap: 8px; font-size: 0.7rem; color: var(--ink-soft); line-height: 1.4; }
.guarantee-note svg { color: var(--green); flex-shrink: 0; margin-top: 2px; }

/* Status tanggal dari jadwal admin */
.dot-gray { background: #9CA3AF; }
.cal-day small { font-size: 0.58rem; font-weight: 700; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.03em; }
.cal-day.cal-blocked { cursor: not-allowed; }
.cal-day.cal-closed { background: repeating-linear-gradient(135deg, #F3F4F6, #F3F4F6 5px, #ECEDEF 5px, #ECEDEF 10px); color: #9CA3AF; }
.cal-day.cal-full { color: var(--red); background: #FEF2F2; }
.cal-day.cal-mine { color: var(--ink-faint); background: var(--bg-alt); }
.cal-error { grid-column: 1 / -1; padding: 24px; color: var(--red); font-size: 0.85rem; font-weight: 600; }
.slot-day-note { font-size: 0.8rem; color: var(--ink-soft); margin: -12px 0 16px; }
.form-alert { background: var(--red-bg); border: 1px solid var(--red); color: var(--red); padding: 14px 20px; border-radius: var(--radius-sm); font-size: 0.85rem; font-weight: 600; margin-bottom: 24px; }
.btn-checkout:disabled { opacity: 0.6; cursor: wait; }

/* Progress Stepper (sama dengan checkout.php) */
.step-indicator { display: flex; justify-content: space-between; align-items: center; max-width: 800px; margin: 0 auto 40px; position: relative; }
.step-indicator::before { content: ""; position: absolute; top: 12px; left: 10%; right: 10%; height: 2px; background: #EAEAEA; z-index: 1; }
.step-item { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; font-weight: 600; color: var(--ink-faint); z-index: 2; background: #FAF9F6; padding: 0 10px; }
.step-item.active { color: #D9822B; }
.step-item.done { color: var(--green); }
.step-circle { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; color: #fff; }
.done .step-circle { background: var(--green); }
.active .step-circle { background: #D9822B; }
.pending .step-circle { background: #EAEAEA; color: var(--ink-soft); }
@media (max-width: 768px) { .step-indicator::before { display:none; } .step-indicator { flex-direction: column; align-items: flex-start; gap: 12px; } }

@media (max-width: 900px) {
    .booking-grid { grid-template-columns: 1fr; }
    .package-header { flex-direction: column; }
    .price-box { min-width: 100%; text-align: left; }
    .price-box-note { justify-content: flex-start; }
    .header-inner { flex-wrap: wrap; }
}
</style>
<!-- Responsive layout (harus paling akhir supaya menimpa CSS lama) -->
<link rel="stylesheet" href="responsive.css?v=<?= time(); ?>">
<script src="responsive.js?v=<?= time(); ?>" defer></script>
</head>
<body>

<!-- ================= HEADER ================= -->
<header class="site-header">
  <div class="container header-inner">
    <a href="index.php" class="logo">CREATIVE<span>PIXEL</span></a>
    <nav class="main-nav">
      <a href="index.php">Etalase Paket</a>
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

<main class="booking-wrap container">
    <!-- PROGRESS BAR -->
    <div class="step-indicator">
        <div class="step-item done"><div class="step-circle">✓</div> Langkah 1: Pilih Paket</div>
        <div class="step-item active"><div class="step-circle">2</div> Langkah 2: Jadwal &amp; Kuota</div>
        <div class="step-item pending"><div class="step-circle">3</div> Langkah 3: Data &amp; Pembayaran</div>
        <div class="step-item pending"><div class="step-circle">4</div> Langkah 4: Konfirmasi Tiket</div>
    </div>

    <a href="index.php" style="display:inline-flex; align-items:center; gap:8px; font-size:0.85rem; font-weight:600; color:var(--ink-soft); text-decoration:none; margin-bottom:24px; transition:color 0.2s;" onmouseover="this.style.color='var(--ink)'" onmouseout="this.style.color='var(--ink-soft)'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        Kembali ke Etalase Paket
    </a>

    <form action="checkout.php" method="POST" id="bookingForm">
        <input type="hidden" name="paket_layanan" value="<?= $paket_nama; ?>">
        <input type="hidden" name="harga_dasar" id="input_harga_dasar" value="<?= $harga_paket; ?>">
        <input type="hidden" name="total_harga" id="input_total_harga" value="<?= $harga_paket; ?>">
        <input type="hidden" name="addons_total" id="input_addons_total" value="0">
        <input type="hidden" name="addons_list" id="input_addons_list" value="">
        <input type="hidden" name="tanggal_sesi" id="input_tanggal" value="<?= date('Y-m-d', strtotime('+3 days')); ?>">
        <input type="hidden" name="lokasi_studio" value="<?= htmlspecialchars($lokasi_studio); ?>">

        <?php if ($err_msg !== ''): ?>
        <div class="form-alert"><?= htmlspecialchars($err_msg); ?></div>
        <?php endif; ?>

        <!-- ================= PACKAGE HEADER ================= -->
        <div class="package-header">
            <div>
                <div class="ph-tags">
                    <span class="badge-soft"><i class="dot dot-amber" style="background:var(--accent);"></i> Paket Pilihan</span>
                    <span class="badge-outline"><i class="dot dot-green"></i> Kalender Kuota Real-time</span>
                </div>
                <h1 class="ph-title"><?= $paket_nama; ?></h1>
                <div class="ph-meta">
                    <div class="ph-meta-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        Durasi Sesi: <?= htmlspecialchars($durasi_sesi); ?>
                    </div>
                    <span>•</span>
                    <div class="ph-meta-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        Lokasi: <?= htmlspecialchars($lokasi_studio); ?>
                    </div>
                </div>
            </div>
            
            <div class="price-box">
                <p class="price-box-label">Harga Dasar Paket</p>
                <p class="price-box-value">Rp <?= number_format($harga_paket, 0, ',', '.'); ?> <span>/sesi</span></p>
                <p class="price-box-note">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    Ketentuan DP Kunci Kuota (30%): Rp <?= number_format($dp_paket, 0, ',', '.'); ?>
                </p>
            </div>
        </div>

        <div class="booking-grid">
            <!-- ================= LEFT COLUMN ================= -->
            <div class="booking-main">
                
                <!-- STEP 1: CALENDAR -->
                <div class="card-section">
                    <p class="step-kicker">Langkah 2A: Tentukan Hari</p>
                    <h2 class="step-title">
                        <span id="cal-month-title">Bulan Tahun</span> <span class="badge-soft" style="font-size: 0.65rem;">High Season</span>
                        <div class="cal-nav">
                            <button type="button" id="cal-prev-btn" title="Bulan Sebelumnya">❮</button>
                            <span id="cal-nav-label">Bln Thn</span>
                            <button type="button" id="cal-next-btn" title="Bulan Berikutnya">❯</button>
                        </div>
                    </h2>

                    <div class="cal-legend">
                        <span style="color:var(--ink);">Status Kuota:</span>
                        <div class="legend-item"><i class="dot dot-green"></i> Tersedia (&ge; 3 slot)</div>
                        <div class="legend-item"><i class="dot dot-amber"></i> Terbatas (1-2 slot)</div>
                        <div class="legend-item"><i class="dot dot-red"></i> Penuh (Full Booked)</div>
                        <div class="legend-item"><i class="dot dot-gray"></i> Tutup</div>
                        <div class="legend-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15v-4H8l4-7v4h3l-4 7z"/></svg> Tanggal Terpilih</div>
                    </div>

                    <div class="cal-grid" id="cal-grid-container">
                        <!-- Kalender diisi secara dinamis oleh JavaScript -->
                    </div>
                </div>

                <!-- STEP 2: TIME SLOTS -->
                <div class="card-section">
                    <p class="step-kicker">Langkah 2B: Pilih Waktu Sesi</p>
                    <h2 class="step-title" id="step2-date-heading" style="margin-bottom: 24px; font-size:1.4rem;">Pilih Tanggal Sesi</h2>

                    <p class="slot-day-note" id="slot-day-note"></p>
                    <div class="time-slot-list" id="time-slot-list"></div>
                </div>
            </div>

            <!-- ================= RIGHT COLUMN (SIDEBAR) ================= -->
            <div class="booking-sidebar">
                
                <!-- ADD-ONS -->
                <div class="sidebar-section">
                    <h3 class="sidebar-title">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg> 
                            Pilihan Add-on Layanan
                        </div>
                        <span>Opsional</span>
                    </h3>
                    <p style="font-size:0.8rem; color:var(--ink-soft); margin-bottom:20px; line-height:1.4;">Tingkatkan hasil sesi dengan asistensi penata rias, durasi tambahan, atau liputan drone. Total harga akan otomatis terhitung.</p>
                    
                    <div class="addon-list">
                        <label class="addon-item">
                            <input type="checkbox" name="addons[]" value="Extra 1 Jam Pemotretan" data-name="Extra 1 Jam Pemotretan" data-price="600000" class="addon-cb">
                            <div class="addon-content">
                                <div class="addon-head">
                                    <p class="addon-name">Extra 1 Jam Pemotretan</p>
                                    <p class="addon-price">+Rp 600.000</p>
                                </div>
                                <p class="addon-desc">Total durasi sesi menjadi 3 jam (lebih santai ganti 2-3 wardrobe)</p>
                            </div>
                        </label>
                        <hr style="border:0; border-bottom:1px solid var(--line); margin:0;">
                        <label class="addon-item">
                            <input type="checkbox" name="addons[]" value="Professional MUA & Hairdo" data-name="Professional MUA & Hairdo" data-price="750000" class="addon-cb">
                            <div class="addon-content">
                                <div class="addon-head">
                                    <p class="addon-name">Professional MUA & Hairdo</p>
                                    <p class="addon-price">+Rp 750.000</p>
                                </div>
                                <p class="addon-desc">Studio Partner (Retouch standby selama sesi foto berlangsung)</p>
                            </div>
                        </label>
                        <hr style="border:0; border-bottom:1px solid var(--line); margin:0;">
                        <label class="addon-item">
                            <input type="checkbox" name="addons[]" value="Express 24-Hour Preview File" data-name="Express 24-Hour Preview File" data-price="300000" class="addon-cb">
                            <div class="addon-content">
                                <div class="addon-head">
                                    <p class="addon-name">Express 24-Hour Preview File</p>
                                    <p class="addon-price">+Rp 300.000</p>
                                </div>
                                <p class="addon-desc">Akses online gallery mentahan beresolusi tinggi H+1 sesi</p>
                            </div>
                        </label>
                        <hr style="border:0; border-bottom:1px solid var(--line); margin:0;">
                        <label class="addon-item">
                            <input type="checkbox" name="addons[]" value="Drone Aerial Outdoor Capture" data-name="Drone Aerial Outdoor Capture" data-price="850000" class="addon-cb">
                            <div class="addon-content">
                                <div class="addon-head">
                                    <p class="addon-name">Drone Aerial Outdoor Capture</p>
                                    <p class="addon-price">+Rp 850.000</p>
                                </div>
                                <p class="addon-desc">Sudut pandang sinematik bird-view di area garden</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- SUMMARY BOX -->
                <div class="summary-box">
                    <div class="summary-header">
                        <h4>Ringkasan Reservasi Kuota</h4>
                        <span class="lock-tag"><i class="dot dot-green" style="background:#3BA5D1;"></i> Kunci Kuota Aktif</span>
                    </div>

                    <div class="summary-detail-list">
                        <div class="summary-row">
                            <span>Jadwal Sesi:</span>
                            <span class="summary-val" id="summary-date-val">Pilih Tanggal</span>
                        </div>
                        <div class="summary-row">
                            <span>Alokasi Waktu:</span>
                            <span class="summary-val" id="summary-time-val">09.00 – 11.00 WIB</span>
                        </div>
                        <div class="summary-row">
                            <span>Fotografer:</span>
                            <span class="summary-val" style="display:flex; align-items:center; gap:4px; justify-content:flex-end;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="var(--accent)"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                Senior Lead (Bagus W.)
                            </span>
                        </div>
                        <div class="summary-row">
                            <span>Status Slot:</span>
                            <span class="summary-val summary-val-accent" id="summary-status-val">Memuat...</span>
                        </div>
                    </div>

                    <div class="price-breakdown">
                        <div class="row">
                            <span><?= $paket_nama; ?></span>
                            <span>Rp <?= number_format($harga_paket, 0, ',', '.'); ?></span>
                        </div>
                        <div id="breakdown-addons"></div>
                    </div>

                    <div class="total-row">
                        <span class="total-label">Total Nilai Sesi:</span>
                        <span class="total-value" id="summary-total-val">Rp <?= number_format($harga_paket, 0, ',', '.'); ?></span>
                    </div>

                    <div class="dp-box">
                        <div class="dp-row">
                            <span class="dp-label">Minimum DP Pengunci Slot (30%):</span>
                            <span class="dp-val" id="summary-dp-val">Rp <?= number_format($dp_paket, 0, ',', '.'); ?></span>
                        </div>
                        <p class="dp-note" id="summary-sisa-note">Sisa pelunasan (Rp <?= number_format($harga_paket - $dp_paket, 0, ',', '.'); ?>) diselesaikan saat tiba di studio.</p>
                    </div>

                    <button type="submit" class="btn-checkout">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        Lanjut ke Formulir & Checkout Kuota ➔
                    </button>

                    <div class="guarantee-note">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        <span><strong>Jaminan Lumina:</strong> Bebas reschedule hingga H-3 tanpa biaya penalti kuota. Slot diamankan secara otomatis di sistem lab.</span>
                    </div>
                </div>

            </div>
        </div>
    </form>
</main>

<script>
// =========================================
// KALENDER & SLOT (tersinkron dengan jadwal admin)
// =========================================
const basePrice = <?= $harga_paket; ?>;
const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const SLOT_JAM = <?= json_encode(slot_daftar_jam(), JSON_UNESCAPED_UNICODE); ?>;
const SERVER_TODAY = '<?= date('Y-m-d'); ?>';
const SERVER_NOW_MIN = <?= (int)date('G') * 60 + (int)date('i'); ?>;

const todayParts = SERVER_TODAY.split('-').map(Number);
let currentYear = todayParts[0];
let currentMonth = todayParts[1] - 1; // 0-indexed
let selectedDate = null;
let calendarReady = false;
const monthCache = {};

function formatRupiah(num) {
  return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}
function pad2(n) { return String(n).padStart(2, '0'); }
function formatDateString(d) {
  return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}
function formatIndoDate(d, shortMonth = false) {
  const dayName = dayNames[d.getDay()];
  const mName = shortMonth ? monthNames[d.getMonth()].substring(0, 3) : monthNames[d.getMonth()];
  return `${dayName}, ${d.getDate()} ${mName} ${d.getFullYear()}`;
}
function startMinutes(mulai) {
  const p = mulai.split(':').map(Number);
  return p[0] * 60 + p[1];
}

// Ambil ketersediaan 1 bulan dari server (data sama dengan yang diatur admin)
async function loadMonth(year, month, force = false) {
  const key = `${year}-${pad2(month + 1)}`;
  if (!force && monthCache[key]) return monthCache[key];
  try {
    const res = await fetch(`jadwal.php?api=slot&tahun=${year}&bulan=${month + 1}`, { credentials: 'same-origin', cache: 'no-store' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();
    monthCache[key] = json.days;
    return json.days;
  } catch (e) {
    return null;
  }
}

// Semua jam hari ini sudah lewat?
function semuaJamLewat(key) {
  return key === SERVER_TODAY && SLOT_JAM.every(j => startMinutes(j.mulai) <= SERVER_NOW_MIN);
}

// Tanggal bisa dipilih kalau belum lewat, studio buka, masih ada sisa slot, dan masih ada jam yang bisa dipakai akun ini
function jamTersedia(key, info) {
  if (!info) return [];
  return SLOT_JAM.filter(j => {
    if (key === SERVER_TODAY && startMinutes(j.mulai) <= SERVER_NOW_MIN) return false;
    if ((info.saya || []).includes(j.value)) return false;
    return true;
  });
}
function bisaDipilih(key, info) {
  if (!info || key < SERVER_TODAY) return false;
  if (info.status === 'tutup' || info.status === 'penuh') return false;
  return jamTersedia(key, info).length > 0;
}

function updateDateUI() {
  const heading = document.getElementById('step2-date-heading');
  const summary = document.getElementById('summary-date-val');
  if (!selectedDate) {
    document.getElementById('input_tanggal').value = '';
    heading.textContent = 'Pilih Tanggal Sesi';
    summary.textContent = 'Pilih Tanggal';
    return;
  }
  document.getElementById('input_tanggal').value = formatDateString(selectedDate);
  heading.textContent = formatIndoDate(selectedDate, false);
  summary.textContent = formatIndoDate(selectedDate, true);
}

async function renderCalendar() {
  const titleEl = document.getElementById('cal-month-title');
  const navLabelEl = document.getElementById('cal-nav-label');
  const gridContainer = document.getElementById('cal-grid-container');

  titleEl.textContent = `${monthNames[currentMonth]} ${currentYear}`;
  navLabelEl.textContent = `${monthNames[currentMonth].substring(0, 3)} ${currentYear}`;

  const data = await loadMonth(currentYear, currentMonth);
  calendarReady = !!data;

  gridContainer.innerHTML = `
    <div class="cal-day-head">Min</div><div class="cal-day-head">Sen</div><div class="cal-day-head">Sel</div>
    <div class="cal-day-head">Rab</div><div class="cal-day-head">Kam</div><div class="cal-day-head">Jum</div>
    <div class="cal-day-head">Sab</div>
  `;

  if (!data) {
    const err = document.createElement('div');
    err.className = 'cal-error';
    err.textContent = 'Ketersediaan jadwal gagal dimuat. Muat ulang halaman, lalu coba lagi.';
    gridContainer.appendChild(err);
    return;
  }

  const firstDayIndex = new Date(currentYear, currentMonth, 1).getDay();
  const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
  const prevDaysInMonth = new Date(currentYear, currentMonth, 0).getDate();

  for (let i = firstDayIndex - 1; i >= 0; i--) {
    const cell = document.createElement('div');
    cell.className = 'cal-day cal-empty';
    cell.textContent = prevDaysInMonth - i;
    gridContainer.appendChild(cell);
  }

  for (let day = 1; day <= daysInMonth; day++) {
    const cell = document.createElement('div');
    const key = `${currentYear}-${pad2(currentMonth + 1)}-${pad2(day)}`;
    const info = data[key];
    const isPast = key < SERVER_TODAY;
    const isSelected = selectedDate && formatDateString(selectedDate) === key;

    if (isPast) {
      cell.className = 'cal-day cal-empty';
      cell.textContent = day;
    } else if (info && info.status === 'tutup') {
      cell.className = 'cal-day cal-blocked cal-closed';
      cell.title = 'Studio tutup pada tanggal ini';
      cell.innerHTML = `${day} <small>Tutup</small>`;
    } else if (info && (info.status === 'penuh' || semuaJamLewat(key))) {
      cell.className = 'cal-day cal-blocked cal-full';
      cell.title = semuaJamLewat(key) ? 'Semua jam hari ini sudah lewat' : 'Slot penuh';
      cell.innerHTML = `${day} <i class="dot dot-red"></i><small>${semuaJamLewat(key) ? 'Lewat' : 'Penuh'}</small>`;
    } else if (info && jamTersedia(key, info).length === 0) {
      cell.className = 'cal-day cal-blocked cal-mine';
      cell.title = 'Semua jam di tanggal ini sudah Anda pesan';
      cell.innerHTML = `${day} <small>Dipesan</small>`;
    } else {
      cell.className = 'cal-day';
      if (isSelected) {
        cell.classList.add('cal-active');
        cell.innerHTML = `${day} <span style="font-size:0.6rem; color:var(--accent); font-weight:700; margin-top:2px;">Terpilih</span>`;
      } else {
        const dot = info && info.status === 'terbatas' ? 'dot-amber' : 'dot-green';
        cell.title = info ? `Sisa ${info.sisa} slot` : '';
        cell.innerHTML = `${day} <i class="dot ${dot}"></i>`;
      }
      cell.addEventListener('click', () => {
        selectedDate = new Date(currentYear, currentMonth, day);
        updateDateUI();
        renderTimeSlots();
        renderCalendar();
      });
    }
    gridContainer.appendChild(cell);
  }
}

// Daftar jam sesi untuk tanggal terpilih
function renderTimeSlots() {
  const list = document.getElementById('time-slot-list');
  const note = document.getElementById('slot-day-note');
  const statusEl = document.getElementById('summary-status-val');
  const timeEl = document.getElementById('summary-time-val');
  list.innerHTML = '';

  if (!selectedDate) {
    note.textContent = 'Pilih tanggal yang tersedia pada kalender untuk melihat jam sesi.';
    statusEl.textContent = 'Belum ada tanggal dipilih';
    timeEl.textContent = '-';
    return;
  }

  const key = formatDateString(selectedDate);
  const data = monthCache[`${selectedDate.getFullYear()}-${pad2(selectedDate.getMonth() + 1)}`] || {};
  const info = data[key];
  if (!info) { note.textContent = 'Data tanggal tidak ditemukan.'; return; }

  note.textContent = `Sisa ${info.sisa} dari ${info.kuota} slot pada tanggal ini.`;
  const tagCls = info.status === 'terbatas' ? 'tag-amber' : 'tag-green';
  const dotCls = info.status === 'terbatas' ? 'dot-amber' : 'dot-green';
  let firstChecked = false;

  SLOT_JAM.forEach(j => {
    const lewat = key === SERVER_TODAY && startMinutes(j.mulai) <= SERVER_NOW_MIN;
    const milikSaya = (info.saya || []).includes(j.value);
    const disabled = lewat || milikSaya;

    const label = document.createElement('label');
    label.className = 'time-slot' + (disabled ? ' slot-disabled' : '');

    let tag, desc = j.desc;
    if (milikSaya)   { tag = `<span class="slot-tag tag-red"><i class="dot dot-red"></i> Sudah Anda pesan</span>`; desc = 'Akun ini sudah punya reservasi di tanggal dan jam yang sama.'; }
    else if (lewat)  { tag = `<span class="slot-tag tag-red"><i class="dot dot-red"></i> Sudah lewat</span>`; desc = 'Jam sesi ini sudah terlewat untuk hari ini.'; }
    else             { tag = `<span class="slot-tag ${tagCls}"><i class="dot ${dotCls}"></i> Sisa ${info.sisa} Slot</span>`; }

    const checked = !disabled && !firstChecked;
    if (checked) firstChecked = true;

    label.innerHTML = `
      <input type="radio" name="waktu_sesi" value="${j.value}" class="slot-radio" ${disabled ? 'disabled' : ''} ${checked ? 'checked' : ''}>
      <div class="slot-info">
        <div class="slot-time-row"><h4 class="slot-time">${j.label}</h4>${tag}</div>
        <p class="slot-desc">${desc}</p>
      </div>
      ${disabled ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--ink-faint)" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>' : ''}
    `;
    list.appendChild(label);
  });

  list.querySelectorAll('input[name="waktu_sesi"]').forEach(radio => {
    radio.addEventListener('change', syncSummarySlot);
  });
  syncSummarySlot();
}

function syncSummarySlot() {
  const checked = document.querySelector('input[name="waktu_sesi"]:checked');
  const timeEl = document.getElementById('summary-time-val');
  const statusEl = document.getElementById('summary-status-val');
  if (!checked) {
    timeEl.textContent = '-';
    statusEl.textContent = 'Tidak ada jam tersedia';
    return;
  }
  const j = SLOT_JAM.find(x => x.value === checked.value);
  timeEl.textContent = j ? j.label : checked.value;
  const key = formatDateString(selectedDate);
  const data = monthCache[`${selectedDate.getFullYear()}-${pad2(selectedDate.getMonth() + 1)}`] || {};
  const info = data[key];
  statusEl.textContent = info ? `Sisa ${info.sisa} slot • Kunci 15 Menit` : 'Tersedia • Kunci 15 Menit';
}

// Navigasi bulan
document.getElementById('cal-prev-btn').addEventListener('click', () => {
  if (currentYear * 12 + currentMonth > todayParts[0] * 12 + (todayParts[1] - 1)) {
    currentMonth--;
    if (currentMonth < 0) { currentMonth = 11; currentYear--; }
    renderCalendar();
  }
});
document.getElementById('cal-next-btn').addEventListener('click', () => {
  currentMonth++;
  if (currentMonth > 11) { currentMonth = 0; currentYear++; }
  renderCalendar();
});

// Cari tanggal pertama yang bisa dipilih (mulai 3 hari dari sekarang, lalu maju sampai ketemu)
async function pilihTanggalAwal() {
  const start = new Date(todayParts[0], todayParts[1] - 1, todayParts[2] + 3);
  let y = start.getFullYear(), m = start.getMonth(), d = start.getDate();
  for (let step = 0; step < 6; step++) {
    const data = await loadMonth(y, m);
    if (!data) return false;
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    for (let day = (step === 0 ? d : 1); day <= daysInMonth; day++) {
      const key = `${y}-${pad2(m + 1)}-${pad2(day)}`;
      if (bisaDipilih(key, data[key])) {
        selectedDate = new Date(y, m, day);
        currentYear = y; currentMonth = m;
        return true;
      }
    }
    m++; if (m > 11) { m = 0; y++; }
  }
  return false;
}

// =========================================
// HITUNG ADD-ON & HARGA SECARA REAL-TIME
// =========================================
function updateAddons() {
  let addonsTotal = 0;
  let selectedAddons = [];
  let breakdownHtml = '';

  document.querySelectorAll('.addon-cb:checked').forEach(cb => {
    const price = parseInt(cb.dataset.price, 10) || 0;
    const name = cb.dataset.name;
    addonsTotal += price;
    selectedAddons.push(`${name} (+Rp ${formatRupiah(price)})`);
    breakdownHtml += `
      <div class="row" style="color:var(--accent); font-weight:600;">
        <span>+ ${name}</span>
        <span>Rp ${formatRupiah(price)}</span>
      </div>
    `;
  });

  const grandTotal = basePrice + addonsTotal;
  const dpVal = Math.round(grandTotal * 0.3);
  const sisaVal = grandTotal - dpVal;

  document.getElementById('input_total_harga').value = grandTotal;
  document.getElementById('input_addons_total').value = addonsTotal;
  document.getElementById('input_addons_list').value = selectedAddons.join(', ');

  document.getElementById('breakdown-addons').innerHTML = breakdownHtml;
  document.getElementById('summary-total-val').textContent = 'Rp ' + formatRupiah(grandTotal);
  document.getElementById('summary-dp-val').textContent = 'Rp ' + formatRupiah(dpVal);
  document.getElementById('summary-sisa-note').textContent = `Sisa pelunasan (Rp ${formatRupiah(sisaVal)}) diselesaikan saat tiba di studio.`;
}

document.querySelectorAll('.addon-cb').forEach(cb => {
  cb.addEventListener('change', updateAddons);
});

// =========================================
// CEK ULANG KE SERVER SEBELUM CHECKOUT
// (kalau admin baru menutup tanggal / slot baru penuh, klien diberi tahu)
// =========================================
document.getElementById('bookingForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const btn = form.querySelector('.btn-checkout');
  const radio = document.querySelector('input[name="waktu_sesi"]:checked');

  if (!calendarReady || !selectedDate || !radio) {
    alert('Pilih tanggal dan jam sesi yang tersedia terlebih dahulu.');
    return;
  }

  btn.disabled = true;
  const key = formatDateString(selectedDate);
  const data = await loadMonth(selectedDate.getFullYear(), selectedDate.getMonth(), true);
  const info = data ? data[key] : null;
  const jamOk = info && jamTersedia(key, info).some(j => j.value === radio.value);

  if (!data || !info || !bisaDipilih(key, info) || !jamOk) {
    btn.disabled = false;
    alert(data
      ? 'Maaf, tanggal atau jam yang Anda pilih baru saja tidak tersedia. Silakan pilih jadwal lain.'
      : 'Ketersediaan jadwal gagal dimuat. Periksa koneksi Anda lalu coba lagi.');
    if (data) {
      if (!bisaDipilih(key, info)) { selectedDate = null; await pilihTanggalAwal(); updateDateUI(); }
      await renderCalendar();
      renderTimeSlots();
    }
    return;
  }
  form.submit();
});

// Jalankan saat pertama kali dimuat
(async function init() {
  updateAddons();
  const ok = await pilihTanggalAwal();
  if (!ok) selectedDate = null;
  updateDateUI();
  await renderCalendar();
  renderTimeSlots();
})();
</script>

<!-- ================= FOOTER ================= -->
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

</body>
</html>