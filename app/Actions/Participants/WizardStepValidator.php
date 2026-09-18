<?php

namespace App\Actions\Participants;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Valida el contenido de cada paso del wizard individual de participantes
 * (POST /participantes/{id}/wizard/step).
 *
 * Reglas (idénticas al schema zod del portal, ver
 * portal/docs/business-logic/12-api-contrato.md):
 *  - datos-basicos: TODOS los campos requeridos (incl. genero, cedula, talla, peso).
 *  - salud: todos opcionales/null.
 *  - contactos-emergencia: minimo 1 contacto; nombre/telefono/parentesco requeridos.
 *  - encargado-retiro: opcional (null o vacio = valido); si se llena, campos requeridos.
 *  - seguro-medico: todo opcional + bandera booleana `noTiene`.
 *  - autorizaciones: todos booleanos opcionales.
 *
 * Los errores se lanzan con prefijo `data.*` para que el portal los mapee
 * directo al campo que falla (mismo shape que los errores nativos de Laravel).
 */
class WizardStepValidator
{
    public const STEP_IDS = [
        'datos-basicos',
        'salud',
        'contactos-emergencia',
        'encargado-retiro',
        'seguro-medico',
        'autorizaciones',
    ];

    private const GENEROS = ['MASCULINO', 'FEMENINO', 'OTRO', 'PREFIERO_NO_DECIR'];

    private const RELACIONES = [
        'Madre', 'Padre', 'Tutor', 'Abuelo/a', 'Hermano/a', 'Otro', 'Padrino', 'Tío', 'Participante',
    ];

    /**
     * Valida el paso. Devuelve el dato original normalizado (lista para
     * persistir) o lanza ValidationException con claves `data.*`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validate(string $stepId, array $data): array
    {
        if (! in_array($stepId, self::STEP_IDS, true)) {
            throw ValidationException::withMessages([
                'steps' => ['Paso de wizard desconocido: '.$stepId],
            ]);
        }

        $validator = Validator::make($data, $this->rulesFor($stepId, $data), $this->messagesFor());

        if ($validator->fails()) {
            $prefixed = [];
            foreach ($validator->errors()->messages() as $key => $messages) {
                $prefixed['data.'.$key] = $messages;
            }

            throw ValidationException::withMessages($prefixed);
        }

        return $this->normalize($stepId, $data);
    }

    /** @return array<string, array<int, string>> */
    private function rulesFor(string $stepId, array $data): array
    {
        $pickup = $data['encargadoRetiro'] ?? null;
        $pickupFilled = is_array($pickup) && $this->pickupFilled($pickup);

        return match ($stepId) {
            'datos-basicos' => [
                'nombre' => ['required', 'string', 'max:255'],
                'fechaNacimiento' => ['required', 'date_format:Y-m-d'],
                'genero' => ['required', 'in:'.implode(',', self::GENEROS)],
                'cedula' => ['required', 'string'],
                'tallaCamisa' => ['required', 'string', 'max:20'],
                'pesoKg' => ['required', 'numeric'],
            ],
            'salud' => [
                'tipoSangre' => ['nullable', 'string'],
                'alergias' => ['nullable', 'string'],
                'condicionesMedicas' => ['nullable', 'string'],
                'medicamentos' => ['nullable', 'string'],
                'discapacidades' => ['nullable', 'string'],
                'infoAdicional' => ['nullable', 'string'],
                'requiereAcompanante' => ['nullable', 'boolean'],
            ],
            'contactos-emergencia' => [
                'contactosEmergencia' => ['required', 'array', 'min:1'],
                'contactosEmergencia.*.nombre' => ['required', 'string'],
                'contactosEmergencia.*.telefono' => ['required', 'string'],
                'contactosEmergencia.*.parentesco' => ['required', 'in:'.implode(',', self::RELACIONES)],
            ],
            'encargado-retiro' => [
                'encargadoRetiro' => ['nullable', 'array'],
                ...($pickupFilled ? [
                    'encargadoRetiro.nombre' => ['required', 'string'],
                    'encargadoRetiro.telefono' => ['required', 'string'],
                    'encargadoRetiro.relacion' => ['required', 'string', 'in:'.implode(',', self::RELACIONES)],
                ] : []),
            ],
            'seguro-medico' => [
                'aseguradora' => ['nullable', 'string'],
                'poliza' => ['nullable', 'string'],
                'telefonoEmergencias' => ['nullable', 'string'],
                'noTiene' => ['nullable', 'boolean'],
            ],
            default => [
                'autorizaFotos' => ['nullable', 'boolean'],
                'autorizaVideo' => ['nullable', 'boolean'],
                'autorizaActividadesAcuaticas' => ['nullable', 'boolean'],
                'autorizaTraslados' => ['nullable', 'boolean'],
                'autorizaAtencionMedicaUrgencia' => ['nullable', 'boolean'],
            ],
        };
    }

    /** @return array<string, string> */
    private function messagesFor(): array
    {
        return [
            'nombre.required' => 'El nombre es requerido',
            'nombre.string' => 'El nombre debe ser texto',
            'nombre.max' => 'El nombre no puede superar los 255 caracteres',
            'fechaNacimiento.required' => 'La fecha de nacimiento es requerida',
            'fechaNacimiento.date_format' => 'La fecha de nacimiento debe tener formato YYYY-MM-DD',
            'genero.required' => 'Selecciona un género',
            'genero.in' => 'El género seleccionado no es válido',
            'cedula.required' => 'La identificación es requerida',
            'cedula.string' => 'La identificación debe ser texto',
            'tallaCamisa.required' => 'La talla de camisa es requerida',
            'tallaCamisa.string' => 'La talla de camisa debe ser texto',
            'tallaCamisa.max' => 'La talla de camisa no puede superar los 20 caracteres',
            'pesoKg.required' => 'El peso es requerido',
            'pesoKg.numeric' => 'El peso debe ser un número',
            'contactosEmergencia.required' => 'Agrega al menos un contacto de emergencia',
            'contactosEmergencia.array' => 'Los contactos de emergencia deben ser una lista',
            'contactosEmergencia.min' => 'Agrega al menos un contacto de emergencia',
            'contactosEmergencia.*.nombre.required' => 'El nombre del contacto es requerido',
            'contactosEmergencia.*.nombre.string' => 'El nombre del contacto debe ser texto',
            'contactosEmergencia.*.telefono.required' => 'El teléfono del contacto es requerido',
            'contactosEmergencia.*.telefono.string' => 'El teléfono del contacto debe ser texto',
            'contactosEmergencia.*.parentesco.required' => 'Selecciona el parentesco del contacto',
            'contactosEmergencia.*.parentesco.in' => 'El parentesco seleccionado no es válido',
            'encargadoRetiro.array' => 'El encargado de retiro debe ser un objeto o null',
            'encargadoRetiro.nombre.required' => 'El nombre del encargado es requerido',
            'encargadoRetiro.nombre.string' => 'El nombre del encargado debe ser texto',
            'encargadoRetiro.telefono.required' => 'El teléfono del encargado es requerido',
            'encargadoRetiro.telefono.string' => 'El teléfono del encargado debe ser texto',
            'encargadoRetiro.relacion.required' => 'Selecciona la relación del encargado',
            'encargadoRetiro.relacion.string' => 'La relación del encargado debe ser texto',
            'encargadoRetiro.relacion.in' => 'La relación seleccionada no es válida',
            'aseguradora.string' => 'La aseguradora debe ser texto',
            'poliza.string' => 'La póliza debe ser texto',
            'telefonoEmergencias.string' => 'El teléfono de emergencias debe ser texto',
            'boolean' => 'El campo :attribute debe ser un valor booleano',
        ];
    }

    /**
     * Normaliza el paso antes de persistir:
     *  - encargado-retiro vacio (sin nombre/telefono/relacion) se guarda como null
     *    para no materializar objetos fantasma en `pickup_contact`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(string $stepId, array $data): array
    {
        if ($stepId === 'encargado-retiro' && is_array($data['encargadoRetiro'] ?? null)) {
            $data['encargadoRetiro'] = $this->pickupFilled($data['encargadoRetiro'])
                ? $data['encargadoRetiro']
                : null;
        }

        return $data;
    }

    /** @param  array<string, mixed>  $pickup */
    private function pickupFilled(array $pickup): bool
    {
        return trim((string) ($pickup['nombre'] ?? '')) !== ''
            || trim((string) ($pickup['telefono'] ?? '')) !== ''
            || trim((string) ($pickup['relacion'] ?? '')) !== '';
    }
}