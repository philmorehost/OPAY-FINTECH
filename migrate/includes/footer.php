            </div>
        </main>
    </div>

    <!-- Bottom Navigation (Mobile) -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 px-6 py-3 z-50 flex justify-between items-center pb-8 shadow-[0_-10px_40px_rgba(0,0,0,0.05)]">
        <?php if (isAdmin()): ?>
            <a href="/admin/transactions" class="flex flex-col items-center gap-1">
                <i data-lucide="file-text" class="w-5 h-5 text-gray-400"></i>
                <span class="text-[8px] font-black uppercase text-gray-400">All TX</span>
            </a>
            <a href="/admin/deposits" class="flex flex-col items-center gap-1">
                <i data-lucide="wallet" class="w-5 h-5 text-gray-400"></i>
                <span class="text-[8px] font-black uppercase text-gray-400">Deposits</span>
            </a>
            <a href="/admin/index" class="flex flex-col items-center justify-center w-12 h-12 bg-billpay-green rounded-2xl shadow-lg -mt-8 border-4 border-gray-50">
                <i data-lucide="home" class="w-5 h-5 text-white"></i>
            </a>
            <a href="/admin/support" class="flex flex-col items-center gap-1">
                <i data-lucide="message-square" class="w-5 h-5 text-gray-400"></i>
                <span class="text-[8px] font-black uppercase text-gray-400">Support</span>
            </a>
            <a href="/admin/users" class="flex flex-col items-center gap-1">
                <i data-lucide="users" class="w-5 h-5 text-gray-400"></i>
                <span class="text-[8px] font-black uppercase text-gray-400">Users</span>
            </a>
        <?php else: ?>
            <button onclick="toggleMobileMenu()" class="flex flex-col items-center gap-1">
                <i data-lucide="menu" class="w-5 h-5 text-gray-400"></i>
                <span class="text-[8px] font-black uppercase text-gray-400">Menu</span>
            </button>
            <a href="/services" class="flex flex-col items-center gap-1">
                <i data-lucide="grid" class="w-5 h-5 text-gray-400"></i>
                <span class="text-[8px] font-black uppercase text-gray-400">Services</span>
            </a>
            <a href="/dashboard" class="flex flex-col items-center justify-center w-12 h-12 bg-billpay-green rounded-2xl shadow-lg -mt-8 border-4 border-gray-50">
                <i data-lucide="home" class="w-5 h-5 text-white"></i>
            </a>
            <a href="/transactions" class="flex flex-col items-center gap-1">
                <i data-lucide="arrow-right-left" class="w-5 h-5 text-gray-400"></i>
                <span class="text-[8px] font-black uppercase text-gray-400">History</span>
            </a>
            <a href="/profile" class="flex flex-col items-center gap-1">
                <i data-lucide="user" class="w-5 h-5 text-gray-400"></i>
                <span class="text-[8px] font-black uppercase text-gray-400">Me</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- Mobile Menu Overlay -->
    <div id="mobileMenuOverlay" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[60] hidden flex flex-col justify-end">
        <div class="bg-white rounded-t-[40px] p-10 space-y-8 animate-slide-up relative">
            <button onclick="toggleMobileMenu()" class="absolute top-6 right-6 w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-400">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>

            <div class="grid grid-cols-3 gap-8">
                <a href="/dashboard" class="flex flex-col items-center gap-3">
                    <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-500"><i data-lucide="layout-dashboard" class="w-6 h-6"></i></div>
                    <span class="text-[10px] font-black uppercase text-gray-400">Dashboard</span>
                </a>
                <a href="/services" class="flex flex-col items-center gap-3">
                    <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-500"><i data-lucide="grid" class="w-6 h-6"></i></div>
                    <span class="text-[10px] font-black uppercase text-gray-400">Services</span>
                </a>
                <a href="/rewards" class="flex flex-col items-center gap-3">
                    <div class="w-14 h-14 bg-yellow-50 rounded-2xl flex items-center justify-center text-yellow-500"><i data-lucide="gift" class="w-6 h-6"></i></div>
                    <span class="text-[10px] font-black uppercase text-gray-400">Rewards</span>
                </a>
                <a href="/referrals" class="flex flex-col items-center gap-3">
                    <div class="w-14 h-14 bg-purple-50 rounded-2xl flex items-center justify-center text-purple-500"><i data-lucide="users" class="w-6 h-6"></i></div>
                    <span class="text-[10px] font-black uppercase text-gray-400">Refer & Earn</span>
                </a>
                <a href="/support" class="flex flex-col items-center gap-3">
                    <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-500"><i data-lucide="message-square" class="w-6 h-6"></i></div>
                    <span class="text-[10px] font-black uppercase text-gray-400">Support</span>
                </a>
                <a href="/profile" class="flex flex-col items-center gap-3">
                    <div class="w-14 h-14 bg-pink-50 rounded-2xl flex items-center justify-center text-pink-500"><i data-lucide="settings" class="w-6 h-6"></i></div>
                    <span class="text-[10px] font-black uppercase text-gray-400">Settings</span>
                </a>
            </div>

            <div class="pt-6 border-t border-gray-100">
                <a href="/logout" class="flex items-center justify-center gap-3 w-full py-5 bg-red-50 text-red-500 rounded-3xl font-black text-xs uppercase tracking-widest">
                    <i data-lucide="log-out" class="w-5 h-5"></i> Sign Out
                </a>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenuOverlay');
            menu.classList.toggle('hidden');
        }
    </script>
</body>
</html>
