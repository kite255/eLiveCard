<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invalid QR Code - eLive Card</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-red-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-2xl shadow p-6 text-center">
        <div class="text-5xl mb-4">⚠️</div>
        <h1 class="text-2xl font-bold text-red-700">Invalid QR Code</h1>
        <p class="mt-3 text-gray-600">
            {{ $message ?? 'This QR code is not valid.' }}
        </p>
    </div>
</body>
</html>