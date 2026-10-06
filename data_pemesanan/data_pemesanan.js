$(document).ready(function () {
  if (!$('#tablePemesanan').length) return;

  function toDatetimeLocal(val) {
    if (!val) return '';
    return val.replace(' ', 'T').slice(0, 16);
  }

  // Check agar tidak double initialize DataTables dan tangani server-side pagination
  const tableElement = $('#tablePemesanan');
  const useServer = tableElement.attr('data-server') === '1';
  const isDataTableInitialized = $.fn.DataTable.isDataTable('#tablePemesanan');
  
  if (!isDataTableInitialized && !useServer) {
    const table = tableElement.DataTable({
      responsive: true,
      lengthChange: true,
      autoWidth: false,
      order: [[0, 'desc']],
      buttons: ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis']
    });
    table.buttons().container().appendTo('#tablePemesanan_wrapper .col-md-6:eq(0)');
  }

  // Event tombol Edit
  $(document).on('click', '.btn-edit', function () {
    var $btn = $(this);
    var id = $btn.data('id');
    var nip = $btn.data('nip') || '';
    var nama = $btn.data('nama') || '';
    var alamat = $btn.data('alamat') || '';
    var ktp = $btn.data('ktp') || '';
    var hp = $btn.data('hp') || '';
    var plat = $btn.data('plat') || '';

    $('#id_pemesanan').val(id);

    // Pastikan NIP terdaftar di dropdown select sebelum diset
    var $selNip = $('#nip_sgt_id');
    if (nip && $selNip.length) {
      if ($selNip.find("option[value='" + nip + "']").length === 0) {
        var labelNip = nip + (nama ? ' - ' + nama : '');
        $selNip.append(new Option(labelNip, nip, true, true));
      }
      $selNip.val(nip);
    }

    // Set nilai identitas penumpang langsung dari data pemesanan
    $('#nama_id').val(nama);
    $('#alamat_id').val(alamat);
    $('#no_ktp_id').val(ktp);
    $('#no_hp_id').val(hp);

    $('#tanggal_pemesanan').val(toDatetimeLocal($btn.data('tglp')));
    $('#tanggal_berangkat').val(toDatetimeLocal($btn.data('tglb')));

    // Pastikan nomor plat terdaftar di dropdown
    var $selPlat = $('#no_plat_id');
    if (plat && $selPlat.length) {
      if ($selPlat.find("option[value='" + plat + "']").length === 0) {
        $selPlat.append(new Option(plat, plat, true, true));
      }
      $selPlat.val(plat);
    }

    $('#kelas_id').val($btn.data('kelas'));
    $('#harga_id').val($btn.data('harga'));
    $('#kursi').val($btn.data('kursi'));
    $('#tujuan_id').val($btn.data('tujuan'));
    $('#status_pembayaran_id').val($btn.data('status'));
    $('#metode_pembayaran_id').val($btn.data('metode'));
    $('#jenis_rekening_id').val($btn.data('rekening'));
    $('#keterangan').val($btn.data('keterangan'));

    // Catatan: JANGAN panggil autofillPelanggan() atau autofillTravel() saat edit,
    // karena data asli transaksi sudah lengkap dan pemanggilan autofill berisiko menghapus nilai awal.
    $('#btnSave').attr('name', 'edit').html('<i class="fas fa-save mr-1"></i> Update');
    $('#modalFormLabel').html('<i class="fas fa-edit mr-2"></i> Edit Pemesanan');
    $('#modalForm').modal('show');
  });

  // Hapus event handler hapus lama
  // $(document).on('click', '.btn-delete', function () {
  //   var id = $(this).data('id');
  //   Swal.fire({
  //     title: 'Yakin hapus?',
  //     text: 'Data akan dihapus permanen!',
  //     icon: 'warning',
  //     showCancelButton: true,
  //     confirmButtonText: 'Ya, hapus!'
  //   }).then((result) => {
  //     if (result.isConfirmed) {
  //       window.location = '?hapus=' + id;
  //     }
  //   });
  // });

  $('#modalForm').on('hidden.bs.modal', function () {
    $('#formPemesanan')[0].reset();
    $('#btnSave').attr('name', 'tambah').text('Simpan');
    $('#modalFormLabel').text('Tambah Pemesanan');
  });
});