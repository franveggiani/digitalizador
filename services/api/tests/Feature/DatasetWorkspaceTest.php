<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DatasetWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_define_an_independent_cadastral_dataset_policy(): void
    {
        $response = $this->putJson('/api/v1/datasets/guaymallen', [
            'name' => 'Catastro Guaymallen',
            'srid' => 22182,
            'precision_grid' => 0.001,
            'area_tolerance' => 0.0001,
            'minimum_area' => null,
            'allow_holes' => false,
            'allow_multipolygon' => false,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.external_key', 'guaymallen')
            ->assertJsonPath('data.srid', 22182)
            ->assertJsonPath('data.precision_grid', 0.001)
            ->assertJsonPath('data.allow_holes', false)
            ->assertJsonPath('data.allow_multipolygon', false);

        $this->assertDatabaseHas('datasets', [
            'external_key' => 'guaymallen',
            'srid' => 22182,
        ]);
    }
}
