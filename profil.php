<?php
session_start();
require 'koneksi.php';

// Cek apakah ada sesi user_id
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$query = "SELECT nama, email, whatsapp, created_at FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Jika data tidak ditemukan, bersihkan sesi
if (!$user) {
    header("Location: logout.php");
    exit;
}

// ===== DATA TAGIHAN: diambil dari reservasi milik user ini (selain yang dibatalkan) =====
$bulan_singkat = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

function tgl_singkat($tanggal, $bulan_singkat) {
    $ts = strtotime($tanggal);
    return $ts ? date('j', $ts) . ' ' . $bulan_singkat[(int)date('n', $ts)] . ' ' . date('Y', $ts) : '-';
}
function rp_fmt($n) {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}
function label_tagihan($p) {
    if ($p['metode_pembayaran'] === 'tempat') return 'Bayar di studio';
    if ($p['belum_bayar_online']) return 'Menunggu pembayaran';
    return 'DP 30% terbayar';
}
function label_pembayaran($p) {
    if ($p['metode_pembayaran'] === 'tempat') return 'Pembayaran di Kasir Studio';
    $via = ($p['metode_pembayaran'] === 'qris') ? 'QRIS Instant' : ucfirst($p['metode_pembayaran']);
    return ($p['skema_pembayaran'] === 'lunas') ? 'Pembayaran Penuh via ' . $via : 'DP 30% + Pelunasan via ' . $via;
}

$stmt_p = $conn->prepare("SELECT id, kode_reservasi, paket_layanan, tanggal_sesi, skema_pembayaran, metode_pembayaran, total_harga, sisa_tagihan, status FROM pesanan WHERE user_id = ? AND status <> 'Dibatalkan' ORDER BY id DESC");
$stmt_p->bind_param("i", $user_id);
$stmt_p->execute();
$semua_pesanan = $stmt_p->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_p->close();

$tagihan_aktif = [];   // masih ada yang harus dibayar
$riwayat_lunas = [];   // sudah lunas seluruhnya
$total_terutang = 0;
foreach ($semua_pesanan as $p) {
    // Pesanan QRIS yang belum diverifikasi: belum ada uang masuk, jadi seluruh total masih terutang
    $p['belum_bayar_online'] = ($p['status'] === 'Menunggu Pembayaran' && $p['metode_pembayaran'] !== 'tempat');
    $p['terutang'] = $p['belum_bayar_online'] ? (float)$p['total_harga'] : (float)$p['sisa_tagihan'];
    if ($p['terutang'] > 0) {
        $tagihan_aktif[] = $p;
        $total_terutang += $p['terutang'];
    } else {
        $riwayat_lunas[] = $p;
    }
}
$jumlah_aktif = count($tagihan_aktif);
if ($jumlah_aktif === 0) {
    $desc_aktif = 'Tidak ada tagihan tertunda';
} elseif ($jumlah_aktif === 1) {
    $desc_aktif = label_tagihan($tagihan_aktif[0]) . ' • Sisa pelunasan';
} else {
    $desc_aktif = $jumlah_aktif . ' reservasi belum lunas';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil Saya - Creative Pixel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<!-- Memanggil CSS Global -->
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<style>
/* =========================================
   GAYA KHUSUS HALAMAN PROFIL
========================================= */
.profile-container { max-width: 900px; margin: 0 auto; padding: 40px 32px 80px; }

.btn-back-pill {
    display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px;
    border: 1px solid var(--line); border-radius: 999px; background: #fff;
    font-size: 0.85rem; font-weight: 500; color: var(--ink-soft); margin-bottom: 32px;
    transition: background 0.2s;
}
.btn-back-pill:hover { background: var(--bg-alt); color: var(--ink); }

/* Profile Header */
.profile-header {
    background: #fff; border: 1px solid var(--line); border-radius: var(--radius-lg);
    padding: 32px; display: flex; align-items: center; gap: 24px; margin-bottom: 32px;
    box-shadow: 0 10px 30px -10px rgba(0,0,0,0.03);
}
.avatar-wrapper { position: relative; width: 80px; height: 80px; flex-shrink: 0; }
.avatar-wrapper img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
.verified-badge {
    position: absolute; bottom: 0; right: 0; background: #fff; border-radius: 50%; padding: 2px;
    color: var(--accent); display: flex;
}
.profile-info h1 { font-family: var(--font-serif); font-size: 1.8rem; font-weight: 500; margin: 0 0 8px; }
.profile-meta { font-size: 0.85rem; color: var(--ink-soft); display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.meta-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--line); }
.text-accent-color { color: var(--accent); font-weight: 500; }

/* Billing & Payment Cards */
.billing-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 48px; }
.bill-card {
    background: #fff; border: 1px solid var(--line); border-radius: var(--radius-md);
    padding: 24px; display: flex; flex-direction: column; position: relative;
}
.bill-card-accent { background: #FFFBF7; border-color: #F1E3D3; }
.bill-card-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.bill-label { font-size: 0.7rem; font-weight: 600; letter-spacing: 0.05em; color: var(--ink-faint); text-transform: uppercase; }
.bill-label-accent { color: var(--accent); }
.badge-soft { background: #F4E7DC; color: var(--accent); font-size: 0.65rem; font-weight: 600; padding: 4px 8px; border-radius: 4px; }
.bill-amount { font-family: var(--font-serif); font-size: 1.6rem; font-weight: 500; color: var(--accent); margin: 0 0 6px; }
.bill-desc { font-size: 0.8rem; color: var(--ink-soft); margin: 0; }
.payment-methods { display: flex; gap: 10px; margin-top: 12px; }
.method-tag { background: var(--bg-alt); padding: 8px 14px; border-radius: 6px; font-size: 0.85rem; font-weight: 500; color: var(--ink); }

/* History Section */
.history-title { font-family: var(--font-serif); font-size: 1.3rem; font-weight: 400; margin: 0 0 20px; color: var(--ink-soft); }
.history-list { background: #fff; border: 1px solid var(--line); border-radius: var(--radius-lg); overflow: hidden; }
.history-item { padding: 24px; border-bottom: 1px solid var(--line); display: flex; flex-direction: column; gap: 8px; }
.history-item:last-child { border-bottom: none; }
.history-meta { display: flex; align-items: center; gap: 12px; font-size: 0.75rem; color: var(--ink-faint); font-weight: 500; text-transform: uppercase; letter-spacing: 0.03em; }
.badge-success { background: var(--green-bg); color: var(--green); padding: 4px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 6px; font-weight: 600; text-transform: none; font-size: 0.7rem; letter-spacing: 0; }
.history-item h3 { font-size: 1.05rem; font-weight: 600; margin: 0; color: var(--ink); }
.history-desc { font-size: 0.85rem; color: var(--ink-soft); margin: 0; }
.history-desc strong { color: var(--ink); }

/* Daftar tagihan aktif di dalam kartu Menunggu Pelunasan */
.bill-orders { margin-top: 16px; padding-top: 14px; border-top: 1px dashed #E9D8C6; display: flex; flex-direction: column; gap: 12px; }
.bill-order { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; font-size: 0.8rem; }
.bill-order strong { display: block; font-weight: 600; color: var(--ink); }
.bill-order .sub { display: block; font-size: 0.72rem; color: var(--ink-soft); margin-top: 2px; }
.bill-order-right { text-align: right; flex-shrink: 0; }
.bill-order-right b { display: block; font-weight: 600; color: var(--accent); }
.bill-order a { font-size: 0.75rem; font-weight: 600; color: var(--accent); text-decoration: none; }
.bill-order a:hover { text-decoration: underline; }
.history-empty { padding: 36px 24px; text-align: center; font-size: 0.85rem; color: var(--ink-soft); }

@media (max-width: 768px) {
    .billing-grid { grid-template-columns: 1fr; }
    .profile-header { flex-direction: column; text-align: center; }
    .profile-meta { justify-content: center; }
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
      <a href="pemesanan.php">Cek Status Reservasi</a>
      <a href="tiket.php">Lihat Tiket Reservasi</a>
    </nav>
    <div class="header-actions">
      <div class="user-chip">
        <a href="profil.php" class="user-chip-link">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['nama']); ?>&background=F3F1EC&color=1B1A17" alt="Avatar">
            <div class="user-chip-text">
              <strong><?= htmlspecialchars($user['nama']); ?></strong>
              <span>Studio Account</span>
            </div>
        </a>
        <a href="logout.php" class="logout-link">Keluar</a>
      </div>
    </div>
  </div>
</header>

<main class="container page-wrapper">

    <!-- Kartu Profil -->
    <div class="profile-header">
        <div class="avatar-wrapper">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['nama']); ?>&background=F3F1EC&color=1B1A17&size=128" alt="Avatar">
            <div class="verified-badge">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm-1.9 14.7L6 12.6l1.5-1.5 2.6 2.6 6.4-6.4 1.5 1.5-7.9 7.9z"/></svg>
            </div>
        </div>
        <div class="profile-info">
            <h1><?= htmlspecialchars($user['nama']); ?></h1>
            <div class="profile-meta">
                <span><?= htmlspecialchars($user['email']); ?></span>
                <span class="meta-dot"></span>
                <span><?= htmlspecialchars($user['whatsapp']); ?></span>
                <span class="meta-dot"></span>
                <span class="text-accent-color">Pekanbaru</span>
            </div>
        </div>
    </div>

    <!-- Grid Tagihan & Metode -->
    <div class="billing-grid">
        <div class="bill-card bill-card-accent">
            <div class="bill-card-head">
                <span class="bill-label bill-label-accent">MENUNGGU PELUNASAN</span>
                <span class="badge-soft"><?= $jumlah_aktif; ?> Aktif</span>
            </div>
            <p class="bill-amount"><?= rp_fmt($total_terutang); ?></p>
            <p class="bill-desc"><?= htmlspecialchars($desc_aktif); ?></p>

            <?php if ($jumlah_aktif > 0): ?>
            <div class="bill-orders">
                <?php foreach ($tagihan_aktif as $p): ?>
                <div class="bill-order">
                    <div>
                        <strong><?= htmlspecialchars($p['paket_layanan']); ?></strong>
                        <span class="sub">#<?= htmlspecialchars(ltrim($p['kode_reservasi'], '#')); ?> • Sesi <?= htmlspecialchars(tgl_singkat($p['tanggal_sesi'], $bulan_singkat)); ?> • <?= htmlspecialchars(label_tagihan($p)); ?></span>
                    </div>
                    <div class="bill-order-right">
                        <b><?= rp_fmt($p['terutang']); ?></b>
                        <?php if ($p['belum_bayar_online']): ?>
                            <a href="bayar.php?id=<?= (int)$p['id']; ?>">Bayar sekarang</a>
                        <?php else: ?>
                            <a href="tiket.php?id=<?= (int)$p['id']; ?>">Lihat tiket</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bill-card">
            <div class="bill-card-head">
                <span class="bill-label">METODE TERSEDIA</span>
            </div>
            <div class="payment-methods">
                <div class="method-tag">QRIS Instant</div>
                <div class="method-tag">Di Studio</div>
            </div>
        </div>
    </div>

    <!-- Riwayat -->
    <h2 class="history-title">Riwayat Tagihan Selesai &amp; Arsip Kuitansi</h2>
    <div class="history-list">
        <?php if (empty($riwayat_lunas)): ?>
        <div class="history-empty">Belum ada tagihan yang lunas. Reservasi akan tampil di sini setelah seluruh pembayarannya selesai.</div>
        <?php endif; ?>

        <?php foreach ($riwayat_lunas as $p): ?>
        <div class="history-item">
            <div class="history-meta">
                <span>#<?= htmlspecialchars(ltrim($p['kode_reservasi'], '#')); ?></span>
                <span class="badge-success"><i class="dot dot-green" style="margin:0; width:6px; height:6px;"></i> Lunas &amp; Selesai</span>
                <span style="color: var(--ink-faint); font-weight: 400; text-transform: none;">Sesi <?= htmlspecialchars(tgl_singkat($p['tanggal_sesi'], $bulan_singkat)); ?></span>
            </div>
            <h3><?= htmlspecialchars($p['paket_layanan']); ?></h3>
            <p class="history-desc"><?= htmlspecialchars(label_pembayaran($p)); ?> • Total: <strong><?= rp_fmt($p['total_harga']); ?></strong></p>
        </div>
        <?php endforeach; ?>

    </div>

</main>

<!-- ================= FOOTER ================= -->
<footer class="site-footer" style="background:transparent;color:var(--ink-soft);border-top:1px solid var(--line);margin-top:40px; padding-top:40px;">
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