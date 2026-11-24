<div class="space-y-4">
    <p class="text-sm text-gray-700 dark:text-gray-200">
        <strong>{{ number_format($missingCount) }}</strong>
        {{ \Illuminate\Support\Str::plural('invitee', $missingCount) }} without a matching invitation SMS log.
        This does not prove an SMS was not sent; the record may be missing or not linked correctly.
    </p>

    @if ($missingCount > 0)
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 text-left text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    <tr>
                        <th scope="col" class="px-3 py-2">Invitee ID</th>
                        <th scope="col" class="px-3 py-2">Name</th>
                        <th scope="col" class="px-3 py-2">Phone</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($missingInvitees as $invitee)
                        <tr>
                            <td class="px-3 py-2">{{ $invitee->id }}</td>
                            <td class="px-3 py-2">{{ $invitee->name }}</td>
                            <td class="px-3 py-2">{{ $invitee->phone ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($missingCount > 20)
            <p class="text-xs text-gray-500">Showing the first 20 invitees, sorted by name.</p>
        @endif
        <p class="text-xs text-gray-500">Check provider logs and message references before attempting any resend.</p>
    @else
        <p class="text-sm text-green-700 dark:text-green-400">Every invitee has at least one matching invitation SMS log.</p>
    @endif
</div>
