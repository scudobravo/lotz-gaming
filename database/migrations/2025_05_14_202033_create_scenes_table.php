<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->enum('type', ['intro', 'investigation', 'puzzle', 'final']);
            $table->text('entry_message');
            $table->string('media_gif')->nullable();
            $table->string('media_audio')->nullable();
            $table->text('puzzle_question')->nullable();
            $table->string('correct_answer')->nullable();
            $table->text('success_message')->nullable();
            $table->text('failure_message')->nullable();
            $table->integer('max_attempts')->default(3);
            $table->foreignId('item_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('character_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenes');
    }
};
