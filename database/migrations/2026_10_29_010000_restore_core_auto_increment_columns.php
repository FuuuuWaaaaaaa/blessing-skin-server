<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const AUTO_INCREMENT_COLUMNS = [
        'players' => 'pid',
        'textures' => 'tid',
        'options' => 'id',
        'language_lines' => 'id',
        'reports' => 'id',
        'scopes' => 'id',
        'jobs' => 'id',
    ];

    public function up(): void
    {
        $schema = Schema::getConnection();
        if ($schema->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::AUTO_INCREMENT_COLUMNS as $tableName => $columnName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            $table = $this->quoteIdentifier(
                $schema->getTablePrefix().$tableName
            );
            $column = $schema->selectOne(
                "SHOW COLUMNS FROM {$table} LIKE ".$schema->getPdo()->quote($columnName)
            );
            if (!$column) {
                continue;
            }

            $this->ensureIndexed($schema, $table, $tableName, $columnName);

            if (!str_contains(strtolower((string) ($column->Extra ?? '')), 'auto_increment')) {
                $type = trim((string) $column->Type);
                if ($type === '') {
                    throw new RuntimeException("Cannot restore {$tableName}.{$columnName} auto-increment without a column type.");
                }

                $schema->statement(sprintf(
                    'ALTER TABLE %s MODIFY %s %s NOT NULL AUTO_INCREMENT',
                    $table,
                    $this->quoteIdentifier($columnName),
                    $type,
                ));
            }
        }
    }

    public function down(): void
    {
        // Restoring AUTO_INCREMENT is intentionally not reversed.
    }

    private function ensureIndexed(
        object $connection,
        string $table,
        string $tableName,
        string $columnName,
    ): void {
        $indexes = $connection->select("SHOW INDEX FROM {$table}");
        $columnIsIndexed = false;
        $hasPrimaryKey = false;

        foreach ($indexes as $index) {
            $hasPrimaryKey = $hasPrimaryKey || $index->Key_name === 'PRIMARY';
            $columnIsIndexed = $columnIsIndexed || (
                $index->Column_name === $columnName
                && (int) $index->Seq_in_index === 1
            );
        }

        if ($columnIsIndexed) {
            return;
        }

        $quotedColumn = $this->quoteIdentifier($columnName);
        $duplicate = $connection->selectOne(
            "SELECT {$quotedColumn} FROM {$table} "
            ."GROUP BY {$quotedColumn} HAVING COUNT(*) > 1 LIMIT 1"
        );
        $hasNull = $connection->selectOne(
            "SELECT 1 FROM {$table} WHERE {$quotedColumn} IS NULL LIMIT 1"
        );
        if ($hasNull || $duplicate) {
            throw new RuntimeException("Cannot restore {$tableName}.{$columnName} auto-increment: ".'the column contains NULL or duplicate values.');
        }

        if ($hasPrimaryKey) {
            $indexName = $tableName.'_'.$columnName.'_auto_increment_unique';
            $connection->statement(sprintf(
                'ALTER TABLE %s ADD UNIQUE INDEX %s (%s)',
                $table,
                $this->quoteIdentifier($indexName),
                $quotedColumn,
            ));
        } else {
            $connection->statement(sprintf(
                'ALTER TABLE %s ADD PRIMARY KEY (%s)',
                $table,
                $quotedColumn,
            ));
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        $quote = chr(96);

        return $quote.str_replace($quote, $quote.$quote, $identifier).$quote;
    }
};
