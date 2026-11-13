{{-- Mi cuenta (ver App\Livewire\MemberAccount). Recibe $client. --}}
@php
    // Clases comunes de los campos del formulario.
    $campo = 'mt-2 w-full rounded-xl border border-carbon-700 bg-carbon-800 px-4 py-3.5 text-base text-niebla placeholder:text-ceniza/60 focus:border-salvia focus:ring-2 focus:ring-salvia/40 focus:outline-none';
@endphp

<div>
    <h1 class="font-display text-4xl leading-tight uppercase">Mi cuenta</h1>

    <dl class="mt-5 divide-y divide-carbon-700 rounded-2xl border border-carbon-700 bg-carbon-800 px-5">
        <div class="flex items-baseline justify-between gap-4 py-3.5">
            <dt class="text-sm text-ceniza">Nombre</dt>
            <dd class="truncate font-semibold">{{ $client->nombre }} {{ $client->apellido }}</dd>
        </div>
        <div class="flex items-baseline justify-between gap-4 py-3.5">
            <dt class="text-sm text-ceniza">DNI</dt>
            <dd class="font-semibold tabular-nums">{{ $client->dni }}</dd>
        </div>
        <div class="flex items-baseline justify-between gap-4 py-3.5">
            <dt class="text-sm text-ceniza">Email</dt>
            <dd class="truncate font-semibold">{{ $client->email }}</dd>
        </div>
    </dl>
    <p class="mt-2 text-sm text-ceniza">¿Algún dato está mal? Avisá en recepción.</p>

    <h2 class="mt-9 font-display text-2xl uppercase">Cambiar contraseña</h2>

    @if ($saved)
        <p role="status" class="mt-3 rounded-2xl border border-salvia/40 bg-salvia/10 px-4 py-3 text-sm text-niebla">
            Listo: tu contraseña quedó cambiada.
        </p>
    @endif

    <form wire:submit="updatePassword" class="mt-4 space-y-5">
        <div>
            <label for="current_password" class="text-sm font-semibold">Contraseña actual</label>
            <input id="current_password" type="password" wire:model="current_password" required autocomplete="current-password" class="{{ $campo }}">
            @unless ($client->hasCustomPassword())
                <p class="mt-2 text-sm text-ceniza">Todavía es tu DNI.</p>
            @endunless
            @error('current_password')
                <p class="mt-2 text-sm text-oxido-light">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="text-sm font-semibold">Contraseña nueva</label>
            <input id="password" type="password" wire:model="password" required minlength="6" autocomplete="new-password" class="{{ $campo }}">
            <p class="mt-2 text-sm text-ceniza">Al menos 6 caracteres.</p>
            @error('password')
                <p class="mt-2 text-sm text-oxido-light">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="text-sm font-semibold">Repetí la contraseña nueva</label>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" required autocomplete="new-password" class="{{ $campo }}">
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            class="w-full cursor-pointer rounded-full bg-salvia px-8 py-4 text-sm font-bold tracking-widest text-carbon uppercase transition hover:bg-salvia-light focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-salvia-light disabled:opacity-60"
        >
            Guardar contraseña
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-10">
        @csrf
        <button
            type="submit"
            class="w-full cursor-pointer rounded-full border border-carbon-700 px-8 py-4 text-sm font-bold tracking-widest text-ceniza uppercase transition hover:border-oxido hover:text-oxido"
        >
            Cerrar sesión
        </button>
    </form>
</div>
