<?php

namespace App\Filament\Resources\Participants;

use App\Filament\Resources\Participants\Pages\ListParticipants;
use App\Filament\Resources\Participants\Pages\ViewParticipant;
use App\Filament\Resources\Participants\RelationManagers\CorrectionRequestsRelationManager;
use App\Filament\Resources\Participants\Schemas\ParticipantForm;
use App\Filament\Resources\Participants\Tables\ParticipantsTable;
use App\Models\Participant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Sin create() en ParticipantPolicy: los participantes se crean desde el
 * wizard del portal, no desde el admin.
 */
class ParticipantResource extends Resource
{
    protected static ?string $model = Participant::class;

    protected static ?string $navigationLabel = 'Participantes';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    public static function form(Schema $schema): Schema
    {
        return ParticipantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ParticipantsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CorrectionRequestsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParticipants::route('/'),
            'view' => ViewParticipant::route('/{record}/details'),
        ];
    }
}
