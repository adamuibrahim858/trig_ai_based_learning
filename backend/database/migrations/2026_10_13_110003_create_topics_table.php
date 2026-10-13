<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description');
            $table->unsignedTinyInteger('week_number');
            $table->json('learning_objectives')->nullable();
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();

            $table->unique(['course_id', 'slug']);
            $table->unique(['course_id', 'week_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
