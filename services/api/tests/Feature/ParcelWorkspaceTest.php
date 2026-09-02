<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ParcelWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_upsert_and_fetch_a_precise_canonical_parcel(): void
    {
        $this->createDataset();

        $geometry = [
            'type' => 'Polygon',
            'coordinates' => [[
                [2534000.125, 6364000.250],
                [2534010.125, 6364000.250],
                [2534010.125, 6364010.250],
                [2534000.125, 6364010.250],
                [2534000.125, 6364000.250],
            ]],
        ];

        $this->putJson('/api/v1/datasets/guaymallen/parcels/PAD-0001', [
            'srid' => 22182,
            'revision' => 7,
            'geometry' => $geometry,
            'properties' => [
                'padron' => '0001',
                'nomenclatura' => '04-01-02-0001',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.external_id', 'PAD-0001')
            ->assertJsonPath('data.srid', 22182)
            ->assertJsonPath('data.revision', 7)
            ->assertJsonPath('data.geometry.type', 'Polygon')
            ->assertJsonPath('data.properties.padron', '0001');

        $this->getJson('/api/v1/datasets/guaymallen/parcels/PAD-0001')
            ->assertOk()
            ->assertJsonPath('data.external_id', 'PAD-0001')
            ->assertJsonPath('data.revision', 7)
            ->assertJsonPath('data.geometry.coordinates.0.1.0', 2534010.125);

        $stored = DB::selectOne(<<<'SQL'
            SELECT ST_SRID(geom) AS srid,
                   ST_GeometryType(geom) AS geometry_type,
                   ST_IsValid(geom) AS is_valid
            FROM parcels
            WHERE external_id = ?
            SQL, ['PAD-0001']);

        self::assertNotNull($stored);
        self::assertSame(22182, (int) $stored->srid);
        self::assertSame('ST_Polygon', $stored->geometry_type);
        self::assertTrue((bool) $stored->is_valid);
    }

    public function test_parcel_srid_must_match_dataset_srid(): void
    {
        $this->createDataset();

        $this->putJson('/api/v1/datasets/guaymallen/parcels/PAD-0002', [
            'srid' => 4326,
            'revision' => 1,
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [-68.8, -32.9],
                    [-68.7, -32.9],
                    [-68.7, -32.8],
                    [-68.8, -32.8],
                    [-68.8, -32.9],
                ]],
            ],
            'properties' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SRID_MISMATCH');
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
}
