<?php
require_once __DIR__ . '/../../common/config.php';

// Check Admin Login
check_admin_login();
$admin_username = $_SESSION['admin_username'] ?? 'Administrator';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Portal - <?= APP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        admin: {
                            primary: '#6366f1',
                            accent: '#f59e0b',
                            dark: '#090d16',
                            card: '#111827',
                            border: '#1f2937'
                        }
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body, * {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
        }
        input, select, textarea {
            -webkit-user-select: text;
            user-select: text;
        }
        ::-webkit-scrollbar {
            width: 4px;
        }
        ::-webkit-scrollbar-track {
            background: #090d16;
        }
        ::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen pb-20 select-none antialiased flex flex-col items-center justify-start overflow-x-hidden">

    <!-- Mobile Frame Container (Max 480px on desktop) -->
    <div class="w-full max-w-md min-h-screen bg-[#0b0f19] border-x border-slate-800 shadow-2xl relative flex flex-col">

        <!-- Top Header Navigation -->
        <header class="sticky top-0 z-40 bg-[#0b0f19]/95 backdrop-blur-md border-b border-slate-800 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <a href="index.php" class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-amber-500 to-rose-500 flex items-center justify-center shadow-md shadow-amber-500/20">
                        <i class="fa-solid fa-shield-halved text-white text-xs"></i>
                    </div>
                    <div>
                        <span class="font-black text-sm tracking-wide text-white uppercase">VORTEX <span class="text-amber-400">ADMIN</span></span>
                        <span class="text-[9px] block text-slate-400">Console v2.4</span>
                    </div>
                </a>
            </div>

            <!-- Header Quick links: Switch to User App & Logout -->
            <div class="flex items-center gap-2">
                <a href="../index.php" title="Open User App" class="text-xs bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-300 px-2 py-1.5 rounded-lg transition-colors flex items-center gap-1">
                    <i class="fa-solid fa-mobile-screen text-[10px] text-cyan-400"></i>
                    <span class="text-[10px]">App</span>
                </a>
                <a href="index.php?action=logout" onclick="return confirm('Sign out of admin?');" title="Logout" class="text-xs bg-rose-950/60 border border-rose-800/40 text-rose-300 hover:bg-rose-900/60 p-1.5 rounded-lg transition-colors">
                    <i class="fa-solid fa-power-off text-xs"></i>
                </a>
            </div>
        </header>

        <!-- Flash Message Banner -->
        <?php $flash = get_flash(); if ($flash): ?>
            <div class="px-4 pt-3">
                <div class="p-3 rounded-xl border text-xs font-medium flex items-start gap-2.5 shadow-lg <?= $flash['type'] === 'success' ? 'bg-emerald-950/80 border-emerald-600/50 text-emerald-200' : 'bg-rose-950/80 border-rose-600/50 text-rose-200' ?>">
                    <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?> mt-0.5 text-sm"></i>
                    <div class="flex-1"><?= htmlspecialchars($flash['message']) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main Admin Body -->
        <main class="flex-1 px-4 py-3">