<?php

namespace Axn\PkIntToBigint\Console;

use Axn\PkIntToBigint\Transformer;
use Illuminate\Console\Command;

class TransformCommand extends Command
{
    protected $signature = 'pk-int-to-bigint:transform';

    protected $description = 'Convert DB primary keys and related foreign keys type from INT to BIGINT in a Laravel project';

    public function handle(Transformer $transformer)
    {
        $transformer->setConsoleCommand($this);
        $transformer->transform();
    }
}
