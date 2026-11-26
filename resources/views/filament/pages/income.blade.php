{{--
    Ingresos (ver App\Filament\Pages\Income). Recibe $from y $to (período), $entries (detalle),
    $hiddenEntries (cuántos no entran en el detalle), $totals (por origen y medio), $byUser (por
    persona; vacío si no corresponde), $byDay (por día; vacío si es un solo día), $methods (medios)
    y $people (personal para el filtro; vacío para un profe). Estilos .orions-income-* al final.
--}}
<x-filament-panels::page>
    {{-- Período y persona --}}
    <div class="orions-income-filters">
        <div class="orions-income-quick" role="group" aria-label="Período">
            @foreach (['hoy' => 'Hoy', 'ayer' => 'Ayer', 'semana' => 'Esta semana', 'mes' => 'Este mes'] as $value => $label)
                <x-filament::button size="sm" color="gray" wire:click="period('{{ $value }}')">{{ $label }}</x-filament::button>
            @endforeach
        </div>

        <label class="orions-income-field">
            <span>Desde</span>
            <input type="date" wire:model.live="desde" class="orions-income-input">
        </label>
        <label class="orions-income-field">
            <span>Hasta</span>
            <input type="date" wire:model.live="hasta" class="orions-income-input">
        </label>

        @if ($this->isAdmin())
            <label class="orions-income-field">
                <span>Persona</span>
                <select wire:model.live="persona" class="orions-income-input">
                    <option value="">Todos</option>
                    @foreach ($people as $person)
                        <option value="{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>

    <p class="orions-income-period">
        {{ $from->equalTo($to) ? $from->format('d/m/Y') : $from->format('d/m/Y').' al '.$to->format('d/m/Y') }}
        @unless ($this->isAdmin())
            · Lo que cobraste vos
        @endunless
    </p>

    {{-- Totales --}}
    <div class="orions-income-cards">
        <div class="orions-income-card is-main">
            <p>Total</p>
            <p class="orions-income-amount">{{ $this->money($totals['total']['total']) }}</p>
        </div>
        @foreach ($methods as $method)
            <div class="orions-income-card">
                <p>{{ $method->getLabel() }}</p>
                <p class="orions-income-amount">{{ $this->money($totals['total'][$method->value]) }}</p>
            </div>
        @endforeach
    </div>

    {{-- Cuotas y kiosco por medio --}}
    <x-filament::section heading="Cuotas y kiosco">
        <table class="orions-income-table">
            <thead>
                <tr>
                    <th></th>
                    @foreach ($methods as $method)
                        <th>{{ $method->getLabel() }}</th>
                    @endforeach
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ([\App\Services\Income\IncomeEntry::CUOTA, \App\Services\Income\IncomeEntry::KIOSCO] as $origin)
                    <tr>
                        <th>{{ $this->originLabel($origin) }}</th>
                        @foreach ($methods as $method)
                            <td data-label="{{ $method->getLabel() }}">{{ $this->money($totals[$origin][$method->value]) }}</td>
                        @endforeach
                        <td data-label="Total">{{ $this->money($totals[$origin]['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th>Total</th>
                    @foreach ($methods as $method)
                        <td data-label="{{ $method->getLabel() }}">{{ $this->money($totals['total'][$method->value]) }}</td>
                    @endforeach
                    <td data-label="Total">{{ $this->money($totals['total']['total']) }}</td>
                </tr>
            </tfoot>
        </table>
    </x-filament::section>

    {{-- Por persona (administradores, sin filtro de persona) --}}
    @if ($byUser !== [])
        <x-filament::section heading="Por persona" description="Lo que tiene que rendir cada uno.">
            <table class="orions-income-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Cuotas</th>
                        <th>Kiosco</th>
                        @foreach ($methods as $method)
                            <th>{{ $method->getLabel() }}</th>
                        @endforeach
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($byUser as $row)
                        <tr>
                            <th>{{ $row['name'] }}</th>
                            <td data-label="Cuotas">{{ $this->money($row['cuota']) }}</td>
                            <td data-label="Kiosco">{{ $this->money($row['kiosco']) }}</td>
                            @foreach ($methods as $method)
                                <td data-label="{{ $method->getLabel() }}">{{ $this->money($row[$method->value]) }}</td>
                            @endforeach
                            <td data-label="Total">{{ $this->money($row['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endif

    {{-- Por día (períodos de más de un día) --}}
    @if ($byDay !== [])
        <x-filament::section heading="Por día">
            <table class="orions-income-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Cuotas</th>
                        <th>Kiosco</th>
                        @foreach ($methods as $method)
                            <th>{{ $method->getLabel() }}</th>
                        @endforeach
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($byDay as $row)
                        <tr>
                            <th>{{ \Carbon\CarbonImmutable::parse($row['day'])->format('d/m') }}</th>
                            <td data-label="Cuotas">{{ $this->money($row['cuota']) }}</td>
                            <td data-label="Kiosco">{{ $this->money($row['kiosco']) }}</td>
                            @foreach ($methods as $method)
                                <td data-label="{{ $method->getLabel() }}">{{ $this->money($row[$method->value]) }}</td>
                            @endforeach
                            <td data-label="Total">{{ $this->money($row['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endif

    {{-- Detalle --}}
    <x-filament::section heading="Detalle" :description="$entries->isEmpty() ? null : 'Cada cobro, del más nuevo al más viejo.'">
        @if ($entries->isEmpty())
            <p class="orions-income-empty">No hubo ingresos en este período.</p>
        @else
            <table class="orions-income-table orions-income-detail">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Origen</th>
                        <th>Detalle</th>
                        <th>Medio</th>
                        <th>Cobró</th>
                        <th>Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr>
                            <td data-label="Fecha">{{ $entry->fecha->format('d/m H:i') }}</td>
                            <td data-label="Origen">{{ $this->originLabel($entry->origen) }}</td>
                            <td data-label="Detalle">{{ $entry->detalle }}</td>
                            <td data-label="Medio"><x-filament::badge :color="$entry->metodo->getColor()" size="sm">{{ $entry->metodo->getLabel() }}</x-filament::badge></td>
                            <td data-label="Cobró">{{ $entry->userName }}</td>
                            <td data-label="Monto">{{ $this->money($entry->cents) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($hiddenEntries > 0)
                <p class="orions-income-empty">Y {{ $hiddenEntries }} cobros más. Achicá el período para verlos; los totales sí los incluyen.</p>
            @endif
        @endif
    </x-filament::section>

    <style>
        .orions-income-filters {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 0.75rem 1rem;
        }

        .orions-income-quick {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-inline-end: auto;
        }

        .orions-income-field {
            display: grid;
            gap: 0.25rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--gray-600);
        }

        .dark .orions-income-field {
            color: var(--gray-300);
        }

        .orions-income-input {
            min-height: 2.5rem;
            padding: 0.375rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.5rem;
            background: #fff;
            color: var(--gray-950);
            font-weight: 400;
        }

        .dark .orions-income-input {
            border-color: rgb(255 255 255 / 0.12);
            background: rgb(255 255 255 / 0.05);
            color: #fff;
        }

        .orions-income-period {
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        .orions-income-cards {
            display: grid;
            /* Hasta 9 rem por tarjeta: en el celular entran los medios de a dos. */
            grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
            gap: 1rem;
        }

        /* En el celular el total ocupa toda la fila y los medios quedan debajo, de a dos. */
        @media (max-width: 639.98px) {
            .orions-income-cards {
                gap: 0.75rem;
            }

            .orions-income-card.is-main {
                grid-column: 1 / -1;
            }

            .orions-income-card:not(.is-main) .orions-income-amount {
                font-size: 1.5rem;
            }
        }

        .orions-income-card {
            padding: 1rem 1.25rem;
            border: 1px solid var(--gray-200);
            border-radius: 0.875rem;
            background: #fff;
            color: var(--gray-600);
            font-size: 0.875rem;
            font-weight: 600;
        }

        .orions-income-card.is-main {
            border-color: var(--primary-600);
            background: var(--primary-600);
            color: #fff;
        }

        .dark .orions-income-card:not(.is-main) {
            border-color: rgb(255 255 255 / 0.1);
            background: var(--gray-900);
            color: var(--gray-300);
        }

        .orions-income-amount {
            margin-top: 0.25rem;
            color: var(--gray-950);
            font-family: 'Anton', 'Bebas Neue', Impact, ui-sans-serif, sans-serif;
            font-size: 1.875rem;
            font-weight: 400;
            letter-spacing: 0.02em;
        }

        .is-main .orions-income-amount,
        .dark .orions-income-amount {
            color: #fff;
        }

        /* Tablas: se desplazan de costado en pantallas chicas. */
        .orions-income-table {
            display: block;
            overflow-x: auto;
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            font-variant-numeric: tabular-nums;
        }

        .orions-income-table :is(th, td) {
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid var(--gray-200);
            text-align: end;
            white-space: nowrap;
        }

        .orions-income-table :is(th, td):first-child {
            padding-inline-start: 0;
            text-align: start;
        }

        .orions-income-table thead th {
            color: var(--gray-500);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .orions-income-table tfoot :is(th, td) {
            border-bottom: 0;
            font-weight: 700;
        }

        .dark .orions-income-table :is(th, td) {
            border-bottom-color: rgb(255 255 255 / 0.08);
        }

        .orions-income-detail :is(th, td):nth-child(-n + 5) {
            text-align: start;
        }

        .orions-income-detail td:nth-child(3) {
            white-space: normal;
            min-width: 10rem;
        }

        /*
            Celular (menos de 640 px): cada fila de las tablas es una tarjeta. El encabezado de la
            fila (Cuotas, Kiosco, la persona o el día) va arriba, y cada dato en un renglón con su
            etiqueta (data-label) a la izquierda. En el detalle, la fecha hace de título.
        */
        @media (max-width: 639.98px) {
            .orions-income-table,
            .orions-income-table :is(tbody, tfoot, tr, th, td) {
                display: block;
                width: 100%;
            }

            .orions-income-table thead {
                display: none;
            }

            .orions-income-table tr {
                padding-block: 0.625rem;
                border-bottom: 1px solid var(--gray-200);
            }

            .dark .orions-income-table tr {
                border-bottom-color: rgb(255 255 255 / 0.08);
            }

            .orions-income-table :is(th, td) {
                padding: 0.125rem 0;
                border-bottom: 0;
            }

            .orions-income-table tbody th,
            .orions-income-table tfoot th {
                font-size: 1rem;
                text-align: start;
            }

            .orions-income-table td[data-label] {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                white-space: normal;
                text-align: end;
            }

            .orions-income-table td[data-label]::before {
                content: attr(data-label);
                flex: none;
                color: var(--gray-500);
                font-size: 0.8125rem;
                font-weight: 500;
            }

            /* Detalle: la fecha va como título de la tarjeta, sin etiqueta. */
            .orions-income-detail :is(th, td):nth-child(-n + 5) {
                text-align: end;
            }

            .orions-income-detail td:first-child {
                font-weight: 600;
                text-align: start;
            }

            .orions-income-detail td:first-child::before {
                content: none;
            }

            .orions-income-detail td:nth-child(3) {
                min-width: 0;
            }
        }

        .orions-income-empty {
            color: var(--gray-500);
            font-size: 0.875rem;
        }
    </style>
</x-filament-panels::page>
