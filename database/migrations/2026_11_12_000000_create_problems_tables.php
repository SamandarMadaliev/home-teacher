<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('problems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('difficulty', 16)->default('easy');
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('problem_test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_id')->constrained()->cascadeOnDelete();
            $table->text('input')->nullable();
            $table->text('expected_output');
            $table->boolean('is_hidden')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('problem_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('language', 32);
            $table->longText('code');
            $table->string('status', 24);
            $table->unsignedInteger('passed_count')->default(0);
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_submissions');
        Schema::dropIfExists('problem_test_cases');
        Schema::dropIfExists('problems');
    }
};
