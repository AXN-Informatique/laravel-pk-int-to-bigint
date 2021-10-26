<?php

namespace Axn\PkIntToBigint;

use Axn\PkIntToBigint\Drivers\Driver;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as Schema;

class Transformer
{
    protected Driver $driver;

    protected Connection $db;

    protected Schema $schema;

    protected $command = null;

    /**
     * @param Driver $driver
     * @param Connection $connection
     */
    public function __construct(Driver $driver, Connection $db)
    {
        $this->driver = $driver;
        $this->db = $db;
        $this->schema = $db->getSchemaBuilder();
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
        $foreignKeyConstraintsInfo = collect();
        $intColumnsInfo = collect();

        foreach ($this->driver->getTablesNames() as $table) {
            $tableForeignKeyConstraintsInfo = $this->driver->getForeignKeyConstraintsInfo($table);

            // keys = primary keys + foreign keys
            $tableKeyColumnsNames = $tableForeignKeyConstraintsInfo
                ->pluck('column')
                ->merge($this->driver->getPrimaryKeyColumnsNames($table));

            // keep only INT columns that are a key
            $tableIntColumnsInfo = $this->driver->getIntColumnsInfo($table)
                ->filter(fn ($column) => $tableKeyColumnsNames->contains($column['column']));

            // keep only foreign keys that are INT
            $tableForeignKeyConstraintsInfo = $tableForeignKeyConstraintsInfo
                ->filter(fn ($constraint) => $tableIntColumnsInfo->contains('column', $constraint['column']));

            $foreignKeyConstraintsInfo = $foreignKeyConstraintsInfo->merge($tableForeignKeyConstraintsInfo);
            $intColumnsInfo = $intColumnsInfo->merge($tableIntColumnsInfo);
        }

        $hasConstraintAnomaly = false;

        foreach ($foreignKeyConstraintsInfo as $constraint) {
            // If there are data that do not respect a foreign key constraint,
            // it will be impossible to restore the constraint after deleting it.
            // So, we check this before doing any action.
            if ($this->hasConstraintAnomaly($constraint)) {
                $this->message(
                    "Anomaly on constraint {$constraint['name']}: "
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
        foreach ($foreignKeyConstraintsInfo as $constraint) {
            $this->message("Drop foreign on {$constraint['table']}.{$constraint['column']}");

            $this->schema->table($constraint['table'], function (Blueprint $blueprint) use ($constraint) {
                $blueprint->dropForeign($constraint['name']);
            });
        }

        // CHANGE INT TO BIGINT
        foreach ($intColumnsInfo as $column) {
            $this->message("Change INT to BIGINT for {$column['table']}.{$column['column']}");

            $this->schema->table($column['table'], function (Blueprint $blueprint) use ($column) {
                $blueprint
                    ->unsignedBigInteger($column['column'], $column['autoIncrement'])
                    ->nullable($column['nullable'])
                    ->default($column['default'])
                    ->change();
            });
        }

        // RESTORE FOREIGN KEY CONSTRAINTS
        foreach ($foreignKeyConstraintsInfo as $constraint) {
            $this->message("Restore foreign on {$constraint['table']}.{$constraint['column']}");

            $this->schema->table($constraint['table'], function (Blueprint $blueprint) use ($constraint) {
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
     * Says if there are data that do not respect a foreign key constraint.
     *
     * @param  array  $constraint
     * @return bool
     */
    protected function hasConstraintAnomaly(array $constraint)
    {
        return $this->db
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
