<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSettingScratchTest extends TestCase
{
    use RefreshDatabase;

    public function test_instantiate_empty_platform_setting(): void
    {
        $model = new PlatformSetting();
        $this->assertTrue($model->getFillable() !== []);
    }

    public function test_instantiate_order_control(): void
    {
        $model = new Order();
        $this->assertTrue($model->getFillable() !== []);
    }

    public function test_query_builder_works(): void
    {
        $row = PlatformSetting::query()->limit(1)->get();
        $this->assertCount(0, $row);
    }
}