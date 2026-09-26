<?php

namespace App\Simulation;

use App\Models\Action;
use App\Models\Metric;
use App\Models\Sphere;
use Illuminate\Support\Facades\DB;

/** Объекты конструктора (scope = point): добавляются идемпотентно — из сидера и из миграции на живой базе. */
final class MapObjectCatalog
{
    private const DEMO = 'Эффект делится по доле района в радиусе объекта. Экспертная оценка (демо), заменить источником.';

    /** key, name, sphere, cost ₸, radius м, effects [metric, delta_pct] */
    private const OBJECTS = [
        ['school', 'Школа', 'social', 45_000_000, 500, [['social_access', 15]]],
        ['kindergarten', 'Детский сад', 'social', 30_000_000, 400, [['social_access', 10]]],
        ['clinic', 'Поликлиника', 'social', 35_000_000, 800, [['social_access', 12]]],
        ['park', 'Сквер', 'climate', 15_000_000, 300, [['heat', -8], ['air', 5]]],
        // Без коэффициентов: как новый маршрут, действует через покрытие остановками (500 м)
        ['bus_stop', 'Остановка', 'transport', 3_000_000, 500, []],
    ];

    /** @return int сколько объектов добавлено */
    public static function ensure(): int
    {
        $spheres = Sphere::pluck('id', 'key');
        $metrics = Metric::pluck('id', 'key');
        $added = 0;

        DB::transaction(function () use ($spheres, $metrics, &$added) {
            foreach (self::OBJECTS as [$key, $name, $sphere, $cost, $radius, $effects]) {
                if (Action::where('key', $key)->exists()) {
                    continue;
                }
                $action = Action::create([
                    'key' => $key, 'name' => $name, 'sphere_id' => $spheres[$sphere], 'cost' => $cost,
                    'scope' => 'point', 'radius_m' => $radius,
                    'assumption' => $effects === [] ? 'Новая остановка: жители в 500 м получают доступ к общественному транспорту.' : self::DEMO,
                ]);
                foreach ($effects as [$metric, $delta]) {
                    $action->effects()->create(['metric_id' => $metrics[$metric], 'delta_pct' => $delta, 'spill' => 0]);
                }
                $added++;
            }
        });

        return $added;
    }
}
