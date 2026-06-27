# TODO - Enhancement Broadcast Tagihan Unpaid (WhatsApp)

## Deskripsi
Menambahkan opsi pada fitur **Tagihan (Unpaid)** di WhatsApp agar admin dapat memilih:
- Target: **Semua pelanggan** belum bayar atau **Sebagian** (batch)
- Jumlah per batch: 5, 10, 15, 20, 25 pelanggan
- Jeda waktu antar batch: 5, 10, 15, 20 menit
- Waktu mulai broadcast
- Hasilnya dimasukkan ke dalam tabel **Antrean Jadwal** (ScheduledMessage)

## Status Pengerjaan

### 1. Persiapan & Analisis ✅
- [x] Pahami alur existing `ScheduledMessage` (Model, migration, casts)
- [x] Pahami alur existing `prepareBroadcast('unpaid')` di blade (JS function)
- [x] Pahami route `whatsapp.broadcast.schedule` (method `scheduleBroadcast` di controller)
- [x] Identifikasi di view `index.blade.php` bagian tab **Tagihan (Unpaid)** (x-data, form, button)

### 2. Modifikasi View - Tab Tagihan Unpaid ✅
- [x] Tambahkan opsi **"Kirim ke"**: Semua Pelanggan / Sebagian (radio button)
- [x] Jika pilih "Sebagian", tampilkan dropdown **Jumlah per Batch**: 5, 10, 15, 20, 25
- [x] Tambahkan dropdown **Jeda Waktu**: 5, 10, 15, 20 menit
- [x] Tambahkan input **Waktu Mulai** (datetime-local) untuk menjadwalkan batch pertama
- [x] Ubah button "Mulai Broadcast Reminder" agar mengirim data ke endpoint baru (`whatsapp.unpaid.batch`)
- [x] Gunakan Alpine.js untuk state management (x-data, x-show, x-model)

### 3. Modifikasi Controller (WhatsappController) ✅
- [x] Tambah method `scheduleUnpaidBatch` di controller
- [x] Logic pembagian pelanggan unpaid ke dalam beberapa batch berdasarkan jumlah per batch
- [x] Hitung waktu kirim setiap batch: `waktu_mulai + (batch_index * jeda_menit)`
- [x] Simpan setiap batch sebagai baris terpisah di tabel `scheduled_messages` (status: pending, scheduled_at terisi)
- [x] Untuk mode "Semua Pelanggan", buat 1 jadwal dengan delay 1 menit
- [x] Validasi input baru (jumlah_per_batch, jeda_menit, waktu_mulai)

### 4. Modifikasi ScheduledMessage (Model) ✅
- [x] Kolom `batch_number` dan `total_batches` ditambahkan via migration

### 5. Migration Database ✅
- [x] Migration `2026_06_27_170000_add_batch_columns_to_scheduled_messages_table.php` sudah running

### 6. Update View - Antrean Jadwal (Queue Tab) ✅
- [x] Tampilkan informasi batch (misal: "Batch 1/10") pada tabel jadwal
- [x] Tampilkan waktu kirim setiap batch sesuai perhitungan

### 7. JavaScript Functions ✅
- [x] `prepareBatchUnpaid()` - Fungsi utama yang dipanggil tombol, validasi + konfirmasi
- [x] `processUnpaidBatch()` - AJAX call ke endpoint `whatsapp.unpaid.batch`
- [x] Tampilkan ringkasan batch di SweetAlert setelah berhasil
- [x] Reload halaman setelah sukses

### 8. Testing
- [ ] Test: Pilih "Sebagian", jumlah 10, jeda 10 menit, mulai 14:00 → 10 jadwal terbuat dengan selisih 10 menit
- [ ] Test: Pilih "Semua Pelanggan" → 1 jadwal dengan delay 1 menit
- [ ] Test: Pastikan cron/command memproses jadwal tepat waktu
- [ ] Test: Validasi error handling jika input tidak valid