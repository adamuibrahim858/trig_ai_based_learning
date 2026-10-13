<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained()->cascadeOnDelete();
            $table->text('question_text');
            $table->string('question_type'); // multiple_choice, true_false, numerical
            $table->text('explanation')->nullable();
            $table->decimal('marks', 5, 2)->default(1.00);
            $table->unsignedInteger('order')->default(1);
            $table->json('metadata')->nullable(); // can store tolerance for numerical or hint
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
