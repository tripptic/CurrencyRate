<?php

namespace App\Model;

use Exception;

class ExchangeRateResult
{
    /**
     * @var float|null
     */
    private ?float $rate;

    /**
     * @var float|null
     */
    private ?float $previousRate;

    /**
     * @var Exception|null
     */
    private ?Exception $exception;

    /**
     * @param float|null $rate
     * @param float|null $previousRate
     * @param Exception|null $exception
     */
    public function __construct(?float $rate = null, ?float $previousRate = null, Exception $exception = null)
    {
        $this->rate = $rate;
        $this->previousRate = $previousRate;
        $this->exception = $exception;
    }

    /**
     * @return float
     */
    public function getRate(): float
    {
        return $this->rate;
    }

    /**
     * @return float
     */
    public function getPreviousRate(): float
    {
        return $this->previousRate;
    }

    /**
     * @return Exception|null
     */
    public function getException(): ?Exception
    {
        return $this->exception;
    }
}