<?php
declare(strict_types=1);

// Konfigurasi cookie session yang aman sebelum session_start()
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Helper escaping XSS yang aman terhadap null
function e(?string $teks): string
{
    return htmlspecialchars((string) ($teks ?? ''), ENT_QUOTES, 'UTF-8');
}

// Inisialisasi data awal ke $_SESSION
function initSessionData(): void
{
    if (!isset($_SESSION['pos_uang_saku']) || !is_array($_SESSION['pos_uang_saku'])) {
        require __DIR__ . '/data.php';
        $_SESSION['pos_uang_saku'] = $posUangSaku;
    }
}

// Daftar akun pengguna dengan password_hash()
function getDaftarPengguna(): array
{
    return [
        'Gihan Dimas Ardiyan' => [
            'nama'          => 'Gihan Dimas Ardiyan',
            'password_hash' => password_hash('mahasiswa1', PASSWORD_DEFAULT),
        ],
        'Alin Rahma Herdiyanti' => [
            'nama'          => 'Alin Rahma Herdiyanti',
            'password_hash' => password_hash('mahasiswa12', PASSWORD_DEFAULT),
        ],
        'Rizky Aulia Zahra' => [
            'nama'          => 'Rizky Aulia Zahra',
            'password_hash' => password_hash('mahasiswa123', PASSWORD_DEFAULT),
        ],
        'Muhammad Hatta Mabarun Khalifatullah' => [
            'nama'          => 'Muhammad Hatta Mabarun Khalifatullah',
            'password_hash' => password_hash('mahasiswa1234', PASSWORD_DEFAULT),
        ],
    ];
}

// Token CSRF untuk semua form POST
function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Proteksi halaman (memastikan struktur sesi user lengkap)
function isLoggedIn(): bool
{
    return isset($_SESSION['user'])
        && is_array($_SESSION['user'])
        && isset($_SESSION['user']['username'], $_SESSION['user']['nama']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('warning', 'Silakan login terlebih dahulu untuk mengakses dasbor uang saku.');
        session_write_close();
        header('Location: login.php');
        exit;
    }
}

function getCurrentUser(): ?array
{
    return isLoggedIn() ? $_SESSION['user'] : null;
}

// Flash message untuk pola PRG (Post-Redirect-Get)
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

// Cookie preferensi tema (httponly, samesite, validasi whitelist)
const TEMA_VALID = ['light', 'dark'];

function getThemePreference(): string
{
    $cookieTema = $_COOKIE['pocketflow_theme'] ?? 'light';
    return in_array($cookieTema, TEMA_VALID, true) ? $cookieTema : 'light';
}

function setThemePreference(string $tema): bool
{
    if (!in_array($tema, TEMA_VALID, true)) {
        return false;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('pocketflow_theme', $tema, [
        'expires'  => time() + (86400 * 30),
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['pocketflow_theme'] = $tema;
    return true;
}