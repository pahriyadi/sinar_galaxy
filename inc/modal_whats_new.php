<?php
/**
 * Modal & Controller: "Apa yang Baru?" (Release Notes / Changelog Modal)
 * Tema: Paper White v2.0 (Putih Bersih, Flat & Presisi Sempurna)
 * PO. Sinar Galaxy Travel
 */
$whatsNewData = file_exists(__DIR__ . '/whats_new_data.php') ? require __DIR__ . '/whats_new_data.php' : [];
$wnVersion = $whatsNewData['version'] ?? 'v3.3.0';
$wnDate = $whatsNewData['release_date'] ?? date('d F Y');
$wnTitle = $whatsNewData['title'] ?? 'Pembaruan Sistem';
$wnBadge = $whatsNewData['badge'] ?? 'Rilis Terbaru';
$wnHighlights = $whatsNewData['highlights'] ?? [];
?>

<!-- Modal Apa yang Baru (Paper White v2.0 - Putih Bersih) -->
<div class="modal fade no-print" id="modalWhatsNew" tabindex="-1" role="dialog" aria-labelledby="modalWhatsNewTitle" aria-hidden="true" style="z-index: 1070;">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document" style="max-width: 740px;">
    <div class="modal-content" style="border: 1px solid #b8b8b8 !important; border-radius: 4px !important; box-shadow: none !important; background: #ffffff !important;">
      
      <!-- Modal Header (Putih Bersih) -->
      <div class="modal-header" style="background: #ffffff !important; border-bottom: 1px solid #b8b8b8 !important; padding: 14px 20px; align-items: center;">
        <div class="d-flex align-items-center">
          <div style="width: 34px; height: 34px; border-radius: 4px; background: #ffffff; border: 1px solid #b8b8b8; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: #0d9f4f; font-size: 1.05rem;">
            <i class="fas fa-bullhorn"></i>
          </div>
          <div>
            <div class="d-flex align-items-center mb-0" style="gap: 8px;">
              <h5 class="modal-title font-weight-bold" id="modalWhatsNewTitle" style="font-size: 1.1rem; color: #333333; margin: 0; line-height: 1.2;">
                Apa yang Baru di SIM Sinar Galaxy Travel
              </h5>
              <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #0d9f4f; font-size: 0.7rem; font-weight: 700; padding: 3px 8px; border-radius: 3px;">
                <?= htmlspecialchars($wnVersion) ?>
              </span>
            </div>
            <small class="text-muted" style="font-size: 0.78rem; color: #666666 !important;">
              <i class="fas fa-calendar-alt mr-1 text-muted"></i>Rilis Pembaruan Sistem &bull; <?= htmlspecialchars($wnDate) ?>
            </small>
          </div>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 1.4rem; color: #666666; opacity: 0.8;" onclick="markWhatsNewSeen()">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <!-- Modal Body (Putih Bersih) -->
      <div class="modal-body" style="padding: 18px 20px; background: #ffffff !important; max-height: 480px; overflow-y: auto;">
        
        <!-- Info Banner Bersih (Paper White Alert) -->
        <div style="background: #ffffff; border: 1px solid #b8b8b8; border-left: 4px solid #0d9f4f; border-radius: 4px; padding: 10px 14px; margin-bottom: 14px; font-size: 0.82rem; color: #333333;">
          <i class="fas fa-info-circle mr-1" style="color: #0d9f4f;"></i>
          Berikut adalah rangkuman fitur baru, otomatisasi, dan peningkatan yang telah aktif pada sistem operasional Anda:
        </div>

        <div class="row" style="gap: 10px 0;">
          <?php foreach ($wnHighlights as $item): 
            $cat = $item['category'] ?? 'Pembaruan';
            $icon = $item['icon'] ?? 'fas fa-check';
            $title = $item['title'] ?? '';
            $desc = $item['desc'] ?? '';
          ?>
          <div class="col-12">
            <div style="background: #ffffff; border: 1px solid #b8b8b8; border-radius: 4px; padding: 12px 14px; box-shadow: none;">
              <div class="d-flex align-items-start">
                <!-- Icon Box Putih Bersih -->
                <div style="width: 32px; height: 32px; min-width: 32px; border-radius: 4px; background: #ffffff; border: 1px solid #b8b8b8; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: #0d9f4f; font-size: 0.95rem; margin-top: 2px;">
                  <i class="<?= htmlspecialchars($icon) ?>"></i>
                </div>
                <!-- Konten -->
                <div class="flex-grow-1">
                  <div class="d-flex align-items-center mb-1 flex-wrap" style="gap: 6px;">
                    <span class="badge" style="background: #f8f9fa; color: #444444; border: 1px solid #b8b8b8; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; padding: 2px 6px; border-radius: 3px;">
                      <?= htmlspecialchars($cat) ?>
                    </span>
                    <span class="font-weight-bold" style="font-size: 0.88rem; color: #333333;">
                      <?= htmlspecialchars($title) ?>
                    </span>
                  </div>
                  <p class="mb-0" style="font-size: 0.8rem; line-height: 1.5; color: #555555;">
                    <?= htmlspecialchars($desc) ?>
                  </p>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

      </div>

      <!-- Modal Footer (Putih Bersih) -->
      <div class="modal-footer" style="background: #ffffff !important; border-top: 1px solid #b8b8b8 !important; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center;">
        <span class="text-muted" style="font-size: 0.78rem; color: #666666 !important;">
          <i class="fas fa-check-circle mr-1" style="color: #0d9f4f;"></i>
          Pemberitahuan ini otomatis tersimpan di cache dan hanya muncul sekali per rilis pembaruan.
        </span>
        <button type="button" class="btn btn-success btn-sm btn-flat font-weight-bold" data-dismiss="modal" onclick="markWhatsNewSeen()" style="background-color: #0d9f4f !important; border: 1px solid #076e34 !important; color: #ffffff !important; border-radius: 4px !important; box-shadow: none !important; padding: 6px 20px; font-size: 0.85rem;">
          <i class="fas fa-check mr-1"></i> Mengerti &amp; Tutup
        </button>
      </div>

    </div>
  </div>
</div>

<script>
(function() {
  const WHATS_NEW_KEY = 'sgt_whats_new_seen_version';
  const CURRENT_VERSION = '<?= $wnVersion ?>';

  // Fungsi menandai modal sudah dilihat di localStorage
  window.markWhatsNewSeen = function() {
    try {
      localStorage.setItem(WHATS_NEW_KEY, CURRENT_VERSION);
    } catch(e) {}
  };

  // Cek saat DOM selesai dimuat
  $(document).ready(function() {
    // 1. Event listener tombol di sidebar
    $(document).on('click', '#btnOpenWhatsNew', function(e) {
      e.preventDefault();
      $('#modalWhatsNew').modal('show');
    });

    // 2. Cek apakah pengguna sudah melihat versi ini
    let seenVersion = null;
    try {
      seenVersion = localStorage.getItem(WHATS_NEW_KEY);
    } catch(e) {}

    // Jika belum pernah lihat atau ada pembaruan versi baru, picu modal otomatis!
    if (seenVersion !== CURRENT_VERSION) {
      setTimeout(function() {
        $('#modalWhatsNew').modal({
          backdrop: 'static',
          keyboard: true
        });
        $('#modalWhatsNew').modal('show');

        $('#modalWhatsNew').on('hidden.bs.modal', function() {
          markWhatsNewSeen();
        });
      }, 700);
    }
  });
})();
</script>
