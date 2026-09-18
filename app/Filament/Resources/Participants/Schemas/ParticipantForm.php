<?php

namespace App\Filament\Resources\Participants\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Salud/contactos/seguro/autorizaciones son de solo lectura: el doc los
 * marca como "mostrar", y el mecanismo de cambio es "solicitar correccion"
 * (el representante corrige desde el portal), no edicion directa del admin.
 *
 * Se usan componentes de Infolist (TextEntry/IconEntry/RepeatableEntry) en
 * vez de volcar el JSON crudo de cada bloque: en Filament v4, Forms e
 * Infolists comparten el mismo Schema, asi que ambos tipos de componente
 * conviven en el mismo formulario sin problema.
 *
 * El tab 'Inscripción' (F-015) lee Participant::enrollment() (HasOne). El
 * estado se resuelve por closures que comprueban la relación
 * (`$record?->enrollment?->campo`) en vez de dot-notation cruda: con la
 * relación null, esa comprobación devuelve null sin tirar excepción al
 * resolver el state del componente, y el heading muestra
 * 'Sin inscripción todavía'.
 */
class ParticipantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Participante')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Datos básicos')
                            ->schema([
                                Placeholder::make('representative')
                                    ->label('Representante')
                                    ->content(fn ($record): string => $record?->user ? "{$record->user->name} ({$record->user->email})" : '—'),
                                TextInput::make('name')->label('Nombre')->required(),
                                DatePicker::make('birth_date')->label('Nacimiento')->required(),
                                Select::make('gender')->label('Género')->options([
                                    'MASCULINO' => 'Masculino',
                                    'FEMENINO' => 'Femenino',
                                ])->required(),
                                TextInput::make('identification')->label('Identificación'),
                                TextInput::make('shirt_size')->label('Talla'),
                                TextInput::make('weight_kg')->label('Peso (kg)')->numeric(),
                            ])
                            ->columns(2),
                        Tab::make('Salud')
                            ->schema([
                                TextEntry::make('health.tipoSangre')->label('Tipo de sangre')->placeholder('—'),
                                IconEntry::make('health.requiereAcompanante')->label('Requiere acompañante')->boolean(),
                                TextEntry::make('health.alergias')->label('Alergias')->placeholder('Ninguna'),
                                TextEntry::make('health.condicionesMedicas')->label('Condiciones médicas')->placeholder('Ninguna'),
                                TextEntry::make('health.medicamentos')->label('Medicamentos')->placeholder('Ninguno'),
                                TextEntry::make('health.discapacidades')->label('Discapacidades')->placeholder('Ninguna'),
                                TextEntry::make('health.infoAdicional')->label('Información adicional')->placeholder('—')->columnSpanFull(),
                            ])
                            ->columns(2),
                        Tab::make('Contactos')
                            ->schema([
                                RepeatableEntry::make('emergency_contacts')
                                    ->label('Contactos de emergencia')
                                    ->columnSpanFull()
                                    ->schema([
                                        TextEntry::make('nombre')->label('Nombre'),
                                        TextEntry::make('telefono')->label('Teléfono'),
                                        TextEntry::make('parentesco')->label('Parentesco'),
                                    ])
                                    ->columns(3),
                                TextEntry::make('pickup_contact_heading')
                                    ->hiddenLabel()
                                    ->state('Encargado de retiro')
                                    ->weight('bold')
                                    ->columnSpanFull(),
                                TextEntry::make('pickup_contact.nombre')->label('Nombre')->placeholder('Sin encargado registrado'),
                                TextEntry::make('pickup_contact.documento')->label('Documento')->placeholder('—'),
                                TextEntry::make('pickup_contact.telefono')->label('Teléfono')->placeholder('—'),
                                TextEntry::make('pickup_contact.relacion')->label('Relación')->placeholder('—'),
                                IconEntry::make('pickup_contact.esContactoEmergencia')->label('Es contacto de emergencia')->boolean(),
                            ])
                            ->columns(2),
                        Tab::make('Seguro y autorizaciones')
                            ->schema([
                                IconEntry::make('medical_insurance.noTiene')->label('No tiene seguro')->boolean(),
                                TextEntry::make('medical_insurance.aseguradora')->label('Aseguradora')->placeholder('—'),
                                TextEntry::make('medical_insurance.poliza')->label('Póliza')->placeholder('—'),
                                TextEntry::make('medical_insurance.telefonoEmergencias')->label('Teléfono de emergencias')->placeholder('—'),
                                TextEntry::make('authorizations_heading')
                                    ->hiddenLabel()
                                    ->state('Autorizaciones')
                                    ->weight('bold')
                                    ->columnSpanFull(),
                                IconEntry::make('authorizations.autorizaFotos')->label('Fotos')->boolean(),
                                IconEntry::make('authorizations.autorizaVideo')->label('Video')->boolean(),
                                IconEntry::make('authorizations.autorizaActividadesAcuaticas')->label('Actividades acuáticas')->boolean(),
                                IconEntry::make('authorizations.autorizaTraslados')->label('Traslados')->boolean(),
                                IconEntry::make('authorizations.autorizaAtencionMedicaUrgencia')->label('Atención médica de urgencia')->boolean(),
                            ])
                            ->columns(2),
                        Tab::make('Inscripción')
                            ->schema([
                                TextEntry::make('enrollment_heading')
                                    ->hiddenLabel()
                                    ->state(fn ($record): string => $record?->enrollment ? 'Datos de la inscripción' : 'Sin inscripción todavía')
                                    ->weight('bold')
                                    ->columnSpanFull(),
                                TextEntry::make('enrollment.plan_name')
                                    ->label('Plan')
                                    ->getStateUsing(fn ($record): ?string => $record?->enrollment?->plan_name ?? null)
                                    ->placeholder('—'),
                                TextEntry::make('enrollment.session_name')
                                    ->label('Sesión')
                                    ->getStateUsing(fn ($record): ?string => $record?->enrollment?->session_name ?? null)
                                    ->placeholder('—'),
                                TextEntry::make('enrollment.plan_type')
                                    ->label('Modalidad')
                                    ->getStateUsing(fn ($record): ?string => match ($record?->enrollment?->plan_type) {
                                        'INDIVIDUAL' => 'Individual',
                                        'HERMANOS' => 'Hermanos',
                                        default => $record?->enrollment?->plan_type,
                                    })
                                    ->placeholder('—'),
                                TextEntry::make('enrollment.total_amount')
                                    ->label('Total')
                                    ->money('USD')
                                    ->getStateUsing(fn ($record) => $record?->enrollment?->total_amount)
                                    ->placeholder('—'),
                                TextEntry::make('enrollment.sibling_discount')
                                    ->label('Descuento hermanos')
                                    ->money('USD')
                                    ->getStateUsing(fn ($record) => $record?->enrollment?->sibling_discount)
                                    ->placeholder('—'),
                                TextEntry::make('enrollment.status')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'PAGADA' => 'success',
                                        'CANCELADA' => 'danger',
                                        default => 'warning',
                                    })
                                    ->getStateUsing(fn ($record): ?string => $record?->enrollment?->status ?? null)
                                    ->formatStateUsing(fn (?string $state): ?string => match ($state) {
                                        'PENDIENTE_PAGO' => 'Pendiente de pago',
                                        'PAGADA' => 'Pagada',
                                        'CANCELADA' => 'Cancelada',
                                        default => $state,
                                    })
                                    ->placeholder('—'),
                            ])
                            ->columns(2),
                        Tab::make('Revisión')
                            ->schema([
                                Placeholder::make('reviewed_at')
                                    ->label('Revisado')
                                    ->content(fn ($record): string => $record?->reviewed_at?->format('Y-m-d H:i') ?? 'No revisado'),
                                Placeholder::make('reviewed_by')
                                    ->label('Revisado por')
                                    ->content(fn ($record): string => $record?->reviewedBy?->name ?? '—'),
                            ]),
                    ]),
            ]);
    }
}
