<?php

namespace App\Support;

/**
 * F-035: unica fuente de verdad de los formatos y limites de subida de
 * archivos del API (comprobantes de pago y fotos de perfil/participante).
 *
 * Debe mantenerse alineada con `portal/src/lib/uploads.ts`. HEIC/HEIF NO se
 * acepta a proposito: iOS convierte a JPEG cuando el `accept` del input no lo
 * incluye, y un HEIC no se puede mostrar en Chrome/Filament al admin. AVIF se
 * omite porque su deteccion depende de la version de libmagic del hosting.
 */
final class UploadRules
{
    /** @var list<string> */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];

    /** @var list<string> */
    public const RECEIPT_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];

    public const RECEIPT_MAX_KB = 10240;

    public const PHOTO_MAX_KB = 5120;

    /** @return list<string> */
    public static function receipt(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimes:'.implode(',', self::RECEIPT_EXTENSIONS),
            'max:'.self::RECEIPT_MAX_KB,
        ];
    }

    /** @return list<string> */
    public static function photo(): array
    {
        return [
            'required',
            'image',
            'mimes:'.implode(',', self::IMAGE_EXTENSIONS),
            'max:'.self::PHOTO_MAX_KB,
        ];
    }

    /** @return array<string, string> */
    public static function receiptMessages(string $field = 'comprobante'): array
    {
        return [
            "{$field}.required" => 'Adjunta el comprobante de pago.',
            "{$field}.file" => 'El comprobante debe ser un archivo.',
            "{$field}.uploaded" => 'No se pudo subir el comprobante. Verifica que pese menos de 10 MB e inténtalo de nuevo.',
            "{$field}.mimes" => 'El comprobante debe ser PDF, JPG, PNG, WEBP, GIF o BMP.',
            "{$field}.max" => 'El comprobante no puede pesar más de 10 MB.',
        ];
    }

    /** @return array<string, string> */
    public static function photoMessages(string $field = 'foto'): array
    {
        return [
            "{$field}.required" => 'Selecciona una foto.',
            "{$field}.image" => 'La foto debe ser una imagen JPG, PNG, WEBP, GIF o BMP.',
            "{$field}.uploaded" => 'No se pudo subir la foto. Verifica que pese menos de 5 MB e inténtalo de nuevo.',
            "{$field}.mimes" => 'La foto debe ser una imagen JPG, PNG, WEBP, GIF o BMP.',
            "{$field}.max" => 'La foto no puede pesar más de 5 MB.',
        ];
    }
}
