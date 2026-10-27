<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$nama_admin = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Clara V.';
$current_page = basename($_SERVER['PHP_SELF']);

// Handle Toggle Status / Delete jika ada
$feedback_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_status' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE paket SET status = IF(status='aktif', 'nonaktif', 'aktif') WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $feedback_msg = 'Status paket berhasil diperbarui.';
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM paket WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $feedback_msg = 'Paket berhasil dihapus dari etalase.';
        }
        $stmt->close();
    }
}

// Ambil parameter filter dan pencarian
$filter_kategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : 'all';
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

// Hitung statistik keseluruhan dari database
$stats_query = $conn->query("SELECT COUNT(*) AS total_paket, SUM(kuota_total) AS total_kuota, SUM(kuota_terpakai) AS total_terpakai FROM paket");
$stats = $stats_query->fetch_assoc();
$total_paket = (int)($stats['total_paket'] ?? 0);
$total_kuota = (int)($stats['total_kuota'] ?? 0);
$total_terpakai = (int)($stats['total_terpakai'] ?? 0);
$sisa_kuota = max(0, $total_kuota - $total_terpakai);

// Query paket sesuai filter
$sql = "SELECT * FROM paket WHERE 1=1";
$params = [];
$types = "";

if ($filter_kategori !== 'all' && !empty($filter_kategori)) {
    $sql .= " AND kategori = ?";
    $params[] = $filter_kategori;
    $types .= "s";
}

if (!empty($search_query)) {
    $sql .= " AND (nama_paket LIKE ? OR deskripsi LIKE ? OR sub_tag LIKE ?)";
    $like = "%" . $search_query . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

$sql .= " ORDER BY kategori ASC, harga ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$pakets = [];
while ($row = $result->fetch_assoc()) {
    $pakets[] = $row;
}

// ── Pengaturan tampilan kartu etalase ──────────────────────────────
$ambang_kritis     = 80;                     // % kuota terpakai untuk label "Hampir habis"
$dp_default_persen = 30;                     // dipakai jika kolom `dp_minimal` belum ada / kosong
$form_action       = htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'admin_etalase.php', ENT_QUOTES);

// Ikon inline (stroke, gaya Lucide)
function ikon($nama, $ukuran = 15) {
    static $path = [
        'clock'   => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/>',
        'sliders' => '<line x1="21" y1="4" x2="14" y2="4"/><line x1="10" y1="4" x2="3" y2="4"/><line x1="21" y1="12" x2="12" y2="12"/><line x1="8" y1="12" x2="3" y2="12"/><line x1="21" y1="20" x2="16" y2="20"/><line x1="12" y1="20" x2="3" y2="20"/><line x1="14" y1="2" x2="14" y2="6"/><line x1="8" y1="10" x2="8" y2="14"/><line x1="16" y1="18" x2="16" y2="22"/>',
        'book'    => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'users'   => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'palette' => '<circle cx="13.5" cy="6.5" r="1"/><circle cx="17.5" cy="10.5" r="1"/><circle cx="8.5" cy="7.5" r="1"/><circle cx="6.5" cy="12.5" r="1"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.9 0 1.5-.7 1.5-1.5 0-.4-.2-.8-.4-1.1-.3-.3-.4-.7-.4-1.1 0-.8.7-1.5 1.5-1.5H16c3.3 0 6-2.7 6-6 0-4.9-4.5-9-10-9z"/>',
        'badge'   => '<path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.78 4.78 4 4 0 0 1-6.74 0 4 4 0 0 1-4.78-4.78 4 4 0 0 1 0-6.75z"/><polyline points="9 12 11 14 15 10"/>',
        'image'   => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
        'check'   => '<circle cx="12" cy="12" r="9"/><polyline points="8 12 11 15 16 9"/>',
        'plus'    => '<circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
        'pencil'  => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>',
        'upload'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'trash'   => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
    ];
    $isi = $path[$nama] ?? $path['check'];
    return '<svg width="'.$ukuran.'" height="'.$ukuran.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$isi.'</svg>';
}

// Pilih ikon chip berdasarkan kata kunci pada teks fitur
function ikon_fitur($teks) {
    $t = strtolower($teks);
    if (preg_match('/retouch|koreksi|edit/', $t))                 return 'sliders';
    if (preg_match('/album|cetak|print|kanvas|frame|bingkai/', $t)) return 'book';
    if (preg_match('/orang|anggota|peserta|keluarga|maks|max/', $t)) return 'users';
    if (preg_match('/backdrop|background|latar|tema|konsep/', $t)) return 'palette';
    if (preg_match('/hak cipta|lisensi|license|copyright/', $t))  return 'badge';
    if (preg_match('/master|file|foto|soft/', $t))                return 'image';
    return 'check';
}

// Chip fitur: durasi lebih dulu, lalu isi kolom `fitur` (dipisah koma / baris baru / "|")
function daftar_fitur($p, $maks = 4) {
    $hasil = [];
    if (!empty($p['durasi'])) $hasil[] = ['clock', $p['durasi']];
    $mentah = $p['fitur'] ?? '';
    if ($mentah !== '') {
        foreach (preg_split('/[\r\n|,]+/', $mentah) as $f) {
            $f = trim($f);
            if ($f !== '') $hasil[] = [ikon_fitur($f), $f];
        }
    }
    return array_slice($hasil, 0, $maks);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Etalase & Stok Paket - Creative Pixel Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
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
.admin-topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; padding-bottom: 20px; border-bottom: 1px solid var(--line); }
.search-box-top { display: flex; align-items: center; background: #fff; border: 1px solid var(--line); border-radius: 999px; padding: 8px 20px; width: 350px; gap: 10px; }
.search-box-top input { border: none; outline: none; width: 100%; font-size: 0.85rem; background: transparent; }
.user-profile { display: flex; align-items: center; gap: 12px; text-align: right; }
.user-profile h4 { margin: 0; font-size: 0.85rem; font-weight: 700; color: var(--ink); }
.user-profile p { margin: 0; font-size: 0.7rem; color: var(--ink-soft); }

.dash-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
.dash-title-kicker { font-size: 0.7rem; font-weight: 700; color: #8F764F; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 6px; }
.dash-title { font-family: var(--font-serif); font-size: 2.2rem; font-weight: 500; margin: 0 0 8px; color: var(--ink); }
.dash-subtitle { font-size: 0.85rem; color: var(--ink-soft); margin: 0; max-width: 600px; line-height: 1.5; }

.btn-dark-primary { background: #111; color: #fff; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 8px; text-decoration: none; transition: 0.2s; }
.btn-dark-primary:hover { background: #333; }

.stats-strip { display: flex; background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 20px 0; margin-bottom: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.01); }
.ss-item { flex: 1; padding: 0 24px; border-right: 1px dashed var(--line); display: flex; justify-content: space-between; align-items: center; }
.ss-item:last-child { border-right: none; }
.ss-label { font-size: 0.65rem; font-weight: 700; color: var(--ink-faint); text-transform: uppercase; margin: 0 0 6px; }
.ss-value { font-family: var(--font-serif); font-size: 1.5rem; font-weight: 500; color: var(--ink); margin: 0; }

.filter-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
.filter-pills-row { display: flex; gap: 8px; flex-wrap: wrap; }
.pill-tab { padding: 8px 16px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; text-decoration: none; color: var(--ink-soft); background: #fff; border: 1px solid var(--line); transition: 0.2s; }
.pill-tab:hover, .pill-tab.active { background: #1B1A17; color: #fff; border-color: #1B1A17; }

.catalog-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 40px; }
@media (max-width: 1200px) { .catalog-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) { .catalog-grid { grid-template-columns: 1fr; } }

/* ── Kartu paket etalase ─────────────────────────────────────── */
.cat-card { --cc-gold: #7A5C0A; --cc-ink: #1B1A17; --cc-muted: #6B665B; position: relative; display: flex; flex-direction: column; background: #fff; border: 1px solid #ECE8DF; border-radius: 18px; overflow: hidden; box-shadow: 0 6px 24px rgba(27,26,23,0.05); transition: box-shadow 0.2s; }
.cat-card:hover { box-shadow: 0 10px 30px rgba(27,26,23,0.09); }
.cat-card.is-alert { border-color: #F2B8C6; box-shadow: 0 6px 24px rgba(200,30,60,0.08); }

/* Header foto + harga */
.cc-header { position: relative; height: 176px; background: #3b362d center / cover no-repeat; }
.cc-header::after { content: ""; position: absolute; inset: 0; background: linear-gradient(to top, rgba(20,16,10,0.8) 0%, rgba(20,16,10,0.35) 38%, rgba(20,16,10,0) 66%); pointer-events: none; }
.cc-badge { position: absolute; top: 12px; left: 12px; z-index: 2; max-width: calc(100% - 170px); background: rgba(255,255,255,0.94); color: var(--cc-gold); font-size: 0.64rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; padding: 6px 12px; border-radius: 999px; line-height: 1.2; }

.cc-status { position: absolute; top: 12px; right: 12px; z-index: 2; display: flex; align-items: center; gap: 8px; margin: 0; padding: 5px 6px 5px 12px; background: rgba(255,255,255,0.94); border-radius: 999px; font-size: 0.72rem; font-weight: 600; }
.cc-status.on .cc-status-text { color: #1E7B4F; }
.cc-status.off .cc-status-text { color: #4B4740; }
.cc-switch { position: relative; width: 34px; height: 20px; padding: 0; border: none; border-radius: 999px; background: #CFCAC0; cursor: pointer; transition: background 0.2s; }
.cc-switch::after { content: ""; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.25); transition: transform 0.2s; }
.cc-switch[aria-checked="true"] { background: var(--cc-gold); }
.cc-switch[aria-checked="true"]::after { transform: translateX(14px); }
.cc-switch:focus-visible { outline: 2px solid var(--cc-gold); outline-offset: 2px; }

.cc-price { position: absolute; left: 16px; right: 16px; bottom: 14px; z-index: 2; display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; color: #fff; }
.cc-price small { display: block; margin-bottom: 3px; font-size: 0.6rem; font-weight: 600; letter-spacing: 0.09em; text-transform: uppercase; opacity: 0.85; }
.cc-price-main { font-family: 'Playfair Display', var(--font-serif, serif); font-size: 1.45rem; font-weight: 600; line-height: 1.1; }
.cc-price-side { text-align: right; }
.cc-price-side strong { font-size: 0.82rem; font-weight: 600; }

/* Isi kartu */
.cc-body { flex: 1; display: flex; flex-direction: column; padding: 20px; }
.cc-title-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 8px; }
.cc-title-row h3 { margin: 0; font-family: 'Playfair Display', var(--font-serif, serif); font-size: 1.2rem; font-weight: 700; line-height: 1.3; color: var(--cc-ink); }
.cc-title-row h3 a { color: inherit; text-decoration: none; }
.cc-title-row h3 a:hover { text-decoration: underline; text-underline-offset: 3px; }
.cc-tag { flex-shrink: 0; max-width: 110px; margin-top: 2px; padding: 5px 11px; border-radius: 999px; background: #EFEDE8; color: #57534A; font-size: 0.62rem; font-weight: 700; letter-spacing: 0.06em; line-height: 1.25; text-align: center; text-transform: uppercase; }
.cc-tag.tag-gold { background: #FCE7A8; color: #5C4300; }
.cc-tag.tag-alert { background: #FDDCDC; color: #C0262D; }

.cc-desc { margin: 0 0 14px; font-size: 0.84rem; line-height: 1.55; color: var(--cc-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

.cc-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
.cc-chip { display: inline-flex; align-items: center; gap: 7px; padding: 7px 11px; background: #F5F2EB; border-radius: 9px; font-size: 0.78rem; font-weight: 500; color: #2E2B25; }
.cc-chip svg { flex-shrink: 0; color: #756B52; }

.cc-foot { margin-top: auto; }

/* Bar kuota */
.cc-quota { padding: 11px 14px 12px; background: #F5F3EE; border-radius: 12px; }
.cc-quota-row { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 2px 10px; margin-bottom: 8px; font-size: 0.82rem; color: var(--cc-muted); }
.cc-quota-row strong { font-weight: 700; color: var(--cc-ink); }
.cc-quota-cap { font-size: 0.62rem; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; }
.cc-bar { height: 6px; background: #E3DFD5; border-radius: 999px; overflow: hidden; }
.cc-bar span { display: block; height: 100%; background: var(--cc-gold); border-radius: 999px; }
.cc-quota.is-alert { background: #FDF3F3; }
.cc-quota.is-alert .cc-quota-cap, .cc-quota.is-alert .cc-quota-row strong { color: #C81E1E; }
.cc-quota.is-alert .cc-bar { background: #F3D3D3; }
.cc-quota.is-alert .cc-bar span { background: #C81E1E; }
.cc-quota.is-awal .cc-quota-row { align-items: center; }

/* Tombol aksi */
.cc-actions { display: flex; align-items: stretch; gap: 8px; margin-top: 16px; }
.cc-actions form { display: contents; }
.cc-btn { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 44px; padding: 8px 12px; border: none; border-radius: 10px; font: inherit; font-size: 0.8rem; font-weight: 600; line-height: 1.15; text-align: center; text-decoration: none; cursor: pointer; transition: background 0.15s, color 0.15s; }
.cc-btn svg { flex-shrink: 0; }
.cc-btn:focus-visible { outline: 2px solid var(--cc-gold); outline-offset: 2px; }
.cc-btn-soft { background: #F3F1EB; color: #2E2B25; }
.cc-btn-soft:hover { background: #E8E4DA; }
.cc-btn-dark { background: #000; color: #fff; }
.cc-btn-dark:hover { background: #2A2A2A; }
.cc-btn-gold { background: var(--cc-gold); color: #fff; }
.cc-btn-gold:hover { background: #634A06; }
.cc-btn-icon { flex: 0 0 40px; padding: 0; }
.cc-btn-icon.danger:hover { background: #FDE3E3; color: #B42318; }

.alert-toast { background: #DEF7EC; color: #03543F; border: 1px solid #BCF0DA; padding: 12px 20px; border-radius: 8px; font-size: 0.85rem; font-weight: 500; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
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
        <form method="GET" action="admin_etalase.php" class="search-box-top">
            <?php if($filter_kategori !== 'all'): ?><input type="hidden" name="kategori" value="<?= htmlspecialchars($filter_kategori); ?>"><?php endif; ?>
            <input type="text" name="q" value="<?= htmlspecialchars($search_query); ?>" placeholder="Cari nama paket, sub-tag, atau deskripsi...">
            <button type="submit" style="background:none; border:none; cursor:pointer; color:var(--ink-soft);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </button>
        </form>
        <div class="user-profile">
            <div><h4><?= htmlspecialchars($nama_admin); ?></h4><p>Studio Administrator</p></div>
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_admin); ?>&background=1B1A17&color=fff" alt="Avatar" style="width:40px; height:40px; border-radius:50%;">
        </div>
    </div>

    <?php if (!empty($feedback_msg) || isset($_GET['msg'])): ?>
    <div class="alert-toast">
        <span>✓ <?= !empty($feedback_msg) ? htmlspecialchars($feedback_msg) : ($_GET['msg'] === 'saved' ? 'Data paket berhasil disimpan ke database.' : 'Perubahan kuota berhasil disimpan.'); ?></span>
        <button onclick="this.parentElement.style.display='none'" style="background:none; border:none; cursor:pointer; font-size:1rem; color:#03543F;">×</button>
    </div>
    <?php endif; ?>

    <div class="dash-header">
        <div>
            <p class="dash-title-kicker">CATALOG MANAGEMENT</p>
            <h1 class="dash-title">Kelola Etalase & Stok Paket Fotografi</h1>
            <p class="dash-subtitle">Katalog paket database terpadu. Perubahan harga, kuota slot per bulan, dan status langsung tersinkronisasi 100% ke etalase pelanggan.</p>
        </div>
        <div style="display:flex; gap:12px;">
            <a href="admin_atur_kuota.php" class="btn-dark-primary" style="background:#8F764F;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                Atur Kuota Massal
            </a>
            <a href="admin_edit_paket.php" class="btn-dark-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Tambah Paket Baru
            </a>
        </div>
    </div>

    <!-- Statistik Real-Time dari Database -->
    <div class="stats-strip">
        <div class="ss-item">
            <div>
                <p class="ss-label">TOTAL PAKET ETALASE</p>
                <h3 class="ss-value"><?= $total_paket; ?> Paket</h3>
            </div>
        </div>
        <div class="ss-item">
            <div>
                <p class="ss-label">TERPESAN / KAPASITAS</p>
                <h3 class="ss-value"><?= $total_terpakai; ?> / <?= $total_kuota; ?> Slot</h3>
            </div>
        </div>
        <div class="ss-item">
            <div>
                <p class="ss-label">SIAP DIAMBIL (SISA KUOTA)</p>
                <h3 class="ss-value" style="color:var(--accent);"><?= $sisa_kuota; ?> Slot</h3>
            </div>
        </div>
    </div>

    <!-- Filter Kategori Tabs -->
    <div class="filter-bar">
        <div class="filter-pills-row">
            <a href="admin_etalase.php?kategori=all" class="pill-tab <?= $filter_kategori === 'all' ? 'active' : ''; ?>">Semua Kategori (<?= $total_paket; ?>)</a>
            <a href="admin_etalase.php?kategori=wisuda" class="pill-tab <?= $filter_kategori === 'wisuda' ? 'active' : ''; ?>">Wisuda & Akademik</a>
            <a href="admin_etalase.php?kategori=portrait" class="pill-tab <?= $filter_kategori === 'portrait' ? 'active' : ''; ?>">Editorial Portrait</a>
            <a href="admin_etalase.php?kategori=prewedding" class="pill-tab <?= $filter_kategori === 'prewedding' ? 'active' : ''; ?>">Prewedding</a>
            <a href="admin_etalase.php?kategori=family" class="pill-tab <?= $filter_kategori === 'family' ? 'active' : ''; ?>">Family & Group</a>
        </div>
        <?php if(!empty($search_query) || $filter_kategori !== 'all'): ?>
            <a href="admin_etalase.php" style="font-size:0.8rem; color:var(--red); text-decoration:none; font-weight:600;">✕ Reset Filter</a>
        <?php endif; ?>
    </div>

    <!-- Grid Paket Database -->
    <?php if(empty($pakets)): ?>
        <div style="background:#fff; border:1px dashed var(--line); border-radius:12px; padding:48px; text-align:center;">
            <p style="color:var(--ink-soft); font-size:1rem; margin-bottom:16px;">Tidak ada paket fotografi yang sesuai dengan filter pencarian.</p>
            <a href="admin_etalase.php" class="btn-dark-primary" style="display:inline-flex;">Lihat Semua Paket</a>
        </div>
    <?php else: ?>
        <div class="catalog-grid">
            <?php foreach($pakets as $p):
                $id         = (int)$p['id'];
                $harga      = (int)$p['harga'];
                $total      = (int)$p['kuota_total'];
                $terpakai   = (int)$p['kuota_terpakai'];
                $sisa_slot  = max(0, $total - $terpakai);
                $pct        = $total > 0 ? min(100, round($terpakai / $total * 100)) : 0;

                $is_aktif   = $p['status'] === 'aktif';
                $is_habis   = $total > 0 && $sisa_slot <= 0;
                $is_kritis  = $total > 0 && !$is_habis && $pct >= $ambang_kritis;
                $is_alert   = $is_aktif && ($is_habis || $is_kritis);
                $kuota_awal = $terpakai === 0 && (!$is_aktif || $total === 0);

                // DP minimal: pakai kolom dp_minimal bila ada, jika tidak hitung dari persentase default
                if ((int)($p['dp_minimal'] ?? 0) > 0) {
                    $dp     = (int)$p['dp_minimal'];
                    $dp_pct = $harga > 0 ? round($dp / $harga * 100) : 0;
                } else {
                    $dp     = (int)(round($harga * $dp_default_persen / 100 / 100000) * 100000);
                    $dp_pct = $dp_default_persen;
                }

                // Label di samping judul
                $sub = trim($p['sub_tag'] ?? '');
                $tag_class = '';
                if ($is_alert) {
                    $tag_teks  = $is_habis ? 'Slot habis!' : 'Hampir habis!';
                    $tag_class = 'tag-alert';
                } else {
                    $tag_teks  = $sub;
                    if (preg_match('/best\s*seller|terlaris|favorit/i', $sub)) $tag_class = 'tag-gold';
                }

                $fitur = daftar_fitur($p);
                $gambar = htmlspecialchars(str_replace("'", '%27', $p['gambar'] ?? ''), ENT_QUOTES);
            ?>
            <article class="cat-card<?= $is_alert ? ' is-alert' : ''; ?>">
                <div class="cc-header" style="background-image:url('<?= $gambar; ?>');">
                    <span class="cc-badge"><?= htmlspecialchars($p['kategori_label'] ?? ''); ?></span>

                    <!-- Toggle tayang / draft (memakai aksi toggle_status yang sudah ada) -->
                    <form method="POST" action="<?= $form_action; ?>" class="cc-status <?= $is_aktif ? 'on' : 'off'; ?>"<?= $is_aktif ? ' onsubmit="return confirm(\'Sembunyikan paket ini dari etalase pelanggan?\');"' : ''; ?>>
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="id" value="<?= $id; ?>">
                        <span class="cc-status-text"><?= $is_aktif ? 'Tayang' : 'Draft / Nonaktif'; ?></span>
                        <button type="submit" class="cc-switch" role="switch" aria-checked="<?= $is_aktif ? 'true' : 'false'; ?>" aria-label="<?= $is_aktif ? 'Sembunyikan paket dari etalase' : 'Tayangkan paket di etalase'; ?>"></button>
                    </form>

                    <div class="cc-price">
                        <div>
                            <small>Harga paket</small>
                            <strong class="cc-price-main">Rp <?= number_format($harga, 0, ',', '.'); ?></strong>
                        </div>
                        <div class="cc-price-side">
                            <?php if ($is_aktif): ?>
                                <small>DP minimal</small>
                                <strong>Rp <?= number_format($dp, 0, ',', '.'); ?> (<?= $dp_pct; ?>%)</strong>
                            <?php else: ?>
                                <small>Status etalase</small>
                                <strong>Tersembunyi</strong>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="cc-body">
                    <div class="cc-title-row">
                        <h3><a href="admin_edit_paket.php?id=<?= $id; ?>"><?= htmlspecialchars($p['nama_paket']); ?></a></h3>
                        <?php if ($tag_teks !== ''): ?>
                            <span class="cc-tag <?= $tag_class; ?>"><?= htmlspecialchars($tag_teks); ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="cc-desc"><?= htmlspecialchars($p['deskripsi'] ?? ''); ?></p>

                    <?php if (!empty($fitur)): ?>
                    <div class="cc-chips">
                        <?php foreach ($fitur as $f): ?>
                            <span class="cc-chip"><?= ikon($f[0], 14); ?><?= htmlspecialchars($f[1]); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="cc-foot">
                        <?php if ($kuota_awal): ?>
                            <div class="cc-quota is-awal">
                                <div class="cc-quota-row">
                                    <span class="cc-quota-cap">Alokasi slot awal</span>
                                    <strong><?= $total > 0 ? $total . ' Slot disiapkan' : 'Belum diatur'; ?></strong>
                                </div>
                                <div class="cc-bar"><span style="width:0%;"></span></div>
                            </div>
                        <?php elseif ($is_alert): ?>
                            <div class="cc-quota is-alert">
                                <div class="cc-quota-row">
                                    <span class="cc-quota-cap"><?= $is_habis ? 'Slot penuh' : 'Tersisa kritis'; ?></span>
                                    <strong><?= $is_habis ? 'Slot habis!' : 'Hanya ' . $sisa_slot . ' Slot!'; ?></strong>
                                    <span>(<?= $terpakai; ?> / <?= $total; ?> Sesi Terisi)</span>
                                </div>
                                <div class="cc-bar"><span style="width:<?= $pct; ?>%;"></span></div>
                            </div>
                        <?php else: ?>
                            <div class="cc-quota">
                                <div class="cc-quota-row">
                                    <strong>Sisa <?= $sisa_slot; ?> Slot</strong>
                                    <span>(<?= $terpakai; ?> / <?= $total; ?> Sesi Terisi)</span>
                                </div>
                                <div class="cc-bar"><span style="width:<?= $pct; ?>%;"></span></div>
                            </div>
                        <?php endif; ?>

                        <div class="cc-actions">
                            <?php if ($is_alert): ?>
                                <a href="admin_atur_kuota.php?id=<?= $id; ?>" class="cc-btn cc-btn-gold"><?= ikon('plus'); ?>Tambah Slot Kuota</a>
                                <a href="admin_edit_paket.php?id=<?= $id; ?>" class="cc-btn cc-btn-soft"><?= ikon('pencil'); ?>Edit Paket</a>
                            <?php elseif (!$is_aktif): ?>
                                <a href="admin_atur_kuota.php?id=<?= $id; ?>" class="cc-btn cc-btn-soft"><?= ikon('sliders'); ?>Atur Kuota</a>
                                <form method="POST" action="<?= $form_action; ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= $id; ?>">
                                    <button type="submit" class="cc-btn cc-btn-dark"><?= ikon('upload'); ?>Publikasikan</button>
                                </form>
                            <?php else: ?>
                                <a href="admin_atur_kuota.php?id=<?= $id; ?>" class="cc-btn cc-btn-soft"><?= ikon('sliders'); ?>Atur Kuota</a>
                                <a href="admin_edit_paket.php?id=<?= $id; ?>" class="cc-btn cc-btn-dark"><?= ikon('pencil'); ?>Edit Paket</a>
                            <?php endif; ?>

                            <form method="POST" action="<?= $form_action; ?>" onsubmit="return confirm('Apakah Anda yakin ingin menghapus paket ini dari database?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $id; ?>">
                                <button type="submit" class="cc-btn cc-btn-soft cc-btn-icon danger" title="Hapus paket" aria-label="Hapus paket"><?= ikon('trash', 16); ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>