<?php

namespace App\Http\Requests;

use App\Actions\Orders\CreateStoreOrder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-058 (api-A-2, api-A-13): el cliente solo elige producto, variante y
 * cantidad. `precio` (shape viejo) se acepta pero se ignora: no se valida ni
 * se usa. Topes coherentes con las columnas de MySQL (varchar 255,
 * decimal(16,4)) para que nada llegue a la BD y dé 500.
 */
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:'.CreateStoreOrder::MAX_ITEMS],
            'items.*' => ['required', 'array'],
            // Shape nuevo.
            'items.*.productoId' => ['nullable', 'string', 'max:64'],
            // Shape viejo (portal cacheado): nombre obligatorio si no hay productoId.
            'items.*.nombre' => ['nullable', 'required_without:items.*.productoId', 'string', 'max:255'],
            'items.*.variante' => ['nullable', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:'.CreateStoreOrder::MAX_QTY],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.max' => 'Un pedido puede tener hasta '.CreateStoreOrder::MAX_ITEMS.' productos distintos.',
            'items.*.qty.max' => 'Puedes pedir hasta '.CreateStoreOrder::MAX_QTY.' unidades de cada producto por pedido.',
            'items.*.qty.min' => 'La cantidad de cada producto debe ser al menos 1.',
            'items.*.nombre.required_without' => 'Falta el producto en uno de los ítems del carrito. Actualiza la página e intenta de nuevo.',
        ];
    }
}
