<?php
require_once '../config.php';
requireLogin();
if (isAdmin()) { header('Location: ' . BASE_URL . 'admin/dashboard.php'); exit; }

$db  = getDB();
$uid = $_SESSION['user_id'];
$msg = '';

// ─────────────────────────────────────────────
// BELI LANGSUNG: simpan ke session lalu redirect
// ─────────────────────────────────────────────
if (isset($_GET['aksi']) && $_GET['aksi'] === 'beli_langsung' && isset($_GET['id'])) {
    $pid = (int)$_GET['id'];
    $jumlah = max(1, (int)($_GET['jumlah'] ?? 1));
    $produk = $db->query("SELECT * FROM produk WHERE id=$pid AND status='aktif'")->fetch_assoc();
    if ($produk && $produk['stok'] >= $jumlah) {
        $_SESSION['beli_langsung'] = [
            'produk_id' => $pid,
            'nama'      => $produk['nama'],
            'harga'     => $produk['harga'],
            'foto'      => $produk['foto'],
            'jumlah'    => $jumlah,
            'subtotal'  => $produk['harga'] * $jumlah,
        ];
        header('Location: keranjang.php?mode=langsung');
    } else {
        header('Location: ' . BASE_URL . 'index.php?error=stok');
    }
    exit;
}

// ─────────────────────────────────────────────
// CHECKOUT BELI LANGSUNG (POST)
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_langsung'])) {
    $bl = $_SESSION['beli_langsung'] ?? null;
    $alamat  = sanitize($_POST['alamat_pengiriman'] ?? '');
    $catatan = sanitize($_POST['catatan'] ?? '');

    if (!$bl) {
        $msg = '<div class="alert alert-danger">Sesi beli langsung tidak ditemukan. Silakan coba lagi.</div>';
    } elseif (empty($alamat)) {
        $msg = '<div class="alert alert-danger">Alamat pengiriman wajib diisi.</div>';
    } else {
        // Cek stok terkini
        $cek = $db->query("SELECT stok FROM produk WHERE id={$bl['produk_id']} AND status='aktif'")->fetch_assoc();
        if (!$cek || $cek['stok'] < $bl['jumlah']) {
            $msg = '<div class="alert alert-danger">Stok produk tidak mencukupi.</div>';
        } else {
            $total = $bl['subtotal'];
            $kode  = 'ORD-' . date('YmdHis') . '-' . $uid;

            $st = $db->prepare("INSERT INTO pesanan (user_id, kode_pesanan, total, alamat_pengiriman, catatan) VALUES (?,?,?,?,?)");
            $st->bind_param("issss", $uid, $kode, $total, $alamat, $catatan);
            $st->execute();
            $pesanan_id = $db->insert_id;

            $subtotal = $bl['subtotal'];
            $st2 = $db->prepare("INSERT INTO detail_pesanan (pesanan_id, produk_id, nama_produk, harga, jumlah, subtotal) VALUES (?,?,?,?,?,?)");
            $st2->bind_param("iisdid", $pesanan_id, $bl['produk_id'], $bl['nama'], $bl['harga'], $bl['jumlah'], $subtotal);
            $st2->execute();

            $db->query("UPDATE produk SET stok = stok - {$bl['jumlah']} WHERE id={$bl['produk_id']}");

            // Hapus session beli langsung
            unset($_SESSION['beli_langsung']);

            header('Location: pesanan.php?sukses=1'); exit;
        }
    }
}

// ─────────────────────────────────────────────
// FITUR KERANJANG NORMAL (tidak berubah)
// ─────────────────────────────────────────────

// Tambah ke keranjang
if (isset($_GET['aksi']) && $_GET['aksi'] === 'tambah' && isset($_GET['id'])) {
    $pid = (int)$_GET['id'];
    $produk = $db->query("SELECT stok FROM produk WHERE id=$pid AND status='aktif'")->fetch_assoc();
    if ($produk && $produk['stok'] > 0) {
        $st = $db->prepare("INSERT INTO keranjang (user_id, produk_id, jumlah) VALUES (?,?,1) ON DUPLICATE KEY UPDATE jumlah = jumlah + 1");
        $st->bind_param("ii", $uid, $pid);
        $st->execute();
        $msg = '<div class="alert alert-success">Produk ditambahkan ke keranjang!</div>';
    }
}

// Hapus item
if (isset($_GET['hapus'])) {
    $kid = (int)$_GET['hapus'];
    $db->query("DELETE FROM keranjang WHERE id=$kid AND user_id=$uid");
    header('Location: keranjang.php'); exit;
}

// Update jumlah
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_jumlah'])) {
    foreach ($_POST['jumlah'] as $kid => $jml) {
        $jml = max(1, (int)$jml);
        $db->query("UPDATE keranjang SET jumlah=$jml WHERE id=$kid AND user_id=$uid");
    }
    $msg = '<div class="alert alert-success">Keranjang diperbarui.</div>';
}

// Checkout dari keranjang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $alamat = sanitize($_POST['alamat_pengiriman'] ?? '');
    $catatan = sanitize($_POST['catatan'] ?? '');
    $user = $db->query("SELECT alamat FROM users WHERE id=$uid")->fetch_assoc();

    $items = $db->query("SELECT k.id, k.jumlah, p.nama, p.harga, p.stok FROM keranjang k JOIN produk p ON k.produk_id=p.id WHERE k.user_id=$uid")->fetch_all(MYSQLI_ASSOC);

    if (empty($items)) {
        $msg = '<div class="alert alert-danger">Keranjang kosong.</div>';
    } elseif (empty($alamat)) {
        $msg = '<div class="alert alert-danger">Alamat pengiriman wajib diisi.</div>';
    } else {
        $total = 0;
        $stok_ok = true;
        foreach ($items as $item) {
            if ($item['jumlah'] > $item['stok']) { $stok_ok = false; break; }
            $total += $item['harga'] * $item['jumlah'];
        }
        if (!$stok_ok) {
            $msg = '<div class="alert alert-danger">Stok beberapa produk tidak mencukupi.</div>';
        } else {
            $kode = 'ORD-' . date('YmdHis') . '-' . $uid;
            $st = $db->prepare("INSERT INTO pesanan (user_id, kode_pesanan, total, alamat_pengiriman, catatan) VALUES (?,?,?,?,?)");
            $st->bind_param("issss", $uid, $kode, $total, $alamat, $catatan);
            $st->execute();
            $pesanan_id = $db->insert_id;

            foreach ($items as $item) {
                $subtotal = $item['harga'] * $item['jumlah'];
                $st2 = $db->prepare("INSERT INTO detail_pesanan (pesanan_id, produk_id, nama_produk, harga, jumlah, subtotal) VALUES (?,?,?,?,?,?)");
                $pid2 = $db->query("SELECT produk_id FROM keranjang WHERE id=" . $item['id'])->fetch_assoc()['produk_id'] ?? 0;
                $st2->bind_param("iisdid", $pesanan_id, $pid2, $item['nama'], $item['harga'], $item['jumlah'], $subtotal);
                $st2->execute();
                $db->query("UPDATE produk SET stok = stok - {$item['jumlah']} WHERE id=$pid2");
            }
            $db->query("DELETE FROM keranjang WHERE user_id=$uid");
            header('Location: pesanan.php?sukses=1'); exit;
        }
    }
}

// Ambil keranjang
$keranjang = $db->query("SELECT k.id as kid, k.jumlah, p.id as pid, p.nama, p.harga, p.stok, p.foto FROM keranjang k JOIN produk p ON k.produk_id=p.id WHERE k.user_id=$uid")->fetch_all(MYSQLI_ASSOC);
$total = array_sum(array_map(fn($i) => $i['harga'] * $i['jumlah'], $keranjang));

$user_data = $db->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();

// Mode beli langsung
$mode_langsung = (($_GET['mode'] ?? '') === 'langsung') && isset($_SESSION['beli_langsung']);
$bl = $mode_langsung ? $_SESSION['beli_langsung'] : null;

$page_title = $mode_langsung ? 'Beli Langsung' : 'Keranjang Belanja';
require_once '../includes/header.php';
?>

<div class="container main-content">
    <div class="breadcrumb">
        <a href="<?= BASE_URL ?>index.php">Beranda</a> <span>&raquo;</span>
        <?= $mode_langsung ? 'Beli Langsung' : 'Keranjang Belanja' ?>
    </div>
    <?= $msg ?>

<?php if ($mode_langsung && $bl): ?>
    <!-- ══════════════════════════════════════ -->
    <!-- TAMPILAN BELI LANGSUNG                 -->
    <!-- ══════════════════════════════════════ -->
    <div style="display:flex; gap:15px; flex-wrap:wrap; align-items:flex-start;">
        <div style="flex:3; min-width:0;">
            <div class="box">
                <div class="box-title">Ringkasan Produk</div>
                <div class="box-body" style="padding:0;">
                    <table class="table">
                        <thead><tr><th>Produk</th><th>Harga</th><th>Jumlah</th><th>Subtotal</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>
                                    <?php if ($bl['foto'] && file_exists(UPLOAD_DIR . $bl['foto'])): ?>
                                        <img src="<?= BASE_URL ?>uploads/products/<?= $bl['foto'] ?>" class="img-product" style="float:left; margin-right:8px;">
                                    <?php endif; ?>
                                    <?= htmlspecialchars($bl['nama']) ?>
                                </td>
                                <td><?= formatRupiah($bl['harga']) ?></td>
                                <td><?= $bl['jumlah'] ?></td>
                                <td><?= formatRupiah($bl['subtotal']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div style="flex:1; min-width:240px;">
            <div class="cart-total-box">
                <table>
                    <tr><td>Subtotal:</td><td class="text-right"><?= formatRupiah($bl['subtotal']) ?></td></tr>
                    <tr><td>Ongkir:</td><td class="text-right">Gratis</td></tr>
                    <tr class="total-row"><td>TOTAL:</td><td class="text-right"><?= formatRupiah($bl['subtotal']) ?></td></tr>
                </table>
            </div>
            <div class="box" style="margin-top:12px;">
                <div class="box-title">Checkout</div>
                <div class="box-body">
                    <form method="POST">
                        <div class="form-group">
                            <label>Alamat Pengiriman *</label>
                            <textarea name="alamat_pengiriman" class="form-control" rows="3" required><?= htmlspecialchars($user_data['alamat'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Catatan (opsional)</label>
                            <textarea name="catatan" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="submit" name="checkout_langsung" class="btn btn-danger btn-block">Buat Pesanan</button>
                        <a href="<?= BASE_URL ?>index.php" class="btn btn-block" style="margin-top:6px; text-align:center;">Batal</a>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ══════════════════════════════════════ -->
    <!-- TAMPILAN KERANJANG NORMAL              -->
    <!-- ══════════════════════════════════════ -->
    <?php if (empty($keranjang)): ?>
        <div class="box"><div class="box-body text-center" style="padding:30px;">
            <p>Keranjang belanja Anda kosong.</p>
            <a href="<?= BASE_URL ?>index.php" class="btn btn-primary mt-10">Belanja Sekarang</a>
        </div></div>
    <?php else: ?>
    <div style="display:flex; gap:15px; flex-wrap:wrap; align-items:flex-start;">
        <div style="flex:2; min-width:300px;">
            <div class="box">
                <div class="box-title">Keranjang Belanja (<?= count($keranjang) ?> item)</div>
                <div class="box-body" style="padding:0;">
                    <form method="POST">
                        <input type="hidden" name="update_jumlah" value="1">
                        <div class="table-responsive">

                            <table class="table">
                                <tbody>
                                    <thead>
                                        <tr>
                                            <th>Produk</th>
                                            <th>Harga</th>
                                            <th>Jumlah</th>
                                            <th>Subtotal</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <?php foreach ($keranjang as $item): ?>
                                    <tr>
                                        <td>
                                            <?php if ($item['foto'] && file_exists(UPLOAD_DIR . $item['foto'])): ?>
                                                <img src="<?= BASE_URL ?>uploads/products/<?= $item['foto'] ?>" class="img-product" style="float:left; margin-right:8px;">
                                                <?php endif; ?>
                                                <?= $item['nama'] ?>
                                                <br><small class="text-muted">Stok: <?= $item['stok'] ?></small>
                                        </td>
                                        <td><?= formatRupiah($item['harga']) ?></td>
                                        <td>
                                            <input type="number"
                                            name="jumlah[<?= $item['kid'] ?>]"
                                            value="<?= $item['jumlah'] ?>"
                                            min="1"
                                            max="<?= $item['stok'] ?>"
                                            onchange="this.form.submit()"
                                            style="width:60px; padding:4px; border:1px solid #ccc; border-radius:3px;">
                                        </td>
                                        <td><?= formatRupiah($item['harga'] * $item['jumlah']) ?></td>
                                        <td style="text-align:center; white-space:nowrap;">
                                            <a href="keranjang.php?hapus=<?= $item['kid'] ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('Hapus item ini?')">
                                            ×
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    </form>
                </div>
            </div>
        </div>

        <div style="flex:1; min-width:240px;">
            <div class="cart-total-box">
                <table>
                    <tr><td>Subtotal:</td><td class="text-right"><?= formatRupiah($total) ?></td></tr>
                    <tr><td>Ongkir:</td><td class="text-right">Gratis</td></tr>
                    <tr class="total-row"><td>TOTAL:</td><td class="text-right"><?= formatRupiah($total) ?></td></tr>
                </table>
            </div>
            <div class="box" style="margin-top:12px;">
                <div class="box-title">Checkout</div>
                <div class="box-body">
                    <form method="POST">
                        <div class="form-group">
                            <label>Alamat Pengiriman *</label>
                            <textarea name="alamat_pengiriman" class="form-control" rows="3" required><?= $user_data['alamat'] ?? '' ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Catatan (opsional)</label>
                            <textarea name="catatan" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="submit" name="checkout" class="btn btn-danger btn-block">Buat Pesanan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>