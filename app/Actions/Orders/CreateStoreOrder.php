<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * F-058 (api-A-2 / fe-A-2 / fe-A-6): crea un pedido de tienda resolviendo
 * precio, nombre, variante y disponibilidad desde `products`. El precio que
 * mande el cliente NUNCA se usa.
 *
 * Acepta dos shapes por ítem:
 *  - nuevo: `{productoId, variante, qty}`;
 *  - viejo (portal cacheado anterior a F-058): `{nombre, variante, qty, precio}`.
 *    Se resuelve por nombre (y variante para desambiguar) y se ignora `precio`.
 *
 * Cualquier ítem que no se pueda resolver con certeza -> 422 con un mensaje
 * en español (nunca 500). Solo afecta pedidos NUEVOS: las órdenes existentes
 * no se tocan ni se re-precian.
 */
class CreateStoreOrder
{
    /** Tope por línea (mismo valor que StoreOrderRequest y el portal). */
    public const MAX_QTY = 99;

    /** Tope de líneas por pedido. */
    public const MAX_ITEMS = 20;

    /** `orders.total` es decimal(16,4): 12 dígitos enteros. */
    private const MAX_TOTAL = 999_999_999_999.0;

    private const REFRESH_HINT = 'Actualiza la página e intenta de nuevo.';

    /**
     * @param  array<int, array<string, mixed>>  $items  ítems ya validados por StoreOrderRequest
     */
    public function handle(User $user, array $items): Order
    {
        /** @var array<string, array{product: Product, variante: ?string, qty: int, index: int}> $lines */
        $lines = [];

        foreach (array_values($items) as $index => $item) {
            $variante = $this->normalizeText($item['variante'] ?? null);
            $product = $this->resolveProduct($item, $variante, $index);

            if (! $product->in_stock) {
                $this->fail($index, "«{$product->name}» no tiene stock en este momento. Quítalo del carrito para continuar.");
            }

            $variante = $this->resolveVariant($product, $variante, $index);
            $qty = (int) $item['qty'];

            // Mismo producto y variante en dos líneas -> una sola línea.
            $key = $product->getKey().'|'.($variante ?? '');
            if (isset($lines[$key])) {
                $lines[$key]['qty'] += $qty;
            } else {
                $lines[$key] = ['product' => $product, 'variante' => $variante, 'qty' => $qty, 'index' => $index];
            }

            if ($lines[$key]['qty'] > self::MAX_QTY) {
                $this->fail($index, 'Puedes pedir hasta '.self::MAX_QTY." unidades de «{$product->name}» por pedido.", 'qty');
            }
        }

        $orderItems = [];
        $total = 0.0;
        foreach ($lines as $line) {
            $price = round((float) $line['product']->price, 4);
            $orderItems[] = [
                'productoId' => $line['product']->uuid,
                'nombre' => $line['product']->name,
                'variante' => $line['variante'],
                'qty' => $line['qty'],
                'precio' => $price,
            ];
            $total = round($total + round($price * $line['qty'], 4), 4);
        }

        if ($total > self::MAX_TOTAL) {
            throw ValidationException::withMessages([
                'items' => 'El total del pedido es demasiado alto. Reduce las cantidades e intenta de nuevo.',
            ]);
        }

        return DB::transaction(fn (): Order => $user->orders()->create([
            'user_uuid' => $user->uuid,
            'ordered_at' => now()->toDateString(),
            'items' => $orderItems,
            'total' => $total,
            'paid' => 0,
            'status' => 'PENDIENTE_PAGO',
            'is_registration' => false,
        ]));
    }

    /** @param  array<string, mixed>  $item */
    private function resolveProduct(array $item, ?string $variante, int $index): Product
    {
        $productoId = $this->normalizeText($item['productoId'] ?? null);

        if ($productoId !== null) {
            $product = Str::isUuid($productoId) ? Product::query()->where('uuid', $productoId)->first() : null;

            if ($product === null) {
                $this->fail($index, 'Uno de los productos del carrito ya no está en la tienda. '.self::REFRESH_HINT, 'productoId');
            }

            return $product;
        }

        // Shape viejo: se busca por nombre. El precio del cliente se ignora.
        $nombre = $this->normalizeText($item['nombre'] ?? null);
        if ($nombre === null) {
            $this->fail($index, 'Falta el producto en uno de los ítems del carrito. '.self::REFRESH_HINT, 'productoId');
        }

        /** @var Collection<int, Product> $candidates */
        $candidates = Product::query()->where('name', $nombre)->get();

        if ($candidates->count() > 1) {
            // Dos productos con el mismo nombre: la variante decide si es única.
            $candidates = $candidates->filter(
                fn (Product $product): bool => $variante === null
                    ? $this->variantsOf($product) === []
                    : in_array($variante, $this->variantsOf($product), true)
            )->values();
        }

        if ($candidates->count() === 0) {
            $this->fail($index, "«{$nombre}» ya no está en la tienda. ".self::REFRESH_HINT, 'nombre');
        }

        if ($candidates->count() > 1) {
            $this->fail($index, "No pudimos identificar «{$nombre}» en la tienda. ".self::REFRESH_HINT, 'nombre');
        }

        return $candidates->first();
    }

    private function resolveVariant(Product $product, ?string $variante, int $index): ?string
    {
        $variants = $this->variantsOf($product);

        if ($variants === []) {
            if ($variante !== null) {
                $this->fail($index, "«{$product->name}» no tiene la opción «{$variante}». ".self::REFRESH_HINT, 'variante');
            }

            return null;
        }

        if ($variante === null) {
            $this->fail($index, "Elige una opción de «{$product->name}» antes de crear el pedido.", 'variante');
        }

        if (! in_array($variante, $variants, true)) {
            $this->fail($index, "«{$product->name}» no tiene la opción «{$variante}». ".self::REFRESH_HINT, 'variante');
        }

        return $variante;
    }

    /** @return list<string> variantes no vacías del producto, recortadas. */
    private function variantsOf(Product $product): array
    {
        return collect($product->variants ?? [])
            ->filter(fn ($variant): bool => is_scalar($variant))
            ->map(fn ($variant): string => trim((string) $variant))
            ->filter(fn (string $variant): bool => $variant !== '')
            ->values()
            ->all();
    }

    private function normalizeText(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function fail(int $index, string $message, string $field = 'productoId'): never
    {
        throw ValidationException::withMessages(["items.{$index}.{$field}" => $message]);
    }
}
