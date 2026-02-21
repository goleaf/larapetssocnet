<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Restricted</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
    <main class="mx-auto flex min-h-screen max-w-2xl items-center justify-center px-6">
        <div class="rounded-xl bg-white p-8 shadow-sm dark:bg-gray-800">
            <h1 class="text-2xl font-semibold">Account Restricted</h1>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                This account is currently restricted. If this seems incorrect, please contact support.
            </p>
            <div class="mt-6">
                <a href="{{ route('login') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Back to Login
                </a>
            </div>
        </div>
    </main>
</body>
</html>
