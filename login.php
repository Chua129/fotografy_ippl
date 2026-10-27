<?php
session_start();
require 'koneksi.php';

// Jika sudah login, arahkan ke dashboard sesuai role
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role']; // 'klien' atau 'admin'

    // Cek user di database
    $query = "SELECT id, nama, password, role FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verifikasi password
        if (password_verify($password, $user['password'])) {
            if ($user['role'] !== $role) {
                $error = "Akun ini terdaftar sebagai " . ucfirst($user['role']) . ". Silakan pilih peran yang sesuai.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit;
            }
        } else {
            $error = "Kata sandi yang Anda masukkan salah.";
        }
    } else {
        $error = "Email tidak ditemukan dalam sistem.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk Portal — Creative Pixel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<!-- Memanggil CSS Utama -->
<link rel="stylesheet" href="style.css">
<style>
/* Tambahan styling untuk pilihan role di form login */
.role-select-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
.role-option { border: 1px solid var(--line); border-radius: 8px; padding: 12px; text-align: center; cursor: pointer; font-size: 0.85rem; font-weight: 600; display: block; background: #fff; transition: 0.2s; }
.role-option input { margin-right: 6px; accent-color: var(--dark); }
.role-option:has(input:checked) { border-color: var(--dark); background: var(--bg-alt); }
.error-alert { background: #FDE8E8; border: 1px solid #F8B4B4; color: #9B1C1C; padding: 12px 16px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 20px; }
</style>
</head>
<body>

<main class="register-wrap">
  <div class="register-card">

    <!-- ============== LEFT: STUDIO IMAGE ============== -->
    <div class="register-media">
      <img src="jpg/login.jpg" alt="Interior studio Creative Pixel">

      <div class="register-media-top">
        <span class="badge-exclusive"><i class="dot"></i>Portal Akses Resmi</span>
        <div class="register-watermark">
          <p>Masuk Portal Klien &amp; Admin</p>
          <p>EST. 2026</p>
        </div>
      </div>

      <div class="register-media-bottom">
        <p class="eyebrow">Creative Pixel • Pekanbaru</p>
        <h2>Kelola Sesi Fotografi &amp; Jadwal</h2>
        <p>Masuk ke akun Anda untuk memantau status pemesanan, melihat tiket reservasi, atau mengelola operasional studio secara real-time.</p>
      </div>
    </div>

    <!-- ============== RIGHT: LOGIN FORM ============== -->
    <div class="register-form">
      <p class="eyebrow">Autentikasi Akun</p>
      <h1 class="section-title">Masuk ke Portal</h1>
      <p>Silakan masukkan kredensial akun Anda untuk melanjutkan.</p>

      <?php if (!empty($error)): ?>
        <div class="error-alert"><?= $error; ?></div>
      <?php endif; ?>

      <form action="" method="POST">
        
        <!-- PILIHAN PERAN (ROLE) -->
        <div class="field-group">
          <label class="field-label">Masuk Sebagai:</label>
          <div class="role-select-grid">
            <label class="role-option">
              <input type="radio" name="role" value="klien" checked> Klien Studio
            </label>
            <label class="role-option">
              <input type="radio" name="role" value="admin"> Admin Operasional
            </label>
          </div>
        </div>

        <div class="field-group">
          <label class="field-label" for="login-email">Email Terdaftar</label>
          <div class="input-with-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="3"></rect><path d="M2 7l10 6 10-6"></path></svg>
            <input type="email" id="login-email" name="email" placeholder="nama@email.com" autocomplete="email" required>
          </div>
        </div>

        <div class="field-group">
          <div class="field-label-row">
            <label class="field-label" for="login-password">Kata Sandi</label>
          </div>
          <div class="input-with-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            <input type="password" id="login-password" name="password" placeholder="••••••••" autocomplete="current-password" required>
          </div>
        </div>

        <button type="submit" class="btn-register" style="margin-top: 12px;">
          Masuk Portal Sekarang
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
        </button>
      </form>

      <p class="existing-account-box">Belum memiliki akun? <a href="registrasi.php">Daftar di sini</a></p>

      <p class="security-note">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V6z"></path></svg>
        Sistem keamanan terverifikasi dengan enkripsi data sesi aktif.
      </p>
    </div>

  </div>
</main>

<!-- ================= FOOTER ================= -->
<footer class="site-footer" style="background:transparent;color:var(--ink-soft);border-top:1px solid var(--line);margin-top:40px;">
  <div class="container footer-grid" style="border-bottom:1px solid var(--line);">
    <div class="footer-brand">
      <p class="logo" style="color:var(--ink);">CREATIVE<span>PIXEL</span></p>
      <p>Jl. Marpoyan No. 42, Bukit Raya,<br>Pekanbaru, Riau</p>
      <p style="text-transform:uppercase;font-size:0.72rem;letter-spacing:0.04em;color:var(--ink-faint);margin-bottom:6px;">Jam Operasional:</p>
      <p style="color:var(--ink);font-weight:600;">Setiap Hari: 09.00 – 21.00 WIB</p>
    </div>

    <div class="footer-col">
      <h5 style="color:var(--ink);">Kategori Layanan</h5>
      <ul>
        <li>Studio Portrait Editorial</li>
        <li>Prewedding &amp; Fine Art Wedding</li>
        <li>Wisuda &amp; Graduation Commemorative</li>
        <li>Komersial &amp; Katalog Produk</li>
        <li>Family &amp; Maternity Sessions</li>
      </ul>
    </div>

    <div class="footer-col">
      <h5 style="color:var(--ink);">Kebijakan &amp; Akses Klien</h5>
      <ul>
        <li>Masuk Portal Klien</li>
        <li>Cek Status Reservasi</li>
        <li><strong style="color:var(--ink);">Reschedule Gratis</strong><br>Maksimal H-3 sebelum sesi</li>
        <li><strong style="color:var(--ink);">DP 30% Pengunci Slot</strong><br>Langsung mengamankan jadwal kalender</li>
      </ul>
    </div>

    <div class="footer-col">
      <h5 style="color:var(--ink);">Bantuan &amp; WhatsApp Concierge</h5>
      <p>Perlu konsultasi moodboard, jadwal darurat, atau wardrobe catalog?</p>
      <p class="online-status"><i class="dot dot-green"></i>Online • Respons Instan</p>
      <p class="phone" style="color:var(--ink);">+62 821-3361-4138</p>
    </div>
  </div>

  <div class="container footer-bottom">
    <p>© 2026 Creative Pixel (Creative Pixel Frame Studio). Hak Cipta Dilindungi.</p>
    <p>Portal Masuk Klien &nbsp;·&nbsp; Contemporary Editorial Photography</p>
  </div>
</footer>

</body>
</html>