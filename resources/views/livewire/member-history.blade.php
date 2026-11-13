{{--
    Historial del socio (ver App\Livewire\MemberHistory). Recibe $payments y $attendances.
    Las dos pestañas se cambian con Alpine, sin ir al servidor.
--}}
<div x-data="{ tab: 'pagos' }">
    <h1 class="font-display text-4xl leading-tight uppercase">Historial</h1>

    <div role="tablist" aria-label="Historial" class="mt-5 grid grid-cols-2 rounded-full border border-carbon-700 bg-carbon-800 p-1">
        <button
            type="button"
            role="tab"
            id="tab-pagos"
            aria-controls="panel-pagos"
            :aria-selected="tab === 'pagos'"
            @click="tab = 'pagos'"
            :class="tab === 'pagos' ? 'bg-salvia text-carbon' : 'text-ceniza'"
            class="cursor-pointer rounded-full px-4 py-2.5 text-sm font-bold tracking-wide transition"
        >
            Mis pagos
        </button>
        <button
            type="button"
            role="tab"
            id="tab-asistencias"
            aria-controls="panel-asistencias"
            :aria-selected="tab === 'asistencias'"
            @click="tab = 'asistencias'"
            :class="tab === 'asistencias' ? 'bg-salvia text-carbon' : 'text-ceniza'"
            class="cursor-pointer rounded-full px-4 py-2.5 text-sm font-bold tracking-wide transition"
        >
            Mis asistencias
        </button>
    </div>

    <section id="panel-pagos" role="tabpanel" aria-labelledby="tab-pagos" x-show="tab === 'pagos'" class="mt-5">
        @forelse ($payments as $payment)
            <article wire:key="pago-{{ $payment->id }}" class="flex items-center justify-between gap-4 border-b border-carbon-700 py-4">
                <div class="min-w-0">
                    <p class="truncate font-semibold">{{ $payment->membership?->plan?->nombre ?? 'Pago' }}</p>
                    <p class="mt-0.5 text-sm text-ceniza">
                        {{ $payment->fecha_pago->format('d/m/Y') }} · {{ $payment->metodo_pago->getLabel() }}
                    </p>
                </div>
                <p class="shrink-0 font-display text-2xl text-salvia-light tabular-nums">
                    $ {{ number_format((float) $payment->monto, 2, ',', '.') }}
                </p>
            </article>
        @empty
            <p class="rounded-2xl border border-carbon-700 px-5 py-8 text-center text-ceniza">Todavía no tenés pagos registrados.</p>
        @endforelse
    </section>

    <section id="panel-asistencias" role="tabpanel" aria-labelledby="tab-asistencias" x-show="tab === 'asistencias'" x-cloak class="mt-5">
        @forelse ($attendances as $attendance)
            <article wire:key="asistencia-{{ $attendance->id }}" class="flex items-center justify-between gap-4 border-b border-carbon-700 py-4">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-check-circle class="size-6 shrink-0 text-salvia" />
                    <p class="font-semibold capitalize">{{ $attendance->fecha_hora->translatedFormat('l d/m/Y') }}</p>
                </div>
                <p class="shrink-0 font-display text-2xl text-salvia-light tabular-nums">{{ $attendance->fecha_hora->format('H:i') }}</p>
            </article>
        @empty
            <p class="rounded-2xl border border-carbon-700 px-5 py-8 text-center text-ceniza">
                Todavía no registraste ingresos. Escaneá el QR de recepción cuando llegues.
            </p>
        @endforelse
    </section>
</div>
