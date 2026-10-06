$(function () {
  if (!$('#tableUsers').length) return;

  const table = $('#tableUsers').DataTable({
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    buttons: ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis']
  });
  table.buttons().container().appendTo('#tableUsers_wrapper .col-md-6:eq(0)');

  $(document).on('click', '.btn-edit', function () {
    const id = $(this).data('id');
    $.get('data_users_fetch.php', { id_users: id }, function (res) {
      let obj;
      try {
        obj = JSON.parse(res);
        if (!obj || !obj.id_users) throw new Error('Data tidak ditemukan');
      } catch (e) {
        console.error('Gagal ambil data:', e.message, res);
        return;
      }

      $('#id_users').val(obj.id_users);
      $('#username').val(obj.username);
      $('#password').val('').prop('required', false); // Kosongkan dan buat opsional saat edit
      $('#passwordHelp').show();
      $('#asal_po').val(obj.asal_po);
      $('#alamat').val(obj.alamat);
      $('#no_hp').val(obj.no_hp);
      $('#role').val(obj.role);
      $('#btnSave').attr('name', 'edit').text('Update');
      $('#modalFormLabel').text('Edit Users');
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
    $('#formUsers')[0].reset();
    $('#id_users').val('');
    $('#password').val('').prop('required', true);
    $('#passwordHelp').hide();
    $('#btnSave').attr('name', 'tambah').text('Simpan');
    $('#modalFormLabel').text('Tambah Users');
  });
});
