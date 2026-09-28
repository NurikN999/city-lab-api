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

    /** key, name, sphere, cost ₸, radius м, effects [metric, delta_pct], scope, допущение */
    private const OBJECTS = [
        ['school', 'Школа', 'social', 45_000_000, 500, [['social_access', 15]]],
        ['kindergarten', 'Детский сад', 'social', 30_000_000, 400, [['social_access', 10]]],
        ['clinic', 'Поликлиника', 'social', 35_000_000, 800, [['social_access', 12]]],
        ['park', 'Сквер', 'climate', 15_000_000, 300, [['heat', -8], ['air', 5]]],
        // Без коэффициентов: как новый маршрут, действует через покрытие остановками (500 м)
        ['bus_stop', 'Остановка', 'transport', 3_000_000, 500, []],
        // Дорога: эффект по доле района в коридоре 400 м вдоль улицы
        ['road_widening', 'Расширение дороги', 'transport', 60_000_000, 400, [['traffic', -10], ['co2', -3]], 'line',
            'Эффект по доле района в коридоре 400 м вдоль улицы. Уже за вычетом индуцированного спроса: новые полосы частично заполняются новыми машинами. Экспертная оценка (демо).'],
        // Снос: своего эффекта нет — освобождает место под объекты конструктора
        ['demolish', 'Снос здания', 'summary', 8_000_000, null, [], 'building',
            'Снос с расселением и расчисткой участка. Своего эффекта нет: освобождает место под сквер, школу или остановку. Экспертная оценка (демо).'],
    ];

    /** @return int сколько объектов добавлено */
    public static function ensure(): int
    {
        $spheres = Sphere::pluck('id', 'key');
        $metrics = Metric::pluck('id', 'key');
        $added = 0;

        DB::transaction(function () use ($spheres, $metrics, &$added) {
            foreach (self::OBJECTS as $object) {
                [$key, $name, $sphere, $cost, $radius, $effects] = $object;
                $scope = $object[6] ?? 'point';
                if (Action::where('key', $key)->exists()) {
                    continue;
                }
                $action = Action::create([
                    'key' => $key, 'name' => $name, 'sphere_id' => $spheres[$sphere], 'cost' => $cost,
                    'scope' => $scope, 'radius_m' => $radius,
                    'assumption' => $object[7] ?? ($effects === [] ? 'Новая остановка: жители в 500 м получают доступ к общественному транспорту.' : self::DEMO),
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
