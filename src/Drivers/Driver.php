<?php

namespace Axn\PkIntToBigint\Drivers;

interface Driver
{
    /**
     * @return array
     */
    public function getTablesNames();

    /**
     * @param  string $table
     * @return string
     */
    public function getSqlCreateTable($table);

    /**
     * @param  string $table
     * @return array
     */
    public function getForeignKeyConstraintsInfo($table);

    /**
     * @param  string $table
     * @return array
     */
    public function getPrimaryKeyColumnsNames($table);

    /**
     * @param  string $table
     * @return array
     */
    public function getIntColumnsInfo($table);
}
