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

// Ambil semua pesanan milik user ini
$stmt = $conn->prepare("SELECT * FROM pesanan WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$orders = [];
while ($row = $res->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cek Status Reservasi - Creative Pixel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<style>
/* =========================================
   GAYA KHUSUS HALAMAN CEK STATUS / INVOICE
========================================= */
.invoice-card { background: #fff; border: 1px solid var(--line); border-radius: var(--radius-lg); box-shadow: 0 4px 20px -8px rgba(0,0,0,0.04); padding: 36px; margin-bottom: 24px; }

/* Card Header */
.ic-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed var(--line); padding-bottom: 20px; margin-bottom: 24px; }
.status-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem; font-weight: 600; padding: 6px 14px; border-radius: 999px; }
.badge-green { color: var(--green); background: var(--green-bg); }
.badge-amber { color: #D9822B; background: #FFF9F2; border: 1px solid #F6E2CD; }
.badge-red { color: var(--red); background: var(--red-bg); }
.invoice-code { font-size: 0.75rem; font-weight: 700; color: var(--ink-faint); letter-spacing: 0.05em; font-family: monospace; }

/* Title Section */
.ic-title-section { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; gap: 24px; flex-wrap: wrap; }
.ic-eyebrow { font-size: 0.7rem; font-weight: 700; color: var(--accent); letter-spacing: 0.06em; margin: 0 0 8px; text-transform: uppercase; }
.ic-title { font-family: var(--font-serif); font-size: 1.8rem; font-weight: 500; color: var(--ink); margin: 0 0 8px; }
.ic-subtitle { font-size: 0.85rem; color: var(--ink-soft); margin: 0; }

/* Info Grid */
.ic-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
.info-box { background: #FAF9F6; border: 1px solid var(--line); border-radius: var(--radius-md); padding: 18px; display: flex; gap: 14px; align-items: flex-start; }
.info-box-icon { width: 40px; height: 40px; background: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--line); flex-shrink: 0; color: var(--ink); }
.info-box-text p { font-size: 0.65rem; font-weight: 700; color: var(--ink-faint); text-transform: uppercase; margin: 0 0 4px; }
.info-box-text h4 { font-size: 0.95rem; font-weight: 600; margin: 0 0 4px; color: var(--ink); }
.info-box-text span { font-size: 0.8rem; color: var(--ink-soft); }

/* Payment Details */
.payment-section { background: #FAF9F6; border: 1px solid var(--line); border-radius: var(--radius-md); padding: 24px; margin-bottom: 24px; }
.breakdown-row { display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 10px; color: var(--ink-soft); }
.breakdown-total { display: flex; justify-content: space-between; font-size: 1.05rem; font-weight: 700; color: var(--ink); border-top: 1px dashed var(--line); padding-top: 12px; margin-top: 12px; }

/* Action Footer */
.card-actions { display: flex; justify-content: flex-end; gap: 12px; align-items: center; }
.btn-dark { background: #1B1A17; color: #fff; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
.btn-outline { border: 1px solid var(--line); background: #fff; color: var(--ink); padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; text-decoration: none; }
.btn-dark:hover, .btn-outline:hover { opacity: 0.9; }

/* Empty State */
.empty-card { background: #fff; border: 1px solid var(--line); border-radius: var(--radius-lg); padding: 60px 40px; text-align: center; }
.empty-card h3 { font-family: var(--font-serif); font-size: 1.8rem; margin: 0 0 12px; color: var(--ink); font-weight: 500; }
.empty-card p { font-size: 0.9rem; color: var(--ink-soft); margin: 0 0 24px; }

@media (max-width: 768px) {
    .ic-info-grid { grid-template-columns: 1fr; }
    .ic-title-section { flex-direction: column; }
    .card-actions { flex-direction: column; width: 100%; }
    .card-actions a { width: 100%; text-align: center; justify-content: center; }
}
</style>
<!-- Responsive layout (harus paling akhir supaya menimpa CSS lama) -->
<link rel="stylesheet" href="responsive.css?v=<?= time(); ?>">
<script src="responsive.js?v=<?= time(); ?>" defer></script>
</head>
<body>

<!-- HEADER -->
<header class="site-header">
  <div class="container header-inner">
    <a href="index.php" class="logo">CREATIVE<span>PIXEL</span></a>
    <nav class="main-nav">
      <a href="index.php">Etalase Paket</a>
      <a href="pemesanan.php" class="nav-pill-active">Cek Status Reservasi</a>
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

<main class="container page-wrapper">
    <div class="page-head">
        <span class="badge-soft eyebrow"><i class="dot dot-amber" style="background:var(--accent);"></i> PORTAL KLIEN • STATUS REAL-TIME</span>
        <h1>Daftar &amp; Status Reservasi Anda</h1>
        <p>Pantau rincian penagihan, status konfirmasi kuota, dan akses e-tiket untuk sesi foto Anda.</p>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'lunas_sukses'): ?>
        <div style="background:#DEF7EC; color:#03543F; border:1px solid #BCF0DA; padding:14px 20px; border-radius:8px; margin-bottom:24px; font-weight:600; display:flex; align-items:center; gap:10px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            Pelunasan sisa tagihan berhasil! Status reservasi Anda kini resmi Lunas 100%.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'pelunasan_menunggu'): ?>
        <div style="background:#FFFBEB; color:#92400E; border:1px solid #FDE68A; padding:14px 20px; border-radius:8px; margin-bottom:24px; font-weight:600; display:flex; align-items:center; gap:10px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            Bukti pelunasan Anda sudah kami terima dan sedang diverifikasi admin studio. Status Lunas akan muncul setelah pembayaran terverifikasi.
        </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <div class="empty-card">
            <h3>Belum Ada Riwayat Reservasi</h3>
            <p>Anda belum memiliki pemesanan aktif. Silakan pilih paket fotografi di etalase kami untuk melakukan reservasi.</p>
            <a href="index.php" class="btn-dark">Lihat Etalase Paket ➔</a>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <?php 
                $badge_class = 'badge-amber';
                if ($order['status'] === 'Dikonfirmasi') {
                    $badge_class = 'badge-green';
                } elseif ($order['status'] === 'Dibatalkan') {
                    $badge_class = 'badge-red';
                }
                $sisa_nominal = (float)$order['sisa_tagihan'];
                $is_lunas = ($sisa_nominal <= 0);
                $st_pel = isset($order['status_pelunasan']) ? $order['status_pelunasan'] : null;
                $pel_menunggu = ($st_pel === 'Menunggu Verifikasi');
                $pel_ditolak  = ($st_pel === 'Ditolak');
                // Bayar di tempat: tanpa DP, tanpa pembayaran online
                $is_tempat = ($order['metode_pembayaran'] === 'tempat');
            ?>
            <div class="invoice-card">
                <div class="ic-header">
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <div class="status-badge <?= $badge_class; ?>">
                            <i class="dot" style="margin:0;"></i> STATUS: <?= strtoupper(htmlspecialchars($order['status'])); ?>
                        </div>
                        <?php if ($is_tempat): ?>
                            <?php if ($order['status'] !== 'Dibatalkan'): ?>
                                <?php if ($is_lunas): ?>
                                    <span style="display:inline-flex; align-items:center; gap:6px; font-size:0.75rem; font-weight:700; color:#03543F; background:#DEF7EC; border:1px solid #BCF0DA; padding:4px 12px; border-radius:999px;">
                                        ✓ LUNAS 100%
                                    </span>
                                <?php else: ?>
                                    <span style="display:inline-flex; align-items:center; gap:6px; font-size:0.75rem; font-weight:700; color:#B45309; background:#FEF3C7; border:1px solid #FDE68A; padding:4px 12px; border-radius:999px;">
                                        💵 BAYAR DI TEMPAT • Rp <?= number_format($sisa_nominal, 0, ',', '.'); ?>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php elseif ($order['status'] === 'Dikonfirmasi'): ?>
                            <?php if ($is_lunas): ?>
                                <span style="display:inline-flex; align-items:center; gap:6px; font-size:0.75rem; font-weight:700; color:#03543F; background:#DEF7EC; border:1px solid #BCF0DA; padding:4px 12px; border-radius:999px;">
                                    ✓ LUNAS 100%
                                </span>
                            <?php else: ?>
                                <span style="display:inline-flex; align-items:center; gap:6px; font-size:0.75rem; font-weight:700; color:#B45309; background:#FEF3C7; border:1px solid #FDE68A; padding:4px 12px; border-radius:999px;">
                                    💳 SISA TAGIHAN: Rp <?= number_format($sisa_nominal, 0, ',', '.'); ?><?= $pel_menunggu ? ' • PELUNASAN MENUNGGU VERIFIKASI' : ''; ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="invoice-code">
                        KODE: <?= htmlspecialchars($order['kode_reservasi']); ?>
                    </div>
                </div>

                <div class="ic-title-section">
                    <div>
                        <h2 class="ic-title"><?= htmlspecialchars($order['paket_layanan']); ?></h2>
                        <p class="ic-subtitle">
                            Lokasi: <?= htmlspecialchars($order['lokasi_studio']); ?>
                            <?= !empty($order['nama_pasangan']) ? ' • Pasangan/Peserta: ' . htmlspecialchars($order['nama_pasangan']) : ''; ?>
                        </p>
                    </div>
                </div>

                <div class="ic-info-grid">
                    <div class="info-box">
                        <div class="info-box-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        </div>
                        <div class="info-box-text">
                            <p>JADWAL SESI</p>
                            <h4><?= date('l, d F Y', strtotime($order['tanggal_sesi'])); ?></h4>
                            <span><?= htmlspecialchars($order['jam_sesi']); ?></span>
                        </div>
                    </div>
                    <div class="info-box">
                        <div class="info-box-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        </div>
                        <div class="info-box-text">
                            <p><?= $is_tempat ? 'METODE PEMBAYARAN' : 'METODE &amp; SKEMA'; ?></p>
                            <h4><?= $is_tempat ? 'BAYAR DI TEMPAT' : strtoupper(htmlspecialchars($order['skema_pembayaran'])) . ' (' . strtoupper(htmlspecialchars($order['metode_pembayaran'])) . ')'; ?></h4>
                            <span>Dibuat: <?= date('d M Y H:i', strtotime($order['created_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($order['catatan'])): ?>
                <div style="background:#FAF9F6; border:1px dashed var(--line); border-radius:var(--radius-sm); padding:14px 18px; margin-bottom:20px; font-size:0.83rem; color:var(--ink);">
                    <strong style="color:var(--accent); display:block; font-size:0.72rem; text-transform:uppercase; margin-bottom:4px;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px;"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                        Add-on Layanan &amp; Catatan Khusus
                    </strong>
                    <?= nl2br(htmlspecialchars($order['catatan'])); ?>
                </div>
                <?php endif; ?>

                <?php if ($is_tempat): ?>
                <div class="payment-section">
                    <div class="breakdown-row">
                        <span>Total Nilai Paket:</span>
                        <span>Rp <?= number_format($order['total_harga'], 0, ',', '.'); ?></span>
                    </div>
                    <div class="breakdown-row">
                        <span>Pembayaran Online:</span>
                        <span>Tidak diperlukan</span>
                    </div>
                    <div class="breakdown-total">
                        <span><?= $is_lunas ? 'Status Pembayaran:' : 'Dibayar di Kasir Studio:'; ?></span>
                        <span style="color:<?= $is_lunas ? 'var(--green)' : 'var(--accent)'; ?>;">
                            <?= $is_lunas ? 'Rp 0 (LUNAS 100%)' : 'Rp ' . number_format($sisa_nominal, 0, ',', '.'); ?>
                        </span>
                    </div>
                    <?php if (!$is_lunas && $order['status'] !== 'Dibatalkan'): ?>
                    <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:8px; padding:10px 14px; margin-top:14px; font-size:0.8rem; color:#92400E;">
                        <strong>Bayar di Tempat:</strong> Tidak perlu bayar online. Tunjukkan e-tiket kepada receptionist, lalu bayar seluruh total di kasir studio saat sesi foto via tunai, QRIS, atau kartu debit/kredit.
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="payment-section">
                    <div class="breakdown-row">
                        <span>Total Nilai Paket:</span>
                        <span>Rp <?= number_format($order['total_harga'], 0, ',', '.'); ?></span>
                    </div>
                    <div class="breakdown-row" style="color:var(--green); font-weight:600;">
                        <span>Jumlah Pembayaran Terverifikasi:</span>
                        <span>- Rp <?= number_format($order['jumlah_dibayar'], 0, ',', '.'); ?></span>
                    </div>
                    <div class="breakdown-total">
                        <span>Sisa Tagihan:</span>
                        <span style="color:<?= $is_lunas ? 'var(--green)' : 'var(--accent)'; ?>;">
                            <?= $is_lunas ? 'Rp 0 (LUNAS 100%)' : 'Rp ' . number_format($sisa_nominal, 0, ',', '.'); ?>
                        </span>
                    </div>
                    <?php if (!$is_lunas && $order['status'] === 'Dikonfirmasi'): ?>
                    <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:8px; padding:10px 14px; margin-top:14px; font-size:0.8rem; color:#92400E;">
                        <?php if ($pel_menunggu): ?>
                            <strong>Pelunasan Diproses:</strong> Bukti pembayaran Anda sedang diverifikasi admin studio. Sisa tagihan baru berubah menjadi lunas setelah verifikasi selesai.
                        <?php elseif ($pel_ditolak): ?>
                            <strong>Bukti Pelunasan Ditolak:</strong> Bukti pembayaran sebelumnya tidak dapat diverifikasi. Silakan unggah ulang bukti yang valid melalui tombol di bawah atau bayar langsung di kasir studio.
                        <?php else: ?>
                            <strong>Informasi Pelunasan:</strong> DP 30% telah mengamankan jadwal sesi foto. Anda dapat melunasi sisa tagihan online (dengan mengunggah bukti pembayaran) melalui tombol di bawah atau langsung di kasir studio saat hari sesi foto.
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php endif; ?>

                <div class="card-actions">
                    <?php if ($is_tempat): ?>
                        <?php if ($order['status'] === 'Dibatalkan'): ?>
                            <span style="font-size:0.85rem; color:var(--red); font-weight:600;">Reservasi dibatalkan</span>
                        <?php elseif ($order['status'] === 'Selesai'): ?>
                            <span style="font-size:0.82rem; color:var(--green); font-weight:600; margin-right:8px;">✓ Sesi selesai</span>
                            <a href="tiket.php?id=<?= $order['id']; ?>" class="btn-outline">Lihat E-Tiket</a>
                        <?php else: ?>
                            <?php if ($order['status'] !== 'Dikonfirmasi'): ?>
                                <span style="font-size:0.82rem; color:var(--ink-soft); margin-right:8px;">Menunggu konfirmasi admin studio</span>
                            <?php elseif ($is_lunas): ?>
                                <span style="font-size:0.82rem; color:var(--green); font-weight:600; margin-right:8px;">✓ Pembayaran Selesai (Lunas)</span>
                            <?php endif; ?>
                            <a href="tiket.php?id=<?= $order['id']; ?>" class="btn-dark">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                Buka E-Tiket Resmi
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                    <?php if ($order['status'] === 'Menunggu Pembayaran'): ?>
                        <a href="bayar.php?id=<?= $order['id']; ?>" class="btn-dark">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            Selesaikan Pembayaran Sekarang
                        </a>
                    <?php elseif ($order['status'] === 'Dikonfirmasi'): ?>
                        <?php if (!$is_lunas): ?>
                            <?php if ($pel_menunggu): ?>
                                <span style="font-size:0.82rem; color:#92400E; font-weight:600; margin-right:8px;">⏳ Pelunasan menunggu verifikasi admin</span>
                            <?php else: ?>
                                <a href="bayar.php?id=<?= $order['id']; ?>" class="btn-dark" style="background:#A9633A; gap:8px;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                                    <?= $pel_ditolak ? 'Unggah Ulang Bukti Pelunasan' : 'Bayar Pelunasan Sisa'; ?> (Rp <?= number_format($sisa_nominal, 0, ',', '.'); ?>)
                                </a>
                            <?php endif; ?>
                            <a href="tiket.php?id=<?= $order['id']; ?>" class="btn-outline">Buka E-Tiket Resmi</a>
                        <?php else: ?>
                            <span style="font-size:0.82rem; color:var(--green); font-weight:600; margin-right:8px;">✓ Pembayaran Selesai (Lunas)</span>
                            <a href="tiket.php?id=<?= $order['id']; ?>" class="btn-dark">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                Buka E-Tiket Resmi
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="font-size:0.85rem; color:var(--ink-soft); margin-right:12px;">Menunggu verifikasi admin studio</span>
                        <a href="tiket.php?id=<?= $order['id']; ?>" class="btn-outline">Lihat E-Tiket Sementara</a>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<!-- ===== FOOTER ===== -->
<footer class="site-footer" style="background:transparent;color:var(--ink-soft);border-top:1px solid var(--line);margin-top:40px;">
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