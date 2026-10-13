<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type'); // lesson_practice, topic_assessment, final_exam
            $table->decimal('pass_mark', 5, 2)->default(70.00); // 70.00%
            $table->decimal('total_marks', 8, 2)->default(100.00);
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('attempts_allowed')->nullable(); // null for unlimited
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tests');
    }
};
