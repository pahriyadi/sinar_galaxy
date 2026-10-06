# MULTIPLE PAYMENT METHOD IMPLEMENTATION
## Sistem Pemesanan Tiket Galaxy Travel

### 🎯 **DESKRIPSI FITUR**
Implementasi sistem pembayaran multiple method yang memungkinkan pelanggan menggunakan kombinasi pembayaran transfer dan cash dalam satu transaksi pemesanan tiket.

### 📋 **FITUR UTAMA**
- ✅ **Pembayaran Tunggal**: Metode pembayaran tradisional (satu metode)
- ✅ **Pembayaran Multiple**: Kombinasi transfer + cash dalam satu transaksi
- ✅ **Validasi Otomatis**: Total pembayaran harus sama dengan harga tiket
- ✅ **Backward Compatibility**: Mendukung data lama tanpa perubahan
- ✅ **Interface Dinamis**: Form yang berubah sesuai tipe pembayaran

### 🗄️ **PERUBAHAN DATABASE**

#### 1. **Tabel `data_pemesanan`**
```sql
ALTER TABLE `data_pemesanan` 
ADD COLUMN `payment_methods` JSON NULL AFTER `jenis_rekening_id`,
ADD COLUMN `payment_details` TEXT NULL AFTER `payment_methods`;
```

#### 2. **Tabel `data_pengiriman`**
```sql
ALTER TABLE `data_pengiriman` 
ADD COLUMN `payment_methods` JSON NULL AFTER `jenis_rekening_id`,
ADD COLUMN `payment_details` TEXT NULL AFTER `payment_methods`;
```

#### 3. **Format JSON Payment Methods**
```json
[
  {
    "method_id": 1,
    "amount": 100000,
    "rekening_id": 2
  },
  {
    "method_id": 2,
    "amount": 70000,
    "rekening_id": 4
  }
]
```

### 🔧 **PERUBAHAN KODE**

#### 1. **File: `assets/fungsi.php`**
- ✅ Update `tambahPemesanan()` - Support multiple payment
- ✅ Update `updatePemesanan()` - Support multiple payment
- ✅ Update `tambahPengiriman()` - Support multiple payment
- ✅ Update `updatePengiriman()` - Support multiple payment

#### 2. **File: `data_pemesanan/data_pemesanan_view.php`**
- ✅ Interface form dengan toggle pembayaran tunggal/multiple
- ✅ JavaScript untuk mengelola multiple payment methods
- ✅ Validasi total pembayaran vs harga tiket
- ✅ Display multiple payment di tabel

#### 3. **File: `data_pemesanan/data_pemesanan_fetch.php`**
- ✅ Include `payment_methods` data untuk edit modal

### 🎨 **INTERFACE FEATURES**

#### **Form Pemesanan**
```
┌─────────────────────────────────────────────────────────┐
│ Tipe Pembayaran: [Pembayaran Tunggal ▼]                │
├─────────────────────────────────────────────────────────┤
│ Metode Pembayaran: [TRANSFER ▼]                        │
│ Jumlah Pembayaran: [Rp 170,000] (readonly)             │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ Tipe Pembayaran: [Pembayaran Multiple ▼]               │
├─────────────────────────────────────────────────────────┤
│ ┌─ Detail Pembayaran Multiple ───────────────────────┐ │
│ │ Metode: [TRANSFER ▼] Jumlah: [100000] Rek: [BRI]  │ │
│ │ Metode: [CASH ▼]    Jumlah: [70000]  Rek: [CASH]  │ │
│ │ [Tambah Metode Pembayaran]                         │ │
│ │ Total Pembayaran: [Rp 170,000] (validated)        │ │
│ └─────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

#### **Tabel Display**
```
┌─────────────────────────────────────────────────────────┐
│ Metode Pembayaran:                                      │
│ [TRANSFER] Rp 100,000                                   │
│ BRI                                                      │
│ [CASH] Rp 70,000                                        │
│ CASH                                                     │
└─────────────────────────────────────────────────────────┘
```

### ⚙️ **FUNGSI JAVASCRIPT**

#### **1. `togglePaymentMethod()`**
- Toggle antara single dan multiple payment interface
- Auto-fill single amount dengan harga tiket

#### **2. `addPaymentMethod()`**
- Menambah baris metode pembayaran baru
- Dynamic form generation

#### **3. `removePaymentMethod(index)`**
- Hapus metode pembayaran
- Re-index remaining methods

#### **4. `calculateTotal()`**
- Hitung total pembayaran
- Validasi vs harga tiket
- Show error jika tidak sama

#### **5. `loadPaymentMethods(paymentMethods)`**
- Load data existing untuk edit
- Populate form dengan data JSON

### 🔄 **WORKFLOW SISTEM**

#### **1. Pembayaran Tunggal**
```
User Input → Single Payment Form → Validation → Save to DB
```

#### **2. Pembayaran Multiple**
```
User Input → Multiple Payment Form → Add Methods → Calculate Total → Validate → Save to DB
```

#### **3. Edit Data**
```
Load Data → Parse JSON → Determine Type → Show Appropriate Form → Edit → Save
```

### 📊 **IMPACT PADA LAPORAN**

#### **Laporan yang Terpengaruh:**
1. **Pendapatan Bank** (`laporan/pendapatan_bank.php`)
2. **Pendapatan Travel** (`laporan/pendapatan_travel.php`)
3. **Jurnal Keuangan** (`laporan/jurnal_keuangan.php`)
4. **Rekap Keuangan** (`laporan/rekap_keuangan.php`)
5. **Daftar Keberangkatan** (`laporan/daftar_keberangkatan.php`)

#### **Perubahan Query:**
```sql
-- Sebelum: Single payment method
SELECT pm.metode_pembayaran_id, pm.jenis_rekening_id, pm.harga_id

-- Sesudah: Multiple payment methods
SELECT 
  JSON_EXTRACT(pm.payment_methods, '$[*].method_id') as method_ids,
  JSON_EXTRACT(pm.payment_methods, '$[*].rekening_id') as rekening_ids,
  JSON_EXTRACT(pm.payment_methods, '$[*].amount') as amounts
```

### 🚀 **IMPLEMENTASI STEP-BY-STEP**

#### **Step 1: Database Update**
```bash
# Jalankan SQL untuk update database
mysql -u username -p database_name < add_profile_fields.sql
```

#### **Step 2: Update Functions**
- Update `assets/fungsi.php` dengan multiple payment support

#### **Step 3: Update Interface**
- Update `data_pemesanan/data_pemesanan_view.php`
- Update `data_pemesanan/data_pemesanan_fetch.php`

#### **Step 4: Update Reports**
- Update semua laporan untuk handle multiple payment

#### **Step 5: Testing**
- Test pembayaran tunggal
- Test pembayaran multiple
- Test edit data existing
- Test backward compatibility

### 🔒 **VALIDASI & SECURITY**

#### **Validasi Frontend:**
- Total pembayaran = harga tiket
- Minimal 1 metode pembayaran
- Required fields validation

#### **Validasi Backend:**
- JSON format validation
- Amount validation
- SQL injection prevention
- XSS prevention

### 📈 **BENEFITS**

#### **Untuk User:**
- ✅ Fleksibilitas pembayaran
- ✅ Kombinasi transfer + cash
- ✅ Interface yang intuitif
- ✅ Validasi real-time

#### **Untuk Admin:**
- ✅ Tracking detail pembayaran
- ✅ Laporan yang akurat
- ✅ Backward compatibility
- ✅ Data integrity

#### **Untuk Sistem:**
- ✅ Scalable architecture
- ✅ JSON-based storage
- ✅ Easy maintenance
- ✅ Future extensibility

### 🐛 **TROUBLESHOOTING**

#### **Common Issues:**
1. **JSON Parse Error**: Check data format in database
2. **Validation Error**: Ensure total amount matches ticket price
3. **Display Issues**: Check JavaScript console for errors
4. **Backward Compatibility**: Old data should still work

#### **Debug Commands:**
```sql
-- Check payment methods data
SELECT id_pemesanan, payment_methods, payment_details 
FROM data_pemesanan 
WHERE payment_methods IS NOT NULL;

-- Check for invalid JSON
SELECT id_pemesanan, payment_methods 
FROM data_pemesanan 
WHERE JSON_VALID(payment_methods) = 0;
```

### 📝 **NOTES**

#### **Migration Notes:**
- Existing data akan dikonversi otomatis
- Single payment method tetap berfungsi
- No data loss during migration

#### **Future Enhancements:**
- Support untuk lebih dari 2 metode pembayaran
- Payment gateway integration
- Real-time payment validation
- Payment history tracking

---

**Dibuat oleh:** AI Assistant  
**Tanggal:** 2025  
**Versi:** 1.0  
**Status:** Implemented ✅ 