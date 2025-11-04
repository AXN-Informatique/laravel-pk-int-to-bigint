<?php

namespace Axn\PkIntToBigint;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class Transformer
{
    protected Connection $connection;

    protected SchemaBuilder $schemaBuilder;

    protected ?Command $command = null;

    private array $intColumnsInfo = [];

    private array $foreignKeysConstraintsInfo = [];

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->schemaBuilder = $connection->getSchemaBuilder();
    }

    /**
     * Set console command instance for printing message to the console.
     *
     * @param  Command $command
     * @return void
     */
    public function setConsoleCommand(Command $command): void
    {
        $this->command = $command;
    }

    /**
     * 1) Drop all foreign key constraints on each table
     * 2) Change INT to BIGINT on primary and foreign key columns on each table
     * 3) Restore all foreign key constraints on each table
     *
     * @return void
     */
    public function transform(): void
    {
        $this->extractSchemaInfos();

        $hasConstraintAnomaly = false;

        foreach ($this->foreignKeysConstraintsInfo as $constraint) {
            // If there are data that do not respect a foreign key constraint,
            // it will be impossible to restore the constraint after deleting it.
            // So, we check this before doing any action.
            if ($this->hasConstraintAnomaly($constraint)) {
                $this->message(
                    "Foreign key constraint anomaly: [{$constraint['name']}] "
                        ."{$constraint['table']}.{$constraint['column']} references "
                        ."{$constraint['relatedTable']}.{$constraint['relatedColumn']}",
                    'error'
                );

                $hasConstraintAnomaly = true;
            }
        }

        if ($hasConstraintAnomaly) {
            return;
        }

        // DROP FOREIGN KEY CONSTRAINTS

        foreach ($this->foreignKeysConstraintsInfo as $constraint) {
            $this->message("Drop foreign on {$constraint['table']}.{$constraint['column']}");

            $this->schemaBuilder->table($constraint['table'], function (Blueprint $blueprint) use ($constraint) {
                $blueprint->dropForeign($constraint['name']);
            });
        }

        // CHANGE INT TO BIGINT

        foreach ($this->intColumnsInfo as $column) {
            $this->message("Change INT to BIGINT for {$column['table']}.{$column['column']}");

            $this->schemaBuilder->table($column['table'], function (Blueprint $blueprint) use ($column) {
                $blueprint
                    ->unsignedBigInteger($column['column'], $column['autoIncrement'])
                    ->nullable($column['nullable'])
                    ->default($column['default'])
                    ->change();
            });
        }

        // RESTORE FOREIGN KEY CONSTRAINTS

        foreach ($this->foreignKeysConstraintsInfo as $constraint) {
            $this->message("Restore foreign on {$constraint['table']}.{$constraint['column']}");

            $this->schemaBuilder->table($constraint['table'], function (Blueprint $blueprint) use ($constraint) {
                $blueprint
                    ->foreign($constraint['column'], $constraint['name'])
                    ->references($constraint['relatedColumn'])
                    ->on($constraint['relatedTable'])
                    ->onDelete($constraint['onDelete'])
                    ->onUpdate($constraint['onUpdate']);
            });
        }
    }

    /**
     * On each table :
     * 1) Extract information on unsigned integer columns that are primary or foreign key.
     * 2) Extract information on foreign keys constraints concerning unsigned integer columns.
     *
     * @return void
     */
    private function extractSchemaInfos(): void
    {
        $this->intColumnsInfo = [];
        $this->foreignKeysConstraintsInfo = [];

        foreach ($this->schemaBuilder->getTables() as $table) {
            $tableName = $table['name'];
            $tableIntColumnsNames = [];

            // GET TABLE KEYS COLUMNS NAMES

            $tableKeysColumnsNames = [];

            // Get all columns for this table
            $columns = $this->schemaBuilder->getColumns($tableName);

            // Get primary keys
            $indexes = $this->schemaBuilder->getIndexes($tableName);
            foreach ($indexes as $index) {
                if ($index['primary']) {
                    $tableKeysColumnsNames = array_merge($tableKeysColumnsNames, $index['columns']);
                }
            }

            // Get foreign keys
            $foreignKeys = $this->schemaBuilder->getForeignKeys($tableName);
            foreach ($foreignKeys as $foreignKey) {
                $tableKeysColumnsNames = array_merge($tableKeysColumnsNames, $foreignKey['columns']);
            }

            // GET UNSIGNED INTEGER COLUMNS NAMES AND INFOS

            foreach ($columns as $column) {
                $columnName = $column['name'];
                $columnType = strtolower($column['type_name'] ?? $column['type'] ?? '');

                // keep only integer columns that are a key
                if (! $this->isIntegerType($columnType)
                    || ! in_array($columnName, $tableKeysColumnsNames)) {
                    continue;
                }

                $tableIntColumnsNames[] = $columnName;

                $this->intColumnsInfo[] = [
                    'table' => $tableName,
                    'column' => $columnName,
                    'nullable' => $column['nullable'],
                    'default' => $column['default'],
                    'autoIncrement' => $column['auto_increment'],
                ];
            }

            // GET FOREIGN KEYS CONSTRAINTS INFOS

            foreach ($foreignKeys as $foreignKey) {
                // keep only foreign keys that are unsigned integer
                if (! in_array($foreignKey['columns'][0], $tableIntColumnsNames)) {
                    continue;
                }

                $this->foreignKeysConstraintsInfo[] = [
                    'name' => $foreignKey['name'],
                    'table' => $tableName,
                    'column' => $foreignKey['columns'][0],
                    'relatedTable' => $foreignKey['foreign_table'],
                    'relatedColumn' => $foreignKey['foreign_columns'][0],
                    'onUpdate' => $foreignKey['on_update'],
                    'onDelete' => $foreignKey['on_delete'],
                ];
            }
        }
    }

    /**
     * Check if a column type is an integer type.
     *
     * @param  string $columnType
     * @return bool
     */
    private function isIntegerType(string $columnType): bool
    {
        $integerTypes = [
            'int',
            'integer',
            'tinyint',
            'smallint',
            'mediumint',
            'bigint',
        ];

        foreach ($integerTypes as $type) {
            if (str_contains($columnType, $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Says if there are data that do not respect a foreign key constraint.
     *
     * @param  array $constraint
     * @return bool
     */
    private function hasConstraintAnomaly(array $constraint): bool
    {
        return $this->connection
            ->table($constraint['table'])
            ->whereNotNull($constraint['column'])
            ->whereNotIn($constraint['column'], function ($query) use ($constraint) {
                $query
                    ->from($constraint['relatedTable'])
                    ->select($constraint['relatedColumn']);
            })
            ->exists();
    }

    /**
     * Print message to the console if set, or do an echo.
     *
     * @param  string $message
     * @param  string $style
     * @return void
     */
    protected function message(string $message, string $style = 'info'): void
    {
        if ($this->command !== null) {
            $this->command->line($message, $style);
        } else {
            echo $message."\n";
        }
    }
}
