<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_progress', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('current_scene_id')->nullable()->constrained('scenes')->onDelete('set null');
            $table->integer('attempts_remaining')->default(3);
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();

            $table->unique(['phone_number', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_progress');
    }
}; 