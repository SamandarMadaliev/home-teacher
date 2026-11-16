<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('description');
            $table->json('hints')->nullable()->after('tags');
            $table->longText('reference_solution')->nullable()->after('hints');
            $table->string('reference_language', 32)->nullable()->after('reference_solution');
            $table->json('starter_code')->nullable()->after('reference_language');
            $table->unsignedInteger('time_limit_ms')->default(4000)->after('starter_code');
            $table->string('compare_mode', 16)->default('trim_lines')->after('time_limit_ms');
        });

        Schema::table('problem_test_cases', function (Blueprint $table) {
            $table->string('label', 80)->nullable()->after('problem_id');
        });

        Schema::table('problem_submissions', function (Blueprint $table) {
            $table->json('results')->nullable()->after('duration_ms');
            $table->index(['problem_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('problem_submissions', function (Blueprint $table) {
            $table->dropIndex(['problem_id', 'status']);
            $table->dropColumn('results');
        });
        Schema::table('problem_test_cases', function (Blueprint $table) {
            $table->dropColumn('label');
        });
        Schema::table('problems', function (Blueprint $table) {
            $table->dropColumn(['tags', 'hints', 'reference_solution', 'reference_language', 'starter_code', 'time_limit_ms', 'compare_mode']);
        });
    }
};
