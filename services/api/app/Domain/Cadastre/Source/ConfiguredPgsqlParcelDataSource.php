<?php

namespace App\Domain\Cadastre\Source;

use App\Domain\Cadastre\Geometry\BoundingBox;
use App\Support\ApiProblemException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use JsonException;

final class ConfiguredPgsqlParcelDataSource implements ParcelDataSource
{
    private const SOURCE_ALIAS = 'p';

    public function find(string $externalId): ?ParcelFeature
    {
        $config = $this->configuration();
        $table = $this->quoteTable($config['table']);
        $id = $this->qualifiedIdentifier($config['id_column']);
        $geom = $this->qualifiedIdentifier($config['geometry_column']);
        $revision = $this->optionalSelect($config['revision_column'], 'revision');
        $label = $config['label_column'] !== null && $config['label_column'] !== ''
            ? $this->qualifiedIdentifier($config['label_column']).'::text AS label'
            : $id.'::text AS label';

        [$activeSql, $activeBindings] = $this->activePredicate($config);

        $sql = <<<SQL
            SELECT {$id}::text AS external_id,
                   {$revision},
                   {$label},
                   ST_SRID({$geom}) AS srid,
                   ST_GeometryType({$geom}) AS geometry_type,
                   ST_AsGeoJSON({$geom}, 9)::jsonb AS geometry
            FROM {$table} AS p
            WHERE {$id}::text = ?
              AND {$geom} IS NOT NULL
              AND NOT ST_IsEmpty({$geom})
              {$activeSql}
            LIMIT 1
            SQL;

        $row = $this->connection()->selectOne($sql, [$externalId, ...$activeBindings]);

        return $row === null ? null : $this->hydrate($row);
    }

    /** @return list<ParcelFeature> */
    public function context(BoundingBox $bbox, int $limit = 2000): array
    {
        $config = $this->configuration();
        $maxLimit = $config['context_limit'];

        if ($limit < 1 || $limit > $maxLimit) {
            throw new ApiProblemException(
                422,
                'CONTEXT_LIMIT_EXCEEDED',
                "Context limit must be between 1 and {$maxLimit}.",
                ['max_limit' => $maxLimit, 'requested_limit' => $limit],
            );
        }

        $table = $this->quoteTable($config['table']);
        $id = $this->qualifiedIdentifier($config['id_column']);
        $geom = $this->qualifiedIdentifier($config['geometry_column']);
        $revision = $this->optionalSelect($config['revision_column'], 'revision');
        $label = $config['label_column'] !== null && $config['label_column'] !== ''
            ? $this->qualifiedIdentifier($config['label_column']).'::text AS label'
            : $id.'::text AS label';

        [$activeSql, $activeBindings] = $this->activePredicate($config);

        $sql = <<<SQL
            WITH bounds AS (
                SELECT ST_MakeEnvelope(?, ?, ?, ?, ?) AS geom
            )
            SELECT {$id}::text AS external_id,
                   {$revision},
                   {$label},
                   ST_SRID({$geom}) AS srid,
                   ST_GeometryType({$geom}) AS geometry_type,
                   ST_AsGeoJSON({$geom}, 9)::jsonb AS geometry
            FROM {$table} AS p
            CROSS JOIN bounds
            WHERE {$geom} IS NOT NULL
              AND NOT ST_IsEmpty({$geom})
              AND {$geom} && bounds.geom
              AND ST_Intersects({$geom}, bounds.geom)
              {$activeSql}
            ORDER BY {$id}::text
            LIMIT ?
            SQL;

        $bindings = [
            $bbox->minX,
            $bbox->minY,
            $bbox->maxX,
            $bbox->maxY,
            $config['srid'],
            ...$activeBindings,
            $limit,
        ];

        return array_map(
            fn (object $row): ParcelFeature => $this->hydrate($row),
            $this->connection()->select($sql, $bindings),
        );
    }

    private function connection(): ConnectionInterface
    {
        return DB::connection(config('database.default'));
    }

    /**
     * @return array{
     *   table:string,id_column:string,geometry_column:string,revision_column:?string,label_column:?string,
     *   active_column:?string,active_value:mixed,srid:int,context_limit:int
     * }
     */
    private function configuration(): array
    {
        $table = config('cadastral.parcels.table');
        $id = config('cadastral.parcels.id_column');
        $geometry = config('cadastral.parcels.geometry_column');
        $srid = (int) config('cadastral.parcels.srid', 0);
        $contextLimit = (int) config('cadastral.parcels.context_limit', 2000);

        if (! is_string($table) || trim($table) === '' || ! is_string($id) || trim($id) === '' || ! is_string($geometry) || trim($geometry) === '' || $srid <= 0) {
            throw new ApiProblemException(
                500,
                'CADASTRAL_DATASOURCE_NOT_CONFIGURED',
                'Cadastral parcel datasource requires table, id column, geometry column and positive SRID configuration.',
            );
        }

        return [
            'table' => trim($table),
            'id_column' => trim($id),
            'geometry_column' => trim($geometry),
            'revision_column' => $this->nullableString(config('cadastral.parcels.revision_column')),
            'label_column' => $this->nullableString(config('cadastral.parcels.label_column')),
            'active_column' => $this->nullableString(config('cadastral.parcels.active_column')),
            'active_value' => config('cadastral.parcels.active_value', '1'),
            'srid' => $srid,
            'context_limit' => max(1, $contextLimit),
        ];
    }

    /** @return array{0:string,1:list<mixed>} */
    private function activePredicate(array $config): array
    {
        if ($config['active_column'] === null) {
            return ['', []];
        }

        return [
            'AND '.$this->qualifiedIdentifier($config['active_column']).' = ?',
            [$config['active_value']],
        ];
    }

    private function optionalSelect(?string $column, string $alias): string
    {
        if ($column === null || $column === '') {
            return 'NULL::bigint AS '.$alias;
        }

        return $this->qualifiedIdentifier($column).'::bigint AS '.$alias;
    }

    private function quoteTable(string $table): string
    {
        $parts = explode('.', $table);
        if (count($parts) < 1 || count($parts) > 2) {
            throw $this->invalidIdentifier($table);
        }

        return implode('.', array_map(fn (string $part): string => $this->quoteIdentifier($part), $parts));
    }

    private function qualifiedIdentifier(string $identifier): string
    {
        return self::SOURCE_ALIAS.'.'.$this->quoteIdentifier($identifier);
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw $this->invalidIdentifier($identifier);
        }

        return '"'.$identifier.'"';
    }

    private function invalidIdentifier(string $identifier): ApiProblemException
    {
        return new ApiProblemException(
            500,
            'INVALID_CADASTRAL_IDENTIFIER',
            'Invalid cadastral SQL identifier: '.$identifier,
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function hydrate(object $row): ParcelFeature
    {
        $type = (string) $row->geometry_type;
        if ($type !== 'ST_Polygon' && $type !== 'ST_MultiPolygon') {
            throw new ApiProblemException(
                422,
                'UNSUPPORTED_SOURCE_GEOMETRY',
                'Configured parcel source returned a non-polygonal geometry.',
                ['geometry_type' => $type, 'external_id' => (string) $row->external_id],
            );
        }

        try {
            $geometry = is_array($row->geometry)
                ? $row->geometry
                : json_decode((string) $row->geometry, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ApiProblemException(
                500,
                'INVALID_SOURCE_GEOMETRY_JSON',
                'Configured parcel source geometry could not be serialized as GeoJSON.',
                ['external_id' => (string) $row->external_id],
                $exception,
            );
        }

        if (! is_array($geometry)) {
            throw new ApiProblemException(500, 'INVALID_SOURCE_GEOMETRY_JSON', 'Configured parcel source returned invalid GeoJSON.');
        }

        return new ParcelFeature(
            externalId: (string) $row->external_id,
            revision: $row->revision === null ? null : (int) $row->revision,
            label: (string) $row->label,
            srid: (int) $row->srid,
            geometry: $geometry,
        );
    }
}
