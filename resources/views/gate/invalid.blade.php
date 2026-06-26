<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invalid QR Code - eLive Card</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-[#F8FAFC] text-[#111827]">
    <main class="flex min-h-screen items-center justify-center px-4 py-8">
        <div class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-xl">
            <div class="bg-red-600 px-6 py-7 text-center text-white">
                <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-white text-5xl text-red-600">
                    !
                </div>

                <h1 class="text-2xl font-bold">
                    Invalid QR Code
                </h1>

                <p class="mt-2 text-sm text-red-100">
                    This invitation card could not be verified.
                </p>
            </div>

            <div class="space-y-5 p-6 text-center">
                <div class="rounded-2xl bg-red-50 p-4">
                    <p class="text-sm font-medium text-red-700">
                        {{ $message ?? 'This QR code is not valid or does not belong to this event.' }}
                    </p>
                </div>

                <div class="rounded-2xl bg-[#F8FAFC] p-4 text-left">
                    <p class="mb-2 text-sm font-bold text-[#213B73]">
                        What to do next
                    </p>

                    <ul class="space-y-2 text-sm text-gray-600">
                        <li>• Confirm the guest is using the correct card.</li>
                        <li>• Check if the card belongs to this event.</li>
                        <li>• Try manual search using serial number, phone, or name.</li>
                    </ul>
                </div>

                <div class="grid grid-cols-1 gap-3">
                    <a
                        href="javascript:history.back()"
                        class="w-full rounded-2xl bg-[#213B73] px-5 py-3 font-bold text-white shadow hover:opacity-90"
                    >
                        Back to Scanner
                    </a>

                    <button
                        type="button"
                        onclick="window.location.reload()"
                        class="w-full rounded-2xl border border-gray-300 bg-white px-5 py-3 font-bold text-[#111827] hover:bg-gray-50"
                    >
                        Try Again
                    </button>
                </div>

                <p class="text-xs text-gray-400">
                    eLive Card Gate Verification
                </p>
            </div>
        </div>
    </main>
</body>
</html>