<?php
// Simple logic to handle form submission
$message_sent = false;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // In a real app, you'd save to a DB or send an email here
        $message_sent = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LaunchPad | PHP Landing Page</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

    <nav class="flex items-center justify-between px-8 py-6 bg-white shadow-sm">
        <div class="text-2xl font-bold text-blue-600">LaunchPad</div>
        <div class="space-x-6">
            <a href="#features" class="hover:text-blue-600">Features</a>
            <a href="#" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700">Get Started</a>
        </div>
    </nav>

    <header class="max-w-6xl mx-auto px-8 py-20 flex flex-col md:flex-row items-center">
        <div class="md:w-1/2 mb-10 md:mb-0">
            <h1 class="text-5xl md:text-6xl font-extrabold leading-tight mb-6">
                Build your next idea <span class="text-blue-600">faster.</span>
            </h1>
            <p class="text-lg text-gray-600 mb-8">
                A lightweight, PHP-powered landing page template to help you validate your product in minutes.
            </p>
            
            <?php if ($message_sent): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    Thanks! We'll be in touch soon.
                </div>
            <?php else: ?>
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" class="flex flex-col sm:flex-row gap-2">
                    <input type="email" name="email" placeholder="Enter your email" required
                           class="flex-1 px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition">
                        Join Waitlist
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <div class="md:w-1/2 flex justify-center">
            <img src="https://via.placeholder.com/500x400" alt="Product Mockup" class="rounded-2xl shadow-2xl">
        </div>
    </header>

    <section id="features" class="bg-white py-20">
        <div class="max-w-6xl mx-auto px-8">
            <h2 class="text-3xl font-bold text-center mb-12">Why Choose Us?</h2>
            <div class="grid md:grid-cols-3 gap-10">
                <div class="p-6 bg-gray-50 rounded-xl">
                    <div class="text-blue-600 text-2xl mb-4">⚡</div>
                    <h3 class="text-xl font-bold mb-2">Lightning Fast</h3>
                    <p class="text-gray-600">Optimized for speed with minimal server-side processing.</p>
                </div>
                <div class="p-6 bg-gray-50 rounded-xl">
                    <div class="text-blue-600 text-2xl mb-4">🛡️</div>
                    <h3 class="text-xl font-bold mb-2">Secure</h3>
                    <p class="text-gray-600">Built-in PHP filtering to keep your lead data safe.</p>
                </div>
                <div class="p-6 bg-gray-50 rounded-xl">
                    <div class="text-blue-600 text-2xl mb-4">📱</div>
                    <h3 class="text-xl font-bold mb-2">Responsive</h3>
                    <p class="text-gray-600">Looks great on mobile, tablet, and desktop screens.</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="py-10 text-center text-gray-500 border-t">
        <p>&copy; <?php echo date("Y"); ?> LaunchPad Inc. All rights reserved.</p>
    </footer>

</body>
</html>