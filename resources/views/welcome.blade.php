@php
    // DATOS DE EJEMPLO: reemplazar por los reales del gimnasio antes de publicar.
    $horarios = [
        ['dias' => 'Lunes a viernes', 'horas' => '06:00 a 23:00'],
        ['dias' => 'Sábados', 'horas' => '10:00 a 13:00 · 19:00 a 21:00'],
        ['dias' => 'Domingos y feriados', 'horas' => 'Cerrado'],
    ];
    $direccion = 'Dirección del gimnasio (a completar)';
    // URL "embed" de Google Maps (Compartir > Insertar un mapa). Con null se muestra el contenedor vacío.
    $mapaEmbedUrl = null;
    // OPINIONES SIMULADAS: reemplazar por reseñas reales de Google antes de publicar.
    $opiniones = [
        ['autor' => 'Socio de ejemplo 1', 'texto' => 'El mejor ambiente para entrenar. Los profes están siempre encima de la técnica.'],
        ['autor' => 'Socio de ejemplo 2', 'texto' => 'Buen equipamiento, buena música y cero careta. Vengo hace un año y no lo cambio.'],
        ['autor' => 'Socio de ejemplo 3', 'texto' => 'Arranqué de cero y me acompañaron en cada paso. Se nota la comunidad.'],
        ['autor' => 'Socio de ejemplo 4', 'texto' => 'Horarios amplios y planes claros. Entrenar acá es otra cosa.'],
        ['autor' => 'Socio de ejemplo 5', 'texto' => 'Lugar amplio, limpio y con hierro de verdad. Recomendadísimo.'],
    ];
    // Demora escalonada con la que entra cada tarjeta de plan (clases completas para que Tailwind las detecte).
    $demoras = ['delay-0', 'delay-150', 'delay-300'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth bg-carbon">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Orions: gimnasio de fuerza y comunidad. Conocé los planes, los horarios y dónde encontrarnos.">
        <meta name="theme-color" content="#161817">

        <title>Orions · Gimnasio</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        x-data="{ planes: false, novedades: false }"
        @keydown.escape.window="planes = false; novedades = false"
        :class="{ 'overflow-hidden': planes || novedades }"
        class="bg-carbon font-sans text-niebla antialiased selection:bg-oxido selection:text-carbon"
    >
        <main>
            {{-- ===================== HERO (primer video) ===================== --}}
            <section class="relative flex h-screen min-h-[36rem] items-center justify-center overflow-hidden">
                <video
                    autoplay muted loop playsinline preload="metadata"
                    aria-hidden="true"
                    class="absolute inset-0 size-full object-cover"
                >
                    <source src="{{ asset('images/Video1.mp4') }}" type="video/mp4">
                </video>
                {{-- Oscurece el video para que el texto se lea y lo funde con el fondo en la parte inferior. --}}
                <div class="absolute inset-0 bg-carbon/45"></div>
                <div class="absolute inset-x-0 bottom-0 h-2/5 bg-linear-to-b from-transparent to-carbon"></div>

                {{-- ===================== NAVEGACIÓN (transparente, flotando sobre el video) ===================== --}}
                <header class="absolute inset-x-0 top-0">
                    <nav aria-label="Principal" class="mx-auto grid max-w-7xl grid-cols-[1fr_auto_1fr] items-center gap-4 px-5 py-5 sm:px-8">
                        <div class="flex items-center gap-5 justify-self-start text-sm font-semibold tracking-widest uppercase sm:gap-8">
                            <button
                                type="button"
                                @click="planes = true"
                                aria-haspopup="dialog"
                                class="cursor-pointer tracking-widest text-niebla uppercase transition hover:text-oxido focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-oxido"
                            >
                                Planes
                            </button>
                            {{-- Novedades: solo aparece si hay anuncios vigentes. El punto naranja avisa que hay algo para ver. --}}
                            @if ($anuncios->isNotEmpty())
                                <button
                                    type="button"
                                    @click="novedades = true"
                                    aria-haspopup="dialog"
                                    class="flex cursor-pointer items-center gap-2 tracking-widest text-niebla uppercase transition hover:text-oxido focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-oxido"
                                >
                                    Novedades
                                    <span aria-hidden="true" class="size-2 rounded-full bg-oxido"></span>
                                </button>
                            @endif
                            {{-- Curev Hue: futura escuela de windsurf. Lleva a su pantalla de "Próximamente". --}}
                            <a
                                href="{{ route('curev-hue') }}"
                                title="Escuela de windsurf · Próximamente"
                                class="hidden items-center gap-2 text-niebla uppercase transition hover:text-oxido focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-oxido sm:flex"
                            >
                                Curev Hue
                                <span class="rounded-full border border-salvia/60 px-2 py-0.5 text-[0.6rem] tracking-wider text-salvia-light">Pronto</span>
                            </a>
                        </div>

                        <a href="{{ route('inicio') }}" aria-label="Orions, inicio" class="justify-self-center">
                            {{-- El logo es negro sobre blanco: se invierte y se funde con el video para verse claro y sin recuadro.
                                 Para que la fusión funcione, el header no debe tener z-index ni opacidad propios. --}}
                            <img src="{{ asset('images/logo1.jpg') }}" alt="Orions" width="64" height="72" class="h-14 w-auto mix-blend-screen invert sm:h-18">
                        </a>

                        <a
                            href="{{ route('login') }}"
                            class="justify-self-end rounded-full border border-oxido px-5 py-2 text-sm font-semibold tracking-widest text-niebla uppercase transition hover:bg-oxido hover:text-carbon focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-oxido"
                        >
                            Socios
                        </a>
                    </nav>
                </header>

                <div class="relative mx-auto max-w-5xl px-5 text-center">
                    <p class="mb-5 text-xs font-semibold tracking-[0.5em] text-oxido uppercase sm:text-sm">Gimnasio · Fuerza · Comunidad</p>
                    <h1 class="font-display text-6xl leading-[0.9] text-balance uppercase sm:text-8xl lg:text-9xl">
                        Fierros <span class="text-salvia-light">y constancia</span>
                    </h1>
                    <p class="mx-auto mt-6 max-w-xl text-base text-pretty text-niebla/80 sm:text-lg">
                        Entrenamiento serio, sin vueltas. Vení a levantar pesado con gente que empuja para el mismo lado.
                    </p>
                    <div class="mt-9 flex flex-wrap items-center justify-center gap-4">
                        <button
                            type="button"
                            @click="planes = true"
                            aria-haspopup="dialog"
                            class="cursor-pointer rounded-full bg-oxido px-8 py-3.5 text-sm font-bold tracking-widest text-carbon uppercase shadow-lg shadow-oxido/30 transition hover:-translate-y-0.5 hover:bg-oxido-light focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-oxido"
                        >
                            Ver planes
                        </button>
                        <a href="#comunidad" class="rounded-full px-6 py-3.5 text-sm font-semibold tracking-widest text-niebla uppercase transition hover:text-salvia-light">
                            Conocé el lugar ↓
                        </a>
                    </div>
                </div>
            </section>

            {{-- ===================== COMUNIDAD (segundo video como separador) ===================== --}}
            <section id="comunidad" class="mx-auto max-w-7xl scroll-mt-10 px-5 py-20 sm:px-8 sm:py-28">
                <div class="grid items-end gap-10 lg:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.4em] text-oxido uppercase">La comunidad</p>
                        <h2 class="mt-4 font-display text-5xl leading-none uppercase sm:text-7xl">Nadie entrena solo</h2>
                    </div>
                    <p class="max-w-lg text-pretty text-ceniza lg:justify-self-end">
                        Orions es un lugar para venir a trabajar: barras, discos, kettlebells y profes que corrigen.
                        Cada uno a su ritmo y con su objetivo, pero nunca solo. Lo que se construye acá, se construye entre todos.
                    </p>
                </div>

                <div class="relative mt-12 overflow-hidden rounded-3xl border border-carbon-700">
                    <video
                        autoplay muted loop playsinline preload="none"
                        aria-hidden="true"
                        class="h-[55vh] min-h-80 w-full object-cover"
                    >
                        <source src="{{ asset('images/video2.mp4') }}" type="video/mp4">
                    </video>
                    <div class="absolute inset-0 bg-linear-to-t from-carbon via-carbon/20 to-transparent"></div>
                    <p class="absolute inset-x-0 bottom-0 p-6 font-display text-3xl uppercase sm:p-10 sm:text-5xl">
                        Venís por el físico. <span class="text-salvia-light">Te quedás por la gente.</span>
                    </p>
                </div>

                {{-- Grilla asimétrica de instalaciones. --}}
                <div class="mt-5 grid gap-5 sm:grid-cols-3">
                    <img src="{{ asset('images/image4.jpg') }}" alt="Barra olímpica cargada sobre el piso del gimnasio" loading="lazy" class="h-72 w-full rounded-3xl object-cover sm:col-span-2 sm:h-96">
                    <img src="{{ asset('images/image3.jpg') }}" alt="Kettlebell junto a una barra con discos" loading="lazy" class="h-72 w-full rounded-3xl object-cover sm:h-96">
                </div>
            </section>

            {{-- ===================== OPINIONES (cinta infinita) ===================== --}}
            <section aria-labelledby="titulo-opiniones" class="border-y border-carbon-700 bg-carbon-800/40 py-16">
                <div class="mx-auto flex max-w-7xl flex-wrap items-end justify-between gap-4 px-5 sm:px-8">
                    <h2 id="titulo-opiniones" class="font-display text-4xl uppercase sm:text-5xl">Lo que dicen en Google</h2>
                    <p class="flex items-center gap-2 text-sm text-ceniza">
                        <span class="text-lg tracking-widest text-oxido" aria-hidden="true">★★★★★</span>
                        Opiniones de socios
                    </p>
                </div>

                <div class="mt-10 overflow-hidden mask-x-from-90% motion-reduce:overflow-x-auto">
                    {{-- La lista va dos veces seguidas para que el loop no tenga corte; la segunda copia es solo visual. --}}
                    <div class="flex w-max animate-marquee hover:[animation-play-state:paused] motion-reduce:animate-none">
                        @foreach ([false, true] as $esCopia)
                            <ul class="flex gap-5 pr-5" @if ($esCopia) aria-hidden="true" @endif>
                                @foreach ($opiniones as $opinion)
                                    <li class="w-80 shrink-0 rounded-2xl border border-carbon-700 bg-carbon p-6">
                                        <p class="tracking-widest text-oxido" aria-label="5 de 5 estrellas">★★★★★</p>
                                        <blockquote class="mt-3 text-sm leading-relaxed text-niebla/90">“{{ $opinion['texto'] }}”</blockquote>
                                        <p class="mt-4 text-xs font-semibold tracking-widest text-ceniza uppercase">{{ $opinion['autor'] }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- ===================== HORARIOS Y UBICACIÓN ===================== --}}
            <section id="ubicacion" class="mx-auto grid max-w-7xl scroll-mt-10 gap-10 px-5 py-20 sm:px-8 sm:py-28 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <p class="text-xs font-semibold tracking-[0.4em] text-oxido uppercase">Dónde y cuándo</p>
                    <h2 class="mt-4 font-display text-5xl leading-none uppercase sm:text-6xl">Horarios</h2>

                    <dl class="mt-8 divide-y divide-carbon-700 border-y border-carbon-700">
                        @foreach ($horarios as $horario)
                            <div class="flex items-baseline justify-between gap-4 py-4">
                                <dt class="text-ceniza">{{ $horario['dias'] }}</dt>
                                <dd class="font-semibold tabular-nums">{{ $horario['horas'] }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <h3 class="mt-10 font-display text-2xl uppercase">Ubicación</h3>
                    <address class="mt-2 text-ceniza not-italic">{{ $direccion }}</address>

                    <button
                        type="button"
                        @click="planes = true"
                        aria-haspopup="dialog"
                        class="mt-8 cursor-pointer rounded-full bg-salvia px-7 py-3 text-sm font-bold tracking-widest text-carbon uppercase transition hover:bg-salvia-light focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-salvia-light"
                    >
                        Elegí tu plan
                    </button>
                </div>

                {{-- Contenedor del mapa: con la URL cargada muestra Google Maps oscurecido con filtros. --}}
                <div class="relative min-h-96 overflow-hidden rounded-3xl border border-carbon-700 bg-carbon-800 lg:col-span-3">
                    @if ($mapaEmbedUrl)
                        <iframe
                            src="{{ $mapaEmbedUrl }}"
                            title="Mapa de ubicación de Orions"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            class="absolute inset-0 size-full border-0 contrast-90 grayscale invert-[.92] hue-rotate-180"
                        ></iframe>
                    @else
                        <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 text-ceniza">
                            <svg class="size-10 text-salvia" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                            </svg>
                            <p class="text-sm tracking-widest uppercase">Mapa próximamente</p>
                        </div>
                    @endif
                </div>
            </section>
        </main>

        <footer class="border-t border-carbon-700">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-8 text-sm text-ceniza sm:px-8">
                <a href="{{ route('curev-hue') }}" class="transition hover:text-niebla">Curev Hue · escuela de windsurf · <span class="text-salvia-light">próximamente</span></a>
                <a href="{{ route('login') }}" class="transition hover:text-niebla">Ingreso de socios</a>
            </div>

            {{-- Última línea: copyright a la izquierda y firma de la agencia a la derecha (centrados en el celular). --}}
            <div class="border-t border-carbon-700/60">
                <div class="mx-auto flex max-w-7xl flex-col items-center gap-2 px-5 py-5 text-xs text-ceniza sm:flex-row sm:justify-between sm:px-8">
                    <p>© {{ date('Y') }} Orions</p>
                    @include('partials.devkore-credit')
                </div>
            </div>
        </footer>

        {{-- ===================== PLANES: vienen a la pantalla, la pantalla no se mueve ===================== --}}
        <div
            x-show="planes"
            x-transition:enter="transition-opacity duration-300 ease-out"
            x-transition:enter-start="opacity-0"
            x-transition:leave="transition-opacity delay-200 duration-300 ease-in"
            x-transition:leave-end="opacity-0"
            x-trap="planes"
            x-cloak
            role="dialog"
            aria-modal="true"
            aria-labelledby="titulo-planes"
            class="fixed inset-0 z-50 overflow-x-hidden overflow-y-auto bg-carbon/85 backdrop-blur-md [&[x-cloak]]:hidden"
        >
            {{-- Clic fuera de las tarjetas: cierra. --}}
            <div class="flex min-h-full flex-col items-center justify-center px-5 py-20" @click.self="planes = false">
                <button
                    type="button"
                    @click="planes = false"
                    class="absolute top-5 right-5 flex size-11 cursor-pointer items-center justify-center rounded-full border border-carbon-700 text-niebla transition hover:border-oxido hover:text-oxido focus-visible:outline-2 focus-visible:outline-oxido sm:top-8 sm:right-8"
                >
                    <span class="sr-only">Cerrar planes</span>
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>

                <div class="text-center">
                    <p class="text-xs font-semibold tracking-[0.4em] text-oxido uppercase">Cuota mensual</p>
                    <h2 id="titulo-planes" class="mt-3 font-display text-5xl uppercase sm:text-7xl">Planes</h2>
                </div>

                <div class="mt-10 grid w-full max-w-6xl gap-6 md:grid-cols-3">
                    @forelse ($planes as $plan)
                        @php
                            // 999 créditos es la convención del sistema para un plan libre.
                            $esLibre = $plan->creditos_otorgados >= 999;
                        @endphp
                        {{-- Cada tarjeta entra deslizándose desde la derecha, una detrás de otra, y sale hacia la izquierda. --}}
                        <article
                            x-show="planes"
                            x-transition:enter="transition duration-700 ease-out motion-reduce:duration-0 {{ $demoras[$loop->index % 3] }}"
                            x-transition:enter-start="translate-x-[100vw] opacity-0"
                            x-transition:enter-end="translate-x-0 opacity-100"
                            x-transition:leave="transition duration-300 ease-in motion-reduce:duration-0"
                            x-transition:leave-start="translate-x-0 opacity-100"
                            x-transition:leave-end="-translate-x-24 opacity-0"
                            class="group flex flex-col rounded-3xl border border-carbon-700 bg-carbon-800 p-8 shadow-2xl shadow-oxido/10 hover:border-oxido/60 hover:shadow-oxido/25"
                        >
                            <div class="flex size-14 items-center justify-center rounded-2xl bg-salvia/15 text-salvia-light transition-colors group-hover:bg-oxido/15 group-hover:text-oxido">
                                @if ($esLibre)
                                    {{-- Infinito: acceso libre. --}}
                                    <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.178 8c5.096 0 5.096 8 0 8-5.095 0-7.133-8-12.739-8-4.585 0-4.585 8 0 8 5.606 0 7.644-8 12.739-8Z" />
                                    </svg>
                                @elseif ($loop->first)
                                    {{-- Rayo. --}}
                                    <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m3.750 13.500 10.500-11.250L12 10.500h8.250L9.750 21.750 12 13.500H3.750Z" />
                                    </svg>
                                @else
                                    {{-- Llama. --}}
                                    <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.250 8.250 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.210 8.210 0 0 0 3 2.480Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.750 3.750 0 0 0 .495-7.468 5.990 5.990 0 0 0-1.925 3.547 5.975 5.975 0 0 1-2.133-1.001A3.750 3.750 0 0 0 12 18Z" />
                                    </svg>
                                @endif
                            </div>

                            <h3 class="mt-6 font-display text-3xl uppercase">{{ $plan->nombre }}</h3>
                            <p class="mt-1 text-sm text-ceniza">
                                {{ $esLibre ? 'Entrená todas las veces que quieras' : $plan->creditos_otorgados.' clases por mes' }}
                            </p>

                            <p class="mt-8 flex items-baseline gap-2">
                                <span class="font-display text-5xl text-niebla tabular-nums">${{ number_format((float) $plan->precio, 0, ',', '.') }}</span>
                                <span class="text-sm text-ceniza">/ mes</span>
                            </p>

                            <a
                                href="#ubicacion"
                                @click="planes = false"
                                class="mt-8 rounded-full border border-salvia px-6 py-3 text-center text-sm font-bold tracking-widest text-niebla uppercase transition group-hover:border-oxido group-hover:bg-oxido group-hover:text-carbon focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-oxido"
                            >
                                Lo quiero
                            </a>
                        </article>
                    @empty
                        <p class="text-center text-ceniza md:col-span-3">Estamos armando los planes. Pasá por el gimnasio y te contamos.</p>
                    @endforelse
                </div>

                <p class="mt-8 text-center text-xs text-ceniza">Acercate al gimnasio para inscribirte. Esc o clic afuera para cerrar.</p>
            </div>
        </div>

        {{-- ===================== NOVEDADES: mismos anuncios que ven los socios, con el estilo del panel de planes ===================== --}}
        @if ($anuncios->isNotEmpty())
            <div
                x-show="novedades"
                x-transition:enter="transition-opacity duration-300 ease-out"
                x-transition:enter-start="opacity-0"
                x-transition:leave="transition-opacity delay-200 duration-300 ease-in"
                x-transition:leave-end="opacity-0"
                x-trap="novedades"
                x-cloak
                role="dialog"
                aria-modal="true"
                aria-labelledby="titulo-novedades"
                class="fixed inset-0 z-50 overflow-x-hidden overflow-y-auto bg-carbon/85 backdrop-blur-md [&[x-cloak]]:hidden"
            >
                {{-- Clic fuera de las tarjetas: cierra. --}}
                <div class="flex min-h-full flex-col items-center justify-center px-5 py-20" @click.self="novedades = false">
                    <button
                        type="button"
                        @click="novedades = false"
                        class="absolute top-5 right-5 flex size-11 cursor-pointer items-center justify-center rounded-full border border-carbon-700 text-niebla transition hover:border-oxido hover:text-oxido focus-visible:outline-2 focus-visible:outline-oxido sm:top-8 sm:right-8"
                    >
                        <span class="sr-only">Cerrar novedades</span>
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
                        </svg>
                    </button>

                    <div class="text-center">
                        <p class="text-xs font-semibold tracking-[0.4em] text-oxido uppercase">Lo último del gimnasio</p>
                        <h2 id="titulo-novedades" class="mt-3 font-display text-5xl uppercase sm:text-7xl">Novedades</h2>
                    </div>

                    {{-- Con una o dos novedades, las tarjetas quedan centradas en lugar de pegadas a la izquierda. --}}
                    <div class="mt-10 flex w-full max-w-6xl flex-wrap justify-center gap-6">
                        @foreach ($anuncios as $anuncio)
                            {{-- Cada tarjeta entra deslizándose desde la derecha, una detrás de otra, igual que los planes. --}}
                            <article
                                x-show="novedades"
                                x-transition:enter="transition duration-700 ease-out motion-reduce:duration-0 {{ $demoras[$loop->index % 3] }}"
                                x-transition:enter-start="translate-x-[100vw] opacity-0"
                                x-transition:enter-end="translate-x-0 opacity-100"
                                x-transition:leave="transition duration-300 ease-in motion-reduce:duration-0"
                                x-transition:leave-start="translate-x-0 opacity-100"
                                x-transition:leave-end="-translate-x-24 opacity-0"
                                class="flex w-full flex-col overflow-hidden rounded-3xl border border-carbon-700 bg-carbon-800 shadow-2xl shadow-oxido/10 hover:border-oxido/60 hover:shadow-oxido/25 md:w-[calc((100%-3rem)/3)]"
                            >
                                @if ($anuncio->imageUrl())
                                    <img src="{{ $anuncio->imageUrl() }}" alt="" loading="lazy" class="aspect-2/1 w-full object-cover">
                                @endif

                                <div class="p-7">
                                    <p class="text-[0.65rem] font-bold tracking-[0.3em] text-oxido uppercase">Novedad</p>
                                    <h3 class="mt-2 font-display text-3xl leading-tight uppercase">{{ $anuncio->titulo }}</h3>

                                    @if ($anuncio->safeContent())
                                        <div class="mt-4 space-y-2 text-sm leading-relaxed text-niebla/85 [&_a]:text-salvia-light [&_a]:underline [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5">
                                            {{ $anuncio->safeContent() }}
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <p class="mt-8 text-center text-xs text-ceniza">Esc o clic afuera para cerrar.</p>
                </div>
            </div>
        @endif

        {{-- Alpine.js viene incluido en Livewire (ya instalado por el panel): no hace falta sumarlo aparte. --}}
        @livewireScripts
    </body>
</html>
