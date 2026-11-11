<?php
require_once __DIR__ . '/../common/config.php';
check_admin_login();

// Handle Delete Request
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        $delStmt = $pdo->prepare("DELETE FROM tournaments WHERE id = ?");
        $delStmt->execute([$id]);
        set_flash('success', 'Tournament ID #' . $id . ' deleted successfully.');
    }
    header("Location: tournament.php");
    exit;
}

// Handle Form Submission: Create New Tournament
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title      = trim($_POST['title'] ?? '');
    $game_name  = trim($_POST['game_name'] ?? '');
    $entry_fee  = floatval($_POST['entry_fee'] ?? 0);
    $prize_pool = floatval($_POST['prize_pool'] ?? 0);
    $commission = floatval($_POST['commission_percentage'] ?? 20.0);
    $match_date = trim($_POST['match_date'] ?? '');
    $match_time = trim($_POST['match_time'] ?? '');

    if (empty($title) || empty($game_name) || empty($match_date) || empty($match_time)) {
        set_flash('error', 'Please fill in all tournament information fields.');
    } else {
        $fullDatetime = $match_date . ' ' . $match_time . ':00';

        $insertStmt = $pdo->prepare("
            INSERT INTO tournaments (title, game_name, entry_fee, prize_pool, commission_percentage, match_time, status)
            VALUES (?, ?, ?, ?, ?, ?, 'Upcoming')
        ");
        $insertStmt->execute([$title, $game_name, $entry_fee, $prize_pool, $commission, $fullDatetime]);

        set_flash('success', 'Tournament "' . htmlspecialchars($title) . '" created successfully!');
        header("Location: tournament.php");
        exit;
    }
}

// Fetch all tournaments with participant counts
$tournaments = $pdo->query("
    SELECT t.*, 
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as participant_count
    FROM tournaments t
    ORDER BY t.match_time DESC
")->fetchAll();

require_once __DIR__ . '/common/header.php';
?>

<!-- Section 1: Create Tournament Form -->
<div class="bg-[#111827] border border-slate-800 rounded-2xl p-4 mb-5 shadow-xl">
    <div class="flex items-center gap-2 mb-3">
        <div class="w-6 h-6 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center text-xs">
            <i class="fa-solid fa-circle-plus"></i>
        </div>
        <h3 class="text-xs font-black text-white uppercase tracking-wider">Create New Tournament</h3>
    </div>

    <form method="POST" action="tournament.php" class="space-y-3">
        <div>
            <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Tournament Title</label>
            <input type="text" name="title" required placeholder="e.g. BGMI Squad Championship Finals" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400">
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Game Name</label>
                <select name="game_name" required class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400">
                    <option value="BGMI (Battlegrounds Mobile)">BGMI Mobile</option>
                    <option value="Free Fire MAX">Free Fire MAX</option>
                    <option value="Call of Duty Mobile">Call of Duty Mobile</option>
                    <option value="Valorant Mobile">Valorant Mobile</option>
                    <option value="Apex Legends Mobile">Apex Legends Mobile</option>
                    <option value="Clash Royale">Clash Royale</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Commission (%)</label>
                <input type="number" name="commission_percentage" value="20" min="0" max="100" step="1" required class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Entry Fee (₹)</label>
                <input type="number" name="entry_fee" min="0" step="5" required value="50" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400">
            </div>
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Prize Pool (₹)</label>
                <input type="number" name="prize_pool" min="0" step="50" required value="1500" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Match Date</label>
                <input type="date" name="match_date" required value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400">
            </div>
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Match Time</label>
                <input type="time" name="match_time" required value="<?= date('H:i', strtotime('+3 hours')) ?>" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400">
            </div>
        </div>

        <button type="submit" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-rose-600 hover:from-amber-400 hover:to-rose-500 text-white font-bold text-xs uppercase tracking-wider shadow-lg shadow-amber-600/20 active:scale-[0.98] transition-all">
            Publish Tournament
        </button>
    </form>
</div>

<!-- Section 2: Created Tournaments List -->
<div class="flex items-center justify-between mb-3">
    <div class="flex items-center gap-2">
        <div class="w-2 h-4 bg-indigo-500 rounded-full"></div>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-200">All Tournaments (<?= count($tournaments) ?>)</h3>
    </div>
</div>

<div class="space-y-3">
    <?php foreach ($tournaments as $t):
        $badgeClass = 'bg-cyan-500/20 text-cyan-400 border-cyan-500/40';
        if ($t['status'] === 'Live') {
            $badgeClass = 'bg-rose-500/20 text-rose-400 border-rose-500/40';
        } elseif ($t['status'] === 'Completed') {
            $badgeClass = 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40';
        }
    ?>
        <div class="bg-[#111827] border border-slate-800 rounded-2xl p-3.5 shadow-md">
            <div class="flex items-start justify-between gap-2 mb-2">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase border <?= $badgeClass ?>">
                            <?= $t['status'] ?>
                        </span>
                        <span class="text-[10px] text-slate-400 font-semibold"><?= htmlspecialchars($t['game_name']) ?></span>
                    </div>
                    <h4 class="text-xs font-bold text-white"><?= htmlspecialchars($t['title']) ?></h4>
                </div>
                <span class="text-[10px] text-slate-400 whitespace-nowrap"><?= date('M d, h:i A', strtotime($t['match_time'])) ?></span>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-1 py-1.5 px-2 bg-slate-900/60 rounded-lg text-center text-[10px] mb-3 text-slate-300">
                <div>Fee: <strong class="text-amber-400"><?= format_inr($t['entry_fee']) ?></strong></div>
                <div class="border-x border-slate-800">Prize: <strong class="text-emerald-400"><?= format_inr($t['prize_pool']) ?></strong></div>
                <div>Players: <strong class="text-cyan-400"><?= $t['participant_count'] ?></strong></div>
            </div>

            <!-- Action buttons -->
            <div class="flex items-center justify-end gap-2 border-t border-slate-800/80 pt-2.5">
                <a href="manage_tournament.php?id=<?= $t['id'] ?>" class="py-1 px-3 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/30 text-amber-300 font-bold text-[11px] transition-colors">
                    <i class="fa-solid fa-sliders text-[10px] mr-1"></i> Manage & Winner
                </a>
                <a href="tournament.php?action=delete&id=<?= $t['id'] ?>" onclick="return confirm('Are you sure you want to delete this tournament?');" class="py-1 px-2.5 rounded-lg bg-rose-950/40 hover:bg-rose-900/60 border border-rose-800/50 text-rose-300 font-bold text-[11px] transition-colors">
                    <i class="fa-solid fa-trash text-[10px]"></i>
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php
require_once __DIR__ . '/common/bottom.php';
?>