<?php
require_once __DIR__ . '/common/header.php';

// Handle Actions (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $deposit_id = intval($_POST['deposit_id'] ?? 0);

    if ($deposit_id > 0) {
        $stmt = $pdo->prepare("SELECT d.*, u.username FROM deposits d JOIN users u ON d.user_id = u.id WHERE d.id = ?");
        $stmt->execute([$deposit_id]);
        $deposit = $stmt->fetch();

        if (!$deposit) {
            set_flash('error', 'Deposit request not found.');
        } elseif ($deposit['status'] !== 'Pending') {
            set_flash('error', 'This deposit request has already been processed as ' . $deposit['status'] . '.');
        } else {
            if ($action === 'approve') {
                $pdo->beginTransaction();
                try {
                    // 1. Mark deposit as Completed
                    $upd = $pdo->prepare("UPDATE deposits SET status = 'Completed' WHERE id = ?");
                    $upd->execute([$deposit_id]);

                    // 2. Add amount to user's wallet
                    $addBal = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $addBal->execute([$deposit['amount'], $deposit['user_id']]);

                    // 3. Log transaction
                    $logTx = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
                    $desc = "Deposit Approved (UTR: " . $deposit['transaction_id'] . ")";
                    $logTx->execute([$deposit['user_id'], $deposit['amount'], $desc]);

                    $pdo->commit();
                    set_flash('success', "Deposit #{$deposit_id} of " . format_inr($deposit['amount']) . " approved! Credited to @{$deposit['username']}.");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    set_flash('error', 'Database error: ' . $e->getMessage());
                }
            } elseif ($action === 'reject') {
                $reason = trim($_POST['admin_note'] ?? 'Invalid or unverified UTR');
                $upd = $pdo->prepare("UPDATE deposits SET status = 'Rejected', admin_note = ? WHERE id = ?");
                $upd->execute([$reason, $deposit_id]);
                set_flash('error', "Deposit #{$deposit_id} of " . format_inr($deposit['amount']) . " from @{$deposit['username']} rejected.");
            }
        }
    }
    header("Location: deposits.php");
    exit;
}

// Filter
$tab = $_GET['tab'] ?? 'Pending';
$allowedTabs = ['Pending', 'Completed', 'Rejected', 'All'];
if (!in_array($tab, $allowedTabs)) $tab = 'Pending';

$whereClause = ($tab === 'All') ? "" : "WHERE d.status = :status";
$query = "SELECT d.*, u.username, u.email, u.wallet_balance 
          FROM deposits d 
          JOIN users u ON d.user_id = u.id 
          {$whereClause} 
          ORDER BY d.created_at DESC";

$stmt = $pdo->prepare($query);
if ($tab !== 'All') {
    $stmt->bindValue(':status', $tab);
}
$stmt->execute();
$deposits = $stmt->fetchAll();

// Counts
$pendingCount = $pdo->query("SELECT COUNT(*) FROM deposits WHERE status = 'Pending'")->fetchColumn();
?>

<!-- Header -->
<div class="flex items-center justify-between mb-4">
    <div class="flex items-center gap-2">
        <div class="w-2 h-4 bg-emerald-400 rounded-full"></div>
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-200">UPI Deposit Requests</h2>
    </div>
    <span class="text-[10px] font-black px-2 py-0.5 rounded-full <?= $pendingCount > 0 ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'bg-slate-800 text-slate-400' ?>">
        <?= $pendingCount ?> Pending
    </span>
</div>

<!-- Tabs -->
<div class="flex bg-slate-900 border border-slate-800 p-1 rounded-xl mb-4 text-xs font-bold">
    <a href="deposits.php?tab=Pending" class="flex-1 text-center py-1.5 rounded-lg transition-all <?= ($tab === 'Pending') ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'text-slate-400 hover:text-white' ?>">
        Pending (<?= $pendingCount ?>)
    </a>
    <a href="deposits.php?tab=Completed" class="flex-1 text-center py-1.5 rounded-lg transition-all <?= ($tab === 'Completed') ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'text-slate-400 hover:text-white' ?>">
        Completed
    </a>
    <a href="deposits.php?tab=Rejected" class="flex-1 text-center py-1.5 rounded-lg transition-all <?= ($tab === 'Rejected') ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'text-slate-400 hover:text-white' ?>">
        Rejected
    </a>
    <a href="deposits.php?tab=All" class="flex-1 text-center py-1.5 rounded-lg transition-all <?= ($tab === 'All') ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'text-slate-400 hover:text-white' ?>">
        All
    </a>
</div>

<!-- List of Requests -->
<?php if (empty($deposits)): ?>
    <div class="text-center py-12 px-4 bg-slate-900/40 border border-slate-800 rounded-2xl">
        <i class="fa-solid fa-receipt text-slate-600 text-2xl mb-2"></i>
        <h4 class="text-xs font-bold text-slate-300">No <?= htmlspecialchars($tab) ?> Deposits</h4>
        <p class="text-[11px] text-slate-500 mt-1">When users send UPI payments and submit their UTR, requests appear here.</p>
    </div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($deposits as $dep): ?>
            <?php
            $isPending = ($dep['status'] === 'Pending');
            $isCompleted = ($dep['status'] === 'Completed');
            ?>
            <div class="bg-[#111827] border border-slate-800 rounded-2xl p-3.5 shadow-md">
                <!-- Request Top -->
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-black text-white">@<?= htmlspecialchars($dep['username']) ?></span>
                            <span class="text-[9px] px-2 py-0.5 rounded-full font-bold uppercase <?= $isPending ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : ($isCompleted ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30') ?>">
                                <?= $dep['status'] ?>
                            </span>
                        </div>
                        <span class="text-[10px] text-slate-400"><?= date('M d, Y • h:i A', strtotime($dep['created_at'])) ?></span>
                    </div>

                    <div class="text-right">
                        <span class="text-sm font-black text-emerald-400 block">+<?= format_inr($dep['amount']) ?></span>
                        <span class="text-[9px] text-slate-400">Bal: <?= format_inr($dep['wallet_balance']) ?></span>
                    </div>
                </div>

                <!-- UPI Transaction ID (UTR) Box with 1-click Copy -->
                <div class="bg-slate-950/80 border border-slate-800/80 rounded-xl p-2.5 mb-3 flex items-center justify-between">
                    <div>
                        <span class="text-[9px] font-semibold text-slate-400 uppercase tracking-wider block">UTR / Ref No.</span>
                        <span class="text-xs font-mono font-bold text-cyan-300 select-all" id="utr-<?= $dep['id'] ?>"><?= htmlspecialchars($dep['transaction_id']) ?></span>
                    </div>
                    <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($dep['transaction_id']) ?>'); alert('UTR copied to clipboard!');" class="py-1 px-2.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] font-bold flex items-center gap-1 transition-colors">
                        <i class="fa-regular fa-copy text-[10px]"></i> Copy
                    </button>
                </div>

                <?php if (!empty($dep['admin_note'])): ?>
                    <p class="text-[10px] text-rose-300 bg-rose-950/30 p-2 rounded-lg border border-rose-900/50 mb-2.5">
                        <i class="fa-solid fa-circle-info mr-1"></i> <?= htmlspecialchars($dep['admin_note']) ?>
                    </p>
                <?php endif; ?>

                <!-- Action Controls (Only for Pending) -->
                <?php if ($isPending): ?>
                    <div class="grid grid-cols-2 gap-2 border-t border-slate-800/80 pt-2.5">
                        <!-- Reject Form -->
                        <form method="POST" action="deposits.php" onsubmit="return confirm('Reject this deposit request?');">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="deposit_id" value="<?= $dep['id'] ?>">
                            <button type="submit" class="w-full py-2 px-3 rounded-xl bg-rose-950/50 hover:bg-rose-900/60 border border-rose-800/50 text-rose-300 text-xs font-bold transition-colors flex items-center justify-center gap-1">
                                <i class="fa-solid fa-xmark text-xs"></i>
                                <span>Reject</span>
                            </button>
                        </form>

                        <!-- Approve Form -->
                        <form method="POST" action="deposits.php" onsubmit="return confirm('Confirm UTR verification? This will add <?= format_inr($dep['amount']) ?> to @<?= htmlspecialchars($dep['username']) ?> wallet.');">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="deposit_id" value="<?= $dep['id'] ?>">
                            <button type="submit" class="w-full py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black uppercase tracking-wider transition-all shadow-md shadow-emerald-600/20 flex items-center justify-center gap-1">
                                <i class="fa-solid fa-check text-xs"></i>
                                <span>Approve</span>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/common/bottom.php'; ?>