<x-app-layout>
    <x-slot name="title">Tarjetas de Publicidad y Banners</x-slot>
    <x-slot name="subtitle">Contenido promocional dinámico administrado para las aplicaciones móviles</x-slot>

    @if (session('status'))
        <div class="mb-5 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 p-4 text-emerald-800 text-sm font-medium">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('panel.banners.create') }}" class="btn-primary inline-flex items-center gap-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Nueva tarjeta publicitaria
            </a>
        </div>

        <form method="GET" action="{{ route('panel.banners.index') }}" class="flex flex-wrap items-center gap-3">
            <input type="text" name="q" value="{{ request('q') }}"
                   placeholder="Buscar por título o tag"
                   class="form-input w-48 text-sm">
            <select name="role" class="form-input text-sm">
                <option value="">Todos los roles</option>
                <option value="passenger" {{ request('role') == 'passenger' ? 'selected' : '' }}>Pasajeros</option>
                <option value="driver" {{ request('role') == 'driver' ? 'selected' : '' }}>Conductores</option>
                <option value="all" {{ request('role') == 'all' ? 'selected' : '' }}>Todos</option>
            </select>
            <select name="activo" class="form-input text-sm">
                <option value="">Estado</option>
                <option value="1" {{ request('activo') == '1' ? 'selected' : '' }}>Activos</option>
                <option value="0" {{ request('activo') == '0' ? 'selected' : '' }}>Pausados</option>
            </select>
            <button type="submit" class="btn-outline text-sm">
                <x-icon name="search" class="h-4 w-4" />
            </button>
        </form>
    </div>

    @if ($banners->isEmpty())
        <div class="card p-12 text-center text-ink-500">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-ink-100 text-ink-400">
                <x-icon name="image" class="h-7 w-7" />
            </div>
            <h3 class="font-display font-semibold text-ink-800 text-base">No hay tarjetas de publicidad</h3>
            <p class="mt-1 text-xs text-ink-400 max-w-sm mx-auto">
                Crea banners promocionales con imágenes, colores de marca y enlaces para destacar ofertas en la app de pasajeros.
            </p>
            <div class="mt-5">
                <a href="{{ route('panel.banners.create') }}" class="btn-primary inline-flex items-center gap-2 text-sm">
                    Crear primer banner
                </a>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($banners as $banner)
                <div class="card overflow-hidden flex flex-col justify-between border border-ink-100 shadow-card transition-all hover:shadow-lg">
                    {{-- Vista previa visual de la tarjeta tal como la verá la app móvil --}}
                    <div class="p-5 text-white relative overflow-hidden rounded-t-3xl min-h-[140px] flex flex-col justify-between"
                         style="background: {{ $banner->background_color_end ? 'linear-gradient(135deg, '.$banner->background_color.', '.$banner->background_color_end.')' : $banner->background_color }}; color: {{ $banner->text_color }};">
                        
                        {{-- Marca de agua o imagen de fondo --}}
                        @if ($banner->display_image_url)
                            <img src="{{ $banner->display_image_url }}" alt="{{ $banner->title }}"
                                 class="absolute right-0 bottom-0 h-28 w-28 object-contain object-bottom opacity-85 pointer-events-none p-1">
                        @endif

                        <div class="relative z-10">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                @if ($banner->tag)
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase bg-white/20 backdrop-blur-sm">
                                        {{ $banner->tag }}
                                    </span>
                                @else
                                    <span></span>
                                @endif

                                <span class="text-[11px] font-medium opacity-80">
                                    Prioridad: {{ $banner->priority }}
                                </span>
                            </div>

                            <h4 class="font-display font-bold text-base leading-snug line-clamp-2 max-w-[70%]">
                                {{ $banner->title }}
                            </h4>

                            @if ($banner->subtitle)
                                <p class="text-xs opacity-90 mt-1 line-clamp-2 max-w-[70%]">
                                    {{ $banner->subtitle }}
                                </p>
                            @endif
                        </div>

                        <div class="relative z-10 mt-3 pt-2 flex items-center justify-between">
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-lg bg-black/20">
                                {{ $banner->action_text ?: 'Ver más' }} →
                            </span>

                            <span class="text-[10px] px-2 py-0.5 rounded-md font-semibold {{ $banner->is_active ? 'bg-emerald-400 text-emerald-950' : 'bg-rose-400 text-rose-950' }}">
                                {{ $banner->is_active ? 'Activo' : 'Pausado' }}
                            </span>
                        </div>
                    </div>

                    {{-- Detalles de control y acciones para el auditor --}}
                    <div class="p-4 bg-white flex-1 flex flex-col justify-between text-xs text-ink-600 gap-3 border-t border-ink-100">
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-ink-400">Público:</span>
                                <span class="font-semibold text-ink-800">
                                    {{ match($banner->target_role) { 'passenger' => 'Pasajeros', 'driver' => 'Conductores', default => 'Todos' } }}
                                </span>
                            </div>
                            @if ($banner->action_url)
                                <div class="flex items-center justify-between truncate">
                                    <span class="font-medium text-ink-400">Enlace:</span>
                                    <span class="truncate max-w-[170px] text-brand-700 font-mono text-[11px]" title="{{ $banner->action_url }}">
                                        {{ $banner->action_url }}
                                    </span>
                                </div>
                            @endif
                            @if ($banner->city)
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-ink-400">Ciudad:</span>
                                    <span class="font-semibold text-ink-800">{{ $banner->city->name }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center justify-between pt-3 border-t border-ink-100 gap-2">
                            <form method="POST" action="{{ route('panel.banners.toggle', $banner) }}">
                                @csrf
                                <button type="submit" class="px-2.5 py-1 rounded-xl text-xs font-semibold {{ $banner->is_active ? 'bg-amber-50 text-amber-700 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                    {{ $banner->is_active ? 'Pausar' : 'Activar' }}
                                </button>
                            </form>

                            <div class="flex items-center gap-2">
                                <a href="{{ route('panel.banners.edit', $banner) }}" class="btn-outline py-1 px-2.5 text-xs">
                                    Editar
                                </a>

                                <form method="POST" action="{{ route('panel.banners.destroy', $banner) }}"
                                      onsubmit="return confirm('¿Seguro que deseas eliminar este banner?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg" title="Eliminar">
                                        <x-icon name="x" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $banners->links() }}
        </div>
    @endif
</x-app-layout>
