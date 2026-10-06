$(function () {
  if (!$('#tableStatus').length) return;

  const table = $('#tableStatus').DataTable({
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    buttons: ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis']
  });
  table.buttons().container().appendTo('#tableStatus_wrapper .col-md-6:eq(0)');

  $(document).on('click', '.btn-edit', function () {
    const id = $(this).data('id');
    $.get('data_status_pembayaran_fetch.php', { id_status_pembayaran: id }, function (res) {
      let obj;
      try {
        obj = JSON.parse(res);
        if (!obj || !obj.id_status_pembayaran) throw new Error('Data tidak ditemukan');
      } catch (e) {
        console.error('Gagal ambil data:', e.message, res);
        return;
      }

      $('#id_status_pembayaran').val(obj.id_status_pembayaran);
      $('#status_pembayaran').val(obj.status_pembayaran);
      $('#btnSave').attr('name', 'edit').text('Update');
      $('#modalFormLabel').text('Edit Status Pembayaran');
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
    $('#formStatus')[0].reset();
    $('#btnSave').attr('name', 'tambah').text('Simpan');
    $('#modalFormLabel').text('Tambah Status Pembayaran');
  });
});
