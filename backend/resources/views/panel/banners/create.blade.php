<x-app-layout>
    <x-slot name="title">Nueva Tarjeta de Publicidad</x-slot>
    <x-slot name="subtitle">Configura el diseño y contenido del banner para la app móvil</x-slot>

    <div x-data="{
        title: '¡Viaja cómodo y seguro!',
        subtitle: 'Obtén descuentos especiales en tus traslados diarios.',
        tag: 'PROMO',
        bgColor: '#310F38',
        bgColorEnd: '#9A0065',
        textColor: '#FFFFFF',
        actionText: 'Ver detalles'
    }" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Formulario principal --}}
        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('panel.banners.store') }}" enctype="multipart/form-data" class="card p-6 space-y-6">
                @csrf

                {{-- Título y Subtítulo --}}
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-content-muted mb-1">
                            Título principal *
                        </label>
                        <input type="text" name="title" x-model="title" required
                               placeholder="Ej: Descuento en tu primer viaje"
                               class="form-input w-full @error('title') border-rose-500 @enderror">
                        @error('title')
                            <p class="mt-1 text-xs text-rose-500 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-content-muted mb-1">
                            Subtítulo / Descripción corta
                        </label>
                        <textarea name="subtitle" x-model="subtitle" rows="2"
                                  placeholder="Ej: Usa el código GAZ50 al solicitar tu viaje en moto o carro."
                                  class="form-input w-full text-sm @error('subtitle') border-rose-500 @enderror"></textarea>
                        @error('subtitle')
                            <p class="mt-1 text-xs text-rose-500 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-content-muted mb-1">
                                Etiqueta / Tag (Opcional)
                            </label>
                            <input type="text" name="tag" x-model="tag"
                                   placeholder="Ej: PROMO, NUEVO, 30% OFF"
                                   class="form-input w-full">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-content-muted mb-1">
                                Texto del botón / acción
                            </label>
                            <input type="text" name="action_text" x-model="actionText"
                                   placeholder="Ej: Ver más, Aprovechar"
                                   class="form-input w-full">
                        </div>
                    </div>
                </div>

                {{-- Enlace o acción --}}
                <div class="border-t border-line pt-5">
                    <h3 class="text-sm font-semibold text-content mb-3">Enlace o Destino</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">
                                URL de destino (web o interna)
                            </label>
                            <input type="text" name="action_url" value="{{ old('action_url') }}"
                                   placeholder="https://ejemplo.com o #promocion"
                                   class="form-input w-full text-sm font-mono">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">
                                Audiencia objetivo *
                            </label>
                            <select name="target_role" required class="form-input w-full text-sm">
                                <option value="passenger" {{ old('target_role') == 'passenger' ? 'selected' : '' }}>Solo Pasajeros</option>
                                <option value="driver" {{ old('target_role') == 'driver' ? 'selected' : '' }}>Solo Conductores</option>
                                <option value="all" {{ old('target_role') == 'all' ? 'selected' : '' }}>Todos los usuarios</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Imagen publicitaria --}}
                <div class="border-t border-line pt-5">
                    <h3 class="text-sm font-semibold text-content mb-3">Gráfico o Imagen del Banner</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">
                                Subir archivo de imagen (PNG, JPG o WebP - fondo transparente recomendado)
                            </label>
                            <input type="file" name="image" accept="image/*" class="form-input w-full text-xs">
                            <p class="text-[11px] text-content-subtle mt-1">Tamaño máximo 4MB. Aparecerá en la esquina de la tarjeta.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">
                                O URL de imagen externa
                            </label>
                            <input type="url" name="image_url" value="{{ old('image_url') }}"
                                   placeholder="https://servidor.com/imagen.png"
                                   class="form-input w-full text-sm">
                        </div>
                    </div>
                </div>

                {{-- Estilos visuales y colores --}}
                <div class="border-t border-line pt-5">
                    <h3 class="text-sm font-semibold text-content mb-3">Colores y Personalización</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">Color fondo inicio</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="bgColor" class="h-9 w-9 rounded-lg cursor-pointer border border-line">
                                <input type="text" name="background_color" x-model="bgColor" class="form-input flex-1 text-xs font-mono uppercase">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">Color fondo fin (gradiente)</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="bgColorEnd" class="h-9 w-9 rounded-lg cursor-pointer border border-line">
                                <input type="text" name="background_color_end" x-model="bgColorEnd" class="form-input flex-1 text-xs font-mono uppercase">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">Color de texto</label>
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="textColor" class="h-9 w-9 rounded-lg cursor-pointer border border-line">
                                <input type="text" name="text_color" x-model="textColor" class="form-input flex-1 text-xs font-mono uppercase">
                            </div>
                        </div>
                    </div>

                    {{-- Paletas rápidas sugeridas --}}
                    <div class="mt-3 flex items-center gap-2">
                        <span class="text-[11px] text-content-subtle">Paletas GAZ:</span>
                        <button type="button" @click="bgColor = '#310F38'; bgColorEnd = '#9A0065'; textColor = '#FFFFFF'"
                                class="px-2 py-0.5 rounded text-[10px] bg-[#310F38] text-white font-medium">
                            Morado & Magenta
                        </button>
                        <button type="button" @click="bgColor = '#16051B'; bgColorEnd = '#007A87'; textColor = '#FFFFFF'"
                                class="px-2 py-0.5 rounded text-[10px] bg-[#16051B] text-cyan-400 font-medium">
                            Oscuro & Cian
                        </button>
                        <button type="button" @click="bgColor = '#9A0065'; bgColorEnd = '#FF5E00'; textColor = '#FFFFFF'"
                                class="px-2 py-0.5 rounded text-[10px] bg-[#9A0065] text-white font-medium">
                            Magenta & Fuego
                        </button>
                    </div>
                </div>

                {{-- Segmentación y estado --}}
                <div class="border-t border-line pt-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">Ciudad (opcional)</label>
                            <select name="city_id" class="form-input w-full text-sm">
                                <option value="">Todas las ciudades</option>
                                @foreach ($ciudades as $ciudad)
                                    <option value="{{ $ciudad->id }}">{{ $ciudad->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">Prioridad (orden de aparición)</label>
                            <input type="number" name="priority" value="0" min="0" max="999" class="form-input w-full text-sm">
                        </div>
                    </div>

                    <div class="mt-4 flex items-center gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" checked class="rounded border-line-strong text-brand-800 dark:text-content focus:ring-brand-500">
                            <span class="text-xs font-medium text-content">Publicar de inmediato (activo)</span>
                        </label>
                    </div>
                </div>

                <div class="border-t border-line pt-5 flex items-center justify-end gap-3">
                    <a href="{{ route('panel.banners.index') }}" class="btn-outline">
                        Cancelar
                    </a>
                    <button type="submit" class="btn-primary">
                        Guardar y Publicar
                    </button>
                </div>
            </form>
        </div>

        {{-- Panel lateral: Previsualización en vivo --}}
        <div class="space-y-4">
            <div class="sticky top-6 card p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-display font-semibold text-xs uppercase tracking-wider text-content-subtle">
                        Vista previa en móvil
                    </h3>
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-accent-400/20 text-accent-700 dark:text-accent-300 font-semibold">
                        En vivo
                    </span>
                </div>

                {{-- Mockup del contenedor del teléfono --}}
                <div class="p-3 bg-ink-900 rounded-3xl shadow-inner">
                    <div class="rounded-2xl p-4 min-h-[145px] flex flex-col justify-between relative overflow-hidden transition-all duration-200"
                         :style="`background: ${bgColorEnd ? 'linear-gradient(135deg, ' + bgColor + ', ' + bgColorEnd + ')' : bgColor}; color: ${textColor};`">

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <template x-if="tag">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase bg-white/20 backdrop-blur-sm"
                                          x-text="tag"></span>
                                </template>
                            </div>

                            <h4 class="font-display font-bold text-base leading-snug line-clamp-2"
                                x-text="title || 'Título del banner'"></h4>

                            <p class="text-xs opacity-90 mt-1 line-clamp-2"
                               x-text="subtitle"></p>
                        </div>

                        <div class="mt-3 pt-2 flex items-center justify-between">
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-xl bg-black/25">
                                <span x-text="actionText || 'Ver más'"></span> →
                            </span>
                        </div>
                    </div>
                </div>

                <p class="text-[11px] text-content-subtle text-center mt-3">
                    Esta tarjeta flotante se renderizará automáticamente en el carrusel de la app del pasajero.
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
