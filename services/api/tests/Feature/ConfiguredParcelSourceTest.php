<?php

namespace Tests\Feature;

use App\Domain\Cadastre\Geometry\BoundingBox;
use App\Domain\Cadastre\Source\ConfiguredPgsqlParcelDataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ConfiguredParcelSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cadastral.parcels.table' => 'source_parcels',
            'cadastral.parcels.id_column' => 'parcel_key',
            'cadastral.parcels.geometry_column' => 'geom',
            'cadastral.parcels.revision_column' => 'rev',
            'cadastral.parcels.label_column' => 'display_name',
            'cadastral.parcels.active_column' => 'active',
            'cadastral.parcels.active_value' => '1',
            'cadastral.parcels.srid' => 22182,
            'cadastral.parcels.context_limit' => 2000,
        ]);

        DB::statement(<<<'SQL'
            CREATE TABLE source_parcels (
                parcel_key text PRIMARY KEY,
                rev bigint,
                display_name text,
                active integer NOT NULL DEFAULT 1,
                geom geometry(Polygon, 22182)
            )
            SQL);

        $this->insertParcel('NEAR', 5, 'Parcela cercana', 1, 2534000, 6364000);
        $this->insertParcel('FAR', 7, 'Parcela lejana', 1, 2535000, 6365000);
        $this->insertParcel('INACTIVE', 3, 'Parcela inactiva', 0, 2534005, 6364005);
    }

    public function test_find_reads_the_configured_source_table_directly(): void
    {
        $source = app(ConfiguredPgsqlParcelDataSource::class);

        $parcel = $source->find('NEAR');

        self::assertNotNull($parcel);
        self::assertSame('NEAR', $parcel->externalId);
        self::assertSame(5, $parcel->revision);
        self::assertSame('Parcela cercana', $parcel->label);
        self::assertSame(22182, $parcel->srid);
        self::assertSame('Polygon', $parcel->geometry['type']);
        self::assertSame(2534000.0, (float) $parcel->geometry['coordinates'][0][0][0]);
    }

    public function test_context_reads_only_active_source_rows_intersecting_bbox(): void
    {
        $source = app(ConfiguredPgsqlParcelDataSource::class);

        $features = $source->context(new BoundingBox(2533990, 6363990, 2534020, 6364020), 100);

        self::assertCount(1, $features);
        self::assertSame('NEAR', $features[0]->externalId);
    }

    public function test_invalid_configured_identifier_is_rejected(): void
    {
        config(['cadastral.parcels.table' => 'source_parcels; DROP TABLE source_parcels']);

        $this->expectExceptionMessage('Invalid cadastral SQL identifier');

        app(ConfiguredPgsqlParcelDataSource::class)->find('NEAR');
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
            INSERT INTO source_parcels (parcel_key, rev, display_name, active, geom)
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
