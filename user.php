<?php
require_once __DIR__ . '/../common/config.php';
check_admin_login();

// Handle Block / Unblock Toggle
if (isset($_GET['action']) && in_array($_GET['action'], ['block', 'unblock'])) {
    $uid = intval($_GET['id'] ?? 0);
    $newStatus = ($_GET['action'] === 'block') ? 'blocked' : 'active';

    if ($uid > 0) {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $uid]);
        set_flash('success', 'User ID #' . $uid . ' status set to ' . strtoupper($newStatus) . '.');
    }
    header("Location: user.php");
    exit;
}

// Fetch all registered users with match count
$users = $pdo->query("
    SELECT u.*, 
    (SELECT COUNT(*) FROM participants WHERE user_id = u.id) as matches_count,
    (SELECT COUNT(*) FROM tournaments WHERE winner_id = u.id) as matches_won
    FROM users u
    ORDER BY u.created_at DESC
")->fetchAll();

require_once __DIR__ . '/common/header.php';
?>

<!-- Header -->
<div class="flex items-center justify-between mb-4">
    <div class="flex items-center gap-2">
        <div class="w-2 h-4 bg-cyan-400 rounded-full"></div>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-200">Registered Players (<?= count($users) ?>)</h3>
    </div>
</div>

<div class="space-y-3">
    <?php foreach ($users as $u): ?>
        <?php
        $isBlocked = ($u['status'] === 'blocked');
        ?>
        <div class="bg-[#111827] border <?= $isBlocked ? 'border-rose-900/60 bg-rose-950/10' : 'border-slate-800' ?> rounded-2xl p-3.5 shadow-md">
            <div class="flex items-start justify-between gap-3 mb-2.5">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center text-slate-300 font-bold text-sm">
                        <?= strtoupper(substr($u['username'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="text-xs font-extrabold text-white">@<?= htmlspecialchars($u['username']) ?></h4>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase <?= $isBlocked ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' ?>">
                                <?= $u['status'] ?>
                            </span>
                        </div>
                        <span class="text-[10px] text-slate-400 block"><?= htmlspecialchars($u['email']) ?></span>
                    </div>
                </div>

                <div class="text-right">
                    <span class="text-[10px] text-slate-400 uppercase font-semibold block">Wallet</span>
                    <span class="text-xs font-black text-cyan-400"><?= format_inr($u['wallet_balance']) ?></span>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="grid grid-cols-3 gap-2 py-1.5 px-2 bg-slate-900/80 rounded-xl text-center text-[10px] mb-2.5 text-slate-300">
                <div>Matches: <strong><?= $u['matches_count'] ?></strong></div>
                <div class="border-x border-slate-800">Won: <strong class="text-amber-400">🏆 <?= $u['matches_won'] ?></strong></div>
                <div>Joined: <strong><?= date('M d', strtotime($u['created_at'])) ?></strong></div>
            </div>

            <!-- Controls: Block / Unblock -->
            <div class="flex items-center justify-between border-t border-slate-800/80 pt-2 text-[11px]">
                <span class="text-[10px] text-slate-500">ID: #<?= $u['id'] ?></span>
                <?php if ($isBlocked): ?>
                    <a href="user.php?action=unblock&id=<?= $u['id'] ?>" class="py-1 px-3 rounded-lg bg-emerald-950/50 hover:bg-emerald-900/60 border border-emerald-800/50 text-emerald-300 font-bold transition-colors">
                        <i class="fa-solid fa-unlock mr-1"></i> Unblock Player
                    </a>
                <?php else: ?>
                    <a href="user.php?action=block&id=<?= $u['id'] ?>" onclick="return confirm('Suspend user @<?= htmlspecialchars($u['username']) ?>?');" class="py-1 px-3 rounded-lg bg-rose-950/40 hover:bg-rose-900/60 border border-rose-800/50 text-rose-300 font-bold transition-colors">
                        <i class="fa-solid fa-ban mr-1"></i> Block Player
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php
require_once __DIR__ . '/common/bottom.php';
?>