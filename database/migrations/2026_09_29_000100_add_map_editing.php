<?php

use App\Simulation\MapObjectCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenario_items', function (Blueprint $t) {
            $t->json('geometry')->nullable(); // расширение дороги: MultiLineString
            $t->unsignedBigInteger('osm_id')->nullable(); // снос: id здания в OSM
        });

        // Живая база: инструменты «Расширение дороги» и «Снос здания» появляются сразу
        if (DB::table('metrics')->exists()) {
            MapObjectCatalog::ensure();
        }
    }

    public function down(): void
    {
        Schema::table('scenario_items', fn (Blueprint $t) => $t->dropColumn(['geometry', 'osm_id']));
    }
};
