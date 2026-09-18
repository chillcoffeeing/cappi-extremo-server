<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OnboardingDraftsRelationManager extends RelationManager
{
    protected static string $relationship = 'onboardingDrafts';

    protected static ?string $title = 'Onboarding';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('status')
            ->columns([
                TextColumn::make('status')->label('Estado')->badge(),
                TextColumn::make('updated_at')->label('Última actividad')->dateTime(),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make()->url(fn ($record): string => route('filament.admin.resources.onboarding-drafts.view', $record)),
            ]);
    }
}
