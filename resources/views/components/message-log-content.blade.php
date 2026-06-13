<div class="space-y-5">
    <div>
        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
            Recipient
        </div>

        <div class="mt-1 text-sm text-gray-950 dark:text-white">
            {{ $record->invitee?->name ?: 'Unknown invitee' }}
            @if ($record->phone)
                — {{ $record->phone }}
            @endif
        </div>
    </div>

    <div>
        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
            Message
        </div>

        <div class="mt-2 whitespace-pre-wrap rounded-xl bg-gray-50 p-4 text-sm text-gray-950 dark:bg-white/5 dark:text-white">
            {{ $record->message ?: 'No message content recorded.' }}
        </div>
    </div>

    @if ($record->error_message)
        <div>
            <div class="text-sm font-medium text-danger-600">
                Error
            </div>

            <div class="mt-2 whitespace-pre-wrap rounded-xl bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-950/30 dark:text-danger-300">
                {{ $record->error_message }}
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Provider
            </div>

            <div class="mt-1 text-sm text-gray-950 dark:text-white">
                {{ $record->provider ?: '-' }}
            </div>
        </div>

        <div>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Status
            </div>

            <div class="mt-1 text-sm text-gray-950 dark:text-white">
                {{ str($record->status ?: 'unknown')->replace('_', ' ')->title() }}
            </div>
        </div>
    </div>
</div>