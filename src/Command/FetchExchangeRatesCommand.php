<?php

namespace App\Command;

use App\Model\ExchangeRateResult;
use App\Service\CurrencyService;
use DateTime;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\DateTime as DateTimeConstraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;


class FetchExchangeRatesCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'app:fetch-exchange-rates';

    /**
     * @var CurrencyService
     */
    private CurrencyService $currencyService;

    /**
     * @var ValidatorInterface
     */
    private ValidatorInterface $validator;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param CurrencyService $currencyService
     * @param ValidatorInterface $validator
     * @param LoggerInterface $logger
     */
    public function __construct(
        CurrencyService $currencyService,
        ValidatorInterface $validator,
        LoggerInterface $logger
    ) {
        $this->currencyService = $currencyService;
        $this->validator = $validator;
        $this->logger = $logger;
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setDescription('Fetches exchange rates.')
            ->addArgument('date', InputArgument::REQUIRED, 'Date (format: dd/mm/yyyy)')
            ->addArgument('currency', InputArgument::REQUIRED, 'Currency code (e.g. USD)')
            ->addArgument(
                'base_currency',
                InputArgument::OPTIONAL,
                'Base currency code (default: RUR)',
                'RUR'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $date = $input->getArgument('date');
        $currencyCode = $input->getArgument('currency');
        $baseCurrencyCode = $input->getArgument('base_currency');

        try {
            $this->validateInput($date, $currencyCode, $baseCurrencyCode);
            $dateObj = DateTime::createFromFormat('d.m.Y', $date);

            $callbackFunction = function (ExchangeRateResult $result)
                use ($output, $dateObj, $currencyCode, $baseCurrencyCode)
            {
                if ($result->getException()) {
                    $output->writeln('<error>Failed to get exchange rates. Try later.</error>');
                    return;
                }
                $output->writeln("Base currency: {$baseCurrencyCode}");
                $output->writeln(
                    "Exchange rate for {$currencyCode}"
                    . " on {$dateObj->format('Y-m-d')}: {$result->getRate()}"
                );
                $output->writeln("Exchange rate on previous trading day: {$result->getPreviousRate()}");
                $difference = number_format($result->getRate() - $result->getPreviousRate(), 4);
                $output->writeln(
                    "Rate difference with previous trading day: " . ($difference >= 0 ? '+' : '') . "$difference"
                );
            };

            $this->currencyService->fetchRatesWithQueue($dateObj, $currencyCode, $baseCurrencyCode, $callbackFunction);
        } catch (InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        } catch (Exception $e) {
            $output->writeln('<error>Failed to get exchange rates. Try later.</error>');
            $this->logger->error($e->getMessage());
        }

        return Command::SUCCESS;
    }

    /**
     * @param string $date
     * @param string $currencyCode
     * @param string $baseCurrencyCode
     * @return void
     */
    private function validateInput(string $date, string $currencyCode, string $baseCurrencyCode): void
    {
        $dateConstraint = new DateTimeConstraint(['format' => 'd.m.Y']);
        $currencyCodeConstraint = new Assert\Regex(['pattern' => '/^[A-Z]{3}$/']);

        $dateViolations = $this->validator->validate($date, $dateConstraint);
        $currencyViolations = $this->validator->validate($currencyCode, $currencyCodeConstraint);
        $baseCurrencyViolations = $this->validator->validate($baseCurrencyCode, $currencyCodeConstraint);

        if (count($dateViolations) > 0) {
            throw new InvalidArgumentException("Invalid date format. Please use 'd.m.Y' format.");
        }

        if (count($currencyViolations) > 0) {
            throw new InvalidArgumentException(
                "Invalid currency code. Please provide a 3-letter uppercase currency code."
            );
        }

        if (count($baseCurrencyViolations) > 0) {
            throw new InvalidArgumentException(
                "Invalid base currency code. Please provide a 3-letter uppercase currency code."
            );
        }

        $today = new DateTime();
        $dateObj = DateTime::createFromFormat('d.m.Y', $date);
        if ($dateObj > $today) {
            throw new InvalidArgumentException("Invalid date. The date cannot be greater than today's date");
        }
    }
}