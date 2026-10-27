-- =============================================================
-- SEED DATA PAKET FOTOGRAFI - Creative Pixel Studio
-- Jalankan di phpMyAdmin (database: creative_pixel)
-- 4 Kategori: wisuda | portrait | prewedding | family
-- =============================================================

USE `creative_pixel`;

-- Hapus data lama jika ada (opsional, uncomment jika ingin reset)
-- TRUNCATE TABLE `paket`;

INSERT INTO `paket`
    (`kategori`, `kategori_label`, `nama_paket`, `sub_tag`, `deskripsi`, `fitur`, `harga`, `dp_minimal`, `durasi`, `lokasi_studio`, `gambar`, `kuota_total`, `kuota_terpakai`, `status`)
VALUES

-- -------------------------------------------------------
-- WISUDA & AKADEMIK
-- -------------------------------------------------------
('wisuda', 'WISUDA & AKADEMIK',
 'Paket Wisuda Esensial', 'SOLO SESSION',
 'Sesi foto wisuda solo klasik dengan latar toga, tampil elegan dan berkesan sepanjang masa.',
 '30 foto seleksi edit|1 set cetak 4R (10 lembar)|1 digital frame eksklusif|Durasi 90 menit',
 1200000, 360000, '1.5 Jam Sesi Foto',
 'Studio A & Garden Lumina Senopati', 'jpg/studio.jpg',
 15, 4, 'aktif'),

('wisuda', 'WISUDA & AKADEMIK',
 'Paket Wisuda Premium', 'SOLO + KELUARGA',
 'Paket lengkap wisuda mencakup sesi solo dan sesi keluarga dengan fotografer senior berpengalaman.',
 '60 foto seleksi edit|Cetak 8R 5 lembar + buku tamu|Digital album online|Durasi 2 jam|MUA opsional tersedia',
 2500000, 750000, '2 Jam Sesi Foto',
 'Studio A & Garden Lumina Senopati', 'jpg/studio.jpg',
 10, 7, 'aktif'),

('wisuda', 'WISUDA & AKADEMIK',
 'Paket Wisuda Grand Lumina', 'FULL EKSKLUSIF',
 'Pengalaman wisuda paling prestisius dengan senior fotografer, MUA profesional, dan hasil liputan sinematik.',
 '120 foto edit full|Cetak kanvas 30x40 + album premium|Drone aerial garden|Live galeri online H+1|Durasi 3 jam',
 4500000, 1350000, '3 Jam Sesi Foto',
 'Studio A & Garden Lumina Senopati', 'jpg/studio.jpg',
 6, 6, 'aktif'),

-- -------------------------------------------------------
-- EDITORIAL PORTRAIT
('portrait', 'EDITORIAL PORTRAIT',
 'Essential Persona (Headshot & Profile)', 'Headshot',
 'Portret profesional tegas untuk profil eksekutif, profil LinkedIn, atau pengenalan persona personal.',
 '8 File Master Retouch|1 Look Wardrobe|Cocok LinkedIn / Eksekutif',
 850000, 250000, '45 Menit',
 'Studio A Daylight', 'jpg/studio.jpg',
 10, 0, 'aktif'),

('portrait', 'EDITORIAL PORTRAIT',
 'The Signature Editorial (Personal Branding)', '★ Best Seller',
 'Eksplorasi persona visual kontemporer berkarakter kuat dengan moodboard yang terkurasi.',
 '15 File Edit Warna Hi-Res|3 Fine Art Retouch & 2x Kostum|Custom Moodboard & Directing',
 1250000, 350000, '60 Menit',
 'Studio A Minimalist', 'jpg/studio2.jpg',
 10, 0, 'aktif'),

('portrait', 'EDITORIAL PORTRAIT',
 'Cinematic Creative Vision (Artistic Tungsten)', 'Cinematic Dark',
 'Eksplorasi tata cahaya hangat remang nan dramatis gaya film bioskop dengan tekstur visual sinematik.',
 '20 File Hi-Res Graded|Tata Cahaya Tungsten Gelap|1 Cetak A3 Museum Paper',
 1750000, 500000, '75 Menit',
 'Studio Dark / Ambient', 'jpg/studio.jpg',
 10, 0, 'aktif'),

('portrait', 'EDITORIAL PORTRAIT',
 'Director\'s Haute Couture & Fashion Editorial', 'Haute Couture',
 'Sesi fashion editorial kaliber majalah mode internasional dengan pendampingan creative director.',
 '30 File Hi-Res Retouch Majalah|Live Tethering 4K Monitor|Private Dressing Suite & CD Directing',
 2600000, 800000, '120 Menit',
 'Cyclorama Full Set', 'jpg/studio2.jpg',
 10, 0, 'aktif'),

-- -------------------------------------------------------
-- PREWEDDING & PASANGAN
-- -------------------------------------------------------
('prewedding', 'PREWEDDING & PASANGAN',
 'Paket Couple Daydream', 'OUTDOOR GARDEN',
 'Sesi foto pasangan romantis di outdoor garden dengan cahaya alami golden hour.',
 '40 foto edit|Outdoor garden + in-studio|Soft copy cloud|Durasi 2 jam',
 2200000, 660000, '2 Jam Sesi Foto',
 'Garden Lumina Senopati', 'jpg/studio2.jpg',
 10, 2, 'aktif'),

('prewedding', 'PREWEDDING & PASANGAN',
 'Paket Prewedding Cinematic', 'FINE ART WEDDING',
 'Prewedding bergaya sinematik dengan konsep editorial dan natural lighting terbaik. Cerita cinta Anda dalam bingkai seni.',
 '80 foto edit cinematic|2 lokasi (garden + studio)|Konsultasi moodboard|Soft copy HD|Drone aerial|Durasi 3 jam',
 4800000, 1440000, '3 Jam Sesi Foto',
 'Studio A & Garden Lumina Senopati', 'jpg/studio.jpg',
 8, 5, 'aktif'),

('prewedding', 'PREWEDDING & PASANGAN',
 'Paket Wedding Anniversary', 'MILESTONE MOMENT',
 'Abadikan momen anniversary pernikahan Anda dengan gaya editorial romantis bersama pasangan.',
 '50 foto edit premium|Lokasi studio + outdoor|Album fisik eksklusif|Durasi 2 jam',
 3200000, 960000, '2 Jam Sesi Foto',
 'Studio A & Garden Lumina Senopati', 'jpg/studio2.jpg',
 10, 3, 'aktif'),

-- -------------------------------------------------------
-- FAMILY & GROUP
-- -------------------------------------------------------
('family', 'FAMILY & GROUP',
 'Paket Family Mini Session', 'MAX 4 ORANG',
 'Sesi foto keluarga kecil yang hangat dan natural dengan suasana studio modern.',
 '30 foto edit|Studio backdrop pilihan|Soft copy|Durasi 1 jam',
 1500000, 450000, '1 Jam Sesi Foto',
 'Studio A Senopati', 'jpg/studio.jpg',
 15, 5, 'aktif'),

('family', 'FAMILY & GROUP',
 'Paket Family Premium', 'MAX 8 ORANG',
 'Foto keluarga besar dengan ruang studio luas, props lengkap, dan hasil premium untuk momen silaturahmi tak terlupakan.',
 '60 foto edit|Props & set styling|Cetak 8R 5 lembar|Album digital|Durasi 2 jam',
 2800000, 840000, '2 Jam Sesi Foto',
 'Studio A & Garden Lumina Senopati', 'jpg/studio.jpg',
 10, 1, 'aktif'),

('family', 'FAMILY & GROUP',
 'Paket Group Komunitas', 'MAX 20 ORANG',
 'Sesi foto untuk komunitas, angkatan, atau kelompok besar dengan setup profesional. Tersedia layout grid & banner digital.',
 '100 foto edit|Foto kelompok + foto per baris|Soft copy beresolusi tinggi|Durasi 3 jam',
 5000000, 1500000, '3 Jam Sesi Foto',
 'Studio A & Garden Lumina Senopati', 'jpg/studio2.jpg',
 5, 2, 'aktif');

-- -------------------------------------------------------
-- Contoh data pesanan demo
-- -------------------------------------------------------
INSERT IGNORE INTO `pesanan`
    (`user_id`, `kode_reservasi`, `paket_layanan`, `tanggal_sesi`, `jam_sesi`,
     `lokasi_studio`, `skema_pembayaran`, `total_harga`, `jumlah_dibayar`, `sisa_tagihan`, `status`)
VALUES
(2, 'CP-OCT-0001', 'Paket Wisuda Premium', '2026-10-16', '09.00 - 11.00 WIB',
 'Studio A & Garden Lumina Senopati', 'dp', 2500000, 750000, 1750000, 'DP Masuk – Menunggu Konfirmasi'),
(2, 'CP-OCT-0002', 'Paket Prewedding Cinematic', '2026-10-17', '15.30 - 18.30 WIB',
 'Studio A & Garden Lumina Senopati', 'dp', 4800000, 1440000, 3360000, 'Terkonfirmasi – Lunas DP'),
(2, 'CP-OCT-0003', 'Paket Portrait Editoria Signature', '2026-10-18', '13.00 - 15.00 WIB',
 'Studio A Senopati', 'lunas', 2800000, 2800000, 0, 'Lunas – Sesi Terjadwal'),
(2, 'CP-OCT-0004', 'Paket Family Premium', '2026-10-20', '10.00 - 12.00 WIB',
 'Studio A & Garden Lumina Senopati', 'dp', 2800000, 840000, 1960000, 'DP Masuk – Menunggu Konfirmasi');

