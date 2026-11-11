<?php
require_once __DIR__ . '/common/config.php';
check_user_login();
$user_id = $_SESSION['user_id'];

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['user_id']);
    unset($_SESSION['username']);
    unset($_SESSION['email']);
    session_destroy();
    header("Location: login.php");
    exit;
}

// Fetch latest user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: login.php");
    exit;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $upi_id   = trim($_POST['upi_id'] ?? '');

        if (empty($username) || empty($email)) {
            set_flash('error', 'Username and email cannot be empty.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Please provide a valid email address.');
        } else {
            // Check for duplicate username or email with another user
            $dupStmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $dupStmt->execute([$username, $email, $user_id]);
            if ($dupStmt->fetch()) {
                set_flash('error', 'Username or email already taken by another player.');
            } else {
                $uStmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, upi_id = ? WHERE id = ?");
                $uStmt->execute([$username, $email, $upi_id, $user_id]);
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                set_flash('success', 'Profile and payout UPI ID saved successfully!');
                header('Location: profile.php');
                exit;
            }
        }
    } elseif ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            set_flash('error', 'All password fields are required.');
        } elseif ($newPass !== $confirmPass) {
            set_flash('error', 'New passwords do not match.');
        } elseif (strlen($newPass) < 6) {
            set_flash('error', 'New password must be at least 6 characters.');
        } elseif (!password_verify($currentPass, $user['password'])) {
            set_flash('error', 'Current password is incorrect.');
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $pStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $pStmt->execute([$newHash, $user_id]);
            set_flash('success', 'Password updated successfully!');
            header('Location: profile.php');
            exit;
        }
    }
}

// Get user stats (total matches joined, won, etc.)
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(p.id) as matches_joined,
        (SELECT COUNT(*) FROM tournaments WHERE winner_id = ?) as matches_won
    FROM participants p
    WHERE p.user_id = ?
");
$statsStmt->execute([$user_id, $user_id]);
$stats = $statsStmt->fetch();

require_once __DIR__ . '/common/header.php';
?>

<!-- Profile Hero Card -->
<div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#10172a] via-[#0b1120] to-[#131b2e] border border-slate-800 p-5 mb-5 shadow-xl text-center">
    <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-gradient-to-tr from-indigo-500 to-cyan-400 p-0.5 shadow-lg shadow-indigo-500/25">
        <div class="w-full h-full bg-slate-900 rounded-[14px] flex items-center justify-center">
            <i class="fa-solid fa-user-astronaut text-2xl text-cyan-400"></i>
        </div>
    </div>
    <h2 class="text-base font-black text-white tracking-wide">@<?= htmlspecialchars($user['username']) ?></h2>
    <p class="text-xs text-slate-400"><?= htmlspecialchars($user['email']) ?></p>

    <!-- Mini stats row -->
    <div class="grid grid-cols-2 gap-2 mt-4 pt-4 border-t border-slate-800/80">
        <div class="bg-slate-900/60 p-2 rounded-xl">
            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Matches Played</span>
            <span class="text-sm font-extrabold text-white"><?= $stats['matches_joined'] ?? 0 ?></span>
        </div>
        <div class="bg-slate-900/60 p-2 rounded-xl">
            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Tournaments Won</span>
            <span class="text-sm font-extrabold text-amber-400">🏆 <?= $stats['matches_won'] ?? 0 ?></span>
        </div>
    </div>
</div>

<!-- SECTION 1: EDIT PROFILE & PAYOUT UPI ID -->
<div class="bg-[#10172a] border border-slate-800/80 rounded-2xl p-4 mb-4 shadow-lg">
    <div class="flex items-center gap-2 mb-3 border-b border-slate-800/80 pb-2.5">
        <div class="w-6 h-6 rounded-lg bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-xs">
            <i class="fa-solid fa-user-pen"></i>
        </div>
        <h3 class="text-xs font-bold text-white uppercase tracking-wider">Player Info & UPI Payout</h3>
    </div>

    <form method="POST" action="profile.php" class="space-y-3">
        <input type="hidden" name="action" value="update_profile">

        <div>
            <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-cyan-400">
        </div>

        <div>
            <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-cyan-400">
        </div>

        <!-- NEW FIELD: Player's Own UPI ID -->
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider">Your Personal UPI ID (For Withdrawals)</label>
                <span class="text-[9px] text-slate-500 font-semibold">Auto-fills on cashout</span>
            </div>
            <div class="relative">
                <input type="text" name="upi_id" value="<?= htmlspecialchars($user['upi_id'] ?? '') ?>" placeholder="e.g. mobile@paytm or name@okhdfcbank" class="w-full pl-8 pr-3 py-2 bg-slate-900 border border-emerald-900/40 rounded-xl text-xs font-mono text-emerald-300 focus:outline-none focus:border-emerald-400">
                <i class="fa-solid fa-money-bill-transfer absolute left-2.5 top-2.5 text-emerald-500/70 text-xs"></i>
            </div>
            <span class="text-[10px] text-slate-400 mt-1 block">Your tournament winning payouts will be sent to this UPI ID by Admin.</span>
        </div>

        <button type="submit" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-indigo-600 hover:from-cyan-400 hover:to-indigo-500 text-white font-bold text-xs uppercase tracking-wider shadow-md transition-all active:scale-[0.98]">
            Save Profile & UPI Details
        </button>
    </form>
</div>

<!-- SECTION 2: CHANGE PASSWORD -->
<div class="bg-[#10172a] border border-slate-800/80 rounded-2xl p-4 mb-4 shadow-lg">
    <div class="flex items-center gap-2 mb-3 border-b border-slate-800/80 pb-2.5">
        <div class="w-6 h-6 rounded-lg bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-xs">
            <i class="fa-solid fa-lock"></i>
        </div>
        <h3 class="text-xs font-bold text-white uppercase tracking-wider">Security & Password</h3>
    </div>

    <form method="POST" action="profile.php" class="space-y-3">
        <input type="hidden" name="action" value="change_password">

        <div>
            <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Current Password</label>
            <input type="password" name="current_password" required placeholder="Enter current password" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-400">
        </div>

        <div>
            <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">New Password</label>
            <input type="password" name="new_password" required minlength="6" placeholder="Min. 6 characters" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-400">
        </div>

        <div>
            <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Confirm New Password</label>
            <input type="password" name="confirm_password" required minlength="6" placeholder="Re-type new password" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-400">
        </div>

        <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-xs uppercase tracking-wider transition-colors">
            Update Password
        </button>
    </form>
</div>

<!-- SECTION 3: LOGOUT -->
<div class="mb-4">
    <a href="profile.php?action=logout" onclick="return confirm('Are you sure you want to log out?');" class="w-full py-3 px-4 rounded-xl bg-rose-950/40 hover:bg-rose-900/60 border border-rose-800/50 text-rose-300 font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-2 transition-all">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
        <span>Log Out of Vortex</span>
    </a>
</div>

<?php
require_once __DIR__ . '/common/bottom.php';
?>