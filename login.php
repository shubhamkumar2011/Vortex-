<?php
require_once __DIR__ . '/../common/config.php';

// Redirect if already logged in as admin
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please fill in all credentials.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            set_flash('success', "Welcome back, Admin!");
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid administrator credentials.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Login - Vortex Esports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body, * {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
        }
        input {
            -webkit-user-select: text;
            user-select: text;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-[#0f1422] border border-slate-800 rounded-3xl p-6 shadow-2xl relative">
        <div class="text-center mb-6">
            <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-gradient-to-tr from-amber-500 to-rose-600 flex items-center justify-center shadow-lg shadow-amber-500/20">
                <i class="fa-solid fa-shield-halved text-white text-2xl"></i>
            </div>
            <h1 class="text-xl font-black tracking-wider text-white uppercase">VORTEX <span class="text-amber-400">ADMIN</span></h1>
            <p class="text-xs text-slate-400 mt-1">Management Portal Authentication</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-950/80 border border-rose-600/50 text-rose-200 text-xs p-3 rounded-xl mb-4 flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Admin Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                        <i class="fa-solid fa-user-shield text-xs"></i>
                    </span>
                    <input type="text" name="username" required autocomplete="username" placeholder="admin" value="admin" class="w-full pl-9 pr-3 py-2.5 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400 transition-colors">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                        <i class="fa-solid fa-lock text-xs"></i>
                    </span>
                    <input type="password" name="password" required autocomplete="current-password" placeholder="admin123" value="admin123" class="w-full pl-9 pr-3 py-2.5 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400 transition-colors">
                </div>
            </div>

            <button type="submit" class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-amber-500 to-rose-600 hover:from-amber-400 hover:to-rose-500 text-white font-bold text-xs uppercase tracking-wider shadow-lg shadow-amber-600/20 active:scale-[0.98] transition-all">
                Enter Admin Portal
            </button>

            <div class="text-center pt-2 text-[11px] text-slate-400">
                Default: <code class="text-amber-400">admin</code> / <code class="text-amber-400">admin123</code>
            </div>
        </form>

        <div class="mt-6 pt-4 border-t border-slate-800 text-center">
            <a href="../login.php" class="text-xs text-slate-400 hover:text-cyan-400 transition-colors">
                <i class="fa-solid fa-arrow-left mr-1"></i> Back to Player App
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('selectstart', e => {
            if (e.target.tagName !== 'INPUT') e.preventDefault();
        });
    </script>
</body>
</html>