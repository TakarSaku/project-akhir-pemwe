<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$temaAktif = getThemePreference();
$flash = getFlash();
if ($flash === null && ($_GET['status'] ?? '') === 'logout') {
    $flash = [
        'type'    => 'success',
        'message' => 'Anda telah berhasil keluar dari sesi.',
    ];
}

$errors = [];
$oldUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['umum'] = 'Token keamanan (CSRF) tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $oldUsername = $username;

        if ($username === '') {
            $errors['username'] = 'Username wajib diisi.';
        }
        if ($password === '') {
            $errors['password'] = 'Password wajib diisi.';
        }

        if (empty($errors)) {
            $daftarPengguna = getDaftarPengguna();
            $akun = $daftarPengguna[$username] ?? null;

            if ($akun !== null && password_verify($password, $akun['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'username' => $username,
                    'nama'     => $akun['nama'],
                ];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                setFlash('success', 'Selamat datang kembali, ' . $akun['nama'] . '!');
                session_write_close();
                header('Location: index.php');
                exit;
            }

            $errors['umum'] = 'Username atau password yang Anda masukkan salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — PocketFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page <?= $temaAktif === 'dark' ? 'theme-dark' : '' ?>">

    <div class="auth-frame">
        <!-- Panel Kiri: Bezel Card Editorial (Tanpa Angka Anggaran) -->
        <aside class="auth-showcase-wrap" aria-label="Pengenalan PocketFlow">
            <div class="auth-showcase-card">
                <div class="showcase-copy">
                    <h1 class="showcase-title">
                        Kelola uang saku<br>
                        lebih terarah<br>
                        <span class="showcase-highlight">
                            setiap bulan.
                            <svg class="showcase-underline" viewBox="0 0 220 14" fill="none" aria-hidden="true">
                                <path d="M3 10.5C64 3.5 154 2 217 8.5" stroke="#a3e654" stroke-width="4" stroke-linecap="round"/>
                            </svg>
                        </span>
                    </h1>
                    <p class="showcase-subtitle">
                        Catat aktivitas harian dan jaga ritme finansialmu dalam satu ruang kerja yang ringkas.
                    </p>
                </div>

                <!-- Artwork Geometris Minimalis (Tanpa Data/Anggaran) -->
                <div class="showcase-artwork" aria-hidden="true">
                    <svg viewBox="0 0 420 230" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="330" cy="195" r="135" fill="rgba(163, 230, 84, 0.12)"/>
                        <circle cx="330" cy="195" r="90" stroke="rgba(163, 230, 84, 0.22)" stroke-width="1.5"/>

                        <!-- Siluet Kartu Belakang -->
                        <rect x="64" y="68" width="236" height="142" rx="22" transform="rotate(-6 64 68)" fill="rgba(255, 255, 255, 0.08)" stroke="rgba(255, 255, 255, 0.14)"/>

                        <!-- Siluet Kartu Utama -->
                        <rect x="108" y="88" width="248" height="148" rx="22" fill="rgba(255, 255, 255, 0.12)" stroke="rgba(255, 255, 255, 0.22)"/>
                        <rect x="134" y="114" width="38" height="26" rx="7" fill="#a3e654"/>
                        <circle cx="318" cy="127" r="11" fill="rgba(255, 255, 255, 0.28)"/>
                        <circle cx="303" cy="127" r="11" fill="rgba(163, 230, 84, 0.55)"/>
                        <rect x="134" y="168" width="112" height="10" rx="5" fill="rgba(255, 255, 255, 0.34)"/>
                        <rect x="134" y="188" width="72" height="8" rx="4" fill="rgba(255, 255, 255, 0.18)"/>
                        <rect x="278" y="180" width="52" height="16" rx="8" fill="rgba(163, 230, 84, 0.28)"/>
                    </svg>
                </div>
            </div>
        </aside>

        <!-- Panel Kanan: Form Login Terpusat & Bersih -->
        <main class="auth-form-pane">
            <div class="auth-form-box">
                <div class="auth-brand-center">
                    <span class="auth-brand-badge" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/>
                            <path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/>
                            <path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>
                        </svg>
                    </span>
                    <span class="auth-brand-title">PocketFlow</span>
                </div>

                <div class="auth-heading-center">
                    <h2>Selamat Datang</h2>
                    <p>Silakan masuk ke akun Anda</p>
                </div>

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

                <form method="POST" action="login.php" novalidate class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

                    <div class="auth-field">
                        <label for="username" class="sr-only">Username</label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            autocomplete="username"
                            class="auth-input <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                            value="<?= e($oldUsername) ?>"
                            placeholder="Username"
                            aria-invalid="<?= isset($errors['username']) ? 'true' : 'false' ?>"
                            <?= isset($errors['username']) ? 'aria-describedby="err-username"' : '' ?>
                        >
                        <?php if (isset($errors['username'])): ?>
                            <span id="err-username" class="field-error"><?= e($errors['username']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="auth-field">
                        <label for="password" class="sr-only">Password</label>
                        <div class="auth-password-wrap">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                autocomplete="current-password"
                                class="auth-input <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                placeholder="Password"
                                aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>"
                                <?= isset($errors['password']) ? 'aria-describedby="err-password"' : '' ?>
                            >
                            <button type="button" class="auth-eye-btn" id="togglePass" aria-label="Tampilkan password" aria-pressed="false">
                                <svg id="iconEyeOpen" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg id="iconEyeClosed" class="is-hidden" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                                    <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c6.5 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                                    <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                                    <line x1="2" x2="22" y1="2" y2="22"/>
                                </svg>
                            </button>
                        </div>
                        <?php if (isset($errors['password'])): ?>
                            <span id="err-password" class="field-error"><?= e($errors['password']) ?></span>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="auth-submit">Masuk</button>
                </form>

                <p class="auth-bottom-note">
                    PocketFlow &bull; Pengelolaan Uang Saku Bulanan
                </p>
            </div>
        </main>
    </div>

    <script>
        const toggleBtn = document.getElementById('togglePass');
        const passInput = document.getElementById('password');
        const iconOpen = document.getElementById('iconEyeOpen');
        const iconClosed = document.getElementById('iconEyeClosed');

        if (toggleBtn && passInput && iconOpen && iconClosed) {
            toggleBtn.addEventListener('click', () => {
                const isHidden = passInput.type === 'password';
                passInput.type = isHidden ? 'text' : 'password';
                iconOpen.classList.toggle('is-hidden', isHidden);
                iconClosed.classList.toggle('is-hidden', !isHidden);
                toggleBtn.setAttribute('aria-pressed', String(isHidden));
                toggleBtn.setAttribute('aria-label', isHidden ? 'Sembunyikan password' : 'Tampilkan password');
            });
        }
    </script>
</body>
</html>