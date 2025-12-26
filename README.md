# Smart School Dummy Data Seeder

Project ini kini memiliki perintah CLI untuk mengisi data contoh sehingga seluruh modul bisa langsung dicoba tanpa input manual.

## Cara pakai

```bash
php index.php cli/seed dummy
```

- Tambahkan argumen `fresh` untuk menghapus data dummy yang pernah disuntik sebelumnya dan mengulang dari awal:

  ```bash
  php index.php cli/seed dummy fresh
  ```

## Ringkasan data yang dibuat

Seeding akan menambahkan data berikut (dengan keterkaitan antar tabel sudah dijaga):

- Kategori siswa, rumah (house), kelas, dan rombongan belajar.
- Penugasan wali kelas, subject group, jadwal pelajaran, dan lesson plan.
- Guru/staf beserta kehadiran, siswa beserta akun login, akun orang tua, dan sesi kelas.
- Kehadiran siswa (harian + per mata pelajaran) serta tugas/penilaian.
- Ujian (legacy schedule + exam group), bank soal, online exam, dan hasilnya.
- Struktur biaya SPP berikut contoh pembayaran.
- Perpustakaan, transport (rute/vehicle/pickup/fees), dan hostel.
- Inventori, pemasukan/pengeluaran, event, timeline, dan pengumuman.

Semua data dummy tercatat pada tabel `dummy_seed_registry` sehingga aman dibersihkan saat menjalankan mode `fresh`.
