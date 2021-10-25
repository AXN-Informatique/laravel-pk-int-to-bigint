<?php

namespace Axn\PkIntToBigint;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        if ($this->app->runningInConsole()) {

            $this->app->singleton('command.pk-int-to-bigint.transform', function($app) {
                $db = $app['db.connection'];
                $driverClass = __NAMESPACE__.'\Drivers\\'.ucfirst($db->getDriverName()).'Driver';
                $driver = new $driverClass($db->getPdo());
    
                $transformer = new Transformer($driver, $db->getSchemaBuilder());
    
                return new Console\TransformCommand($transformer);
            });
    
            $this->commands([
                'command.pk-int-to-bigint.transform'
            ]);
        }
    }
}
