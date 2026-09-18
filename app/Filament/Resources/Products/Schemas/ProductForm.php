<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label('Nombre')->required(),
                TextInput::make('category')->label('Categoría')->required(),
                TextInput::make('price')->label('Precio')->required()->numeric()->prefix('$'),
                TextInput::make('previous_price')->label('Precio anterior')->numeric()->prefix('$'),
                Toggle::make('in_stock')->label('Disponible')->default(true),
                Textarea::make('description')->label('Descripción')->columnSpanFull(),
                Repeater::make('variants')
                    ->label('Variantes')
                    ->simple(TextInput::make('variant')->label('Variante')->required())
                    ->columnSpanFull(),
                FileUpload::make('images')
                    ->label('Imágenes')
                    ->multiple()
                    ->image()
                    ->maxSize(5120)
                    ->disk('public')
                    ->directory('product-images')
                    ->columnSpanFull(),
            ]);
    }
}
