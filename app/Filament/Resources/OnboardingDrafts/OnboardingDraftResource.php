<?php

namespace App\Filament\Resources\OnboardingDrafts;

use App\Filament\Resources\OnboardingDrafts\Pages\ListOnboardingDrafts;
use App\Filament\Resources\OnboardingDrafts\Pages\ViewOnboardingDraft;
use App\Filament\Resources\OnboardingDrafts\Schemas\OnboardingDraftForm;
use App\Filament\Resources\OnboardingDrafts\Tables\OnboardingDraftsTable;
use App\Models\OnboardingDraft;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OnboardingDraftResource extends Resource
{
    protected static ?string $model = OnboardingDraft::class;

    protected static ?string $navigationLabel = 'Onboarding';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function form(Schema $schema): Schema
    {
        return OnboardingDraftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OnboardingDraftsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOnboardingDrafts::route('/'),
            'view' => ViewOnboardingDraft::route('/{record}/details'),
        ];
    }
}
