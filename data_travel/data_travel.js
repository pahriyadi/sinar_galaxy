$(function () {
  if (!$('#tableTravel').length) return;

  const table = $('#tableTravel').DataTable({
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    buttons: ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis']
  });
  table.buttons().container().appendTo('#tableTravel_wrapper .col-md-6:eq(0)');

  $(document).on('click', '.btn-edit', function () {
    const id = $(this).data('id');
    $.get('data_travel_fetch.php', { id_travel: id }, function (res) {
      let obj;
      try {
        obj = JSON.parse(res);
        if (!obj || !obj.id_travel) throw new Error('Data tidak ditemukan');
      } catch (e) {
        console.error('Gagal ambil data:', e.message, res);
        return;
      }

      $('#id_travel').val(obj.id_travel);
      $('#no_plat').val(obj.no_plat);
      $('#kelas').val(obj.kelas);
      $('#harga').val(obj.harga);
      $('#jumlah_kursi').val(obj.jumlah_kursi);
      $('#btnSave').attr('name', 'edit').text('Update');
      $('#modalFormLabel').text('Edit Travel');
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
    $('#formTravel')[0].reset();
    $('#btnSave').attr('name', 'tambah').text('Simpan');
    $('#modalFormLabel').text('Tambah Travel');
  });
});
