<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$nama_admin   = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Admin';
$current_page = basename($_SERVER['PHP_SELF']);

// Kuota sesi per hari kalau tanggal itu belum diatur manual (ubah angka ini sesuai kapasitas studio)
const KUOTA_DEFAULT = 4;

// -------------------------------------------------------
// Tabel slot harian (dibuat otomatis kalau belum ada)
// -------------------------------------------------------
$conn->query("CREATE TABLE IF NOT EXISTS slot_harian (
    tanggal DATE NOT NULL PRIMARY KEY,
    kuota INT NOT NULL DEFAULT " . KUOTA_DEFAULT . ",
    tutup TINYINT(1) NOT NULL DEFAULT 0,
    catatan VARCHAR(150) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Sinkronisasi kuota paket (tetap dipertahankan supaya Etalase & Atur Kuota Paket tidak berubah)
$conn->query("
    UPDATE paket p
    SET p.kuota_terpakai = GREATEST(
        p.kuota_terpakai,
        COALESCE((SELECT COUNT(*) FROM pesanan ps
                  WHERE ps.paket_layanan = p.nama_paket
                    AND ps.status NOT IN ('Dibatalkan', 'Menunggu Pembayaran')), 0)
    )
");

// -------------------------------------------------------
// Helper tanggal Indonesia
// -------------------------------------------------------
function hari_id($d)  { static $h = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu']; return $h[(int)date('w', strtotime($d))]; }
function bulan_id($n) { static $b = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']; return $b[(int)$n]; }
function tgl_lengkap($d) { $t = strtotime($d); return hari_id($d) . ', ' . date('j', $t) . ' ' . bulan_id(date('n', $t)) . ' ' . date('Y', $t); }
function tgl_valid($t) { return is_string($t) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $t, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]); }

function status_slot($kuota, $terisi, $tutup) {
    if ($tutup) return 'tutup';
    $sisa = max(0, $kuota - $terisi);
    if ($kuota <= 0 || $sisa <= 0) return 'penuh';
    if ($sisa / $kuota <= 0.34) return 'terbatas';
    return 'tersedia';
}
$label_status = ['tersedia' => 'Tersedia', 'terbatas' => 'Slot Terbatas', 'penuh' => 'Penuh', 'tutup' => 'Tutup'];

// -------------------------------------------------------
// Parameter tampilan
// -------------------------------------------------------
$hari_ini = date('Y-m-d');
$bulan    = isset($_GET['bulan'])  ? max(1, min(12, (int)$_GET['bulan'])) : (int)date('n');
$tahun    = isset($_GET['tahun'])  ? max(2020, min(2100, (int)$_GET['tahun'])) : (int)date('Y');
$tampil   = (isset($_GET['tampil']) && $_GET['tampil'] === 'daftar') ? 'daftar' : 'kalender';
$f_status = (isset($_GET['status']) && in_array($_GET['status'], ['tersedia','terbatas','penuh','tutup'], true)) ? $_GET['status'] : 'all';

$awal_bulan  = sprintf('%04d-%02d-01', $tahun, $bulan);
$akhir_bulan = date('Y-m-t', strtotime($awal_bulan));
$jumlah_hari = (int)date('t', strtotime($awal_bulan));

// Tanggal yang dipilih harus berada di bulan yang sedang dilihat
$tgl = isset($_GET['tgl']) && tgl_valid($_GET['tgl']) ? $_GET['tgl'] : '';
if ($tgl === '' || $tgl < $awal_bulan || $tgl > $akhir_bulan) {
    $tgl = (substr($hari_ini, 0, 7) === substr($awal_bulan, 0, 7)) ? $hari_ini : $awal_bulan;
}

function url_jadwal($over = []) {
    global $bulan, $tahun, $tampil, $f_status, $tgl;
    $p = array_merge(['bulan' => $bulan, 'tahun' => $tahun, 'tampil' => $tampil, 'status' => $f_status, 'tgl' => $tgl], $over);
    if ($p['status'] === 'all') unset($p['status']);
    return 'admin_jadwal.php?' . http_build_query($p);
}

// -------------------------------------------------------
// Proses simpan (POST)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $flash = ['ok', 'Perubahan tidak tersimpan.'];

    if ($_POST['aksi'] === 'simpan_hari' && tgl_valid($_POST['tanggal'] ?? '')) {
        $t       = $_POST['tanggal'];
        $kuota   = max(0, min(99, (int)($_POST['kuota'] ?? 0)));
        $tutup   = isset($_POST['tutup']) ? 1 : 0;
        $catatan = mb_substr(trim($_POST['catatan'] ?? ''), 0, 150);
        $catatan = $catatan === '' ? null : $catatan;

        $stmt = $conn->prepare("INSERT INTO slot_harian (tanggal, kuota, tutup, catatan) VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE kuota = VALUES(kuota), tutup = VALUES(tutup), catatan = VALUES(catatan)");
        $stmt->bind_param("siis", $t, $kuota, $tutup, $catatan);
        $stmt->execute();
        $stmt->close();

        // Cek apakah ada booking yang sudah masuk di tanggal itu
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM pesanan WHERE tanggal_sesi = ? AND status NOT LIKE '%Batal%' AND status <> 'Menunggu Pembayaran'");
        $stmt->bind_param("s", $t);
        $stmt->execute();
        $terisi = (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();

        $flash = ['ok', 'Slot ' . tgl_lengkap($t) . ' disimpan.'];
        if ($tutup && $terisi > 0)       $flash = ['warn', 'Tanggal ' . tgl_lengkap($t) . ' ditutup, tapi sudah ada ' . $terisi . ' booking di hari itu. Hubungi klien untuk reschedule.'];
        elseif ($kuota < $terisi)        $flash = ['warn', 'Kuota ' . $kuota . ' lebih kecil dari booking yang sudah masuk (' . $terisi . ') pada ' . tgl_lengkap($t) . '.'];

        $_SESSION['flash_jadwal'] = $flash;
        header("Location: " . url_jadwal(['tgl' => $t]));
        exit;
    }

    if ($_POST['aksi'] === 'simpan_bulan') {
        $kuota = max(0, min(99, (int)($_POST['kuota'] ?? KUOTA_DEFAULT)));
        $tutup_minggu = isset($_POST['tutup_minggu']);

        $stmt = $conn->prepare("INSERT INTO slot_harian (tanggal, kuota, tutup) VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE kuota = VALUES(kuota), tutup = IF(VALUES(tutup) = 1, 1, tutup)");
        for ($d = 1; $d <= $jumlah_hari; $d++) {
            $t = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
            $tutup = ($tutup_minggu && date('w', strtotime($t)) == 0) ? 1 : 0;
            $stmt->bind_param("sii", $t, $kuota, $tutup);
            $stmt->execute();
        }
        $stmt->close();

        $_SESSION['flash_jadwal'] = ['ok', 'Kuota ' . $kuota . ' sesi/hari diterapkan ke seluruh ' . bulan_id($bulan) . ' ' . $tahun . '.'];
        header("Location: " . url_jadwal());
        exit;
    }

    $_SESSION['flash_jadwal'] = $flash;
    header("Location: " . url_jadwal());
    exit;
}

$flash_msg = $_SESSION['flash_jadwal'] ?? null;
unset($_SESSION['flash_jadwal']);

// -------------------------------------------------------
// Ambil data slot 1 bulan
// -------------------------------------------------------
$override = [];
$stmt = $conn->prepare("SELECT tanggal, kuota, tutup, catatan FROM slot_harian WHERE tanggal BETWEEN ? AND ?");
$stmt->bind_param("ss", $awal_bulan, $akhir_bulan);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $override[$row['tanggal']] = $row;
$stmt->close();

$hitung = [];
$stmt = $conn->prepare("SELECT tanggal_sesi AS t,
                               SUM(status <> 'Menunggu Pembayaran') AS terisi,
                               SUM(status = 'Menunggu Pembayaran') AS pending
                        FROM pesanan
                        WHERE tanggal_sesi BETWEEN ? AND ? AND status NOT LIKE '%Batal%'
                        GROUP BY tanggal_sesi");
$stmt->bind_param("ss", $awal_bulan, $akhir_bulan);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $hitung[$row['t']] = ['terisi' => (int)$row['terisi'], 'pending' => (int)$row['pending']];
$stmt->close();

$hari = [];
$stat = ['kuota' => 0, 'terisi' => 0, 'sisa' => 0, 'penuh' => 0, 'tutup' => 0];
for ($d = 1; $d <= $jumlah_hari; $d++) {
    $t  = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
    $ov = $override[$t] ?? null;
    $kuota  = $ov ? (int)$ov['kuota'] : KUOTA_DEFAULT;
    $tutup  = $ov ? (int)$ov['tutup'] : 0;
    $terisi = $hitung[$t]['terisi'] ?? 0;
    $pend   = $hitung[$t]['pending'] ?? 0;
    $sisa   = max(0, $kuota - $terisi);
    $st     = status_slot($kuota, $terisi, $tutup);

    $hari[$t] = [
        'tgl' => $t, 'd' => $d, 'hari' => hari_id($t), 'kuota' => $kuota, 'tutup' => $tutup, 'terisi' => $terisi,
        'pending' => $pend, 'sisa' => $sisa, 'status' => $st, 'catatan' => $ov['catatan'] ?? '',
        'lewat' => $t < $hari_ini, 'hari_ini' => $t === $hari_ini,
    ];

    $stat['terisi'] += $terisi;
    if ($st === 'tutup') $stat['tutup']++;
    else { $stat['kuota'] += $kuota; $stat['sisa'] += $sisa; }
    if ($st === 'penuh') $stat['penuh']++;
}

$pilih = $hari[$tgl];

// Booking di tanggal terpilih
$booking_hari = [];
$stmt = $conn->prepare("SELECT ps.*, u.nama AS nama_klien, u.whatsapp FROM pesanan ps
                        JOIN users u ON ps.user_id = u.id
                        WHERE ps.tanggal_sesi = ? AND ps.status NOT LIKE '%Batal%'
                        ORDER BY ps.jam_sesi ASC, ps.id ASC");
$stmt->bind_param("s", $tgl);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $booking_hari[] = $row;
$stmt->close();

// Jadwal 7 hari ke depan
$upcoming = $conn->query(
    "SELECT ps.* FROM pesanan ps
     WHERE ps.tanggal_sesi BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
       AND ps.status NOT LIKE '%Batal%'
     ORDER BY ps.tanggal_sesi ASC, ps.jam_sesi ASC
     LIMIT 20"
)->fetch_all(MYSQLI_ASSOC);

function kelas_status_booking($s) {
    if (stripos($s, 'Lunas') !== false)   return 'ts-lunas';
    if (stripos($s, 'DP') !== false)      return 'ts-dp';
    if (stripos($s, 'Konfirm') !== false) return 'ts-konfirm';
    return 'ts-dp';
}

// Navigasi bulan
$prev = new DateTime($awal_bulan); $prev->modify('-1 month');
$next = new DateTime($awal_bulan); $next->modify('+1 month');
$offset_awal = (int)date('N', strtotime($awal_bulan)) - 1; // Senin = 0
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Jadwal & Kuota Sesi - Creative Pixel Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<style>
:root { --j-green: var(--green, #047857); --j-amber: var(--amber, #B45309); --j-red: var(--red, #DC2626); }

/* ── Layout ── */
body { background-color: #1B1A17; color: #1B1A17; margin: 0; font-family: var(--font-sans); display: flex; min-height: 100vh; }
.admin-sidebar { width: 260px; background: #1B1A17; color: #fff; padding: 24px; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; }
.sidebar-brand { font-family: var(--font-serif); font-size: 1.2rem; color: #fff; margin-bottom: 40px; display: flex; align-items: center; gap: 8px; }
.sidebar-brand span { color: var(--accent); }
.sidebar-menu { display: flex; flex-direction: column; gap: 8px; list-style: none; padding: 0; margin: 0; }
.sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 8px; color: rgba(255,255,255,0.7); text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: 0.2s; }
.sidebar-menu a:hover, .sidebar-menu a.active { background: #2C2B27; color: #fff; }

.admin-main { flex: 1; min-width: 0; background: #F6F5F0; border-top-left-radius: 20px; border-bottom-left-radius: 20px; overflow-y: auto; }

/* ── Topbar ── */
.page-topbar { background: #fff; border-bottom: 1px solid var(--line); padding: 16px 36px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 50; }
.topbar-title { font-size: 0.85rem; font-weight: 600; color: var(--ink); display: flex; align-items: center; gap: 8px; }
.topbar-title .breadcrumb { color: var(--ink-soft); font-weight: 400; }
.topbar-right { display: flex; align-items: center; gap: 12px; }
.sync-badge { background: #DEF7EC; color: #03543F; font-size: 0.7rem; font-weight: 700; padding: 4px 12px; border-radius: 999px; display: flex; align-items: center; gap: 5px; }
.sync-badge::before { content: ''; display: block; width: 6px; height: 6px; background: #03543F; border-radius: 50%; animation: pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }

.page-content { padding: 28px 36px 60px; }
.page-header { margin-bottom: 28px; }
.page-header-kicker { font-size: 0.7rem; font-weight: 700; color: var(--accent); text-transform: uppercase; letter-spacing: 0.07em; margin: 0 0 8px; }
.page-header-title { font-family: var(--font-serif); font-size: 2.1rem; font-weight: 500; margin: 0 0 8px; color: var(--ink); }
.page-header-sub { font-size: 0.85rem; color: var(--ink-soft); margin: 0; line-height: 1.6; max-width: 720px; }

.flash { padding: 12px 18px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; margin-bottom: 20px; }
.flash.ok { background: #DEF7EC; color: #03543F; border: 1px solid #BCF0DA; }
.flash.warn { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }

/* ── Stats ── */
.stats-strip { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 24px; }
.stat-card { background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 16px 20px; display: flex; flex-direction: column; gap: 6px; }
.stat-card-label { font-size: 0.65rem; font-weight: 700; color: var(--ink-faint); text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }
.stat-card-value { font-family: var(--font-serif); font-size: 1.6rem; font-weight: 500; margin: 0; color: var(--ink); }
.stat-card-note { font-size: 0.72rem; color: var(--ink-soft); margin: 0; }
.stat-card.tersedia .stat-card-value { color: var(--j-green); }
.stat-card.terbatas .stat-card-value { color: var(--j-amber); }
.stat-card.habis    .stat-card-value { color: var(--j-red); }

/* ── Control bar ── */
.control-bar { background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 14px 20px; margin-bottom: 24px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.month-nav { display: flex; align-items: center; gap: 8px; }
.nav-btn { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--line); border-radius: 8px; background: #fff; color: var(--ink); text-decoration: none; font-size: 1rem; }
.nav-btn:hover { background: #F3F1EC; }
.month-nav form { display: flex; gap: 6px; margin: 0; }
.month-nav select { border: 1px solid var(--line); border-radius: 8px; padding: 7px 10px; font: 600 0.85rem var(--font-sans); background: #fff; color: var(--ink); }
.filter-sep { width: 1px; height: 28px; background: var(--line); flex-shrink: 0; }
.filter-group { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.filter-label { font-size: 0.7rem; font-weight: 700; color: var(--ink-faint); text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; }
.fpill { padding: 6px 14px; border-radius: 999px; font-size: 0.78rem; font-weight: 600; text-decoration: none; border: 1px solid var(--line); color: var(--ink-soft); background: #fff; transition: 0.15s; white-space: nowrap; }
.fpill:hover { background: #F3F1EC; }
.fpill.active { background: #1B1A17; color: #fff; border-color: #1B1A17; }
.fpill.green.active { background: var(--j-green); border-color: var(--j-green); }
.fpill.amber.active { background: var(--j-amber); border-color: var(--j-amber); }
.fpill.red.active   { background: var(--j-red);   border-color: var(--j-red); }
.fpill.gray.active  { background: #6B7280; border-color: #6B7280; }
.btn-reset-filter { font-size: 0.78rem; color: var(--j-red); font-weight: 600; text-decoration: none; padding: 4px 8px; }

/* ── Two-column ── */
.jadwal-layout { display: grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start; }
@media (max-width: 1100px) { .jadwal-layout { grid-template-columns: 1fr; } }

.panel { background: #fff; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
.panel-head { padding: 16px 20px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
.panel-head h2 { font-family: var(--font-serif); font-size: 1.25rem; font-weight: 500; margin: 0; color: var(--ink); }
.panel-head small { color: var(--ink-soft); font-size: 0.78rem; }

/* ── Kalender ── */
.cal { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; padding: 16px; }
.cal-dow { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ink-faint); text-align: center; padding: 4px 0 6px; }
.cal-empty { min-height: 96px; }
.cal-cell { min-height: 96px; border: 1px solid var(--line); border-radius: 10px; padding: 8px 10px; display: flex; flex-direction: column; gap: 4px; text-decoration: none; color: var(--ink); background: #fff; transition: transform .12s, box-shadow .12s; position: relative; }
.cal-cell:hover { box-shadow: 0 4px 14px rgba(0,0,0,.08); transform: translateY(-1px); }
.cal-num { font-family: var(--font-serif); font-size: 1.15rem; font-weight: 500; line-height: 1; display: flex; justify-content: space-between; align-items: center; }
.cal-num em { font-style: normal; font-family: var(--font-sans); font-size: 0.6rem; font-weight: 700; color: var(--ink-faint); text-transform: uppercase; }
.cal-sisa { font-size: 0.74rem; font-weight: 700; margin-top: auto; }
.cal-frac { font-size: 0.68rem; color: var(--ink-soft); }
.cal-bar { height: 4px; background: rgba(0,0,0,.08); border-radius: 2px; overflow: hidden; }
.cal-bar i { display: block; height: 100%; border-radius: 2px; }
.cal-cell.tersedia { background: #F0FBF6; border-color: #BCF0DA; } .cal-cell.tersedia .cal-sisa { color: #03543F; } .cal-cell.tersedia .cal-bar i { background: var(--j-green); }
.cal-cell.terbatas { background: #FFFAEB; border-color: #FDE68A; } .cal-cell.terbatas .cal-sisa { color: #92400E; } .cal-cell.terbatas .cal-bar i { background: var(--j-amber); }
.cal-cell.penuh    { background: #FEF2F2; border-color: #FECACA; } .cal-cell.penuh .cal-sisa { color: #991B1B; } .cal-cell.penuh .cal-bar i { background: var(--j-red); }
.cal-cell.tutup    { background: repeating-linear-gradient(135deg, #F3F4F6, #F3F4F6 6px, #ECEDEF 6px, #ECEDEF 12px); border-color: #E5E7EB; } .cal-cell.tutup .cal-sisa { color: #6B7280; }
.cal-cell.lewat { opacity: .6; }
.cal-cell.dim { opacity: .25; }
.cal-cell.hari-ini .cal-num::after { content: 'Hari ini'; font-family: var(--font-sans); font-size: 0.58rem; font-weight: 700; background: #1B1A17; color: #fff; padding: 2px 6px; border-radius: 999px; }
.cal-cell.pilih { outline: 2px solid #1B1A17; outline-offset: 1px; }
.cal-legend { display: flex; gap: 16px; flex-wrap: wrap; padding: 0 20px 16px; font-size: 0.74rem; color: var(--ink-soft); }
.cal-legend span { display: inline-flex; align-items: center; gap: 6px; }
.cal-legend i { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }

/* ── Tabel daftar ── */
.slot-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.slot-table th { background: #FAF9F6; text-align: left; padding: 12px 16px; border-bottom: 1px solid var(--line); font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ink-faint); font-weight: 700; white-space: nowrap; }
.slot-table td { padding: 12px 16px; border-bottom: 1px solid var(--line); vertical-align: middle; }
.slot-table tr:hover td { background: #FAFAF8; }
.slot-table tr.pilih td { background: #FFFBEB; }
.slot-table tr.lewat td { color: var(--ink-faint); }
.slot-table .num { text-align: center; font-weight: 700; }
.slot-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 999px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; white-space: nowrap; }
.slot-badge.tersedia { background: #DEF7EC; color: #03543F; }
.slot-badge.terbatas { background: #FEF3C7; color: #92400E; }
.slot-badge.penuh    { background: #FEE2E2; color: #991B1B; }
.slot-badge.tutup    { background: #E5E7EB; color: #4B5563; }
.link-atur { font-size: 0.76rem; font-weight: 700; color: var(--accent); text-decoration: none; }
.table-wrap { overflow-x: auto; }

/* ── Sidebar kanan ── */
.jadwal-sidebar { display: flex; flex-direction: column; gap: 16px; }
.sidebar-card { background: #fff; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
.sidebar-card-header { padding: 14px 18px; border-bottom: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between; }
.sidebar-card-header h3 { font-size: 0.85rem; font-weight: 700; color: var(--ink); margin: 0; }
.detail-body { padding: 18px; }
.detail-date { font-family: var(--font-serif); font-size: 1.35rem; font-weight: 500; margin: 0 0 10px; color: var(--ink); line-height: 1.25; }
.detail-meter { margin: 14px 0 4px; }
.detail-meter .row { display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 6px; }
.detail-meter .row b { font-size: 0.95rem; }
.meter { height: 8px; background: #EEEDE9; border-radius: 4px; overflow: hidden; }
.meter i { display: block; height: 100%; border-radius: 4px; }
.meter i.tersedia { background: var(--j-green); } .meter i.terbatas { background: var(--j-amber); } .meter i.penuh { background: var(--j-red); } .meter i.tutup { background: #9CA3AF; }
.mini-booking { border-top: 1px solid var(--line); padding: 10px 0; font-size: 0.8rem; }
.mini-booking:first-child { border-top: none; }
.mini-booking strong { display: block; color: var(--ink); }
.mini-booking span { color: var(--ink-soft); font-size: 0.74rem; display: block; }
.form-row { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
.form-row label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--ink-faint); }
.form-row input[type=number], .form-row input[type=text] { border: 1px solid var(--line); border-radius: 8px; padding: 9px 12px; font-size: 0.85rem; background: #FAFAFA; font-family: var(--font-sans); }
.form-row input:focus { outline: none; border-color: #1B1A17; background: #fff; }
.check-row { display: flex; align-items: center; gap: 8px; font-size: 0.82rem; margin-bottom: 12px; color: var(--ink); }
.btn-save { width: 100%; background: #1B1A17; color: #fff; border: none; padding: 11px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; }
.btn-save:hover { background: #333; }
.btn-save.light { background: #F3F1EC; color: var(--ink); border: 1px solid var(--line); }
.btn-save.light:hover { background: #E8E6E0; }

.timeline-item { padding: 12px 18px; border-bottom: 1px solid var(--line); display: flex; gap: 12px; align-items: flex-start; }
.timeline-item:last-child { border-bottom: none; }
.timeline-date { flex-shrink: 0; width: 48px; text-align: center; background: #F6F5F0; border-radius: 8px; padding: 6px 4px; text-decoration: none; }
.timeline-date .day { font-family: var(--font-serif); font-size: 1.3rem; font-weight: 500; line-height: 1; color: var(--ink); display: block; }
.timeline-date .mon { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; color: var(--ink-faint); display: block; margin-top: 2px; }
.timeline-info { flex: 1; min-width: 0; }
.timeline-paket { font-size: 0.8rem; font-weight: 700; color: var(--ink); margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.timeline-meta { font-size: 0.72rem; color: var(--ink-soft); margin: 0 0 4px; }
.timeline-status { font-size: 0.65rem; font-weight: 700; padding: 2px 8px; border-radius: 4px; display: inline-block; }
.ts-dp { background: #FEF3C7; color: #92400E; } .ts-lunas { background: #DEF7EC; color: #03543F; } .ts-konfirm { background: #DBEAFE; color: #1E40AF; }
.quick-link { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: #F6F5F0; border-radius: 8px; text-decoration: none; font-size: 0.82rem; font-weight: 600; color: var(--ink); }
.quick-link:hover { background: #EDECEA; }

@media (max-width: 768px) {
    .stats-strip { grid-template-columns: repeat(2, 1fr); }
    .cal { gap: 4px; padding: 10px; }
    .cal-cell { min-height: 74px; padding: 6px; }
    .cal-frac, .cal-num em { display: none; }
    .page-content { padding: 20px 16px 48px; }
}
</style>
</head>
<body>

<!-- ======== SIDEBAR ======== -->
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

<!-- ======== MAIN ======== -->
<main class="admin-main">

    <div class="page-topbar">
        <div class="topbar-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ink-soft)" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            <span class="breadcrumb">Admin</span> / Jadwal &amp; Kuota Sesi
        </div>
        <div class="topbar-right">
            <span class="sync-badge">Tersinkronisasi Otomatis</span>
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="text-align:right;">
                    <div style="font-size:0.82rem; font-weight:700; color:var(--ink);"><?= htmlspecialchars($nama_admin); ?></div>
                    <div style="font-size:0.68rem; color:var(--ink-soft);">Studio Administrator</div>
                </div>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_admin); ?>&background=1B1A17&color=fff" alt="Avatar" style="width:36px; height:36px; border-radius:50%;">
            </div>
        </div>
    </div>

    <div class="page-content">

        <div class="page-header">
            <p class="page-header-kicker">SCHEDULE &amp; QUOTA MANAGEMENT</p>
            <h1 class="page-header-title">Jadwal &amp; Kuota Sesi</h1>
            <p class="page-header-sub">Slot sesi per hari, tanggal, bulan, dan tahun. Setiap booking yang masuk otomatis mengurangi sisa slot di tanggalnya, jadi ketersediaan studio langsung kelihatan.</p>
        </div>

        <?php if ($flash_msg): ?>
        <div class="flash <?= htmlspecialchars($flash_msg[0]); ?>"><?= htmlspecialchars($flash_msg[1]); ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-strip">
            <div class="stat-card">
                <p class="stat-card-label">Total Slot Bulan Ini</p>
                <h3 class="stat-card-value"><?= $stat['kuota']; ?></h3>
                <p class="stat-card-note"><?= bulan_id($bulan) . ' ' . $tahun; ?></p>
            </div>
            <div class="stat-card">
                <p class="stat-card-label">Slot Terisi</p>
                <h3 class="stat-card-value"><?= $stat['terisi']; ?></h3>
                <p class="stat-card-note">Booking terkonfirmasi / DP</p>
            </div>
            <div class="stat-card tersedia">
                <p class="stat-card-label">Sisa Slot</p>
                <h3 class="stat-card-value"><?= $stat['sisa']; ?></h3>
                <p class="stat-card-note">Masih bisa dipesan</p>
            </div>
            <div class="stat-card habis">
                <p class="stat-card-label">Hari Penuh</p>
                <h3 class="stat-card-value"><?= $stat['penuh']; ?></h3>
                <p class="stat-card-note">Full booked</p>
            </div>
            <div class="stat-card">
                <p class="stat-card-label">Hari Tutup</p>
                <h3 class="stat-card-value"><?= $stat['tutup']; ?></h3>
                <p class="stat-card-note">Libur / tidak menerima sesi</p>
            </div>
        </div>

        <!-- Control bar -->
        <div class="control-bar">
            <div class="month-nav">
                <a class="nav-btn" href="<?= url_jadwal(['bulan' => (int)$prev->format('n'), 'tahun' => (int)$prev->format('Y'), 'tgl' => $prev->format('Y-m-01')]); ?>" aria-label="Bulan sebelumnya">&#8249;</a>
                <form method="GET" action="admin_jadwal.php">
                    <input type="hidden" name="tampil" value="<?= $tampil; ?>">
                    <?php if ($f_status !== 'all'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($f_status); ?>"><?php endif; ?>
                    <select name="bulan" onchange="this.form.submit()" aria-label="Bulan">
                        <?php for ($b = 1; $b <= 12; $b++): ?>
                        <option value="<?= $b; ?>" <?= $b === $bulan ? 'selected' : ''; ?>><?= bulan_id($b); ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="tahun" onchange="this.form.submit()" aria-label="Tahun">
                        <?php for ($y = (int)date('Y') - 1; $y <= (int)date('Y') + 3; $y++): ?>
                        <option value="<?= $y; ?>" <?= $y === $tahun ? 'selected' : ''; ?>><?= $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </form>
                <a class="nav-btn" href="<?= url_jadwal(['bulan' => (int)$next->format('n'), 'tahun' => (int)$next->format('Y'), 'tgl' => $next->format('Y-m-01')]); ?>" aria-label="Bulan berikutnya">&#8250;</a>
                <a class="fpill" href="<?= url_jadwal(['bulan' => (int)date('n'), 'tahun' => (int)date('Y'), 'tgl' => $hari_ini]); ?>">Hari Ini</a>
            </div>

            <div class="filter-sep"></div>

            <div class="filter-group">
                <span class="filter-label">Tampilan:</span>
                <a class="fpill <?= $tampil === 'kalender' ? 'active' : ''; ?>" href="<?= url_jadwal(['tampil' => 'kalender']); ?>">Kalender</a>
                <a class="fpill <?= $tampil === 'daftar' ? 'active' : ''; ?>" href="<?= url_jadwal(['tampil' => 'daftar']); ?>">Daftar</a>
            </div>

            <div class="filter-sep"></div>

            <div class="filter-group">
                <span class="filter-label">Status:</span>
                <?php
                $status_nav = ['all' => ['Semua', ''], 'tersedia' => ['Tersedia', 'green'], 'terbatas' => ['Terbatas', 'amber'], 'penuh' => ['Penuh', 'red'], 'tutup' => ['Tutup', 'gray']];
                foreach ($status_nav as $key => $cfg): ?>
                <a class="fpill <?= $cfg[1]; ?> <?= $f_status === $key ? 'active' : ''; ?>" href="<?= url_jadwal(['status' => $key]); ?>"><?= $cfg[0]; ?></a>
                <?php endforeach; ?>
            </div>

            <?php if ($f_status !== 'all'): ?>
            <a href="<?= url_jadwal(['status' => 'all']); ?>" class="btn-reset-filter">✕ Reset</a>
            <?php endif; ?>
        </div>

        <div class="jadwal-layout">

            <!-- ===== KIRI: kalender / daftar ===== -->
            <div class="panel">
                <div class="panel-head">
                    <h2><?= bulan_id($bulan) . ' ' . $tahun; ?></h2>
                    <small>Kuota default <?= KUOTA_DEFAULT; ?> sesi/hari. Klik tanggal untuk melihat detail dan mengubah kuota.</small>
                </div>

                <?php if ($tampil === 'kalender'): ?>
                <div class="cal">
                    <?php foreach (['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $dw): ?>
                    <div class="cal-dow"><?= $dw; ?></div>
                    <?php endforeach; ?>

                    <?php for ($i = 0; $i < $offset_awal; $i++): ?><div class="cal-empty"></div><?php endfor; ?>

                    <?php foreach ($hari as $h):
                        $pct = $h['kuota'] > 0 ? min(100, round($h['terisi'] / $h['kuota'] * 100)) : 100;
                        $cls = $h['status']
                             . ($h['lewat'] ? ' lewat' : '')
                             . ($h['hari_ini'] ? ' hari-ini' : '')
                             . ($h['tgl'] === $tgl ? ' pilih' : '')
                             . (($f_status !== 'all' && $f_status !== $h['status']) ? ' dim' : '');
                    ?>
                    <a class="cal-cell <?= $cls; ?>" href="<?= url_jadwal(['tgl' => $h['tgl']]); ?>"
                       title="<?= htmlspecialchars(tgl_lengkap($h['tgl'])); ?>">
                        <span class="cal-num"><?= $h['d']; ?></span>
                        <?php if ($h['status'] === 'tutup'): ?>
                            <span class="cal-sisa">Tutup</span>
                        <?php else: ?>
                            <span class="cal-sisa"><?= $h['status'] === 'penuh' ? 'Penuh' : 'Sisa ' . $h['sisa'] . ' slot'; ?></span>
                            <span class="cal-frac"><?= $h['terisi']; ?>/<?= $h['kuota']; ?> terisi<?= $h['pending'] ? ' +' . $h['pending'] . ' pending' : ''; ?></span>
                            <span class="cal-bar"><i style="width:<?= $pct; ?>%"></i></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="cal-legend">
                    <span><i style="background:#BCF0DA"></i> Tersedia</span>
                    <span><i style="background:#FDE68A"></i> Slot terbatas</span>
                    <span><i style="background:#FECACA"></i> Penuh</span>
                    <span><i style="background:#D1D5DB"></i> Tutup</span>
                </div>

                <?php else: ?>
                <div class="table-wrap">
                    <table class="slot-table">
                        <thead>
                            <tr>
                                <th>Hari</th><th>Tanggal</th><th>Bulan</th><th>Tahun</th>
                                <th style="text-align:center;">Terisi</th><th style="text-align:center;">Kuota</th><th style="text-align:center;">Sisa</th>
                                <th>Status</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $ada = false; foreach ($hari as $h):
                            if ($f_status !== 'all' && $f_status !== $h['status']) continue;
                            $ada = true; ?>
                            <tr class="<?= ($h['tgl'] === $tgl ? 'pilih ' : '') . ($h['lewat'] ? 'lewat' : ''); ?>">
                                <td><strong><?= $h['hari']; ?></strong><?= $h['hari_ini'] ? ' <span class="slot-badge tersedia" style="margin-left:4px;">Hari ini</span>' : ''; ?></td>
                                <td><?= $h['d']; ?></td>
                                <td><?= bulan_id($bulan); ?></td>
                                <td><?= $tahun; ?></td>
                                <td class="num"><?= $h['terisi']; ?><?= $h['pending'] ? ' <small style="color:var(--j-amber);">+' . $h['pending'] . '</small>' : ''; ?></td>
                                <td class="num"><?= $h['kuota']; ?></td>
                                <td class="num"><?= $h['status'] === 'tutup' ? '-' : $h['sisa']; ?></td>
                                <td><span class="slot-badge <?= $h['status']; ?>"><?= $label_status[$h['status']]; ?></span></td>
                                <td><a class="link-atur" href="<?= url_jadwal(['tgl' => $h['tgl']]); ?>">Atur</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$ada): ?>
                            <tr><td colspan="9" style="text-align:center; padding:32px; color:var(--ink-soft);">Tidak ada tanggal dengan status ini di <?= bulan_id($bulan) . ' ' . $tahun; ?>.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- ===== KANAN ===== -->
            <div class="jadwal-sidebar">

                <!-- Detail tanggal terpilih -->
                <div class="sidebar-card">
                    <div class="sidebar-card-header">
                        <h3>Detail Tanggal</h3>
                        <span class="slot-badge <?= $pilih['status']; ?>"><?= $label_status[$pilih['status']]; ?></span>
                    </div>
                    <div class="detail-body">
                        <h4 class="detail-date"><?= tgl_lengkap($pilih['tgl']); ?></h4>

                        <div class="detail-meter">
                            <?php if ($pilih['status'] === 'tutup'): ?>
                                <div class="row"><span>Studio tidak menerima sesi</span></div>
                            <?php else: ?>
                                <div class="row"><span>Terisi <b><?= $pilih['terisi']; ?></b> / <?= $pilih['kuota']; ?></span><span>Sisa <b><?= $pilih['sisa']; ?></b> slot</span></div>
                                <div class="meter"><i class="<?= $pilih['status']; ?>" style="width:<?= $pilih['kuota'] > 0 ? min(100, round($pilih['terisi'] / $pilih['kuota'] * 100)) : 100; ?>%"></i></div>
                            <?php endif; ?>
                            <?php if ($pilih['pending']): ?>
                                <div style="font-size:0.74rem; color:var(--j-amber); font-weight:600; margin-top:6px;"><?= $pilih['pending']; ?> pesanan menunggu pembayaran (belum mengurangi slot)</div>
                            <?php endif; ?>
                        </div>

                        <div style="margin: 14px 0 16px;">
                            <?php if (empty($booking_hari)): ?>
                                <div style="font-size:0.8rem; color:var(--ink-soft);">Belum ada booking di tanggal ini.</div>
                            <?php else: foreach ($booking_hari as $b): ?>
                                <div class="mini-booking">
                                    <strong><?= htmlspecialchars($b['jam_sesi']); ?> &middot; <?= htmlspecialchars($b['nama_klien']); ?></strong>
                                    <span><?= htmlspecialchars($b['paket_layanan']); ?></span>
                                    <span class="timeline-status <?= kelas_status_booking($b['status']); ?>" style="margin-top:4px;"><?= htmlspecialchars($b['status']); ?></span>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>

                        <form method="POST" action="<?= htmlspecialchars(url_jadwal()); ?>">
                            <input type="hidden" name="aksi" value="simpan_hari">
                            <input type="hidden" name="tanggal" value="<?= $pilih['tgl']; ?>">
                            <div class="form-row">
                                <label for="kuota_hari">Kuota sesi hari ini</label>
                                <input type="number" id="kuota_hari" name="kuota" min="0" max="99" value="<?= $pilih['kuota']; ?>">
                            </div>
                            <div class="form-row">
                                <label for="catatan_hari">Catatan (opsional)</label>
                                <input type="text" id="catatan_hari" name="catatan" maxlength="150" value="<?= htmlspecialchars($pilih['catatan']); ?>" placeholder="Contoh: libur nasional">
                            </div>
                            <label class="check-row"><input type="checkbox" name="tutup" value="1" <?= $pilih['tutup'] ? 'checked' : ''; ?>> Tutup tanggal ini (tidak menerima sesi)</label>
                            <button type="submit" class="btn-save">Simpan Slot Tanggal Ini</button>
                        </form>
                    </div>
                </div>

                <!-- Atur satu bulan -->
                <div class="sidebar-card">
                    <div class="sidebar-card-header"><h3>Atur Satu Bulan</h3><span style="font-size:0.7rem; color:var(--ink-soft);"><?= bulan_id($bulan) . ' ' . $tahun; ?></span></div>
                    <div class="detail-body">
                        <form method="POST" action="<?= htmlspecialchars(url_jadwal()); ?>" onsubmit="return confirm('Kuota semua tanggal di bulan ini akan diganti. Lanjutkan?');">
                            <input type="hidden" name="aksi" value="simpan_bulan">
                            <div class="form-row">
                                <label for="kuota_bulan">Kuota sesi per hari</label>
                                <input type="number" id="kuota_bulan" name="kuota" min="0" max="99" value="<?= KUOTA_DEFAULT; ?>">
                            </div>
                            <label class="check-row"><input type="checkbox" name="tutup_minggu" value="1"> Tutup setiap hari Minggu</label>
                            <button type="submit" class="btn-save light">Terapkan ke Seluruh Bulan</button>
                        </form>
                    </div>
                </div>
            </div><!-- /jadwal-sidebar -->
        </div><!-- /jadwal-layout -->
    </div>
</main>

</body>
</html>