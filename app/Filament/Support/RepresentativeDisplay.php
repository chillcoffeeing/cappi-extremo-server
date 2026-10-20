<?php

namespace App\Filament\Support;

use App\Models\Order;
use App\Models\Payment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * F-054 — como se muestra el representante de una orden o un pago en el
 * backoffice. Desde F-053, al borrar un representante sus ordenes y pagos se
 * conservan con `user_uuid = NULL` y una copia minima de sus datos en
 * `representative_snapshot` ({name, lastName, email, identification,
 * deletedAt}). Aqui se centraliza el placeholder "Representante eliminado"
 * para tablas, ficha de orden, widget y RelationManagers.
 */
class RepresentativeDisplay
{
    public const DELETED_LABEL = 'Representante eliminado';

    /** Nombre visible: el del representante, o el placeholder con el snapshot. */
    public static function name(Order|Payment|null $record): ?string
    {
        if ($record === null) {
            return null;
        }

        if ($record->user) {
            return trim($record->user->name.' '.($record->user->last_name ?? ''));
        }

        if ($record->user_uuid !== null) {
            return null;
        }

        $snapshotName = static::snapshotName($record);

        return $snapshotName !== null
            ? static::DELETED_LABEL.': '.$snapshotName
            : static::DELETED_LABEL;
    }

    private static function snapshotName(Order|Payment $record): ?string
    {
        $snapshot = $record->representative_snapshot;
        if (! is_array($snapshot)) {
            return null;
        }

        $name = trim(($snapshot['name'] ?? '').' '.($snapshot['lastName'] ?? ''));

        return $name !== '' ? $name : null;
    }

    /** Linea secundaria (solo para registros sin representante con snapshot): email · identificacion. */
    public static function snapshotDetails(Order|Payment|null $record): ?string
    {
        if ($record === null || $record->user_uuid !== null) {
            return null;
        }

        $snapshot = $record->representative_snapshot;
        if (! is_array($snapshot)) {
            return null;
        }

        $parts = array_filter([
            $snapshot['email'] ?? null,
            filled($snapshot['identification'] ?? null) ? 'ID '.$snapshot['identification'] : null,
        ], fn ($value): bool => filled($value));

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** Texto completo para fichas (una linea). */
    public static function full(Order|Payment|null $record): string
    {
        if ($record?->user) {
            $email = $record->user->email ? " ({$record->user->email})" : '';

            return static::name($record).$email;
        }

        $name = static::name($record);
        if ($name === null) {
            return '—';
        }

        $details = static::snapshotDetails($record);

        return $details !== null ? "{$name} ({$details})" : $name;
    }

    /**
     * Columna "Representante" compartida por la tabla de Ordenes y las tablas
     * de Pagos. Busca por el representante actual y, para los registros
     * huerfanos, dentro del snapshot.
     */
    public static function column(): TextColumn
    {
        return TextColumn::make('user.name')
            ->label('Representante')
            ->getStateUsing(fn (Order|Payment $record): ?string => static::name($record))
            ->description(fn (Order|Payment $record): ?string => static::snapshotDetails($record))
            ->color(fn (Order|Payment $record): ?string => $record->user_uuid === null ? 'gray' : null)
            ->placeholder('—')
            ->searchable(query: function (Builder $query, string $search): Builder {
                return $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->whereHas('user', fn (Builder $user) => $user
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhere(fn (Builder $orphan) => $orphan
                            ->whereNull('user_uuid')
                            ->where('representative_snapshot', 'like', "%{$search}%"));
                });
            });
    }

    /** Filtro "Sin representante" (registros cuyo representante fue eliminado). */
    public static function withoutRepresentativeFilter(): Filter
    {
        return Filter::make('without_representative')
            ->label('Sin representante')
            ->toggle()
            ->query(fn (Builder $query): Builder => $query->whereNull('user_uuid'));
    }
}
