<?php

namespace App\Helpers;

class CurrencyHelper
{
    public static function format($amount, $withSymbol = true)
    {
        $formatted = number_format($amount, 2, '.', ',');
        
        if ($withSymbol) {
            return 'Rs. ' . $formatted;
        }
        
        return $formatted;
    }
    
    public static function toPaisa($amount)
    {
        return (int) round($amount * 100);
    }
    
    public static function fromPaisa($paisa)
    {
        return $paisa / 100;
    }
}
