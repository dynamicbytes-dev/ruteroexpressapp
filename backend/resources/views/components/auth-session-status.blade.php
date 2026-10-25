@props(['status'])

@if ($status)
    <div {{ $attributes->merge([
        'class' => 'flex items-start gap-2 rounded-xl bg-accent-50 px-3.5 py-3 text-sm font-medium text-accent-800',
    ]) }}>
        <x-icon name="shield" class="mt-0.5 h-4 w-4 shrink-0" />
        <span>{{ $status }}</span>
    </div>
@endif
