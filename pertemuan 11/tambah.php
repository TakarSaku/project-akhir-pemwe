<?php
declare(strict_types=1);

require_once _DIR_ . '/auth.php';
require_once _DIR_ . '/validation.php';

requireLogin();
initSessionData();

$temaAktif = getThemePreference();
$errors = [];

// Nilai default untuk sticky form
$old = [
    'kode'                => '',
    'nama_pos'            => '',
    'kategori'            => '',
    'jatah_anggaran'      => '',
    'terpakai'            => '0',
    'frekuensi_transaksi' => '1',
    'metode_bayar'        => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simpan nilai lama agar form bersifat sticky saat validasi gagal
    $old['kode']                = (string) ($_POST['kode'] ?? '');
    $old['nama_pos']            = (string) ($_POST['nama_pos'] ?? '');
    $old['kategori']            = (string) ($_POST['kategori'] ?? '');
    $old['jatah_anggaran']      = (string) ($_POST['jatah_anggaran'] ?? '');
    $old['terpakai']            = (string) ($_POST['terpakai'] ?? '');
    $old['frekuensi_transaksi'] = (string) ($_POST['frekuensi_transaksi'] ?? '');
    $old['metode_bayar']        = is_array($_POST['metode_bayar'] ?? null) ? $_POST['metode_bayar'] : [];

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['umum'] = 'Token CSRF tidak valid. Silakan kirim ulang formulir.';
    } else {
        $hasilValidasi = validasiPosAnggaran($_POST, $_SESSION['pos_uang_saku']);
        $errors = $hasilValidasi['errors'];

        if (empty($errors)) {
            // Syarat 3: Simpan data ke $_SESSION, set flash message, lalu redirect (pola PRG)
            $_SESSION['pos_uang_saku'][] = $hasilValidasi['clean'];
            setFlash(
                'success',
                'Pos pengeluaran "' . $hasilValidasi['clean']['nama_pos'] . '" (' . $hasilValidasi['clean']['kode'] . ') berhasil ditambahkan!'
            );
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pos Pengeluaran — PocketFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="<?= $temaAktif === 'dark' ? 'theme-dark' : '' ?>">
    <nav class="topbar">
        <div class="topbar-inner">
            <div class="brand">
                <div class="brand-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>
                </div>
                <div class="brand-text">
                    <h1>Tambah Pos Pengeluaran Baru</h1>
                    <p>Validasi sisi server, Sticky Form, CSRF, dan Penyimpanan Sesi (PRG)</p>
                </div>
            </div>
            <a href="index.php" class="btn btn-secondary">← Kembali ke Dasbor</a>
        </div>
    </nav>

    <main class="container" style="max-width: 780px;">
        <section class="form-card">
            <?php if (!empty($errors['umum'])): ?>
                <div class="alert alert-danger"><?= e($errors['umum']) ?></div>
            <?php endif; ?>

            <form method="POST" action="tambah.php" novalidate class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="kode">Kode Pos *</label>
                        <input type="text" id="kode" name="kode" class="form-control <?= isset($errors['kode']) ? 'is-invalid' : '' ?>" value="<?= e($old['kode']) ?>" placeholder="Contoh: POS-11">
                        <?php if (isset($errors['kode'])): ?>
                            <span class="field-error"><?= e($errors['kode']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="kategori">Kategori Pengeluaran (Select) *</label>
                        <select id="kategori" name="kategori" class="form-control <?= isset($errors['kategori']) ? 'is-invalid' : '' ?>">
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach (KATEGORI_VALID as $kat): ?>
                                <option value="<?= e($kat) ?>" <?= $old['kategori'] === $kat ? 'selected' : '' ?>>
                                    <?= e($kat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['kategori'])): ?>
                            <span class="field-error"><?= e($errors['kategori']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="nama_pos">Nama Pos Pengeluaran *</label>
                    <input type="text" id="nama_pos" name="nama_pos" class="form-control <?= isset($errors['nama_pos']) ? 'is-invalid' : '' ?>" value="<?= e($old['nama_pos']) ?>" placeholder="Contoh: Beli Buku Referensi & Modul">
                    <?php if (isset($errors['nama_pos'])): ?>
                        <span class="field-error"><?= e($errors['nama_pos']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label for="jatah_anggaran">Jatah Anggaran (Rp) *</label>
                        <input type="number" id="jatah_anggaran" name="jatah_anggaran" class="form-control <?= isset($errors['jatah_anggaran']) ? 'is-invalid' : '' ?>" value="<?= e($old['jatah_anggaran']) ?>" placeholder="250000">
                        <?php if (isset($errors['jatah_anggaran'])): ?>
                            <span class="field-error"><?= e($errors['jatah_anggaran']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="terpakai">Realisasi Terpakai (Rp) *</label>
                        <input type="number" id="terpakai" name="terpakai" class="form-control <?= isset($errors['terpakai']) ? 'is-invalid' : '' ?>" value="<?= e($old['terpakai']) ?>" placeholder="0">
                        <?php if (isset($errors['terpakai'])): ?>
                            <span class="field-error"><?= e($errors['terpakai']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="frekuensi_transaksi">Frekuensi Transaksi *</label>
                        <input type="number" id="frekuensi_transaksi" name="frekuensi_transaksi" class="form-control <?= isset($errors['frekuensi_transaksi']) ? 'is-invalid' : '' ?>" value="<?= e($old['frekuensi_transaksi']) ?>" placeholder="1">
                        <?php if (isset($errors['frekuensi_transaksi'])): ?>
                            <span class="field-error"><?= e($errors['frekuensi_transaksi']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Syarat 1: Input Checkbox Array -->
                <div class="form-group">
                    <label>Metode Pembayaran Digunakan (Pilih minimal 1) *</label>
                    <div class="checkbox-group <?= isset($errors['metode_bayar']) ? 'is-invalid-box' : '' ?>">
                        <?php foreach (METODE_BAYAR_VALID as $metode): ?>
                            <label class="checkbox-item">
                                <input
                                    type="checkbox"
                                    name="metode_bayar[]"
                                    value="<?= e($metode) ?>"
                                    <?= in_array($metode, $old['metode_bayar'], true) ? 'checked' : '' ?>
                                >
                                <span><?= e($metode) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($errors['metode_bayar'])): ?>
                        <span class="field-error"><?= e($errors['metode_bayar']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <a href="index.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan Pos Pengeluaran</button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>