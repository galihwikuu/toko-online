<?php
require_once '../config.php';
requireAdmin();
$db = getDB();
$page_title = 'Kelola Pesanan';
$active_menu = 'pesanan';
$msg = '';

// Update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $pid    = (int)$_POST['pesanan_id'];
    $status = $_POST['status'];
    $allowed = ['pending','dikonfirmasi','diproses','dikirim','selesai','dibatalkan'];
    if (in_array($status, $allowed)) {
        $db->query("UPDATE pesanan SET status='$status' WHERE id=$pid");
        $msg = '<div class="alert alert-success">Status pesanan berhasil diupdate.</div>';
    }
}

// Detail pesanan
$detail_pesanan = null;
if (isset($_GET['id'])) {
    $pid = (int)$_GET['id'];
    $detail_pesanan = $db->query("SELECT p.*, u.nama as user_nama, u.email, u.telepon FROM pesanan p LEFT JOIN users u ON p.user_id=u.id WHERE p.id=$pid")->fetch_assoc();
    if ($detail_pesanan) {
        $detail_pesanan['items'] = $db->query("SELECT * FROM detail_pesanan WHERE pesanan_id=$pid")->fetch_all(MYSQLI_ASSOC);
    }
}

// Filter
$filter_status = sanitize($_GET['status'] ?? '');
$where = $filter_status ? "WHERE p.status='$filter_status'" : '';
$list = $db->query("SELECT p.*, u.nama as user_nama FROM pesanan p LEFT JOIN users u ON p.user_id=u.id $where ORDER BY p.created_at DESC")->fetch_all(MYSQLI_ASSOC);

require_once 'sidebar.php';
?>

<?= $msg ?>

<?php if ($detail_pesanan): ?>
<!-- Detail Pesanan -->
<div class="box">
    <div class="box-body">
        <div class="box-title detail-header">
            <span>Detail Pesanan: <?= $detail_pesanan['kode_pesanan'] ?></span>
            <a href="pesanan.php" class="btn btn-sm">← Kembali</a>
        </div>
        <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:15px;">
            <div style="flex:1; min-width:220px;">
                <p><strong>Pembeli:</strong> <?= $detail_pesanan['user_nama'] ?></p>
                <p><strong>Email:</strong> <?= $detail_pesanan['email'] ?></p>
                <p><strong>Telepon:</strong> <?= $detail_pesanan['telepon'] ?: '-' ?></p>
                <p><strong>Tanggal:</strong> <?= date('d/m/Y H:i', strtotime($detail_pesanan['created_at'])) ?></p>
            </div>
            <div style="flex:1; min-width:220px;">
                <p><strong>Alamat Pengiriman:</strong><br><?= nl2br($detail_pesanan['alamat_pengiriman']) ?></p>
                <?php if ($detail_pesanan['catatan']): ?>
                    <p><strong>Catatan:</strong> <?= $detail_pesanan['catatan'] ?></p>
                <?php endif; ?>
            </div>
            <div style="flex:1; min-width:180px;">
                <p><strong>Status Saat Ini:</strong><br><span class="status status-<?= $detail_pesanan['status'] ?>"><?= ucfirst($detail_pesanan['status']) ?></span></p>
                <form method="POST" style="margin-top:10px;">
                    <input type="hidden" name="pesanan_id" value="<?= $detail_pesanan['id'] ?>">
                    <div class="form-group">
                        <label>Ubah Status</label>
                        <select name="status" class="form-control">
                            <?php foreach (['pending','dikonfirmasi','diproses','dikirim','selesai','dibatalkan'] as $s): ?>
                                <option value="<?= $s ?>" <?= $detail_pesanan['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="update_status" class="btn btn-primary btn-sm">Update Status</button>
                </form>
            </div>
        </div>
            <div class="table-responsive">
                <table class="table">

                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Harga Satuan</th>
                            <th>Jumlah</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($detail_pesanan['items'] as $item): ?>
                        <tr>
                            <td><?= $item['nama_produk'] ?></td>
                            <td><?= formatRupiah($item['harga']) ?></td>
                            <td><?= $item['jumlah'] ?></td>
                            <td><?= formatRupiah($item['subtotal']) ?></td>
                        </tr>
                        <?php endforeach; ?>

                        <tr>
                            <td colspan="3" class="text-right"><strong>TOTAL</strong></td>
                            <td><strong class="text-red"><?= formatRupiah($detail_pesanan['total']) ?></strong></td>
                        </tr>
                    </tbody>

                </table>
            </div>
        <?php else: ?>

<!-- Daftar Pesanan -->
<div class="box">
    <div class="box-title">Daftar Pesanan</div>
    <div class="box-body">
        <form method="GET" class="search-bar">
            <select name="status" class="form-control" style="width:180px;">
                <option value="">-- Semua Status --</option>
                <?php foreach (['pending','dikonfirmasi','diproses','dikirim','selesai','dibatalkan'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filter_status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($filter_status): ?><a href="pesanan.php" class="btn">Reset</a><?php endif; ?>
        </form>
    </div>
    <div class="box-body" style="padding:0;"><div class="table-responsive"><table class="table">
            <thead><tr><th>Kode Pesanan</th><th>Pembeli</th><th>Total</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php if (empty($list)): ?>
                    <tr><td colspan="6" class="text-center">Belum ada pesanan.</td></tr>
                <?php else: ?>
                <?php foreach ($list as $p): ?>
                <tr>
                    <td><?= $p['kode_pesanan'] ?></td>
                    <td><?= $p['user_nama'] ?></td>
                    <td><?= formatRupiah($p['total']) ?></td>
                    <td><span class="status status-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
                    <td><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
                    <td><a href="pesanan.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">Detail</a></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table></div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'footer_admin.php'; ?>