# Peta Jalan Perbaikan Laravel Sail

**Diperbarui:** 2026-11-23 · **Acuan kode:** commit `056da37` · **Lingkup:** panduan dan stub Laravel Sail di `docs/user-manual/laravel-sail.md`

---

## Ringkasan singkat

Panduan Laravel Sail kami masih berisi tiga berkas yang disalin langsung ke README: skrip `run_turso`, sebuah `Dockerfile`, dan `docker-compose.yml`. Isinya berasal dari Januari 2025, sementara Laravel Sail sekarang memakai PHP 8.5, Node 24, PostgreSQL 18, dan cara menjalankan container yang berbeda.

Lebih penting lagi: skrip lama memeriksa versi **alat installer**, lalu menjalankan perintah yang memperbarui **ekstensi libSQL**. Dua benda yang berbeda. Akibatnya installer dianggap "kedaluwarsa" terus-menerus, update dijalankan tiap kali container start, dan versinya tidak pernah berubah.

Rencana ini memperbaiki akar masalahnya, menyelaraskan konfigurasi dengan Sail versi sekarang, lalu merapikan dokumentasi agar pembaca tidak lagi menyalin berkas secara buta.

---

## Keputusan yang sudah disetujui pemilik proyek

1. **Semua tahap dikerjakan** — Fase 0 sampai Fase 4.
2. **Versi CLI installer di-pin lewat Composer di dalam Dockerfile.** Tidak menambah perintah `self-update` ke CLI.
3. **Ekstensi dimiliki image, bukan di-mount dari host.** Bind mount `./extensions:/opt/extensions` dihapus.
4. **Rencana ditulis di folder `plans/`** beserta tracker yang mudah dibaca orang umum (file ini).

---

## Status pengerjaan

Arti status: **BELUM MULAI** → **SEDANG JALAN** → **SELESAI**. Jika terhalang, tulis `TERHALANG: alasan singkat` di kolom Status.

| No | Apa yang dikerjakan | Dampak bagi pengguna | Prioritas | Status | Mulai setelah |
|----|---------------------|----------------------|-----------|--------|---------------|
| [001](001-verification-baseline.md) | Pasang gerbang tes otomatis dan uji perilaku instalasi | Perubahan pada Sail tidak lagi lolos diam-diam; ada perintah tes satu langkah | P1 | SELESAI | — |
| [002](002-sail-preflight.md) | Ganti pengecekan versi yang salah dengan pemeriksaan ekstensi yang gagal keras | Container tidak lagi menyala dalam keadaan ekstensi hilang; tidak ada unduhan tiap boot | P1 | SELESAI | 001 |
| [003](003-upstream-runtime-alignment.md) | Selaraskan Dockerfile dan compose dengan Sail 8.5; ekstensi di dalam image | Build mengikuti Sail masa kini, versi CLI tetap, tidak ada mount biner dari host | P1 | BELUM MULAI | 002 |
| [004](004-stub-ci-gates.md) | Jadikan pemeriksaan stub dan tautan sebagai bagian CI | Placeholder yang belum diganti dan tautan mati terdeteksi sebelum rilis | P2 | BELUM MULAI | 003 |
| [005](005-public-docs-sweep.md) | Rapikan dokumentasi publik: tautan, nama file, versi PHP, verifikasi, rollback | Pembaca baru tidak lagi menemui 404, nama file lama, atau janji yang menyesatkan | P2 | BELUM MULAI | 004 |

---

## Urutan pengerjaan

```
001  →  002  →  003  →  004  →  005
```

`002` harus mendahului `003`: menyalin Dockerfile Sail terbaru sebelum memutuskan siapa yang mengelola upgrade hanya akan memindahkan bug lama ke template baru. `004` dan `005` bisa dikerjakan bersamaan setelah `003`, tetapi tetap lebih aman berurutan.

Detail teknis, potongan kode, perintah verifikasi, dan syarat selesai ada di masing-masing file `00N-*.md`. File-file itu ditulis dalam bahasa Inggris agar konsisten dengan dokumentasi teknis repo; tracker ini sengaja memakai bahasa Indonesia agar mudah dipahami siapa pun.

---

## Aturan kerja (wajib dibaca sebelum mengeksekusi)

Lihat **[WORKFLOW.md](WORKFLOW.md)**. Ringkasannya:

```
rencana di-commit ke main → issue per plan → branch plan/00N-… → commit (Refs #N)
→ PR (Closes #N + referensi plan) → double check → merge
```

Tidak ada pekerjaan tanpa referensi issue dan plan. Jangan merge sebelum semua done criteria dan verification command di plan hijau. Temuan baru di luar scope → dibuat issue baru, bukan diselundupkan ke PR yang sedang berjalan. Salinan induk aturan ini ada di `~/fs-museum/agent-dir/WORKFLOW.md`.

---

## Cara memperbarui tracker ini

- Executor mengubah **kolom Status** barisnya sendiri setelah pekerjaan selesai.
- Jika ada temuan baru yang tidak masuk rencana, tambahkan baris baru dengan nomor berikutnya (`006`, dst.) dan jelaskan alasannya di ringkasan.
- Jika sebuah rencana dibatalkan, tulis `REJECTED: alasan` pada Status, jangan hapus barisnya.

---

## Temuan yang sengaja tidak dikejar

Dicatat agar tidak diaudit ulang tanpa alasan baru:

- **Menambah perintah `self-update` ke CLI.** Ditolak: pemilik memilih pin Composer di Dockerfile, yang deterministik dan tidak menambah permukaan keamanan baru.
- **Bind mount biner ekstensi dari host dengan mode read-only.** Ditolak: pemilik memilih ekstensi dimiliki image, sehingga izin dan owner hasil build benar-benar berlaku.
- **Menyalin seluruh runtime Sail (termasuk `php.ini`) ke repo.** Ditolak: cukup simpan overlay tipis; `php.ini` tetap memakai berkas yang dipublikasikan `sail:publish`.
- **Hardening path metadata → perintah sudo di `BaseInstaller.php:444-451`.** Ditunda: ini masalah installer global, bukan khusus Sail, dan menyentuh install/uninstall semua pengguna.
- **Memakai `shellcheck` di CI.** Ditolak: tidak terpasang di lingkungan kerja; `bash -n` sudah cukup untuk menangkap kesalahan sintaks tanpa dependency baru.
- **Build Jekyll lokal.** Tidak mungkin dilakukan di mesin ini (`jekyll` tidak tersedia); pembuktian dilakukan oleh workflow `docs.yml` di CI.

---

## Batas audit

Yang **tidak** diperiksa dalam penyusunan rencana ini: Docker build sungguhan dengan jaringan, render situs Jekyll lokal, subsystem `token:*` / `sqld:*` / sertifikat (kecuali saat bersentuhan dengan path instalasi), serta platform Windows dan FrankenPHP. Fakta upstream diambil dari `laravel/sail` cabang `1.x` dan dokumentasi Laravel 13 yang di-fetch pada 2026-11-23; keduanya dapat berubah lagi, sehingga setiap plan meminta pemeriksaan ulang sebelum dieksekusi.
