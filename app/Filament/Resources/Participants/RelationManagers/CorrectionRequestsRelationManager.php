<?php

namespace App\Filament\Resources\Participants\RelationManagers;

use App\Actions\Participants\RequestParticipantCorrection;
use App\Actions\Participants\ResolveParticipantCorrection;
use App\Exceptions\ParticipantActionException;
use App\Models\ParticipantCorrectionRequest;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CorrectionRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'correctionRequests';

    protected static ?string $title = 'Solicitudes de corrección';

    /**
     * Coincide 1:1 con `SectionKey` del portal (portal/src/modules/participantes/detalle-sections.tsx)
     * para que el portal pueda ubicar la solicitud en su sección real.
     *
     * @return array<string, string>
     */
    public static function sectionOptions(): array
    {
        return [
            'datosBasicos' => 'Datos básicos',
            'salud' => 'Salud',
            'contactosEmergencia' => 'Contactos de emergencia',
        ];
    }

    /**
     * F-061 (fe-A-16): `documentos` ya no se ofrece al crear (el portal no
     * tiene esa sección y la familia nunca la veia), pero las solicitudes
     * viejas con esa seccion se siguen mostrando con su etiqueta.
     *
     * @return array<string, string>
     */
    public static function sectionLabels(): array
    {
        // F-076: 'seguroMedico' tambien quedo solo como etiqueta historica.
        return [...static::sectionOptions(), 'seguroMedico' => 'Seguro médico', 'documentos' => 'Documentos'];
    }

    /**
     * Las solicitudes de corrección son editables incluso en la página de
     * solo lectura del participante (F-006): el admin debe poder crearlas
     * desde el detalle. Anula el valor por defecto de Filament
     * (readOnlyRelationManagersOnResourceViewPagesByDefault).
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('section')->label('Sección afectada')->options(static::sectionOptions())->required(),
            Textarea::make('message')->label('Mensaje')->required()->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('section')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('section')->label('Sección')->formatStateUsing(fn (string $state): string => static::sectionLabels()[$state] ?? $state),
                TextColumn::make('message')->label('Mensaje')->limit(40),
                TextColumn::make('status')->label('Estado')->badge()->color(fn (string $state): string => match ($state) {
                    'RESUELTA' => 'success',
                    'DESCARTADA' => 'gray',
                    default => 'warning',
                }),
                TextColumn::make('requestedBy.name')->label('Solicitado por'),
                TextColumn::make('created_at')->label('Fecha')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Solicitar corrección')
                    ->using(function (array $data): ParticipantCorrectionRequest {
                        return app(RequestParticipantCorrection::class)->handle(
                            $this->getOwnerRecord(),
                            $data['section'],
                            $data['message'],
                            Auth::guard('admin')->user(),
                        );
                    }),
            ])
            ->recordActions([
                Action::make('resolve')
                    ->label('Resolver')
                    // F-062 (api-A-6): solo quien puede gestionar correcciones
                    // (policy `update`: participants.edit o corrections.manage).
                    // `authorize` hace que Filament rechace tambien la llamada
                    // directa por Livewire (no solo oculta el boton).
                    ->authorize(fn (ParticipantCorrectionRequest $record): bool => static::canResolve($record))
                    ->visible(fn (ParticipantCorrectionRequest $record): bool => $record->status === 'PENDIENTE')
                    ->schema([
                        Select::make('status')->label('Resultado')->options([
                            'RESUELTA' => 'Resuelta',
                            'DESCARTADA' => 'Descartada',
                        ])->default('RESUELTA')->required(),
                        Textarea::make('resolution')->label('Resolución')->required(),
                    ])
                    ->action(function (array $data, ParticipantCorrectionRequest $record): void {
                        abort_unless(static::canResolve($record), 403);

                        try {
                            app(ResolveParticipantCorrection::class)->handle(
                                $record,
                                $data['resolution'],
                                Auth::guard('admin')->user(),
                                $data['status'],
                            );
                            Notification::make()->title('Solicitud actualizada')->success()->send();
                        } catch (ParticipantActionException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    }),
                // F-054: solo SUPER_ADMIN (ParticipantCorrectionRequestPolicy::delete,
                // records.delete). Borrado de una fila sin efectos laterales.
                DeleteAction::make()
                    ->modalDescription('Se borrará esta solicitud de corrección. Esta acción es irreversible.'),
            ]);
    }

    /** F-062 (api-A-6): ParticipantCorrectionRequestPolicy::update. */
    public static function canResolve(ParticipantCorrectionRequest $record): bool
    {
        return Auth::guard('admin')->user()?->can('update', $record) ?? false;
    }
}
