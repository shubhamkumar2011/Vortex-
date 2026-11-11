<?php
require_once __DIR__ . '/../common/config.php';
check_admin_login();

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    header("Location: login.php");
    exit;
}

// 1. Total Users
$usersCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// 2. Total Tournaments
$tourneysCount = $pdo->query("SELECT COUNT(*) FROM tournaments")->fetchColumn();

// 3. Total Prize Distributed (Sum of prize_pool for 'Completed' tournaments)
$prizeDistributed = $pdo->query("SELECT COALESCE(SUM(prize_pool), 0) FROM tournaments WHERE status = 'Completed'")->fetchColumn();

// 4. Total Platform Commission / Revenue
// Calculated from completed tournaments: total collected entry fees minus prize pool, or commission percentage
$commissionRevenue = $pdo->query("
    SELECT COALESCE(SUM(
        (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) * t.entry_fee - t.prize_pool
    ), 0)
    FROM tournaments t
    WHERE t.status = 'Completed'
")->fetchColumn();

// If negative (e.g., demo initial seed), fallback to estimated commission of entry fees
if ($commissionRevenue <= 0) {
    $estimatedEntry = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'debit' AND description LIKE 'Entry Fee%'")->fetchColumn();
    $commissionRevenue = $estimatedEntry * 0.20; // 20% platform cut
}

// Fetch Active & Recent Tournaments
$recentStmt = $pdo->query("
    SELECT t.*, 
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as participant_count
    FROM tournaments t
    ORDER BY t.created_at DESC
    LIMIT 6
");
$recent_tournaments = $recentStmt->fetchAll();

require_once __DIR__ . '/common/header.php';
?>

<!-- Admin Greeting & Quick Action -->
<div class="mb-4">
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950/60 to-slate-900 border border-slate-800 rounded-2xl p-4 shadow-lg flex items-center justify-between">
        <div>
            <span class="text-[10px] uppercase font-bold text-amber-400 tracking-wider block">Administrator Overview</span>
            <h2 class="text-sm font-black text-white">Vortex Platform Command</h2>
        </div>
        <a href="tournament.php" class="py-2 px-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs uppercase tracking-wider shadow-md shadow-amber-500/20 flex items-center gap-1.5 transition-transform active:scale-95">
            <i class="fa-solid fa-plus text-xs"></i>
            <span>New Match</span>
        </a>
    </div>
</div>

<!-- 4 Quick Stats Grid Cards -->
<div class="grid grid-cols-2 gap-2.5 mb-5">
    <!-- Stat 1: Total Users -->
    <div class="bg-[#111827] border border-slate-800 rounded-2xl p-3 shadow-md relative overflow-hidden">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Total Users</span>
            <div class="w-6 h-6 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center text-xs">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
        <div class="text-lg font-black text-white"><?= number_format($usersCount) ?></div>
        <span class="text-[9px] text-slate-400 mt-0.5 block">Registered Players</span>
    </div>

    <!-- Stat 2: Total Tournaments -->
    <div class="bg-[#111827] border border-slate-800 rounded-2xl p-3 shadow-md relative overflow-hidden">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Tournaments</span>
            <div class="w-6 h-6 rounded-lg bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-xs">
                <i class="fa-solid fa-trophy"></i>
            </div>
        </div>
        <div class="text-lg font-black text-white"><?= number_format($tourneysCount) ?></div>
        <span class="text-[9px] text-slate-400 mt-0.5 block">All Matches Created</span>
    </div>

    <!-- Stat 3: Total Prize Distributed -->
    <div class="bg-[#111827] border border-slate-800 rounded-2xl p-3 shadow-md relative overflow-hidden">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Prize Payouts</span>
            <div class="w-6 h-6 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-xs">
                <i class="fa-solid fa-coins"></i>
            </div>
        </div>
        <div class="text-lg font-black text-emerald-400"><?= format_inr($prizeDistributed) ?></div>
        <span class="text-[9px] text-slate-400 mt-0.5 block">Sent to Winners</span>
    </div>

    <!-- Stat 4: Total Commission Revenue -->
    <div class="bg-[#111827] border border-slate-800 rounded-2xl p-3 shadow-md relative overflow-hidden">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Total Revenue</span>
            <div class="w-6 h-6 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center text-xs">
                <i class="fa-solid fa-chart-line"></i>
            </div>
        </div>
        <div class="text-lg font-black text-amber-400"><?= format_inr($commissionRevenue) ?></div>
        <span class="text-[9px] text-slate-400 mt-0.5 block">Platform Commission</span>
    </div>
</div>

<!-- Section: Recent Tournaments -->
<div class="flex items-center justify-between mb-3">
    <div class="flex items-center gap-2">
        <div class="w-2 h-4 bg-amber-400 rounded-full"></div>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-200">Recent Tournaments</h3>
    </div>
    <a href="tournament.php" class="text-[11px] font-bold text-amber-400 hover:text-amber-300">View All</a>
</div>

<div class="space-y-2.5">
    <?php foreach ($recent_tournaments as $t):
        $badgeClass = 'bg-cyan-500/20 text-cyan-400 border-cyan-500/40';
        if ($t['status'] === 'Live') {
            $badgeClass = 'bg-rose-500/20 text-rose-400 border-rose-500/40';
        } elseif ($t['status'] === 'Completed') {
            $badgeClass = 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40';
        }
    ?>
        <div class="bg-[#111827] border border-slate-800 rounded-xl p-3 flex items-center justify-between shadow-sm">
            <div class="flex-1 min-w-0 pr-3">
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase border <?= $badgeClass ?>">
                        <?= $t['status'] ?>
                    </span>
                    <span class="text-[10px] text-slate-400 truncate"><?= htmlspecialchars($t['game_name']) ?></span>
                </div>
                <h4 class="text-xs font-bold text-white truncate"><?= htmlspecialchars($t['title']) ?></h4>
                <div class="text-[10px] text-slate-400 mt-0.5 flex items-center gap-3">
                    <span>Pool: <strong class="text-emerald-400"><?= format_inr($t['prize_pool']) ?></strong></span>
                    <span>Players: <strong class="text-cyan-400"><?= $t['participant_count'] ?></strong></span>
                </div>
            </div>

            <a href="manage_tournament.php?id=<?= $t['id'] ?>" class="py-1.5 px-3 bg-slate-800 hover:bg-slate-700 text-amber-300 font-bold text-[11px] rounded-lg border border-slate-700 transition-colors whitespace-nowrap">
                Manage <i class="fa-solid fa-chevron-right text-[9px] ml-1"></i>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<?php
require_once __DIR__ . '/common/bottom.php';
?>