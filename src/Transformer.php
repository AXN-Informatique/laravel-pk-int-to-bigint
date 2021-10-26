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
        $constraintsInfoByTable = [];
        $intColumnsInfoByTable = [];
        $primaryKeyColumnsNamesByTable = [];
        $foreignKeyColumnsNamesByTable = [];
        $hasConstraintAnomaly = false;

        foreach ($this->driver->getTablesNames() as $table) {
            $constraintsInfoByTable[$table] = $this->driver->getForeignKeyConstraintsInfo($table);
            $intColumnsInfoByTable[$table] = $this->driver->getIntColumnsInfo($table);
            $primaryKeyColumnsNamesByTable[$table] = $this->driver->getPrimaryKeyColumnsNames($table);
            
            foreach ($constraintsInfoByTable[$table] as $constraintInfo) {
                $foreignKeyColumnsNamesByTable[$table][] = $constraintInfo['foreignKey'];

                // If there are data that do not respect a foreign key constraint,
                // it will be impossible to restore the constraint after deleting it.
                // So, we check this before doing any action.
                if ($this->hasConstraintAnomaly($table, $constraintInfo)) {
                    $this->message(
                        "Anomaly on constraint {$constraintInfo['constraintName']}: "
                            ."$table.{$constraintInfo['foreignKey']} references "
                            ."{$constraintInfo['relatedTable']}.{$constraintInfo['relatedColumn']}",
                        'error'
                    );
                    $hasConstraintAnomaly = true;
                }
            }
        }

        if ($hasConstraintAnomaly) {
            return;
        }

        // DROP FOREIGN KEY CONSTRAINTS
        foreach ($constraintsInfoByTable as $table => $constraintsInfo) {
            foreach ($constraintsInfo as $constraintInfo) {
                $this->message("Drop foreign on $table.{$constraintInfo['foreignKey']}");

                $this->schema->table($table, function (Blueprint $blueprint) use ($constraintInfo) {
                    $blueprint->dropForeign($constraintInfo['constraintName']);
                });
            }
        }

        // CHANGE INT TO BIGINT
        foreach ($intColumnsInfoByTable as $table => $intColumnsInfo) {
            $keyColumnsNames = array_merge(
                $primaryKeyColumnsNamesByTable[$table],
                $foreignKeyColumnsNamesByTable[$table]
            );

            foreach ($intColumnsInfo as $intColumnInfo) {
                if (! in_array($intColumnInfo['columnName'], $keyColumnsNames)) {
                    continue;
                }

                $this->message("Change INT to BIGINT for $table.{$intColumnInfo['columnName']}");

                $this->schema->table($table, function (Blueprint $blueprint) use ($intColumnInfo) {
                    $blueprint
                        ->unsignedBigInteger($intColumnInfo['columnName'], $intColumnInfo['autoIncrement'])
                        ->nullable($intColumnInfo['nullable'])
                        ->default($intColumnInfo['default'])
                        ->change();
                });
            }
        }

        // RESTORE FOREIGN KEY CONSTRAINTS
        foreach ($constraintsInfoByTable as $table => $constraintsInfo) {
            foreach ($constraintsInfo as $constraintInfo) {
                $this->message("Restore foreign on $table.{$constraintInfo['foreignKey']}");

                $this->schema->table($table, function (Blueprint $blueprint) use ($constraintInfo) {
                    $blueprint
                        ->foreign($constraintInfo['foreignKey'], $constraintInfo['constraintName'])
                        ->references($constraintInfo['relatedColumn'])
                        ->on($constraintInfo['relatedTable'])
                        ->onDelete($constraintInfo['onDelete'])
                        ->onUpdate($constraintInfo['onUpdate']);
                });
            }
        }
    }

    /**
     * Says if there are data that do not respect a foreign key constraint.
     *
     * @param  string $table
     * @param  array  $constraintInfo
     * @return bool
     */
    protected function hasConstraintAnomaly($table, array $constraintInfo)
    {
        return $this->db
            ->table($table)
            ->whereNotNull($constraintInfo['foreignKey'])
            ->whereNotIn($constraintInfo['foreignKey'], function ($query) use ($constraintInfo) {
                $query
                    ->from($constraintInfo['relatedTable'])
                    ->select($constraintInfo['relatedColumn']);
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
