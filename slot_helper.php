<?php
/**
 * slot_helper.php
 * Logika slot sesi per hari yang dipakai bersama oleh:
 *   - admin_jadwal.php  (admin mengatur kuota / menutup tanggal)
 *   - jadwal.php        (klien memilih tanggal & jam)
 *   - checkout.php      (validasi terakhir sebelum pesanan disimpan)
 *
 * Aturan hitung (sama untuk admin dan klien):
 *   - Slot terisi   = pesanan di tanggal itu yang tidak dibatalkan dan bukan "Menunggu Pembayaran"
 *   - Pending       = pesanan "Menunggu Pembayaran" (belum mengurangi slot)
 *   - Sisa slot     = kuota hari itu - slot terisi
 */

// Kuota sesi per hari kalau admin belum mengaturnya (ubah sesuai kapasitas studio)
if (!defined('KUOTA_DEFAULT'))      define('KUOTA_DEFAULT', 4);
// Sisa slot sebanyak ini (atau kurang) dianggap "Terbatas"
if (!defined('SLOT_TERBATAS_MAKS')) define('SLOT_TERBATAS_MAKS', 2);

function slot_pastikan_tabel($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS slot_harian (
        tanggal DATE NOT NULL PRIMARY KEY,
        kuota INT NOT NULL DEFAULT " . KUOTA_DEFAULT . ",
        tutup TINYINT(1) NOT NULL DEFAULT 0,
        catatan VARCHAR(150) DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// tersedia | terbatas | penuh | tutup
function slot_status($kuota, $terisi, $tutup) {
    if ($tutup) return 'tutup';
    $sisa = max(0, $kuota - $terisi);
    if ($kuota <= 0 || $sisa <= 0) return 'penuh';
    if ($terisi > 0 && $sisa <= SLOT_TERBATAS_MAKS) return 'terbatas';
    return 'tersedia';
}

// Pilihan jam sesi yang tampil di halaman klien (nilai 'value' = yang disimpan di pesanan.jam_sesi)
function slot_daftar_jam() {
    return [
        ['value' => '09.00 - 11.00 WIB', 'label' => '09.00 – 11.00 WIB', 'mulai' => '09:00', 'desc' => 'Pagi • Cahaya Natural Paling Bagus di Outdoor Garden'],
        ['value' => '13.00 - 15.00 WIB', 'label' => '13.00 – 15.00 WIB', 'mulai' => '13:00', 'desc' => 'Siang • Pencahayaan Studio Stabil untuk Semua Konsep'],
        ['value' => '15.30 - 17.30 WIB', 'label' => '15.30 – 17.30 WIB', 'mulai' => '15:30', 'desc' => 'Sore • Golden Hour Mood & Romantic Warm Tone'],
        ['value' => '18.30 - 20.30 WIB', 'label' => '18.30 – 20.30 WIB', 'mulai' => '18:30', 'desc' => 'Malam • Night Editorial Lighting & Direct Flash Aesthetics'],
    ];
}

function slot_cari_jam($value) {
    foreach (slot_daftar_jam() as $j) {
        if ($j['value'] === $value) return $j;
    }
    return null;
}

function slot_tgl_lengkap($d) {
    static $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    static $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $t = strtotime($d);
    return $hari[(int)date('w', $t)] . ', ' . date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t);
}

/**
 * Data slot semua tanggal dalam 1 bulan.
 * Return: ['YYYY-MM-DD' => [kuota, tutup, catatan, terisi, pending, sisa, status, saya(array jam milik $user_id)]]
 */
function slot_data_bulan($conn, $tahun, $bulan, $user_id = 0) {
    $awal   = sprintf('%04d-%02d-01', $tahun, $bulan);
    $akhir  = date('Y-m-t', strtotime($awal));
    $jumlah = (int)date('t', strtotime($awal));

    // Pengaturan manual admin
    $ov = [];
    $st = $conn->prepare("SELECT tanggal, kuota, tutup, catatan FROM slot_harian WHERE tanggal BETWEEN ? AND ?");
    $st->bind_param("ss", $awal, $akhir);
    $st->execute();
    $r = $st->get_result();
    while ($row = $r->fetch_assoc()) $ov[$row['tanggal']] = $row;
    $st->close();

    // Booking yang masuk
    $terisi = []; $pending = [];
    $st = $conn->prepare("SELECT tanggal_sesi AS t, status FROM pesanan WHERE tanggal_sesi BETWEEN ? AND ? AND status NOT LIKE '%Batal%'");
    $st->bind_param("ss", $awal, $akhir);
    $st->execute();
    $r = $st->get_result();
    while ($row = $r->fetch_assoc()) {
        if ($row['status'] === 'Menunggu Pembayaran') $pending[$row['t']] = ($pending[$row['t']] ?? 0) + 1;
        else                                          $terisi[$row['t']]  = ($terisi[$row['t']] ?? 0) + 1;
    }
    $st->close();

    // Jam yang sudah dipesan akun ini
    $saya = [];
    if ($user_id > 0) {
        $st = $conn->prepare("SELECT tanggal_sesi AS t, jam_sesi AS j FROM pesanan WHERE user_id = ? AND tanggal_sesi BETWEEN ? AND ? AND status NOT LIKE '%Batal%'");
        $st->bind_param("iss", $user_id, $awal, $akhir);
        $st->execute();
        $r = $st->get_result();
        while ($row = $r->fetch_assoc()) $saya[$row['t']][] = trim($row['j']);
        $st->close();
    }

    $out = [];
    for ($d = 1; $d <= $jumlah; $d++) {
        $t = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
        $o = $ov[$t] ?? null;
        $kuota = $o ? (int)$o['kuota'] : KUOTA_DEFAULT;
        $tutup = $o ? (int)$o['tutup'] : 0;
        $ter   = $terisi[$t] ?? 0;
        $out[$t] = [
            'kuota' => $kuota, 'tutup' => $tutup, 'catatan' => $o['catatan'] ?? '',
            'terisi' => $ter, 'pending' => $pending[$t] ?? 0,
            'sisa' => max(0, $kuota - $ter), 'status' => slot_status($kuota, $ter, $tutup),
            'saya' => $saya[$t] ?? [],
        ];
    }
    return $out;
}

// Versi ringkas untuk dikirim ke browser klien (kalender di jadwal.php)
function slot_json_bulan($conn, $user_id, $bulan, $tahun) {
    $days = [];
    foreach (slot_data_bulan($conn, $tahun, $bulan, $user_id) as $t => $h) {
        $days[$t] = ['kuota' => $h['kuota'], 'sisa' => $h['sisa'], 'status' => $h['status'], 'saya' => $h['saya']];
    }
    return ['hari_ini' => date('Y-m-d'), 'menit_sekarang' => (int)date('G') * 60 + (int)date('i'), 'days' => $days];
}

/**
 * Validasi akhir sebelum pesanan disimpan. Panggil di checkout.php.
 * Return: ['ok' => bool, 'pesan' => string]
 */
function slot_validasi($conn, $user_id, $tanggal, $jam) {
    $gagal = function ($pesan) { return ['ok' => false, 'pesan' => $pesan]; };
    $tanggal = trim((string)$tanggal);
    $jam     = trim((string)$jam);

    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tanggal, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        return $gagal('Tanggal sesi tidak valid. Silakan pilih tanggal dari kalender.');
    }
    if ($tanggal < date('Y-m-d')) {
        return $gagal('Tanggal sesi sudah lewat. Silakan pilih tanggal lain.');
    }
    $def = slot_cari_jam($jam);
    if (!$def) {
        return $gagal('Jam sesi tidak valid. Silakan pilih salah satu jam yang tersedia.');
    }
    if ($tanggal === date('Y-m-d') && strtotime($tanggal . ' ' . $def['mulai']) <= time()) {
        return $gagal('Jam sesi ' . $def['label'] . ' hari ini sudah lewat. Silakan pilih jam atau tanggal lain.');
    }

    $h = slot_data_bulan($conn, (int)$m[1], (int)$m[2], (int)$user_id)[$tanggal];
    $tgl_txt = slot_tgl_lengkap($tanggal);

    if ($h['status'] === 'tutup') {
        return $gagal('Studio tutup pada ' . $tgl_txt . '. Silakan pilih tanggal lain.');
    }
    if ($h['status'] === 'penuh') {
        return $gagal('Slot pada ' . $tgl_txt . ' sudah penuh. Silakan pilih tanggal lain.');
    }
    if (in_array($jam, $h['saya'], true)) {
        return $gagal('Akun Anda sudah memiliki reservasi pada ' . $tgl_txt . ' pukul ' . $def['label'] . '. Silakan pilih jam atau tanggal lain.');
    }
    return ['ok' => true, 'pesan' => ''];
}