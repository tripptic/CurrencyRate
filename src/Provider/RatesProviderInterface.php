<?php

namespace App\Provider;

use DateTime;

interface RatesProviderInterface
{
    /**
     * @param string $currencyCode
     * @param string $baseCurrencyCode
     * @param DateTime $date
     * @return float|null
     */
    public function getRate(string $currencyCode, string $baseCurrencyCode, DateTime $date): ?float;
}