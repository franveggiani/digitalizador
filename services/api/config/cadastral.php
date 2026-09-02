<?php

return [
    'parcels' => [
        'table' => env('CADASTRAL_PARCELS_TABLE'),
        'id_column' => env('CADASTRAL_PARCELS_ID_COLUMN'),
        'geometry_column' => env('CADASTRAL_PARCELS_GEOMETRY_COLUMN', 'geom'),
        'revision_column' => env('CADASTRAL_PARCELS_REVISION_COLUMN'),
        'label_column' => env('CADASTRAL_PARCELS_LABEL_COLUMN'),
        'active_column' => env('CADASTRAL_PARCELS_WHERE_ACTIVE_COLUMN'),
        'active_value' => env('CADASTRAL_PARCELS_WHERE_ACTIVE_VALUE', '1'),
        'srid' => (int) env('CADASTRAL_PARCELS_SRID', 0),
        'context_limit' => (int) env('CADASTRAL_PARCELS_CONTEXT_LIMIT', 2000),
    ],
];
