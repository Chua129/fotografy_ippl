<?php
session_start();
require 'koneksi.php';

/* ---------- Proteksi admin ---------- */
$is_admin = isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
if (!$is_admin) {
    if (isset($_GET['partial'])) { http_response_code(401); exit; } // untuk auto-refresh
    header("Location: login.php");
    exit;
}
$nama_admin   = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Admin';
$current_page = basename($_SERVER['PHP_SELF']);

/* ---------- Helper ---------- */
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rupiah($n) { return number_format((float)$n, 0, ',', '.'); }

function tgl_id($d) {
    if (!$d) return '-';
    $b = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $t = strtotime($d);
    return date('d', $t) . ' ' . $b[(int)date('n', $t) - 1] . ' ' . date('Y', $t);
}

// Status di database bervariasi ("Dikonfirmasi", "Terkonfirmasi – Lunas DP", dst), jadi dikelompokkan dulu
function status_grup($s) {
    $s = mb_strtolower((string)$s);
    if (str_contains($s, 'batal'))     return 'batal';
    if (str_contains($s, 'selesai'))   return 'selesai';
    if (str_contains($s, 'menunggu'))  return 'menunggu';
    if (str_contains($s, 'terjadwal')) return 'terjadwal';
    if (str_contains($s, 'konfirm'))   return 'konfirm';
    return 'menunggu';
}

function metode_label($m) {
    $m = strtolower(trim((string)$m));
    $map = ['qris' => 'QRIS', 'transfer' => 'Transfer', 'tunai' => 'Tunai', 'cash' => 'Tunai', 'kasir' => 'Kasir Atelier', 'edc' => 'EDC Kasir'];
    return $map[$m] ?? ($m !== '' ? ucfirst($m) : 'QRIS');
}

// Kalau pesanan tidak ketemu di tabel paket, tebak kategori dari nama paketnya
function kategori_dari_nama($nama) {
    $n = mb_strtolower((string)$nama);
    if (str_contains($n, 'wisuda'))                                  return 'WISUDA & AKADEMIK';
    if (str_contains($n, 'prewedding') || str_contains($n, 'couple') || str_contains($n, 'anniversary')) return 'PREWEDDING & PASANGAN';
    if (str_contains($n, 'family') || str_contains($n, 'group'))     return 'FAMILY & GROUP';
    if (str_contains($n, 'portrait') || str_contains($n, 'editorial') || str_contains($n, 'persona')) return 'EDITORIAL PORTRAIT';
    return 'RESERVASI STUDIO';
}

function gradient_kategori($label) {
    $l = mb_strtolower($label);
    if (str_contains($l, 'wisuda'))     return ['#2f3e66', '#141b30'];
    if (str_contains($l, 'prewedding')) return ['#8a6f55', '#3d2f25'];
    if (str_contains($l, 'family'))     return ['#6b5a4a', '#2b221b'];
    if (str_contains($l, 'portrait'))   return ['#9c7a52', '#3a2a1c'];
    return ['#5b6b6a', '#1f2928'];
}

/* ---------- Ambil data langsung dari tabel pesanan ---------- */
function ambil_riwayat($conn, $id = 0) {
    $sql = "SELECT ps.*, u.nama AS nama_klien, u.whatsapp, u.email,
                   pk.kategori_label, pk.sub_tag, pk.durasi, pk.fitur, pk.gambar
            FROM pesanan ps
            JOIN users u ON ps.user_id = u.id
            LEFT JOIN paket pk ON pk.nama_paket = ps.paket_layanan";
    if ($id > 0) $sql .= " WHERE ps.id = " . (int)$id;
    $sql .= " ORDER BY ps.id DESC";

    $rows = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            if (!isset($rows[$r['id']])) $rows[$r['id']] = $r; // hindari dobel kalau nama paket kembar
        }
    }
    return array_values($rows);
}

// Ubah 1 baris pesanan jadi data siap tampil di kartu
function olah($r) {
    $total = (float)$r['total_harga'];
    $bayar = (float)$r['jumlah_dibayar'];
    $sisa  = (float)$r['sisa_tagihan'];
    $grup  = status_grup($r['status']);
    $lunas = $total > 0 && $sisa <= 0;

    $catatan   = (string)($r['catatan'] ?? '');
    $via_kasir = stripos($catatan, 'Pelunasan Kasir') !== false;
    $metode    = $via_kasir ? 'Kasir Atelier' : metode_label($r['metode_pembayaran']);

    // Badge pembayaran di pojok foto
    if ($grup === 'batal')      $badge = ['Dibatalkan', 'bad'];
    elseif ($lunas)             $badge = ['Lunas • ' . $metode, 'ok'];
    elseif ($bayar > 0)         $badge = ['DP • ' . $metode, 'warn'];
    else                        $badge = ['Belum Bayar', 'warn'];

    // Kotak status 1: pembayaran
    if ($lunas)         $box1 = ['wallet', 'Pembayaran Lunas', 'Rp ' . rupiah($bayar) . ' via ' . $metode, 'ok'];
    elseif ($bayar > 0) $box1 = ['wallet', 'DP Diterima', 'Sisa tagihan Rp ' . rupiah($sisa), 'warn'];
    else                $box1 = ['wallet', 'Belum Bayar', 'Menunggu pembayaran klien', 'warn'];

    // Kotak status 2: status sesi
    $tgl = tgl_id($r['tanggal_sesi']);
    switch ($grup) {
        case 'batal':     $box2 = ['x', 'Dibatalkan', 'Pesanan dibatalkan admin', 'bad']; break;
        case 'selesai':   $box2 = ['check', 'Pesanan Selesai', 'Sesi foto ditandai selesai', 'ok']; break;
        case 'konfirm':   $box2 = ['check', 'Sesi Dikonfirmasi', 'Slot terkunci ' . $tgl, 'ok']; break;
        case 'terjadwal': $box2 = ['check', 'Sesi Terjadwal', 'Jadwal ' . $tgl, 'ok']; break;
        default:          $box2 = ['clock', 'Menunggu Verifikasi', 'Belum dikonfirmasi admin', 'warn'];
    }

    $kategori = !empty($r['kategori_label']) ? $r['kategori_label'] : kategori_dari_nama($r['paket_layanan']);

    $foto = '';
    if (!empty($r['gambar']) && file_exists(__DIR__ . '/' . $r['gambar'])) $foto = $r['gambar'];

    // Deskripsi paket dari data katalog (durasi + fitur)
    $bagian = [];
    if (!empty($r['durasi'])) $bagian[] = 'Durasi ' . $r['durasi'];
    if (!empty($r['fitur']))  foreach (explode('|', $r['fitur']) as $f) { if (trim($f) !== '') $bagian[] = trim($f); }
    $detail = $bagian ? implode(' • ', $bagian) : ('Sesi ' . $r['jam_sesi'] . ' • ' . $r['lokasi_studio']);

    $nama = $r['nama_klien'];
    if (!empty($r['nama_pasangan'])) $nama .= ' & ' . $r['nama_pasangan'];

    return [
        'id' => (int)$r['id'], 'kategori' => $kategori, 'foto' => $foto, 'warna' => gradient_kategori($kategori),
        'sub' => !empty($r['sub_tag']) ? $r['sub_tag'] : $r['jam_sesi'],
        'nama' => $nama, 'badge' => $badge, 'kode' => '#' . $r['kode_reservasi'],
        'label_tgl' => $grup === 'selesai' ? 'Selesai' : 'Sesi', 'tgl' => $tgl,
        'lokasi' => $r['lokasi_studio'], 'paket' => $r['paket_layanan'], 'detail' => $detail,
        'box1' => $box1, 'box2' => $box2,
        'total' => $total, 'bayar' => $bayar, 'sisa' => $sisa, 'skema' => $r['skema_pembayaran'],
        'lunas' => $lunas, 'status' => $r['status'], 'metode' => $metode,
    ];
}

function icon($name) {
    $p = [
        'cloud'    => '<path d="M7 18a4 4 0 0 1-.6-7.96A5.5 5.5 0 0 1 17 8.5a4.5 4.5 0 0 1 .5 9H7z"/><path d="M9.5 13.5l2 2 3.5-3.5"/>',
        'wallet'   => '<rect x="3.5" y="6" width="17" height="13" rx="2"/><path d="M3.5 10h17"/><circle cx="16.5" cy="14.5" r="1"/>',
        'check'    => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
        'x'        => '<circle cx="12" cy="12" r="8.5"/><path d="M9 9l6 6M15 9l-6 6"/>',
        'clock'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'pin'      => '<path d="M12 21s-6-5.6-6-10a6 6 0 0 1 12 0c0 4.4-6 10-6 10z"/><circle cx="12" cy="11" r="2"/>',
        'download' => '<path d="M12 4v11"/><path d="M7.5 11 12 15.5 16.5 11"/><path d="M5 19h14"/>',
        'invoice'  => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
    ];
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p[$name] . '</svg>';
}

function render_kartu($rows) {
    if (empty($rows)) {
        echo '<div class="empty">Belum ada pesanan masuk. Riwayat akan muncul otomatis setelah klien melakukan pemesanan.</div>';
        return;
    }
    foreach ($rows as $r) {
        $h  = olah($r);
        $bg = $h['foto'] !== ''
            ? "url('" . e($h['foto']) . "')"
            : "linear-gradient(135deg, {$h['warna'][0]}, {$h['warna'][1]})";
        ?>
        <article class="port-card">
            <div class="pc-header" style="background-image: <?= $bg ?>;">
                <span class="pc-pill"><?= e($h['kategori']) ?></span>
                <div class="pc-caption">
                    <div>
                        <span class="pc-sub"><?= e($h['sub']) ?></span>
                        <h3 class="pc-name"><?= e($h['nama']) ?></h3>
                    </div>
                    <span class="pc-badge <?= e($h['badge'][1]) ?>"><?= e($h['badge'][0]) ?></span>
                </div>
            </div>

            <div class="pc-body">
                <div class="pc-meta">
                    <span class="kode"><?= e($h['kode']) ?></span>
                    <span class="selesai"><?= e($h['label_tgl']) ?>: <?= e($h['tgl']) ?></span>
                    <span class="lokasi"><?= icon('pin') ?><?= e($h['lokasi']) ?></span>
                </div>

                <h4 class="pc-paket"><?= e($h['paket']) ?></h4>
                <p class="pc-detail"><?= e($h['detail']) ?></p>

                <div class="pc-status">
                    <?php foreach (['box1', 'box2'] as $k): $s = $h[$k]; ?>
                    <div class="st-box <?= e($s[3]) ?>">
                        <div class="st-title"><?= icon($s[0]) ?><span><?= e($s[1]) ?></span></div>
                        <div class="st-ket"><?= e($s[2]) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="pc-total">
                    <div class="lbl">Total Settlement:
                        <span class="dp"><?= ($h['skema'] === 'dp' && !$h['lunas']) ? 'DP' : 'Dibayar' ?>: Rp <?= rupiah($h['bayar']) ?></span>
                        <?php if ($h['sisa'] > 0): ?><span class="dp sisa">Sisa: Rp <?= rupiah($h['sisa']) ?></span><?php endif; ?>
                    </div>
                    <div class="nominal">Rp <?= rupiah($h['total']) ?></div>
                </div>

                <a class="btn-invoice-full" href="admin_history.php?invoice=<?= $h['id'] ?>" target="_blank" rel="noopener">
                    <?= icon('invoice') ?> Unduh Invoice
                </a>
            </div>
        </article>
        <?php
    }
}

/* ---------- Mode: refresh otomatis (hanya kartu) ---------- */
if (isset($_GET['partial'])) {
    render_kartu(ambil_riwayat($conn));
    exit;
}

/* ---------- Mode: export CSV ---------- */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="history_pemesanan_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Kode', 'Klien', 'WhatsApp', 'Paket', 'Tanggal Sesi', 'Jam', 'Lokasi', 'Total', 'Dibayar', 'Sisa', 'Metode', 'Status'], ',', '"', '\\');
    foreach (ambil_riwayat($conn) as $r) {
        $h = olah($r);
        fputcsv($out, [$r['kode_reservasi'], $h['nama'], $r['whatsapp'], $r['paket_layanan'], $r['tanggal_sesi'], $r['jam_sesi'], $r['lokasi_studio'],
            (int)$h['total'], (int)$h['bayar'], (int)$h['sisa'], $h['metode'], $r['status']], ',', '"', '\\');
    }
    fclose($out);
    exit;
}

/* ---------- Mode: invoice (halaman cetak / simpan PDF) ---------- */
if (isset($_GET['invoice'])) {
    $rows = ambil_riwayat($conn, (int)$_GET['invoice']);
    if (!$rows) { http_response_code(404); exit('Pesanan tidak ditemukan.'); }
    $r = $rows[0];
    $h = olah($r);
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Invoice <?= e($r['kode_reservasi']) ?> - Creative Pixel</title>
<style>
body { font-family: 'Inter', Arial, sans-serif; color: #1B1A17; margin: 0; background: #F3F1EC; }
.inv { max-width: 760px; margin: 32px auto; background: #fff; padding: 48px; border-radius: 12px; }
.inv-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1B1A17; padding-bottom: 20px; }
.inv h1 { font-family: Georgia, serif; font-weight: 500; margin: 0; font-size: 1.6rem; }
.inv .meta { text-align: right; font-size: 0.85rem; color: #6B6860; line-height: 1.6; }
.inv table { width: 100%; border-collapse: collapse; margin-top: 28px; font-size: 0.9rem; }
.inv td, .inv th { padding: 12px 0; border-bottom: 1px solid #E8E5DE; text-align: left; }
.inv td:last-child, .inv th:last-child { text-align: right; }
.inv .total td { font-weight: 700; font-size: 1.05rem; border-bottom: 0; }
.stamp { display: inline-block; margin-top: 24px; padding: 6px 14px; border: 2px solid; border-radius: 6px; font-weight: 700; letter-spacing: .08em; }
.stamp.ok { color: #0F7B55; } .stamp.warn { color: #A36A00; } .stamp.bad { color: #B42318; }
.actions { max-width: 760px; margin: 0 auto 32px; text-align: right; }
.actions button { background: #1B1A17; color: #fff; border: 0; padding: 12px 22px; border-radius: 8px; font-weight: 600; cursor: pointer; }
@media print { body { background: #fff; } .inv { margin: 0; padding: 0; } .actions { display: none; } }
</style>
</head>
<body>
<div class="inv">
    <div class="inv-head">
        <div><h1>Creative Pixel Studio</h1><div style="font-size:.85rem;color:#6B6860;margin-top:4px;">Invoice Pemesanan</div></div>
        <div class="meta"><strong><?= e($r['kode_reservasi']) ?></strong><br>Dicetak: <?= tgl_id(date('Y-m-d')) ?></div>
    </div>
    <p style="margin:24px 0 0; font-size:.9rem; line-height:1.7;">
        <strong><?= e($h['nama']) ?></strong><br>
        <?= e($r['whatsapp']) ?> &middot; <?= e($r['email']) ?><br>
        Sesi: <?= e(tgl_id($r['tanggal_sesi'])) ?>, <?= e($r['jam_sesi']) ?><br>
        <?= e($r['lokasi_studio']) ?>
    </p>
    <table>
        <tr><th>Layanan</th><th>Jumlah</th></tr>
        <tr><td><?= e($r['paket_layanan']) ?></td><td>Rp <?= rupiah($h['total']) ?></td></tr>
        <tr><td>Sudah dibayar (<?= e($h['metode']) ?>)</td><td>Rp <?= rupiah($h['bayar']) ?></td></tr>
        <tr class="total"><td>Sisa tagihan</td><td>Rp <?= rupiah($h['sisa']) ?></td></tr>
    </table>
    <?php $stempel = $h['badge'][1] === 'bad' ? 'bad' : ($h['lunas'] ? 'ok' : 'warn'); ?>
    <div class="stamp <?= $stempel ?>"><?= $h['badge'][1] === 'bad' ? 'DIBATALKAN' : ($h['lunas'] ? 'LUNAS' : 'BELUM LUNAS') ?></div>
</div>
<div class="actions"><button onclick="window.print()">Cetak / Simpan PDF</button></div>
</body>
</html>
<?php
    exit;
}

/* ---------- Halaman utama ---------- */
$riwayat = ambil_riwayat($conn);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>History Pemesanan Klien - Creative Pixel Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500&family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<style>
:root {
    --hx-bg: #FAF9F6;
    --hx-ink: #1B1A17;
    --hx-soft: #6B6860;
    --hx-line: #E8E5DE;
    --hx-box: #F5F3EE;
    --hx-gold: #7A5A00;
    --hx-green: #0F7B55;
    --hx-green-bg: #E6F7EF;
    --hx-warn: #A36A00;
    --hx-warn-bg: #FFF7DB;
    --hx-warn-line: #F3E3A3;
    --hx-bad: #B42318;
    --hx-bad-bg: #FDECEC;
    --hx-bad-line: #F5C6C6;
    --hx-serif: 'Playfair Display', 'Fraunces', Georgia, serif;
    --hx-sans: 'Inter', system-ui, sans-serif;
}
.admin-main, .admin-main * { box-sizing: border-box; }
body { background-color: #1B1A17; color: #1B1A17; margin: 0; font-family: var(--font-sans); display: flex; min-height: 100vh; }

/* Sidebar */
.admin-sidebar { width: 260px; background: #1B1A17; color: #fff; padding: 24px; display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; }
.sidebar-brand { font-family: var(--font-serif); font-size: 1.2rem; color: #fff; margin-bottom: 40px; display: flex; align-items: center; gap: 8px; }
.sidebar-brand span { color: var(--accent); }
.sidebar-menu { display: flex; flex-direction: column; gap: 8px; list-style: none; padding: 0; margin: 0; }
.sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 8px; color: rgba(255,255,255,0.7); text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: 0.2s; }
.sidebar-menu a:hover, .sidebar-menu a.active { background: #2C2B27; color: #fff; }

/* Main */
.admin-main { font-family: var(--hx-sans); flex: 1; min-width: 0; background: var(--hx-bg); border-top-left-radius: 20px; border-bottom-left-radius: 20px; overflow-y: auto; padding: 36px 40px 48px; }
.page-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 24px; flex-wrap: wrap; }
.eyebrow { display: flex; align-items: center; gap: 8px; font-size: 0.72rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--hx-gold); margin: 0 0 8px; }
.eyebrow::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--hx-gold); }
.dash-title { font-family: var(--hx-serif); font-size: 2.3rem; font-weight: 500; margin: 0 0 8px; color: var(--hx-ink); line-height: 1.15; }
.dash-desc { color: var(--hx-soft); font-size: 0.95rem; line-height: 1.6; max-width: 640px; margin: 0; }
.btn-export { display: inline-flex; align-items: center; gap: 8px; background: #F1EFEA; border: 0; color: var(--hx-ink); padding: 14px 22px; border-radius: 12px; font: 600 0.8rem var(--hx-sans); letter-spacing: 0.06em; text-transform: uppercase; cursor: pointer; text-decoration: none; transition: background .2s; }
.btn-export:hover { background: #E8E5DE; }
.btn-export:focus-visible, .btn-invoice-full:focus-visible { outline: 2px solid var(--hx-gold); outline-offset: 2px; }

/* Grid & kartu */
.portfolio-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; margin-top: 36px; }
.empty { grid-column: 1 / -1; background: #fff; border-radius: 16px; padding: 48px 24px; text-align: center; color: var(--hx-soft); font-size: 0.95rem; }
.port-card { background: #fff; border-radius: 22px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 1px 2px rgba(27,26,23,.04), 0 8px 24px rgba(27,26,23,.05); }
.pc-header { height: 232px; position: relative; background-size: cover; background-position: center; color: #fff; }
.pc-header::after { content: ""; position: absolute; inset: 0; background: linear-gradient(to top, rgba(20,16,12,.82) 0%, rgba(20,16,12,.35) 45%, rgba(20,16,12,0) 70%); }
.pc-pill { position: absolute; top: 16px; left: 16px; z-index: 2; display: inline-flex; align-items: center; gap: 8px; background: rgba(40,34,28,.72); backdrop-filter: blur(6px); padding: 10px 18px; border-radius: 999px; font-size: 0.68rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; line-height: 1.25; max-width: calc(100% - 32px); }
.pc-pill::before { content: ""; flex-shrink: 0; width: 6px; height: 6px; border-radius: 50%; background: #E0A526; }
.pc-caption { position: absolute; left: 20px; right: 20px; bottom: 16px; z-index: 2; display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; }
.pc-sub { display: block; font-size: 0.68rem; letter-spacing: 0.14em; text-transform: uppercase; color: rgba(255,255,255,.78); margin-bottom: 4px; }
.pc-name { font-family: var(--hx-serif); font-size: 1.6rem; font-weight: 500; line-height: 1.25; margin: 0; text-shadow: 0 1px 8px rgba(0,0,0,.35); }
.pc-badge { flex-shrink: 0; background: var(--hx-green-bg); color: var(--hx-green); font-size: 0.7rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; padding: 8px 12px; border-radius: 6px; line-height: 1.25; max-width: 112px; }
.pc-badge.warn { background: var(--hx-warn-bg); color: var(--hx-warn); }
.pc-badge.bad { background: var(--hx-bad-bg); color: var(--hx-bad); }

.pc-body { padding: 18px 20px 20px; flex: 1; display: flex; flex-direction: column; }
.pc-meta { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; font-size: 0.78rem; font-weight: 600; letter-spacing: 0.04em; padding-bottom: 12px; border-bottom: 1px solid var(--hx-line); }
.pc-meta .kode { font-weight: 700; letter-spacing: 0.06em; word-break: break-all; }
.pc-meta .selesai { display: inline-flex; gap: 8px; align-items: flex-start; margin-right: auto; white-space: nowrap; }
.pc-meta .selesai::before { content: ""; width: 4px; height: 4px; border-radius: 50%; background: var(--hx-ink); margin-top: 7px; flex-shrink: 0; }
.pc-meta .lokasi { display: inline-flex; align-items: flex-start; gap: 3px; color: var(--hx-soft); text-align: left; max-width: 38%; }
.pc-meta .lokasi .ico { margin-top: 1px; }

.pc-paket { font-size: 1.1rem; font-weight: 600; margin: 20px 0 6px; line-height: 1.4; }
.pc-detail { font-size: 0.9rem; color: var(--hx-soft); line-height: 1.6; margin: 0 0 18px; }

.pc-status { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; margin-top: auto; align-items: stretch; }
.st-box { background: var(--hx-box); border: 1px solid var(--hx-line); border-radius: 10px; padding: 12px; }
.st-box .st-title { display: flex; align-items: flex-start; gap: 7px; font-size: 0.9rem; font-weight: 500; color: var(--hx-green); line-height: 1.25; }
.st-box .st-title .ico { margin-top: 1px; }
.st-box .st-ket { font-size: 0.8rem; color: var(--hx-soft); line-height: 1.45; margin-top: 4px; }
.st-box.warn { background: var(--hx-warn-bg); border-color: var(--hx-warn-line); }
.st-box.warn .st-title { color: var(--hx-warn); }
.st-box.bad { background: var(--hx-bad-bg); border-color: var(--hx-bad-line); }
.st-box.bad .st-title { color: var(--hx-bad); }

.pc-total { display: flex; align-items: center; justify-content: space-between; gap: 12px; background: var(--hx-bg); border-radius: 10px; padding: 16px; margin-bottom: 16px; }
.pc-total .lbl { font-size: 0.78rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; line-height: 1.5; }
.pc-total .dp { display: block; font-size: 0.82rem; font-weight: 400; letter-spacing: 0; text-transform: none; color: var(--hx-soft); }
.pc-total .dp.sisa { color: var(--hx-warn); font-weight: 600; }
.pc-total .nominal { font-family: var(--hx-serif); font-size: 1.7rem; font-weight: 600; white-space: nowrap; }

.btn-invoice-full { display: flex; justify-content: center; align-items: center; gap: 8px; width: 100%; background: #EAE8E3; border: 0; color: var(--hx-ink); padding: 14px; border-radius: 10px; font: 600 0.82rem var(--hx-sans); letter-spacing: 0.06em; text-transform: uppercase; cursor: pointer; text-decoration: none; transition: background .2s; }
.btn-invoice-full:hover { background: #DEDBD4; }

.ico { width: 16px; height: 16px; flex-shrink: 0; }

@media (max-width: 1280px) { .portfolio-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 860px) {
    body { flex-direction: column; }
    .admin-sidebar { width: 100%; padding: 16px; }
    .sidebar-brand { margin-bottom: 16px; }
    .sidebar-menu { flex-direction: row; flex-wrap: wrap; flex: none; }
    .sidebar-menu li[style] { margin-top: 0 !important; }
    .admin-main { border-radius: 20px 20px 0 0; padding: 24px 18px 36px; }
}
@media (max-width: 700px) { .portfolio-grid { grid-template-columns: 1fr; } .dash-title { font-size: 1.9rem; } }
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
    <header class="page-head">
        <div>
            <p class="eyebrow">Visual Portfolio CRM • Atelier Gallery View</p>
            <h1 class="dash-title">History pemesanan klien</h1>
            <p class="dash-desc">Tampilan visual karya kurasi hasil pemotretan pelanggan, status pembayaran, kesiapan sesi di atelier, dan ringkasan transaksi. Data tersinkron otomatis dengan pembayaran klien.</p>
        </div>
    </header>

    <section class="portfolio-grid" id="historyGrid">
        <?php render_kartu($riwayat); ?>
    </section>
</main>

<script>
// Auto-refresh: ambil ulang kartu tiap 15 detik supaya pembayaran baru langsung muncul
(function () {
    var grid = document.getElementById('historyGrid');
    var busy = false;
    function refresh() {
        if (document.hidden || busy) return;
        busy = true;
        fetch('admin_history.php?partial=1', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.ok ? r.text() : null; })
            .then(function (html) { if (html !== null && html.trim() !== '') grid.innerHTML = html; })
            .catch(function () {})
            .finally(function () { busy = false; });
    }
    setInterval(refresh, 15000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
})();
</script>
</body>
</html>