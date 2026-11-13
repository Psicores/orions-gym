{{--
    Segundo paso del ingreso del personal con verificación en dos pasos (ver MemberSessionController::storeCode).
    Mismo diseño que el ingreso.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-carbon">
    <head>
        @include('partials.member-head', ['title' => 'Código de verificación'])
    </head>
    <body class="bg-carbon font-sans text-niebla antialiased selection:bg-oxido selection:text-carbon">
        <main class="mx-auto flex min-h-dvh max-w-md flex-col justify-center px-6 py-10">
            <a href="{{ route('inicio') }}" aria-label="Orions, inicio" class="mx-auto">
                <img src="{{ asset('images/icons/icon-192.png') }}" alt="Orions" width="96" height="96" class="size-24 rounded-3xl">
            </a>

            <p class="mt-8 text-center text-xs font-semibold tracking-[0.4em] text-oxido uppercase">Verificación en dos pasos</p>
            <h1 class="mt-3 text-center font-display text-5xl leading-none uppercase">Código</h1>

            <form method="POST" action="{{ route('login.code.store') }}" class="mt-10 space-y-5">
                @csrf

                <div>
                    <label for="codigo" class="text-sm font-semibold text-niebla">Código de tu app de autenticación</label>
                    <input
                        id="codigo"
                        name="codigo"
                        type="text"
                        required
                        autofocus
                        autocomplete="one-time-code"
                        autocapitalize="none"
                        spellcheck="false"
                        placeholder="6 dígitos"
                        @error('codigo') aria-invalid="true" aria-describedby="codigo-error" @enderror
                        class="mt-2 w-full rounded-xl border border-carbon-700 bg-carbon-800 px-4 py-3.5 text-base text-niebla placeholder:text-ceniza/60 focus:border-salvia focus:ring-2 focus:ring-salvia/40 focus:outline-none"
                    >
                    @error('codigo')
                        <p id="codigo-error" class="mt-2 text-sm text-oxido-light">{{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-sm text-ceniza">Si no tenés el celular, escribí uno de tus códigos de recuperación.</p>
                </div>

                <button
                    type="submit"
                    class="w-full cursor-pointer rounded-full bg-salvia px-8 py-4 text-sm font-bold tracking-widest text-carbon uppercase transition hover:bg-salvia-light focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-salvia-light"
                >
                    Verificar
                </button>
            </form>

            <a href="{{ route('login') }}" class="mt-8 text-center text-sm text-ceniza transition hover:text-niebla">← Volver al ingreso</a>
        </main>
    </body>
</html>
