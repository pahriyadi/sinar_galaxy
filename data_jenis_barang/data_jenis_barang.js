/**
 * JavaScript Handler untuk Master Data Jenis Barang & Tarif Paket
 * PO. Sinar Galaxy Travel
 */
$(function () {
  if (!$('#tableJenisBarang').length) return;

  // Inisialisasi DataTable
  var table = $('#tableJenisBarang').DataTable({
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    pageLength: 25,
    order: [[1, 'asc']],
    buttons: ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis'],
    language: {
      search: "Cari Barang / Tarif:",
      lengthMenu: "Tampilkan _MENU_ baris",
      info: "Menampilkan _START_ - _END_ dari _TOTAL_ jenis barang",
      infoEmpty: "Tidak ada data jenis barang",
      zeroRecords: "Data tidak ditemukan",
      paginate: {
        first: "Awal",
        last: "Akhir",
        next: "Lanjut",
        previous: "Kembali"
      }
    }
  });

  if ($('#tableJenisBarang_wrapper .col-md-6:eq(0)').length) {
    table.buttons().container().appendTo('#tableJenisBarang_wrapper .col-md-6:eq(0)');
  }

  // Handle Edit Click
  $(document).on('click', '.btn-edit-barang', function (e) {
    e.preventDefault();
    var id = $(this).data('id');
    var nama = $(this).data('nama');
    var biaya = $(this).data('biaya');
    var ket = $(this).data('keterangan');

    $('#id_barang').val(id);
    $('#aksi_barang').val('edit');
    $('#nama_barang').val(nama);
    $('#biaya_standar').val(biaya);
    $('#keterangan').val(ket);

    $('#btnSave').attr('name', 'edit').html('<i class="fas fa-save mr-1"></i> Simpan Perubahan');
    $('#modalFormLabel').html('<i class="fas fa-edit mr-2 text-warning"></i>Edit Jenis Barang & Tarif');
    $('#modalForm').modal('show');
  });

  // Reset form saat modal ditutup
  $('#modalForm').on('hidden.bs.modal', function () {
    if ($('#formBarang').length) {
      $('#formBarang')[0].reset();
    }
    $('#id_barang').val('');
    $('#aksi_barang').val('tambah');
    $('#btnSave').attr('name', 'tambah').html('<i class="fas fa-save mr-1"></i> Simpan');
    $('#modalFormLabel').html('<i class="fas fa-box mr-2 text-success"></i>Tambah Jenis Barang & Tarif');
  });

  // Handle Delete Click
  $(document).on('click', '.btn-delete-barang', function (e) {
    e.preventDefault();
    var id = $(this).data('id');

    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Konfirmasi Hapus Data?',
        text: 'Jenis barang dan tarif acuan ini akan dihapus permanen dari master!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#c62828',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i> Ya, Hapus',
        cancelButtonText: 'Batal'
      }).then(function (result) {
        if (result.isConfirmed) {
          window.location.href = '?hapus=' + encodeURIComponent(id);
        }
      });
    } else {
      if (confirm('Apakah Anda yakin ingin menghapus data jenis barang ini?')) {
        window.location.href = '?hapus=' + encodeURIComponent(id);
      }
    }
  });
});
