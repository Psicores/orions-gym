{{--
    Ingreso con la cámara (ver App\Livewire\MemberCheckIn). Recibe $client y $accessPoint (null si
    el QR no sirve). El resultado ocupa toda la pantalla y se lee a dos metros: verde si entró al
    día, ámbar si quedó debiendo créditos o si no pudo entrar (nunca rojo).
--}}
@php
    // Entró pero quedó con saldo negativo.
    $debe = in_array($resultado, ['ok', 'repetido'], true) && $creditos !== null && $creditos < 0;
@endphp

<div class="flex min-h-[70dvh] flex-col">
    @if ($resultado === null && $accessPoint === null)
        {{-- QR viejo o apagado. --}}
        <section role="alert" class="my-auto rounded-3xl border border-oxido/60 bg-oxido/15 px-6 py-10 text-center">
            <x-heroicon-o-exclamation-triangle class="mx-auto size-16 text-oxido" />
            <p class="mt-5 font-display text-3xl uppercase">Este QR no está habilitado</p>
            <p class="mt-3 text-niebla">Avisá en recepción para que te registren el ingreso.</p>
        </section>
    @elseif ($resultado === null)
        {{-- Confirmación: abrir la página no registra nada. --}}
        <section class="my-auto rounded-3xl border border-carbon-700 bg-carbon-800 px-6 py-10 text-center">
            <p class="text-sm tracking-widest text-ceniza uppercase">{{ $accessPoint->nombre }}</p>
            <p class="mt-3 font-display text-4xl leading-tight uppercase">Hola, {{ $client->nombre }}</p>
            <p class="mt-3 text-niebla/90">Tocá el botón para registrar tu ingreso de hoy.</p>

            <button
                type="button"
                wire:click="confirmar"
                wire:loading.attr="disabled"
                class="mt-8 w-full cursor-pointer rounded-full bg-salvia px-8 py-5 text-base font-bold tracking-widest text-carbon uppercase transition hover:bg-salvia-light focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-salvia-light disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="confirmar">Registrar ingreso</span>
                <span wire:loading wire:target="confirmar">Registrando…</span>
            </button>
        </section>
    @elseif ($resultado === 'error')
        <section role="alert" aria-live="assertive" class="my-auto rounded-3xl border-2 border-oxido bg-oxido/20 px-6 py-12 text-center">
            <span class="mx-auto flex size-24 items-center justify-center rounded-full bg-oxido text-carbon">
                <x-heroicon-o-exclamation-triangle class="size-14" />
            </span>
            <p class="mt-6 font-display text-4xl leading-tight uppercase">No pudiste ingresar</p>
            <p class="mt-3 text-lg text-niebla">{{ $mensaje }}</p>
            <p class="mt-6 font-display text-3xl uppercase">{{ $client->nombre }} {{ $client->apellido }}</p>
        </section>
    @else
        <section aria-live="assertive" @class([
            'my-auto rounded-3xl border-2 px-6 py-12 text-center',
            'border-oxido bg-oxido/20' => $debe,
            'border-salvia bg-salvia/20' => ! $debe,
        ])>
            <span @class([
                'mx-auto flex size-24 items-center justify-center rounded-full text-carbon',
                'bg-oxido' => $debe,
                'bg-salvia' => ! $debe,
            ])>
                <x-heroicon-o-check class="size-14" />
            </span>
            <p class="mt-6 font-display text-5xl leading-tight uppercase">{{ $client->nombre }} {{ $client->apellido }}</p>
            <p class="mt-2 font-display text-2xl uppercase">{{ $resultado === 'ok' ? 'Ingreso registrado' : 'Ya estabas adentro' }}</p>
            <p class="mt-1 text-lg text-niebla">{{ $mensaje }}</p>

            <dl class="mt-8 grid grid-cols-2 gap-3">
                <div class="rounded-2xl bg-carbon/50 px-4 py-4">
                    <dt class="text-xs tracking-widest text-ceniza uppercase">Hora</dt>
                    <dd class="mt-1 font-display text-4xl tabular-nums">{{ $hora }}</dd>
                </div>
                <div class="rounded-2xl bg-carbon/50 px-4 py-4">
                    <dt class="text-xs tracking-widest text-ceniza uppercase">Créditos</dt>
                    <dd @class(['mt-1 font-display text-4xl tabular-nums', 'text-oxido' => $debe])>{{ $creditos }}</dd>
                </div>
            </dl>

            @if ($debe)
                <p class="mt-5 font-semibold">Quedaste debiendo créditos: pasá por recepción.</p>
            @endif
        </section>
    @endif
</div>
