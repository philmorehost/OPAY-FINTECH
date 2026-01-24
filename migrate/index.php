<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($settings['siteName'] ?? 'VTU-Fintech'); ?> | Best VTU Services</title>
    <meta name="description" content="<?php echo h($settings['siteDescription'] ?? ''); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { 'vtu-green': '#00c689', } } } }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .float-wa { position: fixed; width: 60px; height: 60px; bottom: 40px; right: 40px; background-color: #25d366; color: #FFF; rounded-full; display: flex; align-items: center; justify-content: center; font-size: 30px; box-shadow: 2px 2px 3px #999; z-index: 100; border-radius: 50%; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900">

    <nav class="flex items-center justify-between px-8 py-6 bg-white shadow-sm sticky top-0 z-50">
        <div class="flex items-center gap-2">
            <?php if (!empty($settings['logoPath'])): ?>
                <img src="<?php echo h($settings['logoPath']); ?>" alt="Logo" class="h-10">
            <?php else: ?>
                <div class="w-10 h-10 bg-vtu-green rounded-xl flex items-center justify-center text-white font-black text-xl">V</div>
            <?php endif; ?>
            <span class="text-xl font-black tracking-tight"><?php echo h($settings['siteName'] ?? 'VTU-Fintech'); ?></span>
        </div>
        <div class="hidden md:flex space-x-8 text-sm font-bold uppercase tracking-widest text-gray-500">
            <a href="#services" class="hover:text-vtu-green">Services</a>
            <a href="#features" class="hover:text-vtu-green">Features</a>
            <a href="#contact" class="hover:text-vtu-green">Contact</a>
        </div>
        <div>
            <a href="login" class="bg-vtu-green text-white px-6 py-3 rounded-2xl font-black text-xs uppercase tracking-widest shadow-lg hover:bg-emerald-500 transition-all">Get Started</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="max-w-6xl mx-auto px-8 py-24 flex flex-col md:flex-row items-center gap-16">
        <div class="md:w-1/2">
            <h1 class="text-5xl md:text-7xl font-black leading-tight mb-8">
                Instant <span class="text-vtu-green">VTU</span> & Payment Solutions.
            </h1>
            <p class="text-lg text-gray-500 mb-10 leading-relaxed font-medium">
                The most reliable platform for Airtime, Data, Cable TV, and Utility payments. Fast, secure, and always available.
            </p>
            <div class="flex flex-col sm:flex-row gap-4">
                <a href="login" class="bg-gray-900 text-white px-10 py-5 rounded-[24px] font-black text-sm uppercase tracking-widest shadow-2xl hover:scale-105 transition-all text-center">Login to Account</a>
                <a href="login" class="bg-white text-gray-900 border-2 border-gray-100 px-10 py-5 rounded-[24px] font-black text-sm uppercase tracking-widest hover:bg-gray-50 transition-all text-center">Create Account</a>
            </div>
        </div>
        <div class="md:w-1/2 relative">
            <div class="absolute -inset-4 bg-vtu-green/10 blur-3xl rounded-full"></div>
            <img src="https://img.freepik.com/free-vector/digital-lifestyle-concept-illustration_114360-7290.jpg" alt="VTU Illustration" class="relative rounded-[40px] shadow-2xl">
        </div>
    </header>

    <!-- Services -->
    <section id="services" class="bg-white py-24 border-y border-gray-100">
        <div class="max-w-6xl mx-auto px-8">
            <div class="text-center mb-16">
                <h2 class="text-[10px] font-black uppercase tracking-[0.3em] text-vtu-green mb-4">What we offer</h2>
                <h3 class="text-4xl font-black">Comprehensive VTU Services</h3>
            </div>
            <div class="grid md:grid-cols-4 gap-8">
                <?php
                $vtu_services = [
                    ['icon' => 'phone', 'label' => 'Airtime Topup', 'desc' => 'Instant airtime for all networks.'],
                    ['icon' => 'wifi', 'label' => 'Data Bundles', 'desc' => 'Cheap data for MTN, Airtel, Glo & 9mobile.'],
                    ['icon' => 'tv', 'label' => 'Cable TV', 'desc' => 'DStv, GOtv, and StarTimes subscriptions.'],
                    ['icon' => 'zap', 'label' => 'Electricity', 'desc' => 'Pay your prepaid & postpaid bills easily.'],
                ];
                foreach ($vtu_services as $s): ?>
                <div class="p-8 bg-gray-50 rounded-[32px] hover:shadow-xl transition-all border border-transparent hover:border-vtu-green/20">
                    <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center text-vtu-green shadow-sm mb-6"><i data-lucide="<?php echo $s['icon']; ?>" size="28"></i></div>
                    <h4 class="text-lg font-black mb-3"><?php echo h($s['label']); ?></h4>
                    <p class="text-sm text-gray-500 font-medium leading-relaxed"><?php echo h($s['desc']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section id="features" class="py-24">
        <div class="max-w-6xl mx-auto px-8">
            <div class="grid md:grid-cols-2 gap-16 items-center">
                <div>
                    <img src="https://img.freepik.com/free-vector/mobile-banking-concept-illustration_114360-1502.jpg" alt="Features" class="rounded-[40px] shadow-xl">
                </div>
                <div class="space-y-8">
                    <h3 class="text-4xl font-black leading-tight">Why Choose Our <span class="text-vtu-green">Fintech</span> Platform?</h3>
                    <div class="space-y-6">
                        <div class="flex gap-6">
                            <div class="shrink-0 w-12 h-12 bg-vtu-green/10 rounded-xl flex items-center justify-center text-vtu-green"><i data-lucide="shield-check"></i></div>
                            <div>
                                <h5 class="font-black text-lg mb-1">Ultra Secure</h5>
                                <p class="text-gray-500 text-sm font-medium">Your transactions and data are protected with industry-standard encryption.</p>
                            </div>
                        </div>
                        <div class="flex gap-6">
                            <div class="shrink-0 w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-blue-500"><i data-lucide="zap"></i></div>
                            <div>
                                <h5 class="font-black text-lg mb-1">Instant Delivery</h5>
                                <p class="text-gray-500 text-sm font-medium">No delays. Get your value immediately after a successful payment.</p>
                            </div>
                        </div>
                        <div class="flex gap-6">
                            <div class="shrink-0 w-12 h-12 bg-orange-50 rounded-xl flex items-center justify-center text-orange-500"><i data-lucide="headphones"></i></div>
                            <div>
                                <h5 class="font-black text-lg mb-1">24/7 Support</h5>
                                <p class="text-gray-500 text-sm font-medium">Our team is always available to help you with any issues or inquiries.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact -->
    <section id="contact" class="bg-gray-900 py-24 rounded-t-[60px]">
        <div class="max-w-4xl mx-auto px-8 text-center text-white">
            <h3 class="text-4xl font-black mb-8">Ready to get started?</h3>
            <p class="text-gray-400 mb-12 max-w-xl mx-auto font-medium">Join thousands of users who trust <?php echo h($settings['siteName'] ?? 'VTU-Fintech'); ?> for their daily payment needs.</p>
            <div class="flex flex-wrap justify-center gap-8 mb-16">
                <div class="flex items-center gap-3 text-sm font-bold uppercase tracking-widest"><i data-lucide="mail" class="text-vtu-green"></i> support@vtu-fintech.com</div>
                <div class="flex items-center gap-3 text-sm font-bold uppercase tracking-widest"><i data-lucide="phone" class="text-vtu-green"></i> <?php echo h($settings['adminWhatsapp'] ?? ''); ?></div>
            </div>
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-600">&copy; <?php echo date('Y'); ?> <?php echo h($settings['siteName'] ?? 'VTU-Fintech'); ?>. All Rights Reserved.</p>
        </div>
    </section>

    <!-- Floating WhatsApp -->
    <a href="https://wa.me/<?php echo h($settings['adminWhatsapp'] ?? ''); ?>" class="float-wa" target="_blank">
        <i data-lucide="message-circle" size="32"></i>
    </a>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
