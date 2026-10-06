$(function () {
  if (!$('#tableRekening').length) return;

  const table = $('#tableRekening').DataTable({
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    buttons: ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis']
  });
  table.buttons().container().appendTo('#tableRekening_wrapper .col-md-6:eq(0)');

  $(document).on('click', '.btn-edit', function () {
    const id = $(this).data('id');
    $.get('data_rekening_fetch.php', { id_rekening: id }, function (res) {
      let obj;
      try {
        obj = JSON.parse(res);
        if (!obj || !obj.id_rekening) throw new Error('Data tidak ditemukan');
      } catch (e) {
        console.error('Gagal ambil data:', e.message, res);
        return;
      }

      $('#id_rekening').val(obj.id_rekening);
      $('#nama_rekening').val(obj.nama_rekening);
      $('#nomor_rekening').val(obj.nomor_rekening);
      if(obj.gambar) {
        $('#preview_gambar').html('<img src="../img/' + obj.gambar + '" width="100">');
      } else {
        $('#preview_gambar').html('');
      }
      $('#btnSave').attr('name', 'edit').text('Update');
      $('#modalFormLabel').text('Edit Rekening');
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
    $('#formRekening')[0].reset();
    $('#preview_gambar').html('');
    $('#btnSave').attr('name', 'tambah').text('Simpan');
    $('#modalFormLabel').text('Tambah Rekening');
  });
});
