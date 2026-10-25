@props(['compact' => false])

{{-- Compatibilidad: Breeze referenciaba este componente. Ahora apunta al isotipo real. --}}
<x-logo :compact="$compact" {{ $attributes }} />
