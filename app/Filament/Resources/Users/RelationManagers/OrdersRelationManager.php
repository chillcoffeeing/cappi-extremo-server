<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Fila clicable a /admin/orders/{id}/details (F-018), mismo patron que
 * ParticipantsRelationManager/OnboardingDraftsRelationManager.
 */
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Órdenes';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('uuid')
            ->columns([
                TextColumn::make('uuid')->label('Código')->limit(8),
                TextColumn::make('status')->label('Estado')->badge(),
                TextColumn::make('total')->label('Total')->money('USD'),
                TextColumn::make('paid')->label('Abonado')->money('USD'),
                TextColumn::make('is_registration')->label('Tipo')->formatStateUsing(fn (bool $state): string => $state ? 'Inscripción' : 'Tienda'),
                TextColumn::make('ordered_at')->label('Fecha')->date(),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make()->url(fn ($record): string => route('filament.admin.resources.orders.view', $record)),
            ]);
    }
}
