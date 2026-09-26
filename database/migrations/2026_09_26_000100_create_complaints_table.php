<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $t) {
            $t->id();
            $t->foreignId('district_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sphere_id')->nullable()->constrained()->nullOnDelete(); // null — «другое»
            $t->string('text', 280);
            $t->string('status', 16)->default('new'); // new | accepted | resolved | hidden
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
