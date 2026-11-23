# Workflow Kedapan — Aturan Kerja Tetap

Salinan aturan kerja di repo ini. Induknya ada di
`~/fs-museum/agent-dir/WORKFLOW.md`; kalau keduanya beda, induk yang berlaku
sampai diselaraskan. Berlaku untuk semua session dan executor berikutnya.

## Prinsip

Setiap pekerjaan harus punya jejak yang bisa ditelusuri:

```
rencana tertulis → issue → branch → commit → PR → double check → merge
```

Tidak ada pekerjaan "yang langsung jadi" tanpa referensi. Kalau sebuah commit,
PR, atau merge tidak bisa dijelaskan dengan merujuk ke plan atau issue, berarti
alur kerja sedang dilanggar.

## Urutan wajib

### 1. Rencana dulu, commit sebelum implementasi

- Untuk pekerjaan kompleks, tulis plan ke `plans/NNN-slug.md` beserta tracker
  `plans/README.md`.
- **Commit plan ke `main` sebelum implementasi dimulai.** Ini mengunci scope,
  keputusan, dan done criteria supaya tidak berubah di tengah jalan.

### 2. Sebelum mengerjakan: buat issue

- Satu issue per plan. Gunakan `gh issue create`.
- Judul: `Plan NNN — <judul pendek>`.
- Body WAJIB memuat:
  - tautan langsung ke file plan, mis. `plans/001-verification-baseline.md`;
  - ringkasan 3–7 poin yang akan dikerjakan (diambil dari plan, bukan narasi
    baru);
  - dependensi (plan/issue mana yang harus selesai lebih dulu);
  - done criteria yang bisa dicek;
  - catatan "batas audit"/hal yang sengaja tidak dikerjakan, kalau ada.
- Issue dibuat **sebelum** branch dan sebelum menyentuh source.

### 3. Branch per plan

- Jangan kerjakan langsung di `main`.
- Nama branch: `plan/NNN-<slug>` (contoh: `plan/001-verification-baseline`).
- Satu branch = satu plan = satu PR. Kalau plan terlalu besar untuk satu PR,
  pecah jadi PR berurutan dalam branch yang sama dan sebutkan di issue.

### 4. Commit

- Pesan conventional commit: `test: ...`, `ci: ...`, `fix(docs): ...`, `docs: ...`.
- Body commit menyertakan `Refs #<issue>`.
- Jangan commit perbaikan hasil double check sebagai commit tanpa pesan — tetap
  pakai format yang sama.

### 5. Pull request

- Buat dengan `gh pr create --base main --head <branch>`.
- Body PR WAJIB memuat, dalam urutan ini:
  1. `Closes #<issue>`;
  2. referensi plan: `plans/NNN-....md`;
  3. ringkasan perubahan (faktual, bukan klaim marketing);
  4. perintah verifikasi yang **benar-benar dijalankan** beserta hasilnya;
  5. daftar eksplisit apa yang **tidak** dijalankan dan alasannya;
  6. perubahan tracker status yang akan dilakukan setelah merge.
- Kalau PR belum siap di-merge, tandai draft. Jangan minta review sebelum
  verification gate hijau.

### 6. Double check (sebelum merge)

Lakukan semua ini, jangan hanya satu:

- [ ] Baca ulang **Done criteria** di plan, centang satu per satu.
- [ ] Jalankan ulang **semua** verification command dari tabel plan di PR
      final, bukan hanya yang terakhir dijalankan.
- [ ] `git diff main...HEAD` — baca diffnya, pastikan tidak ada file di luar
      in-scope plan.
- [ ] `git status` — tidak ada artefak audit yang ikut.
- [ ] Cek CI: `gh pr checks <PR> --watch` atau `gh run list`; harus hijau.
- [ ] Cek tidak ada credential/token/.env yang ikut ter-commit.
- [ ] Cek tracker `plans/README.md`: status masih perlu di-update setelah
      merge (lakukan di commit kecil terpisah di `main`).
- [ ] Cek apakah ada temuan baru selama pengerjaan.

Kalau ada yang terlewat: **perbaiki di branch yang sama**, commit lagi, PR akan
ter-update otomatis. Ulangi double check. Jangan merge sambil "nanti diperbaiki".

### 7. Merge

Merge hanya kalau:

- semua done criteria terpenuhi;
- semua verification command hijau;
- CI hijau;
- tidak ada temuan baru yang belum diputuskan.

Gunakan squash merge dengan pesan yang menyertakan referensi:

```bash
gh pr merge <PR> --squash --subject "<judul>" --body "Closes #<issue> — plans/NNN-....md"
```

Setelah merge:

- update status baris plan di `plans/README.md` di `main` (commit kecil:
  `chore(plans): mark 00N as done`);
- pastikan issue tertutup (biasanya otomatis karena `Closes #N`);
- tarik `main` lokal supaya branch berikutnya berangkat dari kepala yang benar.

### 8. Temuan baru saat double check

- Kalau temuan masih masuk scope plan → perbaiki di PR yang sama, sebutkan di
  PR body.
- Kalau temuan di luar scope → **jangan** diselundupkan. Buat issue baru,
  sebutkan di PR sebagai "ditemukan saat X, dicatat di #Y", dan minta
  keputusan sebelum dikerjakan.

## Aturan referensi

- Setiap issue referensikan plan.
- Setiap commit referensikan issue.
- Setiap PR referensikan issue **dan** plan.
- Setiap merge menyebut issue dan plan di pesan commit.

Singkatnya: kalau referensinya hilang, pekerjaannya dianggap belum selesai.

## Pengecualian

- Perubahan darurat yang menghentikan produksi boleh langsung di-`main`, tapi
  harus segera dibuat issue + PR koreksi dengan referensi yang sama.
- Perbaikan satu baris yang melanjutkan PR yang sudah open cukup jadi commit
  tambahan di branch itu, bukan issue baru.
- Workflow ini tidak mengubah batas teknis di tiap plan (misalnya "jangan
  rebuild PHAR" atau "jangan tambah shellcheck"). Kalau keduanya bertentangan,
  ikuti plan dan laporkan konfliknya.
