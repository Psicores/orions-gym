{{--
    Ingreso único de socios y personal (ver MemberSessionController).
    Diseño oscuro, igual al de la página pública, pensado primero para el celular.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-carbon">
    <head>
        @include('partials.member-head', ['title' => 'Ingresar'])
    </head>
    <body class="bg-carbon font-sans text-niebla antialiased selection:bg-oxido selection:text-carbon">
        <main class="mx-auto flex min-h-dvh max-w-md flex-col justify-center px-6 py-10">
            <a href="{{ route('inicio') }}" aria-label="Orions, inicio" class="mx-auto">
                <img src="{{ asset('images/icons/icon-192.png') }}" alt="Orions" width="96" height="96" class="size-24 rounded-3xl">
            </a>

            <p class="mt-8 text-center text-xs font-semibold tracking-[0.4em] text-oxido uppercase">Portal del socio</p>
            <h1 class="mt-3 text-center font-display text-5xl leading-none uppercase">Ingresar</h1>

            <form method="POST" action="{{ route('login.store') }}" class="mt-10 space-y-5">
                @csrf

                <div>
                    <label for="usuario" class="text-sm font-semibold text-niebla">DNI o email</label>
                    <input
                        id="usuario"
                        name="usuario"
                        type="text"
                        value="{{ old('usuario') }}"
                        required
                        autofocus
                        autocomplete="username"
                        autocapitalize="none"
                        placeholder="Tu DNI, sin puntos"
                        @error('usuario') aria-invalid="true" aria-describedby="usuario-error" @enderror
                        class="mt-2 w-full rounded-xl border border-carbon-700 bg-carbon-800 px-4 py-3.5 text-base text-niebla placeholder:text-ceniza/60 focus:border-salvia focus:ring-2 focus:ring-salvia/40 focus:outline-none"
                    >
                    @error('usuario')
                        <p id="usuario-error" class="mt-2 text-sm text-oxido-light">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="text-sm font-semibold text-niebla">Contraseña</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                        class="mt-2 w-full rounded-xl border border-carbon-700 bg-carbon-800 px-4 py-3.5 text-base text-niebla placeholder:text-ceniza/60 focus:border-salvia focus:ring-2 focus:ring-salvia/40 focus:outline-none"
                    >
                    @error('password')
                        <p id="password-error" class="mt-2 text-sm text-oxido-light">{{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-sm text-ceniza">Si es tu primera vez, tu contraseña es tu DNI.</p>
                </div>

                <label class="flex cursor-pointer items-start gap-3 text-sm text-niebla">
                    {{-- Marcado por defecto: el portal se usa como una app en el celular propio. --}}
                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                        @checked(old('remember', true))
                        class="mt-0.5 size-5 shrink-0 cursor-pointer rounded border-carbon-700 bg-carbon-800 accent-salvia focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-salvia-light"
                    >
                    <span>
                        Mantener la sesión iniciada
                        <span class="block text-ceniza">Desmarcalo si el dispositivo no es tuyo.</span>
                    </span>
                </label>

                <button
                    type="submit"
                    class="w-full cursor-pointer rounded-full bg-salvia px-8 py-4 text-sm font-bold tracking-widest text-carbon uppercase transition hover:bg-salvia-light focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-salvia-light"
                >
                    Entrar
                </button>
            </form>

            <a href="{{ route('inicio') }}" class="mt-8 text-center text-sm text-ceniza transition hover:text-niebla">← Volver al sitio</a>
        </main>
    </body>
</html>
