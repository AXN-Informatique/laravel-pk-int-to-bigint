<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    public function up()
    {
        if (! class_exists(Axn\PkIntToBigint\ServiceProvider::class)) {
            return;
        }

        Artisan::call('pk-int-to-bigint:transform');
    }
};
