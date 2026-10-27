-- =======================================================
-- Creative Pixel Studio - Full Database Script
-- Database: creative_pixel
-- Tinggal di-import langsung di phpMyAdmin (tanpa perlu buat database manual)
-- =======================================================

CREATE DATABASE IF NOT EXISTS `creative_pixel` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `creative_pixel`;

-- -------------------------------------------------------
-- 1. Struktur Tabel `users`
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `whatsapp` VARCHAR(20) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'klien') NOT NULL DEFAULT 'klien',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 2. Struktur Tabel `paket`
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `paket` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `kategori` VARCHAR(50) NOT NULL DEFAULT 'portrait',
    `kategori_label` VARCHAR(100) NOT NULL DEFAULT 'EDITORIAL PORTRAIT',
    `nama_paket` VARCHAR(120) NOT NULL,
    `sub_tag` VARCHAR(60) DEFAULT NULL,
    `deskripsi` TEXT NOT NULL,
    `fitur` TEXT NOT NULL,
    `harga` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `dp_minimal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `durasi` VARCHAR(50) DEFAULT '2 Jam Sesi Foto',
    `lokasi_studio` VARCHAR(100) DEFAULT 'Studio A & Garden Lumina Senopati',
    `gambar` VARCHAR(255) DEFAULT 'jpg/studio.jpg',
    `kuota_total` INT NOT NULL DEFAULT 10,
    `kuota_terpakai` INT NOT NULL DEFAULT 0,
    `status` ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 3. Struktur Tabel `pesanan`
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pesanan` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `kode_reservasi` VARCHAR(50) NOT NULL UNIQUE,
    `paket_layanan` VARCHAR(100) NOT NULL,
    `tanggal_sesi` DATE NOT NULL,
    `jam_sesi` VARCHAR(50) NOT NULL,
    `lokasi_studio` VARCHAR(100) DEFAULT 'Studio A & Garden Lumina',
    `nama_pasangan` VARCHAR(100) NULL,
    `skema_pembayaran` VARCHAR(20) DEFAULT 'dp',
    `metode_pembayaran` VARCHAR(50) DEFAULT 'qris',
    `total_harga` DECIMAL(12,2) DEFAULT 0.00,
    `jumlah_dibayar` DECIMAL(12,2) DEFAULT 0.00,
    `sisa_tagihan` DECIMAL(12,2) DEFAULT 0.00,
    `catatan` TEXT NULL,
    `status` VARCHAR(50) DEFAULT 'Menunggu Pembayaran',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pesanan_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 4. Data Awal Pengguna (Users Seed)
-- -------------------------------------------------------
-- Akun Admin:
-- Email: admin@creativepixel.com | Password: admin123
INSERT INTO `users` (`id`, `nama`, `email`, `whatsapp`, `password`, `role`)
VALUES 
(1, 'Admin Operasional', 'admin@creativepixel.com', '081200000001', '$2y$12$QY/xgESlhlueDAzNEu9YCOdRtQ4HKAJzHAgqYMwnLOZOJqAOAUwc6', 'admin')
ON DUPLICATE KEY UPDATE `role`='admin';

-- Akun Demo Klien:
-- Email: klien@creativepixel.com | Password: klien123
INSERT INTO `users` (`id`, `nama`, `email`, `whatsapp`, `password`, `role`)
VALUES 
(2, 'Dimas Anggara', 'klien@creativepixel.com', '081234567890', '$2y$12$UtBkTqO7VcRRN4X1otfQ0O8OsecWFsSRrzi9PPUsnwWWXusJKZHJq', 'klien')
ON DUPLICATE KEY UPDATE `nama`=VALUES(`nama`);

-- -------------------------------------------------------
-- 5. Data Awal Katalog Paket Fotografi (Paket Seed)
-- -------------------------------------------------------