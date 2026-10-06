$(function () {
  if (!$('#tableMetode').length) return;

  const table = $('#tableMetode').DataTable({
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    buttons: ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis']
  });
  table.buttons().container().appendTo('#tableMetode_wrapper .col-md-6:eq(0)');

  $(document).on('click', '.btn-edit', function () {
    const id = $(this).data('id');
    $.get('data_metode_pembayaran_fetch.php', { id_metode_pembayaran: id }, function (res) {
      let obj;
      try {
        obj = JSON.parse(res);
        if (!obj || !obj.id_metode_pembayaran) throw new Error('Data tidak ditemukan');
      } catch (e) {
        console.error('Gagal ambil data:', e.message, res);
        return;
      }

      $('#id_metode_pembayaran').val(obj.id_metode_pembayaran);
      $('#metode_pembayaran').val(obj.metode_pembayaran);
      $('#btnSave').attr('name', 'edit').text('Update');
      $('#modalFormLabel').text('Edit Metode Pembayaran');
      $('#modalForm').modal('show');
    });
  });

  $(document).on('click', '.btn-delete', function () {
    const id = $(this).data('id');
    Swal.fire({
      title: 'Yakin hapus?',
      text: 'Data akan dihapus permanen!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, hapus!'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location = '?hapus=' + id;
      }
    });
  });

  $('#modalForm').on('hidden.bs.modal', function () {
    $('#formMetode')[0].reset();
    $('#btnSave').attr('name', 'tambah').text('Simpan');
    $('#modalFormLabel').text('Tambah Metode Pembayaran');
  });
});
