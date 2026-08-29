<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('home_team_id')
                ->constrained('teams')
                ->restrictOnDelete();

            $table->foreignId('away_team_id')
                ->constrained('teams')
                ->restrictOnDelete();

            $table->foreignId('venue_id')
                ->constrained('venues')
                ->restrictOnDelete();

            $table->dateTime('scheduled_at');

            $table->unsignedInteger('home_score')->nullable();
            $table->unsignedInteger('away_score')->nullable();

            $table->foreignId('winner_team_id')
                ->nullable()
                ->constrained('teams')
                ->restrictOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
