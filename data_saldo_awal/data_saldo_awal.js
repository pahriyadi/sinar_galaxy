$(function () {
  if (!$('#tableSaldoAwal').length) return;

  // Check if DataTable already exists and destroy it
  if ($.fn.DataTable.isDataTable('#tableSaldoAwal')) {
    $('#tableSaldoAwal').DataTable().destroy();
  }

  const table = $('#tableSaldoAwal').DataTable({
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    order: [[0, 'desc']], // urutkan kolom ke-0 secara Z-A
    buttons: ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis']
  });
  table.buttons().container().appendTo('#tableSaldoAwal_wrapper .col-md-6:eq(0)');

  // Reset form ketika modal ditutup
  $('#modalForm').on('hidden.bs.modal', function () {
    $('#formSaldoAwal')[0].reset();
    $('#id_saldo_awal').val('');
    $('#btnSave').attr('name', 'tambah').text('Simpan');
    $('#modalFormLabel').text('Tambah Saldo Awal');
  });

  // Edit data
  $(document).on('click', '.btn-edit', function () {
    const id = $(this).data('id');
    $.get('data_saldo_awal_fetch.php', { id_saldo_awal: id }, function (res) {
      let obj;
      try {
        obj = JSON.parse(res);
        if (!obj || !obj.id_saldo_awal) throw new Error('Data tidak ditemukan');
      } catch (e) {
        console.error('Gagal ambil data:', e.message, res);
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Gagal mengambil data: ' + e.message
        });
        return;
      }

      $('#id_saldo_awal').val(obj.id_saldo_awal);
      $('#id_rekening').val(obj.id_rekening);
      $('#bulan').val(obj.bulan);
      $('#tahun').val(obj.tahun);
      $('#saldo_awal').val(obj.saldo_awal);
      $('#btnSave').attr('name', 'edit').text('Update');
      $('#modalFormLabel').text('Edit Saldo Awal');
      $('#modalForm').modal('show');
    });
  });

  // Delete data
  $(document).on('click', '.btn-delete', function () {
    const id = $(this).data('id');
    Swal.fire({
      title: 'Yakin hapus?',
      text: 'Data saldo awal akan dihapus permanen!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = '?hapus=' + id;
      }
    });
  });

  // Format input saldo awal dengan pemisah ribuan
  $('#saldo_awal').on('input', function() {
    let value = $(this).val().replace(/[^\d]/g, '');
    $(this).val(value);
  });

  // Set bulan dan tahun default ke bulan/tahun saat ini
  if (!$('#bulan').val()) {
    $('#bulan').val(new Date().getMonth() + 1);
  }
  if (!$('#tahun').val()) {
    $('#tahun').val(new Date().getFullYear());
  }
});