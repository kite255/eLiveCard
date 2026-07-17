@php
    use Illuminate\Support\Facades\Storage;

    /** @var \App\Models\InviteeUpload $record */
    $record = $getRecord();

    $isPhoto = $record?->isPhoto() ?? false;
    $hasStoredFile = $isPhoto && ($record?->hasStoredFile() ?? false);

    $photoUrl = $hasStoredFile
        ? Storage::disk('public')->url($record->file_path)
        : null;
@endphp

<div class="flex items-center justify-center">
    @if ($photoUrl)
        <a
            href="{{ $photoUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="group block"
            title="Open submitted photo"
        >
            <img
                src="{{ $photoUrl }}"
                alt="{{ filled($record->message) ? $record->message : 'Submitted event photo' }}"
                class="h-12 w-12 rounded-xl border border-slate-200 object-cover shadow-sm transition group-hover:scale-105 group-hover:shadow-md"
                loading="lazy"
                decoding="async"
            >
        </a>
    @else
        <div
            class="flex h-12 w-12 items-center justify-center rounded-xl border border-orange-200 bg-orange-50 text-[#FD9618]"
            title="Wish submission"
        >
            <x-heroicon-o-chat-bubble-left-ellipsis class="h-5 w-5" />
        </div>
    @endif
</div>