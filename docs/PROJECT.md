# Dokumentasi Proyek Smart School

Dokumen ini menjelaskan alur menjalankan proyek, instalasi ekstensi, struktur kode, database, serta gaya UI.

## Ringkasan

- Framework: CodeIgniter (struktur `system/` dan `application/`).
- Frontend theme untuk publik ada di `application/views/themes/`.
- Backend/admin assets ada di `backend/themes/` dan `backend/rtl/`.
- Database schema tersedia di `application/migrations/001_create_schema.sql`.
- Seeder dummy data tersedia via CLI.

## Kebutuhan Sistem

Mengacu ke `backup/Installasi/requirements.php`:

- PHP 8.1+
- Extensions: `mysqli`, `gd`, `curl`, `mbstring`, `zip`
- `allow_url_fopen` harus aktif
- File/folder yang harus writable:
  - `application/config/config.php`
  - `application/config/database.php`
  - `temp/`
  - `uploads/`

## Cara Install (Local)

1. Letakkan project di htdocs XAMPP:
   - Contoh path: `C:\xampp\htdocs\smart_school_src`
2. Buat database MySQL:
   - Default nama DB di config: `ci_smart_scool`
3. Jalankan migrasi schema:
   - `php index.php cli/migrate/latest`
4. Update koneksi DB:
   - File: `application/config/database.php`
   - Sesuaikan `hostname`, `username`, `password`, `database`
5. Update base URL:
   - File: `application/config/config.php`
   - `base_url` default: `http://localhost/smart_school_src/`
6. Pastikan Apache mod_rewrite aktif (karena `index_page` kosong dan `.htaccess` dipakai).
7. Pastikan permission writable:
   - `application/config/config.php`
   - `application/config/database.php`
   - `temp/`
   - `uploads/`
8. Start Apache + MySQL di XAMPP.
9. Buka URL:
   - `http://localhost/smart_school_src/`

Catatan:
- Folder `backup/Installasi/` adalah legacy installer dan tidak diperlukan.

## API Keys (Non-ENV)

Semua API key disimpan di database dan dapat diatur dari UI:

- Menu: `System Settings > Captcha` (bagian **API Keys**)

## Cara Menjalankan (Flow)

### Web

- Entry point: `index.php`
- Routing utama: `application/config/routes.php`
  - Default controller: `welcome/index`
  - Route cron: `cron/index/{secret}`
    - `secret` ada di `sch_settings.cron_secret_key`
- `index.php` default ENVIRONMENT: `production`
  - Ubah ke `development` saat debug.

### CLI (Seeder)

Seeder dummy data:

```bash
php index.php cli/seed dummy
php index.php cli/seed dummy fresh
```

### CLI (Migrasi)

Migrasi schema:

```bash
php index.php cli/migrate/latest
php index.php cli/migrate/fresh
```

File terkait:
- `application/controllers/cli/Seed.php`
- `application/libraries/DummyDataSeeder.php`

Kredensial dummy yang dibuat:
- Staff: password `StaffPass!23`
- Student: password `Siswa123!`
- Parent: password `Parent123!`

## Struktur Kode

Struktur utama CodeIgniter:

- `application/controllers/`
  - Logic request/route.
  - Admin biasanya ada di `application/controllers/admin/`.
- `application/models/`
  - Query dan akses database.
- `application/views/`
  - Tampilan admin dan front.
  - Front themes di `application/views/themes/`.
- `application/libraries/`
  - Library tambahan (contoh: `DummyDataSeeder`).
- `application/helpers/`
  - Helper functions.
- `system/`
  - Core CodeIgniter.

Catatan:
- Class custom CI menggunakan prefix `MY_`.
- Autoload ada di `application/config/autoload.php`.
- Konstanta tambahan ada di `application/config/ss-constants.php`.

## Database

### Schema

- Schema utama: `application/migrations/001_create_schema.sql`
- Migrasi utama: `application/migrations/001_init_schema.php`

### Konfigurasi Koneksi

File: `application/config/database.php`

- Default group: `default`
- DB driver: `mysqli`
- `db_debug` default `false` (ubah ke `true` saat dev).
- Tersedia mekanisme multi-branch:
  - Jika tabel `multi_branch` ada dan berisi data verified,
    maka koneksi tambahan dibuat otomatis.

### Tabel Kunci (high level)

- Pengguna dan akses:
  - `users`, `roles`, `roles_permissions`, `staff_roles`
- Akademik:
  - `classes`, `sections`, `class_sections`
  - `subjects`, `subject_groups`, `subject_group_subjects`
  - `subject_timetable`, `lesson`, `topic`, `subject_syllabus`
- Siswa dan sesi:
  - `students`, `student_session`, `student_attendences`
  - `student_subject_attendances`
- Keuangan:
  - `fee_groups`, `feetype`, `fee_session_groups`
  - `student_fees_master`, `student_fees_deposite`
  - `income`, `income_head`, `expenses`, `expense_head`
- Ujian:
  - Legacy: `exams`, `exam_schedules`
  - Exam group: `exam_groups`, `exam_group_class_batch_exams`,
    `exam_group_class_batch_exam_subjects`,
    `exam_group_class_batch_exam_students`,
    `exam_group_exam_results`
- Online exam:
  - `onlineexam`, `onlineexam_questions`,
    `onlineexam_students`, `onlineexam_student_results`
- Perpustakaan:
  - `books`, `libarary_members`, `book_issues`
- Transport:
  - `transport_route`, `vehicles`, `vehicle_routes`
  - `pickup_point`, `route_pickup_point`
  - `transport_feemaster`, `student_transport_fees`
- Hostel:
  - `hostel`, `hostel_rooms`, `room_types`
- Inventori:
  - `item_category`, `item_store`, `item_supplier`
  - `item`, `item_stock`, `item_issue`
- Konten/komunikasi:
  - `send_notification`, `share_contents`, `share_content_for`

### Session

Konfigurasi session ada di `application/config/config.php`:

- `sess_driver`: `files`
- `sess_save_path`: mengikuti konfigurasi di `application/config/config.php`

## Styling dan UI

### Backend (Admin)

Assets admin berada di:

- `backend/themes/material_pink/`
- `backend/themes/yellow/`
- `backend/rtl/` (RTL styles)

Isi assets:

- CSS: `bootstrap.min.css`, `style.css`, `font-awesome.min.css`
- JS: `jquery.min.js`, `bootstrap.min.js`, `custom.js`

Tema admin yang aktif biasanya mengikuti setting di tabel `sch_settings.theme`.

### Frontend (Public Site)

Tema front berada di:

- `application/views/themes/bold_blue/`
- `application/views/themes/darkgray/`
- `application/views/themes/default/`
- `application/views/themes/material_pink/`
- `application/views/themes/shadow_white/`
- `application/views/themes/yellow/`

Pengaturan front CMS ada di:
- `front_cms_settings` (database)

## Storage dan Uploads

Folder utama untuk file upload:

- `uploads/`

Pastikan folder ini writable oleh web server. Sub-folder digunakan untuk
gambar siswa, dokumen, materi, kartu, logo, dan asset lain.

## Tips Debugging

- Aktifkan `db_debug` di `application/config/database.php` saat dev.
- Ubah `ENVIRONMENT` di `index.php` ke `development` untuk error detail.
- Periksa `application/logs/` jika logging aktif.

## Quick Checklist Setelah Setup

- Pastikan DB sudah terimport dan `base_url` benar.
- Jika import DB manual, pastikan akun admin sudah dibuat (installer biasanya membuat).
- Jalankan seeder dummy untuk eksplorasi fitur.
- Cek permission `uploads/` dan `temp/`.
