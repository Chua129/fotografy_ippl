<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$nama_admin = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Admin';
$current_page = 'admin_etalase.php';
$target_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = '';

// Proses Simpan Pengaturan Kuota
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['single_save']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $kt = (int)$_POST['kuota_total'];
        $kp = (int)$_POST['kuota_terpakai'];

        $stmt = $conn->prepare("UPDATE paket SET kuota_total = ?, kuota_terpakai = ? WHERE id = ?");
        $stmt->bind_param("iii", $kt, $kp, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_etalase.php?msg=quota_saved");
        exit;
    } elseif (isset($_POST['mass_save']) && isset($_POST['kuota_total']) && is_array($_POST['kuota_total'])) {
        foreach ($_POST['kuota_total'] as $id => $kt) {
            $id = (int)$id;
            $kt = (int)$kt;
            $kp = isset($_POST['kuota_terpakai'][$id]) ? (int)$_POST['kuota_terpakai'][$id] : 0;

            $stmt = $conn->prepare("UPDATE paket SET kuota_total = ?, kuota_terpakai = ? WHERE id = ?");
            $stmt->bind_param("iii", $kt, $kp, $id);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: admin_etalase.php?msg=quota_saved");
        exit;
    }
}

// Ambil semua paket untuk tabel inventori kuota
$result = $conn->query("SELECT * FROM paket ORDER BY kategori ASC, harga ASC");
$pakets = [];
$focused_paket = null;
while ($row = $result->fetch_assoc()) {
    $pakets[] = $row;
    if ($row['id'] == $target_id) {
        $focused_paket = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atur Kuota & Ketersediaan - Creative Pixel</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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

.breadcrumb { font-size: 0.8rem; color: var(--ink-soft); font-weight: 600; margin-bottom: 20px; }
.breadcrumb a { color: var(--accent); text-decoration: none; }

.header-actions { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px; border-bottom: 1px solid var(--line); padding-bottom: 20px; }
.page-title { font-family: var(--font-serif); font-size: 2rem; color: var(--ink); margin: 0 0 6px; }

.card-box { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 28px; margin-bottom: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.01); }
.card-box h3 { font-family: var(--font-serif); font-size: 1.25rem; margin: 0 0 16px; color: var(--ink); }

.inventory-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.inventory-table th { background: #FAF9F6; text-align: left; padding: 14px 16px; border-bottom: 1px solid var(--line); color: var(--ink); font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
.inventory-table td { padding: 16px; border-bottom: 1px solid var(--line); vertical-align: middle; }
.inventory-table tr:hover { background: #FAFAFA; }

.table-input { width: 90px; padding: 8px 12px; border: 1px solid var(--line); border-radius: 6px; font-size: 0.85rem; text-align: center; background: #FAFAFA; font-weight: 600; }
.table-input:focus { outline: none; border-color: var(--ink); background: #fff; }

.badge-status { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
.badge-habis { background: #FDE8E8; color: #9B1C1C; }
.badge-terbatas { background: #FEF08A; color: #854D0E; }
.badge-tersedia { background: #DEF7EC; color: #03543F; }

.btn-primary { background: #111; color: #fff; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
.btn-primary:hover { background: #333; }
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
    <div class="breadcrumb">
        <a href="admin_etalase.php">← Kembali ke Etalase</a> / <span>Manajemen Kuota Slot</span>
    </div>

    <form method="POST" action="admin_atur_kuota.php">
        <input type="hidden" name="mass_save" value="1">
        
        <div class="header-actions">
            <div>
                <h1 class="page-title">Atur Kuota & Ketersediaan Slot Bulanan</h1>
                <p style="color:var(--ink-soft); font-size:0.85rem; margin:0;">Kelola kuota maksimal dan kuota terpakai per paket. Ketika sisa slot 0, tombol booking di etalase klien otomatis nonaktif.</p>
            </div>
            <div>
                <button type="submit" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    Simpan Seluruh Kuota
                </button>
            </div>
        </div>

        <div class="card-box">
            <h3>Daftar Inventori Kuota Paket Fotografi</h3>
            
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Paket Fotografi</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th style="text-align:center;">Kapasitas Total</th>
                        <th style="text-align:center;">Terpakai</th>
                        <th style="text-align:center;">Sisa Slot</th>
                        <th style="text-align:center;">Status Klien</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pakets as $p): 
                        $sisa = max(0, $p['kuota_total'] - $p['kuota_terpakai']);
                        $is_focused = ($p['id'] == $target_id);
                    ?>
                    <tr style="<?= $is_focused ? 'background: #FFFBEB;' : ''; ?>">
                        <td>
                            <strong style="color:var(--ink); font-size:0.95rem;"><?= htmlspecialchars($p['nama_paket']); ?></strong>
                            <?php if(!empty($p['sub_tag'])): ?>
                                <br><small style="color:#8F764F; font-weight:600;"><?= htmlspecialchars($p['sub_tag']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span style="font-size:0.75rem; color:var(--ink-soft); font-weight:600; text-transform:uppercase;"><?= htmlspecialchars($p['kategori_label']); ?></span></td>
                        <td><strong style="color:var(--accent);">Rp <?= number_format($p['harga'], 0, ',', '.'); ?></strong></td>
                        <td style="text-align:center;">
                            <input type="number" name="kuota_total[<?= $p['id']; ?>]" value="<?= (int)$p['kuota_total']; ?>" min="1" class="table-input" title="Kapasitas Total Slot">
                        </td>
                        <td style="text-align:center;">
                            <input type="number" name="kuota_terpakai[<?= $p['id']; ?>]" value="<?= (int)$p['kuota_terpakai']; ?>" min="0" class="table-input" title="Slot yang sudah dipesan">
                        </td>
                        <td style="text-align:center; font-weight:700; font-size:1rem; color:<?= $sisa <= 0 ? 'var(--red)' : ($sisa <= 3 ? 'var(--amber)' : 'var(--green)'); ?>;">
                            <?= $sisa; ?> Slot
                        </td>
                        <td style="text-align:center;">
                            <?php if ($sisa <= 0): ?>
                                <span class="badge-status badge-habis">Kuota Habis</span>
                            <?php elseif ($sisa <= 3): ?>
                                <span class="badge-status badge-terbatas">Slot Terbatas</span>
                            <?php else: ?>
                                <span class="badge-status badge-tersedia">Tersedia</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
</main>
</body>
</html>