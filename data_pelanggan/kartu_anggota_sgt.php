<!-- Modal Kartu Pelanggan -->
<div class="modal fade" id="modalKartu" tabindex="-1" role="dialog" aria-labelledby="modalKartuLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:370px;">
    <div class="modal-content bg-white border rounded shadow">
      <div class="modal-body p-0 d-flex justify-content-center align-items-center" style="min-height:240px;">

        <!-- Kartu Template (Ukuran KTP 336 x 212 px) -->
        <style>
          .kartu-pelanggan-sgt {
            width: 336px;
            height: 212px;
            background: #181818;
            border-radius: 14px;
            box-shadow: 0 4px 16px #0008;
            position: relative;
            color: #FFD700;
            font-family: sans-serif;
            overflow: hidden;
            display: block;
          }

          .kartu-pelanggan-sgt img.logo-bg {
            position: absolute;
            top: 150%;
            left: 50%;
            transform: translate(-50%, -50%) scale(1.5);
            opacity: 0.08;
            z-index: 0;
          }

          .kartu-pelanggan-sgt .glossy {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.25), rgba(255, 255, 255, 0));
            z-index: 1;
            pointer-events: none;
            border-radius: 14px;
          }

          .kartu-pelanggan-sgt .footer-kartu {
            position: absolute;
            bottom: 12px;
            font-size: 0.8em;
            color: #fff7;
          }

          .kartu-pelanggan-sgt .footer-kartu.left {
            left: 20px;
          }

          .kartu-pelanggan-sgt .footer-kartu.right {
            right: 20px;
            font-size: 0.95em;
            font-weight: bold;
            letter-spacing: 1.2px;
            color: #FFD700;
          }
        </style>

        <!-- Sisi Depan -->
        <div id="kartuPelangganDepan" class="kartu-pelanggan-sgt">
          <img src="../img/logo_sgt.png" alt="Logo" class="logo-bg">
          <div class="glossy"></div>

          <!-- Header -->
          <div style="padding:14px 18px 6px 18px;text-align:center;z-index:2;position:relative;">
            <div style="font-size:1.15em;font-weight:bold;letter-spacing:1.2px;">SINAR GALAXY TRAVEL</div>
            <div style="font-size:1em;font-weight:bold;letter-spacing:1.2px;">KARTU TANDA ANGGOTA</div>
          </div>

          <!-- Data -->
          <div style="padding:10px 18px 0 18px;z-index:2;position:relative;">
            <div style="display:flex;align-items:center;font-size:1em;margin-bottom:3px;">
              <div style="width:90px;font-weight:bold;">Nama</div>
              <div style="flex:1;">: <span id="kp_nama"></span></div>
            </div>
            <div style="display:flex;align-items:center;font-size:1em;margin-bottom:3px;">
              <div style="width:90px;font-weight:bold;">Alamat</div>
              <div style="flex:1;">: <span id="kp_alamat"></span></div>
            </div>
            <div style="display:flex;align-items:center;font-size:1em;margin-bottom:3px;">
              <div style="width:90px;font-weight:bold;">No KTP</div>
              <div style="flex:1;">: <span id="kp_no_ktp"></span></div>
            </div>
            <div style="display:flex;align-items:center;font-size:1em;margin-bottom:3px;">
              <div style="width:90px;font-weight:bold;">No HP</div>
              <div style="flex:1;">: <span id="kp_no_hp"></span></div>
            </div>
            <div style="display:flex;align-items:center;font-size:1em;margin-bottom:3px;">
              <div style="width:90px;font-weight:bold;">NIP SGT</div>
              <div style="flex:1;">: <span id="kp_nip_sgt"></span></div>
            </div>
          </div>

          <div class="footer-kartu left">v1.0</div>
          <div class="footer-kartu right">KP-SGT</div>
        </div>

        <!-- Sisi Belakang -->
        <div id="kartuPelangganBelakang" class="kartu-pelanggan-sgt" style="display:none;">
          <img src="../img/logo_sgt.png" alt="Logo" class="logo-bg">
          <div class="glossy"></div>

          <div style="padding:18px 18px 0 18px;position:relative;z-index:2;">
            <div style="font-size:1.1em;font-weight:bold;letter-spacing:1.1px;text-align:center;">KEUNTUNGAN ANGGOTA</div>
            <ul style="padding-left:24px;margin:10px 0 0 0;line-height:1.6;font-size:1em;">
              <li>Setiap Penumpang terlindungi oleh asuransi dari ASDP</li>
              <li>Kontak PO Sumbawa : 081763333330</li>
              <li>Kontak PO Mataram : 082339860600</li>
            </ul>
          </div>

          <div class="footer-kartu left">v1.0</div>
          <div class="footer-kartu right">www.sinargalaxy.my.id</div>
        </div>

      </div>
      <div class="modal-footer justify-content-center">
        <button class="btn btn-dark" id="btnFlipKartu"><i class="fas fa-sync-alt"></i> Tukar Sisi</button>
        <button class="btn btn-dark" id="btnDownloadKartu"><i class="fas fa-download"></i> Download JPG</button>
        <button class="btn btn-secondary" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
