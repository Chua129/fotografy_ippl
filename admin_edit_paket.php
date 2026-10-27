<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$nama_admin = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Admin';
$current_page = 'admin_etalase.php';

$error = '';
$is_edit = false;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ===== Highlight Badge dan Sub-Tag: dua isian teks bebas yang terpisah =====
// Warna background Highlight Badge dipilih admin: Putih (bawaan), Hitam, atau Coklat.
// Nilai => [label, kelas CSS]
$opsi_warna = ['' => ['Putih', 'hb-none'], 'hitam' => ['Hitam', 'hb-dark'], 'coklat' => ['Coklat', 'hb-amber']];
$ada_kolom_badge = false;
try {
    $cek = $conn->query("SHOW COLUMNS FROM paket LIKE 'highlight_badge'");
    if ($cek && $cek->num_rows === 0) {
        // Migrasi otomatis satu kali: tambah kolom teks Highlight Badge
        $conn->query("ALTER TABLE paket ADD COLUMN highlight_badge VARCHAR(30) NOT NULL DEFAULT '' AFTER sub_tag");
    }
    $cek = $conn->query("SHOW COLUMNS FROM paket LIKE 'highlight_warna'");
    if ($cek && $cek->num_rows === 0) {
        // Migrasi otomatis satu kali: tambah kolom warna background badge
        $conn->query("ALTER TABLE paket ADD COLUMN highlight_warna VARCHAR(10) NOT NULL DEFAULT '' AFTER highlight_badge");
        // Data lama tetap tampil sama: Best Seller = hitam, Haute Couture = coklat
        $conn->query("UPDATE paket SET highlight_warna = 'hitam' WHERE highlight_badge = 'Best Seller'");
        $conn->query("UPDATE paket SET highlight_warna = 'coklat' WHERE highlight_badge = 'Haute Couture'");
    }
    $c1 = $conn->query("SHOW COLUMNS FROM paket LIKE 'highlight_badge'");
    $c2 = $conn->query("SHOW COLUMNS FROM paket LIKE 'highlight_warna'");
    $ada_kolom_badge = ($c1 && $c1->num_rows > 0 && $c2 && $c2->num_rows > 0);
} catch (Throwable $e) {
    $ada_kolom_badge = false;
}
if (!$ada_kolom_badge) {
    $error = "Kolom highlight_badge / highlight_warna belum ada di tabel paket. Jalankan SQL ini di phpMyAdmin lalu muat ulang halaman: ALTER TABLE paket ADD COLUMN highlight_badge VARCHAR(30) NOT NULL DEFAULT '' AFTER sub_tag, ADD COLUMN highlight_warna VARCHAR(10) NOT NULL DEFAULT '' AFTER highlight_badge;";
}

$paket = [
    'id' => 0,
    'kategori' => 'portrait',
    'kategori_label' => 'EDITORIAL PORTRAIT',
    'nama_paket' => '',
    'sub_tag' => '',
    'highlight_badge' => '',
    'highlight_warna' => '',
    'deskripsi' => '',
    'fitur' => '',
    'harga' => 1000000,
    'dp_minimal' => 300000,
    'durasi' => '2 Jam Sesi Foto',
    'lokasi_studio' => 'Studio A & Garden Lumina Senopati',
    'gambar' => 'jpg/studio.jpg',
    'kuota_total' => 10,
    'kuota_terpakai' => 0,
    'status' => 'aktif'
];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM paket WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $paket = $res->fetch_assoc();
        $is_edit = true;
    }
    $stmt->close();
}

// ===== Saran badge: badge bawaan + badge yang sudah dipakai paket lain (agar penamaan konsisten) =====
$saran_badge = ['Solo Session', 'Solo + Keluarga', 'Full Eksklusif', 'Studio Intimate'];   // saran Sub-Tag
$res_badge = $conn->query("SELECT DISTINCT sub_tag FROM paket WHERE sub_tag IS NOT NULL AND sub_tag <> '' ORDER BY sub_tag");
if ($res_badge) {
    while ($rb = $res_badge->fetch_assoc()) $saran_badge[] = $rb['sub_tag'];
}
$unik_badge = [];
foreach ($saran_badge as $sb) { $unik_badge[mb_strtolower($sb)] = $sb; }
$saran_badge = array_slice(array_values($unik_badge), 0, 14);

// ===== Upload foto sampul =====
// Disimpan di jpg/paket/ (sejajar dengan jpg/studio.jpg), path relatif disimpan di kolom paket.gambar
// sehingga langsung terbaca oleh index.php (tampilan klien) dan admin_history.php.
const FOTO_DIR_REL = 'jpg/paket';
const FOTO_MAKS_BYTES = 5 * 1024 * 1024;

function ke_bytes($v) {
    $v = trim((string)$v);
    $n = (int)$v;
    switch (strtolower(substr($v, -1))) { case 'g': $n *= 1024; case 'm': $n *= 1024; case 'k': $n *= 1024; }
    return $n;
}
// Batas efektif = yang terkecil antara 5 MB dan batas PHP server
$batas_foto = min(FOTO_MAKS_BYTES, ke_bytes(ini_get('upload_max_filesize')) ?: FOTO_MAKS_BYTES, ke_bytes(ini_get('post_max_size')) ?: FOTO_MAKS_BYTES);

function simpan_foto_sampul($f, $batas) {
    $gagal = function ($pesan) { return ['path' => '', 'error' => $pesan]; };
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        return $gagal('Ukuran foto melebihi batas server (' . round($batas / 1048576, 1) . ' MB). Perkecil foto, atau naikkan upload_max_filesize di php.ini.');
    }
    if ($f['error'] !== UPLOAD_ERR_OK) return $gagal('Upload foto gagal (kode ' . (int)$f['error'] . '). Silakan coba lagi.');
    if ($f['size'] > $batas) return $gagal('Ukuran foto maksimal ' . round($batas / 1048576, 1) . ' MB.');
    if (!is_uploaded_file($f['tmp_name'])) return $gagal('File upload tidak valid.');

    // Validasi isi file sebenarnya (bukan hanya ekstensi)
    $info = @getimagesize($f['tmp_name']);
    $ekstensi = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($ekstensi[$info[2]])) return $gagal('Format foto harus JPG, PNG, atau WEBP.');

    $dir = __DIR__ . '/' . FOTO_DIR_REL;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return $gagal('Folder ' . FOTO_DIR_REL . ' tidak bisa dibuat. Periksa izin tulis folder proyek.');

    $nama = 'paket_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ekstensi[$info[2]];
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $nama)) return $gagal('Gagal menyimpan foto ke server.');
    return ['path' => FOTO_DIR_REL . '/' . $nama, 'error' => ''];
}

// Hanya menghapus file hasil upload (jpg/paket/...). jpg/studio.jpg dan foto bawaan tidak pernah disentuh.
function hapus_foto_upload($path) {
    if (strpos((string)$path, FOTO_DIR_REL . '/') !== 0 || strpos($path, '..') !== false) return;
    $file = __DIR__ . '/' . $path;
    if (is_file($file)) @unlink($file);
}

// Proses Simpan Data (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nama_paket = trim($_POST['nama_paket'] ?? '');
    $kategori = trim($_POST['kategori'] ?? 'portrait');
    $sub_tag = mb_substr(trim($_POST['sub_tag'] ?? ''), 0, 40);
    $highlight_badge = mb_substr(trim($_POST['highlight_badge'] ?? ''), 0, 30);
    $highlight_warna = trim($_POST['highlight_warna'] ?? '');
    if ($highlight_badge === '' || !array_key_exists($highlight_warna, $opsi_warna)) $highlight_warna = '';
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $fitur = trim($_POST['fitur'] ?? '');
    $harga = (float)($_POST['harga'] ?? 0);
    $dp_minimal = (float)($_POST['dp_minimal'] ?? 0);
    $durasi = trim($_POST['durasi'] ?? '2 Jam Sesi Foto');
    $lokasi_studio = trim($_POST['lokasi_studio'] ?? 'Studio A & Garden Lumina Senopati');
    // Foto sampul: diganti hanya jika admin memilih file baru; jika tidak, foto lama di database dipertahankan
    $gambar_lama = '';
    if ($id > 0) {
        $stmt_g = $conn->prepare("SELECT gambar FROM paket WHERE id = ?");
        $stmt_g->bind_param("i", $id);
        $stmt_g->execute();
        $row_g = $stmt_g->get_result()->fetch_assoc();
        $stmt_g->close();
        $gambar_lama = $row_g['gambar'] ?? '';
    }
    $gambar = ($gambar_lama !== '') ? $gambar_lama : 'jpg/studio.jpg';
    $gambar_baru = '';
    if (isset($_FILES['gambar_file']) && $_FILES['gambar_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $hasil_foto = simpan_foto_sampul($_FILES['gambar_file'], $batas_foto);
        if ($hasil_foto['error'] !== '') {
            $error = $hasil_foto['error'];
        } else {
            $gambar = $gambar_baru = $hasil_foto['path'];
        }
    }
    $kuota_total = (int)($_POST['kuota_total'] ?? 10);
    $kuota_terpakai = (int)($_POST['kuota_terpakai'] ?? 0);
    $status = trim($_POST['status'] ?? 'aktif');

    // Tentukan label kategori otomatis
    $kategori_labels = [
        'wisuda' => 'WISUDA & AKADEMIK',
        'portrait' => 'EDITORIAL PORTRAIT',
        'prewedding' => 'PREWEDDING & PASANGAN',
        'family' => 'FAMILY & GROUP'
    ];
    $kategori_label = $kategori_labels[$kategori] ?? strtoupper($kategori);

    if ($dp_minimal <= 0 && $harga > 0) {
        $dp_minimal = round($harga * 0.3);
    }

    if ($error !== '') {
        // Upload foto gagal: tampilkan pesan, data tidak disimpan
    } elseif (empty($nama_paket)) {
        $error = 'Nama paket fotografi wajib diisi.';
    } else {
        if ($id > 0) {
            // Update
            $stmt = $conn->prepare("UPDATE paket SET kategori=?, kategori_label=?, nama_paket=?, sub_tag=?, highlight_badge=?, highlight_warna=?, deskripsi=?, fitur=?, harga=?, dp_minimal=?, durasi=?, lokasi_studio=?, gambar=?, kuota_total=?, kuota_terpakai=?, status=? WHERE id=?");
            $stmt->bind_param("ssssssssddsssissi", 
                $kategori, $kategori_label, $nama_paket, $sub_tag, $highlight_badge, $highlight_warna, $deskripsi, $fitur,
                $harga, $dp_minimal, $durasi, $lokasi_studio, $gambar,
                $kuota_total, $kuota_terpakai, $status, $id
            );
            if ($stmt->execute()) {
                if ($gambar_baru !== '') hapus_foto_upload($gambar_lama); // foto lama sudah tergantikan
                header("Location: admin_etalase.php?msg=saved");
                exit;
            } else {
                $error = 'Gagal menyimpan paket: ' . $conn->error;
            }
            $stmt->close();
        } else {
            // Insert Baru
            $stmt = $conn->prepare("INSERT INTO paket (kategori, kategori_label, nama_paket, sub_tag, highlight_badge, highlight_warna, deskripsi, fitur, harga, dp_minimal, durasi, lokasi_studio, gambar, kuota_total, kuota_terpakai, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssddsssiss", 
                $kategori, $kategori_label, $nama_paket, $sub_tag, $highlight_badge, $highlight_warna, $deskripsi, $fitur,
                $harga, $dp_minimal, $durasi, $lokasi_studio, $gambar,
                $kuota_total, $kuota_terpakai, $status
            );
            if ($stmt->execute()) {
                header("Location: admin_etalase.php?msg=saved");
                exit;
            } else {
                $error = 'Gagal menambahkan paket baru: ' . $conn->error;
            }
            $stmt->close();
        }
    }

    // Gagal menyimpan: buang foto yang baru terunggah supaya tidak menumpuk di server
    if ($error !== '' && $gambar_baru !== '') hapus_foto_upload($gambar_baru);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $is_edit ? 'Edit Paket' : 'Tambah Paket Baru'; ?> - Creative Pixel</title>
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

.form-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
@media (max-width: 900px) { .form-grid { grid-template-columns: 1fr; } }

.form-card { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 28px; margin-bottom: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.01); }
.form-card h3 { font-family: var(--font-serif); font-size: 1.2rem; margin: 0 0 20px; border-bottom: 1px dashed var(--line); padding-bottom: 12px; }

.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--ink); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
.form-input, .form-select, .form-textarea { width: 100%; border: 1px solid var(--line); border-radius: 8px; padding: 12px 14px; font-size: 0.9rem; background: #FAFAFA; box-sizing: border-box; font-family: var(--font-sans); }
.form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--ink); background: #fff; outline: none; }
.form-textarea { resize: vertical; min-height: 100px; line-height: 1.5; }

.btn-submit { background: #111; color: #fff; border: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
.btn-submit:hover { background: #333; }
.btn-cancel { background: #F8F8F8; color: var(--ink); border: 1px solid var(--line); padding: 14px 20px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; text-decoration: none; margin-right: 12px; }
.btn-cancel:hover { background: #eee; }

.cover-upload { display: flex; gap: 18px; align-items: flex-start; }
.cover-preview { width: 200px; aspect-ratio: 4 / 3; border-radius: 10px; overflow: hidden; background: #1B1A17; border: 1px solid var(--line); flex-shrink: 0; }
.cover-preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
.cover-actions { flex: 1; min-width: 0; }
.cover-actions input[type=file]::file-selector-button { background: #111; color: #fff; border: none; border-radius: 6px; padding: 7px 14px; margin-right: 12px; font-weight: 600; font-size: 0.8rem; cursor: pointer; }
.cover-actions small { display: block; color: var(--ink-faint); font-size: 0.75rem; margin-top: 8px; line-height: 1.5; }
@media (max-width: 640px) { .cover-upload { flex-direction: column; } .cover-preview { width: 100%; } }

/* Kartu Badge Highlight */
.badge-edit { display: grid; grid-template-columns: 1fr 240px; gap: 24px; align-items: start; }
@media (max-width: 760px) { .badge-edit { grid-template-columns: 1fr; } }
.badge-edit small { display: block; color: var(--ink-faint); font-size: 0.75rem; line-height: 1.5; margin-top: 12px; }
.badge-edit small.badge-count { text-align: right; margin-top: 6px; }
.badge-mini-title { display: block; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--ink-soft); margin-bottom: 8px; }
.badge-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.badge-chip { border: 1px solid var(--line); background: #fff; color: var(--ink); border-radius: 999px; padding: 6px 12px; font-size: 0.78rem; font-weight: 600; font-family: inherit; cursor: pointer; transition: 0.15s; }
.badge-chip:hover { border-color: var(--ink); }
.badge-chip.is-active { background: #111; border-color: #111; color: #fff; }
.badge-chip-clear { border-style: dashed; color: var(--ink-soft); }
.bpv-photo { position: relative; aspect-ratio: 4 / 3; border-radius: 12px; overflow: hidden; background: #1B1A17 center / cover no-repeat; border: 1px solid var(--line); }
.bpv-photo::after { content: ''; position: absolute; inset: 0; background: linear-gradient(to bottom, rgba(0,0,0,0) 35%, rgba(0,0,0,0.75) 100%); }
.bpv-tl { position: absolute; top: 10px; left: 10px; z-index: 2; max-width: calc(100% - 20px); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; background: rgba(255,255,255,0.95); color: #1B1A17; font-size: 0.62rem; font-weight: 700; padding: 4px 10px; border-radius: 999px; box-shadow: 0 2px 6px rgba(0,0,0,0.12); }


.bpv-br { position: absolute; bottom: 10px; right: 10px; z-index: 2; max-width: 70%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; background: rgba(0,0,0,0.45); color: #fff; font-size: 0.58rem; font-weight: 700; text-transform: uppercase; padding: 3px 8px; border-radius: 6px; }
.hb-none { background: #fff; color: var(--ink-soft); }
.hb-dark { background: #000; color: #fff; }
.hb-amber { background: #78350F; color: #fff; }
.hb-green { background: #166534; color: #fff; }
.hb-blue { background: #1D4ED8; color: #fff; }
.hb-red { background: #B91C1C; color: #fff; }
.hb-options { display: flex; flex-wrap: wrap; gap: 12px; }
.hb-opt { position: relative; cursor: pointer; }
.hb-opt input { position: absolute; inset: 0; width: 100%; height: 100%; margin: 0; opacity: 0; cursor: pointer; }
.hb-pill { display: inline-block; padding: 7px 18px; border-radius: 999px; font-size: 0.78rem; font-weight: 700; box-shadow: 0 0 0 1px var(--line); transition: box-shadow 0.15s; }
.hb-opt:hover .hb-pill { box-shadow: 0 0 0 1px var(--ink-soft); }
.hb-opt input:checked + .hb-pill { box-shadow: 0 0 0 2px #fff, 0 0 0 4px #111; }
.hb-opt input:focus-visible + .hb-pill { box-shadow: 0 0 0 2px #fff, 0 0 0 4px #D9822B; }
.badge-section { margin-bottom: 22px; padding-bottom: 20px; border-bottom: 1px dashed var(--line); }
.badge-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.badge-sec-title { display: block; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ink); margin-bottom: 10px; }
.badge-sec-title em { font-style: normal; font-weight: 500; text-transform: none; letter-spacing: 0; color: var(--ink-faint); }
.bpv-cat { margin-top: 8px; font-size: 0.62rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--ink-soft); }
.bpv-empty { position: absolute; inset: 0; z-index: 2; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.7); font-size: 0.72rem; }
.bpv-empty[hidden] { display: none; }

.alert-error { background: #FDE8E8; color: #9B1C1C; padding: 14px 20px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 24px; border: 1px solid #F8B4B4; }
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
        <a href="admin_etalase.php">← Kembali ke Etalase & Stok</a> / <span><?= $is_edit ? 'Edit Paket: ' . htmlspecialchars($paket['nama_paket']) : 'Tambah Paket Baru'; ?></span>
    </div>

    <?php if(!empty($error)): ?>
        <div class="alert-error">✕ <?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" action="admin_edit_paket.php<?= $is_edit ? '?id=' . $paket['id'] : ''; ?>">
        <input type="hidden" name="id" value="<?= $paket['id']; ?>">

        <div class="header-actions">
            <div>
                <h1 class="page-title"><?= $is_edit ? 'Edit Paket Fotografi' : 'Tambah Paket Fotografi Baru'; ?></h1>
                <p style="color:var(--ink-soft); font-size:0.85rem; margin:0;">Data paket tersimpan terpusat dan langsung muncul di katalog reservasi klien.</p>
            </div>
            <div>
                <a href="admin_etalase.php" class="btn-cancel">Batal</a>
                <button type="submit" class="btn-submit">Simpan Paket ke Database</button>
            </div>
        </div>

        <div class="form-grid">
            <div>
                <!-- Kartu Informasi Utama -->
                <div class="form-card">
                    <h3>Detail Informasi Paket</h3>

                    <div class="form-group">
                        <label>Nama Paket Fotografi</label>
                        <input type="text" name="nama_paket" class="form-input" required value="<?= htmlspecialchars($paket['nama_paket']); ?>" placeholder="Contoh: Solo Degree Milestone">
                    </div>

                    <div>
                        <div class="form-group">
                            <label>Kategori Layanan</label>
                            <select name="kategori" class="form-select">
                                <option value="wisuda" <?= $paket['kategori'] === 'wisuda' ? 'selected' : ''; ?>>Wisuda & Akademik</option>
                                <option value="portrait" <?= $paket['kategori'] === 'portrait' ? 'selected' : ''; ?>>Editorial Portrait</option>
                                <option value="prewedding" <?= $paket['kategori'] === 'prewedding' ? 'selected' : ''; ?>>Prewedding & Pasangan</option>
                                <option value="family" <?= $paket['kategori'] === 'family' ? 'selected' : ''; ?>>Family & Group</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Deskripsi Singkat Paket</label>
                        <textarea name="deskripsi" class="form-textarea" rows="3" placeholder="Jelaskan konsep, nuansa pencahayaan, dan pengalaman sesi..."><?= htmlspecialchars($paket['deskripsi']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Fitur & Benefit Paket (1 per baris)</label>
                        <textarea name="fitur" class="form-textarea" rows="4" placeholder="10 File Master&#10;1 Cetak Bingkai Kayu 10R&#10;Ruang Rias Privat 30 Menit"><?= htmlspecialchars($paket['fitur']); ?></textarea>
                        <small style="color:var(--ink-faint); font-size:0.75rem;">Setiap baris baru akan ditampilkan sebagai poin checklist di etalase klien.</small>
                    </div>
                </div>

                <!-- Kartu Studio & Operasional -->
                <div class="form-card">
                    <h3>Spesifikasi Studio & Durasi Sesi</h3>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                        <div class="form-group">
                            <label>Durasi Sesi</label>
                            <input type="text" name="durasi" class="form-input" value="<?= htmlspecialchars($paket['durasi']); ?>" placeholder="Contoh: 2 Jam Sesi Foto">
                        </div>
                        <div class="form-group">
                            <label>Lokasi Studio</label>
                            <select name="lokasi_studio" class="form-select">
                                <option value="Studio A & B Cyclorama Senopati" <?= $paket['lokasi_studio'] === 'Studio A & B Cyclorama Senopati' ? 'selected' : ''; ?>>Studio A & B Cyclorama Senopati</option>
                                <option value="Studio B Daylight & Fashion Senopati" <?= $paket['lokasi_studio'] === 'Studio B Daylight & Fashion Senopati' ? 'selected' : ''; ?>>Studio B Daylight & Fashion Senopati</option>
                                <option value="Studio A & Garden Lumina Senopati" <?= $paket['lokasi_studio'] === 'Studio A & Garden Lumina Senopati' ? 'selected' : ''; ?>>Studio A & Garden Lumina Senopati</option>
                                <option value="Studio D Family Living Room & Lounge" <?= $paket['lokasi_studio'] === 'Studio D Family Living Room & Lounge' ? 'selected' : ''; ?>>Studio D Family Living Room & Lounge</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Foto Sampul</label>
                        <div class="cover-upload">
                            <div class="cover-preview">
                                <img id="cover-img" src="<?= htmlspecialchars($paket['gambar'] ?: 'jpg/studio.jpg'); ?>" alt="Foto sampul paket" onerror="this.onerror=null;this.src='jpg/studio.jpg'">
                            </div>
                            <div class="cover-actions">
                                <input type="file" name="gambar_file" id="gambar_file" class="form-input" accept="image/jpeg,image/png,image/webp">
                                <small>JPG, PNG, atau WEBP &bull; maks. <?= round($batas_foto / 1048576, 1); ?> MB &bull; disarankan landscape (rasio 4:3).<br>Kosongkan jika tidak ingin mengganti foto. Foto yang diunggah otomatis tampil di etalase klien dan History Pemesanan.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Kartu Highlight Badge & Sub-Tag (dua isian terpisah) -->
                <div class="form-card" id="kartu-badge">
                    <?php $hb_aktif = $paket['highlight_badge'] ?? ''; ?>
                    <h3>Highlight Badge &amp; Sub-Tag</h3>
                    <div class="badge-edit">
                        <div>
                            <!-- 1. Highlight Badge: TEKS BEBAS -->
                            <div class="badge-section">
                                <label class="badge-sec-title" for="highlight_badge">1 &middot; Highlight Badge <em>(teks bebas)</em></label>
                                <input type="text" name="highlight_badge" id="highlight_badge" class="form-input" maxlength="30" autocomplete="off" value="<?= htmlspecialchars($hb_aktif); ?>" placeholder="Contoh: Best Seller / Promo Akhir Tahun">
                                <small class="badge-count"><span id="highlight_badge_count">0</span>/30 karakter</small>
                                <?php $warna_aktif = array_key_exists($paket['highlight_warna'] ?? '', $opsi_warna) ? ($paket['highlight_warna'] ?? '') : ''; ?>
                                <span class="badge-mini-title" style="margin-top:14px;">Warna background</span>
                                <div class="hb-options">
                                    <?php foreach ($opsi_warna as $nilai => $cfg): ?>
                                        <label class="hb-opt">
                                            <input type="radio" name="highlight_warna" value="<?= $nilai; ?>" data-cls="<?= $cfg[1]; ?>" <?= $warna_aktif === $nilai ? 'checked' : ''; ?>>
                                            <span class="hb-pill <?= $cfg[1]; ?>"><?= $cfg[0]; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <small>Pill di pojok kiri atas foto paket pada etalase klien. Jika diisi, ia menggantikan Sub-Tag di posisi itu. Warna hanya berlaku bila badge diisi.</small>
                            </div>

                            <!-- 2. Sub-Tag: TEKS BEBAS -->
                            <div class="badge-section">
                                <label class="badge-sec-title" for="sub_tag">2 &middot; Sub-Tag <em>(teks bebas)</em></label>
                                <input type="text" name="sub_tag" id="sub_tag" class="form-input" maxlength="40" autocomplete="off" value="<?= htmlspecialchars($paket['sub_tag'] ?? ''); ?>" placeholder="Contoh: Solo Session / 2 - 3 Sahabat">
                                <small class="badge-count"><span id="sub_tag_count">0</span>/40 karakter</small>
                                <span class="badge-mini-title" style="margin-top:12px;">Pilih cepat</span>
                                <div class="badge-chips">
                                    <?php foreach ($saran_badge as $sb): ?>
                                        <button type="button" class="badge-chip" data-tag="<?= htmlspecialchars($sb, ENT_QUOTES); ?>"><?= htmlspecialchars($sb); ?></button>
                                    <?php endforeach; ?>
                                    <button type="button" class="badge-chip badge-chip-clear" id="badge-clear">Kosongkan</button>
                                </div>
                                <small>Label deskriptif paket. Wisuda &amp; Family menampilkannya juga di kanan bawah foto; Portrait &amp; Prewedding menampilkannya di baris kategori bila Highlight Badge dipilih.</small>
                            </div>
                        </div>
                        <div>
                            <span class="badge-mini-title">Pratinjau di etalase klien</span>
                            <div class="bpv-photo" id="bpv-photo">
                                <span class="bpv-tl" id="bpv-tl" hidden></span>
                                <span class="bpv-br" id="bpv-br" hidden></span>
                                <span class="bpv-empty" id="bpv-empty">Tanpa badge</span>
                            </div>
                            <div class="bpv-cat" id="bpv-cat" hidden></div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <!-- Kartu Finansial & Kuota -->
                <div class="form-card">
                    <h3>Harga & Skema DP</h3>
                    
                    <div class="form-group">
                        <label>Investasi Total (Harga Paket Rp)</label>
                        <input type="number" name="harga" class="form-input" style="font-size:1.1rem; font-weight:700;" required value="<?= (int)$paket['harga']; ?>" placeholder="Contoh: 1250000">
                    </div>

                    <div class="form-group">
                        <label>Minimal DP 30% (Rp)</label>
                        <input type="number" name="dp_minimal" class="form-input" value="<?= (int)$paket['dp_minimal']; ?>" placeholder="Otomatis 30% jika kosong">
                    </div>
                </div>

                <div class="form-card">
                    <h3>Stok & Kuota Slot Bulanan</h3>
                    
                    <div class="form-group">
                        <label>Kapasitas Kuota Total (Slot)</label>
                        <input type="number" name="kuota_total" class="form-input" required value="<?= (int)$paket['kuota_total']; ?>" min="1">
                    </div>

                    <div class="form-group">
                        <label>Kuota Terpakai Saat Ini</label>
                        <input type="number" name="kuota_terpakai" class="form-input" value="<?= (int)$paket['kuota_terpakai']; ?>" min="0">
                        <small style="color:var(--ink-faint); font-size:0.75rem;">Sisa slot akan dihitung otomatis di etalase klien.</small>
                    </div>

                    <div class="form-group">
                        <label>Status Publikasi di Web</label>
                        <select name="status" class="form-select">
                            <option value="aktif" <?= $paket['status'] === 'aktif' ? 'selected' : ''; ?>>Aktif (Tampil di Etalase)</option>
                            <option value="nonaktif" <?= $paket['status'] === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif (Sembunyikan)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </form>
</main>
<script>
// Pratinjau langsung saat admin memilih foto baru
document.getElementById('gambar_file').addEventListener('change', function () {
    var f = this.files[0];
    if (!f) return;
    var batas = <?= (int)$batas_foto; ?>;
    if (f.size > batas) {
        alert('Ukuran foto maksimal ' + (batas / 1048576).toFixed(1) + ' MB. Pilih file yang lebih kecil.');
        this.value = '';
        return;
    }
    document.getElementById('cover-img').src = URL.createObjectURL(f);
});

// Kartu Highlight Badge & Sub-Tag: pratinjau langsung, penghitung karakter, dan pilih cepat
(function () {
    var input = document.getElementById('sub_tag');
    var kategori = document.querySelector('select[name="kategori"]');
    var photo = document.getElementById('bpv-photo');
    var cover = document.getElementById('cover-img');
    var tl = document.getElementById('bpv-tl');
    var br = document.getElementById('bpv-br');
    var cat = document.getElementById('bpv-cat');
    var kosong = document.getElementById('bpv-empty');
    var hitung = document.getElementById('sub_tag_count');
    var chips = document.querySelectorAll('.badge-chip[data-tag]');
    var hbInput = document.getElementById('highlight_badge');
    var hbHitung = document.getElementById('highlight_badge_count');

    var warnaRadios = document.querySelectorAll('input[name="highlight_warna"]');
    function kelasWarna() {
        var r = document.querySelector('input[name="highlight_warna"]:checked');
        var k = r ? r.getAttribute('data-cls') : '';
        return (k === 'hb-none') ? '' : k;   // putih = gaya bawaan pill
    }
    function render() {
        var sub = input.value.trim();
        var kat = kategori.value;
        var hb = { nilai: hbInput.value.trim(), kelas: kelasWarna() };
        var teksTl = (hb.nilai !== '') ? hb.nilai : sub;   // sama seperti index.php
        hitung.textContent = input.value.length;
        hbHitung.textContent = hbInput.value.length;

        tl.textContent = teksTl;
        tl.hidden = (teksTl === '');
        kosong.hidden = (teksTl !== '');
        tl.className = 'bpv-tl';
        if (hb.nilai !== '') {
            if (hb.kelas) tl.classList.add(hb.kelas);
        } else if (kat === 'portrait') {                    // data lama: gaya dari kata kunci Sub-Tag
            if (/best seller/i.test(sub)) tl.classList.add('hb-dark');
            else if (/haute couture/i.test(sub)) tl.classList.add('hb-amber');
        }

        br.textContent = sub;
        br.hidden = (sub === '') || !(kat === 'wisuda' || kat === 'family');

        var tampilCat = (hb.nilai !== '' && sub !== '' && (kat === 'portrait' || kat === 'prewedding'));
        cat.hidden = !tampilCat;
        cat.textContent = kategori.options[kategori.selectedIndex].text + ' \u2022 ' + sub;

        chips.forEach(function (c) { c.classList.toggle('is-active', c.getAttribute('data-tag').toLowerCase() === sub.toLowerCase()); });
    }
    function sinkronFoto() { photo.style.backgroundImage = 'url("' + cover.src + '")'; }

    input.addEventListener('input', render);
    kategori.addEventListener('change', render);
    hbInput.addEventListener('input', render);
    warnaRadios.forEach(function (r) { r.addEventListener('change', render); });
    chips.forEach(function (c) {
        c.addEventListener('click', function () { input.value = c.getAttribute('data-tag'); render(); input.focus(); });
    });
    document.getElementById('badge-clear').addEventListener('click', function () { input.value = ''; render(); input.focus(); });
    cover.addEventListener('load', sinkronFoto);
    sinkronFoto();
    render();
})();
</script>
</body>
</html>