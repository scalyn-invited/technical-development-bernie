<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            // nullOnDelete: a skill outlives the administrator who created it.
            // Losing the author must never delete assessed skills.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Every skill picker filters on is_active.
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
