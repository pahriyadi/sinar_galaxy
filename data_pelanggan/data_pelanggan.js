$(document).ready(function () {
  if (!$('#tablePelanggan').length) return;

  const table = $('#tablePelanggan').DataTable({
    processing: true,
    serverSide: true,
    ajax: 'data_pelanggan_fetch.php',
    deferRender: true,
    pageLength: 25,
    responsive: false, // Matikan responsive agar header tidak berantakan
    lengthChange: true,
    autoWidth: false,
    order: [[0, 'desc']],
    buttons: ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis'],
    language: {
      processing: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>',
      search: "Cari Umum:",
      lengthMenu: "Tampilkan _MENU_ data",
      info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
      infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
      infoFiltered: "(disaring dari _MAX_ total data)",
      paginate: {
        first: "Pertama",
        last: "Terakhir",
        next: "Berikutnya",
        previous: "Sebelumnya"
      }
    },
    columnDefs: [
      { targets: [8], orderable: false, searchable: false } // Aksi tidak bisa disorting/dicari
    ]
  });

  // Custom Search ID (Exact Match)
  $('#searchIdInput').on('keyup change', function () {
    var val = $(this).val();
    if (val) {
      // Trigger search on column 0 with exact match pattern
      table.column(0).search('^' + val + '$', true, false).draw();
    } else {
      table.column(0).search('').draw();
    }
  });

  table.buttons().container().appendTo('#tablePelanggan_wrapper .col-md-6:eq(0)');

  // Tombol Edit - Fetch data via AJAX
  $(document).on('click', '.btn-edit', function () {
    const id = $(this).data('id');
    $.get('get_pelanggan_by_id.php', { id: id }, function (data) {
      if (data.success) {
        $('#id_pelanggan').val(data.id_pelanggan);
        $('#nama').val(data.nama);
        $('#alamat').val(data.alamat);
        $('#no_ktp').val(data.no_ktp);
        $('#no_hp').val(data.no_hp);
        $('#nip_sgt').val(data.nip_sgt);

        $('#btnSave').attr('name', 'edit').html('<i class="fas fa-save mr-1"></i> Update');
        $('#modalFormLabel').html('<i class="fas fa-edit mr-2"></i> Edit Pelanggan');
        $('#modalForm').modal('show');
      } else {
        Swal.fire('Error', 'Gagal mengambil data pelanggan', 'error');
      }
    }, 'json');
  });

  // Tombol Hapus - Unik class untuk menghindari bentrok
  $(document).on('click', '.btn-delete-pelanggan', function () {
    const id = $(this).data('id');
    Swal.fire({
      title: 'Apakah Anda yakin?',
      text: "Data pelanggan akan dihapus permanen!",
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

  // Handle tombol kartu pelanggan
  $(document).on('click', '.btn-kartu', function () {
    $('#kp_nama').text($(this).data('nama'));
    $('#kp_alamat').text($(this).data('alamat'));
    $('#kp_no_ktp').text($(this).data('no_ktp'));
    $('#kp_no_hp').text($(this).data('no_hp'));
    $('#kp_nip_sgt').text($(this).data('nip_sgt'));
    $('#kartuPelangganDepan').show();
    $('#kartuPelangganBelakang').hide();
    $('#modalKartu').modal('show');
  });

  $('#modalForm').on('hidden.bs.modal', function () {
    $('#formPelanggan')[0].reset();
    $('#btnSave').attr('name', 'tambah').html('<i class="fas fa-save mr-1"></i> Simpan');
    $('#modalFormLabel').html('<i class="fas fa-user-plus mr-2"></i> Tambah Pelanggan');
  });
}); 
