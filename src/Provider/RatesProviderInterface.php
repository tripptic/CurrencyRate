<?php

namespace App\Provider;

use DateTime;

interface RatesProviderInterface
{
    /**
     * @param DateTime $date
     * @param string $currencyCode
     * @param string $baseCurrencyCode
     * @return float|null
     */
    public function getRate(DateTime $date, string $currencyCode, string $baseCurrencyCode): ?float;
}