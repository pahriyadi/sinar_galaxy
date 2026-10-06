<?php
if (!isset($base)) {
  $base = is_file('data_dashboard/dashboard.php') ? '' : '../';
}

// Anti-Cache HTTP Headers: Mencegah caching halaman HTML di browser klien
if (!headers_sent()) {
  header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0, post-check=0, pre-check=0");
  header("Pragma: no-cache");
  header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");
}

$app_favicon_header = function_exists('getSetting') ? getSetting('app_favicon', 'img/logo_sgt.png') : 'img/logo_sgt.png';
$app_name_header = function_exists('getSetting') ? strtoupper(getSetting('app_name', 'SINAR GALAXY TRAVEL')) : 'SINAR GALAXY TRAVEL';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <!-- Anti-Cache Meta Tags: Mengharuskan browser selalu memuat aset & skrip terbaru -->
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title><?= htmlspecialchars($app_name_header) ?></title>

  <!-- Google Font: Source Sans Pro -->
  <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap4.min.css">
  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css">
  <!-- Bootstrap 4 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <!-- AdminLTE 3 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
  <!-- OverlayScrollbars -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.4.0/css/OverlayScrollbars.min.css">
  <!-- Select2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
  <!-- DateRangePicker -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css">
  <!-- Bootstrap Colorpicker -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-colorpicker@3.4.0/dist/css/bootstrap-colorpicker.min.css">
  <!-- Bootstrap Slider -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-slider@11.0.2/dist/css/bootstrap-slider.min.css">
  <!-- Custom CSS (Paper White Design System) dengan Auto Cache Buster -->
  <link rel="stylesheet" href="<?= function_exists('asset_ver') ? asset_ver('assets/enhanced_style.css', $base) : $base . 'assets/enhanced_style.css?v=2.0' ?>">
  <link rel="stylesheet" href="<?= function_exists('asset_ver') ? asset_ver('assets/css/custom.css', $base) : $base . 'assets/css/custom.css?v=2.2' ?>">
  <link rel="icon" href="<?= $base ?><?= htmlspecialchars($app_favicon_header) ?>">
  
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<?php include __DIR__ . '/preloader.php'; ?>
<div class="wrapper">

