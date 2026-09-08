<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('development_plans', function (Blueprint $table) {
            $table->id();

            // cascadeOnDelete: a plan has no meaning without its member.
            // unique: one plan per member for this single cycle.
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->text('key_gaps');
            $table->text('weekly_focus');
            $table->enum('status', ['draft', 'active', 'completed'])->default('draft');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // GET /plans?status= is the administrator's main listing.
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('development_plans');
    }
};
