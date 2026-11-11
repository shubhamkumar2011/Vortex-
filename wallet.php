<?php
require_once __DIR__ . '/common/config.php';
check_user_login();
$user_id = $_SESSION['user_id'];

// Helper to fetch setting from DB
function get_setting_val(PDO $pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Fetch Admin UPI settings
$adminUpiId = get_setting_val($pdo, 'admin_upi_id', 'vortexesports@okaxis');
$adminQrCode = get_setting_val($pdo, 'admin_qr_code', 'uploads/admin_qr.svg');

// Fetch user profile (for saved upi_id and current balance)
$uStmt = $pdo->prepare("SELECT wallet_balance, upi_id FROM users WHERE id = ?");
$uStmt->execute([$user_id]);
$currentUserData = $uStmt->fetch();
$balance = floatval($currentUserData['wallet_balance'] ?? 0);
$savedUpiId = $currentUserData['upi_id'] ?? '';

// Handle Form Submissions (Deposit Request & Withdrawal Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Submit Manual UPI Deposit Request
    if ($action === 'submit_deposit') {
        $amount = floatval($_POST['amount'] ?? 0);
        $transaction_id = trim($_POST['transaction_id'] ?? '');

        if ($amount < 10 || $amount > 50000) {
            set_flash('error', 'Please enter a valid deposit amount between ₹10 and ₹50,000.');
        } elseif (empty($transaction_id) || strlen($transaction_id) < 6) {
            set_flash('error', 'Please enter a valid 12-digit UPI Transaction ID / UTR number.');
        } else {
            // Check if transaction ID was already submitted
            $checkTx = $pdo->prepare("SELECT id FROM deposits WHERE transaction_id = ?");
            $checkTx->execute([$transaction_id]);
            if ($checkTx->fetch()) {
                set_flash('error', 'This UPI Transaction ID has already been submitted.');
            } else {
                $depStmt = $pdo->prepare("INSERT INTO deposits (user_id, amount, transaction_id, status) VALUES (?, ?, ?, 'Pending')");
                $depStmt->execute([$user_id, $amount, $transaction_id]);

                set_flash('success', "Deposit request for " . format_inr($amount) . " submitted! Admin will verify UTR: {$transaction_id} and credit your wallet shortly.");
                header('Location: wallet.php');
                exit;
            }
        }
    }

    // 2. Submit Withdrawal Request
    elseif ($action === 'submit_withdrawal') {
        $amount = floatval($_POST['amount'] ?? 0);
        $upi_id = trim($_POST['upi_id'] ?? $savedUpiId);

        if ($amount < 100) {
            set_flash('error', 'Minimum withdrawal amount is ₹100.');
        } elseif ($amount > $balance) {
            set_flash('error', 'Insufficient wallet balance for withdrawal.');
        } elseif (empty($upi_id) || !str_contains($upi_id, '@')) {
            set_flash('error', 'Please enter a valid UPI ID (e.g., mobile@paytm or name@okhdfcbank).');
        } else {
            $pdo->beginTransaction();
            try {
                // Save UPI ID to user profile if not saved
                if ($upi_id !== $savedUpiId) {
                    $pdo->prepare("UPDATE users SET upi_id = ? WHERE id = ?")->execute([$upi_id, $user_id]);
                }

                // Deduct balance from user
                $deduct = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
                $deduct->execute([$amount, $user_id]);

                // Insert into withdrawals table
                $wStmt = $pdo->prepare("INSERT INTO withdrawals (user_id, amount, upi_id, status) VALUES (?, ?, ?, 'Pending')");
                $wStmt->execute([$user_id, $amount, $upi_id]);

                $pdo->commit();
                set_flash('success', "Withdrawal request for " . format_inr($amount) . " submitted! Funds will be transferred to {$upi_id}.");
                header('Location: wallet.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('error', 'Database error: ' . $e->getMessage());
            }
        }
    }
}

// Fetch Pending Requests (Deposits & Withdrawals) for this user
$pendingDeposits = [];
$pendingWithdrawals = [];
try {
    $pdStmt = $pdo->prepare("SELECT * FROM deposits WHERE user_id = ? AND status = 'Pending' ORDER BY created_at DESC");
    $pdStmt->execute([$user_id]);
    $pendingDeposits = $pdStmt->fetchAll();

    $pwStmt = $pdo->prepare("SELECT * FROM withdrawals WHERE user_id = ? AND status = 'Pending' ORDER BY created_at DESC");
    $pwStmt->execute([$user_id]);
    $pendingWithdrawals = $pwStmt->fetchAll();
} catch (Exception $e) {}

// Fetch verified transaction history
$txStmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$txStmt->execute([$user_id]);
$transactions = $txStmt->fetchAll();

require_once __DIR__ . '/common/header.php';
?>

<!-- Prominent Wallet Balance Card -->
<div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-900 via-[#131b2e] to-cyan-950 border border-slate-700/80 p-5 mb-5 shadow-2xl">
    <div class="absolute -right-8 -bottom-8 w-36 h-36 bg-cyan-500/10 rounded-full blur-2xl pointer-events-none"></div>

    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-sm">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <span class="text-xs font-bold text-slate-300 uppercase tracking-wider">Total Balance</span>
        </div>
        <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
            Active
        </span>
    </div>

    <div class="mb-5">
        <div class="text-3xl font-black tracking-tight text-white flex items-baseline gap-1">
            <span><?= format_inr($balance) ?></span>
        </div>
        <p class="text-[11px] text-slate-400 mt-0.5">Instant registration for BGMI, Free Fire & COD matches</p>
    </div>

    <!-- Action Buttons -->
    <div class="grid grid-cols-2 gap-3">
        <button type="button" onclick="openDepositModal()" class="py-2.5 px-3 rounded-xl bg-gradient-to-r from-cyan-500 to-indigo-600 hover:from-cyan-400 hover:to-indigo-500 text-white font-black text-xs uppercase tracking-wider shadow-lg shadow-cyan-600/20 flex items-center justify-center gap-2 transition-all active:scale-95">
            <i class="fa-solid fa-plus-circle text-xs"></i>
            <span>Add Money</span>
        </button>

        <button type="button" onclick="openWithdrawModal()" class="py-2.5 px-3 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 font-black text-xs uppercase tracking-wider flex items-center justify-center gap-2 transition-all active:scale-95">
            <i class="fa-solid fa-arrow-up-from-bracket text-xs text-amber-400"></i>
            <span>Withdraw</span>
        </button>
    </div>
</div>

<!-- Pending Requests Alert Section (If any) -->
<?php if (!empty($pendingDeposits) || !empty($pendingWithdrawals)): ?>
    <div class="bg-amber-950/30 border border-amber-600/40 rounded-2xl p-3 mb-4 space-y-2">
        <div class="flex items-center gap-2 text-xs font-bold text-amber-300 uppercase tracking-wider">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>Under Verification By Admin</span>
        </div>

        <?php foreach ($pendingDeposits as $pd): ?>
            <div class="bg-slate-900/80 border border-amber-500/20 rounded-xl p-2.5 flex items-center justify-between text-xs">
                <div>
                    <div class="flex items-center gap-1.5 font-bold text-white">
                        <i class="fa-solid fa-arrow-down-left text-emerald-400 text-[10px]"></i>
                        <span>Deposit: <?= format_inr($pd['amount']) ?></span>
                    </div>
                    <span class="text-[10px] text-slate-400 font-mono">UTR: <?= htmlspecialchars($pd['transaction_id']) ?></span>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">
                    Verifying
                </span>
            </div>
        <?php endforeach; ?>

        <?php foreach ($pendingWithdrawals as $pw): ?>
            <div class="bg-slate-900/80 border border-amber-500/20 rounded-xl p-2.5 flex items-center justify-between text-xs">
                <div>
                    <div class="flex items-center gap-1.5 font-bold text-white">
                        <i class="fa-solid fa-arrow-up-right text-amber-400 text-[10px]"></i>
                        <span>Withdrawal: <?= format_inr($pw['amount']) ?></span>
                    </div>
                    <span class="text-[10px] text-slate-400 font-mono">UPI: <?= htmlspecialchars($pw['upi_id']) ?></span>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">
                    Processing
                </span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Transaction History Section -->
<div class="mb-3 flex items-center justify-between">
    <div class="flex items-center gap-2">
        <div class="w-2 h-4 bg-indigo-500 rounded-full"></div>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-200">Transaction History</h3>
    </div>
    <span class="text-[11px] text-slate-400 font-medium"><?= count($transactions) ?> Records</span>
</div>

<?php if (empty($transactions)): ?>
    <div class="text-center py-10 px-4 bg-slate-900/50 border border-slate-800/80 rounded-2xl">
        <div class="w-10 h-10 mx-auto rounded-full bg-slate-800 flex items-center justify-center text-slate-500 mb-2 text-sm">
            <i class="fa-solid fa-receipt"></i>
        </div>
        <h4 class="text-xs font-bold text-slate-300">No Transactions Yet</h4>
        <p class="text-[11px] text-slate-500 mt-1">Approved deposits, tournament entry fees, and prize payouts appear here.</p>
    </div>
<?php else: ?>
    <div class="space-y-2.5">
        <?php foreach ($transactions as $tx): ?>
            <?php
            $isCredit = ($tx['type'] === 'credit');
            $timestamp = strtotime($tx['created_at']);
            ?>
            <div class="bg-[#10172a] border border-slate-800/80 rounded-xl p-3 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl <?= $isCredit ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/15 text-rose-400 border border-rose-500/20' ?> flex items-center justify-center text-xs">
                        <i class="fa-solid <?= $isCredit ? 'fa-arrow-down-left' : 'fa-arrow-up-right' ?>"></i>
                    </div>
                    <div>
                        <h5 class="text-xs font-bold text-white line-clamp-1"><?= htmlspecialchars($tx['description']) ?></h5>
                        <span class="text-[10px] text-slate-400"><?= date('M d, Y • h:i A', $timestamp) ?></span>
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-xs font-black <?= $isCredit ? 'text-emerald-400' : 'text-rose-400' ?>">
                        <?= $isCredit ? '+' : '-' ?><?= format_inr($tx['amount']) ?>
                    </span>
                    <span class="text-[9px] block uppercase font-semibold text-slate-400"><?= $tx['type'] ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- MODAL 1: ADD MONEY (MANUAL UPI DEPOSIT) -->
<div id="modal-add-money" class="fixed inset-0 z-50 bg-black/85 backdrop-blur-sm hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 w-full max-w-sm shadow-2xl relative my-auto">
        <button type="button" onclick="closeModal('modal-add-money')" class="absolute top-4 right-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-2 mb-3">
            <div class="w-8 h-8 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-sm">
                <i class="fa-solid fa-qrcode"></i>
            </div>
            <div>
                <h3 class="text-sm font-black text-white uppercase tracking-wide">Add Money via UPI</h3>
                <span class="text-[10px] text-slate-400">Scan QR & enter UTR below</span>
            </div>
        </div>

        <!-- Admin QR Code Box -->
        <div class="bg-slate-950 border border-slate-800 rounded-2xl p-3.5 mb-3.5 text-center shadow-inner">
            <div class="w-36 h-36 mx-auto bg-white rounded-xl p-2 mb-2.5 flex items-center justify-center shadow-md">
                <img src="<?= htmlspecialchars($adminQrCode) ?>?t=<?= time() ?>" alt="Admin UPI QR Code" class="w-full h-full object-contain">
            </div>

            <!-- Admin UPI ID with 1-Click Copy -->
            <div class="flex items-center justify-between bg-slate-900 border border-slate-800 px-2.5 py-1.5 rounded-xl">
                <div class="text-left overflow-hidden">
                    <span class="text-[9px] text-slate-500 font-semibold block uppercase">Admin UPI ID</span>
                    <span class="text-xs font-mono font-bold text-cyan-300 truncate block select-all"><?= htmlspecialchars($adminUpiId) ?></span>
                </div>
                <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($adminUpiId) ?>'); alert('Admin UPI ID copied! Open PhonePe/GPay/Paytm to pay.');" class="py-1 px-2.5 rounded-lg bg-cyan-500/20 text-cyan-300 hover:bg-cyan-500 hover:text-slate-950 font-bold text-[10px] transition-colors flex items-center gap-1">
                    <i class="fa-regular fa-copy text-[10px]"></i> Copy
                </button>
            </div>
        </div>

        <!-- Deposit Request Form -->
        <form method="POST" action="wallet.php" class="space-y-3">
            <input type="hidden" name="action" value="submit_deposit">

            <div>
                <label class="block text-[10px] font-bold text-slate-300 uppercase tracking-wider mb-1">
                    1. Amount Sent (₹) <span class="text-rose-400">*</span>
                </label>
                <input type="number" name="amount" min="10" max="50000" step="10" required placeholder="e.g. 100" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-cyan-400">
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-300 uppercase tracking-wider mb-1">
                    2. UPI Transaction ID / UTR <span class="text-rose-400">*</span>
                </label>
                <input type="text" name="transaction_id" required minlength="6" placeholder="12-digit UTR from GPay / PhonePe" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs font-mono text-cyan-300 focus:outline-none focus:border-cyan-400">
                <span class="text-[9px] text-slate-400 mt-1 block">Check your payment app receipt for the 12-digit Ref / UTR number.</span>
            </div>

            <button type="submit" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-indigo-600 hover:from-cyan-400 hover:to-indigo-500 text-white font-black text-xs uppercase tracking-wider shadow-lg shadow-cyan-500/20 active:scale-[0.98] transition-all">
                Submit Deposit Request
            </button>
        </form>
    </div>
</div>

<!-- MODAL 2: WITHDRAW FUNDS -->
<div id="modal-withdraw" class="fixed inset-0 z-50 bg-black/85 backdrop-blur-sm hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 w-full max-w-sm shadow-2xl relative my-auto">
        <button type="button" onclick="closeModal('modal-withdraw')" class="absolute top-4 right-4 text-slate-400 hover:text-white">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-2 mb-3">
            <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center text-sm">
                <i class="fa-solid fa-arrow-up-from-bracket"></i>
            </div>
            <div>
                <h3 class="text-sm font-black text-white uppercase tracking-wide">Withdraw Winning Cash</h3>
                <span class="text-[10px] text-slate-400">Direct transfer to your UPI</span>
            </div>
        </div>

        <form method="POST" action="wallet.php" class="space-y-3.5">
            <input type="hidden" name="action" value="submit_withdrawal">

            <div>
                <div class="flex justify-between items-center mb-1">
                    <label class="text-[10px] font-bold text-slate-300 uppercase tracking-wider">Amount to Withdraw (₹)</label>
                    <span class="text-[10px] text-cyan-400 font-semibold">Available: <?= format_inr($balance) ?></span>
                </div>
                <input type="number" name="amount" min="100" max="<?= max(100, $balance) ?>" step="10" required placeholder="Min. ₹100" class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
            </div>

            <div>
                <div class="flex justify-between items-center mb-1">
                    <label class="text-[10px] font-bold text-slate-300 uppercase tracking-wider">Your UPI ID (VPA)</label>
                    <a href="profile.php" class="text-[9px] text-cyan-400 hover:underline">Edit in Profile</a>
                </div>
                <div class="relative">
                    <input type="text" name="upi_id" required value="<?= htmlspecialchars($savedUpiId) ?>" placeholder="e.g. mobile@paytm or name@okhdfcbank" class="w-full pl-8 pr-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs font-mono text-emerald-300 focus:outline-none focus:border-amber-400">
                    <i class="fa-solid fa-at absolute left-2.5 top-3 text-slate-500 text-xs"></i>
                </div>
                <span class="text-[9px] text-slate-400 mt-1 block">Admin will transfer cash directly to this UPI address.</span>
            </div>

            <button type="submit" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-rose-600 hover:from-amber-400 hover:to-rose-500 text-white font-black text-xs uppercase tracking-wider shadow-lg shadow-amber-500/20 active:scale-[0.98] transition-all">
                Request Withdrawal
            </button>
        </form>
    </div>
</div>

<script>
    function openDepositModal() {
        const m = document.getElementById('modal-add-money');
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    function openWithdrawModal() {
        const m = document.getElementById('modal-withdraw');
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    function closeModal(id) {
        const m = document.getElementById(id);
        m.classList.add('hidden');
        m.classList.remove('flex');
    }
</script>

<?php
require_once __DIR__ . '/common/bottom.php';
?>