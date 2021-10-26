<?php

namespace Axn\PkIntToBigint\Drivers;

use Illuminate\Support\Collection;

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
     * @return Collection
     */
    public function getForeignKeyConstraintsInfo($table);

    /**
     * @param  string $table
     * @return Collection
     */
    public function getPrimaryKeyColumnsNames($table);

    /**
     * @param  string $table
     * @return Collection
     */
    public function getIntColumnsInfo($table);
}
