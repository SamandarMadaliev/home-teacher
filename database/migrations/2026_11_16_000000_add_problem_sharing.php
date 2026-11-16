<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Problem sharing: a problem stays with its author and other people get
 * access to it (shared by email, or public to every member). Each person's
 * submissions stay their own; accepted ones double as community solutions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->string('visibility', 16)->default('private')->after('compare_mode');
            $table->timestamp('published_at')->nullable()->after('visibility');
            $table->boolean('show_reference_solution')->default(false)->after('published_at');
            $table->unsignedInteger('revision')->default(1)->after('show_reference_solution');
            $table->timestamp('moderated_at')->nullable()->after('revision');
            $table->foreignId('moderated_by_id')->nullable()->after('moderated_at')->constrained('users')->nullOnDelete();
            $table->index(['visibility', 'published_at']);
        });

        Schema::create('problem_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('include_solution')->default(false);
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();
            $table->unique(['problem_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('problem_submissions', function (Blueprint $table) {
            $table->unsignedInteger('problem_revision')->default(1)->after('results');
            $table->timestamp('hidden_at')->nullable()->after('problem_revision');
            $table->timestamp('moderated_at')->nullable()->after('hidden_at');
            $table->foreignId('moderated_by_id')->nullable()->after('moderated_at')->constrained('users')->nullOnDelete();
            $table->index(['problem_id', 'status', 'user_id']);
        });

        // Until now only the owner could submit, so ownerless rows are theirs.
        DB::table('problem_submissions')->whereNull('user_id')->update([
            'user_id' => DB::raw('(select user_id from problems where problems.id = problem_submissions.problem_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('problem_submissions', function (Blueprint $table) {
            $table->dropIndex(['problem_id', 'status', 'user_id']);
            $table->dropConstrainedForeignId('moderated_by_id');
            $table->dropColumn(['problem_revision', 'hidden_at', 'moderated_at']);
        });

        Schema::dropIfExists('problem_shares');

        Schema::table('problems', function (Blueprint $table) {
            $table->dropIndex(['visibility', 'published_at']);
            $table->dropConstrainedForeignId('moderated_by_id');
            $table->dropColumn(['visibility', 'published_at', 'show_reference_solution', 'revision', 'moderated_at']);
        });
    }
};
