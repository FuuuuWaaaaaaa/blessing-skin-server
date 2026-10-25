<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Older Blessing Skin installations may retain this unused column.
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'regdate')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dateTime('regdate')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Making a legacy column nullable is deliberately irreversible. A
        // rollback must not make registration fail again under strict mode.
    }
};
