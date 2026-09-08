<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('development_plan_id')->constrained()->cascadeOnDelete();

            // restrictOnDelete: a recorded score must never be silently orphaned.
            // Skills are retired by deactivation, never by deletion.
            $table->foreignId('skill_id')->constrained()->restrictOnDelete();

            $table->enum('type', ['baseline', 'final']);
            $table->decimal('score', 5, 2);
            $table->text('note')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');

            $table->timestamps();

            // The integrity rule the whole delta rests on: one baseline and one
            // final per skill per plan. Enforced in the database, not only in code.
            $table->unique(['development_plan_id', 'skill_id', 'type']);

            // The comparison read fetches baselines and finals by plan and type.
            $table->index(['development_plan_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
