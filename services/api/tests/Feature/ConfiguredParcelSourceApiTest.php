<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ConfiguredParcelSourceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cadastral.parcels.table' => 'api_source_parcels',
            'cadastral.parcels.id_column' => 'parcel_key',
            'cadastral.parcels.geometry_column' => 'geom',
            'cadastral.parcels.revision_column' => 'rev',
            'cadastral.parcels.label_column' => 'display_name',
            'cadastral.parcels.active_column' => 'active',
            'cadastral.parcels.active_value' => '1',
            'cadastral.parcels.srid' => 22182,
            'cadastral.parcels.context_limit' => 50,
        ]);

        DB::statement(<<<'SQL'
            CREATE TABLE api_source_parcels (
                parcel_key text PRIMARY KEY,
                rev bigint,
                display_name text,
                active integer NOT NULL DEFAULT 1,
                geom geometry(Polygon, 22182)
            )
            SQL);

        $this->insertParcel('A/01', 8, 'Parcela A', 1, 2534000, 6364000);
        $this->insertParcel('B-02', 4, 'Parcela B', 1, 2535000, 6365000);
    }

    public function test_show_returns_authoritative_source_parcel(): void
    {
        $response = $this->getJson('/api/v1/parcels/'.rawurlencode('A/01'));

        $response->assertOk()
            ->assertJsonPath('data.external_id', 'A/01')
            ->assertJsonPath('data.revision', 8)
            ->assertJsonPath('data.label', 'Parcela A')
            ->assertJsonPath('data.srid', 22182)
            ->assertJsonPath('data.geometry.type', 'Polygon');
    }

    public function test_show_returns_stable_not_found_problem(): void
    {
        $this->getJson('/api/v1/parcels/UNKNOWN')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'PARCEL_NOT_FOUND');
    }

    public function test_context_returns_feature_collection_for_requested_bbox(): void
    {
        $response = $this->getJson('/api/v1/parcels/context?bbox=2533990,6363990,2534020,6364020&limit=25');

        $response->assertOk()
            ->assertJsonPath('data.type', 'FeatureCollection')
            ->assertJsonCount(1, 'data.features')
            ->assertJsonPath('data.features.0.id', 'A/01')
            ->assertJsonPath('data.features.0.properties.revision', 8);
    }

    public function test_context_rejects_invalid_bbox_and_limit(): void
    {
        $this->getJson('/api/v1/parcels/context?bbox=invalid')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_BBOX');

        $this->getJson('/api/v1/parcels/context?bbox=0,0,10,10&limit=999')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CONTEXT_LIMIT_EXCEEDED');
    }

    private function insertParcel(
        string $externalId,
        int $revision,
        string $label,
        int $active,
        float $x,
        float $y,
    ): void {
        DB::insert(<<<'SQL'
            INSERT INTO api_source_parcels (parcel_key, rev, display_name, active, geom)
            VALUES (?, ?, ?, ?, ST_GeomFromText(?, 22182))
            SQL, [
            $externalId,
            $revision,
            $label,
            $active,
            sprintf(
                'POLYGON((%1$f %2$f,%3$f %2$f,%3$f %4$f,%1$f %4$f,%1$f %2$f))',
                $x,
                $y,
                $x + 10,
                $y + 10,
            ),
        ]);
    }
}
