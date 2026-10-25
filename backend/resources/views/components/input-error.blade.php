@props(['messages'])

@if ($messages)
    <div {{ $attributes->merge(['class' => 'space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <p class="form-error">{{ $message }}</p>
        @endforeach
    </div>
@endif
