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
        'message' => 'Anda telah berhasil logout dari sistem.',
    ];
}

$errors = [];
$oldUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['umum'] = 'Token keamanan (CSRF) tidak valid. Muat ulang halaman dan coba lagi.';
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

            $errors['umum'] = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — PocketFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body <?= $temaAktif === 'dark' ? 'theme-dark' : '' ?>">
    <main class="auth-card">
        <div class="auth-header">
            <div class="brand-icon" style="margin: 0 auto 12px;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>
                </svg>
            </div>
            <h1>Masuk ke PocketFlow</h1>
            <p>Sistem Pengelolaan & Evaluasi Uang Saku Bulanan</p>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors['umum'])): ?>
            <div class="alert alert-danger"><?= e($errors['umum']) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" novalidate class="stack-form">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" value="<?= e($oldUsername) ?>" placeholder="Masukkan username">
                <?php if (isset($errors['username'])): ?>
                    <span class="field-error"><?= e($errors['username']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" placeholder="Masukkan password">
                <?php if (isset($errors['password'])): ?>
                    <span class="field-error"><?= e($errors['password']) ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 6px;">Masuk</button>
        </form>
    </main>
</body>
</html>