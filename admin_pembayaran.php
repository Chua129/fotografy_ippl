<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
$nama_admin = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Clara V.';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rincian & Pembayaran - Creative Pixel Admin</title>
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
.dash-title { font-family: var(--font-serif); font-size: 2rem; font-weight: 500; margin: 0 0 8px; color: var(--ink); }
.pay-card-item { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 20px 24px; margin-bottom: 16px; }
.btn-invoice { background: #F4F4F4; color: var(--ink); border: 1px solid var(--line); padding: 6px 14px; border-radius: 6px; font-weight: 600; cursor: pointer; }
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
    <h1 class="dash-title">Rincian & Pembayaran</h1>
    <p style="color:var(--ink-soft); font-size:0.85rem; margin-bottom:24px;">Kelola verifikasi uang muka (DP) dan pelunasan sisa tagihan.</p>
    <div class="pay-card-item">
        <h3>#ATL-8819 - Iqbal & Teman</h3>
        <p style="color:var(--ink-soft); font-size:0.80rem; margin:6px 0 12px;">Status: <strong style="color:#2E7D32;">Lunas</strong></p>
        <button class="btn-invoice">Unduh Invoice</button>
    </div>
</main>
<script>
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.btn-invoice').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            alert('Mengunduh dokumen invoice pembayaran...');
        });
    });
});
</script>
</body>
</html>