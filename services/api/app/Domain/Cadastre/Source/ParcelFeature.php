<?php

namespace App\Domain\Cadastre\Source;

final readonly class ParcelFeature
{
    /** @param array<string, mixed> $geometry */
    public function __construct(
        public string $externalId,
        public ?int $revision,
        public string $label,
        public int $srid,
        public array $geometry,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'revision' => $this->revision,
            'label' => $this->label,
            'srid' => $this->srid,
            'geometry' => $this->geometry,
            'properties' => [],
        ];
    }

    /** @return array<string, mixed> */
    public function toGeoJsonFeature(): array
    {
        return [
            'type' => 'Feature',
            'id' => $this->externalId,
            'properties' => [
                'external_id' => $this->externalId,
                'revision' => $this->revision,
                'label' => $this->label,
                'srid' => $this->srid,
            ],
            'geometry' => $this->geometry,
        ];
    }
}
