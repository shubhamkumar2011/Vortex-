<?php
/**
 * Vortex Esports - Safe Database Schema Updater
 * Updates the database schema for manual UPI Deposit & Withdrawal system
 * without dropping or resetting any existing user or tournament data.
 */
require_once __DIR__ . '/common/config.php';

$logs = [];
$error = null;
$updated = false;

try {
    // 1. Create 'settings' table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(100) NOT NULL UNIQUE,
        `setting_value` TEXT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $logs[] = "Table `settings` verified/created successfully.";

    // Seed default admin UPI details if not present
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `settings` WHERE `setting_key` = ?");
    
    $stmt->execute(['admin_upi_id']);
    if ($stmt->fetchColumn() == 0) {
        $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?)")
            ->execute(['admin_upi_id', 'vortexesports@okaxis']);
        $logs[] = "Default Admin UPI ID seeded: <strong>vortexesports@okaxis</strong>";
    }

    $stmt->execute(['admin_qr_code']);
    if ($stmt->fetchColumn() == 0) {
        $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?)")
            ->execute(['admin_qr_code', 'uploads/admin_qr.svg']);
        $logs[] = "Default Admin QR Code path initialized: <strong>uploads/admin_qr.svg</strong>";
    }

    // 2. Create 'deposits' table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `deposits` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL,
        `transaction_id` VARCHAR(100) NOT NULL,
        `status` ENUM('Pending','Completed','Rejected') NOT NULL DEFAULT 'Pending',
        `admin_note` VARCHAR(255) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`status`),
        INDEX (`user_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $logs[] = "Table `deposits` (UPI manual verification) verified/created successfully.";

    // 3. Create 'withdrawals' table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `withdrawals` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL,
        `upi_id` VARCHAR(100) NOT NULL,
        `status` ENUM('Pending','Completed','Rejected') NOT NULL DEFAULT 'Pending',
        `admin_note` VARCHAR(255) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`status`),
        INDEX (`user_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $logs[] = "Table `withdrawals` (UPI manual payout) verified/created successfully.";

    // 4. Safely add 'upi_id' column to 'users' table if it doesn't exist
    $checkCol = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'upi_id'")->fetch();
    if (!$checkCol) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `upi_id` VARCHAR(255) NULL AFTER `email`;");
        $logs[] = "Column `upi_id` added to table `users`.";
    } else {
        $logs[] = "Column `upi_id` already exists in `users`.";
    }

    // 5. Ensure uploads directory exists
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $updated = true;
} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Database Update - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col items-center justify-center p-4">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-2xl">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-cyan-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-cyan-500/25">
                <i class="fa-solid fa-arrows-rotate text-white text-xl"></i>
            </div>
            <div>
                <h1 class="text-lg font-black tracking-wide text-white">VORTEX ESPORTS</h1>
                <p class="text-xs text-cyan-400 font-medium">Safe Database Migration</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-950/80 border border-rose-600/50 text-rose-200 text-xs p-4 rounded-2xl mb-6">
                <div class="flex items-center gap-2 font-bold mb-1">
                    <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
                    <span>Migration Error</span>
                </div>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($updated): ?>
            <div class="bg-emerald-950/60 border border-emerald-500/40 text-emerald-200 text-xs p-4 rounded-2xl mb-6">
                <div class="flex items-center gap-2 font-black text-sm mb-2 text-emerald-400">
                    <i class="fa-solid fa-circle-check text-base"></i>
                    <span>✅ Database schema updated successfully!</span>
                </div>
                <p class="text-[11px] text-emerald-300/90 mb-3">
                    You can now safely delete this file (<code class="bg-slate-950 px-1 py-0.5 rounded text-cyan-300">update_database.php</code>).
                </p>
                <div class="space-y-1.5 text-[11px] text-slate-300 border-t border-emerald-900/60 pt-2">
                    <?php foreach ($logs as $log): ?>
                        <div class="flex items-start gap-2">
                            <i class="fa-solid fa-check text-emerald-400 text-[10px] mt-0.5"></i>
                            <div><?= $log ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <a href="admin/settings.php" class="text-center py-2.5 px-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition-colors flex items-center justify-center gap-1.5 shadow-md">
                    <i class="fa-solid fa-qrcode text-xs"></i>
                    <span>Admin UPI Setup</span>
                </a>
                <a href="wallet.php" class="text-center py-2.5 px-3 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs transition-colors flex items-center justify-center gap-1.5 shadow-md">
                    <i class="fa-solid fa-wallet text-xs"></i>
                    <span>User Wallet</span>
                </a>
            </div>
        <?php else: ?>
            <p class="text-xs text-slate-400 mb-4">Click below to apply schema updates safely without data loss.</p>
            <a href="update_database.php" class="w-full block text-center py-3 rounded-xl bg-gradient-to-r from-cyan-500 to-indigo-600 text-white font-bold text-xs uppercase tracking-wider shadow-lg">
                Run Database Update
            </a>
        <?php endif; ?>
    </div>
</body>
</html>