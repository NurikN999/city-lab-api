<?php

namespace App\Console\Commands;

use App\Geodata\DemoMetrics;
use App\Models\District;
use App\Models\Metric;
use App\Simulation\Geo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FillDemoMetrics extends Command
{
    protected $signature = 'city:fill-demo-metrics {--population : Пересчитать население всех районов по площади}';

    protected $description = 'Проставить демо-метрики и оценку населения районам, у которых их нет (например, после city:import-geodata)';

    public function handle(): int
    {
        $metrics = Metric::all()->where('is_computed', false)->pluck('id', 'key');
        $filled = 0;
        $populated = 0;
        $density = config('simulation.demo_density_per_ha');

        DB::transaction(function () use ($metrics, $density, &$filled, &$populated) {
            foreach (District::with('metrics:id')->get() as $district) {
                if ($district->population === 0 || $this->option('population')) {
                    $district->update(['population' => (int) round(Geo::areaHa($district->boundary['coordinates'][0]) * $density, -2)]);
                    $populated++;
                }
                $existing = $district->metrics->pluck('id')->all();
                $missing = collect(DemoMetrics::forName($district->name))
                    ->filter(fn ($value, $key) => isset($metrics[$key]) && ! in_array($metrics[$key], $existing, true))
                    ->mapWithKeys(fn ($value, $key) => [$metrics[$key] => ['value' => $value]]);
                if ($missing->isNotEmpty()) {
                    $district->metrics()->attach($missing->all());
                    $filled++;
                }
            }
        });

        $this->info("Демо-метрики проставлены: {$filled} ".$this->districtsWord($filled).'.');
        $this->info("Население оценено: {$populated} (по площади, {$density} жит./га).");

        return self::SUCCESS;
    }

    private function districtsWord(int $n): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;
        if ($mod10 === 1 && $mod100 !== 11) {
            return 'район';
        }

        return $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) ? 'района' : 'районов';
    }
}
