$(function () {
  if (!$('#tableTujuan').length) return;

  const table = $('#tableTujuan').DataTable({
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    buttons: ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis']
  });
  table.buttons().container().appendTo('#tableTujuan_wrapper .col-md-6:eq(0)');

  $(document).on('click', '.btn-edit', function () {
    const id = $(this).data('id');
    $.get('data_tujuan_perjalanan_fetch.php', { id_tujuan_perjalanan: id }, function (res) {
      let obj;
      try {
        obj = JSON.parse(res);
        if (!obj || !obj.id_tujuan_perjalanan) throw new Error('Data tidak ditemukan');
      } catch (e) {
        console.error('Gagal ambil data:', e.message, res);
        return;
      }

      $('#id_tujuan_perjalanan').val(obj.id_tujuan_perjalanan);
      $('#nama_tujuan').val(obj.nama_tujuan);
      $('#btnSave').attr('name', 'edit').text('Update');
      $('#modalFormLabel').text('Edit Tujuan Perjalanan');
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
    $('#formTujuan')[0].reset();
    $('#btnSave').attr('name', 'tambah').text('Simpan');
    $('#modalFormLabel').text('Tambah Tujuan Perjalanan');
  });
});
