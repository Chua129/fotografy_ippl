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
<title>Pemesanan Manual di Toko - Creative Pixel Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<style>
body { background-color: #121110; color: #1B1A17; margin: 0; font-family: var(--font-sans); display: flex; min-height: 100vh; }
.admin-sidebar { width: 260px; background: #1B1A17; color: #fff; padding: 24px; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; }
.sidebar-brand { font-family: var(--font-serif); font-size: 1.2rem; color: #fff; margin-bottom: 40px; display: flex; align-items: center; gap: 8px; }
.sidebar-brand span { color: var(--accent); }
.sidebar-menu { display: flex; flex-direction: column; gap: 8px; list-style: none; padding: 0; margin: 0; }
.sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 8px; color: rgba(255,255,255,0.7); text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: 0.2s; }
.sidebar-menu a:hover, .sidebar-menu a.active { background: #2C2B27; color: #fff; }
.admin-main { flex: 1; background: #FAFCFD; border-radius: 20px; overflow-y: auto; padding: 40px; margin: 20px; }
.page-title { font-family: var(--font-serif); font-size: 2rem; font-weight: 500; margin: 0 0 32px; color: var(--ink); }
.manual-order-grid { display: grid; grid-template-columns: 360px 1fr; gap: 32px; align-items: start; }
.mo-box { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 24px; margin-bottom: 24px; }
.form-input, .form-select { width: 100%; border: 1px solid var(--line); border-radius: 8px; padding: 10px 14px; font-size: 0.85rem; background: #FAFAFA; box-sizing: border-box; }
.btn-submit-order { width: 100%; background: #8F764F; color: #fff; border: none; padding: 14px; border-radius: 8px; font-weight: 700; cursor: pointer; }
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
    <h1 class="page-title">Buat Reservasi & Transaksi Walk-in</h1>
    <div class="manual-order-grid">
        <div class="mo-box">
            <h3>Skema & Pembayaran</h3>
            <p style="font-size:0.8rem; color:var(--ink-soft); margin-top:8px;">Pilih metode pembayaran tunai atau QRIS untuk pesanan langsung di toko.</p>
        </div>
        <div>
            <div class="mo-box">
                <h3>Data Pemesan Utama</h3>
                <div style="margin-top:12px;"><label style="font-size:0.7rem; font-weight:700;">Nama Lengkap</label><input type="text" class="form-input" value="Aditya Pratama" style="margin-top:6px;"></div>
            </div>
            <button class="btn-submit-order">Konfirmasi Pemesanan & Terbitkan QRIS</button>
        </div>
    </div>
</main>
<script>
document.addEventListener("DOMContentLoaded", function() {
    document.querySelector('.btn-submit-order').addEventListener('click', function(e) {
        e.preventDefault();
        alert('Pemesanan manual berhasil dikonfirmasi dan QRIS diterbitkan.');
    });
});
</script>
</body>
</html>