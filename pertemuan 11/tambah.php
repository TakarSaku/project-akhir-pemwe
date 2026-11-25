<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requireLogin();
initSessionData();

if (file_exists(__DIR__ . '/validation.php')) {
    require_once __DIR__ . '/validation.php';
}

// Tangani pergantian tema cookie dari header
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ubah_tema') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('danger', 'Token keamanan tidak valid saat mengubah tema.');
    } else {
        $temaBaru = (string) ($_POST['tema'] ?? 'light');
        if (setThemePreference($temaBaru)) {
            $labelTema = $temaBaru === 'dark' ? 'Mode Gelap' : 'Mode Terang';
            setFlash('success', "Tampilan diubah ke {$labelTema}.");
        } else {
            setFlash('danger', 'Pilihan tema tidak valid.');
        }
    }
    session_write_close();
    header('Location: tambah.php');
    exit;
}

function formatRupiahTambah(float $nominal): string
{
    $isNegatif = $nominal < 0;
    $teks = 'Rp ' . number_format(abs($nominal), 0, ',', '.');
    return $isNegatif ? '-' . $teks : $teks;
}

$daftarKategori = defined('KATEGORI_VALID')
    ? KATEGORI_VALID
    : ['Kebutuhan Pokok', 'Akademik', 'Penunjang', 'Gaya Hidup'];

$daftarMetode = defined('METODE_BAYAR_VALID')
    ? METODE_BAYAR_VALID
    : ['Tunai', 'QRIS', 'Transfer Bank', 'E-Wallet'];

$posSesi = $_SESSION['pos_uang_saku'] ?? [];
$jumlahPosSaatIni = count($posSesi);
$totalJatahSesi = (float) array_sum(array_column($posSesi, 'jatah_anggaran'));
$totalTerpakaiSesi = (float) array_sum(array_column($posSesi, 'terpakai'));

// Ambil kode terakhir & saran kode berikutnya
$kodeTerakhir = '-';
$saranKode = 'POS-01';
if (!empty($posSesi)) {
    $itemTerakhir = end($posSesi);
    $kodeTerakhir = (string) ($itemTerakhir['kode'] ?? '-');
    if (preg_match('/^POS-(\d+)$/i', $kodeTerakhir, $match)) {
        $nomorBerikutnya = (int) $match[1] + 1;
        $saranKode = 'POS-' . str_pad((string) $nomorBerikutnya, 2, '0', STR_PAD_LEFT);
    }
}

$errors = [];
$old = [
    'kode'                => '',
    'kategori'            => '',
    'nama_pos'            => '',
    'jatah_anggaran'      => '',
    'terpakai'            => '0',
    'frekuensi_transaksi' => '1',
    'metode_bayar'        => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'kode'                => strtoupper(trim((string) ($_POST['kode'] ?? ''))),
        'kategori'            => trim((string) ($_POST['kategori'] ?? '')),
        'nama_pos'            => trim((string) ($_POST['nama_pos'] ?? '')),
        'jatah_anggaran'      => trim((string) ($_POST['jatah_anggaran'] ?? '')),
        'terpakai'            => trim((string) ($_POST['terpakai'] ?? '0')),
        'frekuensi_transaksi' => trim((string) ($_POST['frekuensi_transaksi'] ?? '1')),
        'metode_bayar'        => is_array($_POST['metode_bayar'] ?? null) ? $_POST['metode_bayar'] : [],
    ];

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['umum'] = 'Token keamanan (CSRF) tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        if (function_exists('validasiInputPos')) {
            $hasil = validasiInputPos($_POST, $posSesi);
            $errors = $hasil['errors'] ?? [];
            $dataValid = $hasil['data'] ?? null;
        } else {
            // Fallback validasi sisi server mandiri jika fungsi eksternal belum dimuat
            if ($old['kode'] === '' || !preg_match('/^POS-[0-9]{2,3}$/', $old['kode'])) {
                $errors['kode'] = 'Gunakan format kode seperti POS-11.';
            } else {
                foreach ($posSesi as $item) {
                    if (strcasecmp((string) ($item['kode'] ?? ''), $old['kode']) === 0) {
                        $errors['kode'] = "Kode {$old['kode']} sudah terdaftar di sesi ini.";
                        break;
                    }
                }
            }

            if (!in_array($old['kategori'], $daftarKategori, true)) {
                $errors['kategori'] = 'Pilih salah satu kategori pengeluaran.';
            }

            if (mb_strlen($old['nama_pos']) < 3) {
                $errors['nama_pos'] = 'Nama pos minimal terdiri dari 3 karakter.';
            }

            $jatahNum = filter_var($old['jatah_anggaran'], FILTER_VALIDATE_FLOAT);
            if ($jatahNum === false || $jatahNum <= 0) {
                $errors['jatah_anggaran'] = 'Jatah anggaran harus lebih dari Rp 0.';
            }

            $terpakaiNum = filter_var($old['terpakai'], FILTER_VALIDATE_FLOAT);
            if ($terpakaiNum === false || $terpakaiNum < 0) {
                $errors['terpakai'] = 'Realisasi terpakai tidak boleh bernilai negatif.';
            }

            $frekNum = filter_var($old['frekuensi_transaksi'], FILTER_VALIDATE_INT);
            if ($frekNum === false || $frekNum < 0) {
                $errors['frekuensi_transaksi'] = 'Frekuensi transaksi berupa bilangan bulat minimal 0.';
            }

            $metodeTerpilih = array_values(array_intersect($old['metode_bayar'], $daftarMetode));
            if (empty($metodeTerpilih)) {
                $errors['metode_bayar'] = 'Pilih minimal satu metode pembayaran.';
            }

            $dataValid = empty($errors) ? [
                'kode'                => $old['kode'],
                'nama_pos'            => $old['nama_pos'],
                'kategori'            => $old['kategori'],
                'jatah_anggaran'      => (float) $jatahNum,
                'terpakai'            => (float) $terpakaiNum,
                'frekuensi_transaksi' => (int) $frekNum,
                'metode_bayar'        => $metodeTerpilih,
            ] : null;
        }

        if (empty($errors) && is_array($dataValid)) {
            $_SESSION['pos_uang_saku'][] = $dataValid;
            setFlash('success', "Pos \"{$dataValid['nama_pos']}\" ({$dataValid['kode']}) berhasil ditambahkan.");
            session_write_close();
            header('Location: index.php?tab=rincian');
            exit;
        }
    }
}

$user = getCurrentUser();
$temaAktif = getThemePreference();
$temaBerikutnya = $temaAktif === 'dark' ? 'light' : 'dark';
$flash = getFlash();

// Nilai awal untuk kartu pratinjau di sisi kanan
$prevJatah = is_numeric($old['jatah_anggaran']) ? (float) $old['jatah_anggaran'] : 0.0;
$prevTerpakai = is_numeric($old['terpakai']) ? (float) $old['terpakai'] : 0.0;
$prevSisa = $prevJatah - $prevTerpakai;
$prevPersen = $prevJatah > 0 ? ($prevTerpakai / $prevJatah) * 100 : 0.0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pos Pengeluaran — PocketFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="app-body <?= $temaAktif === 'dark' ? 'theme-dark' : '' ?>">

    <!-- Header Terpadu Satu Tema dengan Dasbor -->
    <header class="app-header">
        <div class="app-header-inner">
            <div class="app-header-left">
                <a href="index.php" class="app-brand">
                    <span class="app-brand-mark" aria-hidden="true">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/>
                            <path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/>
                            <path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>
                        </svg>
                    </span>
                    <span class="app-brand-name">PocketFlow</span>
                </a>

                <nav class="app-tabs" aria-label="Navigasi halaman utama">
                    <a href="index.php?tab=ringkasan" class="app-tab">Ringkasan</a>
                    <a href="index.php?tab=rincian" class="app-tab">
                        Rincian Alokasi
                        <span class="app-tab-count"><?= $jumlahPosSaatIni ?></span>
                    </a>
                </nav>
            </div>

            <div class="app-header-right">
                <a href="index.php?tab=rincian" class="pf-btn pf-btn-outline pf-btn-sm">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
                    Kembali
                </a>

                <form method="POST" action="tambah.php" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="ubah_tema">
                    <input type="hidden" name="tema" value="<?= e($temaBerikutnya) ?>">
                    <button type="submit" class="app-icon-btn" title="Ganti ke <?= $temaAktif === 'dark' ? 'Mode Terang' : 'Mode Gelap' ?>" aria-label="Ubah tema tampilan">
                        <?php if ($temaAktif === 'dark'): ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                        <?php else: ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                        <?php endif; ?>
                    </button>
                </form>

                <span class="app-divider" aria-hidden="true"></span>
                <span class="app-user-label"><?= e($user['nama']) ?></span>

                <form method="POST" action="logout.php" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <button type="submit" class="app-logout-btn">Keluar</button>
                </form>
            </div>
        </div>
    </header>

    <main class="app-container">
        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>" role="status">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors['umum'])): ?>
            <div class="alert alert-danger" role="alert">
                <?= e($errors['umum']) ?>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="form-page-head">
            <nav class="breadcrumb-trail" aria-label="Jejak navigasi">
                <a href="index.php?tab=ringkasan">Dasbor</a>
                <span aria-hidden="true">/</span>
                <a href="index.php?tab=rincian">Rincian Alokasi</a>
                <span aria-hidden="true">/</span>
                <span>Tambah Pos Baru</span>
            </nav>
            <h1 class="hero-title">Tambah Pos Pengeluaran</h1>
        </div>

        <!-- 2-Column Workspace: Form Utama (Kiri) + Pratinjau & Konteks (Kanan) -->
        <div class="form-workspace-grid">
            <section class="bento-panel pf-form-panel" aria-label="Formulir pos pengeluaran baru">
                <form method="POST" action="tambah.php" novalidate class="pf-stack-form">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

                    <!-- Kelompok 1: Identitas Pos -->
                    <div class="form-section-block">
                        <div class="form-section-title">Identitas Pos</div>

                        <div class="form-grid-2">
                            <div class="pf-field">
                                <label for="kode">Kode Pos <span class="req-star">*</span></label>
                                <input
                                    type="text"
                                    id="kode"
                                    name="kode"
                                    class="pf-input num-col <?= isset($errors['kode']) ? 'is-invalid' : '' ?>"
                                    value="<?= e($old['kode']) ?>"
                                    placeholder="Contoh: <?= e($saranKode) ?>"
                                    aria-invalid="<?= isset($errors['kode']) ? 'true' : 'false' ?>"
                                    <?= isset($errors['kode']) ? 'aria-describedby="err-kode"' : '' ?>
                                >
                                <?php if (isset($errors['kode'])): ?>
                                    <span id="err-kode" class="field-error"><?= e($errors['kode']) ?></span>
                                <?php else: ?>
                                    <span class="field-hint">Kode terakhir: <strong class="num-col"><?= e($kodeTerakhir) ?></strong></span>
                                <?php endif; ?>
                            </div>

                            <div class="pf-field">
                                <label for="kategori">Kategori Pengeluaran <span class="req-star">*</span></label>
                                <select
                                    id="kategori"
                                    name="kategori"
                                    class="pf-input <?= isset($errors['kategori']) ? 'is-invalid' : '' ?>"
                                    aria-invalid="<?= isset($errors['kategori']) ? 'true' : 'false' ?>"
                                    <?= isset($errors['kategori']) ? 'aria-describedby="err-kategori"' : '' ?>
                                >
                                    <option value="">Pilih kategori</option>
                                    <?php foreach ($daftarKategori as $kat): ?>
                                        <option value="<?= e($kat) ?>" <?= $old['kategori'] === $kat ? 'selected' : '' ?>>
                                            <?= e($kat) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['kategori'])): ?>
                                    <span id="err-kategori" class="field-error"><?= e($errors['kategori']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="pf-field">
                            <label for="nama_pos">Nama Pos Pengeluaran <span class="req-star">*</span></label>
                            <input
                                type="text"
                                id="nama_pos"
                                name="nama_pos"
                                class="pf-input <?= isset($errors['nama_pos']) ? 'is-invalid' : '' ?>"
                                value="<?= e($old['nama_pos']) ?>"
                                placeholder="Contoh: Beli Buku Referensi & Modul Praktikum"
                                aria-invalid="<?= isset($errors['nama_pos']) ? 'true' : 'false' ?>"
                                <?= isset($errors['nama_pos']) ? 'aria-describedby="err-nama"' : '' ?>
                            >
                            <?php if (isset($errors['nama_pos'])): ?>
                                <span id="err-nama" class="field-error"><?= e($errors['nama_pos']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Kelompok 2: Nominal & Aktivitas -->
                    <div class="form-section-block">
                        <div class="form-section-title">Anggaran &amp; Realisasi</div>

                        <div class="form-grid-3">
                            <div class="pf-field">
                                <label for="jatah_anggaran">Jatah Anggaran (Rp) <span class="req-star">*</span></label>
                                <input
                                    type="number"
                                    id="jatah_anggaran"
                                    name="jatah_anggaran"
                                    min="1000"
                                    step="1000"
                                    class="pf-input num-col <?= isset($errors['jatah_anggaran']) ? 'is-invalid' : '' ?>"
                                    value="<?= e($old['jatah_anggaran']) ?>"
                                    placeholder="250000"
                                    aria-invalid="<?= isset($errors['jatah_anggaran']) ? 'true' : 'false' ?>"
                                    <?= isset($errors['jatah_anggaran']) ? 'aria-describedby="err-jatah"' : '' ?>
                                >
                                <?php if (isset($errors['jatah_anggaran'])): ?>
                                    <span id="err-jatah" class="field-error"><?= e($errors['jatah_anggaran']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="pf-field">
                                <label for="terpakai">Realisasi Terpakai (Rp) <span class="req-star">*</span></label>
                                <input
                                    type="number"
                                    id="terpakai"
                                    name="terpakai"
                                    min="0"
                                    step="1000"
                                    class="pf-input num-col <?= isset($errors['terpakai']) ? 'is-invalid' : '' ?>"
                                    value="<?= e($old['terpakai']) ?>"
                                    placeholder="0"
                                    aria-invalid="<?= isset($errors['terpakai']) ? 'true' : 'false' ?>"
                                    <?= isset($errors['terpakai']) ? 'aria-describedby="err-terpakai"' : '' ?>
                                >
                                <?php if (isset($errors['terpakai'])): ?>
                                    <span id="err-terpakai" class="field-error"><?= e($errors['terpakai']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="pf-field">
                                <label for="frekuensi_transaksi">Frekuensi Transaksi <span class="req-star">*</span></label>
                                <input
                                    type="number"
                                    id="frekuensi_transaksi"
                                    name="frekuensi_transaksi"
                                    min="0"
                                    step="1"
                                    class="pf-input num-col <?= isset($errors['frekuensi_transaksi']) ? 'is-invalid' : '' ?>"
                                    value="<?= e($old['frekuensi_transaksi']) ?>"
                                    placeholder="1"
                                    aria-invalid="<?= isset($errors['frekuensi_transaksi']) ? 'true' : 'false' ?>"
                                    <?= isset($errors['frekuensi_transaksi']) ? 'aria-describedby="err-frek"' : '' ?>
                                >
                                <?php if (isset($errors['frekuensi_transaksi'])): ?>
                                    <span id="err-frek" class="field-error"><?= e($errors['frekuensi_transaksi']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Kelompok 3: Metode Pembayaran -->
                    <div class="form-section-block">
                        <div class="pf-field">
                            <span class="pf-group-label" id="label-metode">Metode Pembayaran <span class="req-star">*</span></span>
                            <div
                                class="method-tile-grid <?= isset($errors['metode_bayar']) ? 'is-invalid-box' : '' ?>"
                                role="group"
                                aria-labelledby="label-metode"
                                <?= isset($errors['metode_bayar']) ? 'aria-describedby="err-metode"' : '' ?>
                            >
                                <?php foreach ($daftarMetode as $metode):
                                    $isChecked = in_array($metode, $old['metode_bayar'], true);
                                ?>
                                    <label class="method-tile">
                                        <input
                                            type="checkbox"
                                            name="metode_bayar[]"
                                            value="<?= e($metode) ?>"
                                            <?= $isChecked ? 'checked' : '' ?>
                                        >
                                        <span class="method-tile-box">
                                            <span class="method-check" aria-hidden="true"></span>
                                            <span><?= e($metode) ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if (isset($errors['metode_bayar'])): ?>
                                <span id="err-metode" class="field-error"><?= e($errors['metode_bayar']) ?></span>
                            <?php else: ?>
                                <span class="field-hint">Dapat memilih lebih dari satu metode pembayaran yang biasa dipakai.</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="pf-form-actions">
                        <a href="index.php?tab=rincian" class="pf-btn pf-btn-outline">Batal</a>
                        <button type="submit" class="pf-btn pf-btn-forest">Simpan Pos Pengeluaran</button>
                    </div>
                </form>
            </section>

            <!-- Kolom Kanan: Live Preview Card (Satu Tema dengan Hero Forest Card) + Konteks Sesi -->
            <aside class="form-side-stack" aria-label="Pratinjau pos dan ringkasan sesi">
                <div class="bento-panel preview-forest-card">
                    <div class="forest-top-row">
                        <span class="preview-code-pill num-col" id="prevKode"><?= e($old['kode'] !== '' ? $old['kode'] : $saranKode) ?></span>
                        <span class="forest-status-tag is-safe" id="prevStatus">Aman</span>
                    </div>

                    <div>
                        <span class="forest-kicker" id="prevKategori">
                            <?= e($old['kategori'] !== '' ? $old['kategori'] : 'Kategori belum dipilih') ?>
                        </span>
                        <h2 class="preview-pos-name" id="prevNama">
                            <?= e($old['nama_pos'] !== '' ? $old['nama_pos'] : 'Nama pos pengeluaran') ?>
                        </h2>
                    </div>

                    <div class="preview-metrics">
                        <div class="preview-metric-row">
                            <span>Jatah Anggaran</span>
                            <strong class="num-col" id="prevJatah"><?= formatRupiahTambah($prevJatah) ?></strong>
                        </div>
                        <div class="preview-metric-row">
                            <span>Realisasi Terpakai</span>
                            <strong class="num-col" id="prevTerpakai"><?= formatRupiahTambah($prevTerpakai) ?></strong>
                        </div>
                        <div class="preview-bar-track">
                            <div class="preview-bar-fill" id="prevBar" style="width: <?= min(100, round($prevPersen, 1)) ?>%;"></div>
                        </div>
                        <div class="preview-metric-row preview-metric-foot">
                            <span>Sisa Sebelum Cadangan</span>
                            <strong class="num-col" id="prevSisa"><?= formatRupiahTambah($prevSisa) ?></strong>
                        </div>
                    </div>
                </div>

                <div class="bento-panel session-context-card">
                    <div class="panel-head" style="margin-bottom: 12px;">
                        <h2>Ringkasan Sesi Aktif</h2>
                        <p>Posisi anggaran sebelum pos baru ditambahkan.</p>
                    </div>

                    <div class="context-kv-list">
                        <div class="context-kv-row">
                            <span>Pos Terdaftar</span>
                            <strong class="num-col"><?= $jumlahPosSaatIni ?> pos</strong>
                        </div>
                        <div class="context-kv-row">
                            <span>Total Jatah Saat Ini</span>
                            <strong class="num-col"><?= formatRupiahTambah($totalJatahSesi) ?></strong>
                        </div>
                        <div class="context-kv-row">
                            <span>Total Realisasi</span>
                            <strong class="num-col"><?= formatRupiahTambah($totalTerpakaiSesi) ?></strong>
                        </div>
                        <div class="context-kv-row">
                            <span>Saran Kode Berikutnya</span>
                            <strong class="code-badge"><?= e($saranKode) ?></strong>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <script>
        (function () {
            const inputKode = document.getElementById('kode');
            const inputKategori = document.getElementById('kategori');
            const inputNama = document.getElementById('nama_pos');
            const inputJatah = document.getElementById('jatah_anggaran');
            const inputTerpakai = document.getElementById('terpakai');

            const prevKode = document.getElementById('prevKode');
            const prevKategori = document.getElementById('prevKategori');
            const prevNama = document.getElementById('prevNama');
            const prevJatah = document.getElementById('prevJatah');
            const prevTerpakai = document.getElementById('prevTerpakai');
            const prevSisa = document.getElementById('prevSisa');
            const prevStatus = document.getElementById('prevStatus');
            const prevBar = document.getElementById('prevBar');

            const formatRp = (angka) => {
                const neg = angka < 0;
                const teks = 'Rp ' + Math.round(Math.abs(angka)).toLocaleString('id-ID');
                return neg ? '-' + teks : teks;
            };

            const updatePreview = () => {
                const kodeVal = (inputKode.value || '').trim().toUpperCase();
                prevKode.textContent = kodeVal !== '' ? kodeVal : '<?= e($saranKode) ?>';

                prevKategori.textContent = inputKategori.value !== ''
                    ? inputKategori.value
                    : 'Kategori belum dipilih';

                const namaVal = (inputNama.value || '').trim();
                prevNama.textContent = namaVal !== '' ? namaVal : 'Nama pos pengeluaran';

                const jatah = Math.max(0, parseFloat(inputJatah.value) || 0);
                const terpakai = Math.max(0, parseFloat(inputTerpakai.value) || 0);
                const sisa = jatah - terpakai;
                const persen = jatah > 0 ? (terpakai / jatah) * 100 : 0;

                prevJatah.textContent = formatRp(jatah);
                prevTerpakai.textContent = formatRp(terpakai);
                prevSisa.textContent = formatRp(sisa);
                prevBar.style.width = Math.min(100, Math.round(persen)) + '%';

                prevStatus.classList.remove('is-safe', 'is-warn', 'is-deficit');
                if (jatah > 0 && persen > 100) {
                    prevStatus.textContent = 'Overbudget';
                    prevStatus.classList.add('is-deficit');
                } else if (jatah > 0 && persen >= 80) {
                    prevStatus.textContent = 'Waspada';
                    prevStatus.classList.add('is-warn');
                } else {
                    prevStatus.textContent = 'Aman';
                    prevStatus.classList.add('is-safe');
                }
            };

            [inputKode, inputKategori, inputNama, inputJatah, inputTerpakai].forEach((el) => {
                if (el) {
                    el.addEventListener('input', updatePreview);
                    el.addEventListener('change', updatePreview);
                }
            });

            updatePreview();
        })();
    </script>
</body>
</html>