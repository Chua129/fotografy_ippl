<?php
session_start();
require 'koneksi.php';

// Jika sudah login, lempar ke dashboard
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama = $_POST['nama'];
    $email = $_POST['email'];
    $whatsapp = $_POST['whatsapp'];
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirmation'];
    $role = 'klien'; // Otomatis diset sebagai klien

    if ($password !== $password_confirm) {
        echo "<script>alert('Password dan Konfirmasi Password tidak cocok. Silakan coba lagi.');</script>";
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        try {
            // Cek email/WA
            $cek_query = "SELECT email, whatsapp FROM users WHERE email = ? OR whatsapp = ?";
            $stmt = $conn->prepare($cek_query);
            $stmt->bind_param("ss", $email, $whatsapp);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $ada_email = false;
                $ada_wa = false;
                while ($row = $result->fetch_assoc()) {
                    if (strcasecmp($row['email'], $email) === 0) {
                        $ada_email = true;
                    }
                    if ($row['whatsapp'] === $whatsapp) {
                        $ada_wa = true;
                    }
                }
                if ($ada_email && $ada_wa) {
                    echo "<script>alert('Email dan Nomor WhatsApp tersebut sudah terdaftar. Silakan gunakan data lain atau masuk ke akun Anda.');</script>";
                } elseif ($ada_email) {
                    echo "<script>alert('Email tersebut sudah terdaftar. Silakan gunakan email lain atau masuk ke akun Anda.');</script>";
                } else {
                    echo "<script>alert('Nomor WhatsApp tersebut sudah terdaftar. Silakan gunakan nomor WhatsApp lain.');</script>";
                }
            } else {
                // Insert data klien
                $insert_query = "INSERT INTO users (nama, email, whatsapp, password, role) VALUES (?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($insert_query);
                $stmt_insert->bind_param("sssss", $nama, $email, $whatsapp, $hashed_password, $role);

                if ($stmt_insert->execute()) {
                    echo "<script>
                            alert('Registrasi berhasil! Silakan masuk ke portal.');
                            window.location.href = 'login.php';
                          </script>";
                } else {
                    echo "<script>alert('Error: Data gagal disimpan.');</script>";
                }
                $stmt_insert->close();
            }
            $stmt->close();
        } catch (Exception $e) {
            echo "<script>alert('Terjadi kesalahan saat memproses data: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Akun Klien — Creative Pixel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>

<main class="register-wrap">
  <div class="register-card">

    <!-- ============== LEFT: STUDIO IMAGE ============== -->
    <div class="register-media">
      <img src="jpg/regis.jpg" alt="Interior studio Creative Pixel">

      <div class="register-media-top">
        <span class="badge-exclusive"><i class="dot"></i>Akun Klien Eksklusif</span>
        <div class="register-watermark">
          <p>Daftar Akun Klien Baru</p>
          <p>EST. 2026</p>
        </div>
      </div>

      <div class="register-media-bottom">
        <p class="eyebrow">Creative Pixel • Pekanbaru</p>
        <h2>Mulai Ruang Cerita Visual Anda</h2>
        <p>Simpan arsip cetak, pantau kuota pemotretan secara real-time, dan diskusikan moodboard kurasi bersama tim fotografer secara terpusat.</p>

        <div class="benefits-box">
          <div class="benefit-item">
            <span class="benefit-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="M21 15l-5-5L5 21"></path></svg>
            </span>
            Akses arsip master resolusi penuh &amp; lembar kontak digital
          </div>
          <div class="benefit-item">
            <span class="benefit-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </span>
            Prioritas lock tanggal &amp; jatah reschedule fleksibel H-3
          </div>
          <div class="benefit-item">
            <span class="benefit-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </span>
            Konsultasi wardrobe &amp; lighting guide langsung dari Lab
          </div>
        </div>
      </div>
    </div>

    <!-- ============== RIGHT: REGISTRATION FORM ============== -->
    <div class="register-form">
      <p class="eyebrow">Registrasi Klien</p>
      <h1 class="section-title">Buat Akun Klien Baru</h1>
      <p>Daftar untuk mengelola reservasi kuota slot, akses galeri foto resolusi penuh, dan status jadwal pemotretan Anda.</p>

      <form action="" method="POST">
        <div class="field-group">
          <label class="field-label" for="reg-nama">Nama Lengkap</label>
          <div class="input-with-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"></circle><path d="M4 21v-1a8 8 0 0 1 16 0v1"></path></svg>
            <input type="text" id="reg-nama" name="nama" placeholder="Nama sesuai identitas / KTP" required>
          </div>
        </div>

        <div class="field-row-2">
          <div class="field-group">
            <label class="field-label" for="reg-email">Email Aktif</label>
            <div class="input-with-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="20" height="16" rx="3"></rect><path d="M2 7l10 6 10-6"></path></svg>
              <input type="email" id="reg-email" name="email" placeholder="nama@email.com" autocomplete="email" required>
            </div>
          </div>
          <div class="field-group">
            <label class="field-label" for="reg-wa">Nomor WhatsApp</label>
            <div class="input-with-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
              <input type="tel" id="reg-wa" name="whatsapp" placeholder="0812xxxxxxxx" autocomplete="tel" required>
            </div>
          </div>
        </div>

        <div class="field-row-2">
          <div class="field-group">
            <div class="field-label-row">
              <label class="field-label" for="reg-password">Kata Sandi</label>
              <span class="field-hint">Min. 8 karakter</span>
            </div>
            <div class="input-with-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="10" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
              <input type="password" id="reg-password" name="password" placeholder="••••••••" autocomplete="new-password" required>
            </div>
          </div>
          <div class="field-group">
            <label class="field-label" for="reg-password-confirm">Konfirmasi Sandi</label>
            <div class="input-with-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
              <input type="password" id="reg-password-confirm" name="password_confirmation" placeholder="••••••••" autocomplete="new-password" required>
            </div>
          </div>
        </div>

        <label class="consent-row">
          <input type="checkbox" name="consent" required>
          <span>Saya menyetujui <a href="#">Ketentuan Layanan</a> &amp; <a href="#">Kebijakan Privasi</a> Studio.</span>
        </label>

        <button type="submit" class="btn-register">
          Buat Akun Klien
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
        </button>
      </form>

      <p class="existing-account-box">Sudah memiliki akun klien? <a href="login.php">Masuk di sini</a></p>
    </div>

  </div>
</main>
</body>
</html>