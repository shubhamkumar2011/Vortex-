</main>

        <!-- Fixed Admin Bottom Navigation Bar -->
        <?php
        $admin_page = basename($_SERVER['PHP_SELF']);
        ?>
        <nav class="fixed bottom-0 left-0 right-0 z-50 flex justify-center pointer-events-none">
            <div class="w-full max-w-md bg-[#0b0f19]/95 backdrop-blur-md border-t border-slate-800 px-2 py-2 flex items-center justify-around pointer-events-auto shadow-2xl">
                <!-- Dashboard -->
                <a href="index.php" class="flex flex-col items-center justify-center flex-1 py-1 group transition-all <?= ($admin_page === 'index.php') ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' ?>">
                    <div class="relative">
                        <i class="fa-solid fa-chart-pie text-lg group-hover:scale-110 transition-transform"></i>
                        <?php if ($admin_page === 'index.php'): ?>
                            <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 bg-amber-400 rounded-full"></span>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] mt-1 tracking-tight">Overview</span>
                </a>

                <!-- Tournaments -->
                <a href="tournament.php" class="flex flex-col items-center justify-center flex-1 py-1 group transition-all <?= in_array($admin_page, ['tournament.php', 'manage_tournament.php']) ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' ?>">
                    <div class="relative">
                        <i class="fa-solid fa-trophy text-lg group-hover:scale-110 transition-transform"></i>
                        <?php if (in_array($admin_page, ['tournament.php', 'manage_tournament.php'])): ?>
                            <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 bg-amber-400 rounded-full"></span>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] mt-1 tracking-tight">Matches</span>
                </a>

                <!-- Users -->
                <a href="user.php" class="flex flex-col items-center justify-center flex-1 py-1 group transition-all <?= ($admin_page === 'user.php') ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' ?>">
                    <div class="relative">
                        <i class="fa-solid fa-users text-lg group-hover:scale-110 transition-transform"></i>
                        <?php if ($admin_page === 'user.php'): ?>
                            <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 bg-amber-400 rounded-full"></span>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] mt-1 tracking-tight">Users</span>
                </a>

                <!-- Settings -->
                <a href="setting.php" class="flex flex-col items-center justify-center flex-1 py-1 group transition-all <?= ($admin_page === 'setting.php') ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' ?>">
                    <div class="relative">
                        <i class="fa-solid fa-gear text-lg group-hover:scale-110 transition-transform"></i>
                        <?php if ($admin_page === 'setting.php'): ?>
                            <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 bg-amber-400 rounded-full"></span>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] mt-1 tracking-tight">Settings</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Security & App UX Script: Disable Right-click, Text Selection, and Zoom -->
    <script>
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });

        document.addEventListener('selectstart', function(e) {
            if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'SELECT') {
                e.preventDefault();
                return false;
            }
        });

        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === '+' || e.key === '-' || e.key === '=' || e.key === '0')) {
                e.preventDefault();
            }
        });

        document.addEventListener('wheel', function(e) {
            if (e.ctrlKey) {
                e.preventDefault();
            }
        }, { passive: false });
    </script>
</body>
</html>