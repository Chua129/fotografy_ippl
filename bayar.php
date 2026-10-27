<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$nama_user = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Klien';

// Token CSRF agar form tidak bisa dikirim dari situs lain
if (empty($_SESSION['csrf_bayar'])) {
    $_SESSION['csrf_bayar'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_bayar'];

// Ambil data pesanan
$pesanan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($pesanan_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM pesanan WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $pesanan_id, $user_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM pesanan WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$pesanan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pesanan) {
    header("Location: index.php");
    exit;
}

/* =====================================================================
   JENIS PEMBAYARAN DITENTUKAN DARI DATA DI DATABASE, BUKAN DARI INPUT USER.
   (Sebelumnya field "is_pelunasan" dikirim lewat form sehingga bisa diubah
   user lewat DevTools untuk memaksa status menjadi lunas.)
===================================================================== */
$status_pesanan   = $pesanan['status'];
$sisa_tagihan     = (float)$pesanan['sisa_tagihan'];
$status_pelunasan = isset($pesanan['status_pelunasan']) ? $pesanan['status_pelunasan'] : null;

$is_pembayaran_awal = ($status_pesanan === 'Menunggu Pembayaran');
$is_pelunasan       = ($status_pesanan === 'Dikonfirmasi' && $sisa_tagihan > 0);

// Pesanan yang tidak sedang menunggu pembayaran tidak boleh membuka halaman ini
if (!$is_pembayaran_awal && !$is_pelunasan) {
    header("Location: pemesanan.php");
    exit;
}
// Bukti pelunasan sudah dikirim dan sedang diperiksa admin
if ($is_pelunasan && $status_pelunasan === 'Menunggu Verifikasi') {
    header("Location: pemesanan.php?msg=pelunasan_menunggu");
    exit;
}

/**
 * Validasi & simpan bukti pembayaran.
 * Mengembalikan nama file yang tersimpan, atau null jika gagal ($error terisi).
 */
function simpan_bukti_bayar($file, $prefix, &$error)
{
    // Field file tidak dikirim sama sekali = belum ada bukti
    if ($file === null) {
        $error = 'Anda wajib mengunggah bukti pembayaran sebelum mengonfirmasi.';
        return null;
    }
    if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
        $error = 'Berkas tidak valid. Silakan unggah ulang bukti pembayaran.';
        return null;
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Anda wajib mengunggah bukti pembayaran sebelum mengonfirmasi.';
        return null;
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $error = 'Ukuran file terlalu besar. Maksimal 2 MB.';
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Unggahan gagal (kode ' . (int)$file['error'] . '). Silakan coba lagi.';
        return null;
    }
    if ($file['size'] <= 0 || $file['size'] > 2 * 1024 * 1024) {
        $error = 'Ukuran file terlalu besar. Maksimal 2 MB.';
        return null;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        $error = 'Berkas tidak valid. Silakan unggah ulang bukti pembayaran.';
        return null;
    }

    // Cek tipe asli file dari isinya (bukan dari ekstensi / header buatan user)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $diizinkan = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
    if (!isset($diizinkan[$mime])) {
        $error = 'Format file harus berupa gambar JPG, PNG, atau WEBP.';
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || $info[0] < 200 || $info[1] < 200) {
        $error = 'File bukan gambar bukti pembayaran yang valid.';
        return null;
    }

    $dir = __DIR__ . '/uploads/bukti/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        $error = 'Server gagal menyiapkan folder penyimpanan. Hubungi admin studio.';
        return null;
    }
    // Cegah directory listing
    if (!file_exists($dir . 'index.html')) {
        @file_put_contents($dir . 'index.html', '');
    }

    // Nama & ekstensi dibuat server, jadi file tidak bisa dieksekusi sebagai script
    $nama = $prefix . '_' . bin2hex(random_bytes(16)) . '.' . $diizinkan[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . $nama)) {
        $error = 'Gagal menyimpan bukti pembayaran. Silakan coba lagi.';
        return null;
    }
    return $nama;
}

$error = '';

// Jika tombol konfirmasi pembayaran ditekan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['konfirmasi_bayar'])) {
    $token_post = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';

    if (!hash_equals($csrf_token, $token_post)) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        // Pesanan yang dipakai SELALU pesanan yang dimuat di atas (milik user ini),
        // bukan ID dari input form.
        $p_id = (int)$pesanan['id'];

        if ($is_pelunasan) {
            // ---------- PELUNASAN: hanya mengajukan bukti, TIDAK langsung lunas ----------
            $file_bukti = simpan_bukti_bayar(isset($_FILES['bukti']) ? $_FILES['bukti'] : null, 'lunas_' . $p_id, $error);
            if ($file_bukti !== null) {
                $catatan_tambah = " | [PELUNASAN DIAJUKAN: Rp " . number_format($sisa_tagihan, 0, ',', '.') . " via QRIS pada " . date('d/m/Y H:i') . " - menunggu verifikasi admin]";

                $upd = $conn->prepare("UPDATE pesanan
                    SET bukti_pelunasan = ?, status_pelunasan = 'Menunggu Verifikasi', catatan = CONCAT(IFNULL(catatan, ''), ?)
                    WHERE id = ? AND user_id = ? AND status = 'Dikonfirmasi' AND sisa_tagihan > 0
                      AND (status_pelunasan IS NULL OR status_pelunasan <> 'Menunggu Verifikasi')");
                $upd->bind_param("ssii", $file_bukti, $catatan_tambah, $p_id, $user_id);
                $upd->execute();
                $berhasil = ($upd->affected_rows === 1);
                $upd->close();

                if ($berhasil) {
                    header("Location: pemesanan.php?msg=pelunasan_menunggu");
                    exit;
                }
                @unlink(__DIR__ . '/uploads/bukti/' . $file_bukti);
                $error = 'Pelunasan tidak dapat diproses. Periksa status reservasi Anda.';
            }
        } else {
            // ---------- PEMBAYARAN AWAL (DP / Full): wajib bukti, lalu menunggu admin ----------
            $file_bukti = simpan_bukti_bayar(isset($_FILES['bukti']) ? $_FILES['bukti'] : null, 'bayar_' . $p_id, $error);
            if ($file_bukti !== null) {
                $upd = $conn->prepare("UPDATE pesanan
                    SET status = 'Menunggu Konfirmasi', bukti_bayar = ?
                    WHERE id = ? AND user_id = ? AND status = 'Menunggu Pembayaran'");
                $upd->bind_param("sii", $file_bukti, $p_id, $user_id);
                $upd->execute();
                $berhasil = ($upd->affected_rows === 1);
                $upd->close();

                if ($berhasil) {
                    header("Location: sukses.php?id=" . $p_id);
                    exit;
                }
                @unlink(__DIR__ . '/uploads/bukti/' . $file_bukti);
                $error = 'Pembayaran tidak dapat diproses. Periksa status reservasi Anda.';
            }
        }
    }
}

$nominal_bayar = $is_pelunasan ? $sisa_tagihan : (float)$pesanan['jumlah_dibayar'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Selesaikan Pembayaran - Creative Pixel</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<style>
.payment-container { max-width: 800px; margin: 50px auto 100px; padding: 0 20px; text-align: center; }

/* Header Pembayaran */
.pay-icon { width: 56px; height: 56px; background: #FFF9F2; color: #D9822B; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px; border: 4px solid #F6E2CD; }
.pay-status { display: inline-block; font-size: 0.7rem; font-weight: 700; color: #D9822B; background: #FFF9F2; padding: 6px 14px; border-radius: 999px; letter-spacing: 0.05em; margin-bottom: 16px; border: 1px solid #F6E2CD; }
.pay-title { font-family: var(--font-serif); font-size: 2.2rem; font-weight: 500; color: var(--ink); margin: 0 0 12px; line-height: 1.2; }
.pay-subtitle { font-size: 0.95rem; color: var(--ink-soft); max-width: 550px; margin: 0 auto 32px; }

/* Pesan error */
.alert-error { background: #FDECEC; color: #9B1C1C; border: 1px solid #F8C9C9; padding: 12px 18px; border-radius: 10px; margin: 0 auto 24px; max-width: 550px; font-size: 0.85rem; font-weight: 600; text-align: left; }

/* QR Code Box */
.qr-box { background: #fff; border: 1px solid var(--line); border-radius: 24px; padding: 36px; display: inline-block; box-shadow: 0 10px 40px -10px rgba(0,0,0,0.05); margin-bottom: 32px; max-width: 450px; width: 100%; box-sizing: border-box; }
.qr-box img { width: 220px; height: 220px; object-fit: contain; }
/* Gambar QRIS: bingkai putih (quiet zone) agar tetap mudah dipindai */
.qr-box img.qris-img { display: block; margin: 0 auto; max-width: 100%; width: 260px; height: 260px; padding: 14px; background: #fff; border: 1px solid var(--line); border-radius: 14px; box-sizing: border-box; }
.qr-manual { font-size: 0.8rem; line-height: 1.45; background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; border-radius: 8px; padding: 8px 12px; margin: 12px 0 10px; }
.qr-amount { font-family: var(--font-serif); font-size: 2rem; color: var(--accent); margin: 20px 0 8px; font-weight: 600; }
.qr-scan-note { font-size: 0.8rem; color: var(--ink-soft); margin: 0; line-height: 1.4; }

.order-info-chip { display: flex; justify-content: space-between; background: #FAF9F6; border: 1px solid var(--line); border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; text-align: left; font-size: 0.85rem; }
.order-info-chip div span { font-size: 0.7rem; color: var(--ink-faint); display: block; font-weight: 700; text-transform: uppercase; }

/* Next Steps Box */
.next-steps { background: #FAF9F6; border: 1px solid var(--line); border-radius: 16px; padding: 24px 32px; display: flex; flex-direction: column; align-items: stretch; gap: 20px; text-align: left; }
.ns-left h4 { font-size: 1rem; font-weight: 600; color: var(--ink); margin: 0 0 6px; }
.ns-left p { font-size: 0.85rem; color: var(--ink-soft); margin: 0; max-width: 520px; }
.ns-form { margin: 0; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.ns-actions { display: flex; gap: 12px; }
.btn-outline { border: 1px solid var(--line); background: #fff; color: var(--ink); padding: 12px 20px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; text-align: center; text-decoration: none; }
.btn-dark { border: none; background: #1B1A17; color: #fff; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; }
.btn-dark:hover { opacity: 0.9; }
.btn-dark:disabled { opacity: 0.4; cursor: not-allowed; }

/* Upload bukti */
.upload-box { flex: 1 1 280px; display: flex; align-items: center; gap: 12px; border: 1.5px dashed #CFC9BC; border-radius: 12px; padding: 10px 14px; background: #fff; cursor: pointer; min-width: 0; }
.upload-box:hover { border-color: var(--accent); }
.upload-box.has-file { border-style: solid; border-color: #2E7D32; }
.upload-icon { width: 44px; height: 44px; border-radius: 8px; background: #F3F1EC; color: var(--ink-soft); display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }
.upload-thumb { width: 44px; height: 44px; object-fit: cover; display: none; }
.upload-text { min-width: 0; }
.upload-text strong { display: block; font-size: 0.82rem; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.upload-text span { font-size: 0.72rem; color: var(--ink-faint); }
.sr-only-file { position: absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden; pointer-events: none; }

@media (max-width: 768px) { .next-steps { padding: 20px; } .ns-form { flex-direction: column; align-items: stretch; } .ns-actions { width: 100%; flex-direction: column; } }
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
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user); ?>&background=F3F1EC&color=1B1A17" alt="Avatar">
            <div class="user-chip-text">
              <strong><?= htmlspecialchars($nama_user); ?></strong>
              <span>Studio Account</span>
            </div>
        </a>
        <a href="logout.php" class="logout-link">Keluar</a>
      </div>
    </div>
  </div>
</header>

<main class="payment-container">
    <div class="pay-icon" style="<?= $is_pelunasan ? 'background:#E8F5E9; border-color:#C8E6C9; color:#2E7D32;' : ''; ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
    </div>
    
    <div>
        <?php if ($is_pelunasan): ?>
            <span class="pay-status" style="background:#E8F5E9; border-color:#C8E6C9; color:#2E7D32;">
                <i class="dot dot-green" style="background:#2E7D32; margin-left:0; margin-right:6px;"></i> TAHAP 2 • PELUNASAN SISA TAGIHAN
            </span>
        <?php else: ?>
            <span class="pay-status">
                <i class="dot dot-amber" style="background:#D9822B; margin-left:0; margin-right:6px;"></i> STATUS: <?= strtoupper(htmlspecialchars($pesanan['status'])); ?>
            </span>
        <?php endif; ?>
    </div>
    
    <h1 class="pay-title"><?= $is_pelunasan ? 'Pelunasan Sisa Tagihan Studio' : 'Pembayaran QRIS'; ?></h1>
    <p class="pay-subtitle">
        <?= $is_pelunasan 
            ? 'Scan kode QR di bawah untuk melunasi sisa biaya sesi foto Anda, lalu unggah bukti pembayaran untuk diverifikasi admin.' 
            : 'Scan kode QR di bawah untuk membayar, lalu unggah bukti pembayaran agar reservasi Anda dapat diverifikasi.'; ?>
    </p>

    <?php if ($error !== ''): ?>
        <div class="alert-error"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="qr-box">
        <div class="order-info-chip">
            <div>
                <span>Kode Booking</span>
                <strong><?= htmlspecialchars($pesanan['kode_reservasi']); ?></strong>
            </div>
            <div style="text-align:right;">
                <span>Paket Layanan</span>
                <strong><?= htmlspecialchars($pesanan['paket_layanan']); ?></strong>
            </div>
        </div>

        <?php if ($is_pelunasan): ?>
        <div style="background:#FAF9F6; border:1px solid var(--line); border-radius:12px; padding:12px 18px; margin-bottom:20px; font-size:0.82rem; text-align:left;">
            <div style="display:flex; justify-content:space-between; margin-bottom:4px; color:var(--ink-soft);">
                <span>Total Nilai Paket:</span>
                <span>Rp <?= number_format($pesanan['total_harga'], 0, ',', '.'); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:4px; color:var(--green); font-weight:600;">
                <span>DP 30% Telah Terbayar:</span>
                <span>- Rp <?= number_format($pesanan['jumlah_dibayar'], 0, ',', '.'); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; font-weight:700; color:var(--accent); border-top:1px dashed var(--line); padding-top:6px; margin-top:4px;">
                <span>Total Pelunasan Sekarang:</span>
                <span>Rp <?= number_format($nominal_bayar, 0, ',', '.'); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- QRIS statis milik studio (file: qris.jpg) -->
        <img src="jpg/WhatsApp Image 2026-10-15 at 16.33.12.jpeg?v=<?= (int)@filemtime(__DIR__ . '/jpg/WhatsApp Image 2026-10-15 at 16.33.12.jpeg'); ?>" alt="QRIS pembayaran Creative Pixel" class="qris-img">

        <h3 class="qr-amount" style="<?= $is_pelunasan ? 'color:var(--dark);' : ''; ?>">Rp <?= number_format($nominal_bayar, 0, ',', '.'); ?></h3>
        <p class="qr-manual">QRIS ini bersifat statis. Setelah memindai, <strong>masukkan nominal Rp <?= number_format($nominal_bayar, 0, ',', '.'); ?> secara manual</strong> sesuai tagihan, lalu simpan bukti pembayarannya.</p>
        <p class="qr-scan-note">Dapat dipindai melalui aplikasi E-Wallet atau M-Banking Anda (BCA, Mandiri, GoPay, OVO, ShopeePay, Dana, dll).</p>
    </div>

    <div class="next-steps">
        <div class="ns-left">
            <span style="font-size:0.65rem; font-weight:700; color:var(--ink-faint); text-transform:uppercase; letter-spacing:0.05em; display:block; margin-bottom:4px;">KONFIRMASI PEMBAYARAN</span>
            <h4>Sudah menyelesaikan pembayaran?</h4>
            <p>Unggah screenshot atau foto bukti pembayaran (nominal Rp <?= number_format($nominal_bayar, 0, ',', '.'); ?>) terlebih dahulu. Tombol konfirmasi baru aktif setelah bukti dipilih, lalu admin studio akan memverifikasi pembayaran Anda.</p>
        </div>

        <form method="POST" action="bayar.php?id=<?= (int)$pesanan['id']; ?>" enctype="multipart/form-data" class="ns-form" id="formBayar">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="2097152">

            <label class="upload-box" id="uploadBox" for="inputBukti">
                <span class="upload-icon">
                    <svg id="uploadIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                    <img id="uploadThumb" class="upload-thumb" alt="Pratinjau bukti">
                </span>
                <span class="upload-text">
                    <strong id="uploadName">Pilih bukti pembayaran</strong>
                    <span>JPG, PNG, atau WEBP • maks. 2 MB</span>
                </span>
            </label>
            <input type="file" name="bukti" id="inputBukti" class="sr-only-file" accept="image/jpeg,image/png,image/webp" required>

            <div class="ns-actions">
                <button type="submit" name="konfirmasi_bayar" id="btnKonfirmasi" class="btn-dark" style="<?= $is_pelunasan ? 'background:#00A859;' : ''; ?>" disabled>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span id="btnLabel"><?= $is_pelunasan ? 'Kirim Bukti Pelunasan' : 'Saya Sudah Membayar'; ?></span>
                </button>
                <a href="pemesanan.php" class="btn-outline">Cek Status</a>
            </div>
        </form>
    </div>
</main>

<script>
(function () {
    var input  = document.getElementById('inputBukti');
    var btn    = document.getElementById('btnKonfirmasi');
    var label  = document.getElementById('btnLabel');
    var box    = document.getElementById('uploadBox');
    var name   = document.getElementById('uploadName');
    var thumb  = document.getElementById('uploadThumb');
    var icon   = document.getElementById('uploadIcon');
    var form   = document.getElementById('formBayar');
    var tipeOk = ['image/jpeg', 'image/png', 'image/webp'];
    var maks   = 2 * 1024 * 1024;

    function reset(pesan) {
        input.value = '';
        btn.disabled = true;
        box.classList.remove('has-file');
        thumb.style.display = 'none';
        thumb.removeAttribute('src');
        icon.style.display = '';
        name.textContent = pesan || 'Pilih bukti pembayaran';
    }

    // Catatan: pengecekan di browser hanya untuk kenyamanan.
    // Validasi yang sebenarnya dilakukan di server (bayar.php).
    input.addEventListener('change', function () {
        var f = input.files && input.files[0];
        if (!f) { reset(); return; }
        if (tipeOk.indexOf(f.type) === -1) { reset('Format harus JPG, PNG, atau WEBP'); return; }
        if (f.size > maks) { reset('Ukuran file melebihi 2 MB'); return; }

        name.textContent = f.name;
        box.classList.add('has-file');
        btn.disabled = false;

        var reader = new FileReader();
        reader.onload = function (e) {
            thumb.src = e.target.result;
            thumb.style.display = 'block';
            icon.style.display = 'none';
        };
        reader.readAsDataURL(f);
    });

    form.addEventListener('submit', function (e) {
        if (!input.files || !input.files[0]) {
            e.preventDefault();
            btn.disabled = true;
            return;
        }
        // Cegah klik ganda
        label.textContent = 'Mengirim...';
        setTimeout(function () { btn.disabled = true; }, 0);
    });
})();
</script>

</body>
</html>