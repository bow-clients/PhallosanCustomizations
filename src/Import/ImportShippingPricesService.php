<?php declare(strict_types=1);

namespace PhallosanCustomizations\Import;

use PhallosanCustomizations\Import\Repository\ImportCountryRepository;
use PhallosanCustomizations\Import\Repository\ImportRuleConditionRepository;
use PhallosanCustomizations\Import\Repository\ImportSalesChannelRepository;
use PhallosanCustomizations\Import\Repository\ImportShippingMethodPriceRepository;
use PhallosanCustomizations\Import\Service\ImportConfigurationService;
use PhallosanCustomizations\Import\Service\ImportShippingCostsCsvFileReader;
use PhallosanCustomizations\Import\Storer\ImportShippingMethodPriceStorer;
use Psr\Log\LoggerInterface;

class ImportShippingPricesService
{
    public function __construct(
        private readonly ImportConfigurationService $configuration,
        private readonly ImportShippingCostsCsvFileReader $csvReader,
        private readonly ImportCountryRepository $countryRepository,
        private readonly ImportSalesChannelRepository $salesChannelRepository,
        private readonly ImportRuleConditionRepository $ruleConditionRepository,
        private readonly ImportShippingMethodPriceRepository $shippingMethodPriceRepository,
        private readonly ImportShippingMethodPriceStorer $storer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function importCSV(
        string $filePath,
        string $shippingMethodId,
        bool $dryRun = false,
    ): void {
        $this->logger->info("Starting import of shipping costs from file: $filePath");
        $mainProductRuleId = $this->configuration->getMainProductRuleId();
        $minorProductRuleId = $this->configuration->getMinorProductRuleId();

        $shippingCostDTOs = $this->csvReader->readCsvFile($filePath);
        $this->logger->info('Parsed ' . \count($shippingCostDTOs) . ' entries from CSV file.');

        foreach ($shippingCostDTOs as $shippingCostDTO) {
            $this->logger->debug('Processing shipping cost for country: ' . $shippingCostDTO->getCountryIsoCode2());

            try {
                // collect necessary data
                $country = $this->countryRepository->getCountryByIso2($shippingCostDTO->getCountryIsoCode2());
                $salesChannel = $this->salesChannelRepository->getSalesChannelByMainCountryID($country->getId());
                $rule = $this->ruleConditionRepository->getRuleIdByCountryInCondition($country->getId());

                // fetch shipping method prices to update
                $minorShippingMethodPrice = $this->shippingMethodPriceRepository->getByMethodRuleAndCalculationRule(
                    $shippingMethodId,
                    $rule->getId(),
                    $minorProductRuleId,
                );
                $mainShippingMethodPrice = $this->shippingMethodPriceRepository->getByMethodRuleAndCalculationRule(
                    $shippingMethodId,
                    $rule->getId(),
                    $mainProductRuleId,
                );

                // prepare data for upsert
                $data = [
                    $this->storer->buildDataArray(
                        $minorShippingMethodPrice->getId(),
                        $salesChannel->getCurrencyId(),
                        $shippingCostDTO->getMinorProductShippingPrice(),
                    ),
                    $this->storer->buildDataArray(
                        $mainShippingMethodPrice->getId(),
                        $salesChannel->getCurrencyId(),
                        $shippingCostDTO->getMainProductShippingPrice(),
                    ),
                ];

                // upsert data
                if (!$dryRun) {
                    $this->storer->store($data);
                }
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage() . ' (skipping ' . $shippingCostDTO->getCountryIsoCode2() . ')');
            }
        }

        $this->logger->info('Finished import of shipping costs from file: ' . $filePath);
    }
}
