<?php
session_start();
require 'koneksi.php';

// Pengecekan sesi login
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

if (!$pesanan) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reservasi Berhasil - Creative Pixel</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<style>
/* =========================================
   GAYA KHUSUS HALAMAN SUKSES & E-TIKET
========================================= */
body { background-color: #FAFCFD; }

/* Global Header */
.site-header { border-bottom: 1px solid var(--line); background: #fff; position: sticky; top: 0; z-index: 40; }
.header-inner { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding-top: 18px; padding-bottom: 18px; }
.main-nav { display: flex; gap: 28px; font-size: 0.9rem; color: var(--ink-soft); align-items: center; }
.main-nav a.nav-pill-active { background: var(--dark); color: #fff !important; padding: 8px 18px; border-radius: 999px; font-weight: 500; }
.header-actions { display: flex; align-items: center; gap: 18px; }

/* Main Container */
.success-container { max-width: 860px; margin: 60px auto 100px; padding: 0 20px; display: flex; flex-direction: column; align-items: center; gap: 40px; }

/* Success Header Section */
.success-header { text-align: center; display: flex; flex-direction: column; align-items: center; gap: 16px; }
.sh-icon { width: 64px; height: 64px; border-radius: 50%; background: #FFF9F2; color: #D9822B; display: flex; align-items: center; justify-content: center; font-size: 24px; border: 4px solid #F6E2CD; margin-bottom: 8px; }
.sh-badge { background: #E8F5E9; color: #2E7D32; font-size: 0.7rem; font-weight: 700; padding: 6px 16px; border-radius: 999px; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #C8E6C9; letter-spacing: 0.05em; }
.sh-title { font-family: var(--font-serif); font-size: 2.6rem; color: var(--ink); line-height: 1.15; margin: 0; font-weight: 500; }
.sh-desc { font-size: 0.95rem; color: var(--ink-soft); max-width: 600px; line-height: 1.5; margin: 0; }
.btn-wa { background: #00A859; color: #fff; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-top: 12px; transition: 0.2s; }
.btn-wa:hover { background: #008C4A; }

/* Ticket Card Styling (Mirip dengan tiket.php) */
.ticket-card { width: 100%; background: #fff; border: 1px solid var(--line); border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05); text-align: left; }
.ticket-header { background: #0A0A0A; color: #fff; padding: 20px 28px; display: flex; justify-content: space-between; align-items: center; }
.th-left { display: flex; align-items: center; gap: 12px; }
.th-logo-icon { width: 32px; height: 32px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; }
.th-brand { font-family: var(--font-serif); font-size: 1.1rem; font-weight: 500; letter-spacing: 1px; margin: 0 0 2px; }
.th-sub { font-size: 0.65rem; color: rgba(255,255,255,0.6); letter-spacing: 0.1em; text-transform: uppercase; margin: 0; }
.th-right { text-align: right; }
.th-code-label { font-size: 0.65rem; color: rgba(255,255,255,0.6); letter-spacing: 0.1em; text-transform: uppercase; margin: 0 0 4px; }
.th-code { font-family: monospace; font-size: 1.4rem; font-weight: 700; color: #E8C17A; margin: 0 0 8px; letter-spacing: 1px; }
.badge-status { display: inline-block; font-size: 0.7rem; font-weight: 600; padding: 4px 12px; border-radius: 999px; }
.badge-confirmed { background: #00A859; color: #fff; }

.ticket-body { display: flex; }
.ticket-main { flex: 1; padding: 32px; border-right: 2px dashed #EAEAEA; position: relative; }
.ticket-main::after, .ticket-main::before { content: ""; position: absolute; right: -10px; width: 20px; height: 20px; background: #FAFCFD; border-radius: 50%; border: 1px solid var(--line); }
.ticket-main::before { top: -11px; border-top-color: transparent; border-left-color: transparent; transform: rotate(45deg); }
.ticket-main::after { bottom: -11px; border-bottom-color: transparent; border-right-color: transparent; transform: rotate(45deg); }
.ticket-side { width: 260px; padding: 32px 24px; background: #FAFAFA; display: flex; flex-direction: column; justify-content: center; }

/* Grid Details */
.t-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
.t-label { font-size: 0.65rem; font-weight: 700; color: var(--ink-faint); text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 6px; display: flex; align-items: center; gap: 6px; }
.t-value { font-size: 1.05rem; font-weight: 600; color: var(--ink); margin: 0 0 4px; }
.t-desc { font-size: 0.8rem; color: var(--ink-soft); margin: 0; }
.t-desc-alert { font-size: 0.75rem; color: #D9822B; font-weight: 600; margin: 0; }

.t-location-box { background: #F4F4F4; border-radius: 8px; padding: 16px; margin-bottom: 24px; }
.t-location-box .t-value { font-size: 0.95rem; }

.t-addon-box { background: #FFF9F2; border: 1px solid #F6E2CD; border-radius: 8px; padding: 16px; display: flex; gap: 12px; align-items: flex-start; }
.t-addon-box svg { color: #D9822B; flex-shrink: 0; }

/* Side Price Details */
.ts-title { font-size: 0.7rem; font-weight: 700; color: var(--ink-faint); text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 16px; text-align: center; border-bottom: 1px solid #EAEAEA; padding-bottom: 12px; }
.ts-row { display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 8px; color: var(--ink-soft); }
.ts-row-total { display: flex; flex-direction: column; gap: 6px; margin-top: 16px; padding-top: 16px; border-top: 1px dashed #CCC; }
.ts-row-total span:first-child { font-size: 0.85rem; font-weight: 600; color: var(--ink); }
.ts-row-total span:last-child { font-family: var(--font-serif); font-size: 1.3rem; font-weight: 600; color: #D9822B; }
.ts-note { font-size: 0.65rem; color: var(--ink-faint); margin-top: 12px; line-height: 1.4; }

.ticket-footer { background: #EAEAEA; padding: 12px 32px; font-size: 0.65rem; font-weight: 600; color: var(--ink-soft); display: flex; justify-content: space-between; text-transform: uppercase; letter-spacing: 0.05em; }

/* Help Box */
.help-box { width: 100%; background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 24px 32px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.02); text-align: left; }
.help-left h4 { font-size: 0.7rem; font-weight: 700; color: #D9822B; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 8px; }
.help-left h2 { font-family: var(--font-serif); font-size: 1.4rem; font-weight: 500; margin: 0 0 8px; color: var(--ink); }
.help-left p { font-size: 0.85rem; color: var(--ink-soft); margin: 0; max-width: 480px; line-height: 1.5; }
.help-actions { display: flex; flex-direction: column; gap: 10px; }
.btn-outline { border: 1px solid var(--line); background: #fff; color: var(--ink); padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; text-align: center; text-decoration: none; }
.btn-dark { border: none; background: #111; color: #fff; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; }

@media (max-width: 768px) {
    .ticket-body { flex-direction: column; }
    .ticket-main { border-right: none; border-bottom: 2px dashed #EAEAEA; }
    .ticket-main::before, .ticket-main::after { display: none; }
    .ticket-side { width: 100%; }
    .t-grid-2 { grid-template-columns: 1fr; }
    .help-box { flex-direction: column; align-items: flex-start; gap: 24px; }
    .help-actions { width: 100%; }
}
</style>
<!-- Responsive layout (harus paling akhir supaya menimpa CSS lama) -->
<link rel="stylesheet" href="responsive.css?v=<?= time(); ?>">
<script src="responsive.js?v=<?= time(); ?>" defer></script>
</head>
<body>

<?php
$is_pelunasan = (isset($_GET['tipe']) && $_GET['tipe'] === 'pelunasan') || ($pesanan['sisa_tagihan'] <= 0 && $pesanan['skema_pembayaran'] === 'lunas');
?>
<!-- HEADER -->
<header class="site-header">
  <div class="container header-inner">
    <div class="logo">CREATIVE<span>PIXEL</span></div>
    <nav class="main-nav">
      <a href="index.php">Etalase Paket</a>
      <a href="pemesanan.php">Cek Status Reservasi</a>
      <a href="tiket.php" class="nav-pill-active">Lihat Tiket Reservasi</a>
    </nav>
    <div class="header-actions">
      <a href="profil.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
          <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user); ?>&background=F3F1EC&color=1B1A17" alt="Avatar" style="width:36px; height:36px; border-radius:50%;">
          <div style="display:flex; flex-direction:column; line-height:1.25; font-size:0.82rem;">
            <strong style="color:var(--ink);"><?= htmlspecialchars($nama_user); ?></strong>
            <span style="color:var(--ink-faint);">Studio Account</span>
          </div>
      </a>
    </div>
  </div>
</header>

<main class="success-container">
    
    <!-- 1. SUCCESS HEADER TEXT -->
    <div class="success-header">
        <div class="sh-icon" style="<?= $is_pelunasan ? 'background:#E8F5E9; color:#2E7D32; border-color:#C8E6C9;' : ''; ?>">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
        <div class="sh-badge" style="<?= $is_pelunasan ? 'background:#E8F5E9; color:#2E7D32; border-color:#C8E6C9;' : ''; ?>">
            <i class="dot dot-green" style="margin:0;"></i> STATUS: <?= $is_pelunasan ? 'LUNAS 100%' : strtoupper(htmlspecialchars($pesanan['status'])); ?>
        </div>
        <h1 class="sh-title"><?= $is_pelunasan ? 'Pelunasan Berhasil!<br>Tagihan Reservasi Anda Lunas 100%' : 'Reservasi Berhasil!<br>Slot Anda Telah Terkunci'; ?></h1>
        <p class="sh-desc"><?= $is_pelunasan ? 'Terima kasih, <strong>' . htmlspecialchars($nama_user) . '</strong>. Seluruh sisa tagihan sebesar <span style="color:#00A859; font-weight:600;">Rp ' . number_format($pesanan['total_harga'], 0, ',', '.') . '</span> telah terverifikasi lunas 100%.' : 'Terima kasih, <strong>' . htmlspecialchars($nama_user) . '</strong>. Pembayaran DP sebesar <span style="color:#D9822B; font-weight:600;">Rp ' . number_format($pesanan['jumlah_dibayar'], 0, ',', '.') . '</span> telah diproses dengan status <strong>' . htmlspecialchars($pesanan['status']) . '</strong>.'; ?></p>
        
        <a href="tiket.php?id=<?= $pesanan['id']; ?>" class="btn-wa" style="background:#1B1A17;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            Lihat Tiket Reservasi Saya
        </a>
    </div>

    <!-- 2. TIKET BOARDING PASS -->
    <div class="ticket-card">
        <div class="ticket-header">
            <div class="th-left">
                <div class="th-logo-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="3"></circle></svg>
                </div>
                <div>
                    <h3 class="th-brand">CREATIVE PIXEL</h3>
                    <p class="th-sub">OFFICIAL STUDIO BOARDING PASS</p>
                </div>
            </div>
            <div class="th-right">
                <p class="th-code-label">KODE BOOKING RESMI</p>
                <p class="th-code"><?= htmlspecialchars($pesanan['kode_reservasi']); ?></p>
                <span class="badge-status badge-confirmed"><?= htmlspecialchars($pesanan['status']); ?></span>
            </div>
        </div>

        <div class="ticket-body">
            <div class="ticket-main">
                <div class="t-grid-2">
                    <div>
                        <p class="t-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> TANGGAL SESI</p>
                        <p class="t-value"><?= date('l, d F Y', strtotime($pesanan['tanggal_sesi'])); ?></p>
                        <p class="t-desc">Sesi Terjadwal</p>
                    </div>
                    <div>
                        <p class="t-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> WAKTU SESI FOTO</p>
                        <p class="t-value"><?= htmlspecialchars($pesanan['jam_sesi']); ?></p>
                        <p class="t-desc-alert">Harap hadir 15 menit lebih awal</p>
                    </div>
                </div>

                <div class="t-location-box">
                    <p class="t-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> LOKASI STUDIO</p>
                    <p class="t-value"><?= htmlspecialchars($pesanan['lokasi_studio']); ?></p>
                    <p class="t-desc" style="font-size:0.75rem;">Jl. Marpoyan No. 42, Bukit Raya, Pekanbaru, Riau</p>
                </div>

                <div style="margin-bottom:24px;">
                    <p class="t-label">PAKET TERPILIH</p>
                    <p class="t-value"><?= htmlspecialchars($pesanan['paket_layanan']); ?></p>
                    <p class="t-desc" style="font-size:0.75rem;">Nama Pasangan / Peserta: <?= htmlspecialchars($pesanan['nama_pasangan'] ?: '-'); ?></p>
                </div>
            </div>

            <div class="ticket-side">
                <p class="ts-title">RINCIAN BIAYA</p>
                <div class="ts-row"><span>Total Biaya Paket:</span> <span>Rp <?= number_format($pesanan['total_harga'], 0, ',', '.'); ?></span></div>
                <div class="ts-row" style="color:#00A859; font-weight:600;"><span>Jumlah Dibayar:</span> <span>- Rp <?= number_format($pesanan['jumlah_dibayar'], 0, ',', '.'); ?></span></div>
                <div class="ts-row-total">
                    <span>Sisa Tagihan:</span>
                    <span style="<?= $pesanan['sisa_tagihan'] <= 0 ? 'color:#00A859;' : ''; ?>">Rp <?= number_format($pesanan['sisa_tagihan'], 0, ',', '.'); ?> <?= $pesanan['sisa_tagihan'] <= 0 ? '(LUNAS)' : ''; ?></span>
                </div>
                <p class="ts-note"><?= $pesanan['sisa_tagihan'] <= 0 ? '✓ Seluruh tagihan reservasi lunas 100%. Anda siap melakukan sesi foto!' : 'Dapat diselesaikan via QRIS online atau tunai/EDC saat tiba di studio.'; ?></p>
            </div>
        </div>

        <div class="ticket-footer">
            <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg> E-TIKET TERENKRIPSI + LUMINA CLOUD RESIDENTIAL BOOKING ID: 884920-JKT</span>
            <span>Lumina Studio &amp; Lab Senopati</span>
        </div>
    </div>

    <!-- 3. BANTUAN BOX -->
    <div class="help-box">
        <div class="help-left">
            <h4>BANTUAN &amp; PERUBAHAN JADWAL</h4>
            <h2>Butuh Konsultasi Moodboard Tambahan?</h2>
            <p>Tim concierge kami siap membantu penyesuaian warna busana, panduan makeup, atau rute parkir Senopati setiap hari pukul 09.00 - 21.00 WIB.</p>
        </div>
        <div class="help-actions">
            <a href="index.php" class="btn-outline">← Kembali ke Etalase</a>
            <a href="#" class="btn-dark">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                WhatsApp Concierge
            </a>
        </div>
    </div>

</main>

<!-- FOOTER -->
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
    <p>© 2026 Creative Pixel (Creative Pixel Frame Studio). Hak C