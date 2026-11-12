<x-app-layout>
    <x-slot name="title">{{ $usuario->name }}</x-slot>
    <x-slot name="subtitle">{{ $usuario->role->label() }} · V-{{ $usuario->national_id }}</x-slot>

    <x-slot name="actions">
        <a href="{{ route('panel.usuarios.index') }}" class="btn-outline">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Volver a la lista
        </a>
    </x-slot>

    @php
        $tintes = [
            'amber' => 'badge-pending',
            'cyan' => 'badge-approved',
            'red' => 'badge-rejected',
            'slate' => 'badge-neutral',
        ];
        $puedeAprobar = $usuario->canBeApproved();
        $aprobados = $usuario->documents->where('status', \App\Enums\DocumentStatus::Approved)->count();
        $total = count($usuario->role->requiredDocuments());
        $selfie = $usuario->documents->first(fn ($documento) => $documento->type === \App\Enums\DocumentType::Selfie
            && str_starts_with((string) $documento->mime_type, 'image/'));
    @endphp

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        {{-- ------------------------------------------------------------------
             Columna principal
             ------------------------------------------------------------------ --}}
        <div class="space-y-6">

            {{-- Motivo del rechazo o la suspensión: lo primero que el auditor necesita ver. --}}
            @if (in_array($usuario->status, [\App\Enums\UserStatus::Rejected, \App\Enums\UserStatus::Suspended], true) && $usuario->status_reason)
                @php
                    $esSuspension = $usuario->status === \App\Enums\UserStatus::Suspended;
                @endphp
                <div class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 dark:bg-red-950 px-4 py-3">
                    <x-icon name="alert" class="mt-0.5 h-5 w-5 shrink-0 text-red-600 dark:text-red-300" />
                    <div>
                        <p class="text-sm font-semibold text-red-700 dark:text-red-300">
                            {{ $esSuspension ? 'Esta cuenta está suspendida' : 'Esta cuenta fue rechazada' }}
                        </p>
                        <p class="mt-0.5 text-sm text-red-700/90 dark:text-red-300">{{ $usuario->status_reason }}</p>
                    </div>
                </div>
            @endif

            {{-- Documentos --}}
            <section class="card">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div>
                        <h2 class="panel-title">Documentos</h2>
                        <p class="text-xs text-content-muted">
                            {{ $aprobados }} de {{ $total }} aprobados ·
                            El archivo se sirve con un enlace temporal y privado
                        </p>
                    </div>
                </header>

                <div class="divide-y divide-line">
                    @foreach ($documentos as $documento)
                        @continue($documento === null)

                        <article class="p-5">
                            <div class="flex flex-wrap items-start gap-5">
                                {{-- Vista previa --}}
                                <div class="shrink-0">
                                    @if (str_starts_with((string) $documento->mime_type, 'image/'))
                                        <a href="{{ $documento->temporaryUrl() }}" target="_blank" rel="noopener"
                                           class="block h-32 w-40 overflow-hidden rounded-xl border border-line bg-surface-alt">
                                            <img src="{{ $documento->temporaryUrl() }}"
                                                 alt="{{ $documento->type->label() }} de {{ $usuario->name }}"
                                                 class="h-full w-full object-cover">
                                        </a>
                                    @else
                                        <a href="{{ $documento->temporaryUrl() }}" target="_blank" rel="noopener"
                                           class="flex h-32 w-40 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-line-strong bg-surface-alt text-content-muted hover:border-brand-300 hover:text-brand-700 dark:hover:text-accent-300">
                                            <x-icon name="document" class="h-7 w-7" />
                                            <span class="text-xs font-medium">Abrir archivo</span>
                                        </a>
                                    @endif
                                </div>

                                {{-- Datos y decisiones --}}
                                <div class="min-w-64 flex-1" x-data="{ rechazando: false }">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-display text-base font-semibold text-brand-900 dark:text-content">
                                            {{ $documento->type->label() }}
                                        </h3>
                                        <span class="{{ $tintes[$documento->status->tone()] ?? 'badge-neutral' }}">
                                            {{ $documento->status->label() }}
                                        </span>
                                        @if ($documento->isExpired())
                                            <span class="badge-rejected">Vencido</span>
                                        @elseif ($documento->isExpiringSoon())
                                            <span class="badge-pending">
                                                Vence en {{ $documento->daysUntilExpiration() }} días
                                            </span>
                                        @endif
                                    </div>

                                    <p class="mt-1 text-xs text-content-muted">{{ $documento->type->hint() }}</p>

                                    <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-content-muted">
                                        <div class="flex gap-1">
                                            <dt class="font-medium text-content-muted">Subido:</dt>
                                            <dd>{{ $documento->uploaded_at?->translatedFormat('d M Y, H:i') ?? '—' }}</dd>
                                        </div>
                                        @if ($documento->expires_at)
                                            <div class="flex gap-1">
                                                <dt class="font-medium text-content-muted">Vence:</dt>
                                                <dd>{{ $documento->expires_at->translatedFormat('d M Y') }}</dd>
                                            </div>
                                        @endif
                                        @if ($documento->reviewer)
                                            <div class="flex gap-1">
                                                <dt class="font-medium text-content-muted">Revisado por:</dt>
                                                <dd>{{ $documento->reviewer->name }}</dd>
                                            </div>
                                        @endif
                                    </dl>

                                    @if ($documento->status === \App\Enums\DocumentStatus::Rejected && $documento->rejection_reason)
                                        <p class="mt-3 rounded-xl bg-red-50 dark:bg-red-950 px-3 py-2 text-xs text-red-700 dark:text-red-300">
                                            <span class="font-semibold">Motivo enviado:</span> {{ $documento->rejection_reason }}
                                        </p>
                                    @endif

                                    {{-- Acciones --}}
                                    @if ($documento->status !== \App\Enums\DocumentStatus::Approved)
                                        <div class="mt-4 flex flex-wrap items-center gap-2">
                                            <form method="POST" action="{{ route('panel.documentos.aprobar', $documento) }}">
                                                @csrf
                                                <button type="submit" class="btn-accent">
                                                    <x-icon name="check" class="h-4 w-4" />
                                                    Aprobar
                                                </button>
                                            </form>

                                            <button type="button" class="btn-outline" x-show="! rechazando" @click="rechazando = true">
                                                <x-icon name="x" class="h-4 w-4" />
                                                Rechazar
                                            </button>
                                        </div>
                                    @else
                                        <div class="mt-4">
                                            <form method="POST" action="{{ route('panel.documentos.rechazar', $documento) }}"
                                                  x-data="{ rechazando: false }">
                                                @csrf
                                                <button type="button" class="btn-ghost" x-show="! rechazando" @click="rechazando = true">
                                                    Volver a rechazar
                                                </button>
                                                <div x-cloak x-show="rechazando" class="mt-2">
                                                    <label for="reason-{{ $documento->id }}" class="form-label">
                                                        Motivo del rechazo
                                                    </label>
                                                    <textarea id="reason-{{ $documento->id }}" name="reason" rows="3"
                                                              class="form-input" required minlength="10" maxlength="500"
                                                              placeholder="Qué está mal y qué debe corregir la persona"></textarea>
                                                    <div class="mt-2 flex gap-2">
                                                        <button type="submit" class="btn-danger">Enviar rechazo</button>
                                                        <button type="button" class="btn-ghost" @click="rechazando = false">Cancelar</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    @endif

                                    {{-- Formulario de rechazo --}}
                                    @if ($documento->status !== \App\Enums\DocumentStatus::Approved)
                                        <form method="POST" action="{{ route('panel.documentos.rechazar', $documento) }}"
                                              x-cloak x-show="rechazando" class="mt-3">
                                            @csrf
                                            <label for="reason-{{ $documento->id }}" class="form-label">Motivo del rechazo</label>
                                            <textarea id="reason-{{ $documento->id }}" name="reason" rows="3"
                                                      class="form-input" required minlength="10" maxlength="500"
                                                      placeholder="Qué está mal y qué debe corregir la persona"></textarea>
                                            <div class="mt-2 flex gap-2">
                                                <button type="submit" class="btn-danger">Enviar rechazo</button>
                                                <button type="button" class="btn-ghost" @click="rechazando = false">Cancelar</button>
                                            </div>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach

                    {{-- Documentos que todavía no subió --}}
                    @foreach ($usuario->missingDocuments() as $tipo)
                        <article class="flex items-center gap-5 p-5">
                            <div class="flex h-32 w-40 shrink-0 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-line-strong bg-surface-alt text-content-subtle">
                                <x-icon name="image" class="h-7 w-7" />
                                <span class="text-xs font-medium">Sin subir</span>
                            </div>
                            <div>
                                <h3 class="font-display text-base font-semibold text-content-muted">{{ $tipo->label() }}</h3>
                                <p class="mt-1 text-xs text-content-muted">{{ $tipo->hint() }}</p>
                                <p class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300">La persona todavía no lo envió.</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            {{-- Historial --}}
            <section class="card">
                <header class="border-b border-line px-5 py-4">
                    <h2 class="panel-title">Historial de revisiones</h2>
                    <p class="text-xs text-content-muted">Cada decisión queda registrada y no se puede editar</p>
                </header>

                @if ($revisiones->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-content-muted">
                        Todavía no hay decisiones registradas para esta cuenta.
                    </p>
                @else
                    <ol class="divide-y divide-line">
                        @foreach ($revisiones as $revision)
                            <li class="flex items-start gap-3 px-5 py-4">
                                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full
                                             {{ $revision->isPositive() ? 'bg-accent-100 dark:bg-accent-900 text-accent-800 dark:text-accent-300' : 'bg-red-100 dark:bg-red-950 text-red-600 dark:text-red-300' }}">
                                    <x-icon :name="$revision->isPositive() ? 'check' : 'x'" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-content">
                                        {{ $revision->decisionLabel() }}
                                        {{ $revision->scope === \App\Models\VerificationReview::SCOPE_ACCOUNT
                                            ? 'la cuenta'
                                            : '«'.$revision->document_type?->label().'»' }}
                                    </p>
                                    <p class="text-xs text-content-muted">
                                        {{ $revision->auditor?->name ?? 'Auditor' }} ·
                                        {{ $revision->created_at->translatedFormat('d M Y, H:i') }}
                                    </p>
                                    @if ($revision->reason)
                                        <p class="mt-1 text-xs text-content-muted">{{ $revision->reason }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>

        {{-- ------------------------------------------------------------------
             Columna lateral: datos y decisión final
             ------------------------------------------------------------------ --}}
        <div class="space-y-6">
            <section class="card-pad">
                <div class="flex items-center gap-3">
                    @if ($selfie)
                        <img src="{{ $selfie->temporaryUrl() }}"
                             alt="Foto de {{ $usuario->name }}"
                             class="h-12 w-12 shrink-0 rounded-2xl border border-line object-cover">
                    @else
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 dark:bg-brand-900/30 font-display text-lg font-bold text-brand-700 dark:text-accent-300">
                            {{ strtoupper(substr($usuario->first_name, 0, 1)) }}
                        </span>
                    @endif
                    <div class="min-w-0">
                        <p class="truncate font-display text-base font-semibold text-brand-900 dark:text-content">{{ $usuario->name }}</p>
                        <span class="{{ $tintes[$usuario->status->tone()] ?? 'badge-neutral' }}">
                            {{ $usuario->status->label() }}
                        </span>
                    </div>
                </div>

                <dl class="mt-5 space-y-3 text-sm">
                    @foreach ([
                        'Cédula' => 'V-'.$usuario->national_id,
                        'Teléfono' => $usuario->phone,
                        'Dirección' => $usuario->address,
                        'Ciudad' => $usuario->city?->displayName() ?? 'Sin asignar',
                        'Registro' => $usuario->created_at->translatedFormat('d M Y'),
                    ] as $etiqueta => $valor)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-content-subtle">{{ $etiqueta }}</dt>
                            <dd class="text-content">{{ $valor }}</dd>
                        </div>
                    @endforeach

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-content-subtle">Correo</dt>
                        <dd class="flex flex-wrap items-center gap-2 text-content">
                            {{ $usuario->email }}
                            @if ($usuario->hasVerifiedEmail())
                                <span class="badge-approved">Verificado</span>
                            @else
                                <span class="badge-pending">Sin verificar</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="card-pad" x-data="{ corrigiendo: false }">
                <h2 class="panel-title">Cédula</h2>
                <p class="mt-2 text-sm text-content-muted">
                    V-{{ $usuario->national_id }}
                    <span class="ml-1 text-xs text-content-subtle">Solo lectura para la persona</span>
                </p>
                <p class="mt-2 text-xs text-content-muted">
                    Corrige únicamente errores de digitación. La persona no puede cambiarla
                    desde su perfil y la corrección queda en la auditoría con tu nombre y motivo.
                </p>

                <button type="button" class="btn-ghost mt-4 w-full" x-show="! corrigiendo" @click="corrigiendo = true">
                    <x-icon name="pencil" class="h-4 w-4" />
                    Corregir cédula
                </button>

                <form method="POST" action="{{ route('panel.usuarios.cedula', $usuario) }}"
                      x-cloak x-show="corrigiendo" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label for="cedula-nueva" class="form-label">Cédula correcta</label>
                        <input id="cedula-nueva" name="national_id" type="text" inputmode="numeric"
                               pattern="[0-9]{6,9}" minlength="6" maxlength="9" class="form-input"
                               value="{{ $usuario->national_id }}" required>
                        @error('national_id')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="cedula-motivo" class="form-label">Motivo de la corrección</label>
                        <textarea id="cedula-motivo" name="reason" rows="3" class="form-input"
                                  required minlength="10" maxlength="500"
                                  placeholder="Ej.: la persona se equivocó al escribir el número en el registro"></textarea>
                        @error('reason')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-danger">Guardar corrección</button>
                        <button type="button" class="btn-ghost" @click="corrigiendo = false">Cancelar</button>
                    </div>
                </form>
            </section>

            @if ($usuario->driverProfile)
                <section class="card-pad">
                    <h2 class="panel-title">Vehículo</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        @foreach ([
                            'Tipo' => $usuario->driverProfile->vehicleTypeLabel(),
                            'Marca' => $usuario->driverProfile->vehicle_brand,
                            'Modelo' => $usuario->driverProfile->vehicle_model,
                            'Año' => $usuario->driverProfile->vehicle_year,
                            'Placa' => $usuario->driverProfile->vehicle_plate,
                        ] as $etiqueta => $valor)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-content-muted">{{ $etiqueta }}</dt>
                                <dd class="font-medium text-content">{{ $valor }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif

            {{-- Billetera y ajuste manual --}}
            <section class="card-pad" x-data="{ ajustando: false }">
                <h2 class="panel-title">Billetera</h2>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-content-muted">Saldo</dt>
                        <dd class="font-display text-lg font-semibold text-brand-900 dark:text-content">
                            ${{ number_format((float) ($usuario->wallet?->balance ?? 0), 2, '.', ',') }}
                        </dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-content-muted">Retenido</dt>
                        <dd class="font-medium text-content">
                            ${{ number_format((float) ($usuario->wallet?->held_balance ?? 0), 2, '.', ',') }}
                        </dd>
                    </div>
                </dl>

                <button type="button" class="btn-ghost mt-4 w-full" x-show="! ajustando" @click="ajustando = true">
                    <x-icon name="wallet" class="h-4 w-4" />
                    Ajustar saldo
                </button>

                <form method="POST" action="{{ route('panel.usuarios.ajuste', $usuario) }}"
                      x-cloak x-show="ajustando" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label for="ajuste-monto" class="form-label">Monto en USD</label>
                        <input id="ajuste-monto" name="amount" type="number" step="0.01" min="-100000" max="100000"
                               class="form-input" required
                               placeholder="Positivo suma, negativo resta. Ej.: -2.50">
                    </div>
                    <div>
                        <label for="ajuste-nota" class="form-label">Nota obligatoria</label>
                        <textarea id="ajuste-nota" name="note" rows="3" class="form-input"
                                  required minlength="10" maxlength="500"
                                  placeholder="Explica por qué se mueve el saldo: la nota queda en el libro mayor"></textarea>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-accent flex-1">Aplicar ajuste</button>
                        <button type="button" class="btn-ghost" @click="ajustando = false">Cancelar</button>
                    </div>
                </form>
            </section>

            {{-- Decisión final --}}
            <section class="card-pad" x-data="{ rechazando: false, suspendiendo: false }">
                <h2 class="panel-title">Decisión de la cuenta</h2>

                @if ($usuario->status === \App\Enums\UserStatus::Approved)
                    <p class="mt-3 flex items-center gap-2 text-sm text-accent-800 dark:text-accent-300">
                        <x-icon name="check" class="h-5 w-5" />
                        Cuenta aprobada el {{ $usuario->approved_at?->translatedFormat('d M Y') }}.
                    </p>
                    <p class="mt-2 text-xs text-content-muted">
                        {{ $usuario->isDriver()
                            ? 'El conductor ya puede activarse para recibir viajes.'
                            : 'El pasajero ya puede pedir viajes y recargar su billetera.' }}
                    </p>

                    <button type="button" class="btn-ghost mt-3 w-full" x-show="! suspendiendo" @click="suspendiendo = true">
                        <x-icon name="alert" class="h-4 w-4" />
                        Suspender la cuenta
                    </button>

                    <form method="POST" action="{{ route('panel.usuarios.suspender', $usuario) }}"
                          x-cloak x-show="suspendiendo" class="mt-3">
                        @csrf
                        <label for="motivo-suspension" class="form-label">Motivo de la suspensión</label>
                        <textarea id="motivo-suspension" name="reason" rows="3" class="form-input"
                                  required minlength="10" maxlength="500"
                                  placeholder="Explica qué pasó y qué debe hacer la persona para recuperar la cuenta"></textarea>
                        <div class="mt-2 flex gap-2">
                            <button type="submit" class="btn-danger flex-1">Suspender</button>
                            <button type="button" class="btn-ghost" @click="suspendiendo = false">Cancelar</button>
                        </div>
                    </form>
                @elseif ($usuario->status === \App\Enums\UserStatus::Suspended)
                    <p class="mt-3 flex items-center gap-2 text-sm text-red-700 dark:text-red-300">
                        <x-icon name="alert" class="h-5 w-5" />
                        Cuenta suspendida. No puede operar en la app.
                    </p>
                    <p class="mt-2 text-xs text-content-muted">
                        Al reactivarla vuelve a estar aprobada con los mismos documentos que ya revisaste.
                    </p>

                    <form method="POST" action="{{ route('panel.usuarios.reactivar', $usuario) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="btn-accent w-full">
                            <x-icon name="check" class="h-4 w-4" />
                            Reactivar la cuenta
                        </button>
                    </form>
                @else
                    @if ($puedeAprobar)
                        <p class="mt-2 text-sm text-content-muted">
                            Todos los documentos están aprobados. Al aprobar la cuenta, la persona podrá
                            {{ $usuario->isDriver() ? 'activarse para recibir viajes' : 'pedir viajes y recargar su billetera' }}.
                        </p>

                        <form method="POST" action="{{ route('panel.usuarios.aprobar', $usuario) }}" class="mt-4">
                            @csrf
                            <button type="submit" class="btn-accent w-full">
                                <x-icon name="check" class="h-4 w-4" />
                                Aprobar la cuenta
                            </button>
                        </form>
                    @else
                        <p class="mt-2 rounded-xl bg-surface-alt px-3 py-2 text-xs text-content-muted">
                            Todavía no se puede aprobar: hay documentos sin aprobar, vencidos o sin subir.
                            Revisa cada uno en la lista de documentos.
                        </p>
                    @endif

                    <button type="button" class="btn-ghost mt-3 w-full" x-show="! rechazando" @click="rechazando = true">
                        <x-icon name="x" class="h-4 w-4" />
                        Rechazar la cuenta
                    </button>

                    <form method="POST" action="{{ route('panel.usuarios.rechazar', $usuario) }}"
                          x-cloak x-show="rechazando" class="mt-3">
                        @csrf
                        <label for="motivo-cuenta" class="form-label">Motivo del rechazo</label>
                        <textarea id="motivo-cuenta" name="reason" rows="3" class="form-input"
                                  required minlength="10" maxlength="500"
                                  placeholder="Por ejemplo: los datos del vehículo no coinciden con el carnet de circulación"></textarea>
                        <div class="mt-2 flex gap-2">
                            <button type="submit" class="btn-danger flex-1">Enviar rechazo</button>
                            <button type="button" class="btn-ghost" @click="rechazando = false">Cancelar</button>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
