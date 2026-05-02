<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->decimal('last_position', 12, 3)->default(0);
            $table->decimal('duration_seconds', 12, 3)->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamps();

            $table->unique('video_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_progress');
    }
};
