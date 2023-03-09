<?php

namespace App\Service;

use App\Entity\Currency;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;

class NBPService
{
    private CurlService $curlService;
    private string $apiUrl;
    private Container $container;
    private EntityManagerInterface $em;

    public function __construct(
        CurlService            $curlService,
        Container              $container,
        EntityManagerInterface $em,
    )
    {
        $this->curlService = $curlService;
        $this->container = $container;
        $this->em = $em;
        $this->apiUrl = $this->container->getParameter('nbp_api_url');
    }

    public function updateCurrencyRates(): string
    {
        $apiUrl = $this->apiUrl;

        $currencyRates = $this->curlService->performRequest($apiUrl);
        $currencyRatesDecoded = json_decode($currencyRates, true);

        if (!isset($currencyRatesDecoded[0]['rates'])) {
            return 'Api returned error';
        }

        foreach ($currencyRatesDecoded[0]['rates'] as $currencyRate) {
            $this->saveOrUpdateRate($currencyRate);
        }

        $this->em->flush();

        return 'Currency rates updated';

    }

    private function saveOrUpdateRate($currencyRate): void
    {
        $currentCurrencyRate = $this->em->getRepository(Currency::class)->findOneBy([
            'currencyCode' => $currencyRate['code']
        ]);

        $currencyMultiplied = false;

        if (number_format($currencyRate['mid'], 2, '.', '') == "0.00") {
            $currencyRateValue = $currencyRate['mid'] * 100;
            $currencyMultiplied = true;
        } else {
            $currencyRateValue = $currencyRate['mid'];
        }

        if (!$currentCurrencyRate) {
            $currentCurrencyRate = new Currency();

            if ($currencyMultiplied) {
                $currentCurrencyRate
                    ->setName($currencyRate['currency'] . ' (' . 100 . ' ' . $currencyRate['code'] . ')');
            } else {
                $currentCurrencyRate
                    ->setName($currencyRate['currency']);
            }

            $currentCurrencyRate
                ->setCurrencyCode($currencyRate['code'])
                ->setExchangeRate(number_format($currencyRateValue, '2', '', ''));
        } else {
            $currentCurrencyRate
                ->setExchangeRate(number_format($currencyRateValue, '2', '', ''));
        }

        $this->em->persist($currentCurrencyRate);
    }

}