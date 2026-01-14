<?php declare(strict_types=1);

namespace PhallosanCustomizations\Subscriber;

use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SaleschannelCountrySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EntityRepository $countryRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => 'onStorefrontRender',
        ];
    }

    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        /** @var Request|null $request */
        $request = $this->requestStack->getMainRequest();

        if (!$request) {
            return;
        }

        /** @var SessionInterface $session */
        $session = $request->getSession();

        $salesChannelContext = $event->getSalesChannelContext();
        $key = $salesChannelContext->getSalesChannelId() . PhallosanConstants::SESSION_SALES_CHANNEL_COUNTRY;

        $defaultCountryId = $salesChannelContext->getSalesChannel()->getCountryId();
        $country = $this->getCountryById($defaultCountryId, $salesChannelContext->getContext());

        if (!$country) {
            return;
        }

        $translated = $country->getTranslated();
        $countryName = !empty($translated['name']) ? $translated['name'] : $country->getName();

        $session->set($key, $countryName);

        $session->set(PhallosanConstants::SESSION_SALES_CHANNEL_COUNTRY_ID, $defaultCountryId);

        $isEu = $country->getIsEu();
        $session->set(PhallosanConstants::SESSION_SALES_CHANNEL_COUNTRY_IS_EU, $isEu);

        $salesChannelContext->addExtension(
            PhallosanConstants::EXTENSION_SALES_CHANNEL_COUNTRY,
            new ArrayStruct([
                'country' => $countryName,
                'countryId' => $session->get(PhallosanConstants::SESSION_SALES_CHANNEL_COUNTRY_ID),
                'countryIsEu' => $session->get(PhallosanConstants::SESSION_SALES_CHANNEL_COUNTRY_IS_EU),
            ])
        );
    }

    private function getCountryById(string $countryId, Context $context): ?CountryEntity
    {
        /** @var CountryEntity|null $countryEntity */
        $countryEntity = $this->countryRepository->search(new Criteria([$countryId]), $context)->first();

        return $countryEntity;
    }
}
