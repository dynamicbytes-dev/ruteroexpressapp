<x-app-layout>
    <x-slot name="title">Retiro de {{ $retiro->user->name }}</x-slot>
    <x-slot name="subtitle">
        Solicitado el {{ $retiro->created_at?->translatedFormat('d \d\e F \d\e Y, H:i') }}
    </x-slot>

    @php
        $tintes = [
            'amber' => 'badge-pending',
            'cyan' => 'badge-approved',
            'red' => 'badge-rejected',
        ];
        $pendiente = $retiro->status === \App\Enums\WithdrawalStatus::Pending;
        $billetera = $retiro->user->wallet;
    @endphp

    <div class="mb-4">
        <a href="{{ route('panel.retiros.index', ['tab' => $pendiente ? 'pending' : 'todas']) }}" class="btn-ghost -ml-2">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Volver a retiros
        </a>
    </div>

    <div class="grid gap-5 xl:grid-cols-5">
        <div class="space-y-5 xl:col-span-3">
            <section class="card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="panel-title">Datos del retiro</h2>
                        <p class="mt-1 text-sm text-content-muted">{{ $retiro->user->nameWithId }}</p>
                    </div>
                    <span class="{{ $tintes[$retiro->status->tone()] ?? 'badge-neutral' }}">
                        {{ $retiro->status->label() }}
                    </span>
                </div>

                <div class="mt-4 rounded-xl bg-brand-50 dark:bg-brand-900/30 px-4 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-700 dark:text-accent-300">Monto solicitado</p>
                    <p class="mt-1 font-display text-3xl font-semibold text-brand-900 dark:text-content">
                        ${{ number_format((float) $retiro->amount_usd, 2, '.', ',') }}
                    </p>
                    <p class="mt-1 text-xs text-content-muted">
                        Retenido desde que hizo la solicitud. Si se rechaza, vuelve a estar disponible.
                    </p>
                </div>

                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-content-muted">Banco</dt>
                        <dd class="mt-1 font-medium text-content">{{ $retiro->bank }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-content-muted">Número de cuenta</dt>
                        <dd class="mt-1 font-mono font-medium text-content">{{ $retiro->account_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-content-muted">Titular</dt>
                        <dd class="mt-1 font-medium text-content">{{ $retiro->account_holder }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-content-muted">Cédula del titular</dt>
                        <dd class="mt-1 font-medium text-content">{{ $retiro->account_id ?? '—' }}</dd>
                    </div>
                </dl>
            </section>

            @unless ($pendiente)
                <section class="card p-5">
                    <h2 class="panel-title">Decisión</h2>

                    @if ($retiro->status === \App\Enums\WithdrawalStatus::Paid)
                        <p class="mt-2 text-sm text-content-muted">
                            Pagado por {{ $retiro->reviewer?->name ?? '—' }}
                            el {{ $retiro->reviewed_at?->translatedFormat('d M Y, H:i') ?? '—' }}.
                        </p>

                        @if ($retiro->proof_path)
                            <div class="mt-4 flex flex-wrap gap-3">
                                <a href="{{ $retiro->proofUrl() }}" target="_blank" rel="noopener" class="btn-outline">
                                    <x-icon name="external" class="h-4 w-4" />
                                    Ver comprobante
                                </a>
                            </div>

                            @if ($retiro->proofIsImage())
                                <div class="mt-4 rounded-xl border border-line bg-surface-alt/60 p-3">
                                    <img src="{{ $retiro->proofUrl() }}"
                                         alt="Comprobante del retiro"
                                         class="mx-auto max-h-96 w-auto rounded-lg bg-surface object-contain">
                                </div>
                            @endif
                        @endif
                    @else
                        <div class="mt-3 rounded-xl bg-red-50 dark:bg-red-950 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-red-700 dark:text-red-300">Motivo del rechazo</p>
                            <p class="mt-1 text-sm text-red-800 dark:text-red-300">{{ $retiro->rejection_reason }}</p>
                        </div>
                        <p class="mt-3 text-sm text-content-muted">
                            El monto de ${{ number_format((float) $retiro->amount_usd, 2, '.', ',') }}
                            ya volvió a estar disponible en la billetera del conductor.
                        </p>
                    @endif
                </section>
            @endunless
        </div>

        <div class="space-y-5 xl:col-span-2">
            <section class="card p-5">
                <h2 class="panel-title">Billetera del conductor</h2>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-content-muted">Saldo total</dt>
                        <dd class="font-semibold text-content">
                            ${{ number_format((float) ($billetera?->balance ?? 0), 2, '.', ',') }}
                        </dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-content-muted">Retenido</dt>
                        <dd class="text-content-muted">
                            ${{ number_format((float) ($billetera?->held_balance ?? 0), 2, '.', ',') }}
                        </dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 border-t border-line pt-3">
                        <dt class="text-content-muted">Disponible</dt>
                        <dd class="font-display text-lg font-semibold text-brand-900 dark:text-content">
                            ${{ number_format((float) ($billetera?->available() ?? 0), 2, '.', ',') }}
                        </dd>
                    </div>
                </dl>

                <p class="mt-3 text-xs text-content-muted">
                    El monto de este retiro ya está descontado de lo disponible.
                </p>
            </section>

            @if ($pendiente)
                <section class="card p-5">
                    <h2 class="panel-title">Marcar como pagado</h2>
                    <p class="mt-1 text-sm text-content-muted">
                        Haz la transferencia a la cuenta indicada y adjunta el comprobante.
                        Al confirmar, el monto sale de la billetera del conductor.
                    </p>

                    <form method="POST" action="{{ route('panel.retiros.pagar', $retiro) }}"
                          enctype="multipart/form-data" class="mt-4 space-y-4">
                        @csrf

                        <div>
                            <label for="proof" class="form-label">Comprobante de la transferencia</label>
                            <input id="proof" name="proof" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf"
                                   class="form-input text-sm file:mr-2 file:rounded-lg file:border-0 file:bg-brand-50 dark:file:bg-brand-900/30 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-800 dark:file:text-content">
                            @error('proof')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                            <p class="form-hint">JPG, PNG, WEBP o PDF · máximo 6 MB.</p>
                        </div>

                        <button type="submit" class="btn-primary w-full">
                            <x-icon name="check" class="h-4 w-4" />
                            Confirmar pago
                        </button>
                    </form>
                </section>

                <section class="card p-5">
                    <h2 class="panel-title">Rechazar retiro</h2>
                    <p class="mt-1 text-sm text-content-muted">
                        Se libera el monto retenido y la persona ve el motivo en la app.
                    </p>

                    <form method="POST" action="{{ route('panel.retiros.rechazar', $retiro) }}" class="mt-4 space-y-4">
                        @csrf

                        <div>
                            <label for="reason" class="form-label">Motivo</label>
                            <textarea id="reason" name="reason" rows="4" class="form-input"
                                      placeholder="Ej.: la cuenta no está a nombre del conductor.">{{ old('reason') }}</textarea>
                            @error('reason')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="btn-danger w-full">
                            <x-icon name="x" class="h-4 w-4" />
                            Rechazar retiro
                        </button>
                    </form>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
