<?php

namespace Tests\Integration;

use App\Domain\Cadastre\Geometry\GeometryValidator;
use App\Models\Dataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GeometryValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_polygon_is_normalized_to_dataset_precision(): void
    {
        $dataset = $this->dataset(precisionGrid: 0.001);
        $validator = app(GeometryValidator::class);

        $result = $validator->validate($dataset, 22182, [
            'type' => 'Polygon',
            'coordinates' => [[
                [2534000.12349, 6364000.25049],
                [2534010.12349, 6364000.25049],
                [2534010.12349, 6364010.25049],
                [2534000.12349, 6364010.25049],
                [2534000.12349, 6364000.25049],
            ]],
        ]);

        self::assertTrue($result->valid);
        self::assertSame([], $result->errors);
        self::assertNotNull($result->normalizedGeometry);
        self::assertSame('Polygon', $result->normalizedGeometry['type']);

        $ring = $result->normalizedGeometry['coordinates'][0];
        $containsExpectedCorner = false;

        foreach ($ring as $coordinate) {
            if (
                abs((float) $coordinate[0] - 2534000.123) <= 0.0000001
                && abs((float) $coordinate[1] - 6364000.250) <= 0.0000001
            ) {
                $containsExpectedCorner = true;
                break;
            }
        }

        self::assertTrue(
            $containsExpectedCorner,
            'Precision reduction must round the coordinate without requiring PostGIS to preserve ring start order.',
        );
    }

    public function test_self_intersection_returns_stable_code_and_problem_coordinate(): void
    {
        $dataset = $this->dataset();
        $validator = app(GeometryValidator::class);

        $result = $validator->validate($dataset, 22182, [
            'type' => 'Polygon',
            'coordinates' => [[
                [0, 0],
                [10, 10],
                [0, 10],
                [10, 0],
                [0, 0],
            ]],
        ]);

        self::assertFalse($result->valid);
        self::assertNull($result->normalizedGeometry);
        self::assertSame('SELF_INTERSECTION', $result->errors[0]['code']);
        self::assertEqualsWithDelta(5.0, $result->errors[0]['coordinate'][0], 0.0000001);
        self::assertEqualsWithDelta(5.0, $result->errors[0]['coordinate'][1], 0.0000001);
    }

    public function test_holes_are_rejected_when_dataset_policy_disallows_them(): void
    {
        $dataset = $this->dataset(allowHoles: false);
        $validator = app(GeometryValidator::class);

        $result = $validator->validate($dataset, 22182, [
            'type' => 'Polygon',
            'coordinates' => [
                [[0, 0], [20, 0], [20, 20], [0, 20], [0, 0]],
                [[5, 5], [5, 10], [10, 10], [10, 5], [5, 5]],
            ],
        ]);

        self::assertFalse($result->valid);
        self::assertSame('HOLES_NOT_ALLOWED', $result->errors[0]['code']);
    }

    public function test_srid_mismatch_is_rejected_before_geometry_is_accepted(): void
    {
        $dataset = $this->dataset();
        $validator = app(GeometryValidator::class);

        $result = $validator->validate($dataset, 4326, [
            'type' => 'Polygon',
            'coordinates' => [[
                [-68.8, -32.9],
                [-68.7, -32.9],
                [-68.7, -32.8],
                [-68.8, -32.8],
                [-68.8, -32.9],
            ]],
        ]);

        self::assertFalse($result->valid);
        self::assertSame('SRID_MISMATCH', $result->errors[0]['code']);
        self::assertSame(22182, $result->errors[0]['details']['expected_srid']);
        self::assertSame(4326, $result->errors[0]['details']['actual_srid']);
    }

    private function dataset(
        float $precisionGrid = 0.001,
        bool $allowHoles = false,
        bool $allowMultipolygon = false,
    ): Dataset {
        return Dataset::query()->create([
            'external_key' => 'test-dataset',
            'name' => 'Test dataset',
            'srid' => 22182,
            'precision_grid' => $precisionGrid,
            'area_tolerance' => 0.0001,
            'minimum_area' => null,
            'allow_holes' => $allowHoles,
            'allow_multipolygon' => $allowMultipolygon,
        ]);
    }
}
