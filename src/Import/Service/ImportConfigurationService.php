<?php declare(strict_types=1);

namespace PhallosanCustomizations\Import\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ImportConfigurationService
{
    public const CONFIG_MAIN_PRODUCT_RULE = 'PhallosanCustomizations.config.ImportMainProductRule';
    public const CONFIG_MINOR_PRODUCT_RULE = 'PhallosanCustomizations.config.ImportMinorProductRule';

    public function __construct(
        private SystemConfigService $systemConfigService
    ) {
    }

    public function getMainProductRuleID(): string
    {
        $ruleId = $this->systemConfigService->get(self::CONFIG_MAIN_PRODUCT_RULE);

        if (!\is_string($ruleId) || empty($ruleId)) {
            throw new \RuntimeException("The rule for the main product is not configured. Please check the configuration '" . self::CONFIG_MAIN_PRODUCT_RULE . "'.");
        }

        return $ruleId;
    }

    public function getMinorProductRuleID(): string
    {
        $ruleId = $this->systemConfigService->get(self::CONFIG_MINOR_PRODUCT_RULE);

        if (!\is_string($ruleId) || empty($ruleId)) {
            throw new \RuntimeException("The rule for the minor product is not configured. Please check the configuration '" . self::CONFIG_MINOR_PRODUCT_RULE . "'.");
        }

        return $ruleId;
    }
}
