<?php
require_once __DIR__ . '/common/config.php';
check_user_login();
$user_id = $_SESSION['user_id'];

$tab = $_GET['tab'] ?? 'active'; // 'active' or 'completed'

// 1. Fetch Active/Live tournaments joined by user
$activeStmt = $pdo->prepare("
    SELECT t.*, p.joined_at,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as total_players
    FROM tournaments t
    JOIN participants p ON t.id = p.tournament_id
    WHERE p.user_id = ? AND t.status IN ('Upcoming', 'Live')
    ORDER BY t.match_time ASC
");
$activeStmt->execute([$user_id]);
$active_tournaments = $activeStmt->fetchAll();

// 2. Fetch Completed tournaments joined by user
$completedStmt = $pdo->prepare("
    SELECT t.*, p.joined_at, u.username as winner_name,
    (SELECT COUNT(*) FROM participants WHERE tournament_id = t.id) as total_players
    FROM tournaments t
    JOIN participants p ON t.id = p.tournament_id
    LEFT JOIN users u ON t.winner_id = u.id
    WHERE p.user_id = ? AND t.status = 'Completed'
    ORDER BY t.match_time DESC
");
$completedStmt->execute([$user_id]);
$completed_tournaments = $completedStmt->fetchAll();

require_once __DIR__ . '/common/header.php';
?>

<!-- Two-Tab Header -->
<div class="mb-4">
    <div class="grid grid-cols-2 p-1 bg-slate-900 border border-slate-800 rounded-xl">
        <a href="my_tournaments.php?tab=active" class="py-2 text-center text-xs font-bold rounded-lg transition-all <?= ($tab === 'active') ? 'bg-cyan-500 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white' ?>">
            <i class="fa-solid fa-gamepad mr-1.5"></i> Upcoming / Live
            <span class="ml-1 px-1.5 py-0.2 rounded-full text-[10px] <?= ($tab === 'active') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-slate-400' ?>"><?= count($active_tournaments) ?></span>
        </a>
        <a href="my_tournaments.php?tab=completed" class="py-2 text-center text-xs font-bold rounded-lg transition-all <?= ($tab === 'completed') ? 'bg-cyan-500 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white' ?>">
            <i class="fa-solid fa-clock-rotate-left mr-1.5"></i> Completed
            <span class="ml-1 px-1.5 py-0.2 rounded-full text-[10px] <?= ($tab === 'completed') ? 'bg-slate-950/20 text-slate-950' : 'bg-slate-800 text-slate-400' ?>"><?= count($completed_tournaments) ?></span>
        </a>
    </div>
</div>

<?php if ($tab === 'active'): ?>
    <!-- TAB 1: UPCOMING / LIVE TOURNAMENTS -->
    <?php if (empty($active_tournaments)): ?>
        <div class="text-center py-12 px-4 bg-slate-900/50 border border-slate-800/80 rounded-2xl">
            <div class="w-12 h-12 mx-auto rounded-full bg-slate-800 flex items-center justify-center text-slate-500 mb-3 text-lg">
                <i class="fa-solid fa-trophy"></i>
            </div>
            <h4 class="text-xs font-bold text-slate-300">No Active Tournaments</h4>
            <p class="text-[11px] text-slate-500 mt-1 mb-4">You haven't joined any upcoming tournaments yet.</p>
            <a href="index.php" class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition-colors">
                <i class="fa-solid fa-bolt"></i> Explore Tournaments
            </a>
        </div>
    <?php else: ?>
        <div class="space-y-3.5">
            <?php foreach ($active_tournaments as $t): ?>
                <?php
                $isLive = ($t['status'] === 'Live');
                $matchTimestamp = strtotime($t['match_time']);
                ?>
                <div class="bg-[#10172a] border <?= $isLive ? 'border-cyan-500/80 shadow-cyan-500/10' : 'border-slate-800/90' ?> rounded-2xl overflow-hidden shadow-lg">
                    <!-- Status Header -->
                    <div class="px-3.5 py-2 <?= $isLive ? 'bg-cyan-950/60 border-b border-cyan-800/60' : 'bg-slate-900/80 border-b border-slate-800/60' ?> flex items-center justify-between text-[11px]">
                        <div class="flex items-center gap-2">
                            <?php if ($isLive): ?>
                                <span class="flex h-2 w-2 relative">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                                </span>
                                <span class="font-extrabold text-rose-400 uppercase tracking-wider">LIVE NOW</span>
                            <?php else: ?>
                                <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                <span class="font-bold text-amber-300 uppercase tracking-wide">Upcoming</span>
                            <?php endif; ?>
                            <span class="text-slate-400">• <?= htmlspecialchars($t['game_name']) ?></span>
                        </div>
                        <span class="text-slate-400 font-medium"><?= date('M d, h:i A', $matchTimestamp) ?></span>
                    </div>

                    <div class="p-3.5">
                        <h4 class="font-bold text-sm text-white mb-2"><?= htmlspecialchars($t['title']) ?></h4>

                        <!-- Stats Row -->
                        <div class="grid grid-cols-2 gap-2 py-2 px-2.5 bg-slate-950/60 rounded-xl border border-slate-800/50 mb-3 text-center text-xs">
                            <div>
                                <span class="text-[10px] text-slate-400 block uppercase">Prize Pool</span>
                                <span class="font-extrabold text-emerald-400"><?= format_inr($t['prize_pool']) ?></span>
                            </div>
                            <div class="border-l border-slate-800">
                                <span class="text-[10px] text-slate-400 block uppercase">Players</span>
                                <span class="font-bold text-cyan-300"><?= $t['total_players'] ?> Joined</span>
                            </div>
                        </div>

                        <!-- ROOM CREDENTIALS SECTION -->
                        <?php if ($isLive && !empty($t['room_id'])): ?>
                            <div class="bg-gradient-to-r from-cyan-950/80 to-indigo-950/80 border border-cyan-500/50 rounded-xl p-3 shadow-inner">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-1.5 text-xs font-black text-cyan-300 uppercase tracking-wider">
                                        <i class="fa-solid fa-key text-cyan-400"></i>
                                        <span>Custom Room Credentials</span>
                                    </div>
                                    <span class="text-[10px] bg-cyan-400 text-slate-950 font-bold px-1.5 py-0.5 rounded">Ready</span>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div class="bg-slate-900/90 border border-slate-700 p-2 rounded-lg text-center">
                                        <span class="text-[10px] text-slate-400 block font-semibold uppercase">Room ID</span>
                                        <span class="font-mono font-bold text-sm text-white tracking-widest select-all"><?= htmlspecialchars($t['room_id']) ?></span>
                                    </div>
                                    <div class="bg-slate-900/90 border border-slate-700 p-2 rounded-lg text-center">
                                        <span class="text-[10px] text-slate-400 block font-semibold uppercase">Password</span>
                                        <span class="font-mono font-bold text-sm text-amber-400 tracking-widest select-all"><?= htmlspecialchars($t['room_password'] ?? 'NO PASS') ?></span>
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-300 text-center mt-2">Open the game, go to Custom Rooms, and join using these credentials.</p>
                            </div>
                        <?php else: ?>
                            <div class="bg-slate-950/40 border border-slate-800/80 rounded-xl p-2.5 flex items-center gap-2.5 text-slate-400 text-xs">
                                <i class="fa-solid fa-lock text-slate-500"></i>
                                <span class="text-[11px]">Room ID & Password will be displayed here 15 mins before match time.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- TAB 2: COMPLETED TOURNAMENTS -->
    <?php if (empty($completed_tournaments)): ?>
        <div class="text-center py-12 px-4 bg-slate-900/50 border border-slate-800/80 rounded-2xl">
            <div class="w-12 h-12 mx-auto rounded-full bg-slate-800 flex items-center justify-center text-slate-500 mb-3 text-lg">
                <i class="fa-solid fa-medal"></i>
            </div>
            <h4 class="text-xs font-bold text-slate-300">No Match History</h4>
            <p class="text-[11px] text-slate-500 mt-1">Tournaments you participate in will appear here once finalized.</p>
        </div>
    <?php else: ?>
        <div class="space-y-3.5">
            <?php foreach ($completed_tournaments as $t): ?>
                <?php
                $isWinner = ($t['winner_id'] == $user_id);
                ?>
                <div class="bg-[#10172a] border border-slate-800/90 rounded-2xl overflow-hidden shadow-lg">
                    <!-- Top Strip -->
                    <div class="px-3.5 py-2 bg-slate-900/80 border-b border-slate-800/60 flex items-center justify-between text-[11px]">
                        <span class="font-bold text-slate-300"><?= htmlspecialchars($t['game_name']) ?></span>
                        <span class="text-slate-400"><?= date('M d, Y', strtotime($t['match_time'])) ?></span>
                    </div>

                    <div class="p-3.5">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <h4 class="font-bold text-sm text-white line-clamp-1"><?= htmlspecialchars($t['title']) ?></h4>
                            <?php if ($isWinner): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 whitespace-nowrap">
                                    <i class="fa-solid fa-crown mr-1"></i> Winner
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase bg-slate-800 text-slate-300 border border-slate-700 whitespace-nowrap">
                                    Participated
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Result Box -->
                        <?php if ($isWinner): ?>
                            <div class="bg-gradient-to-r from-emerald-950/50 to-slate-900 border border-emerald-800/40 rounded-xl p-2.5 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-trophy text-amber-400 text-base"></i>
                                    <div>
                                        <span class="text-[10px] text-emerald-300 block font-semibold uppercase">Prize Credited</span>
                                        <span class="text-xs font-black text-white"><?= format_inr($t['prize_pool']) ?></span>
                                    </div>
                                </div>
                                <span class="text-[10px] font-bold text-emerald-400 bg-emerald-500/10 px-2 py-1 rounded-md">1st Place</span>
                            </div>
                        <?php else: ?>
                            <div class="bg-slate-950/40 border border-slate-800/60 rounded-xl p-2.5 flex items-center justify-between text-xs text-slate-400">
                                <span>Champion: <strong class="text-slate-200"><?= htmlspecialchars($t['winner_name'] ?? 'Announced') ?></strong></span>
                                <span>Prize: <strong class="text-slate-200"><?= format_inr($t['prize_pool']) ?></strong></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php
require_once __DIR__ . '/common/bottom.php';
?>