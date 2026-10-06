
$(document).ready(function () {
  if (!$('#tablePengeluaran').length) return;

  function toDatetimeLocal(val) {
    if (!val) return '';
    return val.replace(' ', 'T').slice(0, 16);
  }

  const table = $('#tablePengeluaran').DataTable({
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    pageLength: 25,
    deferRender: true,
    buttons: ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis']
  });
  table.buttons().container().appendTo('#tablePengeluaran_wrapper .col-md-6:eq(0)');

  // Event tombol Edit
  $(document).on('click', '.btn-edit', function () {
    $('#id_pengeluaran').val($(this).data('id'));
    $('#tanggal_pengeluaran').val($(this).data('tanggal'));
    $('#kategori_pengeluaran').val($(this).data('kategori'));
    $('#jenis_pengeluaran_id').val($(this).data('jenis'));
    $('#harga_operasional').val($(this).data('hargaoperasional'));
    $('#keterangan').val($(this).data('keterangan'));
    $('#travel_id').val($(this).data('travel'));
    $('#kelas').val($(this).data('kelas'));
    $('#harga').val($(this).data('harga'));
    $('#status_pembayaran_id').val($(this).data('status'));
    $('#metode_pembayaran_id').val($(this).data('metode'));
    $('#jenis_rekening_id').val($(this).data('rekening'));
    $('#user_id').val($(this).data('user'));
    $('#asal_po').val($(this).data('asalpo'));
    
    // Toggle travel fields based on kategori
    if (typeof toggleTravelFields === 'function') toggleTravelFields();
    if (typeof autofillTravel === 'function') autofillTravel();
    
    $('#btnSave').attr('name', 'edit').text('Update');
    $('#modalFormLabel').text('Edit Pengeluaran');
    $('#modalForm').modal('show');
  });

  // Event tombol Hapus
  $(document).on('click', '.btn-delete', function () {
    var id = $(this).data('id');
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

  // Reset form saat modal ditutup
  $('#modalForm').on('hidden.bs.modal', function () {
    $('#formPengeluaran')[0].reset();
    $('#btnSave').attr('name', 'tambah').text('Simpan');
    $('#modalFormLabel').text('Tambah Pengeluaran');
    $('#kelas').val("");
    $('#harga').val("");
    $('#asal_po').val("");
  });


});
