<?php

namespace App\Filament\Resources\OnboardingDrafts\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Sin edicion libre del JSON (data/completed_steps): "Permitir seguimiento,
 * reactivacion y contacto, pero no edicion libre del JSON"
 * (03-panel-y-recursos.md). Los cambios de estado van por Actions en
 * ViewOnboardingDraft, no por este formulario.
 *
 * 'data' guarda un paso del wizard por clave (cuenta, participantes, pago,
 * confirmacion, adicionales...) y cada paso tiene una forma libre/distinta
 * (SaveOnboardingStep::handle hace array_replace por stepId). En vez de
 * mapear campo por campo como en ParticipantForm, se renderiza con un
 * recorrido recursivo generico a Markdown (sanitizado por Filament vía
 * ->markdown()) para que cualquier paso nuevo se muestre legible sin tocar
 * este archivo.
 */
class OnboardingDraftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Placeholder::make('representative')
                    ->label('Representante')
                    ->content(fn ($record): string => $record?->user ? "{$record->user->name} ({$record->user->email})" : '—'),
                Placeholder::make('status')
                    ->label('Estado')
                    ->content(fn ($record): string => $record?->status ?? '—'),
                Placeholder::make('last_step')
                    ->label('Último paso completado')
                    ->content(function ($record): string {
                        $steps = $record?->completed_steps ?? [];

                        return $steps !== [] ? (string) end($steps) : '—';
                    }),
                Placeholder::make('updated_at')
                    ->label('Última actividad')
                    ->content(fn ($record): string => $record?->updated_at?->format('Y-m-d H:i') ?? '—'),
                Placeholder::make('data')
                    ->label('Datos capturados')
                    ->columnSpanFull()
                    ->markdown()
                    ->content(fn ($record): string => static::renderSteps($record?->data ?? [])),
            ]);
    }

    /** @param array<string, mixed> $steps */
    private static function renderSteps(array $steps): string
    {
        if ($steps === []) {
            return '_Sin datos capturados todavía_';
        }

        $markdown = '';

        foreach ($steps as $step => $stepData) {
            $markdown .= '**'.static::stepLabel((string) $step)."**\n\n";
            $markdown .= is_array($stepData)
                ? static::renderNode($stepData)
                : '- '.static::renderScalar($stepData)."\n";
            $markdown .= "\n";
        }

        return rtrim($markdown);
    }

    private static function renderNode(array $node, int $depth = 0): string
    {
        if ($node === []) {
            return str_repeat('  ', $depth)."- _Sin datos_\n";
        }

        $isList = array_is_list($node);
        $indent = str_repeat('  ', $depth);
        $markdown = '';

        foreach ($node as $key => $value) {
            $label = $isList ? null : static::humanizeKey((string) $key);

            if (is_array($value)) {
                $markdown .= $isList
                    ? "{$indent}- \n".static::renderNode($value, $depth + 1)
                    : "{$indent}- **{$label}:**\n".static::renderNode($value, $depth + 1);

                continue;
            }

            $rendered = static::renderScalar($value);
            $markdown .= $isList
                ? "{$indent}- {$rendered}\n"
                : "{$indent}- **{$label}:** {$rendered}\n";
        }

        return $markdown;
    }

    private static function renderScalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        if ($value === null || $value === '') {
            return '—';
        }

        return (string) $value;
    }

    private static function humanizeKey(string $key): string
    {
        return Str::headline($key);
    }

    private static function stepLabel(string $step): string
    {
        return match ($step) {
            'cuenta' => 'Cuenta y plan',
            'participantes' => 'Participantes',
            'pago' => 'Pago',
            'confirmacion' => 'Confirmación',
            'adicionales' => 'Adicionales',
            default => Str::headline($step),
        };
    }
}
