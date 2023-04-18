<?php

namespace Unit\Service;

use App\Model\ExchangeRateResult;
use App\Provider\RatesProviderInterface;
use App\Service\CurrencyService;
use App\Service\MessageBrokerServiceInterface;
use DateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CurrencyServiceTest extends TestCase
{
    /**
     * @var CurrencyService
     */
    private CurrencyService $service;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $provider = $this->createMock(RatesProviderInterface::class);
        $broker = $this->createMock(MessageBrokerServiceInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->service = new CurrencyService($provider, $broker, $logger);
    }

    /**
     * @return void
     */
    public function testFetchExchangeRate(): void
    {
        $provider = $this->createMock(RatesProviderInterface::class);
        $provider
            ->expects($this->once())
            ->method('getRate')
            ->with(new DateTime('2022-01-01'), 'USD', 'RUR')
            ->willReturn(73.123);

        $this->service = new CurrencyService(
            $provider,
            $this->createMock(MessageBrokerServiceInterface::class),
            $this->createMock(LoggerInterface::class)
        );

        $rate = $this->service->fetchExchangeRate(new DateTime('2022-01-01'), 'USD');
        $this->assertEquals(73.123, $rate);
    }

    /**
     * @return void
     */
    public function testFetchRatesWithQueue(): void
    {
        $provider = $this->createMock(RatesProviderInterface::class);
        $provider
            ->method('getRate')
            ->with(new DateTime('2022-01-01'), 'USD', 'RUR')
            ->willReturn(73.123);

        $messageBroker = $this->createMock(MessageBrokerServiceInterface::class);
        $messageBroker
            ->expects($this->once())
            ->method('send')
            ->with(
                CurrencyService::RATES_QUEUE_NAME,
                '{"date":"01.01.2022","currency":"USD","base_currency":"RUR"}'
            );

        $exchangeRateResult = new ExchangeRateResult(73.123, 72.123);
        $outputCallback = function ($result) use ($exchangeRateResult) {
            $this->assertEquals($exchangeRateResult, $result);
        };

        $this->service = new CurrencyService(
            $provider,
            $messageBroker,
            $this->createMock(LoggerInterface::class)
        );
        $this->service->fetchRatesWithQueue(
            new DateTime('2022-01-01'),
            'USD',
            'RUR',
            $outputCallback
        );
    }
}