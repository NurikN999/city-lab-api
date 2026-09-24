<?php

namespace App\Geodata;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class OsrmClient
{
    public function __construct(private string $baseUrl, private int $timeout) {}

    /**
     * @param  list<array{0: float, 1: float}>  $points  [lat, lng]
     * @return array{path: list<array{0: float, 1: float}>, stops: list<array{0: float, 1: float}>, snapped: bool}
     */
    public function route(array $points): array
    {
        $coords = implode(';', array_map(fn (array $p) => $p[1].','.$p[0], $points));

        try {
            return Cache::remember('osrm:'.sha1($coords), 86_400, function () use ($coords) {
                $data = Http::timeout($this->timeout)->withUserAgent('aktau-city-lab/1.0')
                    ->get("{$this->baseUrl}/route/v1/driving/{$coords}", ['overview' => 'full', 'geometries' => 'geojson'])
                    ->throw()->json();
                if (($data['code'] ?? null) !== 'Ok') {
                    throw new RuntimeException('OSRM: '.($data['code'] ?? 'no code'));
                }

                return [
                    'path' => $data['routes'][0]['geometry']['coordinates'],
                    'stops' => array_map(fn (array $w) => [$w['location'][1], $w['location'][0]], $data['waypoints']),
                    'snapped' => true,
                ];
            });
        } catch (Throwable $e) {
            report($e);

            return ['path' => array_map(fn (array $p) => [$p[1], $p[0]], $points), 'stops' => $points, 'snapped' => false];
        }
    }
}
