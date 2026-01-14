<?php declare(strict_types=1);

namespace PhallosanCustomizations\Decorator\Account;

use PhallosanCustomizations\PhallosanConstants;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangeCustomerProfileRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangeEmailRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangePasswordRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractDeleteCustomerRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\AccountProfileController;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AccountProfileControllerDecorator extends AccountProfileController
{
    public function __construct(
        AccountOverviewPageLoader $overviewPageLoader,
        AccountProfilePageLoader $profilePageLoader,
        AbstractChangeCustomerProfileRoute $changeCustomerProfileRoute,
        AbstractChangePasswordRoute $changePasswordRoute,
        AbstractChangeEmailRoute $changeEmailRoute,
        AbstractDeleteCustomerRoute $deleteCustomerRoute,
        LoggerInterface $logger,
        private readonly SystemConfigService $systemConfigService
    ) {
        parent::__construct(
            $overviewPageLoader,
            $profilePageLoader,
            $changeCustomerProfileRoute,
            $changePasswordRoute,
            $changeEmailRoute,
            $deleteCustomerRoute,
            $logger
        );
    }

    public function index(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $affiliateCustomerGroup = $this->systemConfigService->get(PhallosanConstants::PLUGIN_CONFIG_AFFILIATE_GROUPS);
        if (!\is_array($affiliateCustomerGroup)) {
            $affiliateCustomerGroup = [];
        }

        $referrer = $request->server->get('HTTP_REFERER');

        $blockedReferrers = [
            'dvsn/affiliate',
            'account',
        ];

        $allow = true;
        foreach ($blockedReferrers as $blockedReferrer) {
            if (str_contains($referrer, $blockedReferrer)) {
                $allow = false;
            }
        }

        if (\in_array($customer->getGroupId(), $affiliateCustomerGroup, true) && $allow) {
            return $this->redirectToRoute('frontend.dvsn.affiliate');
        }

        return parent::index($request, $context, $customer);
    }
}
