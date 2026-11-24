<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Pages\Kiosk;
use App\Filament\Resources\Sales\SaleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Atajo a la pantalla de venta.
            Action::make('sell')
                ->label('Vender')
                ->icon(Heroicon::OutlinedShoppingCart)
                ->url(Kiosk::getUrl()),
        ];
    }
}
