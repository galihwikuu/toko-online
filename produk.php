<?php
require_once 'config.php';

$page_title = 'Detail Produk';

$db = getDB();

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header("Location: index.php");
    exit;
}

// Ambil data produk
$stmt = $db->prepare("SELECT p.*, k.nama as kategori_nama
                      FROM produk p
                      LEFT JOIN kategori k ON p.kategori_id = k.id
                      WHERE p.id = ? AND p.status='aktif'");
$stmt->bind_param("i", $id);
$stmt->execute();
$produk = $stmt->get_result()->fetch_assoc();

if (!$produk) {
    header("Location: index.php");
    exit;
}

// Produk terkait
$related_stmt = $db->prepare("SELECT * FROM produk
                              WHERE kategori_id = ?
                              AND id != ?
                              AND status='aktif'
                              LIMIT 4");
$related_stmt->bind_param("ii", $produk['kategori_id'], $produk['id']);
$related_stmt->execute();
$related_products = $related_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle POST beli langsung (dari form di modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'beli_langsung' && isLoggedIn() && !isAdmin()) {
    $jumlah = max(1, (int)($_POST['jumlah'] ?? 1));
    if ($jumlah <= $produk['stok']) {
        $_SESSION['beli_langsung'] = [
            'produk_id' => $id,
            'nama'      => $produk['nama'],
            'harga'     => $produk['harga'],
            'foto'      => $produk['foto'],
            'jumlah'    => $jumlah,
            'subtotal'  => $produk['harga'] * $jumlah,
        ];
        header('Location: user/keranjang.php?mode=langsung');
        exit;
    }
}

require_once 'includes/header.php';
?>

<div class="container main-content">

    <div class="box">
        <div class="box-body">
            <div style="display:flex; gap:30px; flex-wrap:wrap;">

                <!-- Foto Produk -->
                <div style="flex:1; min-width:300px;">
                    <?php if ($produk['foto'] && file_exists(UPLOAD_DIR . $produk['foto'])): ?>
                        <img src="<?= BASE_URL ?>uploads/products/<?= $produk['foto'] ?>"
                             alt="<?= $produk['nama'] ?>"
                             style="width:100%; border-radius:10px;">
                    <?php else: ?>
                        <div style="height:350px; background:#eee; display:flex; align-items:center; justify-content:center; color:#999; border-radius:10px;">
                            Tidak ada foto
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Detail Produk -->
                <div style="flex:1; min-width:300px;">
                    <div style="font-size:13px; color:#777; margin-bottom:8px;">
                        <?= $produk['kategori_nama'] ?? 'Tanpa kategori' ?>
                    </div>

                    <h1 style="margin-bottom:15px;"><?= $produk['nama'] ?></h1>

                    <div style="font-size:28px; font-weight:bold; color:#e63946; margin-bottom:15px;">
                        <?= formatRupiah($produk['harga']) ?>
                    </div>

                    <div style="margin-bottom:15px;">
                        <strong>Stok:</strong>
                        <?php if ($produk['stok'] > 0): ?>
                            <span style="color:green;"><?= $produk['stok'] ?> tersedia</span>
                        <?php else: ?>
                            <span style="color:red;">Stok habis</span>
                        <?php endif; ?>
                    </div>

                    <div style="line-height:1.8; color:#444; margin-bottom:20px;">
                        <?= nl2br($produk['deskripsi']) ?>
                    </div>

                    <?php if (isLoggedIn() && !isAdmin()): ?>
                        <?php if ($produk['stok'] > 0): ?>
                            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                <a href="user/keranjang.php?aksi=tambah&id=<?= $produk['id'] ?>" class="btn btn-danger">
                                    + Keranjang
                                </a>
                                <button type="button" class="btn btn-primary" onclick="bukaModal()">
                                    ⚡ Beli Langsung
                                </button>
                            </div>
                        <?php else: ?>
                            <button class="btn" disabled>Stok Habis</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary">Login untuk membeli</a>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- Produk Terkait -->
    <?php if (!empty($related_products)): ?>
    <div class="box">
        <div class="box-title">Produk Terkait</div>
        <div class="box-body">
            <div class="product-grid">
                <?php foreach ($related_products as $p): ?>
                <a href="produk.php?id=<?= $p['id'] ?>" style="text-decoration:none; color:inherit;">
                    <div class="product-card">
                        <?php if ($p['foto'] && file_exists(UPLOAD_DIR . $p['foto'])): ?>
                            <img src="<?= BASE_URL ?>uploads/products/<?= $p['foto'] ?>" alt="<?= $p['nama'] ?>">
                        <?php else: ?>
                            <div style="height:160px; background:#eee; display:flex; align-items:center; justify-content:center; color:#aaa;">Tidak ada foto</div>
                        <?php endif; ?>
                        <div class="product-card-body">
                            <div class="product-card-title"><?= $p['nama'] ?></div>
                            <div class="product-card-price"><?= formatRupiah($p['harga']) ?></div>
                            <div class="product-card-stok">Stok: <?= $p['stok'] ?> pcs</div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ========== MODAL BELI LANGSUNG ========== -->
<?php if (isLoggedIn() && !isAdmin() && $produk['stok'] > 0): ?>
<div id="modalBeliLangsung" style="
    position:fixed; inset:0; z-index:9999;
    background:rgba(0,0,0,0.5);
    align-items:flex-end;
    justify-content:center;
">
    <!-- Panel -->
    <div style="
        background:#fff;
        width:100%;
        max-width:520px;
        border-radius:16px 16px 0 0;
        padding:24px;
        animation: slideUp 0.25s ease;
        position:relative;
    ">
        <!-- Tombol tutup -->
        <button onclick="tutupModal()" style="
            position:absolute; top:14px; right:16px;
            background:none; border:none; font-size:20px;
            cursor:pointer; color:#888; line-height:1;
        ">✕</button>

        <div style="font-size:13px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:16px;">
            Beli Langsung
        </div>

        <!-- Info produk -->
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:20px; padding-bottom:16px; border-bottom:1px solid #eee;">
            <?php if ($produk['foto'] && file_exists(UPLOAD_DIR . $produk['foto'])): ?>
                <img src="<?= BASE_URL ?>uploads/products/<?= $produk['foto'] ?>"
                     style="width:64px; height:64px; object-fit:cover; border-radius:8px; border:1px solid #eee;">
            <?php endif; ?>
            <div>
                <div style="font-weight:600; font-size:15px; margin-bottom:4px;"><?= $produk['nama'] ?></div>
                <div style="color:#e63946; font-weight:700; font-size:18px;"><?= formatRupiah($produk['harga']) ?></div>
            </div>
        </div>

        <!-- Stok -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <span style="font-size:13px; color:#555; font-weight:500;">Stok tersedia</span>
            <span style="font-size:13px; font-weight:700; color:#333;"><?= $produk['stok'] ?> pcs</span>
        </div>

        <!-- Jumlah -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <span style="font-size:13px; color:#555; font-weight:500;">Jumlah</span>
            <div style="display:flex; align-items:center; gap:0; border:1.5px solid #ddd; border-radius:8px; overflow:hidden;">
                <button type="button" onclick="ubahJumlah(-1)" style="
                    width:36px; height:36px; border:none; background:#f5f5f5;
                    font-size:18px; cursor:pointer; font-weight:bold; color:#333;
                    display:flex; align-items:center; justify-content:center;
                ">−</button>
                <input type="number" id="inputJumlah" value="1" min="1" max="<?= $produk['stok'] ?>"
                       oninput="updateTotal()"
                       style="width:52px; height:36px; border:none; border-left:1.5px solid #ddd; border-right:1.5px solid #ddd;
                              text-align:center; font-size:14px; font-weight:600; font-family:inherit; outline:none;">
                <button type="button" onclick="ubahJumlah(1)" style="
                    width:36px; height:36px; border:none; background:#f5f5f5;
                    font-size:18px; cursor:pointer; font-weight:bold; color:#333;
                    display:flex; align-items:center; justify-content:center;
                ">+</button>
            </div>
        </div>

        <!-- Total -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; padding:14px 16px; background:#f8f8f8; border-radius:10px;">
            <span style="font-size:14px; font-weight:600; color:#333;">Total</span>
            <span id="totalHarga" style="font-size:20px; font-weight:800; color:#e63946;"><?= formatRupiah($produk['harga']) ?></span>
        </div>

        <!-- Form submit -->
        <form method="POST">
            <input type="hidden" name="aksi" value="beli_langsung">
            <input type="hidden" name="jumlah" id="hiddenJumlah" value="1">
            <button type="submit" onclick="syncJumlah()" class="btn btn-primary btn-block" style="padding:14px; font-size:15px; border-radius:10px;">
                ⚡ Beli Sekarang
            </button>
        </form>
    </div>
</div>

<style>
@keyframes slideUp {
    from { transform: translateY(100%); opacity: 0; }
    to   { transform: translateY(0);    opacity: 1; }
}
#modalBeliLangsung { display: none !important; }
#modalBeliLangsung.aktif { display: flex !important; }
</style>

<script>
const harga   = <?= $produk['harga'] ?>;
const maxStok = <?= $produk['stok'] ?>;

function bukaModal() {
    document.getElementById('modalBeliLangsung').classList.add('aktif');
    document.body.style.overflow = 'hidden';
}

function tutupModal() {
    document.getElementById('modalBeliLangsung').classList.remove('aktif');
    document.body.style.overflow = '';
}

// Tutup modal kalau klik backdrop
document.getElementById('modalBeliLangsung').addEventListener('click', function(e) {
    if (e.target === this) tutupModal();
});

function ubahJumlah(delta) {
    const input = document.getElementById('inputJumlah');
    let val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > maxStok) val = maxStok;
    input.value = val;
    updateTotal();
}

function updateTotal() {
    const input = document.getElementById('inputJumlah');
    let val = parseInt(input.value) || 1;
    if (val < 1) val = 1;
    if (val > maxStok) val = maxStok;
    input.value = val;
    const total = harga * val;
    document.getElementById('totalHarga').textContent = 'Rp ' + total.toLocaleString('id-ID');
}

function syncJumlah() {
    document.getElementById('hiddenJumlah').value = document.getElementById('inputJumlah').value;
}
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>