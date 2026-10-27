<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
$nama_admin = $_SESSION['nama'];
$current_page = basename($_SERVER['PHP_SELF']);

// Query statistik riil dari database
$count_antrean = 0;
$res_antrean = $conn->query("SELECT COUNT(*) as total FROM pesanan WHERE status IN ('Menunggu Konfirmasi', 'Menunggu Pembayaran')");
if ($res_antrean) $count_antrean = (int)$res_antrean->fetch_assoc()['total'];

$total_pendapatan = 0;
$res_pendapatan = $conn->query("SELECT SUM(jumlah_dibayar) as total FROM pesanan WHERE status = 'Dikonfirmasi'");
if ($res_pendapatan) $total_pendapatan = (float)($res_pendapatan->fetch_assoc()['total'] ?? 0);

$count_dikonfirmasi = 0;
$res_konf = $conn->query("SELECT COUNT(*) as total FROM pesanan WHERE status = 'Dikonfirmasi'");
if ($res_konf) $count_dikonfirmasi = (int)$res_konf->fetch_assoc()['total'];

// Kata kunci pencarian dari kotak cari admin (?q=...)
$q = mb_substr(trim($_GET['q'] ?? ''), 0, 100);
$cari = ltrim($q, "# \t");          // "#CP-2026..." tetap bisa dicari
$is_search = ($cari !== '');

// Tanpa pencarian: 5 pesanan terbaru. Dengan pencarian: cari di SEMUA pesanan (maks. 50 hasil)
$recent_orders = [];
$res_recent = false;
$sql_pesanan = "SELECT p.*, u.nama AS nama_klien, u.whatsapp FROM pesanan p JOIN users u ON p.user_id = u.id";
if ($is_search) {
    // Kode booking/invoice, nama klien, WhatsApp, paket, nama pasangan, status, tanggal sesi
    $kolom_cari = ['p.kode_reservasi', 'u.nama', 'u.whatsapp', 'p.paket_layanan', 'p.nama_pasangan', 'p.status', 'p.tanggal_sesi'];
    $kata = '%' . addcslashes($cari, '%_\\') . '%';
    $stmt_cari = $conn->prepare($sql_pesanan . " WHERE " . implode(' LIKE ? OR ', $kolom_cari) . " LIKE ? ORDER BY p.id DESC LIMIT 50");
    if ($stmt_cari) {
        $params_cari = array_fill(0, count($kolom_cari), $kata);
        $stmt_cari->bind_param(str_repeat('s', count($params_cari)), ...$params_cari);
        $stmt_cari->execute();
        $res_recent = $stmt_cari->get_result();
    }
} else {
    $res_recent = $conn->query($sql_pesanan . " ORDER BY p.id DESC LIMIT 5");
}
if ($res_recent) {
    while ($row = $res_recent->fetch_assoc()) {
        $recent_orders[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin - Creative Pixel</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<style>
body { background-color: #1B1A17; color: #1B1A17; margin: 0; font-family: var(--font-sans); display: flex; min-height: 100vh; }
.admin-sidebar { width: 260px; background: #1B1A17; color: #fff; padding: 24px; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; }
.sidebar-brand { font-family: var(--font-serif); font-size: 1.2rem; color: #fff; margin-bottom: 40px; display: flex; align-items: center; gap: 8px; }
.sidebar-brand span { color: var(--accent); }
.sidebar-menu { display: flex; flex-direction: column; gap: 8px; list-style: none; padding: 0; margin: 0; }
.sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 8px; color: rgba(255,255,255,0.7); text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: 0.2s; }
.sidebar-menu a:hover, .sidebar-menu a.active { background: #2C2B27; color: #fff; }
.admin-main { flex: 1; background: #FAFCFD; border-top-left-radius: 20px; border-bottom-left-radius: 20px; overflow-y: auto; padding: 32px 40px; }
.admin-topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; border-bottom: 1px solid var(--line); padding-bottom: 20px; }
.search-box { display: flex; align-items: center; background: #fff; border: 1px solid var(--line); border-radius: 999px; padding: 8px 20px; width: 400px; gap: 10px; }
.search-box input { border: none; outline: none; width: 100%; font-size: 0.85rem; background: transparent; font-family: inherit; color: var(--ink); }
.search-box { margin: 0; transition: border-color 0.2s, box-shadow 0.2s; }
.search-box:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(169,99,58,0.12); }
.search-clear { color: var(--ink-faint); text-decoration: none; font-size: 1.2rem; line-height: 1; padding: 0 4px; flex-shrink: 0; }
.search-clear:hover { color: var(--ink); }
.dash-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; }
.dash-title-kicker { font-size: 0.7rem; font-weight: 700; color: var(--ink-faint); text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 6px; }
.dash-title { font-family: var(--font-serif); font-size: 2rem; font-weight: 500; margin: 0; color: var(--ink); }
.dash-subtitle { font-size: 0.85rem; color: var(--ink-soft); margin: 6px 0 0; }
.btn-group { display: flex; gap: 12px; }
.btn-outline { background: #fff; border: 1px solid var(--line); color: var(--ink); padding: 10px 18px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
.btn-dark { background: var(--dark); border: none; color: #fff; padding: 10px 18px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 32px; }
.stat-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 24px; position: relative; }
.stat-card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; font-size: 0.7rem; font-weight: 700; color: var(--ink-faint); text-transform: uppercase; }
.stat-card-value { font-family: var(--font-serif); font-size: 1.8rem; font-weight: 500; color: var(--ink); margin: 0 0 8px; }
.stat-card-note { font-size: 0.75rem; color: var(--ink-soft); margin: 0; display: flex; align-items: center; gap: 6px; }
.btn-card-action { display: block; width: 100%; text-align: center; background: #F8F8F8; border: 1px solid var(--line); padding: 10px; border-radius: 8px; font-size: 0.8rem; font-weight: 600; color: var(--ink); text-decoration: none; margin-top: 16px; transition: 0.2s; box-sizing: border-box; }
.btn-card-action:hover { background: var(--dark); color: #fff; }
.table-section { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 24px; }
.table-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.table-head h3 { font-family: var(--font-serif); font-size: 1.2rem; margin: 0; font-weight: 500; }
table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem; }
th { color: var(--ink-faint); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border-bottom: 1px solid var(--line); }
td { padding: 16px; border-bottom: 1px solid var(--line); color: var(--ink); }
.badge-status-table { font-size: 0.7rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; color: #fff; display: inline-block; }
.bg-green { background: #238551; }
.bg-amber { background: #D9822B; }
.bg-red { background: #C53030; }
</style>
</head>
<body>
<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="var(--accent)"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
        Creative <span>Pixel</span>
    </div>
    <p style="font-size:0.65rem; color:rgba(255,255,255,0.4); text-transform:uppercase; letter-spacing:0.05em; margin:0 0 16px;">Operasional Studio</p>
    <ul class="sidebar-menu">
        <li><a href="admin_dashboard.php" class="<?= $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
        <li><a href="admin_konfirmasi.php" class="<?= $current_page == 'admin_konfirmasi.php' ? 'active' : ''; ?>">Konfirmasi Pesanan</a></li>
        <li><a href="admin_etalase.php" class="<?= $current_page == 'admin_etalase.php' ? 'active' : ''; ?>">Etalase & Stok Paket</a></li>
        <li><a href="admin_jadwal.php" class="<?= $current_page == 'admin_jadwal.php' ? 'active' : ''; ?>">Jadwal & Kuota Sesi</a></li>
        <li><a href="admin_history.php" class="<?= $current_page == 'admin_history.php' ? 'active' : ''; ?>">History Pemesanan</a></li>
        <li style="margin-top:auto;"><a href="logout.php" style="color:var(--red);">Keluar Sistem</a></li>
    </ul>
</aside>
<main class="admin-main">
    <div class="admin-topbar">
        <form class="search-box" method="get" action="admin_dashboard.php#pesanan-terbaru" role="search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ink-faint)" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" name="q" value="<?= htmlspecialchars($q); ?>" placeholder="Cari kode booking, nama klien, invoice..." aria-label="Cari pesanan reservasi" autocomplete="off">
            <?php if ($is_search): ?>
                <a href="admin_dashboard.php" class="search-clear" aria-label="Hapus pencarian" title="Hapus pencarian">&times;</a>
            <?php endif; ?>
        </form>
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="text-align: right;">
                <span style="font-size:0.85rem; font-weight:600; display:block;"><?= htmlspecialchars($nama_admin); ?></span>
                <span style="font-size:0.7rem; color:var(--ink-soft);">(Admin Operasional)</span>
            </div>
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_admin); ?>&background=1B1A17&color=fff" alt="Admin" style="width:36px; height:36px; border-radius:50%;">
        </div>
    </div>
    <div class="dash-header">
        <div>
            <p class="dash-title-kicker">OPERASIONAL STUDIO / DASHBOARD ADMIN</p>
            <h1 class="dash-title">Ikhtisar Studio & Operasional</h1>
            <p class="dash-subtitle">Ringkasan reservasi, verifikasi pembayaran, dan jadwal sesi pemotretan aktif.</p>
        </div>
        <div class="btn-group">
            <a href="admin_konfirmasi.php" class="btn-dark">+ Konfirmasi Pesanan</a>
        </div>
    </div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-head"><span>ANTREAN VERIFIKASI</span> <i class="dot dot-amber" style="margin:0;"></i></div>
            <p class="stat-card-value"><?= $count_antrean; ?> Pesanan</p>
            <p class="stat-card-note">Perlu tindakan persetujuan admin</p>
            <a href="admin_konfirmasi.php" class="btn-card-action">Buka Antrean Konfirmasi ›</a>
        </div>
        <div class="stat-card">
            <div class="stat-card-head"><span>PENDAPATAN MASUK</span> <i class="dot dot-green" style="margin:0;"></i></div>
            <p class="stat-card-value">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></p>
            <p class="stat-card-note">Total pembayaran terkonfirmasi</p>
            <a href="admin_history.php" class="btn-card-action">Lihat Riwayat Transaksi ›</a>
        </div>
        <div class="stat-card">
            <div class="stat-card-head"><span>SESI TERKONFIRMASI</span> <i class="dot dot-green" style="margin:0;"></i></div>
            <p class="stat-card-value"><?= $count_dikonfirmasi; ?> Sesi Terkunci</p>
            <p class="stat-card-note">Jadwal slot studio aktif</p>
            <a href="admin_jadwal.php" class="btn-card-action">Monitor Jadwal Studio ›</a>
        </div>
    </div>

    <div class="table-section" id="pesanan-terbaru">
        <div class="table-head">
            <h3><?= $is_search ? 'Hasil Pencarian' : 'Pesanan Reservasi Terbaru'; ?></h3>
            <span style="font-size:0.8rem; font-weight:600; color:var(--ink-soft); background:var(--bg-alt); padding:6px 12px; border-radius:6px;">
                <?= $is_search ? count($recent_orders) . ' hasil untuk “' . htmlspecialchars(mb_strimwidth($q, 0, 40, '…')) . '”' : 'Total ' . count($recent_orders) . ' Data Terkini'; ?>
            </span>
        </div>
        <?php if (empty($recent_orders)): ?>
            <p style="text-align:center; padding:30px; color:var(--ink-soft);">
                <?php if ($is_search): ?>
                    Tidak ada pesanan yang cocok dengan “<?= htmlspecialchars(mb_strimwidth($q, 0, 60, '…')); ?>”.<br>
                    Coba kode booking, nama klien, atau nomor WhatsApp lain. <a href="admin_dashboard.php" style="color:var(--dark); font-weight:600; text-decoration:underline;">Tampilkan pesanan terbaru</a>
                <?php else: ?>
                    Belum ada pemesanan yang masuk ke sistem.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Kode &amp; Tanggal Sesi</th>
                        <th>Klien &amp; WhatsApp</th>
                        <th>Paket &amp; Studio</th>
                        <th>Jumlah Bayar</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders as $ord): ?>
                        <?php 
                            $bg = 'bg-amber';
                            if ($ord['status'] === 'Dikonfirmasi') $bg = 'bg-green';
                            elseif ($ord['status'] === 'Dibatalkan') $bg = 'bg-red';
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($ord['kode_reservasi']); ?></strong><br>
                                <span style="font-size:0.75rem; color:var(--ink-soft);"><?= date('d M Y', strtotime($ord['tanggal_sesi'])); ?> (<?= htmlspecialchars($ord['jam_sesi']); ?>)</span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($ord['nama_klien']); ?></strong><br>
                                <span style="font-size:0.75rem; color:var(--ink-soft);"><?= htmlspecialchars($ord['whatsapp']); ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($ord['paket_layanan']); ?></strong><br>
                                <span style="font-size:0.75rem; color:var(--ink-soft);"><?= htmlspecialchars($ord['lokasi_studio']); ?></span>
                            </td>
                            <td style="font-weight:600;">
                                Rp <?= number_format($ord['jumlah_dibayar'], 0, ',', '.'); ?>
                            </td>
                            <td>
                                <span class="badge-status-table <?= $bg; ?>"><?= htmlspecialchars($ord['status']); ?></span>
                            </td>
                            <td>
                                <a href="admin_konfirmasi.php" style="color:var(--dark); font-weight:600; text-decoration:underline; font-size:0.8rem;">Kelola</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>
</body>
</html>