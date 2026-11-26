{{--
    Campo de contraseña con el botón del ojo para mostrar u ocultar lo escrito. Para pantallas con
    Alpine (las del portal del socio, que usan Livewire). El ingreso (/ingresar) no carga Alpine y
    tiene su propia versión con un script chico (ver auth/login.blade.php).

    Recibe:
    - $id: id del campo (también lo usa el <label for="...">).
    - $model: propiedad de Livewire a la que se enlaza (wire:model).
    - $autocomplete: "current-password" o "new-password", para que el navegador ofrezca lo correcto.
    - $minlength (opcional): largo mínimo que valida el navegador.

    Cada campo recuerda por su cuenta si está visible. Mismo aspecto que el resto de los campos
    del portal, con lugar a la derecha para el botón.
--}}
<div x-data="{ visible: false }" class="relative mt-2">
    <input
        id="{{ $id }}"
        type="password"
        x-bind:type="visible ? 'text' : 'password'"
        x-ref="input"
        wire:model="{{ $model }}"
        required
        @isset($minlength) minlength="{{ $minlength }}" @endisset
        autocomplete="{{ $autocomplete }}"
        class="w-full rounded-xl border border-carbon-700 bg-carbon-800 py-3.5 ps-4 pe-14 text-base text-niebla placeholder:text-ceniza/60 focus:border-salvia focus:ring-2 focus:ring-salvia/40 focus:outline-none"
    >
    <button
        type="button"
        x-on:click="visible = ! visible; $refs.input.focus()"
        aria-controls="{{ $id }}"
        aria-pressed="false"
        x-bind:aria-pressed="visible.toString()"
        aria-label="Mostrar contraseña"
        x-bind:aria-label="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'"
        title="Mostrar contraseña"
        x-bind:title="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'"
        class="absolute inset-y-0 end-0 flex w-14 cursor-pointer items-center justify-center rounded-e-xl text-ceniza transition hover:text-niebla focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-salvia-light"
    >
        {{-- Ojo abierto: se ve mientras la contraseña está oculta. --}}
        <svg x-show="! visible" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
        </svg>
        {{-- Ojo tachado: se ve mientras la contraseña está a la vista. --}}
        <svg x-show="visible" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
        </svg>
    </button>
</div>
