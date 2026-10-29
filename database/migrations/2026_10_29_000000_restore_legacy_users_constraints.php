<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const CURRENT_SCHEMA_COLUMNS = [
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
        'verified',
        'verification_token',
        'remember_token',
        'locale',
        'is_dark_mode',
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
        $columns = $connection->select("SHOW FULL COLUMNS FROM {$table}");
        $columnNames = array_map(
            static fn (object $column): string => (string) $column->Field,
            $columns
        );

        foreach ($columns as $column) {
            $name = (string) $column->Field;
            $extra = strtolower(trim((string) ($column->Extra ?? '')));

            if (
                in_array($name, self::CURRENT_SCHEMA_COLUMNS, true)
                || strtoupper((string) $column->Null) !== 'YES'
                || $column->Default !== null
                || str_contains($extra, 'auto_increment')
                || str_contains($extra, 'virtual')
                || str_contains($extra, 'stored')
            ) {
                continue;
            }

            $type = strtolower(trim((string) $column->Type));
            $quotedName = $this->quoteIdentifier($name);
            $valueExpression = $this->defaultExpression(
                $name,
                $type,
                $columnNames,
                $connection
            );

            $connection->statement(sprintf(
                'UPDATE %s SET %s = %s WHERE %s IS NULL',
                $table,
                $quotedName,
                $valueExpression,
                $quotedName,
            ));
            $connection->statement(sprintf(
                'ALTER TABLE %s MODIFY %s %s NOT NULL',
                $table,
                $quotedName,
                $this->columnType($column),
            ));
        }
    }

    public function down(): void
    {
        // Keeping legacy columns required preserves the old database contract.
    }

    private function defaultExpression(
        string $name,
        string $type,
        array $columnNames,
        object $connection,
    ): string {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]/', '', $name));

        if ($this->isDateColumn($normalized, $type)) {
            $reference = $this->isLastLoginColumn($normalized)
                ? ['last_sign_at', 'register_at']
                : ['register_at', 'last_sign_at'];
            $references = array_values(array_filter(
                $reference,
                static fn (string $column): bool => in_array($column, $columnNames, true)
            ));
            if ($references !== []) {
                $expression = 'COALESCE('
                    .implode(', ', array_map(
                        fn (string $column): string => $this->quoteIdentifier($column),
                        $references
                    ))
                    .', CURRENT_TIMESTAMP)';

                return preg_match('/(?:tinyint|smallint|mediumint|int|bigint)/', $type) === 1
                    ? "UNIX_TIMESTAMP({$expression})"
                    : $expression;
            }

            return $connection->getPdo()->quote(
                preg_match('/(?:tinyint|smallint|mediumint|int|bigint)/', $type) === 1
                    ? now()->timestamp
                    : now()->format('Y-m-d H:i:s')
            );
        }

        if ($this->isIpColumn($normalized)) {
            if (in_array('ip', $columnNames, true)) {
                return 'COALESCE('.$this->quoteIdentifier('ip').", '')";
            }

            return $connection->getPdo()->quote('');
        }

        if ($this->isNameColumn($normalized) && in_array('nickname', $columnNames, true)) {
            return 'COALESCE('.$this->quoteIdentifier('nickname').", '')";
        }

        if (str_contains($type, 'enum(')) {
            return $connection->getPdo()->quote($this->firstEnumValue($type));
        }

        if (preg_match('/(?:tinyint|smallint|mediumint|int|bigint|decimal|float|double|bit)/', $type) === 1) {
            return '0';
        }

        if (str_contains($type, 'json')) {
            return $connection->getPdo()->quote('{}');
        }

        return $connection->getPdo()->quote('');
    }

    private function isDateColumn(string $normalized, string $type): bool
    {
        return $this->isLastLoginColumn($normalized)
            || in_array($normalized, [
                'regdate',
                'registerdate',
                'registrationdate',
                'registeredat',
                'registrationat',
                'createdat',
            ], true)
            || preg_match('/(?:date|time|year)/', $type) === 1;
    }

    private function isLastLoginColumn(string $normalized): bool
    {
        return in_array($normalized, [
            'lastlogin',
            'lastlogindate',
            'lastloginat',
        ], true);
    }

    private function isIpColumn(string $normalized): bool
    {
        return in_array($normalized, [
            'regip',
            'registerip',
            'registrationip',
            'lastip',
            'lastloginip',
        ], true);
    }

    private function isNameColumn(string $normalized): bool
    {
        return in_array($normalized, [
            'username',
            'realname',
            'name',
            'displayname',
        ], true);
    }

    private function firstEnumValue(string $type): string
    {
        $start = strpos($type, '(');
        $end = strrpos($type, ')');
        if ($start === false || $end === false || $end <= $start) {
            return '';
        }

        $values = str_getcsv(substr($type, $start + 1, $end - $start - 1), ',', "'");

        return (string) ($values[0] ?? '');
    }

    private function columnType(object $column): string
    {
        $type = trim((string) $column->Type);
        if ($type === '') {
            throw new RuntimeException('Cannot restore a users column without a type.');
        }

        return $type;
    }

    private function quoteIdentifier(string $identifier): string
    {
        $quote = chr(96);

        return $quote.str_replace($quote, $quote.$quote, $identifier).$quote;
    }
};
