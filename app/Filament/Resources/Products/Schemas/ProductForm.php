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
                TextInput::make('previous_price')->label('Precio anterior')->numeric()->prefix('$')
                    ->helperText('Si se completa, este valor se muestra tachado junto al Precio actual en la tienda del portal (marca el producto como en oferta).'),
                Toggle::make('in_stock')->label('Disponible')->default(true),
                Textarea::make('description')->label('Descripción')->columnSpanFull(),
                Repeater::make('variants')
                    ->label('Variantes')
                    ->helperText('Cada texto es una opción que el comprador elige en la tienda del portal antes de agregar el producto al carrito (ej. talla, color, sabor).')
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
