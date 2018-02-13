<?php


namespace Nero360\FirstChekout;

use Illuminate\Support\ServiceProvider;

class FirstChekoutServiceProvider extends ServiceProvider
{

    public function boot()
    {
        $path = realpath(__DIR__.'/../resources/config/firstChekout.php');

        $this->publishes([
            $path => config_path('firstChekout.php')
        ]);
    }

    public function register()
    {
        $this->app->bind('firstChekout', function () {
            return new FirstChekout;
        });
    }

}