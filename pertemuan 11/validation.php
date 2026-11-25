<?php
declare(strict_types=1);

const KATEGORI_VALID = [
    'Kebutuhan Pokok',
    'Akademik',
    'Penunjang',
    'Gaya Hidup',
];

const METODE_BAYAR_VALID = [
    'Tunai',
    'QRIS',
    'Transfer Bank',
    'E-Wallet',
];

/**
 * Syarat 2: Fungsi validasi sisi server dengan 6 aturan & pesan galat per field.
 * Mengembalikan array ['errors' => [...], 'clean' => [...]].
 */
function validasiPosAnggaran(array $post, array $existingData): array
{
    $errors = [];

    $kode       = strtoupper(trim((string) ($post['kode'] ?? '')));
    $namaPos    = trim((string) ($post['nama_pos'] ?? ''));
    $kategori   = trim((string) ($post['kategori'] ?? ''));
    $jatahRaw   = trim((string) ($post['jatah_anggaran'] ?? ''));
    $terpakaiRaw = trim((string) ($post['terpakai'] ?? ''));
    $frekRaw    = trim((string) ($post['frekuensi_transaksi'] ?? ''));
    $metodeRaw  = $post['metode_bayar'] ?? [];

    // Aturan 1: Kode wajib diisi, berformat POS-XX, dan tidak boleh duplikat
    if ($kode === '') {
        $errors['kode'] = 'Kode pos wajib diisi.';
    } elseif (!preg_match('/^POS-\d{2,}$/', $kode)) {
        $errors['kode'] = 'Format kode harus POS-XX (contoh: POS-11).';
    } else {
        $kodeTerdaftar = array_map('strtoupper', array_column($existingData, 'kode'));
        if (in_array($kode, $kodeTerdaftar, true)) {
            $errors['kode'] = "Kode {$kode} sudah digunakan. Pilih kode lain.";
        }
    }

    // Aturan 2: Nama pos wajib diisi dan panjang 3–80 karakter
    if ($namaPos === '') {
        $errors['nama_pos'] = 'Nama pos pengeluaran wajib diisi.';
    } elseif (mb_strlen($namaPos) < 3 || mb_strlen($namaPos) > 80) {
        $errors['nama_pos'] = 'Nama pos harus terdiri dari 3 hingga 80 karakter.';
    }

    // Aturan 3: Kategori (select) wajib termasuk dalam whitelist
    if (!in_array($kategori, KATEGORI_VALID, true)) {
        $errors['kategori'] = 'Silakan pilih kategori pengeluaran yang valid.';
    }

    // Aturan 4: Jatah anggaran wajib angka positif (> 0)
    if ($jatahRaw === '' || !is_numeric($jatahRaw) || (float) $jatahRaw <= 0) {
        $errors['jatah_anggaran'] = 'Jatah anggaran wajib berupa angka lebih dari 0.';
    }

    // Aturan 5: Realisasi terpakai wajib angka >= 0 & frekuensi wajib bilangan bulat >= 0
    if ($terpakaiRaw === '' || !is_numeric($terpakaiRaw) || (float) $terpakaiRaw < 0) {
        $errors['terpakai'] = 'Realisasi terpakai wajib berupa angka minimal 0.';
    }

    if ($frekRaw === '' || filter_var($frekRaw, FILTER_VALIDATE_INT) === false || (int) $frekRaw < 0) {
        $errors['frekuensi_transaksi'] = 'Frekuensi transaksi wajib berupa bilangan bulat minimal 0.';
    }

    // Aturan 6: Checkbox array metode_bayar minimal pilih 1 dan wajib valid sesuai whitelist
    $metodeBersih = [];
    if (!is_array($metodeRaw) || count($metodeRaw) === 0) {
        $errors['metode_bayar'] = 'Pilih minimal satu metode pembayaran.';
    } else {
        foreach ($metodeRaw as $m) {
            if (is_string($m) && in_array($m, METODE_BAYAR_VALID, true)) {
                $metodeBersih[] = $m;
            }
        }
        $metodeBersih = array_values(array_unique($metodeBersih));
        if (count($metodeBersih) !== count($metodeRaw)) {
            $errors['metode_bayar'] = 'Terdapat pilihan metode pembayaran yang tidak valid.';
        }
    }

    return [
        'errors' => $errors,
        'clean'  => [
            'kode'                => $kode,
            'nama_pos'            => $namaPos,
            'kategori'            => $kategori,
            'jatah_anggaran'      => (float) $jatahRaw,
            'terpakai'            => (float) $terpakaiRaw,
            'frekuensi_transaksi' => (int) $frekRaw,
            'metode_bayar'        => $metodeBersih,
        ],
    ];
}