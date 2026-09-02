<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ParcelContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_returns_only_active_parcels_intersecting_the_requested_bbox(): void
    {
        $this->createDataset();
        $this->putParcel('NEAR', 2534000, 6364000);
        $this->putParcel('FAR', 2535000, 6365000);

        $this->getJson('/api/v1/datasets/guaymallen/context?bbox=2533990,6363990,2534020,6364020')
            ->assertOk()
            ->assertJsonPath('data.type', 'FeatureCollection')
            ->assertJsonCount(1, 'data.features')
            ->assertJsonPath('data.features.0.id', 'NEAR')
            ->assertJsonPath('data.features.0.properties.external_id', 'NEAR')
            ->assertJsonPath('data.features.0.geometry.type', 'Polygon');
    }

    public function test_context_rejects_malformed_bbox_with_stable_error_code(): void
    {
        $this->createDataset();

        $this->getJson('/api/v1/datasets/guaymallen/context?bbox=1,2,3')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_BBOX');
    }

    private function createDataset(): void
    {
        $this->putJson('/api/v1/datasets/guaymallen', [
            'name' => 'Catastro Guaymallen',
            'srid' => 22182,
            'precision_grid' => 0.001,
            'area_tolerance' => 0.0001,
            'minimum_area' => null,
            'allow_holes' => false,
            'allow_multipolygon' => false,
        ])->assertCreated();
    }

    private function putParcel(string $externalId, float $x, float $y): void
    {
        $this->putJson('/api/v1/datasets/guaymallen/parcels/'.$externalId, [
            'srid' => 22182,
            'revision' => 1,
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [$x, $y],
                    [$x + 10, $y],
                    [$x + 10, $y + 10],
                    [$x, $y + 10],
                    [$x, $y],
                ]],
            ],
            'properties' => [],
        ])->assertCreated();
    }
}
