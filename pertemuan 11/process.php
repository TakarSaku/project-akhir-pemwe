<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../pertemuan10/function.php';

initSessionData();
$posUangSaku = $_SESSION['pos_uang_saku'];

// Filter & pencarian GET
$kataKunciRaw  = trim((string) ($_GET['cari'] ?? ''));
$kataKunci     = e($kataKunciRaw);
$kategoriPilih = trim((string) ($_GET['kategori'] ?? 'Semua'));
$opsiCadangan  = (int) ($_GET['cadangan'] ?? 5);

if ($opsiCadangan < 0 || $opsiCadangan > 20) {
    $opsiCadangan = 5;
}
$persenCadangan = $opsiCadangan / 100;

$semuaKategori = array_unique(array_column($posUangSaku, 'kategori'));

$dataTersaring = array_filter($posUangSaku, function (array $item) use ($kataKunciRaw, $kategoriPilih): bool {
    $cocokKategori = ($kategoriPilih === 'Semua') || (strcasecmp($item['kategori'], $kategoriPilih) === 0);
    $cocokTeks = ($kataKunciRaw === '')
        || (stripos($item['nama_pos'], $kataKunciRaw) !== false)
        || (stripos($item['kode'], $kataKunciRaw) !== false);
    return $cocokKategori && $cocokTeks;
});

$totalJatah     = (float) array_sum(array_column($dataTersaring, 'jatah_anggaran'));
$totalTerpakai  = (float) array_sum(array_column($dataTersaring, 'terpakai'));
$totalTransaksi = (int) array_sum(array_column($dataTersaring, 'frekuensi_transaksi'));
$totalSisaKotor = $totalJatah - $totalTerpakai;

$totalSisaEfektif = 0.0;
foreach ($dataTersaring as $pos) {
    $rincian = olahRincianPos($pos, $persenCadangan);
    $totalSisaEfektif += $rincian['sisa_efektif'];
}