<?php
// Pastikan config sudah di-include sebelum memanggil file ini
$page_title = isset($page_title) ? $page_title . ' - Toko Online' : 'Toko Online';

// Hitung item keranjang
$cart_count = 0;
if (isLoggedIn() && !isAdmin()) {
    $db = getDB();
    $uid = $_SESSION['user_id'];
    $r = $db->query("SELECT SUM(jumlah) as total FROM keranjang WHERE user_id = $uid");
    $cart_count = $r->fetch_assoc()['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
</head>
<body>

<div class="navbar">
    <div class="navbar-inner">

        <a href="<?= BASE_URL ?>index.php" class="navbar-brand">
            TokoKu
        </a>

        <!-- Tombol Hamburger -->
        <button class="nav-toggle" id="navToggle" type="button">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <!-- Overlay -->
        <div class="nav-overlay" id="navOverlay"></div>

        <ul class="navbar-nav" id="navbarNav">
            <li><a href="<?= BASE_URL ?>index.php">Beranda</a></li>
            <?php if (isLoggedIn()): ?>
                <?php if (isAdmin()): ?>
                    <li>
                        <a href="<?= BASE_URL ?>admin/dashboard.php">
                            Admin Panel
                        </a>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="<?= BASE_URL ?>user/keranjang.php">
                            Keranjang
                            <?php if ($cart_count > 0): ?>
                                <span class="badge-cart"><?= $cart_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <li>
                        <a href="<?= BASE_URL ?>user/pesanan.php">
                            Pesanan Saya
                        </a>
                    </li>

                    <li>
                        <a href="<?= BASE_URL ?>user/profil.php">
                            Profil
                        </a>
                    </li>
                <?php endif; ?>

                <li>
                    <a href="<?= BASE_URL ?>logout.php" class="nav-link-danger">
                        Keluar
                    </a>
                </li>

            <?php else: ?>

                <li>
                    <a href="<?= BASE_URL ?>login.php">
                        Masuk
                    </a>
                </li>

                <li>
                    <a href="<?= BASE_URL ?>register.php" class="nav-link-cta">
                        Daftar
                    </a>
                </li>

            <?php endif; ?>
        </ul>

    </div>
</div>
