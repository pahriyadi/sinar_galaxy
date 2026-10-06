<?php
$base = is_file('data_dashboard/dashboard.php') ? '' : '../';
$app_name_hdr = function_exists('getSetting') ? getSetting('app_name', 'Sinar Galaxy') : 'Sinar Galaxy';
$app_favicon_hdr = function_exists('getSetting') ? getSetting('app_favicon', 'img/logo_sgt.png') : 'img/logo_sgt.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title><?= strtoupper(htmlspecialchars($app_name_hdr)) ?> TRAVEL</title>

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
  <!-- AdminLTE -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
  <!-- Custom CSS (Paper White Design System) -->
  <link rel="stylesheet" href="<?= $base ?>assets/css/custom.css?v=2.2">
  <link rel="icon" href="<?= $base . htmlspecialchars($app_favicon_hdr) ?>">
</head>
<body class="hold-transition sidebar-mini layout-fixed sidebar-collapse">
<?php include __DIR__ . '/preloader.php'; ?>
<div class="wrapper">

  <!-- Navbar -->
  <?php include __DIR__ . '/navbar.php'; ?>
  <!-- Sidebar -->
  <?php include __DIR__ . '/sidebar.php'; ?>