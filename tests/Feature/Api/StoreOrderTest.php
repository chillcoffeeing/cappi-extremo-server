<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * F-058 (api-A-2 / fe-A-2 / fe-A-6 / api-A-13): POST /ordenes resuelve
 * precio, nombre, variante y stock en el servidor. Incluye los tests de
 * compatibilidad de progress/compat-rules-2026-10-19.md (shape viejo de un
 * portal cacheado, órdenes existentes intactas).
 */
class StoreOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Franela Xtremo',
            'category' => 'Ropa',
            'price' => 25.5,
            'variants' => ['S', 'M', 'L'],
            'images' => ['product-images/franela.jpg'],
            'description' => 'Franela oficial',
            'in_stock' => true,
        ], $overrides));
    }

    // ---- Shape nuevo ---------------------------------------------------

    public function test_new_shape_takes_price_and_name_from_catalog(): void
    {
        $franela = $this->product();

        $response = $this->postJson('/api/ordenes', ['items' => [
            ['productoId' => $franela->uuid, 'variante' => 'L', 'qty' => 2],
        ]])->assertCreated();

        $response->assertJsonPath('data.total', 51)
            ->assertJsonPath('data.abonado', 0)
            ->assertJsonPath('data.estado', 'PENDIENTE_PAGO')
            ->assertJsonPath('data.esInscripcion', false)
            ->assertJsonPath('data.items.0.productoId', $franela->uuid)
            ->assertJsonPath('data.items.0.nombre', 'Franela Xtremo')
            ->assertJsonPath('data.items.0.variante', 'L')
            ->assertJsonPath('data.items.0.qty', 2)
            ->assertJsonPath('data.items.0.precio', 25.5);

        $this->assertSame('51.0000', Order::sole()->total);
    }

    public function test_client_price_and_name_are_ignored_in_new_shape(): void
    {
        $franela = $this->product();

        $this->postJson('/api/ordenes', ['items' => [
            ['productoId' => $franela->uuid, 'nombre' => 'Otra cosa', 'variante' => 'M', 'qty' => 1, 'precio' => 0.01],
        ]])->assertCreated()
            ->assertJsonPath('data.total', 25.5)
            ->assertJsonPath('data.items.0.nombre', 'Franela Xtremo')
            ->assertJsonPath('data.items.0.precio', 25.5);
    }

    public function test_product_without_variants_can_be_bought(): void
    {
        $botella = $this->product(['name' => 'Botella', 'price' => 12, 'variants' => null, 'images' => null]);

        $this->postJson('/api/ordenes', ['items' => [
            ['productoId' => $botella->uuid, 'variante' => null, 'qty' => 3],
        ]])->assertCreated()
            ->assertJsonPath('data.total', 36)
            ->assertJsonPath('data.items.0.variante', null);

        // Variantes vacías ([]) y la cadena vacía del portal se tratan igual.
        $vaso = $this->product(['name' => 'Vaso', 'price' => 5, 'variants' => []]);
        $this->postJson('/api/ordenes', ['items' => [
            ['productoId' => $vaso->uuid, 'variante' => '', 'qty' => 1],
        ]])->assertCreated()->assertJsonPath('data.total', 5);
    }

    public function test_unknown_product_id_is_422(): void
    {
        $this->postJson('/api/ordenes', ['items' => [
            ['productoId' => '9b1d7a52-0000-4000-8000-000000000000', 'variante' => 'M', 'qty' => 1],
        ]])->assertStatus(422)
            ->assertJsonPath('message', 'Uno de los productos del carrito ya no está en la tienda. Actualiza la página e intenta de nuevo.')
            ->assertJsonValidationErrors(['items.0.productoId']);

        // Un id con formato raro (p. ej. número del tipo viejo del portal) tampoco da 500.
        $this->postJson('/api/ordenes', ['items' => [['productoId' => '123', 'qty' => 1]]])
            ->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_invalid_variant_is_422(): void
    {
        $franela = $this->product();

        $this->postJson('/api/ordenes', ['items' => [
            ['productoId' => $franela->uuid, 'variante' => 'XXL', 'qty' => 1],
        ]])->assertStatus(422)
            ->assertJsonPath('message', '«Franela Xtremo» no tiene la opción «XXL». Actualiza la página e intenta de nuevo.')
            ->assertJsonValidationErrors(['items.0.variante']);

        $this->assertSame(0, Order::count());
    }

    public function test_missing_variant_when_product_has_variants_is_422(): void
    {
        $franela = $this->product();

        $this->postJson('/api/ordenes', ['items' => [
            ['productoId' => $franela->uuid, 'qty' => 1],
        ]])->assertStatus(422)
            ->assertJsonPath('message', 'Elige una opción de «Franela Xtremo» antes de crear el pedido.');
    }

    public function test_variant_on_product_without_variants_is_422(): void
    {
        $botella = $this->product(['name' => 'Botella', 'variants' => null]);

        $this->postJson('/api/ordenes', ['items' => [
            ['productoId' => $botella->uuid, 'variante' => 'M', 'qty' => 1],
        ]])->assertStatus(422)->assertJsonValidationErrors(['items.0.variante']);
    }

    public function test_out_of_stock_product_is_422(): void
    {
        $franela = $this->product(['in_stock' => false]);

        $this->postJson('/api/ordenes', ['items' => [
            ['productoId' => $franela->uuid, 'variante' => 'S', 'qty' => 1],
        ]])->assertStatus(422)
            ->assertJsonPath('message', '«Franela Xtremo» no tiene stock en este momento. Quítalo del carrito para continuar.');
    }

    public function test_huge_qty_is_422_and_never_500(): void
    {
        $franela = $this->product();

        $this->postJson('/api/ordenes', ['items' => [
            ['productoId' => $franela->uuid, 'variante' => 'M', 'qty' => 999999999],
        ]])->assertStatus(422)
            ->assertJsonPath('message', 'Puedes pedir hasta 99 unidades de cada producto por pedido.')
            ->assertJsonValidationErrors(['items.0.qty']);

        // Shape viejo con qty y precio enormes (el caso que en MySQL daba 500).
        $this->postJson('/api/ordenes', ['items' => [
            ['nombre' => 'Franela Xtremo', 'variante' => 'M', 'qty' => 999999999, 'precio' => 99999999],
        ]])->assertStatus(422)->assertJsonValidationErrors(['items.0.qty']);

        // Dos líneas iguales que juntas pasan el tope.
        $this->postJson('/api/ordenes', ['items' => [
            ['productoId' => $franela->uuid, 'variante' => 'M', 'qty' => 60],
            ['productoId' => $franela->uuid, 'variante' => 'M', 'qty' => 60],
        ]])->assertStatus(422)->assertJsonValidationErrors(['items.1.qty']);

        $this->postJson('/api/ordenes', ['items' => [
            ['productoId' => $franela->uuid, 'variante' => 'M', 'qty' => 0],
        ]])->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_repeated_lines_are_merged(): void
    {
        $franela = $this->product();

        $this->postJson('/api/ordenes', ['items' => [
            ['productoId' => $franela->uuid, 'variante' => 'M', 'qty' => 1],
            ['productoId' => $franela->uuid, 'variante' => 'M', 'qty' => 2],
            ['productoId' => $franela->uuid, 'variante' => 'L', 'qty' => 1],
        ]])->assertCreated()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.qty', 3)
            ->assertJsonPath('data.total', 102);
    }

    public function test_input_limits_match_mysql_columns(): void
    {
        $franela = $this->product();

        $this->postJson('/api/ordenes', ['items' => [
            ['productoId' => $franela->uuid, 'variante' => str_repeat('x', 256), 'qty' => 1],
        ]])->assertStatus(422)->assertJsonValidationErrors(['items.0.variante']);

        $this->postJson('/api/ordenes', ['items' => [
            ['nombre' => str_repeat('x', 256), 'qty' => 1],
        ]])->assertStatus(422)->assertJsonValidationErrors(['items.0.nombre']);

        $this->postJson('/api/ordenes', ['items' => array_fill(0, 21, ['productoId' => $franela->uuid, 'variante' => 'M', 'qty' => 1])])
            ->assertStatus(422)->assertJsonValidationErrors(['items']);

        $this->postJson('/api/ordenes', ['items' => ['no-es-un-objeto']])->assertStatus(422);
    }

    // ---- Compatibilidad: shape viejo (portal cacheado) ------------------

    public function test_old_shape_with_tampered_price_gets_catalog_price(): void
    {
        $franela = $this->product();

        $this->postJson('/api/ordenes', ['items' => [
            ['nombre' => 'Franela Xtremo', 'variante' => 'M', 'qty' => 3, 'precio' => 0],
        ]])->assertCreated()
            ->assertJsonPath('data.total', 76.5)
            ->assertJsonPath('data.items.0.productoId', $franela->uuid)
            ->assertJsonPath('data.items.0.precio', 25.5)
            ->assertJsonPath('data.items.0.variante', 'M');

        // Precio negativo o no numérico: se ignora igual, no da 422 ni 500.
        $this->postJson('/api/ordenes', ['items' => [
            ['nombre' => 'Franela Xtremo', 'variante' => 'S', 'qty' => 1, 'precio' => -5],
            ['nombre' => 'Franela Xtremo', 'variante' => 'L', 'qty' => 1, 'precio' => 'gratis'],
        ]])->assertCreated()->assertJsonPath('data.total', 51);
    }

    public function test_old_shape_unknown_product_is_422_with_clear_message(): void
    {
        $this->postJson('/api/ordenes', ['items' => [
            ['nombre' => 'Producto borrado', 'variante' => 'M', 'qty' => 1, 'precio' => 10],
        ]])->assertStatus(422)
            ->assertJsonPath('message', '«Producto borrado» ya no está en la tienda. Actualiza la página e intenta de nuevo.')
            ->assertJsonValidationErrors(['items.0.nombre']);

        $this->assertSame(0, Order::count());
    }

    public function test_old_shape_invalid_variant_is_422(): void
    {
        $this->product();

        $this->postJson('/api/ordenes', ['items' => [
            ['nombre' => 'Franela Xtremo', 'variante' => 'XXL', 'qty' => 1, 'precio' => 25.5],
        ]])->assertStatus(422)->assertJsonValidationErrors(['items.0.variante']);
    }

    public function test_old_shape_product_without_variants_works(): void
    {
        $this->product(['name' => 'Botella', 'price' => 12, 'variants' => null, 'images' => []]);

        // El portal viejo manda `variante: ""` (ConvertEmptyStringsToNull -> null).
        $this->postJson('/api/ordenes', ['items' => [
            ['nombre' => 'Botella', 'variante' => '', 'qty' => 2, 'precio' => 1],
        ]])->assertCreated()
            ->assertJsonPath('data.total', 24)
            ->assertJsonPath('data.items.0.variante', null);
    }

    public function test_old_shape_duplicate_names_are_disambiguated_by_variant_or_rejected(): void
    {
        $adulto = $this->product(['name' => 'Gorra', 'price' => 15, 'variants' => ['Adulto']]);
        $this->product(['name' => 'Gorra', 'price' => 10, 'variants' => ['Niño']]);

        $this->postJson('/api/ordenes', ['items' => [
            ['nombre' => 'Gorra', 'variante' => 'Adulto', 'qty' => 1, 'precio' => 1],
        ]])->assertCreated()
            ->assertJsonPath('data.items.0.productoId', $adulto->uuid)
            ->assertJsonPath('data.total', 15);

        $this->product(['name' => 'Gorra', 'price' => 99, 'variants' => ['Adulto']]);
        $this->postJson('/api/ordenes', ['items' => [
            ['nombre' => 'Gorra', 'variante' => 'Adulto', 'qty' => 1, 'precio' => 1],
        ]])->assertStatus(422)
            ->assertJsonPath('message', 'No pudimos identificar «Gorra» en la tienda. Actualiza la página e intenta de nuevo.');
    }

    public function test_existing_orders_are_not_repriced_or_touched(): void
    {
        $franela = $this->product();
        // Orden vieja (anterior a F-058): sin productoId y con el precio que mandó el cliente.
        $old = $this->user->orders()->create([
            'user_uuid' => $this->user->uuid,
            'ordered_at' => '2026-10-01',
            'items' => [['nombre' => 'Franela Xtremo', 'variante' => 'S', 'qty' => 1, 'precio' => 20]],
            'total' => 20,
            'paid' => 20,
            'status' => 'PAGADA',
            'is_registration' => false,
        ]);

        $franela->update(['price' => 30]);
        $this->postJson('/api/ordenes', ['items' => [
            ['productoId' => $franela->uuid, 'variante' => 'S', 'qty' => 1],
        ]])->assertCreated()->assertJsonPath('data.total', 30);

        $old->refresh();
        $this->assertSame('20.0000', $old->total);
        $this->assertSame('20.0000', $old->paid);
        $this->assertSame('PAGADA', $old->status);
        $this->assertSame([['nombre' => 'Franela Xtremo', 'variante' => 'S', 'qty' => 1, 'precio' => 20]], $old->items);

        // GET /ordenes sigue sirviendo la orden vieja con su shape (sin productoId).
        $this->getJson('/api/ordenes')->assertOk()
            ->assertJsonFragment(['id' => $old->uuid, 'total' => 20, 'estado' => 'PAGADA'])
            ->assertJsonMissingPath('data.1.items.0.productoId');
    }

    public function test_product_resource_shape_is_unchanged(): void
    {
        $this->product(['images' => null, 'variants' => null]);

        $this->getJson('/api/tienda/productos')->assertOk()
            ->assertJsonStructure(['data' => [['id', 'nombre', 'categoria', 'precio', 'precioAnterior', 'variantes', 'imagenes', 'descripcion', 'sinStock']]])
            ->assertJsonPath('data.0.variantes', [])
            ->assertJsonPath('data.0.imagenes', []);
    }
}
