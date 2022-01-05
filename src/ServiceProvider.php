<?php

namespace Axn\PkIntToBigint;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->app->singleton('command.pk-int-to-bigint.transform', function($app) {
                $transformer = new Transformer($app['db.connection']);

                return new Console\TransformCommand($transformer);
            });

            $this->commands([
                'command.pk-int-to-bigint.transform'
            ]);
        }
    }
}
