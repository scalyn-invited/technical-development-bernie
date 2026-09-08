<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Two fixed roles for a single programme cycle. A roles table would be
            // dead weight: nothing in the approved workflow adds a third role.
            $table->enum('role', ['administrator', 'member'])
                ->default('member')
                ->after('email');

            // The plans index filters members out of administrator-only listings.
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
