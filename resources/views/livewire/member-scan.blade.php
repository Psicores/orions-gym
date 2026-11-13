{{--
    Escanear QR (ver App\Livewire\MemberScan).

    El componente Alpine "escanerQr" (resources/js/app.js) abre la cámara dentro de la página y,
    al leer un código, llama a $wire.procesarIngreso(texto). Estados de Alpine: inactivo,
    iniciando, escaneando y procesando.
--}}
<div
    x-data="escanerQr"
    x-on:ingreso-registrado.window="sonar(true)"
    x-on:ingreso-rechazado.window="sonar(false)"
>
    <h1 class="font-display text-4xl leading-tight uppercase">Escanear QR</h1>

    {{-- Resultado del último escaneo. Se oculta mientras la cámara está abierta. --}}
    <div x-show="estado === 'inactivo'" aria-live="assertive">
        @if ($resultado === 'ok' || $resultado === 'repetido')
            <section class="mt-6 rounded-3xl border border-salvia bg-salvia/15 px-6 py-8 text-center">
                <span class="mx-auto flex size-20 items-center justify-center rounded-full bg-salvia text-carbon">
                    <x-heroicon-o-check class="size-11" />
                </span>
                <p class="mt-5 font-display text-3xl text-salvia-light uppercase">
                    {{ $resultado === 'ok' ? 'Ingreso registrado' : 'Ya estabas adentro' }}
                </p>
                <p class="mt-2 text-niebla">{{ $mensaje }}</p>
                @if ($creditos !== null)
                    <p class="mt-5 text-sm tracking-widest text-ceniza uppercase">
                        Te quedan <span class="font-display text-2xl text-salvia-light tabular-nums">{{ $creditos }}</span> créditos
                    </p>
                @endif
            </section>
        @elseif ($resultado === 'error')
            <section role="alert" class="mt-6 rounded-3xl border border-red-500 bg-red-500/15 px-6 py-8 text-center">
                <span class="mx-auto flex size-20 items-center justify-center rounded-full bg-red-500 text-white">
                    <x-heroicon-o-x-mark class="size-11" />
                </span>
                <p class="mt-5 font-display text-3xl text-red-300 uppercase">No pudiste ingresar</p>
                <p class="mt-2 text-niebla">{{ $mensaje }}</p>
            </section>
        @else
            <section class="mt-6 rounded-3xl border border-carbon-700 bg-carbon-800 px-6 py-10 text-center">
                <span class="mx-auto flex size-20 items-center justify-center rounded-3xl bg-salvia/15 text-salvia-light">
                    <x-heroicon-o-qr-code class="size-10" />
                </span>
                <p class="mt-5 text-niebla/90">Apuntá la cámara al código QR del mostrador para registrar tu ingreso.</p>
            </section>
        @endif
    </div>

    {{-- Visor de la cámara. wire:ignore: Livewire no lo toca, así el video no se corta al actualizar la pantalla. --}}
    <div x-show="estado !== 'inactivo'" x-cloak class="mt-6">
        <div wire:ignore class="overflow-hidden rounded-3xl border border-salvia/50 bg-black">
            <div id="lector-qr" class="min-h-64"></div>
        </div>
        <p class="mt-3 text-center text-sm text-ceniza" x-text="estado === 'procesando' ? 'Registrando tu ingreso…' : 'Buscando el código…'"></p>
    </div>

    <p x-show="error" x-cloak x-text="error" role="alert" class="mt-4 rounded-2xl border border-oxido/50 bg-oxido/10 px-4 py-3 text-sm text-niebla"></p>

    <div class="mt-6 space-y-3">
        <button
            type="button"
            x-show="estado === 'inactivo'"
            @click="iniciar()"
            class="w-full cursor-pointer rounded-full bg-salvia px-8 py-4 text-sm font-bold tracking-widest text-carbon uppercase transition hover:bg-salvia-light focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-salvia-light"
        >
            {{ $resultado === null ? 'Escanear' : 'Escanear de nuevo' }}
        </button>

        <button
            type="button"
            x-show="estado === 'escaneando'"
            x-cloak
            @click="cambiarCamara()"
            class="w-full cursor-pointer rounded-full border border-carbon-700 px-8 py-3.5 text-sm font-bold tracking-widest text-niebla uppercase transition hover:border-salvia"
        >
            Cambiar de cámara
        </button>

        <button
            type="button"
            x-show="estado === 'iniciando' || estado === 'escaneando'"
            x-cloak
            @click="detener()"
            class="w-full cursor-pointer rounded-full border border-carbon-700 px-8 py-3.5 text-sm font-bold tracking-widest text-ceniza uppercase transition hover:text-niebla"
        >
            Cancelar
        </button>
    </div>
</div>
