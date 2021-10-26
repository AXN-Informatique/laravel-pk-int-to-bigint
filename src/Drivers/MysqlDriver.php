<?php

namespace Axn\PkIntToBigint\Drivers;

use Illuminate\Support\Collection;
use PDO;

class MysqlDriver implements Driver
{
    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * @var array
     */
    protected $sqlCreateTable = [];

    /**
     * @param  PDO $pdo
     * @return void
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array
     */
    public function getTablesNames()
    {
        $query = $this->pdo->query('SHOW FULL TABLES WHERE Table_Type != "VIEW"');

        return $query->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @param  string $table
     * @return string
     */
    public function getSqlCreateTable($table)
    {
        if (empty($this->sqlCreateTable[$table])) {
            $query = $this->pdo->query("SHOW CREATE TABLE $table");

            $this->sqlCreateTable[$table] = $query->fetchColumn(1);
        }

        return $this->sqlCreateTable[$table];
    }

    /**
     * @param  string $table
     * @return Collection
     */
    public function getForeignKeyConstraintsInfo($table)
    {
        preg_match_all(
            '/CONSTRAINT `(\w+)` FOREIGN KEY \(`(\w+)`\) REFERENCES `(\w+)` \(`(\w+)`\)'
                .'( ON DELETE (CASCADE|SET NULL|NO ACTION)|)'
                .'( ON UPDATE (CASCADE|SET NULL|NO ACTION)|)/Us',
            $this->getSqlCreateTable($table),
            $matches
        );

        $constraintsInfo = [];

        foreach ($matches[0] as $index => $constraint) {
            $constraintsInfo[] = [
                'name'          => $matches[1][$index],
                'table'         => $table,
                'column'        => $matches[2][$index],
                'relatedTable'  => $matches[3][$index],
                'relatedColumn' => $matches[4][$index],
                'onDelete'      => $matches[6][$index] ?: null,
                'onUpdate'      => $matches[8][$index] ?: null,
            ];
        }

        return collect($constraintsInfo);
    }

    /**
     * @param  string $table
     * @return Collection
     */
    public function getPrimaryKeyColumnsNames($table)
    {
        preg_match(
            '/PRIMARY KEY \(([\w`,]+)\)/Us',
            $this->getSqlCreateTable($table),
            $matches
        );

        if (! isset($matches[1])) {
            return collect();
        }

        return collect(explode(',', str_replace('`', '', $matches[1])));
    }

    /**
     * @param  string $table
     * @return Collection
     */
    public function getIntColumnsInfo($table)
    {
        preg_match_all(
            '/`(\w+)` int\(\d{1,2}\) unsigned(.*),/Us',
            $this->getSqlCreateTable($table),
            $matches
        );

        $columnsInfo = [];

        foreach ($matches[0] as $index => $column) {
            if (preg_match('/DEFAULT \'(.+)\'/U', $matches[2][$index], $defaultMatches)) {
                $default = $defaultMatches[1];
            } else {
                $default = null;
            }

            $columnsInfo[] = [
                'table'         => $table,
                'column'        => $matches[1][$index],
                'autoIncrement' => strpos($matches[2][$index], 'AUTO_INCREMENT') !== false,
                'nullable'      => strpos($matches[2][$index], 'NOT NULL') === false,
                'default'       => $default
            ];
        }

        return collect($columnsInfo);
    }
}
