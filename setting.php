<?php
require_once __DIR__ . '/common/header.php';

// Helper to get a setting value from DB
function get_app_setting(PDO $pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Helper to set a setting value in DB
function set_app_setting(PDO $pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$key, $value, $value]);
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Update UPI & QR Code Settings
    if ($action === 'update_upi') {
        $upi_id = trim($_POST['admin_upi_id'] ?? '');

        if (empty($upi_id)) {
            set_flash('error', 'Please enter a valid Admin UPI ID.');
        } else {
            set_app_setting($pdo, 'admin_upi_id', $upi_id);

            // Handle QR Code Upload if provided
            if (isset($_FILES['admin_qr_file']) && $_FILES['admin_qr_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath   = $_FILES['admin_qr_file']['tmp_name'];
                $fileName      = $_FILES['admin_qr_file']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                $allowedExtensions = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
                if (in_array($fileExtension, $allowedExtensions)) {
                    $uploadDir = __DIR__ . '/../uploads/';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0777, true);
                    }

                    $newFileName = 'admin_qr_' . time() . '.' . $fileExtension;
                    $destPath    = $uploadDir . $newFileName;

                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        set_app_setting($pdo, 'admin_qr_code', 'uploads/' . $newFileName);
                        set_flash('success', 'Admin UPI ID and QR Code image updated successfully!');
                    } else {
                        set_flash('error', 'Failed to save uploaded QR code image file.');
                    }
                } else {
                    set_flash('error', 'Invalid file type. Please upload JPG, PNG, WEBP, or SVG.');
                }
            } else {
                set_flash('success', 'Admin UPI ID updated successfully!');
            }

            header("Location: settings.php");
            exit;
        }
    }

    // 2. Update Admin Password
    elseif ($action === 'update_password') {
        $admin_id    = $_SESSION['admin_id'];
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM admin WHERE id = ?");
        $stmt->execute([$admin_id]);
        $currHash = $stmt->fetchColumn();

        if (!password_verify($currentPass, $currHash)) {
            set_flash('error', 'Current password is incorrect.');
        } elseif (strlen($newPass) < 6) {
            set_flash('error', 'New password must be at least 6 characters.');
        } elseif ($newPass !== $confirmPass) {
            set_flash('error', 'Confirm password does not match.');
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE admin SET password = ? WHERE id = ?");
            $stmt->execute([$newHash, $admin_id]);
            set_flash('success', 'Admin password changed successfully!');
            header("Location: settings.php");
            exit;
        }
    }
}

// Fetch current values
$adminUpiId = get_app_setting($pdo, 'admin_upi_id', 'vortexesports@okaxis');
$adminQrCode = get_app_setting($pdo, 'admin_qr_code', 'uploads/admin_qr.svg');
?>

<!-- Title Header -->
<div class="flex items-center justify-between mb-4">
    <div class="flex items-center gap-2">
        <div class="w-2 h-4 bg-amber-400 rounded-full"></div>
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-200">Payment & System Settings</h2>
    </div>
</div>

<!-- Section 1: Admin UPI & QR Code Settings -->
<div class="bg-[#111827] border border-slate-800 rounded-2xl p-4 mb-4 shadow-xl">
    <div class="flex items-center gap-2 mb-3 border-b border-slate-800/80 pb-2.5">
        <div class="w-7 h-7 rounded-lg bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-xs">
            <i class="fa-solid fa-qrcode"></i>
        </div>
        <div>
            <h3 class="text-xs font-black text-white uppercase tracking-wider">UPI Deposit Gateway</h3>
            <p class="text-[10px] text-slate-400">Displayed in user's wallet modal for manual "Add Money" transfers</p>
        </div>
    </div>

    <form method="POST" action="settings.php" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="action" value="update_upi">

        <div>
            <label class="block text-[10px] font-bold text-slate-300 uppercase tracking-wider mb-1.5">
                Admin UPI ID (VPA) <span class="text-rose-400">*</span>
            </label>
            <div class="relative">
                <input type="text" name="admin_upi_id" required value="<?= htmlspecialchars($adminUpiId) ?>" placeholder="e.g. yourname@paytm / business@okaxis" class="w-full pl-9 pr-3 py-2.5 bg-slate-900 border border-slate-700/80 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-cyan-400">
                <i class="fa-solid fa-at absolute left-3 top-3 text-slate-500 text-xs"></i>
            </div>
            <span class="text-[10px] text-slate-400 mt-1 block">Users will copy this UPI ID to transfer funds from PhonePe, GPay, or Paytm.</span>
        </div>

        <!-- Current QR Code Preview -->
        <div>
            <label class="block text-[10px] font-bold text-slate-300 uppercase tracking-wider mb-1.5">
                Current Payment QR Code
            </label>
            <div class="flex items-center gap-4 bg-slate-900/90 border border-slate-800 p-3 rounded-2xl">
                <div class="w-20 h-20 rounded-xl bg-white p-1.5 flex items-center justify-center overflow-hidden shadow-md flex-shrink-0">
                    <img src="../<?= htmlspecialchars($adminQrCode) ?>?t=<?= time() ?>" alt="Admin QR Code" class="w-full h-full object-contain">
                </div>
                <div class="space-y-1 text-[11px]">
                    <div class="text-slate-300 font-semibold">Active QR Image</div>
                    <div class="text-[10px] text-cyan-400 font-mono break-all"><?= htmlspecialchars($adminQrCode) ?></div>
                    <span class="inline-block text-[9px] px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 font-bold border border-emerald-500/30">
                        Live on User App
                    </span>
                </div>
            </div>
        </div>

        <!-- Upload New QR Code Image -->
        <div>
            <label class="block text-[10px] font-bold text-slate-300 uppercase tracking-wider mb-1.5">
                Upload New QR Code Image (Optional)
            </label>
            <input type="file" name="admin_qr_file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="block w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-800 file:text-cyan-400 hover:file:bg-slate-700 file:cursor-pointer bg-slate-900 border border-slate-800 rounded-xl">
            <span class="text-[10px] text-slate-500 mt-1 block">Accepted formats: PNG, JPG, WEBP, or SVG.</span>
        </div>

        <button type="submit" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-indigo-600 hover:from-cyan-400 hover:to-indigo-500 text-white font-black text-xs uppercase tracking-wider shadow-lg shadow-cyan-500/20 active:scale-[0.98] transition-all flex items-center justify-center gap-1.5">
            <i class="fa-solid fa-check"></i>
            <span>Save UPI & QR Settings</span>
        </button>
    </form>
</div>

<!-- Section 2: Quick Links to Requests -->
<div class="grid grid-cols-2 gap-3 mb-4">
    <a href="deposits.php" class="bg-[#111827] hover:bg-slate-800/80 border border-slate-800 p-3.5 rounded-2xl flex items-center gap-3 transition-colors shadow-md group">
        <div class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-sm group-hover:scale-105 transition-transform">
            <i class="fa-solid fa-arrow-down-left"></i>
        </div>
        <div>
            <h4 class="text-xs font-bold text-white">Deposit Requests</h4>
            <span class="text-[10px] text-emerald-400 font-semibold">Verify UTR & Approve</span>
        </div>
    </a>

    <a href="withdrawals.php" class="bg-[#111827] hover:bg-slate-800/80 border border-slate-800 p-3.5 rounded-2xl flex items-center gap-3 transition-colors shadow-md group">
        <div class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center text-sm group-hover:scale-105 transition-transform">
            <i class="fa-solid fa-arrow-up-right"></i>
        </div>
        <div>
            <h4 class="text-xs font-bold text-white">Withdrawal Requests</h4>
            <span class="text-[10px] text-amber-400 font-semibold">Payouts to Player UPI</span>
        </div>
    </a>
</div>

<!-- Section 3: Change Admin Password -->
<div class="bg-[#111827] border border-slate-800 rounded-2xl p-4 mb-4 shadow-xl">
    <div class="flex items-center gap-2 mb-3 border-b border-slate-800/80 pb-2.5">
        <div class="w-7 h-7 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center text-xs">
            <i class="fa-solid fa-lock"></i>
        </div>
        <h3 class="text-xs font-black text-white uppercase tracking-wider">Change Admin Password</h3>
    </div>

    <form method="POST" action="settings.php" class="space-y-3">
        <input type="hidden" name="action" value="update_password">

        <div>
            <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Current Password</label>
            <input type="password" name="current_password" required placeholder="Enter current admin password" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400">
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">New Password</label>
                <input type="password" name="new_password" required minlength="6" placeholder="Min. 6 chars" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400">
            </div>
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Confirm New Password</label>
                <input type="password" name="confirm_password" required minlength="6" placeholder="Confirm" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400">
            </div>
        </div>

        <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-amber-400 border border-slate-700 font-bold text-xs uppercase tracking-wider transition-colors active:scale-[0.98]">
            Update Password
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/common/bottom.php'; ?>