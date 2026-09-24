<?php

namespace App\CityAi;

use App\Simulation\Goal;

final readonly class Intent
{
    /** @param list<Goal> $goals */
    public function __construct(
        public ?int $districtId, // null — район не назван, выбирает движок
        public array $goals,
        public int $budget,
        public bool $fromFallback = false,
    ) {}
}
