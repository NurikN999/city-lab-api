<?php

namespace App\Simulation\Data;

final readonly class ActionDef
{
    /** @param list<EffectDef> $effects */
    public function __construct(
        public int $id,
        public string $key,
        public string $name,
        public int $cost,
        public string $scope,
        public array $effects,
        public string $assumption = '',
        public ?string $sourceUrl = null,
    ) {}
}
