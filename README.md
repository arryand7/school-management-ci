# Smart School

Dokumentasi ini menjelaskan alur menjalankan aplikasi, kebutuhan extension, struktur kode, database, dan style/UI.

## Ringkasan

- Framework: CodeIgniter 3 (struktur `system/` + `application/`).
- Admin/backoffice assets ada di `backend/`.
- Frontend themes ada di `application/views/themes/`.
- Schema database dikelola lewat migrasi SQL (`application/migrations/001_create_schema.sql`).
- CLI tersedia untuk migrasi dan dummy data.

## Kebutuhan Sistem

Mengacu ke `backup/Installasi/requirements.php`:

- PHP 8.1+
- Extensions: `mysqli`, `gd`, `curl`, `mbstring`, `zip`
- `allow_url_fopen` aktif
- Web server (Apache/Nginx) dengan URL rewrite aktif

### Cara mengaktifkan extension

Contoh umum di `php.ini` (XAMPP/Apache):

```
; pastikan baris berikut tidak diawali tanda ;
extension=mysqli
extension=gd
extension=curl
extension=mbstring
extension=zip
allow_url_fopen=On
```

Restart Apache setelah mengubah `php.ini`.

## Cara Menjalankan (Flow)

### 1) Clone proyek

```
cd /path/to/htdocs

git clone <repo-url> smart_school_src
```

### 2) Buat database

Buat database kosong (contoh: `ci_smart_scool`).

### 3) Konfigurasi koneksi DB

Edit `application/config/database.php`:

- `hostname`, `username`, `password`, `database`
- `dbdriver` tetap `mysqli`

### 4) Konfigurasi base URL

Edit `application/config/config.php`:

- `base_url` ke `http://localhost/smart_school_src/`

### 5) Permission file/folder

Pastikan path berikut writable:

- `application/config/config.php`
- `application/config/database.php`
- `application/logs/`
- `temp/`
- `uploads/`

### 6) Jalankan migrasi

```
php index.php cli/migrate/latest
```

Untuk reset ulang schema:

```
php index.php cli/migrate/fresh
```

### 7) (Opsional) Seed dummy data

```
php index.php cli/seed dummy
php index.php cli/seed dummy fresh
```

Seeder membuat akun staff, siswa, dan orang tua dengan password default:

- Staff: `StaffPass!23`
- Student: `Siswa123!`
- Parent: `Parent123!`

Email/username dapat dilihat di tabel `staff`, `students`, dan `users` setelah seeding.

### 8) Akses aplikasi

Start Apache + MySQL, lalu buka:

- `http://localhost/smart_school_src/`

Halaman default akan menuju login.

Catatan:
- Folder `backup/Installasi/` adalah legacy installer, tidak dibutuhkan untuk setup baru.

## API Keys (Non-ENV)

Semua API key disimpan di database dan bisa diatur dari UI:

- Menu: `System Settings > Captcha` (bagian **API Keys**)
- Isi Google Maps API key, Firebase service account JSON, Yandex Translate key, dan Paymongo keys sesuai kebutuhan.
Catatan: jangan menyimpan service account JSON langsung di file project.

## Perintah CLI

Migrasi:

```
php index.php cli/migrate/latest
php index.php cli/migrate/fresh
php index.php cli/migrate/version 1
```

Seed:

```
php index.php cli/seed dummy
php index.php cli/seed dummy fresh
```

## Struktur Kode

Struktur utama CodeIgniter:

- `application/controllers/` - controller utama
- `application/controllers/admin/` - controller admin/backoffice
- `application/controllers/cli/` - CLI commands
- `application/models/` - query dan akses database
- `application/views/` - template UI
- `application/libraries/` - library custom
- `application/helpers/` - helper functions
- `system/` - core CodeIgniter

Catatan:
- Custom core CI menggunakan prefix `MY_`.
- Autoload ada di `application/config/autoload.php`.
- Konstanta tambahan ada di `application/config/ss-constants.php`.

## Database

### Schema

- Source schema: `application/migrations/001_create_schema.sql`
- Migrasi utama: `application/migrations/001_init_schema.php`

### Konfigurasi koneksi

File: `application/config/database.php`

- Default group: `default`
- Driver: `mysqli`
- `db_debug` default `false` (ubah ke `true` saat debugging)

### Multi-branch (opsional)

Aplikasi mendukung multi-branch bila tabel `multi_branch` berisi data verified.

## Styling dan UI

### Backend (Admin)

Assets admin:

- `backend/themes/` (tema admin)
- `backend/rtl/` (RTL styles)
- Override custom: `backend/dist/css/custom_style.css`

Tema admin aktif mengikuti setting di tabel `sch_settings.theme`.

### Frontend (Public)

Theme public tersedia di:

- `application/views/themes/bold_blue/`
- `application/views/themes/darkgray/`
- `application/views/themes/default/`
- `application/views/themes/material_pink/`
- `application/views/themes/shadow_white/`
- `application/views/themes/yellow/`

Pengaturan CMS front ada di tabel `front_cms_settings`.

## Catatan Development

- ENV default ada di `index.php` (ubah ke `development` saat debugging).
- Session menggunakan driver `files` dan path pada `application/config/config.php`.
