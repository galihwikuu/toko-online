<?php
require_once '../config.php';
requireLogin();
if (isAdmin()) { header('Location: ' . BASE_URL . 'admin/dashboard.php'); exit; }

$db  = getDB();
$uid = $_SESSION['user_id'];
$page_title = 'Pesanan Saya';

// Batal pesanan
if (isset($_GET['batal'])) {
    $pid = (int)$_GET['batal'];
    $cek = $db->query("SELECT status FROM pesanan WHERE id=$pid AND user_id=$uid")->fetch_assoc();
    if ($cek && $cek['status'] === 'pending') {
        $db->query("UPDATE pesanan SET status='dibatalkan' WHERE id=$pid AND user_id=$uid");
    }
    header('Location: pesanan.php'); exit;
}

// Detail
$detail = null;
if (isset($_GET['id'])) {
    $pid = (int)$_GET['id'];
    $detail = $db->query("SELECT * FROM pesanan WHERE id=$pid AND user_id=$uid")->fetch_assoc();
    if ($detail) {
        $detail['items'] = $db->query("SELECT * FROM detail_pesanan WHERE pesanan_id=$pid")->fetch_all(MYSQLI_ASSOC);
    }
}

$list = $db->query("SELECT * FROM pesanan WHERE user_id=$uid ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<div class="container main-content">
    <div class="breadcrumb"><a href="<?= BASE_URL ?>index.php">Beranda</a> <span>&raquo;</span> Pesanan Saya</div>

    <?php if (isset($_GET['sukses'])): ?>
        <div class="alert alert-success">✅ Pesanan berhasil dibuat! Silakan tunggu konfirmasi dari kami.</div>
    <?php endif; ?>

    <?php if ($detail): ?>
    <div class="box">
        <div class="box-body">
            <div class="box-title detail-header">
                <span>Detail Pesanan: <?= $detail['kode_pesanan'] ?></span>
                <a href="pesanan.php" class="btn btn-sm">← Kembali</a>
            </div>
            <p><strong>Tanggal:</strong> <?= date('d/m/Y H:i', strtotime($detail['created_at'])) ?></p>
            <p><strong>Status:</strong> <span class="status status-<?= $detail['status'] ?>"><?= ucfirst($detail['status']) ?></span></p>
            <p><strong>Alamat:</strong> <?= nl2br($detail['alamat_pengiriman']) ?></p>
            <?php if ($detail['catatan']): ?><p><strong>Catatan:</strong> <?= $detail['catatan'] ?></p><?php endif; ?>
            <div class="table-responsive">
                <table class="table" style="margin-top:12px">

                    <thead><tr><th>Produk</th><th>Harga</th><th>Jumlah</th><th>Subtotal</th></tr></thead>
                    <tbody>
                        <?php foreach ($detail['items'] as $item): ?>
                            <tr>
                                <td><?= $item['nama_produk'] ?></td>
                                <td><?= formatRupiah($item['harga']) ?></td>
                                <td><?= $item['jumlah'] ?></td>
                                <td><?= formatRupiah($item['subtotal']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="3" class="text-right"><strong>TOTAL</strong></td>
                                <td><strong class="text-red"><?= formatRupiah($detail['total']) ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                        </div>
                </div>
            </div>
    <?php else: ?>
    <div class="box">
        <div class="box-title">Riwayat Pesanan</div>
        <div class="box-body" style="padding:0;">
            <?php if (empty($list)): ?>
                <div style="padding:25px; text-align:center;">
                    <p>Anda belum memiliki pesanan.</p>
                    <a href="<?= BASE_URL ?>index.php" class="btn btn-primary mt-10">Belanja Sekarang</a>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Kode Pesanan</th><th>Total</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach ($list as $p): ?>
                            <tr>
                                <td><?= $p['kode_pesanan'] ?></td>
                                <td><?= formatRupiah($p['total']) ?></td>
                                <td><span class="status status-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
                                <td><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
                                <td>
                                    <a href="pesanan.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">Detail</a>
                                    <?php if ($p['status'] === 'pending'): ?>
                                        <a href="pesanan.php?batal=<?= $p['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Batalkan pesanan ini?')">Batalkan</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
