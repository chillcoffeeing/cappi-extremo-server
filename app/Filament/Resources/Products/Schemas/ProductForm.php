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
                TextInput::make('name')->label('Nombre')->required()->maxLength(255)
                    ->helperText('Cambiar el nombre no modifica los pedidos ya creados: conservan el nombre y el precio con que se pidieron.'),
                TextInput::make('category')->label('Categoría')->required()->maxLength(255),
                TextInput::make('price')->label('Precio')->required()->numeric()->minValue(0)->maxValue(999999.99)->prefix('$')
                    ->helperText('Es el precio que se cobra: el pedido de la tienda lo toma siempre de aquí al crearse (el portal no puede cambiarlo).'),
                TextInput::make('previous_price')->label('Precio anterior')->numeric()->minValue(0)->maxValue(999999.99)->prefix('$')
                    ->helperText('Si se completa, este valor se muestra tachado junto al Precio actual en la tienda del portal (marca el producto como en oferta).'),
                Toggle::make('in_stock')->label('Disponible')->default(true)
                    ->helperText('Si se desactiva, el portal lo muestra "Sin stock" y no se pueden crear pedidos nuevos con este producto. Los pedidos ya creados no cambian.'),
                Textarea::make('description')->label('Descripción')->columnSpanFull(),
                Repeater::make('variants')
                    ->label('Variantes')
                    ->helperText('Cada texto es una opción que el comprador elige en el detalle del producto del portal antes de agregarlo al carrito (ej. talla, color, sabor); con una sola opción se agrega directamente. Sin variantes, el producto se compra sin elegir opción. Quitar o renombrar una opción no cambia pedidos ya creados.')
                    ->simple(TextInput::make('variant')->label('Variante')->required()->maxLength(255)->distinct())
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
