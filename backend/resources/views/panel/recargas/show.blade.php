<x-app-layout>
    <x-slot name="title">Recarga de {{ $recarga->user->name }}</x-slot>
    <x-slot name="subtitle">
        Recibida el {{ $recarga->created_at?->translatedFormat('d \d\e F \d\e Y, H:i') }}
    </x-slot>

    @php
        $tintes = [
            'amber' => 'badge-pending',
            'cyan' => 'badge-approved',
            'red' => 'badge-rejected',
        ];
        $pendiente = $recarga->status === \App\Enums\RechargeStatus::Pending;
        $billetera = $recarga->user->wallet;
    @endphp

    <div class="mb-4">
        <a href="{{ route('panel.recargas.index', ['tab' => $pendiente ? 'pending' : 'todas']) }}" class="btn-ghost -ml-2">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Volver a recargas
        </a>
    </div>

    <div class="grid gap-5 xl:grid-cols-5">
        {{-- ------------------------------------------------------------
             Comprobante
             ------------------------------------------------------------ --}}
        <section class="card xl:col-span-3">
            <header class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                <div>
                    <h2 class="panel-title">Comprobante</h2>
                    <p class="text-xs text-content-muted">Archivo privado · enlace temporal</p>
                </div>
                <a href="{{ $recarga->proofUrl() }}" target="_blank" rel="noopener" class="btn-outline">
                    <x-icon name="external" class="h-4 w-4" />
                    Abrir original
                </a>
            </header>

            <div class="bg-surface-alt/60 p-4">
                @if ($recarga->proofIsImage())
                    <a href="{{ $recarga->proofUrl() }}" target="_blank" rel="noopener" class="block">
                        <img src="{{ $recarga->proofUrl() }}"
                             alt="Comprobante de la recarga"
                             class="mx-auto max-h-[32rem] w-auto rounded-xl border border-line bg-surface object-contain">
                    </a>
                @else
                    <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface text-brand-700 dark:text-accent-300 shadow-sm">
                            <x-icon name="document" class="h-7 w-7" />
                        </span>
                        <p class="text-sm font-medium text-content-muted">El comprobante está en PDF.</p>
                        <a href="{{ $recarga->proofUrl() }}" target="_blank" rel="noopener" class="btn-primary">
                            Abrir PDF
                        </a>
                    </div>
                @endif
            </div>
        </section>

        {{-- ------------------------------------------------------------
             Datos de la transferencia
             ------------------------------------------------------------ --}}
        <div class="space-y-5 xl:col-span-2">
            <section class="card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="panel-title">Datos de la transferencia</h2>
                        <p class="mt-1 text-sm text-content-muted">{{ $recarga->user->nameWithId }}</p>
                    </div>
                    <span class="{{ $tintes[$recarga->status->tone()] ?? 'badge-neutral' }}">
                        {{ $recarga->status->label() }}
                    </span>
                </div>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-content-muted">Monto transferido</dt>
                        <dd class="font-display text-lg font-semibold text-brand-900 dark:text-content">
                            Bs {{ number_format((float) $recarga->amount_bs, 2, ',', '.') }}
                        </dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-content-muted">Banco</dt>
                        <dd class="font-medium text-content">{{ $recarga->bank }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-content-muted">Referencia</dt>
                        <dd class="font-mono text-sm font-semibold text-content">{{ $recarga->reference }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-content-muted">Fecha de la transferencia</dt>
                        <dd class="font-medium text-content">{{ $recarga->transferred_at?->format('d/m/Y') }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 border-t border-line pt-3">
                        <dt class="text-content-muted">Billetera actual</dt>
                        <dd class="font-semibold text-content">
                            ${{ number_format((float) ($billetera?->balance ?? 0), 2, '.', ',') }}
                        </dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-content-muted">Disponible</dt>
                        <dd class="text-content-muted">
                            ${{ number_format((float) ($billetera?->available() ?? 0), 2, '.', ',') }}
                        </dd>
                    </div>
                </dl>
            </section>

            @if ($referenciaRepetida)
                <section class="rounded-2xl border border-amber-200 bg-amber-50 dark:bg-amber-950 p-5">
                    <div class="flex items-start gap-3">
                        <x-icon name="alert" class="mt-0.5 h-5 w-5 shrink-0 text-amber-700 dark:text-amber-300" />
                        <div>
                            <p class="text-sm font-semibold text-amber-900 dark:text-amber-300">Esta referencia ya aparece en otra recarga</p>
                            <p class="mt-1 text-xs text-amber-800 dark:text-amber-300">
                                Revisa antes de aprobar: puede ser un error de la persona o un intento de usar el mismo comprobante dos veces.
                            </p>

                            <ul class="mt-3 space-y-1 text-xs text-amber-900 dark:text-amber-300">
                                @foreach ($otrasConLaMismaReferencia as $otra)
                                    <li class="flex items-center justify-between gap-3 border-t border-amber-200/70 pt-1">
                                        <a href="{{ route('panel.recargas.show', $otra) }}" class="font-medium underline">
                                            {{ $otra->user->name }}
                                        </a>
                                        <span>{{ $otra->created_at?->format('d/m/Y') }} · {{ $otra->status->label() }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </section>
            @endif

            @unless ($pendiente)
                <section class="card p-5">
                    <h2 class="panel-title">Decisión</h2>

                    @if ($recarga->status === \App\Enums\RechargeStatus::Approved)
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-content-muted">Monto acreditado</dt>
                                <dd class="font-display text-lg font-semibold text-brand-800 dark:text-content">
                                    ${{ number_format((float) $recarga->amount_usd, 2, '.', ',') }}
                                </dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-content-muted">Tasa aplicada</dt>
                                <dd class="font-medium text-content">{{ $recarga->exchange_rate }} Bs/$</dd>
                            </div>
                        </dl>
                    @else
                        <div class="mt-3 rounded-xl bg-red-50 dark:bg-red-950 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-red-700 dark:text-red-300">Motivo del rechazo</p>
                            <p class="mt-1 text-sm text-red-800 dark:text-red-300">{{ $recarga->rejection_reason }}</p>
                        </div>
                    @endif

                    <p class="mt-4 text-xs text-content-muted">
                        Revisada por {{ $recarga->reviewer?->name ?? '—' }}
                        el {{ $recarga->reviewed_at?->translatedFormat('d M Y, H:i') ?? '—' }}.
                    </p>
                </section>
            @endunless
        </div>
    </div>

    @if ($pendiente)
        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            {{-- --------------------------------------------------------
                 Aprobar
                 -------------------------------------------------------- --}}
            <section class="card p-5" x-data="{ tasa: '{{ old('exchange_rate', $tasaVigente?->rate) }}', bs: {{ (float) $recarga->amount_bs }} }">
                <h2 class="panel-title">Aprobar recarga</h2>
                <p class="mt-1 text-sm text-content-muted">
                    Se acredita el monto en dólares a la billetera de {{ $recarga->user->first_name }}.
                </p>

                <form method="POST" action="{{ route('panel.recargas.aprobar', $recarga) }}" class="mt-4 space-y-4">
                    @csrf

                    <div>
                        <label for="exchange_rate" class="form-label">Tasa aplicada (Bs por $)</label>
                        <input id="exchange_rate" name="exchange_rate" type="number" step="0.0001" min="0.0001"
                               x-model="tasa"
                               value="{{ old('exchange_rate', $tasaVigente?->rate) }}"
                               class="form-input">
                        @error('exchange_rate')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                        <p class="form-hint">
                            @if ($tasaVigente)
                                Tasa vigente: <strong>{{ $tasaVigente->rate }}</strong> ({{ $tasaVigente->effective_date->format('d/m/Y') }}).
                                Puedes cambiarla solo para esta aprobación.
                            @else
                                No hay tasa vigente. Escribe la tasa del día para poder aprobar.
                            @endif
                        </p>
                    </div>

                    <div class="rounded-xl bg-brand-50 dark:bg-brand-900/30 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-brand-700 dark:text-accent-300">Se acreditará</p>
                        <p class="mt-1 font-display text-2xl font-semibold text-brand-900 dark:text-content">
                            $<span x-text="tasa > 0 ? (bs / tasa).toFixed(2) : '0.00'">0.00</span>
                        </p>
                        <p class="text-xs text-content-muted">Redondeado al centavo. El monto final lo calcula el servidor.</p>
                    </div>

                    <button type="submit" class="btn-primary w-full">
                        <x-icon name="check" class="h-4 w-4" />
                        Aprobar y acreditar
                    </button>
                </form>
            </section>

            {{-- --------------------------------------------------------
                 Rechazar
                 -------------------------------------------------------- --}}
            <section class="card p-5">
                <h2 class="panel-title">Rechazar recarga</h2>
                <p class="mt-1 text-sm text-content-muted">
                    No se acredita saldo. La persona verá el motivo en la app y podrá subir un comprobante nuevo.
                </p>

                <form method="POST" action="{{ route('panel.recargas.rechazar', $recarga) }}" class="mt-4 space-y-4">
                    @csrf

                    <div>
                        <label for="reason" class="form-label">Motivo</label>
                        <textarea id="reason" name="reason" rows="4" class="form-input"
                                  placeholder="Ej.: el comprobante no coincide con el monto o la referencia está repetida.">{{ old('reason') }}</textarea>
                        @error('reason')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="btn-danger w-full">
                        <x-icon name="x" class="h-4 w-4" />
                        Rechazar
                    </button>
                </form>
            </section>
        </div>
    @endif
</x-app-layout>
