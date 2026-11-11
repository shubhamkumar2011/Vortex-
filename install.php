<?php
define('INSTALLING', true);
require_once __DIR__ . '/common/config.php';

$installed = false;
$error = null;
$logs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['auto'])) {
    try {
        // Connect to MySQL server without database first to create it
        $rootPdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Create Database if not exists
        $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $logs[] = "Database `" . DB_NAME . "` verified/created successfully.";

        // Connect to the newly created database
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Table 1: users (Starts completely clean with 0 users)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `wallet_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `status` ENUM('active','blocked') NOT NULL DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $logs[] = "Table `users` created successfully.";

        // Table 2: admin
        $pdo->exec("CREATE TABLE IF NOT EXISTS `admin` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $logs[] = "Table `admin` created successfully.";

        // Table 3: tournaments
        $pdo->exec("CREATE TABLE IF NOT EXISTS `tournaments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(150) NOT NULL,
            `game_name` VARCHAR(100) NOT NULL,
            `entry_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `prize_pool` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `commission_percentage` DECIMAL(5,2) NOT NULL DEFAULT 20.00,
            `match_time` DATETIME NOT NULL,
            `room_id` VARCHAR(50) DEFAULT NULL,
            `room_password` VARCHAR(50) DEFAULT NULL,
            `status` ENUM('Upcoming','Live','Completed','Cancelled') NOT NULL DEFAULT 'Upcoming',
            `winner_id` INT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`status`),
            INDEX (`match_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $logs[] = "Table `tournaments` created successfully.";

        // Table 4: participants
        $pdo->exec("CREATE TABLE IF NOT EXISTS `participants` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `tournament_id` INT NOT NULL,
            `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `user_tourney` (`user_id`, `tournament_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`tournament_id`) REFERENCES `tournaments`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $logs[] = "Table `participants` created successfully.";

        // Table 5: transactions
        $pdo->exec("CREATE TABLE IF NOT EXISTS `transactions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `type` ENUM('credit','debit') NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $logs[] = "Table `transactions` created successfully.";

        // Insert Default Admin (admin / admin123)
        $adminUsername = 'admin';
        $adminPassHash = password_hash('admin123', PASSWORD_BCRYPT);
        $checkAdmin = $pdo->prepare("SELECT id FROM `admin` WHERE `username` = ?");
        $checkAdmin->execute([$adminUsername]);
        if (!$checkAdmin->fetch()) {
            $insertAdmin = $pdo->prepare("INSERT INTO `admin` (`username`, `password`) VALUES (?, ?)");
            $insertAdmin->execute([$adminUsername, $adminPassHash]);
            $logs[] = "Default Admin account created: <strong>admin</strong> / <strong>admin123</strong>";
        } else {
            $logs[] = "Admin account already exists.";
        }

        // Seed Sample Tournaments
        $checkTourneys = $pdo->query("SELECT COUNT(*) as cnt FROM `tournaments`")->fetch();
        if ($checkTourneys['cnt'] == 0) {
            $sampleTournaments = [
                [
                    'title' => 'BGMI Battle Royale Squads - Erangel',
                    'game'  => 'BGMI (Battlegrounds Mobile)',
                    'entry' => 50.00,
                    'prize' => 1800.00,
                    'time'  => date('Y-m-d H:i:s', strtotime('+2 hours')),
                    'status'=> 'Upcoming'
                ],
                [
                    'title' => 'Free Fire MAX - Bermuda Clash Squad',
                    'game'  => 'Free Fire MAX',
                    'entry' => 30.00,
                    'prize' => 1000.00,
                    'time'  => date('Y-m-d H:i:s', strtotime('+5 hours')),
                    'status'=> 'Upcoming'
                ],
                [
                    'title' => 'Call of Duty Mobile - Search & Destroy 5v5',
                    'game'  => 'COD Mobile',
                    'entry' => 100.00,
                    'prize' => 3500.00,
                    'time'  => date('Y-m-d H:i:s', strtotime('+1 day')),
                    'status'=> 'Upcoming'
                ],
                [
                    'title' => 'BGMI Pro Scrims Season 4 [LIVE]',
                    'game'  => 'BGMI (Battlegrounds Mobile)',
                    'entry' => 60.00,
                    'prize' => 2200.00,
                    'time'  => date('Y-m-d H:i:s', strtotime('-15 minutes')),
                    'status'=> 'Live',
                    'room_id' => '784920',
                    'room_password' => 'VORTEX99'
                ]
            ];

            $tourneyStmt = $pdo->prepare("INSERT INTO `tournaments` (`title`, `game_name`, `entry_fee`, `prize_pool`, `match_time`, `room_id`, `room_password`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($sampleTournaments as $t) {
                $tourneyStmt->execute([
                    $t['title'],
                    $t['game'],
                    $t['entry'],
                    $t['prize'],
                    $t['time'],
                    $t['room_id'] ?? null,
                    $t['room_password'] ?? null,
                    $t['status']
                ]);
            }
            $logs[] = "Sample tournaments (BGMI, Free Fire MAX, COD Mobile) seeded.";
        }

        $installed = true;
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Database Setup - Vortex Esports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col items-center justify-center p-4">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-indigo-600 to-cyan-400 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                <i class="fa-solid fa-database text-white text-xl"></i>
            </div>
            <div>
                <h1 class="text-xl font-black tracking-wide text-white">VORTEX ESPORTS</h1>
                <p class="text-xs text-cyan-400 font-medium">Automatic Database Setup</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-950/80 border border-rose-600/50 text-rose-200 text-xs p-4 rounded-xl mb-6">
                <div class="flex items-center gap-2 font-bold mb-1">
                    <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
                    <span>Installation Error</span>
                </div>
                <?= htmlspecialchars($error) ?>
                <p class="mt-2 text-slate-400 text-[11px]">Please check MySQL credentials in <code class="text-cyan-300">common/config.php</code>.</p>
            </div>
        <?php endif; ?>

        <?php if ($installed): ?>
            <div class="bg-emerald-950/80 border border-emerald-600/50 text-emerald-200 text-xs p-4 rounded-xl mb-6">
                <div class="flex items-center gap-2 font-bold text-sm mb-2 text-emerald-300">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Database Installed Successfully!</span>
                </div>
                <div class="space-y-1.5 text-[11px] text-slate-300 mb-3">
                    <?php foreach ($logs as $log): ?>
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-emerald-400 text-[10px]"></i>
                            <span><?= $log ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="border-t border-emerald-800/60 pt-2 text-[11px] text-emerald-300 font-semibold">
                    Admin Account: <span class="text-white">admin</span> / <span class="text-white">admin123</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <a href="login.php" class="text-center py-2.5 px-4 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs transition-colors">
                    <i class="fa-solid fa-gamepad mr-1"></i> User Login
                </a>
                <a href="admin/login.php" class="text-center py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition-colors">
                    <i class="fa-solid fa-shield-halved mr-1"></i> Admin Login
                </a>
            </div>
        <?php else: ?>
            <div class="text-xs text-slate-300 mb-6 space-y-2">
                <p>This script will automatically create the <strong>vortex_esports</strong> MySQL database and tables:</p>
                <ul class="list-disc list-inside text-slate-400 text-[11px] space-y-1 pl-1">
                    <li><code class="text-cyan-300">users</code> (clean player directory)</li>
                    <li><code class="text-cyan-300">admin</code> (default: admin / admin123)</li>
                    <li><code class="text-cyan-300">tournaments</code> (game matches & prize pools)</li>
                    <li><code class="text-cyan-300">participants</code> (joined player records)</li>
                    <li><code class="text-cyan-300">transactions</code> (credit & debit logs)</li>
                </ul>
            </div>

            <form method="POST" action="install.php">
                <button type="submit" class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-indigo-600 to-cyan-500 hover:from-indigo-500 hover:to-cyan-400 text-white font-bold text-sm shadow-lg shadow-indigo-500/25 transition-all">
                    <i class="fa-solid fa-play mr-2"></i> Start 1-Click Database Installation
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>