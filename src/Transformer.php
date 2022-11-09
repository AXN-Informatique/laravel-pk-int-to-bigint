<?php

namespace Axn\PkIntToBigint;

use Doctrine\DBAL\Schema\AbstractSchemaManager as DoctrineSchemaManager;
use Doctrine\DBAL\Types\IntegerType;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class Transformer
{
    protected Connection $connection;

    protected DoctrineSchemaManager $doctrineSchemaManager;

    protected SchemaBuilder $schemaBuilder;

    protected $command = null;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->doctrineSchemaManager = $connection->getDoctrineSchemaManager();
        $this->schemaBuilder = $connection->getSchemaBuilder();
    }

    /**
     * Set console command instance for printing message to the console.
     *
     * @param  Command $command
     * @return void
     */
    public function setConsoleCommand(Command $command)
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
    public function transform()
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
    protected function extractSchemaInfos()
    {
        $this->intColumnsInfo = [];
        $this->foreignKeysConstraintsInfo = [];

        foreach ($this->doctrineSchemaManager->listTables() as $table) {
            $tableIntColumnsNames = [];

            // GET TABLE KEYS COLUMNS NAMES

            $tableKeysColumnsNames = [];

            // primary keys...
            if ($primaryKey = $table->getPrimaryKey()) {
                $tableKeysColumnsNames = $primaryKey->getColumns();
            }

            // ... + foreign keys
            foreach ($table->getForeignKeys() as $foreignKey) {
                $tableKeysColumnsNames = array_merge($tableKeysColumnsNames, $foreignKey->getLocalColumns());
            }

            // GET UNSIGNED INTEGER COLUMNS NAMES AND INFOS

            foreach ($table->getColumns() as $column) {
                // keep only unsigned integer columns that are a key
                if (! $column->getType() instanceof IntegerType
                    || ! $column->getUnsigned()
                    || ! in_array($column->getName(), $tableKeysColumnsNames)) {

                    continue;
                }

                $tableIntColumnsNames[] = $column->getName();

                $this->intColumnsInfo[] = [
                    'table' => $table->getName(),
                    'column' => $column->getName(),
                    'nullable' => ! $column->getNotnull(),
                    'default' => $column->getDefault(),
                    'autoIncrement' => $column->getAutoincrement(),
                ];
            }

            // GET FOREIGN KEYS CONSTRAINTS INFOS

            foreach ($table->getForeignKeys() as $foreignKey) {
                // keep only foreign keys that are unsigned integer
                if (! in_array($foreignKey->getLocalColumns()[0], $tableIntColumnsNames)) {
                    continue;
                }

                $this->foreignKeysConstraintsInfo[] = [
                    'name' => $foreignKey->getName(),
                    'table' => $foreignKey->getLocalTableName(),
                    'column' => $foreignKey->getLocalColumns()[0],
                    'relatedTable' => $foreignKey->getForeignTableName(),
                    'relatedColumn' => $foreignKey->getForeignColumns()[0],
                    'onUpdate' => $foreignKey->onUpdate(),
                    'onDelete' => $foreignKey->onDelete(),
                ];
            }
        }
    }

    /**
     * Says if there are data that do not respect a foreign key constraint.
     *
     * @param  array $constraint
     * @return bool
     */
    protected function hasConstraintAnomaly(array $constraint)
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
    protected function message($message, $style = 'info')
    {
        if ($this->command !== null) {
            $this->command->line($message, $style);
        } else {
            echo $message."\n";
        }
    }
}
