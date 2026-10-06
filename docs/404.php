<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Halaman Tidak Ditemukan | Sinar Galaxy Travel</title>
    <link rel="icon" href="img/logo_sgt.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: #f1f5f9;
            color: #1e293b;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .notfound-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 48px 32px;
            text-align: center;
            max-width: 400px;
        }
        .notfound-logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin-bottom: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        }
        .notfound-title {
            font-size: 48px;
            font-weight: 700;
            color: #2563eb;
            margin-bottom: 10px;
        }
        .notfound-desc {
            font-size: 18px;
            color: #64748b;
            margin-bottom: 28px;
        }
        .notfound-btn {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 12px 32px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: background 0.2s;
        }
        .notfound-btn:hover {
            background: #1e40af;
        }
        .notfound-footer {
            margin-top: 32px;
            color: #94a3b8;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="notfound-container">
        <img src="img/logo_sgt.png" alt="Logo Sinar Galaxy Travel" class="notfound-logo">
        <div class="notfound-title"><i class="fas fa-exclamation-triangle"></i> 404</div>
        <div class="notfound-desc">Maaf, halaman yang Anda cari tidak ditemukan.<br>Silakan periksa kembali URL atau kembali ke beranda.</div>
        <a href="index.php" class="notfound-btn"><i class="fas fa-home"></i> Kembali ke Beranda</a>
    </div>
    <div class="notfound-footer">&copy; <?php echo date('Y'); ?> Sinar Galaxy Travel</div>
</body>
</html> 