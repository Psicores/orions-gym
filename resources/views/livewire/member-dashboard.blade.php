{{--
    Inicio del portal del socio (ver App\Livewire\MemberDashboard).
    Recibe $client, $membership (la membresía vigente con su plan, o null) y $announcements
    (los anuncios vigentes que el socio todavía no cerró).
--}}
@php
    use App\Enums\MembershipStatusEnum;

    // Colores de la tarjeta según el estado: verde salvia si puede entrar, naranja óxido si está en pausa.
    $colores = match ($membership?->estado) {
        MembershipStatusEnum::Entrenando, MembershipStatusEnum::ActivoFlex => [
            'borde' => 'border-salvia/40',
            'estado' => 'bg-salvia/15 text-salvia-light',
            'numero' => 'text-salvia-light',
        ],
        MembershipStatusEnum::EnPausa => [
            'borde' => 'border-oxido/50',
            'estado' => 'bg-oxido/15 text-oxido',
            'numero' => 'text-oxido',
        ],
        default => [
            'borde' => 'border-carbon-700',
            'estado' => 'bg-carbon-700 text-ceniza',
            'numero' => 'text-ceniza',
        ],
    };

    // Días que faltan para el vencimiento (negativo si ya pasó).
    $diasRestantes = $membership ? (int) today()->diffInDays($membership->fecha_vencimiento, false) : null;
    // Un plan libre (999 créditos) no tiene un número que mostrar.
    $esPaseLibre = $membership && $membership->plan->creditos_otorgados >= 999;
@endphp

<div>
    {{-- Termómetro de ocupación: qué tan lleno está el gimnasio ahora (se actualiza solo). --}}
    <livewire:gym-occupancy-meter />

    <p class="text-sm text-ceniza">Hola,</p>
    <h1 class="font-display text-4xl leading-tight uppercase">{{ $client->nombre }}</h1>

    {{-- Anuncios del gimnasio: cada socio los puede cerrar y no le vuelven a aparecer. --}}
    @if ($announcements->isNotEmpty())
        <section aria-label="Anuncios" class="mt-6 space-y-4">
            @foreach ($announcements as $announcement)
                <article wire:key="anuncio-{{ $announcement->id }}" class="relative overflow-hidden rounded-3xl border border-oxido/40 bg-carbon-800">
                    <button
                        type="button"
                        wire:click="dismissAnnouncement({{ $announcement->id }})"
                        wire:loading.attr="disabled"
                        aria-label="Cerrar el anuncio: {{ $announcement->titulo }}"
                        class="absolute top-3 right-3 z-10 flex size-9 cursor-pointer items-center justify-center rounded-full bg-carbon/80 text-niebla backdrop-blur transition hover:bg-carbon hover:text-oxido-light focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-oxido"
                    >
                        <x-heroicon-o-x-mark class="size-5" />
                    </button>

                    @if ($announcement->imageUrl())
                        <img src="{{ $announcement->imageUrl() }}" alt="" loading="lazy" class="max-h-72 w-full object-cover">
                    @endif

                    <div class="p-5 @unless ($announcement->imageUrl()) pr-14 @endunless">
                        <p class="text-[0.65rem] font-bold tracking-[0.3em] text-oxido uppercase">Novedad</p>
                        <h2 class="mt-1 font-display text-2xl leading-tight uppercase">{{ $announcement->titulo }}</h2>

                        @if ($announcement->safeContent())
                            <div class="mt-3 space-y-2 text-sm leading-relaxed text-niebla/90 [&_a]:text-salvia-light [&_a]:underline [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5">
                                {{ $announcement->safeContent() }}
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>
    @endif

    @if ($membership)
        <section aria-label="Mi membresía" class="mt-6 rounded-3xl border {{ $colores['borde'] }} bg-carbon-800 p-6 text-center">
            <p class="inline-flex rounded-full px-4 py-1.5 text-xs font-bold tracking-[0.25em] uppercase {{ $colores['estado'] }}">
                {{ $membership->estado->getLabel() }}
            </p>

            <p class="mt-6 font-display text-[7rem] leading-none tabular-nums {{ $colores['numero'] }}">
                {{ $esPaseLibre ? '∞' : $membership->creditos_disponibles }}
            </p>
            <p class="mt-2 text-sm font-semibold tracking-widest text-ceniza uppercase">
                {{ $esPaseLibre ? 'Pase libre' : 'Créditos disponibles' }}
            </p>

            <dl class="mt-8 grid grid-cols-2 gap-4 border-t border-carbon-700 pt-5 text-left">
                <div>
                    <dt class="text-xs tracking-widest text-ceniza uppercase">Plan</dt>
                    <dd class="mt-1 font-semibold">{{ $membership->plan->nombre }}</dd>
                </div>
                <div class="text-right">
                    <dt class="text-xs tracking-widest text-ceniza uppercase">Vence</dt>
                    <dd class="mt-1 font-semibold tabular-nums">{{ $membership->fecha_vencimiento->format('d/m/Y') }}</dd>
                </div>
            </dl>

            <p class="mt-4 text-sm text-ceniza">
                @if ($diasRestantes > 1)
                    Te quedan {{ $diasRestantes }} días.
                @elseif ($diasRestantes === 1)
                    Vence mañana.
                @elseif ($diasRestantes === 0)
                    Vence hoy.
                @else
                    Venció. Pasá por recepción para renovarla.
                @endif
            </p>
        </section>

        @if ($membership->estado === MembershipStatusEnum::EnPausa)
            <p class="mt-4 rounded-2xl border border-oxido/40 bg-oxido/10 px-4 py-3 text-sm text-niebla">
                Tu membresía está en pausa. Pasá por recepción para reactivarla.
            </p>
        @endif
    @else
        <section aria-label="Mi membresía" class="mt-6 rounded-3xl border border-carbon-700 bg-carbon-800 p-8 text-center">
            <p class="inline-flex rounded-full bg-carbon-700 px-4 py-1.5 text-xs font-bold tracking-[0.25em] text-ceniza uppercase">
                Sin membresía vigente
            </p>
            <p class="mt-5 text-niebla/90">Pasá por recepción para elegir tu plan y empezar a entrenar.</p>
        </section>
    @endif

    @unless ($client->hasCustomPassword())
        <a
            href="{{ route('member.account') }}"
            wire:navigate
            class="mt-4 flex items-center justify-between gap-4 rounded-2xl border border-carbon-700 px-4 py-3 text-sm text-niebla transition hover:border-salvia"
        >
            <span>Tu contraseña todavía es tu DNI. Cambiala por una propia.</span>
            <x-heroicon-o-chevron-right class="size-5 shrink-0 text-ceniza" />
        </a>
    @endunless
</div>
