<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requireLogin();

// Simpan preferensi tema ke cookie (POST + CSRF + PRG) dan pertahankan tab aktif
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ubah_tema') {
    $returnTab = in_array((string) ($_POST['return_tab'] ?? ''), ['ringkasan', 'rincian'], true)
        ? (string) $_POST['return_tab']
        : 'ringkasan';

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
    header('Location: index.php?tab=' . $returnTab);
    exit;
}

require_once __DIR__ . '/process.php';

function formatRupiahUI(float $nominal): string
{
    $isNegatif = $nominal < 0;
    $teks = 'Rp ' . number_format(abs($nominal), 0, ',', '.');
    return $isNegatif ? '-' . $teks : $teks;
}

function namaBulanIndonesia(): string
{
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];
    return ($bulan[(int) date('n')] ?? date('F')) . ' ' . date('Y');
}

function mapStatusBadge(string $status): array
{
    if (str_starts_with($status, 'Aman')) {
        return ['status-ok', 'Aman', 'is-ok'];
    }
    if (str_starts_with($status, 'Waspada')) {
        return ['status-warn', 'Waspada', 'is-warn'];
    }
    return ['status-danger', 'Overbudget', 'is-danger'];
}

function buildAppUrl(array $override = []): string
{
    $params = [];
    foreach (['tab', 'cari', 'kategori', 'cadangan'] as $key) {
        if (isset($_GET[$key]) && $_GET[$key] !== '') {
            $params[$key] = (string) $_GET[$key];
        }
    }
    foreach ($override as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = (string) $v;
        }
    }
    return 'index.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}

$sedangFilter = ($kataKunciRaw !== '') || ($kategoriPilih !== 'Semua');
$tabParam = (string) ($_GET['tab'] ?? ($sedangFilter ? 'rincian' : 'ringkasan'));
$activeTab = in_array($tabParam, ['ringkasan', 'rincian'], true) ? $tabParam : 'ringkasan';

$user = getCurrentUser();
$temaAktif = getThemePreference();
$temaBerikutnya = $temaAktif === 'dark' ? 'light' : 'dark';
$flash = getFlash();

// Agregasi menyeluruh untuk tab Ringkasan
$semuaJatah = (float) array_sum(array_column($posUangSaku, 'jatah_anggaran'));
$semuaTerpakai = (float) array_sum(array_column($posUangSaku, 'terpakai'));
$semuaTransaksi = (int) array_sum(array_column($posUangSaku, 'frekuensi_transaksi'));
$semuaSisaAktual = $semuaJatah - $semuaTerpakai;
$persenSerapanSemua = ($semuaJatah > 0) ? (($semuaTerpakai / $semuaJatah) * 100) : 0.0;
$persenSerapanFilter = ($totalJatah > 0) ? (($totalTerpakai / $totalJatah) * 100) : 0.0;

$semuaSisaEfektif = 0.0;
$kategoriMap = [
    'Kebutuhan Pokok' => ['jatah' => 0.0, 'terpakai' => 0.0, 'jumlah' => 0, 'warna' => '#84cc16'],
    'Akademik'        => ['jatah' => 0.0, 'terpakai' => 0.0, 'jumlah' => 0, 'warna' => '#15803d'],
    'Penunjang'       => ['jatah' => 0.0, 'terpakai' => 0.0, 'jumlah' => 0, 'warna' => '#0d9488'],
    'Gaya Hidup'      => ['jatah' => 0.0, 'terpakai' => 0.0, 'jumlah' => 0, 'warna' => '#d97706'],
];

$posPerhatian = [];
foreach ($posUangSaku as $item) {
    $rincian = olahRincianPos($item, $persenCadangan);
    $semuaSisaEfektif += $rincian['sisa_efektif'];

    $kat = (string) ($item['kategori'] ?? 'Lainnya');
    if (!isset($kategoriMap[$kat])) {
        $kategoriMap[$kat] = ['jatah' => 0.0, 'terpakai' => 0.0, 'jumlah' => 0, 'warna' => '#64748b'];
    }
    $kategoriMap[$kat]['jatah'] += (float) $item['jatah_anggaran'];
    $kategoriMap[$kat]['terpakai'] += (float) $item['terpakai'];
    $kategoriMap[$kat]['jumlah']++;

    if (!str_starts_with($rincian['status'], 'Aman')) {
        $posPerhatian[] = ['pos' => $item, 'rincian' => $rincian];
    }
}

usort($posPerhatian, fn(array $a, array $b): int => $b['rincian']['persen_pakai'] <=> $a['rincian']['persen_pakai']);
$daftarPantauan = array_slice($posPerhatian, 0, 4);
if (empty($daftarPantauan)) {
    foreach (array_slice(array_reverse($posUangSaku), 0, 4) as $item) {
        $daftarPantauan[] = [
            'pos'     => $item,
            'rincian' => olahRincianPos($item, $persenCadangan),
        ];
    }
}

// Conic-gradient untuk donut chart serapan
$gradientStops = [];
$akumulasiDeg = 0.0;
if ($semuaTerpakai > 0) {
    foreach ($kategoriMap as $katInfo) {
        if ($katInfo['terpakai'] <= 0) {
            continue;
        }
        $porsiDeg = ($katInfo['terpakai'] / $semuaTerpakai) * 360.0;
        $mulai = round($akumulasiDeg, 1);
        $akhir = round($akumulasiDeg + $porsiDeg, 1);
        $gradientStops[] = "{$katInfo['warna']} {$mulai}deg {$akhir}deg";
        $akumulasiDeg += $porsiDeg;
    }
}
$donutGradient = !empty($gradientStops)
    ? 'conic-gradient(' . implode(', ', $gradientStops) . ')'
    : 'conic-gradient(var(--pf-track) 0deg 360deg)';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $activeTab === 'rincian' ? 'Rincian Alokasi' : 'Ringkasan' ?> — PocketFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="app-body <?= $temaAktif === 'dark' ? 'theme-dark' : '' ?>">

    <!-- Top Navigation Bar (Full-Width Laptop) -->
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
                    <a href="<?= e(buildAppUrl(['tab' => 'ringkasan', 'cari' => null, 'kategori' => null])) ?>"
                       class="app-tab <?= $activeTab === 'ringkasan' ? 'is-active' : '' ?>"
                       <?= $activeTab === 'ringkasan' ? 'aria-current="page"' : '' ?>>
                        Ringkasan
                    </a>
                    <a href="<?= e(buildAppUrl(['tab' => 'rincian'])) ?>"
                       class="app-tab <?= $activeTab === 'rincian' ? 'is-active' : '' ?>"
                       <?= $activeTab === 'rincian' ? 'aria-current="page"' : '' ?>>
                        Rincian Alokasi
                        <span class="app-tab-count"><?= count($posUangSaku) ?></span>
                    </a>
                </nav>
            </div>

            <div class="app-header-right">
                <a href="tambah.php" class="pf-btn pf-btn-forest pf-btn-sm">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah Pos
                </a>

                <form method="POST" action="index.php" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="ubah_tema">
                    <input type="hidden" name="tema" value="<?= e($temaBerikutnya) ?>">
                    <input type="hidden" name="return_tab" value="<?= e($activeTab) ?>">
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

        <?php if ($activeTab === 'ringkasan'): ?>
            <!-- ===================================================
                 TAB 1: RINGKASAN (BENTO GRID FULL-WIDTH)
                 =================================================== -->
            <section class="hero-bento-grid" aria-label="Saldo aktual dan sisa efektif">
                <!-- Kartu Kiri: Sisa Aktual & Kontrol Cadangan -->
                <article class="bento-panel hero-left-card">
                    <div class="hero-top-row">
                        <div>
                            <span class="eyebrow-label">Periode <?= e(namaBulanIndonesia()) ?></span>
                            <h1 class="hero-title">Sisa Aktual Uang Saku</h1>
                        </div>

                        <div class="reserve-control" role="group" aria-label="Persentase cadangan darurat">
                            <span class="reserve-label">Cadangan</span>
                            <div class="reserve-segment">
                                <?php foreach ([0, 5, 10, 15, 20] as $opsi): ?>
                                    <a href="<?= e(buildAppUrl(['tab' => 'ringkasan', 'cadangan' => $opsi])) ?>"
                                       class="reserve-item <?= $opsiCadangan === $opsi ? 'is-active' : '' ?>">
                                        <?= $opsi ?>%
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="hero-figure-wrap">
                        <div class="hero-display-num num-col <?= $semuaSisaAktual < 0 ? 'is-negative' : '' ?>">
                            <?= formatRupiahUI($semuaSisaAktual) ?>
                        </div>
                        <p class="hero-caption">
                            Dari total jatah <strong class="num-col"><?= formatRupiahUI($semuaJatah) ?></strong> &middot;
                            <strong class="num-col"><?= number_format($persenSerapanSemua, 1) ?>%</strong> telah terealisasi dalam <?= $semuaTransaksi ?> transaksi
                        </p>
                    </div>

                    <div class="hero-bottom-row">
                        <div class="hero-actions">
                            <a href="<?= e(buildAppUrl(['tab' => 'rincian'])) ?>" class="pf-btn pf-btn-outline">
                                Lihat Rincian Alokasi
                            </a>
                        </div>

                        <div class="hero-inline-stats">
                            <div class="inline-stat">
                                <span>Realisasi Terpakai</span>
                                <strong class="num-col"><?= formatRupiahUI($semuaTerpakai) ?></strong>
                            </div>
                            <span class="stat-sep" aria-hidden="true"></span>
                            <div class="inline-stat">
                                <span>Total Pos</span>
                                <strong class="num-col"><?= count($posUangSaku) ?> pos</strong>
                            </div>
                        </div>
                    </div>
                </article>

                <!-- Kartu Kanan: Deep Forest Green Editorial Card (Sisa Efektif) -->
                <article class="bento-panel hero-forest-card">
                    <div class="forest-top-row">
                        <span class="forest-kicker">Sisa Efektif (Setelah Cadangan <?= $opsiCadangan ?>%)</span>
                        <span class="forest-status-tag <?= $semuaSisaEfektif < 0 ? 'is-deficit' : 'is-safe' ?>">
                            <?= $semuaSisaEfektif < 0 ? 'Defisit Cadangan' : 'Batas Aman' ?>
                        </span>
                    </div>

                    <div class="forest-figure-wrap">
                        <div class="forest-display-num num-col <?= $semuaSisaEfektif < 0 ? 'is-deficit' : '' ?>">
                            <?= formatRupiahUI($semuaSisaEfektif) ?>
                        </div>
                        <p class="forest-caption">
                            <?= $semuaSisaEfektif < 0
                                ? 'Sisa aktual belum mencukupi target tabungan darurat ' . $opsiCadangan . '%.'
                                : 'Dana bersih yang masih leluasa digunakan setelah menyisihkan cadangan.' ?>
                        </p>
                    </div>

                    <div class="forest-sub-grid">
                        <div class="forest-sub-box">
                            <span>Dana Disisihkan</span>
                            <strong class="num-col"><?= formatRupiahUI($semuaJatah * $persenCadangan) ?></strong>
                        </div>
                        <div class="forest-sub-box">
                            <span>Rata-rata per Pos</span>
                            <strong class="num-col"><?= formatRupiahUI(count($posUangSaku) > 0 ? $semuaTerpakai / count($posUangSaku) : 0.0) ?></strong>
                        </div>
                    </div>
                </article>
            </section>

            <!-- Baris Analitik 3 Kolom Seimbang -->
            <section class="analytics-grid" aria-label="Analitik kategori dan daftar pantauan">
                <!-- Kolom 1: Serapan Anggaran (Donut Chart) -->
                <article class="bento-panel">
                    <div class="panel-head">
                        <h2>Proporsi Serapan</h2>
                        <p>Distribusi realisasi pengeluaran per kategori.</p>
                    </div>

                    <div class="donut-stage">
                        <div class="donut-ring" style="background: <?= e($donutGradient) ?>;" role="img" aria-label="Grafik proporsi serapan <?= number_format($persenSerapanSemua, 1) ?> persen">
                            <div class="donut-center">
                                <strong class="num-col"><?= formatRupiahUI($semuaTerpakai) ?></strong>
                                <span><?= number_format($persenSerapanSemua, 1) ?>% terpakai</span>
                            </div>
                        </div>
                    </div>

                    <div class="donut-legend">
                        <?php foreach ($kategoriMap as $katNama => $katInfo):
                            if ($katInfo['jumlah'] === 0 && $katInfo['terpakai'] <= 0) {
                                continue;
                            }
                        ?>
                            <div class="legend-row">
                                <span class="legend-dot" style="background: <?= e($katInfo['warna']) ?>;"></span>
                                <span class="legend-label"><?= e($katNama) ?></span>
                                <strong class="legend-amount num-col"><?= formatRupiahUI($katInfo['terpakai']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <!-- Kolom 2: Alokasi & Serapan per Kategori -->
                <article class="bento-panel">
                    <div class="panel-head">
                        <h2>Realisasi per Kategori</h2>
                        <p>Perbandingan pengeluaran terhadap pagu tiap kelompok.</p>
                    </div>

                    <div class="cat-list">
                        <?php foreach ($kategoriMap as $katNama => $katInfo):
                            if ($katInfo['jumlah'] === 0 && $katInfo['jatah'] <= 0) {
                                continue;
                            }
                            $persenKat = ($katInfo['jatah'] > 0) ? (($katInfo['terpakai'] / $katInfo['jatah']) * 100) : 0.0;
                            $barTone = $persenKat >= 100 ? 'is-danger' : ($persenKat >= 80 ? 'is-warn' : 'is-ok');
                        ?>
                            <a href="<?= e(buildAppUrl(['tab' => 'rincian', 'kategori' => $katNama])) ?>" class="cat-item">
                                <div class="cat-item-top">
                                    <span class="cat-item-name"><?= e($katNama) ?></span>
                                    <span class="cat-item-nums num-col">
                                        <strong><?= formatRupiahUI($katInfo['terpakai']) ?></strong> / <?= formatRupiahUI($katInfo['jatah']) ?>
                                    </span>
                                </div>
                                <div class="cat-item-bar">
                                    <div class="cat-track">
                                        <div class="cat-fill <?= $barTone ?>" style="width: <?= min(100, round($persenKat, 1)) ?>%;"></div>
                                    </div>
                                    <span class="cat-pct num-col <?= $barTone ?>"><?= number_format($persenKat, 1) ?>%</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </article>

                <!-- Kolom 3: Pos Perlu Perhatian -->
                <article class="bento-panel panel-col">
                    <div class="panel-head">
                        <h2><?= !empty($posPerhatian) ? 'Perlu Perhatian' : 'Pos Terbaru' ?></h2>
                        <p>
                            <?= !empty($posPerhatian)
                                ? count($posPerhatian) . ' pos telah mencapai 80% atau melampaui jatah.'
                                : 'Seluruh pos berada dalam batas terkendali.' ?>
                        </p>
                    </div>

                    <div class="watch-list">
                        <?php foreach ($daftarPantauan as $entry):
                            $p = $entry['pos'];
                            $r = $entry['rincian'];
                            [$badgeClass, $badgeLabel] = mapStatusBadge($r['status']);
                        ?>
                            <div class="watch-row">
                                <div class="watch-main">
                                    <strong><?= e($p['nama_pos']) ?></strong>
                                    <span><?= e($p['kategori']) ?> &middot; <?= number_format($r['persen_pakai'], 1) ?>% terpakai</span>
                                </div>
                                <div class="watch-side">
                                    <strong class="num-col"><?= formatRupiahUI((float) $p['terpakai']) ?></strong>
                                    <span class="status-badge status-badge-sm <?= $badgeClass ?>">
                                        <span class="status-dot"></span>
                                        <?= e($badgeLabel) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="panel-foot">
                        <a href="<?= e(buildAppUrl(['tab' => 'rincian'])) ?>" class="text-arrow-link">
                            Lihat seluruh rincian alokasi
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </a>
                    </div>
                </article>
            </section>

        <?php else: ?>
            <!-- ===================================================
                 TAB 2: RINCIAN ALOKASI (FILTER GET + TABEL LENGKAP)
                 =================================================== -->
            <section class="rincian-head">
                <div>
                    <h1 class="hero-title">Rincian Alokasi</h1>
                    <p class="hero-caption">
                        Menampilkan <?= count($dataTersaring) ?> dari <?= count($posUangSaku) ?> pos
                        &middot; Realisasi <strong class="num-col"><?= formatRupiahUI($totalTerpakai) ?></strong>
                        &middot; Sisa efektif <strong class="num-col <?= $totalSisaEfektif < 0 ? 'is-negative' : 'is-positive' ?>"><?= formatRupiahUI($totalSisaEfektif) ?></strong>
                    </p>
                </div>
            </section>

            <section class="bento-panel table-shell" aria-label="Filter dan daftar pos pengeluaran">
                <form method="GET" action="index.php" class="table-toolbar">
                    <input type="hidden" name="tab" value="rincian">

                    <div class="toolbar-field toolbar-search">
                        <label for="cari" class="sr-only">Cari nama atau kode pos</label>
                        <div class="input-wrapper">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                            <input type="text" id="cari" name="cari" class="form-control" value="<?= $kataKunci ?>" placeholder="Cari nama atau kode pos...">
                        </div>
                    </div>

                    <div class="toolbar-field">
                        <label for="kategori" class="sr-only">Filter kategori</label>
                        <select id="kategori" name="kategori" class="form-control">
                            <option value="Semua" <?= $kategoriPilih === 'Semua' ? 'selected' : '' ?>>Semua Kategori</option>
                            <?php foreach ($semuaKategori as $kat): ?>
                                <option value="<?= e($kat) ?>" <?= $kategoriPilih === $kat ? 'selected' : '' ?>>
                                    <?= e($kat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="toolbar-field">
                        <label for="cadangan" class="sr-only">Simulasi cadangan</label>
                        <select id="cadangan" name="cadangan" class="form-control">
                            <?php for ($persen = 0; $persen <= 20; $persen += 5): ?>
                                <option value="<?= $persen ?>" <?= $opsiCadangan === $persen ? 'selected' : '' ?>>
                                    Cadangan <?= $persen ?>%
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="toolbar-actions">
                        <button type="submit" class="pf-btn pf-btn-forest pf-btn-sm">Terapkan</button>
                        <?php if ($sedangFilter || $opsiCadangan !== 5): ?>
                            <a href="index.php?tab=rincian" class="pf-btn pf-btn-outline pf-btn-sm">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="clean-table">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Pos Pengeluaran</th>
                                <th>Kategori</th>
                                <th class="text-right">Jatah</th>
                                <th>Realisasi</th>
                                <th class="text-right">Sisa Efektif</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dataTersaring)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        Tidak ada pos yang sesuai dengan kata kunci "<strong><?= $kataKunci ?></strong>" pada kategori "<strong><?= e($kategoriPilih) ?></strong>".
                                        <div style="margin-top: 12px;">
                                            <a href="index.php?tab=rincian" class="pf-btn pf-btn-outline pf-btn-sm">Reset Filter</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dataTersaring as $pos):
                                    $rincian = olahRincianPos($pos, $persenCadangan);
                                    [$kelasStatus, , $barTone] = mapStatusBadge($rincian['status']);
                                    $metodeList = is_array($pos['metode_bayar'] ?? null) ? $pos['metode_bayar'] : [];
                                ?>
                                    <tr>
                                        <td><span class="code-badge"><?= e($pos['kode']) ?></span></td>
                                        <td>
                                            <div class="pos-title"><?= e($pos['nama_pos']) ?></div>
                                            <div class="pos-sub">
                                                <span><?= e($rincian['prioritas']) ?></span>
                                                <span>&middot;</span>
                                                <span><?= (int) $pos['frekuensi_transaksi'] ?>x transaksi</span>
                                            </div>
                                            <?php if (!empty($metodeList)): ?>
                                                <div class="method-tags">
                                                    <?php foreach ($metodeList as $metode): ?>
                                                        <span class="method-tag"><?= e($metode) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="cat-label"><?= e($pos['kategori']) ?></span></td>
                                        <td class="text-right num-col"><strong><?= formatRupiahUI((float) $pos['jatah_anggaran']) ?></strong></td>
                                        <td class="usage-box">
                                            <div class="usage-top">
                                                <span class="usage-amount num-col"><?= formatRupiahUI((float) $pos['terpakai']) ?></span>
                                                <span class="usage-pct num-col"><?= number_format($rincian['persen_pakai'], 1) ?>%</span>
                                            </div>
                                            <div class="cat-track">
                                                <div class="cat-fill <?= $barTone ?>" style="width: <?= min(100, round($rincian['persen_pakai'], 1)) ?>%;"></div>
                                            </div>
                                        </td>
                                        <td class="text-right num-col">
                                            <div class="eff-amount <?= $rincian['sisa_efektif'] < 0 ? 'is-negative' : 'is-positive' ?>">
                                                <?= formatRupiahUI($rincian['sisa_efektif']) ?>
                                            </div>
                                            <div class="eff-reserve">Cadangan <?= formatRupiahUI($rincian['dana_cadangan']) ?></div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $kelasStatus ?>">
                                                <span class="status-dot"></span>
                                                <?= e($rincian['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($dataTersaring)): ?>
                            <tfoot>
                                <tr>
                                    <td colspan="3">Total Rekapitulasi (<?= count($dataTersaring) ?> pos)</td>
                                    <td class="text-right num-col"><?= formatRupiahUI($totalJatah) ?></td>
                                    <td class="num-col"><?= formatRupiahUI($totalTerpakai) ?> (<?= number_format($persenSerapanFilter, 1) ?>%)</td>
                                    <td class="text-right num-col <?= $totalSisaEfektif < 0 ? 'is-negative' : 'is-positive' ?>">
                                        <?= formatRupiahUI($totalSisaEfektif) ?>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>