$(document).ready(function () {
  if (!$('#tablePengiriman').length) return;

  const tableElement = $('#tablePengiriman');
  const useServer = tableElement.attr('data-server') === '1';

  if (useServer) {
    const table = tableElement.DataTable({
      processing: true,
      serverSide: true,
      ajax: 'data_pengiriman_fetch.php',
      autoWidth: false,
      deferRender: true,
      lengthChange: true,
      pageLength: 25,
      order: [[0, 'desc']],
      buttons: ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis'],
      columnDefs: [
        { targets: 1, orderable: false } // Aksi column is not orderable
      ],
      language: {
        processing: 'Memuat data...',
        search: 'Cari:',
        zeroRecords: 'Tidak ada data pengiriman',
        info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
        infoEmpty: 'Tidak ada data',
        lengthMenu: 'Tampilkan _MENU_ data',
        paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
      }
    });
    table.buttons().container().appendTo('#tablePengiriman_wrapper .col-md-6:eq(0)');

    // Pencarian khusus kolom ID (kolom 0)
    $('#searchIdInput').on('keyup change', function () {
      const val = this.value || '';
      if (/^\d+$/.test(val)) {
        table.column(0).search('^' + val + '$', true, false).draw();
      } else {
        table.column(0).search(val, false, true).draw();
      }
    });
  } else if (!$('#tablePengiriman').hasClass('dataTable')) {
    const table = tableElement.DataTable({
      responsive: true,
      lengthChange: true,
      autoWidth: false,
      order: [[0, 'desc']],
      buttons: ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis']
    });
    table.buttons().container().appendTo('#tablePengiriman_wrapper .col-md-6:eq(0)');
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
    var tgl = $btn.data('tgl') || '';
    var bVal = $btn.data('barang') || '';

    $('#id_pengiriman').val(id);
    $('#btnSave').attr('name', 'edit').html('<i class="fas fa-save mr-1"></i> Simpan Perubahan');
    $('#modalFormLabel').html('<i class="fas fa-edit mr-2 text-warning"></i>Edit Data Pengiriman Paket');

    // 1. Pastikan Option NIP Pelanggan ada di select sebelum di-set
    var $selNip = $('#nip_sgt_id');
    if ($selNip.length) {
      if (nip) {
        if ($selNip.find("option[value='" + nip + "']").length === 0) {
          var labelNip = nip + (nama ? ' - ' + nama : '');
          var newOption = new Option(labelNip, nip, true, true);
          $selNip.append(newOption);
        }
        $selNip.val(nip).trigger('change');
      } else {
        $selNip.val('').trigger('change');
      }
    }

    // 2. Set field identitas pengirim
    $('#nama_id').val(nama);
    $('#alamat_id').val(alamat);
    $('#no_ktp_id').val(ktp);
    $('#no_hp_id').val(hp);

    // 3. Set Jenis Barang
    var $selBarang = $('#jenis_barang');
    if (bVal && $selBarang.find("option[value='" + bVal + "']").length === 0) {
      $selBarang.append(new Option(bVal, bVal, true, true));
    }
    $selBarang.val(bVal).trigger('change');

    // 4. Set field lainnya
    $('#tanggal_pengiriman').val(tgl);
    $('#nama_penerima').val($btn.data('penerima') || '');
    $('#no_hp_penerima').val($btn.data('hppenerima') || '');
    $('#no_plat_id').val($btn.data('plat') || '');
    $('#kelas_id').val($btn.data('kelas') || '');
    $('#tujuan_id').val($btn.data('tujuan') || '');
    $('#status_pembayaran_id').val($btn.data('status') || '');
    $('#metode_pembayaran_id').val($btn.data('metode') || '');
    $('#jenis_rekening_id').val($btn.data('rekening') || '');
    $('#jumlah').val($btn.data('jumlah') || '');
    $('#keterangan').val($btn.data('keterangan') || '');

    $('#modalForm').modal('show');

    // 5. Pastikan nilai NIP tetap terpilih setelah modal selesai ditampilkan
    setTimeout(function () {
      if (nip && $selNip.length) {
        if ($selNip.find("option[value='" + nip + "']").length === 0) {
          var labelNip = nip + (nama ? ' - ' + nama : '');
          $selNip.append(new Option(labelNip, nip, true, true));
        }
        $selNip.val(nip).trigger('change.select2');
      }
    }, 100);
  });

  // Ganti event hapus dengan SweetAlert2 (Gunakan class unik agar tidak bentrok)
  $(document).on('click', '.btn-delete-pengiriman', function () {
    var id = $(this).data('id');
    Swal.fire({
      title: 'Konfirmasi Hapus Data?',
      text: "Data pengiriman paket ini akan dihapus permanen dari sistem!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#c62828',
      cancelButtonColor: '#6c757d',
      confirmButtonText: '<i class="fas fa-trash mr-1"></i> Ya, Hapus',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = '?hapus=' + id;
      }
    });
  });

  // Autofill saat jenis_barang dipilih di form pengiriman
  $(document).on('change select2:select', '#jenis_barang', function (e) {
    var val = $(this).val();
    if (e && e.params && e.params.data && e.params.data.id) {
      val = e.params.data.id;
    }
    if (typeof autofillOngkosBarang === 'function') {
      autofillOngkosBarang(val);
    } else {
      var $opt = $(this).find('option:selected');
      var biaya = $opt.attr('data-biaya') || $opt.data('biaya');
      if (biaya) {
        $('#jumlah').val(biaya).trigger('input').trigger('change');
      }
      if (!$('#status_pembayaran_id').val()) $('#status_pembayaran_id').val('1').trigger('change');
      if (!$('#metode_pembayaran_id').val()) $('#metode_pembayaran_id').val('2').trigger('change');
      if (!$('#jenis_rekening_id').val()) {
        var cVal = $('#jenis_rekening_id option:contains("CASH")').first().val();
        if (cVal) $('#jenis_rekening_id').val(cVal).trigger('change');
      }
    }
  });

  // Reset form saat modal ditutup
  $('#modalForm').on('hidden.bs.modal', function () {
    $('#formPengiriman')[0].reset();
    $('#id_pengiriman').val('');
    $('#nip_sgt_id').val('').trigger('change');
    $('#jenis_barang').val('').trigger('change');
    $('#btnSave').attr('name', 'tambah').html('<i class="fas fa-save mr-1"></i> Simpan Pengiriman');
    $('#modalFormLabel').html('<i class="fas fa-boxes mr-2 text-success"></i>Formulir Data Pengiriman Paket');
  });
});