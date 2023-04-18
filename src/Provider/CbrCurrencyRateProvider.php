<?php

namespace App\Provider;

use DateTime;
use DOMDocument;
use DOMNodeList;
use DOMXPath;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Logger;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;

class CbrCurrencyRateProvider implements RatesProviderInterface
{
    /**
     * @var int
     */
    private const RATE_CACHE_TTL = 3600;

    /**
     * @var string
     */
    private const CBR_DAILY_URL = 'https://www.cbr.ru/scripts/XML_daily.asp?date_req=%s';

    /**
     * @var string
     */
    public const BASE_CURRENCY_CODE = 'RUR';

    /**
     * @var AdapterInterface
     */
    private AdapterInterface $cache;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param AdapterInterface $cache
     * @param Logger $logger
     */
    public function __construct(AdapterInterface $cache, LoggerInterface $logger)
    {
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * @param string $currencyCode
     * @param string $baseCurrencyCode
     * @param DateTime $date
     * @return float|null
     * @throws InvalidArgumentException|GuzzleException
     */
    public function getRate(DateTime $date, string $currencyCode, string $baseCurrencyCode = self::BASE_CURRENCY_CODE): ?float
    {
        try {
            $cacheKey = $this->createCacheKey($currencyCode, $baseCurrencyCode, $date);
            $cachedItem = $this->cache->getItem($cacheKey);
            if ($cachedItem->isHit()) {
                return $cachedItem->get();
            }

            $rate = $this->findRateInXml($date, $currencyCode, $baseCurrencyCode);

            if ($rate === null) {
                throw new Exception("Currency code {$currencyCode} not found");
            }

            $this->cacheRate($cacheKey, $rate);

            return $rate;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), [
                'currencyCode' => $currencyCode,
                'baseCurrencyCode' => $baseCurrencyCode,
                'date' => $date,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * @param string $currencyCode
     * @param string $baseCurrencyCode
     * @param DateTime $date
     * @return string
     */
    private function createCacheKey(string $currencyCode, string $baseCurrencyCode, DateTime $date): string
    {
        return "rate_{$currencyCode}_{$baseCurrencyCode}_{$date->format('Ymd')}";
    }

    /**
     * @param string $cacheKey
     * @param float $rate
     * @return void
     * @throws InvalidArgumentException
     */
    private function cacheRate(string $cacheKey, float $rate): void
    {
        $cachedItem = $this->cache->getItem($cacheKey);
        $cachedItem->set($rate);
        $cachedItem->expiresAfter(self::RATE_CACHE_TTL);
        $this->cache->saveDeferred($cachedItem);
        $this->cache->commit();
    }

    /**
     * @param DateTime $date
     * @param string $currency
     * @param string $baseCurrency
     * @return float|null
     * @throws Exception|GuzzleException
     */
    private function findRateInXml(DateTime $date, string $currency, string $baseCurrency = self::BASE_CURRENCY_CODE): ?float
    {
        $url = $this->createUrl($date);
        try{
            $client = new Client();
            $response = $client->get($url);
            $xmlContent = $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to fetch data from CBR API.', [
                'url' => $url,
                'exception' => $e,
            ]);
            throw $e;
        }

        $dom = new DOMDocument();
        @$dom->loadXML($xmlContent);

        $xpath = new DOMXPath($dom);
        $currencyNode = $this->findCurrencyCodeNode($xpath, $currency);

        $exchangeRate = $this->parseExchangeRate($currencyNode);

        if ($baseCurrency !== self::BASE_CURRENCY_CODE) {
            $baseCurrencyNode = $this->findCurrencyCodeNode($xpath, $baseCurrency);
            $baseExchangeRate = $this->parseExchangeRate($baseCurrencyNode);
            $baseNominal = $this->parseNominal($baseCurrencyNode);
            $nominal = $this->parseNominal($currencyNode);
            $exchangeRate = ($exchangeRate / $baseExchangeRate) * ($baseNominal / $nominal);
        }

        return $exchangeRate;
    }

    /**
     * @param DateTime $date
     * @return string
     */
    private function createUrl(DateTime $date): string
    {
        return sprintf(self::CBR_DAILY_URL, $date->format('d.m.Y'));
    }

    /**
     * @param DOMXPath $xpath
     * @param $currencyCode
     * @return mixed
     * @throws Exception
     */
    private function findCurrencyCodeNode(DOMXPath $xpath, $currencyCode): mixed
    {
        $baseCurrencyNode = $xpath->query("//Valute[CharCode='{$currencyCode}']");
        if (!$baseCurrencyNode->length) {
            throw new Exception("Currency node not found: {$currencyCode}");
        }
        return $baseCurrencyNode;
    }

    /**
     * @param DOMNodeList $currencyNode
     * @return float
     * @throws Exception
     */
    private function parseExchangeRate(DOMNodeList $currencyNode): float
    {
        $rate = $currencyNode->item(0)?->getElementsByTagName('Value')?->item(0)?->nodeValue;
        if (is_null($rate)) {
            throw new Exception("Rate value not found");
        }
        return (float)str_replace(',', '.', $rate);
    }

    /**
     * @param DOMNodeList $currencyNode
     * @return int
     * @throws Exception
     */
    private function parseNominal(DOMNodeList $currencyNode): int
    {
        $rate = $currencyNode->item(0)?->getElementsByTagName('Nominal')?->item(0)?->nodeValue;
        if (is_null($rate)) {
            throw new Exception("Nominal value not found");
        }
        return (float)str_replace(',', '.', $rate);
    }
}