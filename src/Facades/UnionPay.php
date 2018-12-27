<?php

namespace Lewee\UnionPay\Facades;
use Illuminate\Support\Facades\Facade;

class UnionPay extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'unionPay';
    }
}
