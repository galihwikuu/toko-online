<?php
require_once 'config.php';
$page_title = 'Beranda';

$db = getDB();

// Filter
$search     = sanitize($_GET['q'] ?? '');
$kategori_id = (int)($_GET['kat'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 12;
$offset     = ($page - 1) * $per_page;

$where = "WHERE p.status = 'aktif'";
$params = [];
$types  = '';

if ($search) {
    $where   .= " AND (p.nama LIKE ? OR p.deskripsi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types   .= 'ss';
}
if ($kategori_id) {
    $where   .= " AND p.kategori_id = ?";
    $params[] = $kategori_id;
    $types   .= 'i';
}

// Hitung total
$count_sql = "SELECT COUNT(*) as total FROM produk p $where";
$stmt = $db->prepare($count_sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total_rows = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $per_page);

// Ambil produk
$sql = "SELECT p.*, k.nama as kategori_nama FROM produk p 
        LEFT JOIN kategori k ON p.kategori_id = k.id 
        $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types   .= 'ii';
$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$produk_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Kategori untuk filter
$kategori_list = $db->query("SELECT * FROM kategori ORDER BY nama")->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<div class="container main-content">

    <!-- Filter & Search -->
    <div class="box">
        <div class="box-body">
            <form method="GET" class="search-bar">
                <input type="text" name="q" placeholder="Cari produk..." value="<?= $search ?>">
                <select name="kat" class="form-control" style="width:180px;">
                    <option value="">-- Semua Kategori --</option>
                    <?php foreach ($kategori_list as $kat): ?>
                        <option value="<?= $kat['id'] ?>" <?= $kategori_id == $kat['id'] ? 'selected' : '' ?>>
                            <?= $kat['nama'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Cari</button>
                <?php if ($search || $kategori_id): ?>
                    <a href="index.php" class="btn">Reset</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Info hasil -->
    <p style="margin-bottom:10px; font-size:13px; color:#555;">
        Menampilkan <?= count($produk_list) ?> dari <?= $total_rows ?> produk
        <?php if ($search): ?> &mdash; Hasil pencarian: "<strong><?= $search ?></strong>"<?php endif; ?>
    </p>

    <!-- Produk Grid -->
    <?php if (empty($produk_list)): ?>
        <div class="box"><div class="box-body text-center" style="padding:30px;">
            <p>Tidak ada produk ditemukan.</p>
        </div></div>
    <?php else: ?>
    <div class="product-grid">
        <?php foreach ($produk_list as $p): ?>
        <a href="produk.php?id=<?= $p['id'] ?>" style="text-decoration:none; color:inherit;">
            <div class="product-card">
                <?php if ($p['foto'] && file_exists(UPLOAD_DIR . $p['foto'])): ?>
                    <img src="<?= BASE_URL ?>uploads/products/<?= $p['foto'] ?>" alt="<?= $p['nama'] ?>">
                <?php else: ?>
                    <div style="height:160px; background:#eee; display:flex; align-items:center; justify-content:center; color:#aaa; font-size:12px;">Tidak ada foto</div>
                <?php endif; ?>
                <div class="product-card-body">
                    <div class="product-card-title"><?= $p['nama'] ?></div>
                    <div style="font-size:11px; color:#888; margin-bottom:4px;"><?= $p['kategori_nama'] ?? '-' ?></div>
                    <div class="product-card-price"><?= formatRupiah($p['harga']) ?></div>
                    <div class="product-card-stok">Stok: <?= $p['stok'] ?> pcs</div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php $url = "index.php?page=$i" . ($search ? "&q=$search" : '') . ($kategori_id ? "&kat=$kategori_id" : ''); ?>
            <a href="<?= $url ?>" <?= $i == $page ? 'class="active"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>