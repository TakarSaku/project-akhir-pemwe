# PocketFlow — Pengelolaan Uang Saku Bulanan

Aplikasi web berbasis PHP untuk mencatat, mengalokasikan, dan mengevaluasi pos pengeluaran uang saku bulanan mahasiswa. Pengembangan pada **Pertemuan 11** melanjutkan modul logika dan gaya dasar dari **Pertemuan 10** dengan menambahkan autentikasi pengguna, manajemen sesi, perlindungan CSRF, pola _Post-Redirect-Get_ (PRG), validasi sisi server, serta penyimpanan preferensi tema melalui _cookie_.

---

## Fitur Utama

- **Autentikasi & Proteksi Sesi**
  - Verifikasi kredensial menggunakan `password_hash()` dan `password_verify()`.
  - Pembaruan ID sesi saat login (`session_regenerate_id(true)`) serta parameter _cookie_ sesi aman (`httponly`, `samesite=Lax`).
  - Pembatasan akses halaman dasbor dan form tambah data bagi pengguna yang belum login (`requireLogin()`).
- **Keamanan Form & Preferensi Cookie**
  - Validasi token **CSRF** (`hash_equals()`) pada seluruh aksi `POST` (login, logout, tambah pos, dan pengubahan tema).
  - Penyimpanan preferensi tampilan (_Light Mode_ / _Dark Mode_) ke dalam _cookie_ `pocketflow_theme` selama 30 hari dengan validasi _whitelist_.
- **Dasbor Dua Tab (`Ringkasan` & `Rincian Alokasi`)**
  - **Tab Ringkasan:** Menampilkan sisa aktual, simulasi dana darurat (`0%`–`20%`), sisa efektif, proporsi serapan per kategori, progres realisasi tiap kelompok pos, serta daftar pos berstatus _Waspada_ atau _Overbudget_.
  - **Tab Rincian Alokasi:** Menyediakan pencarian kata kunci, filter kategori, dan simulasi cadangan via metode `GET` yang diolah menggunakan fungsi `olahRincianPos()` dari `pertemuan10/function.php`.
- **Tambah Pos Pengeluaran (`POST` + Validasi + PRG)**
  - Formulir penambahan pos baru ke dalam `$_SESSION['pos_uang_saku']` dengan input array `metode_bayar[]`.
  - Validasi sisi server dengan pesan galat spesifik per _field_, _sticky form_ saat terjadi kesalahan input, serta pengalihan PRG disertai _flash message_ satu kali tampil.

---

## Struktur Direktori

Pastikan folder `pertemuan10` dan `pertemuan11` berada sejajar di dalam direktori utama repositori karena `pertemuan11` memuat fungsi perhitungan dan CSS dasar dari `pertemuan10`:

```text
project/
├── pertemuan10/
│   ├── function.php        # Modul fungsi perhitungan serapan, sisa efektif, dan status pos
│   └── style.css           # Gaya dasar komponen tabel, badge status, dan tipografi
└── pertemuan11/
    ├── auth.php            # Konfigurasi session aman, CSRF token, flash message, & cookie tema
    ├── data.php            # Dataset awal pos pengeluaran uang saku (termasuk array metode_bayar)
    ├── index.php           # Halaman utama dasbor (tab Ringkasan & Rincian Alokasi + filter GET)
    ├── login.php           # Halaman masuk pengguna dengan proteksi CSRF & session regeneration
    ├── logout.php          # Handler keluar sesi via POST + CSRF dan pembersihan cookie sesi
    ├── process.php         # Pengolahan filter GET, pencarian, dan agregasi rekapitulasi anggaran
    ├── style.css           # Ekstensi desain Editorial Forest, tata letak responsif, & dark mode
    ├── tambah.php          # Formulir tambah pos baru (POST, validasi server-side, & PRG)
    ├── validation.php      # Aturan validasi sisi server dan sanitasi input form
    └── README.md           # Dokumentasi proyek Pertemuan 11
```

---

## Cara Menjalankan Aplikasi

1. **Prasyarat:** Terpasang **PHP 8.1 atau lebih baru** pada sistem.
2. Buka terminal di **direktori utama repositori** (folder yang menaungi `pertemuan10` dan `pertemuan11`), lalu jalankan _built-in web server_ PHP:
   ```bash
   php -S localhost:8000
   ```
3. Buka peramban web (_browser_) dan akses alamat berikut:
   ```text
   http://localhost:8000/pertemuan11/login.php
   ```

> **Catatan:** Jalankan perintah `php -S` dari direktori induk (bukan dari dalam folder `pertemuan11`) agar jalur relatif `../pertemuan10/function.php` dan `../pertemuan10/style.css` dapat dimuat dengan benar.

---

## Akun Uji (Kredensial Demo)

Gunakan salah satu akun berikut untuk masuk ke dalam sistem (_Username_ bersifat _case-sensitive_ sesuai `getDaftarPengguna()` di `auth.php`):

| No  | Username                               | Password        | Nama Tampilan                          |
| :-- | :------------------------------------- | :-------------- | :------------------------------------- |
| 1   | `Gihan Dimas Ardiyan`                  | `mahasiswa1`    | `Gihan Dimas Ardiyan`                  |
| 2   | `Alin Rahma Herdiyanti`                | `mahasiswa12`   | `Alin Rahma Herdiyanti`                |
| 3   | `Rizky Aulia Zahra`                    | `mahasiswa123`  | `Rizky Aulia Zahra`                    |
| 4   | `Muhammad Hatta Mabarun Khalifatullah` | `mahasiswa1234` | `Muhammad Hatta Mabarun Khalifatullah` |

> Kredensial di atas hanya digunakan untuk keperluan pengujian lokal dan diverifikasi menggunakan `password_verify()` pada sisi server.

---

## Skenario Pengujian Fitur

1. **Proteksi Halaman:** Akses `http://localhost:8000/pertemuan11/index.php` sebelum login — sistem otomatis mengalihkan ke `login.php` dengan pesan peringatan.
2. **Autentikasi & Sesi:** Login menggunakan username `Gihan Dimas Ardiyan` dan password `mahasiswa1`, pastikan berhasil masuk ke tab **Ringkasan** dan muncul notifikasi selamat datang.
3. **Simulasi & Filter `GET`:** Ubah persentase cadangan (`0%`–`20%`) pada tab **Ringkasan**, lalu buka tab **Rincian Alokasi** untuk menguji pencarian nama/kode pos dan filter kategori.
4. **Validasi `POST` & PRG:** Klik **+ Tambah Pos**, kirim formulir dalam keadaan kosong untuk memastikan validasi server-side dan _sticky form_ bekerja, lalu isi data valid dan simpan hingga kembali ke tabel rincian dengan pesan sukses.
5. **Persistensi Cookie Tema:** Klik ikon bulan/matahari di bilah navigasi atas untuk beralih antara mode terang dan gelap, lalu muat ulang halaman untuk memastikan preferensi tersimpan.
6. **Logout Aman:** Klik tombol **Keluar** pada bilah navigasi atas untuk menghancurkan sesi dan kembali ke halaman login.

---
