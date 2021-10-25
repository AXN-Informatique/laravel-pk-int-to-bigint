<?php

namespace Axn\PkIntToBigint;

use Axn\PkIntToBigint\Drivers\Driver;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as Schema;

class Transformer
{
    protected Driver $driver;

    protected Schema $schema;

    protected $command = null;

    /**
     * @param Driver $driver
     * @param Schema $schema
     */
    public function __construct(Driver $driver, Schema $schema)
    {
        $this->driver = $driver;
        $this->schema = $schema;
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

        foreach ($this->driver->getTablesNames() as $table) {
            $constraintsInfoByTable[$table] = $this->driver->getForeignKeyConstraintsInfo($table);
            $intColumnsInfoByTable[$table] = $this->driver->getIntColumnsInfo($table);
            $primaryKeyColumnsNamesByTable[$table] = $this->driver->getPrimaryKeyColumnsNames($table);
        }

        // DROP FOREIGN KEY CONSTRAINTS
        foreach ($constraintsInfoByTable as $table => $constraintsInfo) {
            $foreignKeyColumnsNamesByTable[$table] = [];

            $this->schema->table($table, function (Blueprint $blueprint) use ($constraintsInfo, &$foreignKeyColumnsNamesByTable, $table) {
                foreach ($constraintsInfo as $constraintInfo) {
                    $this->message("Drop foreign on $table.{$constraintInfo['foreignKey']}");

                    $blueprint->dropForeign($constraintInfo['constraintName']);

                    $foreignKeyColumnsNamesByTable[$table][] = $constraintInfo['foreignKey'];
                }
            });
        }

        // CHANGE INT TO BIGINT
        foreach ($intColumnsInfoByTable as $table => $intColumnsInfo) {
            $keyColumnsNames = array_merge(
                $primaryKeyColumnsNamesByTable[$table],
                $foreignKeyColumnsNamesByTable[$table]
            );

            $this->schema->table($table, function (Blueprint $blueprint) use ($intColumnsInfo, $keyColumnsNames, $table) {
                foreach ($intColumnsInfo as $intColumnInfo) {
                    if (! in_array($intColumnInfo['columnName'], $keyColumnsNames)) {
                        continue;
                    }

                    $this->message("Change INT to BIGINT for $table.{$intColumnInfo['columnName']}");

                    $blueprint
                        ->unsignedBigInteger($intColumnInfo['columnName'], $intColumnInfo['autoIncrement'])
                        ->nullable($intColumnInfo['nullable'])
                        ->default($intColumnInfo['default'])
                        ->change();
                }
            });
        }

        // RESTORE FOREIGN KEY CONSTRAINTS
        foreach ($constraintsInfoByTable as $table => $constraintsInfo) {
            $this->schema->table($table, function (Blueprint $blueprint) use ($constraintsInfo, $table) {
                foreach ($constraintsInfo as $constraintInfo) {
                    $this->message("Restore foreign on $table.{$constraintInfo['foreignKey']}");

                    $blueprint
                        ->foreign($constraintInfo['foreignKey'], $constraintInfo['constraintName'])
                        ->references($constraintInfo['relatedColumn'])
                        ->on($constraintInfo['relatedTable']);
                }
            });
        }
    }

    /**
     * Print message to the console if set, or do an echo.
     *
     * @param  string $message
     * @return void
     */
    protected function message($message)
    {
        if ($this->command !== null) {
            $this->command->info($message);
        } else {
            echo $message."\n";
        }
    }
}
