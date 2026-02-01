            <!-- Spacer for mobile bottom nav -->
            <div class="md:hidden h-20 w-full"></div>
            </main>
        </div>
    </div>

    <!-- Mobile Bottom Navigation (Quick Links) -->
    <div id="mobile-bottom-nav" class="md:hidden fixed bottom-0 left-0 right-0 bg-white/95 dark:bg-gray-800/95 backdrop-blur-xl border-t border-gray-100 dark:border-gray-700 z-40 pb-safe shadow-[0_-8px_30px_rgb(0,0,0,0.04)] rounded-t-[2rem] transition-all duration-300">
        <div class="flex justify-around items-center h-20 px-4">
            <a href="<?php echo $prefix; ?>index.php" class="flex flex-col items-center justify-center w-full h-full <?php echo basename('index.php')===$curPageName?'text-brand-600':'text-gray-400'; ?> transition-all active:scale-90">
                <span class="text-2xl mb-1 <?php echo basename('index.php')===$curPageName?'scale-110 drop-shadow-md':'opacity-70'; ?>">🏠</span>
                <span class="text-[10px] font-black uppercase tracking-tighter">Home</span>
            </a>
            <a href="<?php echo $prefix; ?>pages/expenses.php" class="flex flex-col items-center justify-center w-full h-full <?php echo basename('expenses.php')===$curPageName?'text-brand-600':'text-gray-400'; ?> transition-all active:scale-90">
                <span class="text-2xl mb-1 <?php echo basename('expenses.php')===$curPageName?'scale-110 drop-shadow-md':'opacity-70'; ?>">💸</span>
                <span class="text-[10px] font-black uppercase tracking-tighter">Cash</span>
            </a>
            
            <!-- Central Action / Menu Trigger -->
            <div class="relative -top-3">
                <button onclick="toggleBottomSheet()" class="flex flex-col items-center justify-center w-16 h-16 bg-brand-500 rounded-3xl shadow-xl shadow-brand-500/40 text-white transform transition-all active:scale-95 active:rotate-12">
                    <span class="text-2xl">✨</span>
                </button>
            </div>

            <a href="<?php echo $prefix; ?>pages/income.php" class="flex flex-col items-center justify-center w-full h-full <?php echo basename('income.php')===$curPageName?'text-brand-600':'text-gray-400'; ?> transition-all active:scale-90">
                <span class="text-2xl mb-1 <?php echo basename('income.php')===$curPageName?'scale-110 drop-shadow-md':'opacity-70'; ?>">💰</span>
                <span class="text-[10px] font-black uppercase tracking-tighter">Bank</span>
            </a>
            <button onclick="toggleBottomSheet()" class="flex flex-col items-center justify-center w-full h-full text-gray-400 transition-all active:scale-90">
                <span class="text-2xl mb-1 opacity-70">☰</span>
                <span class="text-[10px] font-black uppercase tracking-tighter">More</span>
            </button>
        </div>
    </div>

    <script>
    // Swipe Up on Bottom Nav to open Menu
    const navBar = document.getElementById('mobile-bottom-nav');
    let navStartY = 0;

    navBar.addEventListener('touchstart', (e) => {
        navStartY = e.touches[0].clientY;
    }, {passive: true});

    navBar.addEventListener('touchend', (e) => {
        const navEndY = e.changedTouches[0].clientY;
        if (navStartY - navEndY > 60) { // Swiped up at least 60px
            toggleBottomSheet();
        }
    });

    // Auto-close on outside click (if header/overlay logic needs it)
    // Already handled by overlay in nav.php
    </script>
</body>
</html>
