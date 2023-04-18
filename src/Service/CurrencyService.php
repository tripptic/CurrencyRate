<?php

namespace App\Service;

use App\Model\ExchangeRateResult;
use App\Provider\RatesProviderInterface;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;

class CurrencyService
{
    protected const RATES_QUEUE_NAME = 'exchange_rates_queue';

    /**
     * @var RatesProviderInterface
     */
    private RatesProviderInterface $currencyRateProvider;

    /**
     * @var MessageBrokerServiceInterface
     */
    private MessageBrokerServiceInterface $messageBrokerService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param RatesProviderInterface $currencyRateProvider
     * @param MessageBrokerServiceInterface $messageBrokerService
     * @param LoggerInterface $logger
     */
    public function __construct(
        RatesProviderInterface $currencyRateProvider,
        MessageBrokerServiceInterface $messageBrokerService,
        LoggerInterface $logger
    ) {
        $this->currencyRateProvider = $currencyRateProvider;
        $this->messageBrokerService = $messageBrokerService;
        $this->logger = $logger;
    }

    /**
     * @param DateTime $date
     * @param string $currencyCode
     * @param string $baseCurrencyCode
     * @return float|null
     */
    public function fetchExchangeRate(DateTime $date, string $currencyCode, string $baseCurrencyCode = 'RUR'): ?float
    {
        return $this->currencyRateProvider->getRate($currencyCode, $baseCurrencyCode, $date);
    }

    /**
     * @param DateTime $date
     * @return DateTime
     */
    private function getPreviousDate(DateTime $date): DateTime
    {
        $previousDate = clone $date;
        $previousDate->modify('-1 day');

        while ($previousDate->format('N') >= 6) {
            $previousDate->modify('-1 day');
        }

        return $previousDate;
    }

    /**
     * @param DateTime $date
     * @param string $currencyCode
     * @param string $baseCurrencyCode
     * @param callback $outputCallback
     * @return void
     */
    public function fetchRatesWithQueue(DateTime $date, string $currencyCode, string $baseCurrencyCode, $outputCallback): void
    {
        $message = json_encode([
            'date' => $date->format('d.m.Y'),
            'currency' => $currencyCode,
            'base_currency' => $baseCurrencyCode
        ]);

        $this->logger->info("Sending message to RabbitMQ: {$message}");

        $this->messageBrokerService->send(self::RATES_QUEUE_NAME, $message);

        $this->messageBrokerService->receive(self::RATES_QUEUE_NAME, function ($msg) use ($outputCallback) {
            try {
                $data = json_decode($msg->body, true);

                $date = DateTime::createFromFormat('d.m.Y', $data['date']);
                $currencyCode = $data['currency'];
                $baseCurrencyCode = $data['base_currency'];

                $rate = $this->fetchExchangeRate($date, $currencyCode, $baseCurrencyCode);
                $previousDate = $this->getPreviousDate($date);
                $previousRate = $this->currencyRateProvider->getRate($currencyCode, $baseCurrencyCode, $previousDate);

                $outputCallback(new ExchangeRateResult($rate, $previousRate));
            } catch (Exception $e) {
                $this->logger->error("Error processing received message: {$e->getMessage()}");
                $outputCallback(new ExchangeRateResult(null, null, $e));
            }
        });
    }
}