<?php

namespace App\Filament\Resources\Plans\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AnnouncementsRelationManager extends RelationManager
{
    protected static string $relationship = 'announcements';

    protected static ?string $title = 'Avisos';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->label('Título')->required()->columnSpanFull(),
                Textarea::make('body')->label('Contenido')->required()->columnSpanFull(),
                Select::make('severity')->label('Severidad')->options([
                    'INFO' => 'Info',
                    'WARNING' => 'Advertencia',
                    'URGENT' => 'Urgente',
                ])->default('INFO')->required(),
                Toggle::make('is_visible')->label('Visible')->default(true),
                DateTimePicker::make('starts_at')->label('Vigente desde'),
                DateTimePicker::make('ends_at')->label('Vigente hasta'),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Título')->searchable(),
                TextColumn::make('severity')->label('Severidad')->badge()->color(fn (string $state): string => match ($state) {
                    'URGENT' => 'danger',
                    'WARNING' => 'warning',
                    default => 'info',
                }),
                IconColumn::make('is_visible')->label('Visible')->boolean(),
                TextColumn::make('starts_at')->label('Desde')->dateTime(),
                TextColumn::make('ends_at')->label('Hasta')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth('admin')->id() ? auth('admin')->user()->uuid : null;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
