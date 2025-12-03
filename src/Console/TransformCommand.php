<?php

namespace Axn\PkIntToBigint\Console;

use Axn\PkIntToBigint\Transformer;
use Illuminate\Console\Command;

class TransformCommand extends Command
{
    protected $signature = 'pk-int-to-bigint:transform
                            {--database= : The database/schema to use}';

    protected $description = 'Convert DB primary keys and related foreign keys type from INT to BIGINT in a Laravel project';

    public function handle(Transformer $transformer): void
    {
        $transformer->setConsoleCommand($this);

        if ($database = $this->option('database')) {
            $transformer->setSchema($database);
        }

        $transformer->transform();
    }
}
