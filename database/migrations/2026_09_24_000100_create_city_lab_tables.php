<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spheres', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('name');
        });

        Schema::create('metrics', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('name');
            $t->string('unit', 16);
            $t->foreignId('sphere_id')->constrained()->restrictOnDelete();
            $t->boolean('lower_is_better');
            $t->decimal('min_value', 8, 2)->default(0);
            $t->decimal('max_value', 8, 2)->default(100);
            $t->decimal('satisfaction_weight', 4, 3)->default(0);
            $t->boolean('is_computed')->default(false);
        });

        Schema::create('districts', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->unsignedInteger('population');
            $t->decimal('center_lat', 9, 6);
            $t->decimal('center_lng', 9, 6);
            $t->json('boundary');
            $t->timestamps();
        });

        Schema::create('district_metric_values', function (Blueprint $t) {
            $t->foreignId('district_id')->constrained()->cascadeOnDelete();
            $t->foreignId('metric_id')->constrained()->cascadeOnDelete();
            $t->decimal('value', 8, 2);
            $t->primary(['district_id', 'metric_id']);
        });

        Schema::create('stops', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->decimal('lat', 9, 6);
            $t->decimal('lng', 9, 6);
        });

        Schema::create('routes', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('name');
            $t->json('path');
            $t->timestamps();
        });

        Schema::create('route_stops', function (Blueprint $t) {
            $t->id();
            $t->foreignId('route_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('position');
            $t->decimal('lat', 9, 6);
            $t->decimal('lng', 9, 6);
            $t->unique(['route_id', 'position']);
        });

        Schema::create('district_route', function (Blueprint $t) {
            $t->foreignId('district_id')->constrained()->cascadeOnDelete();
            $t->foreignId('route_id')->constrained()->cascadeOnDelete();
            $t->primary(['district_id', 'route_id']);
        });

        Schema::create('actions', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('name');
            $t->foreignId('sphere_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('cost');
            $t->string('scope', 16); // district | route — проверяется в FormRequest
            $t->text('assumption');
            $t->string('source_url')->nullable();
            $t->timestamps();
        });

        Schema::create('action_effects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('action_id')->constrained()->cascadeOnDelete();
            $t->foreignId('metric_id')->constrained()->restrictOnDelete();
            $t->decimal('delta_pct', 6, 2);
            $t->decimal('spill', 3, 2)->default(0);
            $t->unique(['action_id', 'metric_id']);
        });

        Schema::create('metric_couplings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('source_metric_id')->constrained('metrics')->restrictOnDelete();
            $t->foreignId('target_metric_id')->constrained('metrics')->restrictOnDelete();
            $t->decimal('factor', 6, 3);
            $t->unique(['source_metric_id', 'target_metric_id']);
        });

        Schema::create('scenarios', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('source', 16)->default('manual'); // manual | ai
            $t->unsignedBigInteger('budget');
            $t->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('scenario_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $t->foreignId('action_id')->constrained()->restrictOnDelete();
            $t->foreignId('district_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('route_id')->nullable()->constrained()->restrictOnDelete();
            $t->unsignedTinyInteger('quantity')->default(1);
        });
    }

    public function down(): void
    {
        foreach (['scenario_items', 'scenarios', 'metric_couplings', 'action_effects', 'actions', 'district_route',
            'route_stops', 'routes', 'stops', 'district_metric_values', 'districts', 'metrics', 'spheres'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
