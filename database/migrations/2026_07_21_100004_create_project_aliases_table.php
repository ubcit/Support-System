<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->timestamps();

            $table->index('alias');
            $table->unique(['project_id', 'alias']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_aliases');
    }
};
