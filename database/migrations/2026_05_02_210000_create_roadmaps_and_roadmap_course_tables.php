<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadmaps', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('roadmap_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order');
            $table->unique(['roadmap_id', 'course_id']);
            $table->unique(['roadmap_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_course');
        Schema::dropIfExists('roadmaps');
    }
};
