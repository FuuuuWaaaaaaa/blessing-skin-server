<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users') || Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $table = str_replace('`', '``', DB::connection()->getTablePrefix().'users');
        $column = DB::selectOne("SHOW COLUMNS FROM `{$table}` LIKE 'uid'");
        if (!$column) {
            return;
        }

        $indexes = DB::select("SHOW INDEX FROM `{$table}`");
        $uidIsIndexed = false;
        $hasPrimaryKey = false;
        foreach ($indexes as $index) {
            $hasPrimaryKey = $hasPrimaryKey || $index->Key_name === 'PRIMARY';
            $uidIsIndexed = $uidIsIndexed || (
                $index->Column_name === 'uid'
                && (int) $index->Seq_in_index === 1
            );
        }

        if (!$uidIsIndexed) {
            $duplicate = DB::selectOne(
                "SELECT `uid` FROM `{$table}` GROUP BY `uid` HAVING COUNT(*) > 1 LIMIT 1"
            );
            if (DB::table('users')->whereNull('uid')->exists() || $duplicate) {
                throw new RuntimeException('Cannot restore users.uid auto-increment: uid contains NULL or duplicate values.');
            }

            if ($hasPrimaryKey) {
                DB::statement("ALTER TABLE `{$table}` ADD UNIQUE INDEX `users_uid_auto_increment_unique` (`uid`)");
            } else {
                DB::statement("ALTER TABLE `{$table}` ADD PRIMARY KEY (`uid`)");
            }
        }

        if (!str_contains(strtolower((string) ($column->Extra ?? '')), 'auto_increment')) {
            // `uid` was created with increments('uid') and must remain an
            // unsigned auto-incrementing key for new registrations.
            DB::statement("ALTER TABLE `{$table}` MODIFY `uid` INT UNSIGNED NOT NULL AUTO_INCREMENT");
        }
    }

    public function down(): void
    {
        // Restoring AUTO_INCREMENT is intentionally not reversed: removing it
        // would make future registrations fail again.
    }
};
