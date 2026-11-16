{{--
    Manual de uso (ver App\Filament\Pages\UserManual). Recibe $manual: el HTML ya convertido desde
    resources/manual/manual-de-usuario.md (archivo propio del proyecto, sin HTML crudo).
    Los estilos .orions-manual* están en filament/admin/styles.blade.php. Al imprimir sale solo el manual.
--}}
<x-filament-panels::page>
    <x-filament::section>
        <div class="fi-prose orions-manual">
            {!! $manual !!}
        </div>
    </x-filament::section>
</x-filament-panels::page>
