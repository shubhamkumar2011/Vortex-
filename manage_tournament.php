<?php
require_once __DIR__ . '/../common/config.php';
check_admin_login();

$tournament_id = intval($_GET['id'] ?? 0);
if ($tournament_id <= 0) {
    set_flash('error', 'Invalid tournament ID.');
    header("Location: tournament.php");
    exit;
}

// Fetch Tournament Details
$stmt = $pdo->prepare("
    SELECT t.*, u.username as winner_name
    FROM tournaments t
    LEFT JOIN users u ON t.winner_id = u.id
    WHERE t.id = ?
");
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch();

if (!$tournament) {
    set_flash('error', 'Tournament not found.');
    header("Location: tournament.php");
    exit;
}

// Handle Room ID & Credentials Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_room') {
    $roomId   = trim($_POST['room_id'] ?? '');
    $roomPass = trim($_POST['room_password'] ?? '');
    $status   = $_POST['status'] ?? $tournament['status'];

    $upStmt = $pdo->prepare("UPDATE tournaments SET room_id = ?, room_password = ?, status = ? WHERE id = ?");
    $upStmt->execute([$roomId, $roomPass, $status, $tournament_id]);

    set_flash('success', 'Room credentials and tournament status updated successfully!');
    header("Location: manage_tournament.php?id=" . $tournament_id);
    exit;
}

// Handle Declare Winner & Distribute Prize
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'declare_winner') {
    $winner_user_id = intval($_POST['winner_user_id'] ?? 0);

    if ($tournament['status'] === 'Completed') {
        set_flash('error', 'Winner has already been declared for this tournament.');
        header("Location: manage_tournament.php?id=" . $tournament_id);
        exit;
    }

    if ($winner_user_id <= 0) {
        set_flash('error', 'Please select a valid participant from the dropdown.');
        header("Location: manage_tournament.php?id=" . $tournament_id);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Verify winner is in participants
        $vpStmt = $pdo->prepare("SELECT u.id, u.username FROM participants p JOIN users u ON p.user_id = u.id WHERE p.tournament_id = ? AND p.user_id = ?");
        $vpStmt->execute([$tournament_id, $winner_user_id]);
        $winnerUser = $vpStmt->fetch();

        if (!$winnerUser) {
            $pdo->rollBack();
            set_flash('error', 'The selected user is not a participant in this tournament.');
            header("Location: manage_tournament.php?id=" . $tournament_id);
            exit;
        }

        $prize = floatval($tournament['prize_pool']);

        // 2. Add prize_pool to winner's wallet
        $creditStmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $creditStmt->execute([$prize, $winner_user_id]);

        // 3. Record transaction for winner
        $txDesc = "Tournament 1st Prize: " . $tournament['title'];
        $txStmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
        $txStmt->execute([$winner_user_id, $prize, $txDesc]);

        // 4. Update tournament status to Completed and save winner_id
        $completeStmt = $pdo->prepare("UPDATE tournaments SET status = 'Completed', winner_id = ? WHERE id = ?");
        $completeStmt->execute([$winner_user_id, $tournament_id]);

        $pdo->commit();

        set_flash('success', 'Winner declared! ' . format_inr($prize) . ' successfully distributed to @' . htmlspecialchars($winnerUser['username']) . '\'s wallet.');
        header("Location: manage_tournament.php?id=" . $tournament_id);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('error', 'Failed to distribute prize: ' . $e->getMessage());
        header("Location: manage_tournament.php?id=" . $tournament_id);
        exit;
    }
}

// Fetch all joined participants for this tournament
$partStmt = $pdo->prepare("
    SELECT p.joined_at, u.id as user_id, u.username, u.email, u.wallet_balance
    FROM participants p
    JOIN users u ON p.user_id = u.id
    WHERE p.tournament_id = ?
    ORDER BY p.joined_at ASC
");
$partStmt->execute([$tournament_id]);
$participants = $partStmt->fetchAll();

require_once __DIR__ . '/common/header.php';
?>

<!-- Back link -->
<div class="mb-3">
    <a href="tournament.php" class="text-xs text-slate-400 hover:text-white flex items-center gap-1.5 font-medium">
        <i class="fa-solid fa-arrow-left text-[10px]"></i>
        <span>Back to Tournaments</span>
    </a>
</div>

<!-- Tournament Details Header -->
<div class="bg-[#111827] border border-slate-800 rounded-2xl p-4 mb-4 shadow-xl">
    <div class="flex items-center justify-between mb-2">
        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase border <?= ($tournament['status'] === 'Completed') ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40' : (($tournament['status'] === 'Live') ? 'bg-rose-500/20 text-rose-400 border-rose-500/40' : 'bg-cyan-500/20 text-cyan-400 border-cyan-500/40') ?>">
            <?= $tournament['status'] ?>
        </span>
        <span class="text-[10px] text-slate-400 font-semibold"><?= htmlspecialchars($tournament['game_name']) ?></span>
    </div>
    <h2 class="text-sm font-black text-white mb-2"><?= htmlspecialchars($tournament['title']) ?></h2>

    <div class="grid grid-cols-3 gap-2 py-2 px-2.5 bg-slate-900/80 rounded-xl text-center text-xs">
        <div>
            <span class="text-[10px] text-slate-400 block uppercase">Entry Fee</span>
            <span class="font-extrabold text-amber-400"><?= format_inr($tournament['entry_fee']) ?></span>
        </div>
        <div class="border-x border-slate-800">
            <span class="text-[10px] text-slate-400 block uppercase">Prize Pool</span>
            <span class="font-extrabold text-emerald-400"><?= format_inr($tournament['prize_pool']) ?></span>
        </div>
        <div>
            <span class="text-[10px] text-slate-400 block uppercase">Players</span>
            <span class="font-bold text-cyan-400"><?= count($participants) ?> Joined</span>
        </div>
    </div>

    <?php if ($tournament['status'] === 'Completed'): ?>
        <div class="mt-3 p-2.5 bg-emerald-950/40 border border-emerald-800/40 rounded-xl flex items-center justify-between text-xs">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-trophy text-amber-400"></i>
                <span class="text-slate-300">Champion: <strong class="text-white">@<?= htmlspecialchars($tournament['winner_name'] ?? 'Player') ?></strong></span>
            </div>
            <span class="text-[10px] font-bold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded">Prizes Distributed</span>
        </div>
    <?php endif; ?>
</div>

<!-- FORM 1: ROOM CREDENTIALS & STATUS -->
<div class="bg-[#111827] border border-slate-800 rounded-2xl p-4 mb-4 shadow-xl">
    <div class="flex items-center gap-2 mb-3">
        <i class="fa-solid fa-key text-xs text-amber-400"></i>
        <h3 class="text-xs font-black text-white uppercase tracking-wider">Room Credentials & Status</h3>
    </div>

    <form method="POST" action="manage_tournament.php?id=<?= $tournament_id ?>" class="space-y-3">
        <input type="hidden" name="action" value="update_room">

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Room ID</label>
                <input type="text" name="room_id" value="<?= htmlspecialchars($tournament['room_id'] ?? '') ?>" placeholder="e.g. 549321" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400">
            </div>
            <div>
                <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Room Password</label>
                <input type="text" name="room_password" value="<?= htmlspecialchars($tournament['room_password'] ?? '') ?>" placeholder="e.g. PASS123" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400">
            </div>
        </div>

        <div>
            <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Match Status</label>
            <select name="status" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400">
                <option value="Upcoming" <?= ($tournament['status'] === 'Upcoming') ? 'selected' : '' ?>>Upcoming (Registration Open)</option>
                <option value="Live" <?= ($tournament['status'] === 'Live') ? 'selected' : '' ?>>Live (Display Room ID to Players)</option>
                <option value="Completed" <?= ($tournament['status'] === 'Completed') ? 'selected' : '' ?>>Completed</option>
                <option value="Cancelled" <?= ($tournament['status'] === 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>

        <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-amber-300 font-bold text-xs uppercase tracking-wider border border-slate-700 transition-colors">
            Update Room & Status
        </button>
    </form>
</div>

<!-- FORM 2: DECLARE WINNER & DISTRIBUTE PRIZE -->
<?php if ($tournament['status'] !== 'Completed'): ?>
    <div class="bg-[#111827] border border-amber-500/40 rounded-2xl p-4 mb-4 shadow-xl relative overflow-hidden">
        <div class="flex items-center gap-2 mb-3">
            <i class="fa-solid fa-trophy text-xs text-amber-400"></i>
            <h3 class="text-xs font-black text-amber-300 uppercase tracking-wider">Declare Winner & Distribute Prize</h3>
        </div>

        <?php if (empty($participants)): ?>
            <p class="text-xs text-slate-400">No participants have joined this tournament yet. Winner can only be declared once players join.</p>
        <?php else: ?>
            <form method="POST" action="manage_tournament.php?id=<?= $tournament_id ?>" class="space-y-3" onsubmit="return confirm('Confirm prize payout of <?= format_inr($tournament['prize_pool']) ?> to the selected winner? This will mark match as Completed.');">
                <input type="hidden" name="action" value="declare_winner">

                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Select Winner from Participants</label>
                    <select name="winner_user_id" required class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
                        <option value="">-- Choose Winning Player --</option>
                        <?php foreach ($participants as $p): ?>
                            <option value="<?= $p['user_id'] ?>">@<?= htmlspecialchars($p['username']) ?> (<?= htmlspecialchars($p['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="p-2.5 bg-amber-950/30 border border-amber-800/40 rounded-xl text-[11px] text-amber-200">
                    <i class="fa-solid fa-circle-info mr-1"></i> Submitting will automatically credit <strong><?= format_inr($tournament['prize_pool']) ?></strong> to winner's wallet and set tournament to Completed.
                </div>

                <button type="submit" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-emerald-600 hover:from-amber-400 hover:to-emerald-500 text-slate-950 font-black text-xs uppercase tracking-wider shadow-lg transition-transform active:scale-[0.98]">
                    <i class="fa-solid fa-coins mr-1"></i> Declare Winner & Distribute Prize
                </button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- PARTICIPANTS LIST -->
<div class="flex items-center justify-between mb-3">
    <div class="flex items-center gap-2">
        <div class="w-2 h-4 bg-cyan-400 rounded-full"></div>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-200">Joined Participants (<?= count($participants) ?>)</h3>
    </div>
</div>

<?php if (empty($participants)): ?>
    <div class="text-center py-8 px-4 bg-slate-900/50 border border-slate-800 rounded-xl text-xs text-slate-400">
        No players have registered for this tournament yet.
    </div>
<?php else: ?>
    <div class="space-y-2">
        <?php foreach ($participants as $index => $p): ?>
            <?php $isWinner = ($tournament['winner_id'] == $p['user_id']); ?>
            <div class="bg-[#111827] border <?= $isWinner ? 'border-amber-500/60 bg-amber-950/10' : 'border-slate-800' ?> rounded-xl p-3 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-slate-800 text-slate-400 flex items-center justify-center text-[10px] font-bold">
                        <?= $index + 1 ?>
                    </span>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-bold text-white">@<?= htmlspecialchars($p['username']) ?></span>
                            <?php if ($isWinner): ?>
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase bg-amber-500/20 text-amber-400 border border-amber-500/40">
                                    <i class="fa-solid fa-crown text-[8px] mr-0.5"></i> Winner
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="text-[10px] text-slate-400 block"><?= htmlspecialchars($p['email']) ?></span>
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-[10px] text-slate-400 block">Joined</span>
                    <span class="text-[10px] font-semibold text-slate-300"><?= date('M d, h:i A', strtotime($p['joined_at'])) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/common/bottom.php';
?>