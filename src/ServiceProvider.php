<?php

namespace Axn\PkIntToBigint;

use Axn\PkIntToBigint\Console\TransformCommand;
use Axn\PkIntToBigint\Transformer;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->app->singleton('command.pk-int-to-bigint.transform', function($app) {
            $transformer = new Transformer($app['db.connection']);

            return new TransformCommand($transformer);
        });

        $this->commands([
            'command.pk-int-to-bigint.transform',
        ]);

        $this->publishes([
            __DIR__.'/../database/migrations/transform_pk_int_to_bigint.php.stub' =>
                database_path('migrations/'.now()->format('Y_m_d_His').'_transform_pk_int_to_bigint.php'),
        ], 'pk-int-to-bigint-migration');
    }
}
