<?php

namespace Database\Seeders;

use App\Models\Action;
use App\Models\BusRoute;
use App\Models\District;
use App\Models\Metric;
use App\Models\MetricCoupling;
use App\Models\Sphere;
use App\Models\Stop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ponytail: schematic Aktau layout mirroring the design mockups (grid units ≈ 420 м).
 * Replace geometry with real polygons/stops via `php artisan city:import-geodata` (spec §16).
 * All metric values and costs are DEMO data.
 */
class DemoCitySeeder extends Seeder
{
    private const SIZE = 1.7;

    /** name, gx, gy, traffic (демо) */
    private const DISTRICTS = [
        ['1 мкр', 0.2, 0.4, 42], ['2 мкр', 2.3, 0.4, 48], ['3 мкр', 4.4, 0.4, 55],
        ['4 мкр', 6.5, 0.4, 61], ['5 мкр', 8.6, 0.4, 38], ['6 мкр', 10.7, 0.4, 44],
        ['7 мкр', 1.2, 2.6, 52], ['8 мкр', 3.3, 2.6, 66], ['9 мкр', 5.4, 2.6, 58],
        ['11 мкр', 7.5, 2.6, 71], ['12 мкр', 9.6, 2.6, 84],
        ['14 мкр', 2.2, 4.8, 45], ['15 мкр', 4.3, 4.8, 63], ['17 мкр', 6.4, 4.8, 57],
        ['27 мкр', 8.5, 4.8, 74], ['29 мкр', 10.6, 4.8, 40],
    ];

    /** existing stops (gx, gy): coastal road + two inland roads */
    private const STOPS = [
        [0, 0.2], [2, 0.2], [4, 0.2], [6, 0.2], [8, 0.2], [10, 0.2], [12, 0.2],
        [5.3, 1.5], [5.3, 3.5], [5.3, 5.5], [2.1, 3.5], [2.1, 5.5],
    ];

    /** key, name, stop vertices (gx, gy), served districts */
    private const ROUTES = [
        ['a', 'Маршрут А', [[0.5, 4.6], [3.0, 4.6], [5.4, 4.6], [7.8, 4.6], [10.2, 4.6], [12.5, 4.6]],
            ['14 мкр', '15 мкр', '17 мкр', '27 мкр', '29 мкр']],
        ['b', 'Маршрут Б', [[1.6, 2.35], [4.2, 2.35], [6.9, 2.35], [9.45, 2.35], [9.45, 3.5], [9.45, 4.6], [11.6, 4.6]],
            ['7 мкр', '8 мкр', '9 мкр', '11 мкр', '12 мкр', '27 мкр', '29 мкр']],
        ['c', 'Маршрут В', [[9.45, 0.1], [9.45, 1.8], [9.45, 3.5], [9.45, 5.2], [9.45, 7.0]],
            ['5 мкр', '11 мкр', '12 мкр', '27 мкр']],
    ];

    /** key, name, unit, sphere, lower_is_better, min, max, satisfaction_weight, is_computed */
    private const METRICS = [
        ['traffic', 'Загрузка дорог', '%', 'transport', true, 0, 100, 0.30, false],
        ['transit_coverage', 'Покрытие общественным транспортом', '%', 'transport', false, 0, 100, 0.20, true],
        ['travel_time', 'Время в пути', 'индекс', 'transport', true, 0, 200, 0.10, false],
        ['co2', 'Выбросы CO₂', 'индекс', 'climate', true, 0, 200, 0.05, false],
        ['heat', 'Индекс жары', 'индекс', 'climate', true, 0, 100, 0.15, false],
        ['air', 'Качество воздуха', 'индекс', 'climate', false, 0, 100, 0.10, false],
        ['water_loss', 'Потери воды в сетях', '%', 'water', true, 0, 100, 0.05, false],
        ['social_access', 'Доступность соцобъектов', '%', 'social', false, 0, 100, 0.05, false],
        ['satisfaction', 'Удовлетворённость', 'индекс', 'summary', false, 0, 100, 0.00, false],
    ];

    private const DEMO = 'Экспертная оценка (демо), заменить источником.';

    /** key, name, sphere, cost ₸, scope, effects [metric, delta_pct, spill], assumption */
    private const ACTIONS = [
        ['new_bus_route', 'Новый автобусный маршрут', 'transport', 42_000_000, 'route', [],
            'Субсидия перевозчику на год. Эффект считается через покрытие остановками (500 м), а не коэффициентом.'],
        ['bus_lane', 'Выделенная полоса', 'transport', 30_000_000, 'district', [['travel_time', -8, 0.3], ['traffic', -4, 0.3]], self::DEMO],
        ['smart_lights', 'Умные светофоры', 'transport', 10_000_000, 'district', [['traffic', -6, 0.3], ['co2', -2, 0.3]], self::DEMO],
        ['bus_frequency', 'Повышение частоты автобусов', 'transport', 15_000_000, 'district', [['traffic', -3, 0], ['travel_time', -4, 0]], self::DEMO],
        ['greening', 'Озеленение и теневые навесы', 'climate', 18_000_000, 'district', [['heat', -6, 0.3], ['air', 4, 0.3]], self::DEMO],
        ['koshkar_ata_dust', 'Пылеподавление на Кошкар-Ате', 'climate', 25_000_000, 'district', [['air', 9, 0.5]], self::DEMO],
        ['pipe_renewal', 'Замена изношенных сетей', 'water', 35_000_000, 'district', [['water_loss', -8, 0]], self::DEMO],
        ['smart_meters', 'Умные счётчики воды', 'water', 12_000_000, 'district', [['water_loss', -3, 0]], self::DEMO],
        ['school_buses', 'Школьные автобусы', 'social', 20_000_000, 'district', [['social_access', 10, 0]], self::DEMO],
        ['gp_office', 'Кабинет врача общей практики', 'social', 25_000_000, 'district', [['social_access', 8, 0.3]], self::DEMO],
    ];

    public function run(): void
    {
        $spheres = collect([
            'transport' => 'Транспорт', 'climate' => 'Климат', 'water' => 'Вода',
            'social' => 'Соцобъекты', 'summary' => 'Итог',
        ])->map(fn ($name, $key) => Sphere::create(['key' => $key, 'name' => $name])->id);

        $metrics = collect(self::METRICS)->mapWithKeys(fn ($m) => [$m[0] => Metric::create([
            'key' => $m[0], 'name' => $m[1], 'unit' => $m[2], 'sphere_id' => $spheres[$m[3]],
            'lower_is_better' => $m[4], 'min_value' => $m[5], 'max_value' => $m[6],
            'satisfaction_weight' => $m[7], 'is_computed' => $m[8],
        ])->id]);

        $districtIds = [];
        foreach (self::DISTRICTS as $i => [$name, $gx, $gy, $traffic]) {
            [$lat, $lng] = $this->geo($gx + self::SIZE / 2, $gy + self::SIZE / 2);
            $district = District::create([
                'name' => $name,
                'population' => 10_000, // ponytail: equal demo population — replace with stat.gov.kz / akimat figures
                'center_lat' => round($lat, 6),
                'center_lng' => round($lng, 6),
                'boundary' => ['type' => 'Polygon', 'coordinates' => [$this->ring($gx, $gy)]],
            ]);
            $row = (int) round($gy / 2.2);
            $district->metrics()->attach([
                $metrics['traffic'] => ['value' => $traffic],
                $metrics['travel_time'] => ['value' => 100],
                $metrics['co2'] => ['value' => 100],
                $metrics['heat'] => ['value' => round(55 + 0.25 * $traffic)],
                $metrics['air'] => ['value' => round(92 - 0.4 * $traffic)],
                $metrics['water_loss'] => ['value' => 24 + ($i % 4) * 3],
                $metrics['social_access'] => ['value' => 72 - $row * 6],
                $metrics['satisfaction'] => ['value' => round(95 - 0.55 * $traffic)],
            ]);
            $districtIds[$name] = $district->id;
        }

        foreach (self::STOPS as [$gx, $gy]) {
            [$lat, $lng] = $this->geo($gx, $gy);
            Stop::create(['lat' => round($lat, 6), 'lng' => round($lng, 6)]);
        }

        foreach (self::ROUTES as [$key, $name, $vertices, $served]) {
            $coords = array_map(fn ($v) => $this->geo($v[0], $v[1]), $vertices);
            $route = BusRoute::create([
                'key' => $key,
                'name' => $name,
                'path' => ['type' => 'LineString', 'coordinates' => array_map(fn ($c) => [round($c[1], 6), round($c[0], 6)], $coords)],
            ]);
            foreach ($coords as $position => [$lat, $lng]) {
                $route->stops()->create(['position' => $position, 'lat' => round($lat, 6), 'lng' => round($lng, 6)]);
            }
            $route->districts()->attach(array_map(fn ($n) => $districtIds[$n], $served));
        }

        foreach (self::ACTIONS as [$key, $name, $sphere, $cost, $scope, $effects, $assumption]) {
            $action = Action::create([
                'key' => $key, 'name' => $name, 'sphere_id' => $spheres[$sphere],
                'cost' => $cost, 'scope' => $scope, 'assumption' => $assumption,
            ]);
            foreach ($effects as [$metric, $delta, $spill]) {
                $action->effects()->create(['metric_id' => $metrics[$metric], 'delta_pct' => $delta, 'spill' => $spill]);
            }
        }

        MetricCoupling::create(['source_metric_id' => $metrics['transit_coverage'], 'target_metric_id' => $metrics['traffic'], 'factor' => -0.35]);
        MetricCoupling::create(['source_metric_id' => $metrics['traffic'], 'target_metric_id' => $metrics['co2'], 'factor' => 0.5]);

        $password = env('ADMIN_PASSWORD') ?: Str::random(16);
        User::create(['name' => 'Акимат', 'email' => 'akimat@citylab.kz', 'password' => Hash::make($password)]);
        if (! env('ADMIN_PASSWORD')) {
            $this->command?->warn("ADMIN_PASSWORD не задан. Пароль akimat@citylab.kz: {$password}");
        }
    }

    /** schematic grid → [lat, lng] */
    private function geo(float $gx, float $gy): array
    {
        return [43.635 + $gy * 0.0036 + $gx * 0.0021, 51.130 + $gx * 0.0042 - $gy * 0.0020];
    }

    /** closed GeoJSON ring [lng, lat] of the district square */
    private function ring(float $gx, float $gy): array
    {
        $s = self::SIZE;
        $corners = [[$gx, $gy], [$gx + $s, $gy], [$gx + $s, $gy + $s], [$gx, $gy + $s], [$gx, $gy]];

        return array_map(function ($c) {
            [$lat, $lng] = $this->geo($c[0], $c[1]);

            return [round($lng, 6), round($lat, 6)];
        }, $corners);
    }
}
