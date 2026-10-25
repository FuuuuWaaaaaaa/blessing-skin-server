<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Columns that the current application supplies or that are part of the
     * current schema contract. Legacy-only columns can safely be nullable.
     */
    private const CURRENT_INSERT_COLUMNS = [
        'uid',
        'email',
        'nickname',
        'score',
        'avatar',
        'password',
        'ip',
        'permission',
        'last_sign_at',
        'register_at',
    ];

    public function up(): void
    {
        if (
            !Schema::hasTable('users')
            || Schema::getConnection()->getDriverName() !== 'mysql'
        ) {
            return;
        }

        $connection = Schema::getConnection();
        $table = $this->quoteIdentifier($connection->getTablePrefix().'users');

        foreach ($connection->select("SHOW FULL COLUMNS FROM {$table}") as $column) {
            $name = (string) $column->Field;
            $extra = strtolower(trim((string) ($column->Extra ?? '')));

            if (
                in_array($name, self::CURRENT_INSERT_COLUMNS, true)
                || strtoupper((string) $column->Null) !== 'NO'
                || $column->Default !== null
                || str_contains($extra, 'auto_increment')
                || str_contains($extra, 'virtual')
                || str_contains($extra, 'stored')
            ) {
                continue;
            }

            $type = trim((string) $column->Type);
            if ($type === '') {
                continue;
            }

            $onUpdate = preg_match('/^on update\\s+.+$/i', $extra)
                ? ' '.$extra
                : '';

            $connection->statement(sprintf(
                'ALTER TABLE %s MODIFY %s %s NULL DEFAULT NULL%s',
                $table,
                $this->quoteIdentifier($name),
                $type,
                $onUpdate,
            ));
        }
    }

    public function down(): void
    {
        // This compatibility change is intentionally irreversible. Restoring
        // NOT NULL would make existing registrations fail under strict mode.
    }

    private function quoteIdentifier(string $identifier): string
    {
        $quote = chr(96);

        return $quote.str_replace($quote, $quote.$quote, $identifier).$quote;
    }
};
