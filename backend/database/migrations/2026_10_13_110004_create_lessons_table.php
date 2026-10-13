<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('summary');
            $table->longText('content');
            $table->json('learning_objectives')->nullable();
            $table->unsignedInteger('estimated_minutes')->default(15);
            $table->unsignedInteger('order')->default(1);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->unique(['topic_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
