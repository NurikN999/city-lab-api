<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $t) {
            $t->foreignId('scenario_id')->nullable()->constrained()->nullOnDelete(); // сценарий, которым акимат решает жалобу
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $t) {
            $t->dropConstrainedForeignId('scenario_id');
        });
    }
};
