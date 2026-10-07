<?php
/**
 * Router / Redirection untuk E-Resi Pengiriman Cargo
 */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$format = isset($_GET['format']) ? trim($_GET['format']) : '';

$queryParams = [];
if ($id > 0) $queryParams['id'] = $id;
if (!empty($token)) $queryParams['token'] = $token;
if (!empty($format)) $queryParams['format'] = $format;

$queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
header('Location: pelanggan.php' . $queryString);
exit;
