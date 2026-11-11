<?php
require_once __DIR__ . '/common/header.php';

// Handle Actions (Complete / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $withdrawal_id = intval($_POST['withdrawal_id'] ?? 0);

    if ($withdrawal_id > 0) {
        $stmt = $pdo->prepare("SELECT w.*, u.username FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE w.id = ?");
        $stmt->execute([$withdrawal_id]);
        $withdrawal = $stmt->fetch();

        if (!$withdrawal) {
            set_flash('error', 'Withdrawal request not found.');
        } elseif ($withdrawal['status'] !== 'Pending') {
            set_flash('error', 'This withdrawal request has already been processed as ' . $withdrawal['status'] . '.');
        } else {
            if ($action === 'complete') {
                $pdo->beginTransaction();
                try {
                    // 1. Mark withdrawal as Completed
                    $upd = $pdo->prepare("UPDATE withdrawals SET status = 'Completed' WHERE id = ?");
                    $upd->execute([$withdrawal_id]);

                    // 2. Log debit transaction
                    $logTx = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
                    $desc = "Withdrawal Transferred to UPI: " . $withdrawal['upi_id'];
                    $logTx->execute([$withdrawal['user_id'], $withdrawal['amount'], $desc]);

                    $pdo->commit();
                    set_flash('success', "Withdrawal #{$withdrawal_id} of " . format_inr($withdrawal['amount']) . " marked Completed!");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    set_flash('error', 'Database error: ' . $e->getMessage());
                }
            } elseif ($action === 'reject') {
                $pdo->beginTransaction();
                try {
                    $reason = trim($_POST['admin_note'] ?? 'Invalid UPI ID or transfer failed');

                    // 1. Mark as Rejected
                    $upd = $pdo->prepare("UPDATE withdrawals SET status = 'Rejected', admin_note = ? WHERE id = ?");
                    $upd->execute([$reason, $withdrawal_id]);

                    // 2. Refund balance back to user's wallet
                    $refund = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $refund->execute([$withdrawal['amount'], $withdrawal['user_id']]);

                    // 3. Log refund transaction
                    $logTx = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)");
                    $desc = "Withdrawal #{$withdrawal_id} Refunded: " . $reason;
                    $logTx->execute([$withdrawal['user_id'], $withdrawal['amount'], $desc]);

                    $pdo->commit();
                    set_flash('error', "Withdrawal #{$withdrawal_id} rejected. " . format_inr($withdrawal['amount']) . " refunded to @{$withdrawal['username']}.");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    set_flash('error', 'Database error: ' . $e->getMessage());
                }
            }
        }
    }
    header("Location: withdrawals.php");
    exit;
}

// Filter
$tab = $_GET['tab'] ?? 'Pending';
$allowedTabs = ['Pending', 'Completed', 'Rejected', 'All'];
if (!in_array($tab, $allowedTabs)) $tab = 'Pending';

$whereClause = ($tab === 'All') ? "" : "WHERE w.status = :status";
$query = "SELECT w.*, u.username, u.email, u.wallet_balance 
          FROM withdrawals w 
          JOIN users u ON w.user_id = u.id 
          {$whereClause} 
          ORDER BY w.created_at DESC";

$stmt = $pdo->prepare($query);
if ($tab !== 'All') {
    $stmt->bindValue(':status', $tab);
}
$stmt->execute();
$withdrawals = $stmt->fetchAll();

// Counts
$pendingCount = $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'Pending'")->fetchColumn();
?>

<!-- Header -->
<div class="flex items-center justify-between mb-4">
    <div class="flex items-center gap-2">
        <div class="w-2 h-4 bg-amber-400 rounded-full"></div>
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-200">Player Withdrawal Requests</h2>
    </div>
    <span class="text-[10px] font-black px-2 py-0.5 rounded-full <?= $pendingCount > 0 ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'bg-slate-800 text-slate-400' ?>">
        <?= $pendingCount ?> Pending
    </span>
</div>

<!-- Tabs -->
<div class="flex bg-slate-900 border border-slate-800 p-1 rounded-xl mb-4 text-xs font-bold">
    <a href="withdrawals.php?tab=Pending" class="flex-1 text-center py-1.5 rounded-lg transition-all <?= ($tab === 'Pending') ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'text-slate-400 hover:text-white' ?>">
        Pending (<?= $pendingCount ?>)
    </a>
    <a href="withdrawals.php?tab=Completed" class="flex-1 text-center py-1.5 rounded-lg transition-all <?= ($tab === 'Completed') ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'text-slate-400 hover:text-white' ?>">
        Completed
    </a>
    <a href="withdrawals.php?tab=Rejected" class="flex-1 text-center py-1.5 rounded-lg transition-all <?= ($tab === 'Rejected') ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'text-slate-400 hover:text-white' ?>">
        Rejected
    </a>
    <a href="withdrawals.php?tab=All" class="flex-1 text-center py-1.5 rounded-lg transition-all <?= ($tab === 'All') ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'text-slate-400 hover:text-white' ?>">
        All
    </a>
</div>

<!-- List of Requests -->
<?php if (empty($withdrawals)): ?>
    <div class="text-center py-12 px-4 bg-slate-900/40 border border-slate-800 rounded-2xl">
        <i class="fa-solid fa-arrow-up-right text-slate-600 text-2xl mb-2"></i>
        <h4 class="text-xs font-bold text-slate-300">No <?= htmlspecialchars($tab) ?> Withdrawals</h4>
        <p class="text-[11px] text-slate-500 mt-1">Player prize cash-out requests will appear here for payout.</p>
    </div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($withdrawals as $w): ?>
            <?php
            $isPending = ($w['status'] === 'Pending');
            $isCompleted = ($w['status'] === 'Completed');
            ?>
            <div class="bg-[#111827] border border-slate-800 rounded-2xl p-3.5 shadow-md">
                <!-- Request Top -->
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-black text-white">@<?= htmlspecialchars($w['username']) ?></span>
                            <span class="text-[9px] px-2 py-0.5 rounded-full font-bold uppercase <?= $isPending ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : ($isCompleted ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30') ?>">
                                <?= $w['status'] ?>
                            </span>
                        </div>
                        <span class="text-[10px] text-slate-400"><?= date('M d, Y • h:i A', strtotime($w['created_at'])) ?></span>
                    </div>

                    <div class="text-right">
                        <span class="text-sm font-black text-amber-400 block">-<?= format_inr($w['amount']) ?></span>
                        <span class="text-[9px] text-slate-400">Current Bal: <?= format_inr($w['wallet_balance']) ?></span>
                    </div>
                </div>

                <!-- Player UPI ID Box with 1-click Copy -->
                <div class="bg-slate-950/80 border border-slate-800/80 rounded-xl p-2.5 mb-3 flex items-center justify-between">
                    <div>
                        <span class="text-[9px] font-semibold text-slate-400 uppercase tracking-wider block">Send Payout To UPI ID</span>
                        <span class="text-xs font-mono font-bold text-emerald-300 select-all" id="upi-<?= $w['id'] ?>"><?= htmlspecialchars($w['upi_id']) ?></span>
                    </div>
                    <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($w['upi_id']) ?>'); alert('Player UPI ID copied to clipboard!');" class="py-1 px-2.5 rounded-lg bg-emerald-950/50 hover:bg-emerald-900/60 text-emerald-300 text-[10px] font-bold border border-emerald-800/40 flex items-center gap-1 transition-colors">
                        <i class="fa-regular fa-copy text-[10px]"></i> Copy UPI
                    </button>
                </div>

                <?php if (!empty($w['admin_note'])): ?>
                    <p class="text-[10px] text-rose-300 bg-rose-950/30 p-2 rounded-lg border border-rose-900/50 mb-2.5">
                        <i class="fa-solid fa-circle-info mr-1"></i> <?= htmlspecialchars($w['admin_note']) ?>
                    </p>
                <?php endif; ?>

                <!-- Action Controls (Only for Pending) -->
                <?php if ($isPending): ?>
                    <div class="grid grid-cols-2 gap-2 border-t border-slate-800/80 pt-2.5">
                        <!-- Reject Form -->
                        <form method="POST" action="withdrawals.php" onsubmit="return confirm('Reject this withdrawal? Amount will be refunded to player.');">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="withdrawal_id" value="<?= $w['id'] ?>">
                            <button type="submit" class="w-full py-2 px-3 rounded-xl bg-rose-950/50 hover:bg-rose-900/60 border border-rose-800/50 text-rose-300 text-xs font-bold transition-colors flex items-center justify-center gap-1">
                                <i class="fa-solid fa-xmark text-xs"></i>
                                <span>Reject & Refund</span>
                            </button>
                        </form>

                        <!-- Complete Form -->
                        <form method="POST" action="withdrawals.php" onsubmit="return confirm('Confirm you have manually sent <?= format_inr($w['amount']) ?> to <?= htmlspecialchars($w['upi_id']) ?>?');">
                            <input type="hidden" name="action" value="complete">
                            <input type="hidden" name="withdrawal_id" value="<?= $w['id'] ?>">
                            <button type="submit" class="w-full py-2 px-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-black uppercase tracking-wider transition-all shadow-md shadow-amber-500/20 flex items-center justify-center gap-1">
                                <i class="fa-solid fa-paper-plane text-xs"></i>
                                <span>Mark Sent</span>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/common/bottom.php'; ?>