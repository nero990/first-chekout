<?php

namespace Nero360\FirstChekout\Facades;

use Illuminate\Support\Facades\Facade;

class FirstChekout extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'firstChekout';
    }
}