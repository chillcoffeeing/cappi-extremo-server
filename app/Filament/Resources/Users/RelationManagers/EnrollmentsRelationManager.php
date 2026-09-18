<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * `enrollments` en User es un hasManyThrough(Participant); de solo lectura.
 * Fila clicable a /admin/enrollments/{id}/details (F-018), mismo patron que
 * ParticipantsRelationManager/OnboardingDraftsRelationManager.
 */
class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Inscripciones';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('plan_name')
            ->columns([
                TextColumn::make('plan_name')->label('Plan'),
                TextColumn::make('session_name')->label('Sesión'),
                TextColumn::make('plan_type')->label('Modalidad'),
                TextColumn::make('total_amount')->label('Total')->money('USD'),
                TextColumn::make('sibling_discount')->label('Descuento')->money('USD'),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make()->url(fn ($record): string => route('filament.admin.resources.enrollments.view', $record)),
            ]);
    }
}
