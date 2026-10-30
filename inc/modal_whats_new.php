<?php
/**
 * Modal & Controller: "Apa yang Baru?" & Riwayat Pembaruan Lengkap
 * Tema: Paper White v2.0 (Putih Bersih, Flat & Presisi Sempurna)
 * PO. Sinar Galaxy Travel
 */
$whatsNewData = file_exists(__DIR__ . '/whats_new_data.php') ? require __DIR__ . '/whats_new_data.php' : [];
$wnReleases = $whatsNewData['releases'] ?? [];
if (empty($wnReleases) && !empty($whatsNewData['version'])) {
    $wnReleases = [$whatsNewData];
}

$latestRelease = $wnReleases[0] ?? [];
$wnVersion = $latestRelease['version'] ?? ($whatsNewData['version'] ?? 'v3.5.0');
$wnDate = $latestRelease['release_date'] ?? ($whatsNewData['release_date'] ?? date('d F Y'));
$wnTitle = $latestRelease['title'] ?? ($whatsNewData['title'] ?? 'Pembaruan Sistem');
$wnHighlights = $latestRelease['highlights'] ?? ($whatsNewData['highlights'] ?? []);
$totalReleases = count($wnReleases);
?>

<!-- Modal Apa yang Baru (Paper White v2.0 - Putih Bersih) -->
<div class="modal fade no-print" id="modalWhatsNew" tabindex="-1" role="dialog" aria-labelledby="modalWhatsNewTitle" aria-hidden="true" style="z-index: 1070;">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document" style="max-width: 780px;">
    <div class="modal-content" style="border: 1px solid #b8b8b8 !important; border-radius: 4px !important; box-shadow: none !important; background: #ffffff !important;">
      
      <!-- Modal Header (Putih Bersih) -->
      <div class="modal-header" style="background: #ffffff !important; border-bottom: 1px solid #b8b8b8 !important; padding: 14px 20px; align-items: center;">
        <div class="d-flex align-items-center">
          <div style="width: 36px; height: 36px; border-radius: 4px; background: #ffffff; border: 1px solid #b8b8b8; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: #0d9f4f; font-size: 1.1rem;">
            <i class="fas fa-bullhorn"></i>
          </div>
          <div>
            <div class="d-flex align-items-center mb-0" style="gap: 8px;">
              <h5 class="modal-title font-weight-bold" id="modalWhatsNewTitle" style="font-size: 1.1rem; color: #333333; margin: 0; line-height: 1.2;">
                Apa yang Baru &amp; Riwayat Pembaruan
              </h5>
              <span class="badge" style="background: #e6f4ea; color: #076e34; border: 1px solid #0d9f4f; font-size: 0.72rem; font-weight: 700; padding: 3px 8px; border-radius: 3px;">
                <?= htmlspecialchars($wnVersion) ?>
              </span>
            </div>
            <small class="text-muted" style="font-size: 0.78rem; color: #666666 !important;">
              <i class="fas fa-calendar-alt mr-1 text-muted"></i>Rilis Aktif: <?= htmlspecialchars($wnDate) ?> &bull; <?= $totalReleases ?> Riwayat Versi Tersimpan
            </small>
          </div>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 1.4rem; color: #666666; opacity: 0.8;" onclick="markWhatsNewSeen()">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <!-- Tab Navigasi: Rilis Terbaru vs Riwayat Lengkap -->
      <div class="px-3 pt-2 bg-light border-bottom">
        <ul class="nav nav-tabs border-0" id="whatsNewTabs" role="tablist" style="gap: 4px;">
          <li class="nav-item">
            <a class="nav-link active font-weight-bold text-xs py-2 px-3" id="tab-latest-nav" data-toggle="tab" href="#pane-latest" role="tab" aria-controls="pane-latest" aria-selected="true" style="border: 1px solid #b8b8b8; border-bottom: none; border-radius: 4px 4px 0 0; background: #ffffff; color: #0d9f4f;">
              <i class="fas fa-star text-warning mr-1"></i> Rilis Terbaru (<?= htmlspecialchars($wnVersion) ?>)
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link font-weight-bold text-xs py-2 px-3" id="tab-history-nav" data-toggle="tab" href="#pane-history" role="tab" aria-controls="pane-history" aria-selected="false" style="border: 1px solid #cbd5e1; border-bottom: none; border-radius: 4px 4px 0 0; background: #f1f5f9; color: #475569;">
              <i class="fas fa-history text-primary mr-1"></i> Semua Riwayat Versi (<?= $totalReleases ?> Rilis)
            </a>
          </li>
        </ul>
      </div>

      <!-- Modal Body (Putih Bersih) -->
      <div class="modal-body" style="padding: 16px 20px; background: #ffffff !important; max-height: 490px; overflow-y: auto;">
        
        <div class="tab-content" id="whatsNewTabContent">
          
          <!-- TAB 1: RILIS TERBARU -->
          <div class="tab-pane fade show active" id="pane-latest" role="tabpanel" aria-labelledby="tab-latest-nav">
            
            <!-- Info Banner Bersih -->
            <div style="background: #ffffff; border: 1px solid #b8b8b8; border-left: 4px solid #0d9f4f; border-radius: 4px; padding: 10px 14px; margin-bottom: 14px; font-size: 0.82rem; color: #333333;">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <i class="fas fa-info-circle mr-1" style="color: #0d9f4f;"></i>
                  Fitur baru &amp; peningkatan aktif pada versi <strong><?= htmlspecialchars($wnVersion) ?></strong> (<?= htmlspecialchars($wnDate) ?>):
                </div>
                <span class="badge badge-success font-weight-bold px-2 py-1" style="background-color: #0d9f4f; font-size: 11px;">Rilis Terbaru</span>
              </div>
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

            <!-- Link ke Tab Riwayat -->
            <div class="mt-3 p-2 bg-light border rounded text-center text-xs text-muted">
              <i class="fas fa-history mr-1 text-primary"></i> Ingin melihat seluruh riwayat pembaruan versi terdahulu?
              <a href="javascript:void(0)" id="btnSwitchToHistory" class="font-weight-bold text-success ml-1" style="text-decoration: underline;">
                Buka Tab Riwayat Versi (<?= $totalReleases ?> Versi) &rarr;
              </a>
            </div>

          </div>

          <!-- TAB 2: SEMUA RIWAYAT VERSI (TIMELINE CHANGELOG) -->
          <div class="tab-pane fade" id="pane-history" role="tabpanel" aria-labelledby="tab-history-nav">
            
            <!-- Filter Search Bar -->
            <div class="mb-3">
              <div class="input-group input-group-sm">
                <div class="input-group-prepend">
                  <span class="input-group-text bg-white border-right-0" style="border-color: #b8b8b8;">
                    <i class="fas fa-search text-muted"></i>
                  </span>
                </div>
                <input type="text" id="wnHistorySearchInput" class="form-control form-control-sm border-left-0" placeholder="Ketik kata kunci untuk mencari riwayat (contoh: kursi, e-tiket, kargo, grafik, kasir)..." style="border-color: #b8b8b8;">
                <div class="input-group-append">
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="btnResetHistorySearch" title="Reset Pencarian">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </div>
            </div>

            <!-- List Accordion Versi -->
            <div class="accordion" id="accordionHistory">
              <?php foreach ($wnReleases as $idx => $rel): 
                $relVer = $rel['version'] ?? 'v3.0.0';
                $relDate = $rel['release_date'] ?? '-';
                $relTitle = $rel['title'] ?? 'Pembaruan';
                $relBadge = $rel['badge'] ?? 'Rilis';
                $relBadgeClass = $rel['badge_class'] ?? ($idx === 0 ? 'badge-success' : 'badge-secondary');
                $isLatest = !empty($rel['is_latest']) || ($idx === 0);
                $collapseId = 'collapseRel_' . preg_replace('/[^a-zA-Z0-9]/', '', $relVer);
                // Buka versi terbaru secara default
                $isExpanded = ($idx === 0);
              ?>
              <div class="card mb-2 border history-item" style="border-color: #b8b8b8 !important;" data-search="<?= strtolower(htmlspecialchars($relVer . ' ' . $relTitle . ' ' . json_encode($rel['highlights'] ?? []))) ?>">
                
                <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center" style="cursor: pointer;" data-toggle="collapse" data-target="#<?= $collapseId ?>" aria-expanded="<?= $isExpanded ? 'true' : 'false' ?>">
                  <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                    <span class="badge <?= $relBadgeClass ?> font-weight-bold px-2 py-1" style="font-size: 11px;">
                      <?= htmlspecialchars($relVer) ?>
                    </span>
                    <strong class="text-dark text-xs mb-0" style="font-size: 13px;">
                      <?= htmlspecialchars($relTitle) ?>
                    </strong>
                    <span class="badge badge-light border text-muted text-xs">
                      <i class="fas fa-calendar-alt mr-1"></i><?= htmlspecialchars($relDate) ?>
                    </span>
                    <?php if ($isLatest): ?>
                      <span class="badge badge-success font-weight-bold text-xs" style="background:#0d9f4f; font-size:10px;">
                        <i class="fas fa-check-circle mr-1"></i>Aktif Sekarang
                      </span>
                    <?php endif; ?>
                  </div>
                  <div class="text-muted text-xs">
                    <i class="fas fa-chevron-down chevron-icon"></i>
                  </div>
                </div>

                <div id="<?= $collapseId ?>" class="collapse <?= $isExpanded ? 'show' : '' ?>" data-parent="#accordionHistory">
                  <div class="card-body p-3 bg-white border-top">
                    <div class="row" style="gap: 8px 0;">
                      <?php foreach (($rel['highlights'] ?? []) as $hItem): ?>
                      <div class="col-12">
                        <div class="p-2 border rounded" style="background: #fafafa; border-color: #e2e8f0 !important;">
                          <div class="d-flex align-items-start">
                            <div style="width: 26px; height: 26px; min-width: 26px; border-radius: 4px; background: #ffffff; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; margin-right: 10px; color: #0d9f4f; font-size: 0.85rem; margin-top: 2px;">
                              <i class="<?= htmlspecialchars($hItem['icon'] ?? 'fas fa-check') ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                              <div class="d-flex align-items-center mb-1 flex-wrap" style="gap: 4px;">
                                <span class="badge badge-light border text-muted" style="font-size: 10px; font-weight: 700; text-transform: uppercase;">
                                  <?= htmlspecialchars($hItem['category'] ?? 'Fitur') ?>
                                </span>
                                <strong class="text-dark" style="font-size: 12.5px;">
                                  <?= htmlspecialchars($hItem['title'] ?? '') ?>
                                </strong>
                              </div>
                              <div class="text-muted text-xs" style="line-height: 1.45; font-size: 11.5px;">
                                <?= htmlspecialchars($hItem['desc'] ?? '') ?>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>

              </div>
              <?php endforeach; ?>
            </div>

            <div id="wnHistoryEmpty" class="text-center py-4 text-muted text-xs" style="display: none;">
              <i class="fas fa-search-minus fa-2x mb-2 text-secondary d-block"></i>
              Tidak ditemukan riwayat pembaruan yang cocok dengan kata kunci tersebut.
            </div>

          </div>

        </div>

      </div>

      <!-- Modal Footer (Putih Bersih) -->
      <div class="modal-footer" style="background: #ffffff !important; border-top: 1px solid #b8b8b8 !important; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center;">
        <span class="text-muted" style="font-size: 0.78rem; color: #666666 !important;">
          <i class="fas fa-shield-alt mr-1" style="color: #0d9f4f;"></i>
          SIM Sinar Galaxy Travel &bull; <?= htmlspecialchars($wnVersion) ?> (<?= $totalReleases ?> Versi Rilis Tercatat)
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
      // Default to tab latest when opened
      $('#tab-latest-nav').tab('show');
      $('#modalWhatsNew').modal('show');
    });

    // 2. Tab styling toggle
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
      $('#whatsNewTabs .nav-link').css({
        'background': '#f1f5f9',
        'color': '#475569',
        'border-color': '#cbd5e1'
      });
      $(this).css({
        'background': '#ffffff',
        'color': '#0d9f4f',
        'border-color': '#b8b8b8'
      });
    });

    // 3. Tombol switch ke tab history
    $('#btnSwitchToHistory').on('click', function(e) {
      e.preventDefault();
      $('#tab-history-nav').tab('show');
    });

    // 4. Live search riwayat pembaruan
    $('#wnHistorySearchInput').on('keyup', function() {
      let q = $(this).val().toLowerCase().trim();
      let matchCount = 0;

      if (!q) {
        $('.history-item').show();
        $('#wnHistoryEmpty').hide();
        return;
      }

      $('.history-item').each(function() {
        let text = $(this).data('search') || '';
        if (text.indexOf(q) !== -1) {
          $(this).show();
          // Auto expand matched item
          $(this).find('.collapse').collapse('show');
          matchCount++;
        } else {
          $(this).hide();
        }
      });

      if (matchCount === 0) {
        $('#wnHistoryEmpty').show();
      } else {
        $('#wnHistoryEmpty').hide();
      }
    });

    // Reset search
    $('#btnResetHistorySearch').on('click', function() {
      $('#wnHistorySearchInput').val('').trigger('keyup').focus();
    });

    // 5. Cek apakah pengguna sudah melihat versi ini
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
