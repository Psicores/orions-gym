{{--
    Termómetro de ocupación (ver App\Livewire\GymOccupancyMeter).
    Recibe $estado: 'tranqui', 'movido' o 'a_pleno'. Nunca muestra cuánta gente hay.
    Se vuelve a consultar solo cada 5 minutos (wire:poll), sin recargar la página.
--}}
@php
    // Color del punto, texto y cuánto se llena la barra según el estado.
    $nivel = match ($estado) {
        'tranqui' => ['punto' => 'bg-emerald-400', 'texto' => 'Tranqui, ideal para entrenar.', 'barra' => 'w-1/3'],
        'movido' => ['punto' => 'bg-amber-400', 'texto' => 'Movido (compartiendo máquinas).', 'barra' => 'w-2/3'],
        default => ['punto' => 'bg-oxido', 'texto' => 'A pleno.', 'barra' => 'w-full'],
    };
@endphp

<div
    wire:poll.300s
    role="status"
    data-estado="{{ $estado }}"
    class="relative mb-5 flex items-center gap-3 overflow-hidden rounded-2xl border border-carbon-700 bg-carbon px-4 py-2.5 text-niebla"
>
    <span class="relative flex size-2.5 shrink-0" aria-hidden="true">
        <span class="absolute inline-flex size-full animate-pulse rounded-full motion-reduce:animate-none {{ $nivel['punto'] }}"></span>
    </span>

    <p class="text-xs leading-snug">
        <span class="text-niebla/70">Ocupación actual:</span>
        <span class="font-semibold">{{ $nivel['texto'] }}</span>
    </p>

    {{-- Barra fina al pie: se llena un tercio, dos tercios o entera. --}}
    <span class="absolute inset-x-0 bottom-0 h-0.5 bg-carbon-700" aria-hidden="true">
        <span class="block h-full transition-all duration-500 {{ $nivel['barra'] }} {{ $nivel['punto'] }}"></span>
    </span>
</div>
