{{-- Mi cuenta (ver App\Livewire\MemberAccount). Recibe $client. --}}
@php
    // Clases comunes de los campos del formulario.
    $campo = 'mt-2 w-full rounded-xl border border-carbon-700 bg-carbon-800 px-4 py-3.5 text-base text-niebla placeholder:text-ceniza/60 focus:border-salvia focus:ring-2 focus:ring-salvia/40 focus:outline-none';
@endphp

<div>
    <h1 class="font-display text-4xl leading-tight uppercase">Mi cuenta</h1>

    {{-- Mientras la contraseña sea el DNI, el resto del portal está cerrado (ver EnsureMemberHasOwnPassword). --}}
    @unless ($client->hasCustomPassword())
        <p role="alert" class="mt-4 rounded-2xl border border-oxido/50 bg-oxido/10 px-4 py-3 text-sm text-niebla">
            <span class="font-semibold">Antes de seguir, elegí una contraseña propia.</span>
            Tu contraseña todavía es tu DNI y cualquiera que lo conozca podría entrar a tu cuenta.
        </p>
    @endunless

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

    <h2 id="celular" class="mt-9 scroll-mt-6 font-display text-2xl uppercase">Tu celular</h2>
    <p class="mt-2 text-sm text-ceniza">Lo usamos para avisarte de tu cuenta, solo si lo aceptás abajo.</p>

    @if ($celSaved)
        <p role="status" class="mt-3 rounded-2xl border border-salvia/40 bg-salvia/10 px-4 py-3 text-sm text-niebla">
            Listo: guardamos tu celular.
        </p>
    @elseif (! $client->hasValidCel())
        <p class="mt-3 rounded-2xl border border-oxido/50 bg-oxido/10 px-4 py-3 text-sm text-niebla">
            {{ filled($client->cel) ? 'Revisá tu celular: el que tenemos no parece válido.' : 'Todavía no tenemos tu celular.' }}
        </p>
    @endif

    <form wire:submit="updateCel" class="mt-4">
        <label for="cel" class="text-sm font-semibold">Celular</label>
        <input id="cel" type="tel" wire:model="cel" required autocomplete="tel" inputmode="tel" placeholder="280 15 412-3456" class="{{ $campo }}">
        <p class="mt-2 text-sm text-ceniza">Con la característica, como lo marcarías desde otra ciudad.</p>
        @error('cel')
            <p class="mt-2 text-sm text-oxido-light">{{ $message }}</p>
        @enderror

        <button
            type="submit"
            wire:loading.attr="disabled"
            class="mt-4 w-full cursor-pointer rounded-full border border-salvia px-8 py-4 text-sm font-bold tracking-widest text-niebla uppercase transition hover:bg-salvia hover:text-carbon focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-salvia-light disabled:opacity-60"
        >
            Guardar celular
        </button>
    </form>

    <h2 class="mt-9 font-display text-2xl uppercase">Avisos</h2>
    <p class="mt-2 text-sm text-ceniza">Elegí qué querés recibir. Lo podés cambiar cuando quieras.</p>

    @if ($consentsSaved)
        <p role="status" class="mt-3 rounded-2xl border border-salvia/40 bg-salvia/10 px-4 py-3 text-sm text-niebla">
            Listo: guardamos tus preferencias.
        </p>
    @endif

    <form wire:submit="updateConsents" class="mt-4">
        <div class="divide-y divide-carbon-700 rounded-2xl border border-carbon-700 bg-carbon-800 px-5">
            <label class="flex cursor-pointer items-start gap-3 py-4">
                <input type="checkbox" wire:model="acepta_avisos_whatsapp" class="mt-1 size-5 shrink-0 cursor-pointer rounded border-carbon-700 bg-carbon accent-salvia">
                <span>
                    <span class="block font-semibold">Avisos de mi cuenta por WhatsApp</span>
                    <span class="mt-0.5 block text-sm text-ceniza">Vencimiento de la cuota, pago recibido y créditos agotados.</span>
                </span>
            </label>
            <label class="flex cursor-pointer items-start gap-3 py-4">
                <input type="checkbox" wire:model="acepta_novedades" class="mt-1 size-5 shrink-0 cursor-pointer rounded border-carbon-700 bg-carbon accent-salvia">
                <span>
                    <span class="block font-semibold">Novedades del gimnasio</span>
                    <span class="mt-0.5 block text-sm text-ceniza">Promociones, eventos y novedades.</span>
                </span>
            </label>
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            class="mt-4 w-full cursor-pointer rounded-full border border-salvia px-8 py-4 text-sm font-bold tracking-widest text-niebla uppercase transition hover:bg-salvia hover:text-carbon focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-salvia-light disabled:opacity-60"
        >
            Guardar avisos
        </button>
    </form>

    <h2 class="mt-9 font-display text-2xl uppercase">Cambiar contraseña</h2>

    @if ($saved)
        <p role="status" class="mt-3 rounded-2xl border border-salvia/40 bg-salvia/10 px-4 py-3 text-sm text-niebla">
            Listo: tu contraseña quedó cambiada.
        </p>
    @endif

    <form wire:submit="updatePassword" class="mt-4 space-y-5">
        <div>
            <label for="current_password" class="text-sm font-semibold">Contraseña actual</label>
            @include('partials.password-input', ['id' => 'current_password', 'model' => 'current_password', 'autocomplete' => 'current-password'])
            @unless ($client->hasCustomPassword())
                <p class="mt-2 text-sm text-ceniza">Todavía es tu DNI.</p>
            @endunless
            @error('current_password')
                <p class="mt-2 text-sm text-oxido-light">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="text-sm font-semibold">Contraseña nueva</label>
            @include('partials.password-input', ['id' => 'password', 'model' => 'password', 'autocomplete' => 'new-password', 'minlength' => 8])
            <p class="mt-2 text-sm text-ceniza">Al menos 8 caracteres, con letras y números.</p>
            @error('password')
                <p class="mt-2 text-sm text-oxido-light">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="text-sm font-semibold">Repetí la contraseña nueva</label>
            @include('partials.password-input', ['id' => 'password_confirmation', 'model' => 'password_confirmation', 'autocomplete' => 'new-password'])
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
