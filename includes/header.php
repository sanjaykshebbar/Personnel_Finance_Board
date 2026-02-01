<?php
// Calculate base path for assets
$basePath = (basename(dirname($_SERVER['PHP_SELF'])) === 'pages') ? '../' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script>
        // Block-level Dark Mode Initialization
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Finance Board</title>
    <link rel="icon" type="image/png" href="<?php echo $basePath; ?>assets/favicon.png">
    <link rel="manifest" href="<?php echo $basePath; ?>manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <!-- Tailwind CSS (Play CDN for instant local usage without build step) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            900: '#0c4a6e',
                        }
                    }
                }
            }
        }
        
    </script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/tailwind.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('<?php echo $basePath; ?>sw.js')
                    .then(reg => console.log('SW registered'))
                    .catch(err => console.log('SW failed', err));
            });
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-800 antialiased">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include __DIR__ . '/nav.php'; ?>
        
        <!-- Main Content -->
        <div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden">
            <!-- Header -->
            <header class="bg-white dark:bg-gray-900 shadow border-b border-gray-200 dark:border-gray-800 transition-colors duration-300">
                <div class="px-4 py-4 sm:px-6 lg:px-8 flex justify-between items-center">
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        <?php echo isset($pageTitle) ? $pageTitle : 'Dashboard'; ?>
                    </h1>
                    <div class="flex items-center space-x-4">
                        <button id="theme-toggle" class="p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg text-sm transition-all">
                            <span id="theme-toggle-dark-icon" class="hidden">🌙</span>
                            <span id="theme-toggle-light-icon" class="hidden">☀️</span>
                        </button>
                        <span class="text-sm text-gray-500 dark:text-gray-400 hidden sm:inline"><?php echo date('F j, Y'); ?></span>
                        <div class="h-8 w-8 rounded-full bg-brand-500 flex items-center justify-center text-white font-bold shadow-sm">
                            <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                        </div>
                    </div>
                </div>
            </header>
            
            <main class="w-full flex-grow p-6 dark:bg-gray-900 transition-colors duration-300">
            
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div id="flash-message" class="mb-6 p-4 bg-brand-500 text-white rounded-2xl shadow-lg shadow-brand-500/20 flex justify-between items-center animate-scale-in">
                    <div class="flex items-center space-x-3">
                        <span class="text-xl">✨</span>
                        <span class="font-bold text-sm tracking-tight"><?php echo $_SESSION['flash_message']; ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-white/60 hover:text-white transition-colors">✕</button>
                </div>
                <?php unset($_SESSION['flash_message']); ?>
                <script>
                    setTimeout(() => {
                        const msg = document.getElementById('flash-message');
                        if (msg) {
                            msg.style.opacity = '0';
                            msg.style.transform = 'translateY(-10px)';
                            msg.style.transition = 'all 0.5s ease-out';
                            setTimeout(() => msg.remove(), 500);
                        }
                    }, 4000);
                </script>
            <?php endif; ?>
            
            <script>
                const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
                const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');

                // Toggle icon visibility based on current theme
                if (document.documentElement.classList.contains('dark')) {
                    themeToggleLightIcon.classList.remove('hidden');
                } else {
                    themeToggleDarkIcon.classList.remove('hidden');
                }

                const themeToggleBtn = document.getElementById('theme-toggle');

                themeToggleBtn.addEventListener('click', function() {
                    // toggle icons
                    themeToggleDarkIcon.classList.toggle('hidden');
                    themeToggleLightIcon.classList.toggle('hidden');

                    // if set via local storage previously
                    if (localStorage.getItem('color-theme')) {
                        if (localStorage.getItem('color-theme') === 'light') {
                            document.documentElement.classList.add('dark');
                            localStorage.setItem('color-theme', 'dark');
                        } else {
                            document.documentElement.classList.remove('dark');
                            localStorage.setItem('color-theme', 'light');
                        }
                    // if NOT set via local storage previously
                    } else {
                        if (document.documentElement.classList.contains('dark')) {
                            document.documentElement.classList.remove('dark');
                            localStorage.setItem('color-theme', 'light');
                        } else {
                            document.documentElement.classList.add('dark');
                            localStorage.setItem('color-theme', 'dark');
                        }
                    }
                });
            </script>
