<?php

namespace Axn\PkIntToBigint\Console;

use Axn\PkIntToBigint\Transformer;
use Illuminate\Console\Command;

class TransformCommand extends Command
{
    protected $signature = 'pk-int-to-bigint:transform';

    protected $description = 'Convert DB primary keys and related foreign keys type from INT to BIGINT in a Laravel project';

    protected $transformer;

    public function __construct(Transformer $transformer)
    {
        $this->transformer = $transformer;
        $this->transformer->setConsoleCommand($this);

        parent::__construct();
    }

    public function handle()
    {
        $this->transformer->transform();
    }
}
