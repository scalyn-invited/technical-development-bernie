<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('development_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('week_number');

            // restrictOnDelete, same reasoning as assessments.
            $table->foreignId('skill_id')->constrained()->restrictOnDelete();

            $table->text('objective');
            $table->text('evidence')->nullable();
            $table->decimal('outcome_score', 5, 2)->nullable();
            $table->enum('status', ['planned', 'evidenced', 'closed'])->default('planned');

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            // One entry per week per plan; week numbers must also be contiguous,
            // which is a service-layer rule the database cannot express.
            $table->unique(['development_plan_id', 'week_number']);

            // The open-weeks queue filters on both columns together.
            $table->index(['status', 'week_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_entries');
    }
};
