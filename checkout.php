<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$nama_user = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Klien';

// Ambil info kontak pengguna dari database
$stmt_u = $conn->prepare("SELECT nama, email, whatsapp FROM users WHERE id = ?");
$stmt_u->bind_param("i", $user_id);
$stmt_u->execute();
$user_data = $stmt_u->get_result()->fetch_assoc();
$stmt_u->close();

$error_msg = '';

// ===== KUNCI SLOT SEMENTARA 15 MENIT =====
// Timer dimulai saat user membuka checkout & disimpan di session, jadi tidak reset ketika halaman di-refresh.
const DURASI_KUNCI_DETIK = 15 * 60;
$lock_habis = false;

// PROSES SUBMIT PEMESANAN KE DATABASE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_reservasi'])) {
    $paket_layanan = trim($_POST['paket_layanan'] ?? 'Paket Foto Studio');
    $tanggal_sesi = $_POST['tanggal_sesi'] ?? date('Y-m-d', strtotime('+3 days'));
    $jam_sesi = $_POST['jam_sesi'] ?? '09.00 - 11.00 WIB';
    $lokasi_studio = $_POST['lokasi_studio'] ?? 'Studio A & Garden Lumina Senopati';
    $nama_pemesan = trim($_POST['nama_pemesan'] ?? $nama_user);
    $nama_pasangan = trim($_POST['nama_pasangan'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');
    $moodboard = trim($_POST['moodboard'] ?? '');
    $skema_pembayaran = (($_POST['skema'] ?? 'dp') === 'lunas') ? 'lunas' : 'dp';
    $metode_pembayaran = (($_POST['metode'] ?? 'qris') === 'tempat') ? 'tempat' : 'qris';
    $bayar_di_tempat = ($metode_pembayaran === 'tempat');
    $total_harga = (float)($_POST['total_harga'] ?? 3500000);
    $addons_list = trim($_POST['addons_list'] ?? '');
    $addons_total = (float)($_POST['addons_total'] ?? 0);

    if ($bayar_di_tempat) {
        // Bayar di tempat: tanpa DP & tanpa pembayaran online, seluruh total dibayar di kasir studio
        $skema_pembayaran = 'tempat';
        $jumlah_dibayar = 0.00;
        $sisa_tagihan = $total_harga;
        $status_awal = 'Menunggu Konfirmasi';   // e-tiket langsung terbit, tinggal dikonfirmasi admin
    } elseif ($skema_pembayaran === 'lunas') {
        $jumlah_dibayar = $total_harga;
        $sisa_tagihan = 0.00;
        $status_awal = 'Menunggu Pembayaran';
    } else {
        $jumlah_dibayar = (float)round($total_harga * 0.3); // 30% DP
        $sisa_tagihan = $total_harga - $jumlah_dibayar;
        $status_awal = 'Menunggu Pembayaran';
    }

    // Integritas Database: Gabungkan add-ons dan konsep ke catatan
    $catatan_full = "";
    if (!empty($addons_list)) {
        $catatan_full .= "Add-ons: " . $addons_list;
    }
    if (!empty($moodboard)) {
        $catatan_full .= (!empty($catatan_full) ? " | " : "") . "Konsep: " . $moodboard;
    }
    if (!empty($catatan)) {
        $catatan_full .= (!empty($catatan_full) ? " | Catatan: " : "") . $catatan;
    }

    // Buat kode reservasi unik: CP-YYYYMMDD-XXXX
    $kode_reservasi = 'CP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

    // Validasi: kunci slot harus masih berlaku untuk slot yang sama
    $kunci_post = md5($paket_layanan . '|' . $tanggal_sesi . '|' . $jam_sesi);
    $lock = $_SESSION['checkout_lock'] ?? null;
    if (!$lock || $lock['key'] !== $kunci_post || $lock['expires'] <= time()) {
        $lock_habis = true;
        unset($_SESSION['checkout_lock']);
        $error_msg = 'Waktu penguncian slot (15 menit) sudah habis dan slot dirilis kembali. Silakan pilih jadwal ulang.';
    }

    if (!$lock_habis) {
    try {
        $insert = $conn->prepare("INSERT INTO pesanan (user_id, kode_reservasi, paket_layanan, tanggal_sesi, jam_sesi, lokasi_studio, nama_pasangan, skema_pembayaran, metode_pembayaran, total_harga, jumlah_dibayar, sisa_tagihan, catatan, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->bind_param("issssssssdddss", 
            $user_id, $kode_reservasi, $paket_layanan, $tanggal_sesi, $jam_sesi, $lokasi_studio, 
            $nama_pasangan, $skema_pembayaran, $metode_pembayaran, $total_harga, $jumlah_dibayar, $sisa_tagihan, $catatan_full, $status_awal
        );

        if ($insert->execute()) {
            $pesanan_id = $insert->insert_id;
            $insert->close();

            // Sinkronisasi otomatis stok & kuota terpakai pada paket
            $stmt_up = $conn->prepare("UPDATE paket SET kuota_terpakai = kuota_terpakai + 1 WHERE (nama_paket = ? OR ? LIKE CONCAT('%', nama_paket, '%')) AND kuota_terpakai < kuota_total LIMIT 1");
            if ($stmt_up) {
                $stmt_up->bind_param("ss", $paket_layanan, $paket_layanan);
                $stmt_up->execute();
                $stmt_up->close();
            }

            // Slot sudah resmi dipesan, kunci sementara tidak diperlukan lagi
            unset($_SESSION['checkout_lock']);

            // Bayar di tempat: e-tiket langsung terbit. QRIS: lanjut ke halaman pembayaran
            header("Location: " . ($bayar_di_tempat ? "tiket.php?id=" : "bayar.php?id=") . $pesanan_id);
            exit;
        } else {
            $error_msg = "Gagal membuat reservasi: " . $conn->error;
        }
    } catch (Exception $e) {
        $error_msg = "Terjadi kesalahan: " . $e->getMessage();
    }
    }
}

// Data dari jadwal.php atau URL
$paket_layanan = $_POST['paket_layanan'] ?? ($_GET['paket'] ?? 'Prewedding & Intimate Couple Session');
$harga_dasar = (float)($_POST['harga_dasar'] ?? ($_GET['harga'] ?? 3500000));
$addons_total = (float)($_POST['addons_total'] ?? 0);
$addons_list = trim($_POST['addons_list'] ?? '');
$total_harga = (float)($_POST['total_harga'] ?? ($harga_dasar + $addons_total));

// Integrity Check: total_harga harus minimal sama dengan harga_dasar + addons_total
if ($total_harga < ($harga_dasar + $addons_total)) {
    $total_harga = $harga_dasar + $addons_total;
}

$tanggal_sesi = $_POST['tanggal_sesi'] ?? date('Y-m-d', strtotime('+3 days'));
$jam_sesi = $_POST['waktu_sesi'] ?? ($_POST['jam_sesi'] ?? '09.00 - 11.00 WIB');
$lokasi_studio = $_POST['lokasi_studio'] ?? 'Studio A & Garden Lumina Senopati';

// Foto & durasi paket diambil dari tabel `paket` sesuai paket yang dipilih klien
// (supaya foto di Rangkuman Booking selalu sama dengan foto paket di etalase)
$paket_foto = 'jpg/studio.jpg';
$paket_durasi = '';
$nama_paket_raw = trim($paket_layanan);
$nama_paket_dec = html_entity_decode($nama_paket_raw, ENT_QUOTES, 'UTF-8'); // jadwal.php mengirim nama yang sudah di-escape (&amp;)
$stmt_pk = $conn->prepare("SELECT gambar, durasi FROM paket WHERE nama_paket = ? OR nama_paket = ? LIMIT 1");
if ($stmt_pk) {
    $stmt_pk->bind_param("ss", $nama_paket_dec, $nama_paket_raw);
    $stmt_pk->execute();
    $row_pk = $stmt_pk->get_result()->fetch_assoc();
    $stmt_pk->close();
    if ($row_pk) {
        if (!empty(trim((string)$row_pk['gambar']))) $paket_foto = trim($row_pk['gambar']);
        $paket_durasi = trim((string)($row_pk['durasi'] ?? ''));
    }
}

$dp_nominal = round($total_harga * 0.3);
$sisa_nominal = $total_harga - $dp_nominal;

// Timer kunci slot: mulai saat pertama kali halaman dibuka, lanjut jika di-refresh (slot yang sama)
$kunci_slot = md5(trim($paket_layanan) . '|' . $tanggal_sesi . '|' . $jam_sesi);
$lock = $_SESSION['checkout_lock'] ?? null;
if (!$lock_habis && (!$lock || $lock['key'] !== $kunci_slot || $lock['expires'] <= time())) {
    $_SESSION['checkout_lock'] = ['key' => $kunci_slot, 'expires' => time() + DURASI_KUNCI_DETIK];
}
$sisa_detik = $lock_habis ? 0 : max(0, $_SESSION['checkout_lock']['expires'] - time());
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout & Pembayaran - Creative Pixel</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= time(); ?>">
<style>
/* CSS Khusus Checkout */
.checkout-wrap { max-width: 1240px; margin: 40px auto 80px; padding: 0 32px; }

/* Progress Bar */
.step-indicator { display: flex; justify-content: space-between; align-items: center; max-width: 800px; margin: 0 auto 40px; position: relative; }
.step-indicator::before { content: ""; position: absolute; top: 12px; left: 10%; right: 10%; height: 2px; background: #EAEAEA; z-index: 1; }
.step-item { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; font-weight: 600; color: var(--ink-faint); z-index: 2; background: #FAF9F6; padding: 0 10px; }
.step-item.active { color: #D9822B; }
.step-item.done { color: var(--green); }
.step-circle { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; color: #fff; }
.done .step-circle { background: var(--green); }
.active .step-circle { background: #D9822B; }
.pending .step-circle { background: #EAEAEA; color: var(--ink-soft); }

/* Banner Timer */
.timer-banner { background: #FFF9F2; border: 1px solid #F6E2CD; border-radius: 8px; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 40px; transition: background .3s, border-color .3s; }
.timer-text { font-size: 0.85rem; color: #D9822B; font-weight: 600; display: flex; align-items: center; gap: 8px; }
.timer-clock { font-family: var(--font-serif); font-size: 1.5rem; font-weight: 600; color: #D9822B; font-variant-numeric: tabular-nums; letter-spacing: 0.02em; margin-left: 6px; }
.timer-note { font-size: 0.75rem; color: var(--ink-soft); }
.timer-banner.is-urgent { background: #FFF5F5; border-color: #FED7D7; }
.timer-banner.is-urgent .timer-text, .timer-banner.is-urgent .timer-clock { color: #C53030; }

/* Modal waktu habis */
.lock-overlay { position: fixed; inset: 0; background: rgba(27,26,23,0.6); display: none; align-items: center; justify-content: center; z-index: 999; padding: 24px; }
.lock-overlay.show { display: flex; }
.lock-modal { background: #fff; border-radius: 16px; padding: 36px; max-width: 420px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.25); }
.lock-modal h3 { font-family: var(--font-serif); font-size: 1.5rem; margin: 0 0 10px; color: var(--ink); }
.lock-modal p { font-size: 0.9rem; color: var(--ink-soft); line-height: 1.5; margin: 0 0 24px; }
.lock-modal a { display: inline-block; background: var(--dark); color: #fff; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; text-decoration: none; }

/* Layout Grid */
.checkout-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 40px; }

/* Form Sections */
.form-section { margin-bottom: 40px; }
.form-title { font-family: var(--font-serif); font-size: 1.3rem; margin: 0 0 24px; display: flex; align-items: center; gap: 12px; color: var(--ink); border-bottom: 1px dashed var(--line); padding-bottom: 16px; }
.form-title span { font-size: 0.8rem; font-family: var(--font-sans); color: var(--ink-faint); background: #F4F4F4; padding: 4px 10px; border-radius: 999px; }

.form-group-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.form-group label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--ink); margin-bottom: 8px; }
.form-group label span { color: var(--red); }
.form-control { width: 100%; padding: 12px 16px; border: 1px solid var(--line); border-radius: 8px; font-size: 0.9rem; font-family: inherit; box-sizing: border-box; }
.form-control:focus { outline: none; border-color: var(--dark); }

/* Options (Skema & Metode) */
.radio-box { border: 1px solid var(--line); border-radius: 8px; padding: 16px; margin-bottom: 12px; cursor: pointer; display: flex; gap: 16px; background: #fff; transition: 0.2s; align-items: flex-start; }
.radio-box:hover { border-color: var(--dark); }
.radio-box input[type="radio"] { margin-top: 4px; accent-color: var(--dark); width: 18px; height: 18px; }
.radio-content { flex: 1; }
.radio-head { display: flex; justify-content: space-between; margin-bottom: 4px; }
.radio-title { font-size: 0.95rem; font-weight: 600; margin: 0; color: var(--ink); }
.radio-price { font-size: 1rem; font-weight: 700; margin: 0; color: var(--ink); }
.radio-desc { font-size: 0.8rem; color: var(--ink-soft); margin: 0 0 8px; }
.radio-tag { font-size: 0.65rem; background: #FFF9F2; color: #D9822B; padding: 4px 8px; border-radius: 4px; font-weight: 600; }
.form-section.is-muted .radio-box { opacity: 0.45; pointer-events: none; }
.skema-muted-note { font-size: 0.8rem; color: var(--ink-soft); background: #F6F4EF; border-radius: 8px; padding: 10px 14px; margin: 0 0 12px; }
.btn-pay:disabled { opacity: 0.5; cursor: not-allowed; }

/* Summary Sidebar */
.summary-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 32px; position: sticky; top: 100px; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.04); }
.sc-eyebrow { font-size: 0.65rem; font-weight: 700; color: var(--ink-faint); text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 6px; }
.sc-title { font-family: var(--font-serif); font-size: 1.4rem; font-weight: 500; margin: 0 0 24px; border-bottom: 1px dashed var(--line); padding-bottom: 16px; }
.sc-item { display: flex; gap: 12px; margin-bottom: 16px; align-items: flex-start; }
.sc-item svg { color: #D9822B; margin-top: 2px; flex-shrink: 0; }
.sc-item h5 { font-size: 0.8rem; font-weight: 600; margin: 0 0 4px; color: var(--ink); }
.sc-item p { font-size: 0.75rem; color: var(--ink-soft); margin: 0; }
.sc-breakdown { border-top: 1px dashed var(--line); border-bottom: 1px dashed var(--line); padding: 16px 0; margin: 16px 0; font-size: 0.8rem; color: var(--ink-soft); }
.sc-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
.sc-total { background: #FAF9F6; border: 1px solid var(--line); padding: 16px; border-radius: 8px; margin-bottom: 24px; }
.sc-total-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.sc-total-row span:first-child { font-size: 0.8rem; font-weight: 600; color: var(--ink); }
.sc-total-row span:last-child { font-family: var(--font-serif); font-size: 1.4rem; font-weight: 600; color: var(--accent); }
.sc-note { font-size: 0.7rem; color: var(--ink-faint); margin: 0; line-height: 1.4; }

.btn-pay { background: var(--accent); color: #fff; width: 100%; padding: 16px; border: none; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: 0.2s; margin-bottom: 16px; }
.btn-pay:hover { opacity: 0.9; }

@media (max-width: 768px) { .checkout-grid { grid-template-columns: 1fr; } .form-group-grid { grid-template-columns: 1fr; } .step-indicator::before { display:none; } .step-indicator { flex-direction: column; align-items: flex-start; gap: 12px; } }
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

<main class="checkout-wrap">
    
    <!-- PROGRESS BAR -->
    <div class="step-indicator">
        <div class="step-item done"><div class="step-circle">✓</div> Langkah 1: Pilih Paket</div>
        <div class="step-item done"><div class="step-circle">✓</div> Langkah 2: Jadwal & Kuota</div>
        <div class="step-item active"><div class="step-circle">3</div> Langkah 3: Data & Pembayaran</div>
        <div class="step-item pending"><div class="step-circle">4</div> Langkah 4: Konfirmasi Tiket</div>
    </div>

    <!-- TIMER ALERT -->
    <div class="timer-banner">
        <div class="timer-text">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            Slot Anda dikunci sementara. Sisa waktu:
            <span class="timer-clock" id="timer-clock"><?= sprintf('%02d:%02d', intdiv($sisa_detik, 60), $sisa_detik % 60); ?></span>
        </div>
        <span class="timer-note">Selesaikan pemesanan dalam 15:00 menit agar slot tidak dirilis kembali ke publik.</span>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div style="background:#FED7D7; border:1px solid #FEB2B2; color:#C53030; padding:12px 20px; border-radius:8px; margin-bottom:24px; font-size:0.9rem;">
            <?= htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <!-- FORM & SUMMARY (Form Action mengarah ke checkout.php) -->
    <form action="checkout.php" method="POST" class="checkout-grid">
        <input type="hidden" name="proses_reservasi" value="1">
        <input type="hidden" name="paket_layanan" value="<?= htmlspecialchars($paket_layanan); ?>">
        <input type="hidden" name="harga_dasar" value="<?= $harga_dasar; ?>">
        <input type="hidden" name="total_harga" id="checkout_total_harga" value="<?= $total_harga; ?>">
        <input type="hidden" name="addons_total" value="<?= $addons_total; ?>">
        <input type="hidden" name="addons_list" value="<?= htmlspecialchars($addons_list); ?>">
        <input type="hidden" name="tanggal_sesi" value="<?= htmlspecialchars($tanggal_sesi); ?>">
        <input type="hidden" name="jam_sesi" value="<?= htmlspecialchars($jam_sesi); ?>">
        <input type="hidden" name="lokasi_studio" value="<?= htmlspecialchars($lokasi_studio); ?>">
        
        <!-- BAGIAN KIRI: FORMULIR -->
        <div class="checkout-form">
            
            <!-- Section 01 -->
            <div class="form-section">
                <h3 class="form-title"><span>01</span> Data Pemesan Utama</h3>
                <div class="form-group-grid">
                    <div class="form-group">
                        <label>Nama Lengkap Pemesan <span>*</span></label>
                        <input type="text" name="nama_pemesan" class="form-control" value="<?= htmlspecialchars($user_data['nama'] ?? $nama_user); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Pasangan / Anggota yang Difoto</label>
                        <input type="text" name="nama_pasangan" class="form-control" placeholder="Contoh: Nabila Larasati / Keluarga">
                    </div>
                </div>
                <div class="form-group-grid">
                    <div class="form-group">
                        <label>Email Pengiriman Tiket & File <span>*</span></label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nomor WhatsApp Aktif <span>*</span></label>
                        <input type="tel" name="whatsapp" class="form-control" value="<?= htmlspecialchars($user_data['whatsapp'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <!-- Section 02 -->
            <div class="form-section">
                <h3 class="form-title"><span>02</span> Preferensi Konsep & Catatan Khusus</h3>
                <div class="form-group">
                    <label>Mood Board / Gaya Foto Pilihan</label>
                    <select class="form-control" name="moodboard">
                        <option>Warm Minimalist (Cahaya hangat, earth tones)</option>
                        <option>Cinematic Dark (Dramatis, elegan)</option>
                        <option>Bright & Clean (Terang, bersih, Korea style)</option>
                    </select>
                </div>
                <div class="form-group" style="margin-top:16px;">
                    <label>Catatan Khusus untuk Tim Fotografer</label>
                    <textarea name="catatan" class="form-control" rows="3" placeholder="Contoh: Ingin fokus banyak candid portrait dan detail wardrobe..."></textarea>
                </div>
            </div>

            <!-- Section 03 -->
            <div class="form-section" id="section-skema">
                <h3 class="form-title"><span>03</span> Skema Pembayaran</h3>
                <p class="skema-muted-note" id="skema-muted-note" hidden>Tidak berlaku untuk Bayar di Tempat. Seluruh pembayaran dilakukan di kasir studio saat sesi foto.</p>
                <label class="radio-box" id="box-skema-dp" style="border-color: #D9822B; background: #FFFBF7;">
                    <input type="radio" name="skema" value="dp" checked onchange="toggleSkema('dp')">
                    <div class="radio-content">
                        <div class="radio-head">
                            <h4 class="radio-title">Uang Muka (DP 30%) - Pengunci Slot</h4>
                            <p class="radio-price">Rp <?= number_format($dp_nominal, 0, ',', '.'); ?></p>
                        </div>
                        <p class="radio-desc">Kunci kuota sekarang dan amankan jadwal Anda. Sisa pelunasan (Rp <?= number_format($sisa_nominal, 0, ',', '.'); ?>) dibayar langsung di kasir studio saat hari sesi foto.</p>
                        <span class="radio-tag">Paling Populer</span>
                    </div>
                </label>
                <label class="radio-box" id="box-skema-lunas">
                    <input type="radio" name="skema" value="lunas" onchange="toggleSkema('lunas')">
                    <div class="radio-content">
                        <div class="radio-head">
                            <h4 class="radio-title">Bayar Lunas Langsung <span style="color:var(--green); font-size:0.75rem;">Free Gift!</span></h4>
                            <p class="radio-price">Rp <?= number_format($total_harga, 0, ',', '.'); ?></p>
                        </div>
                        <p class="radio-desc">Tanpa pusing pelunasan di muka. Dapatkan benefit eksklusif gratis 1 wooden frame cetak 20x30 cm.</p>
                    </div>
                </label>
            </div>

            <!-- Section 04 -->
            <div class="form-section">
                <h3 class="form-title"><span>04</span> Metode Pembayaran</h3>
                <label class="radio-box" id="box-metode-qris" style="border-color: #D9822B; background: #FFFBF7;">
                    <input type="radio" name="metode" value="qris" checked onchange="toggleMetode()">
                    <div class="radio-content">
                        <div class="radio-head">
                            <h4 class="radio-title">QRIS (Semua E-Wallet & Mobile Banking)</h4>
                            <span class="radio-tag" style="background:var(--bg-alt); color:var(--ink);">QRIS Instan</span>
                        </div>
                        <p class="radio-desc">GoPay, OVO, Dana, ShopeePay, BCA Mobile, Livin', dll.</p>
                    </div>
                </label>
                <label class="radio-box" id="box-metode-tempat">
                    <input type="radio" name="metode" value="tempat" onchange="toggleMetode()">
                    <div class="radio-content">
                        <div class="radio-head">
                            <h4 class="radio-title">Bayar di Tempat (Kasir Studio)</h4>
                            <span class="radio-tag" style="background:var(--bg-alt); color:var(--ink);">Tunai / Kartu</span>
                        </div>
                        <p class="radio-desc">Tanpa pembayaran online sekarang. Seluruh total (Rp <?= number_format($total_harga, 0, ',', '.'); ?>) dibayar di kasir studio saat sesi foto via tunai, QRIS, atau kartu debit/kredit.</p>
                    </div>
                </label>
            </div>

        </div>

        <!-- BAGIAN KANAN: SUMMARY -->
        <div class="checkout-summary">
            <div class="summary-card">
                <p class="sc-eyebrow">RINCIAN RESERVASI</p>
                <h3 class="sc-title">Rangkuman Booking</h3>
                
                <div class="sc-item">
                    <img src="<?= htmlspecialchars($paket_foto); ?>" alt="<?= htmlspecialchars($nama_paket_dec); ?>" onerror="this.onerror=null;this.src='jpg/studio.jpg'" style="width:48px; height:48px; border-radius:8px; object-fit:cover;">
                    <div>
                        <h5><?= htmlspecialchars($nama_paket_dec); ?></h5>
                        <p><?= $paket_durasi !== '' ? htmlspecialchars($paket_durasi) : 'Durasi Sesi 2 Jam Studio'; ?></p>
                    </div>
                </div>

                <div class="sc-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <div>
                        <h5>Tanggal Pelaksanaan</h5>
                        <p><?= date('d F Y', strtotime($tanggal_sesi)); ?></p>
                    </div>
                </div>
                <div class="sc-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <div>
                        <h5>Waktu Sesi Foto</h5>
                        <p><?= htmlspecialchars($jam_sesi); ?></p>
                    </div>
                </div>
                <div class="sc-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    <div>
                        <h5>Lokasi Studio</h5>
                        <p><?= htmlspecialchars(html_entity_decode($lokasi_studio, ENT_QUOTES, 'UTF-8')); ?></p>
                    </div>
                </div>

                <div class="sc-breakdown">
                    <div class="sc-row">
                        <span>Biaya Dasar Paket</span>
                        <span>Rp <?= number_format($harga_dasar, 0, ',', '.'); ?></span>
                    </div>
                    <?php if ($addons_total > 0 && !empty($addons_list)): ?>
                    <div class="sc-row" style="color:var(--accent); font-weight:600; margin-top:8px;">
                        <span>+ Add-on Layanan:<br><small style="font-weight:400; color:var(--ink-soft);"><?= htmlspecialchars($addons_list); ?></small></span>
                        <span>Rp <?= number_format($addons_total, 0, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="sc-total">
                    <div class="sc-row" style="margin-bottom:12px; font-weight:600; color:var(--ink);">
                        <span>Total Nilai Sesi:</span>
                        <span>Rp <?= number_format($total_harga, 0, ',', '.'); ?></span>
                    </div>
                    <div class="sc-total-row">
                        <span>TOTAL DIBAYAR SEKARANG<br><span id="sc-skema-label" style="font-size:0.7rem; font-weight:400; color:var(--ink-soft);">Uang Muka 30% Kunci Kuota</span></span>
                        <span id="sc-dibayar-val">Rp <?= number_format($dp_nominal, 0, ',', '.'); ?></span>
                    </div>
                    <p class="sc-note" id="sc-sisa-note">✔ Sisa Rp <?= number_format($sisa_nominal, 0, ',', '.'); ?> dibayar saat tiba di studio.</p>
                </div>

                <label style="display:flex; gap:12px; align-items:flex-start; font-size:0.75rem; color:var(--ink-soft); margin-bottom:24px; cursor:pointer;">
                    <input type="checkbox" required style="margin-top:2px; accent-color:var(--dark);">
                    <span>Saya menyetujui syarat & ketentuan Creative Pixel (bebas reschedule H-3 dan kuota dikunci secara resmi).</span>
                </label>

                <button type="submit" class="btn-pay" id="btn-submit">🔒 Konfirmasi & Lanjut Bayar</button>
                
                <div style="display:flex; justify-content:center; gap:16px; font-size:0.7rem; color:var(--ink-faint); font-weight:600;">
                    <span style="display:flex; align-items:center; gap:4px;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg> Enkripsi SSL</span>
                    <span style="display:flex; align-items:center; gap:4px;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> Kuota Terkunci</span>
                </div>
            </div>
        </div>

    </form>
</main>

<!-- MODAL WAKTU HABIS -->
<div class="lock-overlay" id="lock-overlay" role="alertdialog" aria-modal="true" aria-labelledby="lock-title">
    <div class="lock-modal">
        <h3 id="lock-title">Waktu Penguncian Habis</h3>
        <p>Slot Anda sudah dirilis kembali karena melewati batas 15 menit. Silakan pilih jadwal kembali untuk melanjutkan.</p>
        <a href="index.php">Pilih Jadwal Ulang</a>
    </div>
</div>

<script>
const totalHarga = <?= $total_harga; ?>;
const dpNominal = <?= $dp_nominal; ?>;
const sisaNominal = <?= $sisa_nominal; ?>;

function formatRupiah(n) {
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function highlightBox(id, aktif) {
    const el = document.getElementById(id);
    el.style.borderColor = aktif ? '#D9822B' : 'var(--line)';
    el.style.background = aktif ? '#FFFBF7' : '#fff';
}

// Satu fungsi untuk menyinkronkan kartu pilihan, section skema, ringkasan, dan tombol
function renderPembayaran() {
    const metode = document.querySelector('input[name="metode"]:checked').value;
    const skema = document.querySelector('input[name="skema"]:checked').value;
    const tempat = (metode === 'tempat');

    highlightBox('box-metode-qris', !tempat);
    highlightBox('box-metode-tempat', tempat);
    highlightBox('box-skema-dp', skema === 'dp');
    highlightBox('box-skema-lunas', skema === 'lunas');

    // Skema DP/Lunas tidak relevan untuk bayar di tempat
    const secSkema = document.getElementById('section-skema');
    secSkema.classList.toggle('is-muted', tempat);
    secSkema.inert = tempat;
    document.getElementById('skema-muted-note').hidden = !tempat;

    const labelEl = document.getElementById('sc-skema-label');
    const valEl = document.getElementById('sc-dibayar-val');
    const noteEl = document.getElementById('sc-sisa-note');
    
    if (tempat) {
        labelEl.textContent = 'Belum ada pembayaran online';
        valEl.textContent = 'Rp 0';
        noteEl.textContent = '✔ Total Rp ' + formatRupiah(totalHarga) + ' dibayar di kasir studio saat sesi foto.';
        document.getElementById('btn-submit').textContent = '🔒 Konfirmasi Reservasi';
    } else if (skema === 'lunas') {
        labelEl.textContent = 'Pelunasan Penuh (100%)';
        valEl.textContent = 'Rp ' + formatRupiah(totalHarga);
        noteEl.textContent = '✔ Lunas! Bebas biaya tambahan saat tiba di studio.';
        document.getElementById('btn-submit').textContent = '🔒 Konfirmasi & Lanjut Bayar';
    } else {
        labelEl.textContent = 'Uang Muka 30% Kunci Kuota';
        valEl.textContent = 'Rp ' + formatRupiah(dpNominal);
        noteEl.textContent = '✔ Sisa Rp ' + formatRupiah(sisaNominal) + ' dibayar saat tiba di studio.';
        document.getElementById('btn-submit').textContent = '🔒 Konfirmasi & Lanjut Bayar';
    }
}

// Dipanggil dari onchange pada radio skema & metode
function toggleSkema() { renderPembayaran(); }
function toggleMetode() { renderPembayaran(); }
renderPembayaran();

// Hitung mundur kunci slot (sisa waktu dihitung server, jadi aman saat di-refresh)
const deadline = Date.now() + <?= (int)$sisa_detik; ?> * 1000;
const clockEl = document.getElementById('timer-clock');
const bannerEl = document.querySelector('.timer-banner');
const btnEl = document.getElementById('btn-submit');
const overlayEl = document.getElementById('lock-overlay');

function pad(n) { return String(n).padStart(2, '0'); }

function tick() {
    const sisa = Math.max(0, Math.round((deadline - Date.now()) / 1000));
    clockEl.textContent = pad(Math.floor(sisa / 60)) + ':' + pad(sisa % 60);
    bannerEl.classList.toggle('is-urgent', sisa <= 120);

    if (sisa <= 0) {
        clearInterval(timerId);
        btnEl.disabled = true;
        overlayEl.classList.add('show');
    }
}
const timerId = setInterval(tick, 1000);
tick();
</script>
</body>
</html>