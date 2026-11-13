{{--
    Curev Hue: futura escuela de windsurf. Por ahora es una sola pantalla con el video de fondo
    y el aviso "Próximamente" en el centro. Mismo diseño que el inicio (ver welcome.blade.php).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-carbon">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Curev Hue: escuela de windsurf. Próximamente.">
        <meta name="theme-color" content="#161817">

        <title>Curev Hue · Próximamente</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-carbon font-sans text-niebla antialiased selection:bg-oxido selection:text-carbon">
        <main>
            <section class="relative flex h-screen min-h-[36rem] items-center justify-center overflow-hidden">
                <video
                    autoplay muted loop playsinline preload="metadata"
                    aria-hidden="true"
                    class="absolute inset-0 size-full object-cover"
                >
                    <source src="{{ asset('images/windsurf.mp4') }}" type="video/mp4">
                </video>
                {{-- Oscurece el video para que el texto se lea. --}}
                <div class="absolute inset-0 bg-carbon/45"></div>
                <div class="absolute inset-x-0 bottom-0 h-2/5 bg-linear-to-b from-transparent to-carbon"></div>

                {{-- Navegación: volver al inicio a la izquierda y el logo en el centro. --}}
                <header class="absolute inset-x-0 top-0">
                    <nav aria-label="Principal" class="mx-auto grid max-w-7xl grid-cols-[1fr_auto_1fr] items-center gap-4 px-5 py-5 sm:px-8">
                        <a
                            href="{{ route('inicio') }}"
                            class="justify-self-start text-sm font-semibold tracking-widest text-niebla uppercase transition hover:text-oxido focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-oxido"
                        >
                            ← Orions
                        </a>

                        <a href="{{ route('inicio') }}" aria-label="Orions, inicio" class="justify-self-center">
                            {{-- Mismo tratamiento del logo que en el inicio: el header no debe tener z-index ni opacidad propios. --}}
                            <img src="{{ asset('images/logo1.jpg') }}" alt="Orions" width="64" height="72" class="h-14 w-auto mix-blend-screen invert sm:h-18">
                        </a>
                    </nav>
                </header>

                <div class="relative mx-auto max-w-5xl px-5 text-center">
                    <p class="mb-5 text-xs font-semibold tracking-[0.5em] text-oxido uppercase sm:text-sm">Curev Hue · Escuela de windsurf</p>
                    <h1 class="font-display text-6xl leading-[0.9] text-balance uppercase sm:text-8xl lg:text-9xl">
                        Próximamente
                    </h1>
                </div>
            </section>
        </main>
    </body>
</html>
