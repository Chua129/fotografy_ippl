<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
$nama_admin = $_SESSION['nama'];
$current_page = basename($_SERVER['PHP_SELF']);

$notif = '';
$notif_error = '';

// Proses aksi konfirmasi atau batalkan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['aksi'])) {
    $order_id = (int)$_POST['order_id'];
    $aksi = $_POST['aksi'];

    if ($aksi === 'konfirmasi') {
        $stmt_act = $conn->prepare("UPDATE pesanan SET status = 'Dikonfirmasi' WHERE id = ?");
        $stmt_act->bind_param("i", $order_id);
        if ($stmt_act->execute()) {
            $notif = 'Pesanan ID #' . $order_id . ' berhasil disetujui & dikonfirmasi!';
        }
        $stmt_act->close();
    } elseif ($aksi === 'batalkan') {
        $stmt_act = $conn->prepare("UPDATE pesanan SET status = 'Dibatalkan' WHERE id = ?");
        $stmt_act->bind_param("i", $order_id);
        if ($stmt_act->execute()) {
            $notif = 'Pesanan ID #' . $order_id . ' telah dibatalkan.';
        }
        $stmt_act->close();
    } elseif ($aksi === 'pelunasan_kasir') {
        $stmt_chk = $conn->prepare("SELECT total_harga, sisa_tagihan, kode_reservasi FROM pesanan WHERE id = ?");
        $stmt_chk->bind_param("i", $order_id);
        $stmt_chk->execute();
        $res_chk = $stmt_chk->get_result()->fetch_assoc();
        $stmt_chk->close();

        if ($res_chk) {
            $sisa = (float)$res_chk['sisa_tagihan'];
            $tot = (float)$res_chk['total_harga'];
            $log_note = "\n[Pembayaran di Kasir Studio Rp " . number_format($sisa, 0, ',', '.') . " diverifikasi oleh " . $nama_admin . " pada " . date('d/m/Y H:i') . "]";
            $stmt_act = $conn->prepare("UPDATE pesanan SET jumlah_dibayar = ?, sisa_tagihan = 0.00, skema_pembayaran = IF(metode_pembayaran = 'tempat', skema_pembayaran, 'lunas'), catatan = CONCAT(COALESCE(catatan, ''), ?) WHERE id = ?");
            $stmt_act->bind_param("dsi", $tot, $log_note, $order_id);
            if ($stmt_act->execute()) {
                $notif = 'Pembayaran di Studio untuk kode ' . $res_chk['kode_reservasi'] . ' (Rp ' . number_format($sisa, 0, ',', '.') . ') berhasil diproses. Status kini Lunas 100%!';
            }
            $stmt_act->close();
        }
    } elseif ($aksi === 'selesaikan') {
        // Pesanan hanya boleh diselesaikan jika sudah dikonfirmasi DAN sudah lunas 100%
        $log_note = "\n[Pesanan diselesaikan oleh " . $nama_admin . " pada " . date('d/m/Y H:i') . " - pembayaran lunas]";
        $stmt_act = $conn->prepare("UPDATE pesanan SET status = 'Selesai', catatan = CONCAT(COALESCE(catatan, ''), ?) WHERE id = ? AND status = 'Dikonfirmasi' AND sisa_tagihan <= 0");
        $stmt_act->bind_param("si", $log_note, $order_id);
        if ($stmt_act->execute() && $stmt_act->affected_rows > 0) {
            $notif = 'Pesanan ID #' . $order_id . ' berhasil diselesaikan. Sesi foto ditandai Selesai!';
        } else {
            $notif_error = 'Pesanan ID #' . $order_id . ' belum bisa diselesaikan. Pastikan statusnya Dikonfirmasi dan pembayaran sudah Lunas 100%.';
        }
        $stmt_act->close();
    }
}

// Stats
$c_menunggu = 0;
$r_m = $conn->query("SELECT COUNT(*) as t FROM pesanan WHERE status IN ('Menunggu Konfirmasi', 'Menunggu Pembayaran')");
if ($r_m) $c_menunggu = (int)$r_m->fetch_assoc()['t'];

$c_dikonfirmasi = 0;
$r_k = $conn->query("SELECT COUNT(*) as t FROM pesanan WHERE status = 'Dikonfirmasi'");
if ($r_k) $c_dikonfirmasi = (int)$r_k->fetch_assoc()['t'];

// Bayar di tempat yang belum dibayar di kasir (tanpa DP / tanpa pembayaran online)
$c_dp = 0;
$r_dp = $conn->query("SELECT COUNT(*) as t FROM pesanan WHERE metode_pembayaran = 'tempat' AND sisa_tagihan > 0 AND status NOT IN ('Dibatalkan', 'Selesai')");
if ($r_dp) $c_dp = (int)$r_dp->fetch_assoc()['t'];

$c_batal = 0;
$r_b = $conn->query("SELECT COUNT(*) as t FROM pesanan WHERE status = 'Dibatalkan'");
if ($r_b) $c_batal = (int)$r_b->fetch_assoc()['t'];

// List all orders
$orders = [];
$res_orders = $conn->query("SELECT p.*, u.nama as nama_klien, u.whatsapp, u.email FROM pesanan p JOIN users u ON p.user_id = u.id ORDER BY p.id DESC");
if ($res_orders) {
    while ($row = $res_orders->fetch_assoc()) {
        $orders[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Konfirmasi Pesanan - Creative Pixel Admin</title>
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
.admin-topbar { display: flex; justify-content: flex-end; align-items: center; margin-bottom: 32px; padding-bottom: 20px; }
.user-profile { display: flex; align-items: center; gap: 12px; text-align: right; }
.user-profile h4 { margin: 0; font-size: 0.85rem; font-weight: 700; color: var(--ink); }
.user-profile p { margin: 0; font-size: 0.7rem; color: var(--ink-soft); }
.dash-header { margin-bottom: 32px; }
.live-status { font-size: 0.7rem; font-weight: 700; color: #8F764F; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px; margin-bottom: 8px; }
.dash-title { font-family: var(--font-serif); font-size: 2.2rem; font-weight: 500; margin: 0 0 8px; color: var(--ink); }
.dash-subtitle { font-size: 0.85rem; color: var(--ink-soft); margin: 0; max-width: 600px; line-height: 1.5; }
.stats-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 40px; }
.sc4-card { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 20px; position: relative; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
.sc4-head { display: flex; justify-content: space-between; align-items: center; font-size: 0.7rem; font-weight: 700; color: var(--ink-faint); text-transform: uppercase; margin-bottom: 12px; }
.sc4-value { font-family: var(--font-serif); font-size: 2rem; font-weight: 500; color: var(--ink); margin: 0 0 4px; display: flex; align-items: baseline; gap: 8px; }
.sc4-value span { font-size: 0.8rem; font-family: var(--font-sans); color: var(--ink-soft); font-weight: 600; }
.sc4-note { font-size: 0.75rem; color: var(--ink-soft); margin: 0; }
.sc4-indicator { position: absolute; bottom: 0; left: 20px; right: 20px; height: 3px; border-radius: 3px 3px 0 0; }
.antrean-head { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
.antrean-head h3 { font-size: 1.1rem; font-weight: 700; margin: 0; color: var(--ink); }
.antrean-badge { background: #EAEAEA; color: var(--ink); font-size: 0.75rem; font-weight: 600; padding: 4px 10px; border-radius: 999px; }
.order-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 24px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); }
.oc-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.oc-info-left { display: flex; gap: 16px; }
.oc-avatar { width: 56px; height: 56px; border-radius: 8px; object-fit: cover; }
.oc-client-name { font-size: 1.1rem; font-weight: 700; color: var(--ink); margin: 0 0 4px; display: flex; align-items: center; gap: 10px; }
.oc-code { font-size: 0.75rem; color: var(--ink-faint); font-weight: 600; letter-spacing: 0.05em; font-family: monospace; }
.oc-package { font-size: 0.95rem; font-weight: 600; color: #8F764F; margin: 4px 0 8px; }
.oc-details { display: flex; gap: 20px; font-size: 0.75rem; color: var(--ink-soft); flex-wrap: wrap; }
.oc-details span { display: flex; align-items: center; gap: 6px; }
.badge { font-size: 0.65rem; font-weight: 700; padding: 4px 8px; border-radius: 4px; letter-spacing: 0.02em; }
.badge-green { background: #E8F5E9; color: #2E7D32; }
.badge-yellow { background: #FFF3E0; color: #E65100; }
.badge-red { background: #FFEBEE; color: #C62828; }
.badge-blue { background: #E3F2FD; color: #1565C0; }
.oc-pay-right { text-align: right; }
.oc-pay-label { font-size: 0.65rem; font-weight: 700; color: var(--ink-faint); text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 4px; }
.oc-pay-amount { font-family: var(--font-serif); font-size: 1.3rem; font-weight: 600; color: var(--ink); margin: 0 0 4px; }
.oc-pay-method { font-size: 0.75rem; font-weight: 600; color: #2E7D32; margin: 0; }
.oc-notes { background: #F8F8F8; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; font-size: 0.8rem; }
.oc-notes h5 { margin: 0 0 4px; font-size: 0.75rem; font-weight: 700; color: var(--ink); }
.oc-notes p { margin: 0; color: var(--ink-soft); }
.oc-actions { display: flex; justify-content: flex-end; gap: 12px; }
.btn-cancel { background: #FFF5F5; color: #C53030; border: 1px solid #FED7D7; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; }
.btn-confirm { background: #111; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.btn-finish { background: #1565C0; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.btn-cancel:hover { background: #FED7D7; }
.btn-confirm:hover, .btn-finish:hover { opacity: 0.9; }

.alert-success { background: #E8F5E9; border: 1px solid #C8E6C9; color: #2E7D32; padding: 14px 20px; border-radius: 8px; margin-bottom: 24px; font-size: 0.9rem; font-weight: 600; }
.alert-error { background: #FFEBEE; border: 1px solid #FFCDD2; color: #C62828; padding: 14px 20px; border-radius: 8px; margin-bottom: 24px; font-size: 0.9rem; font-weight: 600; }
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
        <div class="user-profile">
            <div><h4><?= htmlspecialchars($nama_admin); ?></h4><p>Studio Administrator</p></div>
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_admin); ?>&background=1B1A17&color=fff" alt="Admin" style="width:40px; height:40px; border-radius:50%;">
        </div>
    </div>
    
    <?php if (!empty($notif)): ?>
        <div class="alert-success">
            ✓ <?= htmlspecialchars($notif); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($notif_error)): ?>
        <div class="alert-error">
            ✕ <?= htmlspecialchars($notif_error); ?>
        </div>
    <?php endif; ?>

    <div class="dash-header">
        <div class="live-status"><i class="dot dot-amber" style="margin:0;"></i> Verifikasi Pesanan Riil</div>
        <h1 class="dash-title">Konfirmasi & Pembatalan Pemesanan</h1>
        <p class="dash-subtitle">Verifikasi pesanan klien, setujui status sesi foto agar terkunci resmi, atau batalkan pesanan yang bermasalah.</p>
    </div>
    <div class="stats-grid-4">
        <div class="sc4-card"><div class="sc4-head"><span>ANTREAN VERIFIKASI</span> <i class="dot dot-amber" style="margin:0;"></i></div><h2 class="sc4-value"><?= $c_menunggu; ?> <span>Pesanan</span></h2><p class="sc4-note">Menunggu Tindakan</p><div class="sc4-indicator" style="background:#F6E2CD; width:40%;"></div></div>
        <div class="sc4-card"><div class="sc4-head"><span>TERKONFIRMASI</span></div><h2 class="sc4-value"><?= $c_dikonfirmasi; ?> <span>Sesi</span></h2><p class="sc4-note">Slot resmi di-lock</p><div class="sc4-indicator" style="background:#A5D6A7; width:70%;"></div></div>
        <div class="sc4-card"><div class="sc4-head"><span>BAYAR DI TEMPAT</span></div><h2 class="sc4-value"><?= $c_dp; ?> <span>Belum Bayar</span></h2><p class="sc4-note">Dibayar di kasir studio</p><div class="sc4-indicator" style="background:#EAEAEA; width:30%;"></div></div>
        <div class="sc4-card"><div class="sc4-head"><span>DIBATALKAN</span></div><h2 class="sc4-value"><?= $c_batal; ?> <span>Pesanan</span></h2><p class="sc4-note">Status batal</p><div class="sc4-indicator" style="background:#FED7D7; width:15%;"></div></div>
    </div>

    <div class="antrean-head">
        <h3>Daftar Seluruh Pesanan Masuk</h3>
        <span class="antrean-badge"><?= count($orders); ?> Total Pesanan</span>
    </div>

    <?php if (empty($orders)): ?>
        <div style="background:#fff; border:1px solid var(--line); border-radius:16px; padding:40px; text-align:center; color:var(--ink-soft);">
            Belum ada pesanan masuk dari klien.
        </div>
    <?php else: ?>
        <?php foreach ($orders as $ord): ?>
            <?php 
                $b_class = 'badge-yellow';
                if ($ord['status'] === 'Dikonfirmasi') $b_class = 'badge-green';
                elseif ($ord['status'] === 'Dibatalkan') $b_class = 'badge-red';
                elseif ($ord['status'] === 'Selesai') $b_class = 'badge-blue';
                // Bayar di tempat: tanpa status DP
                $is_tempat = ($ord['metode_pembayaran'] === 'tempat');
            ?>
            <div class="order-card">
                <div class="oc-top">
                    <div class="oc-info-left">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($ord['nama_klien']); ?>&background=F3F1EC&color=1B1A17" alt="Avatar" class="oc-avatar">
                        <div>
                            <h3 class="oc-client-name">
                                <?= htmlspecialchars($ord['nama_klien']); ?> 
                                <span class="badge <?= $b_class; ?>"><?= strtoupper(htmlspecialchars($ord['status'])); ?></span> 
                                <?php if ($ord['sisa_tagihan'] <= 0): ?>
                                    <span class="badge badge-green">✓ LUNAS 100%</span>
                                <?php elseif ($is_tempat): ?>
                                    <?php if ($ord['status'] !== 'Dibatalkan'): ?>
                                        <span class="badge badge-yellow">BAYAR DI TEMPAT</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge badge-yellow">DP 30% • SISA RP <?= number_format($ord['sisa_tagihan'], 0, ',', '.'); ?></span>
                                <?php endif; ?>
                                <span class="oc-code"><?= htmlspecialchars($ord['kode_reservasi']); ?></span>
                            </h3>
                            <p class="oc-package"><?= htmlspecialchars($ord['paket_layanan']); ?></p>
                            <div class="oc-details">
                                <span>📅 <?= date('d M Y', strtotime($ord['tanggal_sesi'])); ?></span>
                                <span>⏰ <?= htmlspecialchars($ord['jam_sesi']); ?></span>
                                <span>📍 <?= htmlspecialchars($ord['lokasi_studio']); ?></span>
                                <span>📱 WA: <?= htmlspecialchars($ord['whatsapp']); ?></span>
                                <?php if (!empty($ord['nama_pasangan'])): ?>
                                    <span>👥 Pasangan/Anggota: <?= htmlspecialchars($ord['nama_pasangan']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="oc-pay-right">
                        <?php if ($is_tempat): ?>
                            <p class="oc-pay-label">PEMBAYARAN</p>
                            <h4 class="oc-pay-amount">Rp <?= number_format($ord['total_harga'], 0, ',', '.'); ?> <span style="font-size:0.75rem; color:var(--ink-soft); font-weight:normal;">/ bayar di kasir</span></h4>
                            <p class="oc-pay-method" style="color: <?= $ord['sisa_tagihan'] <= 0 ? '#2E7D32' : '#E65100'; ?>;">
                                <?= $ord['sisa_tagihan'] <= 0 ? '✓ LUNAS (100%) • DIBAYAR DI KASIR' : 'BELUM DIBAYAR • BAYAR DI TEMPAT'; ?>
                            </p>
                        <?php else: ?>
                        <p class="oc-pay-label">STATUS PEMBAYARAN</p>
                        <h4 class="oc-pay-amount">Rp <?= number_format($ord['jumlah_dibayar'], 0, ',', '.'); ?> <span style="font-size:0.75rem; color:var(--ink-soft); font-weight:normal;">/ Rp <?= number_format($ord['total_harga'], 0, ',', '.'); ?></span></h4>
                        <p class="oc-pay-method" style="color: <?= $ord['sisa_tagihan'] <= 0 ? '#2E7D32' : '#E65100'; ?>;">
                            <?= $ord['sisa_tagihan'] <= 0 ? '✓ LUNAS (100%)' : 'SISA TAGIHAN: Rp ' . number_format($ord['sisa_tagihan'], 0, ',', '.'); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($ord['catatan'])): ?>
                    <div class="oc-notes">
                        <h5>Catatan Khusus Klien:</h5>
                        <p><?= nl2br(htmlspecialchars($ord['catatan'])); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($ord['status'] !== 'Dibatalkan'): ?>
                <div class="oc-actions">
                    <form method="POST" style="display:flex; gap:12px; margin:0; flex-wrap:wrap; justify-content:flex-end;" onsubmit="return (event.submitter && event.submitter.value === 'batalkan') || confirm('Apakah Anda yakin ingin memproses aksi ini?');">
                        <input type="hidden" name="order_id" value="<?= $ord['id']; ?>">
                        <?php if ($ord['status'] !== 'Dibatalkan' && $ord['status'] !== 'Selesai'): ?>
                            <button type="submit" name="aksi" value="batalkan" class="btn-cancel">Batalkan Pesanan</button>
                        <?php endif; ?>
                        <?php if ($ord['status'] === 'Dikonfirmasi' && $ord['sisa_tagihan'] <= 0): ?>
                            <button type="submit" name="aksi" value="selesaikan" class="btn-finish">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                Selesaikan Pesanan
                            </button>
                        <?php endif; ?>
                        <?php if ($ord['status'] !== 'Dikonfirmasi' && $ord['status'] !== 'Selesai'): ?>
                            <button type="submit" name="aksi" value="konfirmasi" class="btn-confirm">Setujui &amp; Konfirmasi Sesi</button>
                        <?php endif; ?>
                        <?php if ($ord['status'] === 'Dikonfirmasi' && $ord['sisa_tagihan'] > 0): ?>
                            <button type="submit" name="aksi" value="pelunasan_kasir" class="btn-confirm" style="background:#00A859;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <?= $is_tempat ? 'Tandai Dibayar di Kasir' : 'Tandai Pelunasan di Kasir'; ?> (Rp <?= number_format($ord['sisa_tagihan'], 0, ',', '.'); ?>)
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
</body>
</html>