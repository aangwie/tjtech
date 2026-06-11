# TODO - Komisi Operator & Tagihan Bersih

## Rencana
1. Buat migrasi baru untuk tabel `tabel_komisi`.
   - Kolom: operator_id, month, year, komisi_percent, komisi_value.
   - Unique: (operator_id, month, year).
2. Tambah route & controller method untuk simpan/update komisi operator.
   - Endpoint: POST `/billing/rekap-operator/komisi` (atau nama lain yang dipilih konsisten).
3. Modifikasi `BillingRekapController@rekapOperator`:
   - ambil komisi dari `tabel_komisi` per operator untuk bulan+tahun.
   - kirim ke view nilai komisi_percent dan/atau komisi_value.
4. Modifikasi Blade `billing/rekap-operator.blade.php`:
   - ganti kolom “Persentase” → “KOMISI”.
   - sel KOMISI berupa input angka 0..100 yang editable hanya admin.
   - ganti/menambahkan kolom “Tagihan Bersih” di sebelah kanan.
   - perhitungan tagihan bersih = (komisi_percent/100) * tagihan_lunas.
5. Validasi permission saat simpan komisi.
   - Admin/Superadmin: pastikan operator berada di bawah admin yang login (untuk admin).
6. Upsert komisi ke `tabel_komisi` dengan perhitungan `komisi_value`.
7. Jalankan migration.

## Status
- [x] Step 1: migrasi `tabel_komisi`
- [x] Step 2: route + method simpan komisi
- [x] Step 3: update `rekapOperator` baca komisi
- [x] Step 4: update Blade UI (KOMISI & Tagihan Bersih)
- [x] Step 5: permission check simpan komisi
- [x] Step 6: upsert + hitung komisi_value
- [x] Step 7: run migration & test manual



