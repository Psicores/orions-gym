{{--
    Layout del portal del socio (/mi-perfil): pensado 100 % para el celular.

    Arriba, el logo y el acceso a "Mi cuenta"; abajo, fija, la barra de navegación con Inicio,
    Escanear QR (botón central) e Historial. Lo usan los componentes Livewire Member* con
    #[Layout('layouts::member')]; $slot es la pantalla y $title su título.
--}}
@php
    // Enlaces de la barra inferior, en el orden en que se muestran.
    $navegacion = [
        ['ruta' => 'member.dashboard', 'texto' => 'Inicio', 'icono' => 'heroicon-o-home'],
        ['ruta' => 'member.scan', 'texto' => 'Escanear QR', 'icono' => 'heroicon-o-qr-code', 'destacado' => true],
        ['ruta' => 'member.history', 'texto' => 'Historial', 'icono' => 'heroicon-o-clock'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-carbon">
    <head>
        @include('partials.member-head', ['title' => $title ?? 'Mi perfil'])
    </head>
    <body class="bg-carbon font-sans text-niebla antialiased selection:bg-oxido selection:text-carbon">
        <div class="mx-auto flex min-h-dvh max-w-md flex-col">
            <header class="flex items-center justify-between px-5 pt-[calc(env(safe-area-inset-top)+1rem)] pb-2">
                <a href="{{ route('member.dashboard') }}" wire:navigate class="flex items-center gap-3">
                    <img src="{{ asset('images/icons/icon-192.png') }}" alt="" width="40" height="40" class="size-10 rounded-xl">
                    <span class="font-display text-2xl tracking-wide uppercase">Orions</span>
                </a>

                <a
                    href="{{ route('member.account') }}"
                    wire:navigate
                    aria-label="Mi cuenta"
                    @if (request()->routeIs('member.account')) aria-current="page" @endif
                    @class([
                        'flex size-11 items-center justify-center rounded-full border transition',
                        'border-salvia text-salvia-light' => request()->routeIs('member.account'),
                        'border-carbon-700 text-ceniza hover:text-niebla' => ! request()->routeIs('member.account'),
                    ])
                >
                    <x-heroicon-o-user class="size-5" />
                </a>
            </header>

            {{-- El margen inferior deja lugar a la barra fija para que no tape el contenido. --}}
            <main class="flex-1 px-5 pt-4 pb-[calc(env(safe-area-inset-bottom)+7.5rem)]">
                {{ $slot }}
            </main>
        </div>

        <nav aria-label="Portal del socio" class="fixed inset-x-0 bottom-0 z-10 border-t border-carbon-700 bg-carbon-800/95 pb-[env(safe-area-inset-bottom)] backdrop-blur">
            <ul class="mx-auto grid max-w-md grid-cols-3">
                @foreach ($navegacion as $enlace)
                    @php($activo = request()->routeIs($enlace['ruta']))
                    <li>
                        <a
                            href="{{ route($enlace['ruta']) }}"
                            wire:navigate
                            @if ($activo) aria-current="page" @endif
                            @class([
                                'flex h-18 flex-col items-center justify-end gap-1 pb-2.5 text-[0.7rem] font-semibold tracking-wide transition',
                                'text-salvia-light' => $activo,
                                'text-ceniza hover:text-niebla' => ! $activo,
                            ])
                        >
                            @if ($enlace['destacado'] ?? false)
                                {{-- Botón central: sobresale de la barra porque es la acción de todos los días. --}}
                                <span @class([
                                    '-mt-7 flex size-14 items-center justify-center rounded-full text-carbon shadow-lg shadow-black/40 ring-4 ring-carbon',
                                    'bg-salvia-light' => $activo,
                                    'bg-salvia' => ! $activo,
                                ])>
                                    @svg($enlace['icono'], 'size-7')
                                </span>
                            @else
                                @svg($enlace['icono'], 'size-6')
                            @endif
                            <span>{{ $enlace['texto'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </body>
</html>
