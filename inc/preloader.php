<?php
/**
 * Universal Page Preloader (Glassmorphism Paper White)
 * Sinar Galaxy Travel
 */
if (!isset($base)) {
    $base = is_file('data_dashboard/dashboard.php') ? '' : '../';
}
$preloader_logo = function_exists('getSetting') ? getSetting('app_logo', 'img/logo_sgt.png') : 'img/logo_sgt.png';
$preloader_name = function_exists('getSetting') ? getSetting('app_name', 'Sinar Galaxy') : 'Sinar Galaxy';
?>
<!-- Page Preloader -->
<div id="page-preloader" class="page-preloader">
    <div class="preloader-spinner-wrapper">
        <div class="preloader-logo-container">
            <img src="<?= $base . htmlspecialchars($preloader_logo) ?>" alt="Logo <?= htmlspecialchars($preloader_name) ?> Travel" class="preloader-logo-img">
            <div class="preloader-spinner"></div>
        </div>
        <div class="preloader-title"><?= strtoupper(htmlspecialchars($preloader_name)) ?> TRAVEL</div>
        <div class="preloader-text">Memuat data...</div>
    </div>
</div>
