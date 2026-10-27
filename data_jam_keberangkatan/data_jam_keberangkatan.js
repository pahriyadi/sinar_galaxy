$(function () {
  if (!$('#tableJam').length) return;

  const table = $('#tableJam').DataTable({
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    order: [[1, 'asc']],
    buttons: ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis']
  });
  table.buttons().container().appendTo('#tableJam_wrapper .col-md-6:eq(0)');

  // Handler tombol Edit
  $(document).on('click', '.btn-edit', function () {
    const id = $(this).data('id');
    $.get('data_jam_keberangkatan_fetch.php', { id: id }, function (res) {
      let obj = res;
      if (typeof res === 'string') {
        try {
          obj = JSON.parse(res);
        } catch (e) {
          console.error('Gagal parse JSON:', e);
          return;
        }
      }
      if (!obj || !obj.id) {
        Swal.fire('Error', 'Data jam keberangkatan tidak ditemukan!', 'error');
        return;
      }

      $('#id_jam').val(obj.id);
      // Format jam ke HH:mm untuk input type="time"
      let jamVal = (obj.jam || '').slice(0, 5);
      $('#jam').val(jamVal);
      $('#keterangan').val(obj.keterangan || '');
      $('#is_active').val(obj.is_active !== undefined ? obj.is_active : 1);

      $('#btnSave').attr('name', 'edit').html('<i class="fas fa-save mr-1"></i> Update Data');
      $('#modalFormLabel').text('Edit Jadwal Jam Keberangkatan');
      $('#modalForm').modal('show');
    });
  });

  // Handler tombol Hapus
  $(document).on('click', '.btn-delete', function () {
    const id = $(this).data('id');
    const jamText = $(this).data('jam') || '';
    Swal.fire({
      title: 'Hapus Jadwal Jam?',
      html: `Apakah Anda yakin ingin menghapus jam <b>${jamText}</b> dari master data?<br><small class="text-danger">* Transaksi tiket lama yang menggunakan jam ini tidak akan terhapus.</small>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#6c757d',
      confirmButtonText: '<i class="fas fa-trash mr-1"></i> Ya, Hapus!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location = '?hapus=' + id;
      }
    });
  });

  // Reset modal ketika ditutup
  $('#modalForm').on('hidden.bs.modal', function () {
    $('#formJam')[0].reset();
    $('#id_jam').val('');
    $('#is_active').val(1);
    $('#btnSave').attr('name', 'tambah').html('<i class="fas fa-save mr-1"></i> Simpan Data');
    $('#modalFormLabel').text('Tambah Jadwal Jam Keberangkatan');
  });
});
