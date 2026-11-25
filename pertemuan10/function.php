<?php
declare(strict_types=1);

/**
 * Fungsi 1: Menghitung sisa uang saku efektif setelah dikurangi cadangan tabungan darurat.
 * Memenuhi syarat: Parameter bertipe, return float, dan parameter nilai default 
 */
function hitungSisaEfektif(float $jatahAnggaran, float $terpakai, float $cadanganDarurat = 0.05): float
{
    $danaCadangan = $jatahAnggaran * $cadanganDarurat;
    $batasAman = $jatahAnggaran - $danaCadangan;
    return $batasAman - $terpakai;
}

/**
 * Fungsi 2: Mengevaluasi status pemakaian pos uang saku.
 * Memenuhi syarat: Parameter bertipe, return string, dan percabangan if/elseif/else.
 */
function evaluasiStatusPos(float $jatahAnggaran, float $terpakai): string
{
    $persentase = ($jatahAnggaran > 0) ? (($terpakai / $jatahAnggaran) * 100) : 100.0;
    if ($persentase > 100.0) {
        return 'Overbudget';
    } elseif ($persentase >= 80.0) {
        return 'Waspada';
    } else {
        return 'Aman';
    }
}

/**
 * Fungsi 3: Mengolah rincian lengkap tiap baris pos uang saku.
 * Memenuhi syarat: Parameter bertipe, mengembalikan array, dan menggunakan match.
 */
function olahRincianPos(array $pos, float $cadanganDarurat = 0.05): array
{
    $jatah = (float) $pos['jatah_anggaran'];
    $terpakai = (float) $pos['terpakai'];
    $sisaKotor = $jatah - $terpakai;
    $danaCadangan = $jatah * $cadanganDarurat;
    $sisaEfektif = hitungSisaEfektif($jatah, $terpakai, $cadanganDarurat);
    $persenPakai = ($jatah > 0) ? (($terpakai / $jatah) * 100) : 0.0;
    $status = evaluasiStatusPos($jatah, $terpakai);

    $prioritas = match ($pos['kategori']) {
        'Kebutuhan Pokok' => 'Prioritas 1',
        'Akademik'        => 'Prioritas 2',
        'Penunjang'       => 'Prioritas 3',
        'Gaya Hidup'      => 'Prioritas 4',
        default           => 'Prioritas Umum',
    };

    
    $levelSerapan = (int) min(5, max(1, ceil($persenPakai / 20)));

    return [
        'sisa_kotor'    => $sisaKotor,
        'dana_cadangan' => $danaCadangan,
        'sisa_efektif'  => $sisaEfektif,
        'persen_pakai'  => $persenPakai,
        'status'        => $status,
        'prioritas'     => $prioritas,
        'level_serapan' => $levelSerapan
    ];
}