<?php

namespace Lewee\UnionPay;

use Illuminate\Support\ServiceProvider;

class UnionPayProvider extends ServiceProvider
{
    protected $defer = true;

    public function boot()
    {
        $this->publishes([
            __DIR__. '/../config/union-pay.php' => config_path('union-pay.php'),
        ]);
    }

    public function register()
    {
        $this->app->singleton('unionPay', function ($app) {
            return new UnionPay($app['session'], $app['config']);
        });
    }

    public function provides()
    {
        return [
            'unionPay',
        ];
    }
}
