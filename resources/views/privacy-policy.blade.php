<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Privacy Policy - {{ config('app.name', 'TurfBooking') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased font-sans bg-gray-50 dark:bg-gray-950 text-gray-800 dark:text-gray-200 min-h-screen flex flex-col justify-between selection:bg-emerald-500 selection:text-white">
        
        <!-- Header -->
        <header class="sticky top-0 z-50 bg-white/80 dark:bg-gray-950/80 backdrop-blur-md border-b border-gray-100 dark:border-gray-900 px-6 py-4">
            <div class="max-w-4xl mx-auto flex items-center justify-between">
                <a href="{{ url('/') }}" class="flex items-center gap-2">
                    <x-application-logo class="h-8 w-auto fill-current text-emerald-500" />
                    <span class="font-bold text-lg text-gray-900 dark:text-white">{{ config('app.name', 'TurfBooking') }}</span>
                </a>
                <a href="{{ url('/') }}" class="inline-flex items-center justify-center px-4 py-2 text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300 hover:text-emerald-500 dark:hover:text-emerald-400 transition">
                    &larr; Back to Home
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow py-12 px-6">
            <article class="max-w-4xl mx-auto bg-white dark:bg-gray-900/40 border border-gray-100 dark:border-gray-900 rounded-3xl p-8 sm:p-12 shadow-sm">
                
                <header class="border-b border-gray-100 dark:border-gray-900 pb-8 mb-8">
                    <h1 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">Privacy Policy</h1>
                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-2">Last Updated: July 16, 2026</p>
                </header>

                <div class="space-y-8 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                    
                    <section class="space-y-3">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">1. Introduction</h2>
                        <p>
                            Welcome to {{ config('app.name', 'TurfBooking') }}. We value your privacy and are committed to protecting your personal data. This privacy policy explains how we collect, use, disclose, and safeguard your information when you use our website and mobile applications (available on the Google Play Store and iOS App Store).
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">2. Information We Collect</h2>
                        <p>We may collect personal information that you voluntarily provide to us when you register on our platform, book a turf, or contact support. This information includes:</p>
                        <ul class="list-disc list-inside space-y-1.5 ps-4 text-xs">
                            <li><strong>Account Data:</strong> Name, email address, mobile number, and password.</li>
                            <li><strong>Transactional Data:</strong> Details of the bookings, dates, times, turf selections, and payment history.</li>
                            <li><strong>Device & Usage Information:</strong> IP address, device type, OS, and log data.</li>
                        </ul>
                    </section>

                    <section class="space-y-3 p-6 bg-emerald-50/50 dark:bg-emerald-950/10 rounded-2xl border border-emerald-100/50 dark:border-emerald-950/30">
                        <h2 class="text-lg font-bold text-emerald-800 dark:text-emerald-400">3. Location Data Consent (App Store & Play Store Compliance)</h2>
                        <p>
                            Our mobile applications may request permission to access your device's precise location (GPS and network-based location) in the foreground. 
                        </p>
                        <p class="font-medium text-gray-900 dark:text-white">Why we collect location data:</p>
                        <p class="text-xs">
                            We use location data to show you nearby sports turfs, calculate distances to the turfs, and autofill location parameters for finding venues. We do NOT collect background location data without explicit notification, and we do NOT track your device when the application is closed.
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            You can choose to allow or deny location access through your device's operating system settings. If you choose to disable location access, some features (such as automatically searching for turfs nearest to you) may not function correctly.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">4. How We Use Your Information</h2>
                        <p>We use the collected information for the following purposes:</p>
                        <ul class="list-disc list-inside space-y-1.5 ps-4 text-xs">
                            <li>To create and manage your user account.</li>
                            <li>To process and confirm bookings at your chosen turf.</li>
                            <li>To send notifications, booking confirmations, and transactional updates.</li>
                            <li>To maintain, analyze, and optimize our website and mobile application experience.</li>
                            <li>To prevent fraud, secure our services, and comply with legal requirements.</li>
                        </ul>
                    </section>

                    <section class="space-y-3">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">5. Account Deletion & Data Retention</h2>
                        <p>
                            We retain your personal data only as long as necessary to provide you with our booking services. If you wish to delete your account and remove all your data permanently:
                        </p>
                        <p class="text-xs">
                            You can initiate account deletion at any time by going to your <strong>Profile Settings</strong> page inside the application and clicking "Delete Account", or by contacting our support team at <a href="mailto:support@turfbooking.com" class="text-emerald-500 hover:underline">support@turfbooking.com</a>. Upon receiving your request, we will delete your account and associated personal data from our active databases within 30 days, except where data must be retained for legal, financial, or auditing compliance.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">6. Security of Your Data</h2>
                        <p>
                            We implement industry-standard administrative, technical, and physical security measures to protect your personal information. However, please be aware that no security measures are perfect or impenetrable, and no method of data transmission can be guaranteed against any interception or other type of misuse.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">7. Changes to This Privacy Policy</h2>
                        <p>
                            We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last Updated" date at the top. We encourage you to review this policy periodically.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">8. Contact Us</h2>
                        <p>If you have any questions or feedback regarding this Privacy Policy, please contact us at:</p>
                        <p class="text-xs mt-1">
                            Email: <a href="mailto:support@turfbooking.com" class="text-emerald-500 hover:underline">support@turfbooking.com</a><br>
                            Address: Mumbai, India
                        </p>
                    </section>

                </div>
            </article>
        </main>

        <!-- Footer -->
        <footer class="bg-white dark:bg-gray-950 border-t border-gray-100 dark:border-gray-900 py-8 px-6 text-center text-xs text-gray-400 dark:text-gray-500">
            <div class="max-w-4xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
                <p>&copy; {{ date('Y') }} {{ config('app.name', 'TurfBooking') }}. All rights reserved.</p>
                <div class="flex items-center gap-4">
                    <a href="{{ url('/') }}" class="hover:text-emerald-500 transition">Home</a>
                    <a href="{{ route('privacy-policy') }}" class="hover:text-emerald-500 font-medium transition">Privacy Policy</a>
                </div>
            </div>
        </footer>

    </body>
</html>
