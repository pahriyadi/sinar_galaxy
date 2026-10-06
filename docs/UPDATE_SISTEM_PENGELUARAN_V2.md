# UPDATE SISTEM PENGELUARAN V2 - PEMISAHAN TRAVEL & KANTOR

## 🎯 **FITUR BARU YANG DITAMBAHKAN:**

### **1. Pemisahan Visual Pengeluaran Travel & Kantor**
- **Warna Berbeda**: 
  - Travel: Background biru (`table-primary`)
  - Kantor: Background kuning (`table-warning`)
- **Filter Kategori**: Dropdown untuk memfilter berdasarkan kategori
- **Counter Real-time**: Menampilkan jumlah pengeluaran per kategori

### **2. Input Saldo Awal pada Jurnal Keuangan**
- **Input Manual**: User dapat input saldo awal sendiri
- **Update Real-time**: Tombol save untuk update saldo
- **Baris Pertama**: Saldo awal muncul di baris pertama tabel
- **Perhitungan Akurat**: Saldo berjalan dihitung dari saldo awal

### **3. Laporan Terpisah**
- **Pengeluaran Travel**: `laporan/pengeluaran_travel.php`
- **Pengeluaran Kantor**: `laporan/pengeluaran_kantor.php` (BARU)
- **Menu Sidebar**: Menu terpisah untuk setiap laporan

## 🔧 **PERUBAHAN TEKNIS:**

### **Database Updates**
```sql
-- Sudah dijalankan sebelumnya
ALTER TABLE data_pengeluaran 
ADD COLUMN kategori_pengeluaran ENUM('travel', 'kantor') NOT NULL DEFAULT 'travel',
ADD COLUMN keterangan TEXT NULL;

-- Update data existing
UPDATE data_pengeluaran SET kategori_pengeluaran = 'travel' WHERE travel_id IS NOT NULL;
```

### **File yang Diperbarui:**

#### **1. Data Pengeluaran View**
- ✅ Filter kategori dengan dropdown
- ✅ Warna berbeda untuk setiap kategori
- ✅ Counter real-time
- ✅ JavaScript untuk filter dan counter

#### **2. Jurnal Keuangan**
- ✅ Input saldo awal dengan tombol save
- ✅ Baris saldo awal di tabel
- ✅ Perhitungan saldo berjalan yang akurat
- ✅ JavaScript untuk update saldo

#### **3. Laporan Terpisah**
- ✅ `pengeluaran_kantor.php` - Laporan khusus pengeluaran kantor
- ✅ `pengeluaran_travel.php` - Update filter untuk travel only
- ✅ Sidebar menu terpisah

#### **4. Sidebar Navigation**
- ✅ Menu "Pengeluaran Operasional Kantor" ditambahkan
- ✅ Icon dan styling yang sesuai

## 📊 **FITUR DETAIL:**

### **Filter Kategori di Data Pengeluaran**
```javascript
// Filter berdasarkan kategori
function filterPengeluaran() {
  var filter = document.getElementById('filterKategori').value;
  var rows = document.querySelectorAll('#tablePengeluaran tbody tr[data-kategori]');
  
  rows.forEach(function(row) {
    var kategori = row.getAttribute('data-kategori');
    if (filter === 'semua' || kategori === filter) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
  
  updateCounters();
}
```

### **Saldo Awal di Jurnal Keuangan**
```javascript
// Update saldo awal
function updateSaldoAwal() {
  var saldoAwal = document.getElementById('saldo_awal').value;
  var currentUrl = new URL(window.location);
  currentUrl.searchParams.set('saldo_awal', saldoAwal);
  window.location.href = currentUrl.toString();
}
```

### **Counter Real-time**
```javascript
// Counter untuk statistik
function updateCounters() {
  var totalTravel = document.querySelectorAll('tr[data-kategori="travel"]').length;
  var totalKantor = document.querySelectorAll('tr[data-kategori="kantor"]').length;
  var totalSemua = totalTravel + totalKantor;
  
  document.getElementById('totalTravel').textContent = totalTravel + ' Travel';
  document.getElementById('totalKantor').textContent = totalKantor + ' Kantor';
  document.getElementById('totalSemua').textContent = totalSemua + ' Total';
}
```

## 🎨 **VISUAL IMPROVEMENTS:**

### **Warna Kategori**
- **Travel**: `table-primary` (Biru)
- **Kantor**: `table-warning` (Kuning)
- **Badge Kategori**: Warna yang sesuai

### **Counter Badge**
- **Travel**: Badge biru dengan jumlah
- **Kantor**: Badge kuning dengan jumlah  
- **Total**: Badge info dengan total keseluruhan

### **Form Input**
- **Saldo Awal**: Input group dengan tombol save
- **Filter**: Dropdown yang responsif
- **Modal**: Form yang dinamis berdasarkan kategori

## 📈 **LAPORAN YANG DIPERBARUI:**

### **1. Jurnal Keuangan**
- Saldo awal dapat diinput manual
- Baris pertama menampilkan saldo awal
- Perhitungan saldo berjalan yang akurat
- Uraian menampilkan kategori (TRAVEL/KANTOR)

### **2. Pengeluaran per Bank**
- Mengelompokkan berdasarkan kategori
- Total pengeluaran per kategori
- Filter yang lebih detail

### **3. Pengeluaran per Travel**
- Hanya menampilkan pengeluaran kategori travel
- Fokus pada operasional kendaraan

### **4. Pengeluaran Operasional Kantor** (BARU)
- Laporan khusus pengeluaran kantor
- Filter berdasarkan jenis pengeluaran
- Statistik yang relevan untuk kantor

## 🚀 **CARA PENGGUNAAN:**

### **Input Saldo Awal**
1. Buka Jurnal Keuangan
2. Masukkan saldo awal di field "Saldo Awal"
3. Klik tombol save (💾)
4. Saldo awal akan muncul di baris pertama

### **Filter Pengeluaran**
1. Di halaman Data Pengeluaran
2. Pilih kategori dari dropdown filter
3. Tabel akan menampilkan data sesuai filter
4. Counter akan update otomatis

### **Laporan Terpisah**
1. Menu "Pengeluaran per Travel" - untuk operasional kendaraan
2. Menu "Pengeluaran Operasional Kantor" - untuk operasional kantor
3. Setiap laporan memiliki filter dan statistik yang sesuai

## ✅ **KEUNTUNGAN UPDATE:**

1. **Visual Clarity**: Pemisahan yang jelas antara travel dan kantor
2. **Flexibility**: Saldo awal dapat disesuaikan
3. **Accuracy**: Perhitungan keuangan yang lebih akurat
4. **User Experience**: Interface yang lebih intuitif
5. **Reporting**: Laporan yang lebih spesifik dan relevan
6. **Maintenance**: Mudah untuk maintenance dan pengembangan

## 🔄 **MIGRATION NOTES:**

- Data existing otomatis dikategorikan sebagai 'travel'
- Tidak ada data yang hilang atau rusak
- Semua laporan tetap berfungsi normal
- Backward compatible dengan sistem lama

---

**Status**: ✅ **COMPLETED**  
**Version**: 2.0  
**Date**: <?= date('Y-m-d H:i:s') ?> 