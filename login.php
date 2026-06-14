<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? BASE_URL . 'admin/dashboard.php' : BASE_URL . 'index.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, nama, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['role']    = $user['role'];
            $redirect = $user['role'] === 'admin' ? BASE_URL . 'admin/dashboard.php' : BASE_URL . 'index.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Email atau password salah.';
        }
    }
}
$page_title = 'Login';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Toko Online</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>

<div class="login-wrapper">
    <div class="login-header">
        <h2>TokoKu</h2>
        <p style="font-size:13px; margin-top:4px;">Silakan masuk ke akun Anda</p>
    </div>
    <div class="login-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success">Registrasi berhasil! Silakan login.</div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= sanitize($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Password</label>

                <div style="position:relative;">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-control"
                        required
                        style="padding-right:45px;"
                    >

                    <i
                        id="togglePassword"
                        class="fa-solid fa-eye"
                        style="
                            position:absolute;
                            right:15px;
                            top:50%;
                            transform:translateY(-50%);
                            cursor:pointer;
                            color:#777;
                        "
                    ></i>
                </div>
            </div>
            <button type="submit" class="btn btn-danger btn-block">Masuk</button>
        </form>
        <p class="text-center mt-10" style="font-size:13px;">
            Belum punya akun? <a href="register.php">Daftar sekarang</a>
        </p>
        <hr style="margin:12px 0; border:none; border-top:1px solid #ddd;">
        <p style="font-size:12px; color:#888; text-align:center;">
            Demo: admin@toko.com / password &nbsp;|&nbsp; user@gmail.com / user123
        </p>
        <p class="text mt-10" style="font-size:13px;">
            Kembali ke <a href="<?= BASE_URL ?>index.php">Beranda</a>
        </p>
    </div>
</div>

<script>
const togglePassword = document.getElementById('togglePassword');
const password = document.getElementById('password');

togglePassword.addEventListener('click', function() {

    const type = password.getAttribute('type') === 'password'
        ? 'text'
        : 'password';

    password.setAttribute('type', type);

    this.classList.toggle('fa-eye');
    this.classList.toggle('fa-eye-slash');
});
</script>

</body>
</html>
