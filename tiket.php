<?php
session_start();
require 'koneksi.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$nama_user = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Klien';

$pesanan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($pesanan_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM pesanan WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $pesanan_id, $user_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM pesanan WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$pesanan = $stmt->get_result()->fetch_assoc();
$stmt->close();

$has_reservasi = ($pesanan !== null);

function rp($n) {
    return 'Rp ' . number_format((int)$n, 0, ',', '.');
}

if ($has_reservasi) {
    // Tanggal dalam Bahasa Indonesia
    $hari  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $ts = strtotime($pesanan['tanggal_sesi']);
    $tanggal_id = $hari[(int)date('w', $ts)] . ', ' . date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);

    // Slot (Pagi/Siang/Sore/Malam) & durasi dari jam sesi, mis. "09.00 - 12.00 WIB"
    $slot_label = 'Sesi Terjadwal Resmi';
    $durasi = !empty($pesanan['durasi']) ? $pesanan['durasi'] : '';
    if (preg_match('/(\d{1,2})[.:](\d{2})\s*[-–—]\s*(\d{1,2})[.:](\d{2})/u', $pesanan['jam_sesi'], $m)) {
        $mulai = (int)$m[1];
        $slot_label = 'Slot Terjadwal ' . ($mulai < 11 ? 'Pagi' : ($mulai < 15 ? 'Siang' : ($mulai < 18 ? 'Sore' : 'Malam')));
        if ($durasi === '') {
            $menit = ((int)$m[3] * 60 + (int)$m[4]) - ((int)$m[1] * 60 + (int)$m[2]);
            if ($menit > 0) {
                $durasi = rtrim(rtrim(number_format($menit / 60, 1, ',', ''), '0'), ',') . ' Jam Sesi';
            }
        }
    }

    // Kode booking & ID tiket
    $kode_tampil = ($pesanan['kode_reservasi'] !== '' && $pesanan['kode_reservasi'][0] === '#') ? $pesanan['kode_reservasi'] : '#' . $pesanan['kode_reservasi'];
    $booking_id  = 'CP-' . str_pad($pesanan['id'], 6, '0', STR_PAD_LEFT) . '-PKU';

    // Rincian pembayaran
    $total_harga = (int)$pesanan['total_harga'];
    $dibayar     = (int)$pesanan['jumlah_dibayar'];
    $sisa        = max(0, (int)$pesanan['sisa_tagihan']);
    $is_lunas    = ($sisa === 0);
    $bayar_penuh = stripos((string)$pesanan['skema_pembayaran'], 'lunas') !== false;
    // DP: pakai kolom jumlah_dp bila ada, jika tidak 30% dari total
    $nominal_dp  = isset($pesanan['jumlah_dp']) ? (int)$pesanan['jumlah_dp'] : (int)round($total_harga * 0.30);
    // Bayar di tempat: tanpa DP & tanpa pembayaran online
    $is_tempat   = ($pesanan['metode_pembayaran'] === 'tempat');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lihat Tiket Reservasi - Creative Pixel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<style>
/* =========================================
   TIKET RESERVASI - BOARDING PASS (sesuai desain)
========================================= */
.bp-card {
  --bp-amber: #8A4B00;
  --bp-orange: #F29A3C;
  --bp-green: #0E9F6E;
  --bp-gray: #F2F2F2;
  font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
  background: #FFFFFF;
  border-radius: 24px;
  box-shadow: 0 18px 50px -14px rgba(0, 0, 0, 0.18);
  overflow: hidden;
  color: #1B1A17;
  margin-bottom: 24px;
}
.bp-card p { margin: 0; }

/* Header hitam */
.bp-head {
  background: #000;
  color: #fff;
  padding: 36px 40px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}
.bp-brand { display: flex; align-items: center; gap: 16px; }
.bp-brand-icon {
  width: 52px; height: 52px; border-radius: 14px;
  background: #1C1C1C; color: #fff;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.bp-brand-title { font-family: var(--font-serif, 'Fraunces', serif); font-size: 1.6rem; font-weight: 500; letter-spacing: 0.01em; }
.bp-brand-sub { font-size: 0.8rem; letter-spacing: 0.1em; text-transform: uppercase; color: rgba(255,255,255,0.8); margin-top: 4px !important; }
.bp-code { text-align: right; }
.bp-code-label { font-size: 0.8rem; letter-spacing: 0.12em; text-transform: uppercase; color: rgba(255,255,255,0.8); }
.bp-code-value { font-family: var(--font-serif, 'Fraunces', serif); font-size: 2.4rem; font-weight: 600; line-height: 1.1; color: var(--bp-orange); letter-spacing: 0.03em; }
.bp-pill {
  display: inline-block; margin-top: 8px; padding: 3px 12px; border-radius: 999px;
  font-size: 0.68rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;
}
.bp-pill-ok { background: rgba(35,133,81,0.25); color: #5ED995; }
.bp-pill-wait { background: rgba(217,130,43,0.25); color: #F8B475; }
.bp-pill-cash { background: rgba(255,255,255,0.14); color: #fff; margin-left: 6px; }
.bp-cash-status { display: inline-flex; align-items: center; gap: 6px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; padding: 5px 12px; border-radius: 999px; margin-bottom: 14px; }
.bp-cash-status.is-unpaid { background: #FFF0E6; color: #8A4B00; }
.bp-cash-status.is-paid { background: #DEF7EC; color: #03543F; }

/* Isi tiket */
.bp-body {
  display: grid;
  grid-template-columns: 1.75fr 1fr;
  gap: 40px;
  padding: 56px 40px 48px;
  align-items: start;
}
.bp-when { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 28px; }
.bp-label {
  display: flex; align-items: center; gap: 7px;
  font-size: 0.8rem; font-weight: 600; letter-spacing: 0.03em; text-transform: uppercase; color: #3A3935;
  margin-bottom: 8px !important;
}
.bp-label svg { color: var(--bp-amber); flex-shrink: 0; }
.bp-value { font-size: 1.6rem; font-weight: 600; line-height: 1.2; }
.bp-sub { font-size: 0.95rem; color: #55534E; margin-top: 8px !important; line-height: 1.5; }
.bp-sub-alert { color: var(--bp-amber); }

.bp-loc { background: var(--bp-gray); border-radius: 14px; padding: 20px 22px; margin-bottom: 28px; }
.bp-loc .bp-value { font-size: 1.35rem; margin-bottom: 4px; }
.bp-loc .bp-sub { margin-top: 4px !important; }

.bp-pkg-label { font-size: 0.8rem; font-weight: 600; letter-spacing: 0.03em; text-transform: uppercase; color: #55534E; }
.bp-pkg-name { font-family: var(--font-serif, 'Fraunces', serif); font-size: 2.4rem; font-weight: 400; line-height: 1.15; margin: 2px 0 6px !important; }
.bp-pkg .bp-sub { margin-top: 0 !important; }

.bp-addon { background: #FFF0E6; border-radius: 14px; padding: 18px 22px; margin-top: 28px; display: flex; gap: 12px; align-items: flex-start; }
.bp-addon svg { color: #7A3B10; flex-shrink: 0; margin-top: 3px; }
.bp-addon-title { font-size: 1.2rem; font-weight: 600; color: #2B1405; }
.bp-addon-text { font-size: 0.95rem; color: #4A3322; margin-top: 4px !important; line-height: 1.5; }

/* Kolom pembayaran */
.bp-pay { background: var(--bp-gray); border-radius: 22px; padding: 22px; }
.bp-pay-title { text-align: center; font-size: 0.85rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: #3A3935; margin-bottom: 18px !important; }
.bp-pay-inner { background: #fff; border-radius: 14px; padding: 18px; }
.bp-row { display: flex; justify-content: space-between; gap: 12px; font-size: 0.92rem; font-weight: 500; color: #2A2925; margin-bottom: 8px; }
.bp-row-green { color: var(--bp-green); }
.bp-row-total { align-items: center; margin: 14px 0 12px; font-size: 1.05rem; font-weight: 600; }
.bp-row-total strong { font-size: 1.2rem; font-weight: 700; color: var(--bp-amber); text-align: right; }
.bp-pay-note { font-size: 0.92rem; color: #55534E; line-height: 1.5; }
.bp-pay-meta { font-size: 0.75rem; color: #8A877F; margin-top: 10px !important; }

.bp-qr { display: flex; align-items: center; gap: 16px; margin-top: 16px; padding: 16px; border: 1px dashed #D5D2CA; border-radius: 16px; }
.bp-qr img { width: 92px; height: 92px; border-radius: 8px; border: 1px solid #E6E3DB; flex-shrink: 0; }
.bp-qr strong { display: block; font-size: 0.95rem; }
.bp-qr span { display: block; font-size: 0.8rem; color: #55534E; margin-top: 3px; line-height: 1.4; }

/* Footer abu-abu */
.bp-foot {
  background: #E8E8E8; padding: 15px 32px;
  display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;
  font-size: 0.8rem; font-weight: 600; letter-spacing: 0.03em; text-transform: uppercase; color: #3A3935;
}
.bp-foot-left { display: flex; align-items: center; gap: 10px; }

.bp-extra { display: grid; grid-template-columns: 1.55fr 1fr; gap: 28px; align-items: start; margin-top: 40px; }
.bp-extra .side-guide-card { margin-bottom: 0; }
.ticket-actions-row { margin-top: 4px; }

/* Tombol Aksi Tiket */
.ticket-actions-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}
.btn-print {
  background: var(--dark);
  color: #FFFFFF;
  padding: 12px 22px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.85rem;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: opacity 0.15s ease;
}
.btn-print:hover {
  opacity: 0.9;
}
.btn-outline-action {
  border: 1px solid var(--line);
  background: #FFFFFF;
  color: var(--ink);
  padding: 12px 20px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.85rem;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: background 0.15s ease;
}
.btn-outline-action:hover {
  background: #FAF9F6;
}

/* Sidebar Panduan & Bantuan */
.side-guide-card {
  background: #FFFFFF;
  border: 1px solid var(--line);
  border-radius: var(--radius-lg);
  padding: 28px;
  box-shadow: 0 4px 20px -8px rgba(27, 26, 23, 0.04);
  margin-bottom: 24px;
}
.side-guide-card h3 {
  font-family: var(--font-serif);
  font-size: 1.25rem;
  font-weight: 500;
  color: var(--ink);
  margin: 0 0 16px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.guide-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.guide-item {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}
.guide-number {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: #F4E7DC;
  color: var(--accent);
  font-size: 0.75rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 2px;
}
.guide-content h4 {
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--ink);
  margin: 0 0 4px;
}
.guide-content p {
  font-size: 0.78rem;
  color: var(--ink-soft);
  margin: 0;
  line-height: 1.45;
}

/* Banner Bantuan & Perubahan Jadwal */
.bp-help {
  font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
  background: #EDEDEB;
  border-radius: 24px;
  padding: 36px 32px 36px 40px;
  display: flex;
  justify-content: space-between;
  align-items: stretch;
  gap: 40px;
}
.bp-help-text { max-width: 640px; }
.bp-help-eyebrow { margin: 0 0 8px; font-size: 0.8rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: #8A4B00; }
.bp-help-title { margin: 0 0 14px; font-family: var(--font-serif, 'Fraunces', serif); font-size: 1.85rem; font-weight: 500; line-height: 1.2; color: #1B1A17; }
.bp-help-desc { margin: 0; font-size: 1rem; line-height: 1.7; color: #55534E; }
.bp-help-actions { display: flex; flex-direction: column; justify-content: space-between; gap: 16px; width: 260px; flex-shrink: 0; }
.bp-help-btn {
  display: flex; align-items: center; justify-content: center; gap: 10px;
  padding: 16px 20px; border-radius: 12px;
  font-size: 1rem; font-weight: 500; text-decoration: none;
  transition: opacity 0.15s ease;
}
.bp-help-btn:hover { opacity: 0.88; }
.bp-help-btn:focus-visible { outline: 2px solid #8A4B00; outline-offset: 2px; }
.bp-help-btn-light { background: #FFFFFF; color: #1B1A17; }
.bp-help-btn-dark { background: #000000; color: #FFFFFF; }

/* Empty State */
.empty-ticket-card {
  background: #FFFFFF;
  border: 1px solid var(--line);
  border-radius: var(--radius-lg);
  padding: 64px 32px;
  text-align: center;
  max-width: 680px;
  margin: 0 auto;
}
.empty-icon-wrap {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: #F4E7DC;
  color: var(--accent);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
}
.empty-ticket-card h2 {
  font-family: var(--font-serif);
  font-size: 2rem;
  color: var(--ink);
  margin: 0 0 10px;
}
.empty-ticket-card p {
  font-size: 0.92rem;
  color: var(--ink-soft);
  max-width: 480px;
  margin: 0 auto 28px;
  line-height: 1.5;
}

/* Print Styles */
@media print {
  .site-header, .site-footer, .bp-extra, .bp-help, .ticket-actions-row, .page-head { display: none !important; }
  .page-wrapper { padding: 0; }
  .bp-card { box-shadow: none; border: 1px solid #000; }
  .bp-head, .bp-foot, .bp-addon, .bp-loc, .bp-pay { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}

@media (max-width: 960px) {
  .bp-body, .bp-extra { grid-template-columns: 1fr; }
  .bp-body { padding: 36px 24px 32px; gap: 32px; }
  .bp-head { padding: 28px 24px; }
  .bp-code { text-align: left; }
  .bp-when { grid-template-columns: 1fr; gap: 22px; }
  .bp-value { font-size: 1.4rem; }
  .bp-pkg-name { font-size: 2rem; }
  .bp-foot { padding: 14px 24px; }
  .bp-help { flex-direction: column; padding: 28px 24px; gap: 24px; }
  .bp-help-actions { width: 100%; }
  .bp-help-title { font-size: 1.5rem; }
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
      <a href="tiket.php" class="nav-pill-active">Lihat Tiket Reservasi</a>
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

<main class="container page-wrapper">
    <div class="page-head">
        <span class="badge-soft eyebrow"><i class="dot dot-green"></i> PORTAL KLIEN • E-TIKET RESMI</span>
        <h1>Tiket Masuk &amp; Boarding Pass Studio</h1>
        <p>Tunjukkan e-tiket ini kepada tim receptionist studio kami saat tiba untuk verifikasi sesi foto editorial Anda.</p>
    </div>

    <?php if ($has_reservasi): ?>
        <!-- KARTU BOARDING PASS -->
        <div class="bp-card">

            <!-- HEADER -->
            <div class="bp-head">
                <div class="bp-brand">
                    <div class="bp-brand-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m14.31 8 5.74 9.94M9.69 8h11.48M7.38 12l5.74-9.94M9.69 16 3.95 6.06M14.31 16H2.83m13.79-4-5.74 9.94"></path></svg>
                    </div>
                    <div>
                        <p class="bp-brand-title">CREATIVE PIXEL</p>
                        <p class="bp-brand-sub">Official Studio Boarding Pass</p>
                    </div>
                </div>
                <div class="bp-code">
                    <p class="bp-code-label">Kode Booking Resmi</p>
                    <p class="bp-code-value"><?= htmlspecialchars($kode_tampil); ?></p>
                    <span class="bp-pill <?= $pesanan['status'] === 'Dikonfirmasi' ? 'bp-pill-ok' : 'bp-pill-wait'; ?>"><?= htmlspecialchars($pesanan['status']); ?></span>
                    <?php if ($is_tempat): ?>
                        <span class="bp-pill bp-pill-cash"><?= $is_lunas ? 'Lunas di Kasir' : 'Bayar di Tempat'; ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ISI -->
            <div class="bp-body">

                <!-- KIRI -->
                <div>
                    <div class="bp-when">
                        <div>
                            <p class="bp-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                Tanggal Sesi
                            </p>
                            <p class="bp-value"><?= htmlspecialchars($tanggal_id); ?></p>
                            <p class="bp-sub"><?= htmlspecialchars($slot_label); ?></p>
                        </div>
                        <div>
                            <p class="bp-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                Waktu Sesi Foto
                            </p>
                            <p class="bp-value"><?= htmlspecialchars($pesanan['jam_sesi']); ?></p>
                            <p class="bp-sub bp-sub-alert">Harap hadir 15 menit lebih awal</p>
                        </div>
                    </div>

                    <div class="bp-loc">
                        <p class="bp-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            Lokasi Studio &amp; Suite
                        </p>
                        <p class="bp-value"><?= htmlspecialchars($pesanan['lokasi_studio']); ?></p>
                        <p class="bp-sub">Jl. Marpoyan No. 42, Bukit Raya, Pekanbaru, Riau • Ruang Daylight &amp; Editorial Dark</p>
                    </div>

                    <div class="bp-pkg">
                        <p class="bp-pkg-label">Paket Terpilih</p>
                        <p class="bp-pkg-name"><?= htmlspecialchars($pesanan['paket_layanan']); ?></p>
                        <?php if ($durasi !== ''): ?>
                        <p class="bp-sub">Durasi: <?= htmlspecialchars($durasi); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($pesanan['catatan'])): ?>
                    <div class="bp-addon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
                        <div>
                            <p class="bp-addon-title">Layanan Tambahan &amp; Catatan Khusus</p>
                            <p class="bp-addon-text"><?= nl2br(htmlspecialchars($pesanan['catatan'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- KANAN: PEMBAYARAN -->
                <div>
                    <div class="bp-pay">
                        <?php if ($is_tempat): ?>
                        <p class="bp-pay-title">Pembayaran di Tempat</p>
                        <div class="bp-pay-inner">
                            <?php if ($is_lunas): ?>
                                <span class="bp-cash-status is-paid">✓ Lunas di Kasir</span>
                            <?php else: ?>
                                <span class="bp-cash-status is-unpaid">Bayar di Tempat</span>
                            <?php endif; ?>

                            <div class="bp-row"><span>Total Biaya Paket:</span><span><?= rp($total_harga); ?></span></div>
                            <div class="bp-row"><span>Pembayaran Online:</span><span>Tidak diperlukan</span></div>

                            <div class="bp-row bp-row-total">
                                <span><?= $is_lunas ? 'Sudah Dibayar di Kasir:' : 'Dibayar di Kasir Studio:'; ?></span>
                                <strong><?= rp($is_lunas ? $total_harga : $sisa); ?></strong>
                            </div>

                            <p class="bp-pay-note">
                                <?php if ($is_lunas): ?>
                                    Pembayaran sudah diterima di kasir studio. Tidak ada tagihan tersisa.
                                <?php else: ?>
                                    Tidak perlu bayar online. Cukup tunjukkan e-tiket ini, lalu bayar seluruh total di kasir studio via tunai, QRIS, atau kartu debit/kredit.
                                <?php endif; ?>
                            </p>
                            <p class="bp-pay-meta">Metode: BAYAR DI TEMPAT</p>
                        </div>
                        <?php else: ?>
                        <p class="bp-pay-title">Pembayaran Uang Muka</p>
                        <div class="bp-pay-inner">
                            <div class="bp-row"><span>Total Biaya Paket:</span><span><?= rp($total_harga); ?></span></div>

                            <?php if ($is_lunas && $bayar_penuh): ?>
                                <div class="bp-row bp-row-green"><span>Pembayaran Lunas :</span><span>- <?= rp($total_harga); ?></span></div>
                            <?php elseif ($is_lunas): ?>
                                <div class="bp-row bp-row-green"><span>DP Terverifikasi :</span><span>- <?= rp($nominal_dp); ?></span></div>
                                <div class="bp-row bp-row-green"><span>Pembayaran sisa :</span><span>- <?= rp($total_harga - $nominal_dp); ?></span></div>
                            <?php elseif ($dibayar <= 0): ?>
                                <div class="bp-row"><span>Pembayaran Online :</span><span>Belum ada</span></div>
                            <?php else: ?>
                                <div class="bp-row bp-row-green"><span>DP Terverifikasi :</span><span>- <?= rp($dibayar); ?></span></div>
                            <?php endif; ?>

                            <div class="bp-row bp-row-total">
                                <span>Sisa Tagihan di Lokasi:</span>
                                <strong><?= rp($sisa); ?></strong>
                            </div>

                            <p class="bp-pay-note">
                                <?php if ($is_lunas): ?>
                                    Pembayaran lunas. Tidak ada tagihan tersisa.
                                <?php else: ?>
                                    Dapat diselesaikan via QRIS, Kartu Kredit, atau Debit saat wrap-up sesi.
                                <?php endif; ?>
                            </p>
                            <p class="bp-pay-meta">Metode: <?= strtoupper(htmlspecialchars($pesanan['metode_pembayaran'])) . ' (' . strtoupper(htmlspecialchars($pesanan['skema_pembayaran'])) . ')'; ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="bp-foot">
                <span class="bp-foot-left">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    E-Tiket Terenkripsi • Booking ID: <?= htmlspecialchars($booking_id); ?>
                </span>
                <span>Creative Pixel Studio Marpoyan</span>
            </div>
        </div>

        <!-- BANNER BANTUAN & PERUBAHAN JADWAL -->
        <div class="bp-help">
            <div class="bp-help-text">
                <p class="bp-help-eyebrow">Bantuan &amp; Perubahan Jadwal</p>
                <h3 class="bp-help-title">Butuh Konsultasi Moodboard Tambahan?</h3>
                <p class="bp-help-desc">Tim concierge kami siap membantu penyesuaian warna busana, panduan makeup, atau rute parkir Marpoyan setiap hari pukul 09.00 - 21.00 WIB.</p>
            </div>
            <div class="bp-help-actions">
                <a href="index.php" class="bp-help-btn bp-help-btn-light">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"></path><path d="m12 19-7-7 7-7"></path></svg>
                    Kembali ke Etalase
                </a>
                <a href="https://wa.me/6282133614138?text=Halo%20Creative%20Pixel,%20saya%20ingin%20konfirmasi%20reservasi%20dengan%20kode%20<?= urlencode($pesanan['kode_reservasi']); ?>" target="_blank" rel="noopener" class="bp-help-btn bp-help-btn-dark">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Zm0 0a9 9 0 1 1 18 0m0 0v5a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3Z"></path><path d="M21 16v2a4 4 0 0 1-4 4h-5"></path></svg>
                    WhatsApp Concierge
                </a>
            </div>
        </div>

    <?php else: ?>
        
        <!-- TAMPILAN KOSONG -->
        <div class="empty-ticket-card">
            <div class="empty-icon-wrap">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </div>
            <h2>Belum Ada Tiket Reservasi Aktif</h2>
            <p>Anda belum memiliki pemesanan sesi studio yang tercatat. Silakan jelajahi koleksi paket fotografi editorial kami dan amankan slot jadwal pilihan Anda.</p>
            <a href="index.php" class="btn btn-dark" style="display:inline-flex; align-items:center; gap:8px;">
                Lihat Etalase Paket ➔
            </a>
        </div>

    <?php endif; ?>

</main>

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