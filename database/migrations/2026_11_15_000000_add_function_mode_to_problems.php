<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Function-style (LeetCode-like) problems: the solver writes one function
     * described by `signature`; each test's `input` holds one JSON value per
     * line (one per parameter) and `expected_output` the JSON return value.
     */
    public function up(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->string('io_mode', 16)->default('stdin')->after('description');
            $table->json('signature')->nullable()->after('io_mode');
        });
    }

    public function down(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->dropColumn(['io_mode', 'signature']);
        });
    }
};
