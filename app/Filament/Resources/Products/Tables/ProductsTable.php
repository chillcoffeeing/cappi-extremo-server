<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Support\AdminDeleteActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('category')->label('Categoría')->searchable(),
                TextColumn::make('price')->label('Precio')->money('USD')->sortable(),
                TextColumn::make('previous_price')->label('Precio anterior')->money('USD')->sortable(),
                IconColumn::make('in_stock')->label('Disponible')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                AdminDeleteActions::product(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // F-054 (C-5): visible con deleteAny; cada registro se
                    // re-autoriza con la policy delete() (products.manage).
                    // Las ordenes guardan su propia copia del producto.
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }
}
