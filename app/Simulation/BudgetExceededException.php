<?php

namespace App\Simulation;

use Illuminate\Http\JsonResponse;
use RuntimeException;

final class BudgetExceededException extends RuntimeException
{
    public function __construct(public readonly int $over)
    {
        parent::__construct("Budget exceeded by {$over}");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Сценарий превышает бюджет на '.number_format($this->over, 0, ',', ' ').' ₸.',
            'error' => 'budget_exceeded',
            'over' => $this->over,
        ], 422);
    }
}
