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
        Schema::table('actions', function (Blueprint $t) {
            $t->unsignedInteger('radius_m')->nullable(); // только для объектов на карте (scope = point)
        });
        Schema::table('scenario_items', function (Blueprint $t) {
            $t->decimal('lat', 9, 6)->nullable();
            $t->decimal('lng', 9, 6)->nullable();
        });

        // Живая база (прод): объекты конструктора появляются сразу. Свежая база получит их из сидера.
        if (DB::table('metrics')->exists()) {
            MapObjectCatalog::ensure();
        }
    }

    public function down(): void
    {
        Schema::table('scenario_items', fn (Blueprint $t) => $t->dropColumn(['lat', 'lng']));
        Schema::table('actions', fn (Blueprint $t) => $t->dropColumn('radius_m'));
    }
};
