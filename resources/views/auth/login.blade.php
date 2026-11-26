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
                    {{-- Campo con el botón del ojo a la derecha para mostrar u ocultar lo escrito (script al pie). --}}
                    <div class="relative mt-2">
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                            class="w-full rounded-xl border border-carbon-700 bg-carbon-800 py-3.5 ps-4 pe-14 text-base text-niebla placeholder:text-ceniza/60 focus:border-salvia focus:ring-2 focus:ring-salvia/40 focus:outline-none"
                        >
                        <button
                            type="button"
                            id="password-toggle"
                            aria-controls="password"
                            aria-pressed="false"
                            aria-label="Mostrar contraseña"
                            title="Mostrar contraseña"
                            class="absolute inset-y-0 end-0 flex w-14 cursor-pointer items-center justify-center rounded-e-xl text-ceniza transition hover:text-niebla focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-salvia-light"
                        >
                            {{-- Ojo abierto: se ve mientras la contraseña está oculta. --}}
                            <svg data-icon="show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            {{-- Ojo tachado: se ve mientras la contraseña está a la vista. --}}
                            <svg data-icon="hide" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="hidden size-6" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
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

            {{-- Firma de la agencia (también es el ingreso del personal al panel). --}}
            @include('partials.devkore-credit', ['class' => 'mt-6 text-center'])
        </main>

        <script>
            // Botón del ojo: alterna la contraseña entre oculta y visible, y actualiza el ícono y el
            // texto para lectores de pantalla. Sin JavaScript, el campo sigue funcionando oculto.
            (() => {
                // Campo de la contraseña y su botón.
                const input = document.getElementById('password');
                const toggle = document.getElementById('password-toggle');

                // Al tocar el ojo.
                toggle.addEventListener('click', () => {
                    // true si ahora hay que mostrarla.
                    const show = input.type === 'password';

                    // Texto plano para verla, password para ocultarla.
                    input.type = show ? 'text' : 'password';
                    // Estado del botón para lectores de pantalla.
                    toggle.setAttribute('aria-pressed', String(show));
                    // Qué hace el próximo toque.
                    toggle.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
                    toggle.title = toggle.getAttribute('aria-label');
                    // Ojo tachado cuando se ve; ojo abierto cuando está oculta.
                    toggle.querySelector('[data-icon="show"]').classList.toggle('hidden', show);
                    toggle.querySelector('[data-icon="hide"]').classList.toggle('hidden', ! show);
                    // Deja el cursor en el campo para seguir escribiendo.
                    input.focus();
                });
            })();
        </script>
    </body>
</html>
