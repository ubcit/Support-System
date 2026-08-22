<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            
            // Scope can be 'global', 'project', 'customer'
            $table->string('scope')->default('global');
            $table->unsignedBigInteger('scope_id')->nullable();
            
            $table->boolean('is_enabled')->default(false);
            
            $table->timestamps();
            
            $table->unique(['name', 'scope', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
