<?php declare(strict_types=1);

namespace PhallosanCustomizations\LanguagePopUp\Decorator;

use GuzzleHttp\Client;
use NetInventors\NetiNextLanguageDetector\Core\Content\SalesChannelDomainPriority\SalesChannelDomainPriorityEntity;
use NetInventors\NetiNextLanguageDetector\Errors\IpLocationApiError;
use NetInventors\NetiNextLanguageDetector\Extension\Content\SalesChannelDomain\SalesChannelDomainExtension;
use NetInventors\NetiNextLanguageDetector\Service\LanguageDetectorService;
use NetInventors\NetiNextLanguageDetector\Service\LogService;
use NetInventors\NetiNextLanguageDetector\Storefront\Controller\LanguageDetectorController;
use NetInventors\NetiNextLanguageDetector\Struct\PluginConfigStruct;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class LanguageDetectorControllerDecorator extends LanguageDetectorController
{
    public function __construct(
        EntityRepository $domainRepository,
        private readonly PluginConfigStruct $pluginConfig,
        private readonly LanguageDetectorService $languageDetectorService,
        private readonly LogService $logger,
        private readonly Translator $translator,
        SalesChannelContextPersister $contextPersister
    ) {
        parent::__construct(
            $domainRepository,
            $pluginConfig,
            $languageDetectorService,
            $logger,
            $translator,
            $contextPersister
        );
    }

    public function checkLanguage(Request $request, SalesChannelContext $salesChannelContext): JsonResponse
    {
        $debugIPs = \explode(',', $this->pluginConfig->getLogAddresses());

        if (!$this->pluginConfig->isActive()) {
            $this->addLogEntry(
                $request,
                'Plugin not active',
                [],
                $debugIPs,
            );

            $response = new JsonResponse(
                [
                    'success' => false,
                ],
            );
            $response->headers->set('X-Robots-Tag', 'noindex, follow');

            return $response;
        }

        $acceptLanguage = $this->getValidHeaderAcceptLanguage($request);
        if ($acceptLanguage === null) {
            $this->addLogEntry(
                $request,
                'Request header "Accept-Language" is missing or invalid',
                [
                    'acceptLanguage' => $acceptLanguage,
                ],
                $debugIPs,
            );

            $response = new JsonResponse(
                [
                    'success' => false,
                ],
            );
            $response->headers->set('X-Robots-Tag', 'noindex, follow');

            return $response;
        }

        $userLocale = $this->getUserLocaleLanguage($acceptLanguage);
        if ($userLocale === null) {
            $this->addLogEntry(
                $request,
                'Request header "Accept-Language" is invalid',
                [
                    'acceptLanguage' => $acceptLanguage,
                ],
                $debugIPs,
            );

            $response = new JsonResponse(
                [
                    'success' => false,
                ],
            );
            $response->headers->set('X-Robots-Tag', 'noindex, follow');

            return $response;
        }

        // example IPS for debugging
        // DE - 85.214.132.117
        // US - 170.171.1.1
        // GB - 178.238.11.6
        // CH - 37.140.254.101
        // AT - 213.208.157.36
        // JP - 210.138.184.59

        $userCountry = '';

        if ($this->pluginConfig->getDetectionMethod() === PluginConfigStruct::DETECTION_METHOD_IP) {
            try {
                $userCountry = $this->getLocationByIp($request->getClientIp());
                $userLocale = preg_replace('/^([a-z]{2})(-[A-Z]{2})?$/', "$1-$userCountry", $userLocale);
            } catch (IpLocationApiError $e) {
                $this->addLogEntry(
                    $request,
                    'Error detecting the IP location: ' . $e->getMessage(),
                    [
                        'ipAddress' => $e->getIpAddress(),
                        'responseStatusCode' => $e->getResponse()?->getStatusCode(),
                        'responseReasonPhrase' => $e->getResponse()?->getReasonPhrase(),
                        'responseBody' => $e->getResponse()?->getBody()->getContents(),
                    ],
                    $debugIPs,
                );
            }
        }

        $currentLocalEntity = $this->getCurrentLocalEntity($salesChannelContext);
        if ($currentLocalEntity === null) {
            $response = new JsonResponse(
                [
                    'success' => false,
                ],
            );
            $response->headers->set('X-Robots-Tag', 'noindex, follow');

            return $response;
        }

        $shopLocale = $currentLocalEntity->getCode();
        if (\str_contains($shopLocale, (string) $userLocale)) {
            $this->addLogEntry(
                $request,
                'Shop locale is User-Locale',
                [
                    'shopLocale' => $shopLocale,
                    'userLocale' => $userLocale,
                ],
                $debugIPs,
            );

            $response = new JsonResponse(
                [
                    'redirectNotRequired' => true,
                    'setCookie' => !\in_array(
                        $request->getClientIp(),
                        explode(',', $this->pluginConfig->getNoCookieIps()),
                        true,
                    ),
                ],
            );
            $response->headers->set('X-Robots-Tag', 'noindex, follow');

            return $response;
        }

        $domains = $this->getSalesChannelDomains($salesChannelContext);
        if ($domains === null) {
            $this->addLogEntry(
                $request,
                'No domains found',
                [
                    'shopLocale' => $shopLocale,
                    'userLocale' => $userLocale,
                ],
                $debugIPs,
            );

            $response = new JsonResponse([
                'success' => false,
            ]);
            $response->headers->set('X-Robots-Tag', 'noindex, follow');

            return $response;
        }

        $targetDomain = $this->getUserLocaleTargetDomain($salesChannelContext, $domains, (string) $userLocale, $userCountry);
        if ($targetDomain === null) {
            $this->addLogEntry(
                $request,
                'No target domain found for user locale',
                [
                    'shopLocale' => $shopLocale,
                    'userLocale' => $userLocale,
                ],
                $debugIPs,
            );

            if ($this->pluginConfig->getDefaultLanguage() === '') {
                $this->addLogEntry(
                    $request,
                    'The default language is not specified in the plugin configuration',
                    [
                        'shopLocale' => $shopLocale,
                        'userLocale' => $userLocale,
                    ],
                    $debugIPs,
                );

                $response = new JsonResponse([
                    'success' => false,
                ]);
                $response->headers->set('X-Robots-Tag', 'noindex, follow');

                return $response;
            }
        }

        $targetDomain = $targetDomain ?? $this->getTargetDomainForDefaultLanguage($salesChannelContext);
        if ($targetDomain === null) {
            $this->addLogEntry(
                $request,
                'No domain found for default language',
                [
                    'shopLocale' => $shopLocale,
                    'userLocale' => $userLocale,
                ],
                $debugIPs,
            );

            $response = new JsonResponse([
                'success' => false,
            ]);
            $response->headers->set('X-Robots-Tag', 'noindex, follow');

            return $response;
        }

        $targetLocale = $this->getTargetLocale($targetDomain);
        if ($targetLocale !== null && $targetLocale->getCode() === $shopLocale) {
            $this->addLogEntry(
                $request,
                'No redirect needed',
                [
                    'shopLocale' => $shopLocale,
                    'userLocale' => $userLocale,
                    'targetLocale' => $targetLocale->getCode(),
                ],
                $debugIPs,
            );

            $response = new JsonResponse([
                'redirectNotRequired' => true,
                'setCookie' => !\in_array(
                    $request->getClientIp(),
                    explode(',', $this->pluginConfig->getNoCookieIps()),
                    true,
                ),
            ]);
            $response->headers->set('X-Robots-Tag', 'noindex, follow');

            return $response;
        }

        /** @var array<string, mixed>|null $data */
        $data = $this->getDomainsLanguages($request, $salesChannelContext, $domains, $targetDomain);
        if ($data === null) {
            $this->addLogEntry(
                $request,
                'No translations were found for target domain',
                [
                    'shopLocale' => $shopLocale,
                    'userLocale' => $userLocale,
                ],
                $debugIPs,
            );

            $response = new JsonResponse([
                'success' => false,
            ]);
            $response->headers->set('X-Robots-Tag', 'noindex, follow');

            return $response;
        }

        $response = new JsonResponse(
            [
                'success' => true,
                'data' => $data,
                'setCookie' => !\in_array(
                    $request->getClientIp(),
                    explode(',', $this->pluginConfig->getNoCookieIps()),
                    true,
                ),
                'html' => $this->renderStorefront(
                    '@Storefront/storefront/component/neti-language-detector/pseudo-modal.html.twig',
                    $data,
                )->getContent(),
            ],
        );
        $response->headers->set('X-Robots-Tag', 'noindex, follow');

        return $response;
    }

    private function getSalesChannelDomains(SalesChannelContext $salesChannelContext): ?array
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $context = $salesChannelContext->getContext();
        $domains = $this->languageDetectorService->getSalesChannelsDomains($salesChannelId, $context)->getElements();
        if ($this->pluginConfig->isAllSalesChannels()) {
            $notSalesChannelsDomains = $this->languageDetectorService->getOtherSalesChannelsDomains($domains, $salesChannelId, $context);
            /** @var SalesChannelDomainEntity $notSalesChannelsDomain */
            foreach ($notSalesChannelsDomains as $notSalesChannelsDomain) {
                $domains[$notSalesChannelsDomain->getId()] = $notSalesChannelsDomain;
            }
        }

        return $domains !== [] ? $domains : null;
    }

    private function getUserLocaleTargetDomain(SalesChannelContext $salesChannelContext, array $domains, string $userLocale, string $userCountry): ?SalesChannelDomainEntity
    {
        $targetDomain = null;

        /** @var SalesChannelDomainEntity $domain */
        foreach ($domains as $domain) {
            $domainLanguage = $domain->getLanguage();

            if (!$domainLanguage instanceof LanguageEntity) {
                continue;
            }

            $domainLocale = $domainLanguage->getLocale();
            if (!$domainLocale instanceof LocaleEntity) {
                continue;
            }

            $domainLocaleCode = $domainLocale->getCode();
            if (\str_contains($domainLocaleCode, $userLocale)) {
                if ($domain->getSalesChannel()?->getCountry()?->getIso() !== $userCountry) {
                    continue;
                }

                if ($targetDomain === null) {
                    $targetDomain = $domain;

                    continue;
                }

                /** @var SalesChannelDomainPriorityEntity|null $targetPriority */
                $targetPriority = $targetDomain->getExtension(SalesChannelDomainExtension::SALES_CHANNEL_DOMAIN_EXTENSION_NAME);

                /** @var SalesChannelDomainPriorityEntity|null $currentPriority */
                $currentPriority = $domain->getExtension(SalesChannelDomainExtension::SALES_CHANNEL_DOMAIN_EXTENSION_NAME);

                if ($currentPriority !== null && $targetPriority !== null && $currentPriority->getPriority() >= $targetPriority->getPriority()) {
                    $targetDomain = $domain;
                }
            }
        }

        return $targetDomain;
    }

    private function getTargetDomainForDefaultLanguage(SalesChannelContext $salesChannelContext): ?SalesChannelDomainEntity
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $context = $salesChannelContext->getContext();

        // we want to search for the default language in the active salesChannel first
        $targetDomain = $this->languageDetectorService->getDefaultTargetDomain($this->pluginConfig->getDefaultLanguage(), $context, $salesChannelId);
        if ($targetDomain === null && $this->pluginConfig->isAllSalesChannels()) {
            $targetDomain = $this->languageDetectorService->getDefaultTargetDomain($this->pluginConfig->getDefaultLanguage(), $context);
        }

        return $targetDomain;
    }

    private function getTargetLocale(SalesChannelDomainEntity $targetDomain): ?LocaleEntity
    {
        $targetLanguage = $targetDomain->getLanguage();
        if (!$targetLanguage instanceof LanguageEntity) {
            return null;
        }

        return $targetLanguage->getLocale();
    }

    private function getDomainsLanguages(Request $request, SalesChannelContext $salesChannelContext, array $domains, SalesChannelDomainEntity $targetDomain): ?array
    {
        $context = $salesChannelContext->getContext();
        /** @var LanguageEntity $targetLanguage */
        $targetLanguage = $targetDomain->getLanguage();
        $netiLanguageDetector = ['targetLanguage' => $targetDomain];

        /** @var SalesChannelDomainEntity $domain */
        foreach ($domains as $domain) {
            $domainLanguage = $domain->getLanguage();
            if (!$domainLanguage instanceof LanguageEntity) {
                continue;
            }

            $domainLocale = $domainLanguage->getLocale();
            if (!$domainLocale instanceof LocaleEntity) {
                continue;
            }

            $foundLanguage = $this->languageDetectorService->getLanguageByLocale(
                $domainLocale->getCode(),
                $context,
            );
            $foundCountry = $domain->getSalesChannel()?->getCountry();
            \assert($foundCountry instanceof CountryEntity);

            if (!$foundLanguage instanceof LanguageEntity) {
                continue;
            }

            $foundLanguageId = $foundLanguage->getId();
            $foundCountryId = $domain->getSalesChannel()?->getCountry()?->getId();
            $locale = $foundLanguage->getLocale();
            if (!$locale instanceof LocaleEntity) {
                continue;
            }

            $localeCode = $locale->getCode();
            $this->translator->injectSettings($domain->getSalesChannelId(), $foundLanguageId, $localeCode, $context);
            /** @var SalesChannelDomainPriorityEntity|null $domainPriorityEntity */
            $domainPriorityEntity = $domain->getExtension(SalesChannelDomainExtension::SALES_CHANNEL_DOMAIN_EXTENSION_NAME);
            $domainPriority = $domainPriorityEntity?->getPriority() ?? 0;

            if (!isset($netiLanguageDetector['languages'])) {
                $netiLanguageDetector['languages'] = [];
            }

            if (isset($netiLanguageDetector['languages'][$foundCountryId]['priority'])
                && $netiLanguageDetector['languages'][$foundCountryId]['priority'] >= $domainPriority
            ) {
                continue;
            }

            $route = (string) $request->query->get('redirectRoute');
            if ($route === '' || $route === '/') {
                $route = 'index';
            }

            $netiLanguageDetector['languages'][$foundCountryId] = [
                'id' => $foundCountryId,
                'route' => $route,
                'domain' => $domain->getId(),
                'headline' => $this->translator->trans('neti-next-language-detector.modal.headline'),
                'text' => str_replace(
                    '%LANG%',
                    $domainLanguage->getName(),
                    $this->translator->trans('neti-next-language-detector.modal.text'),
                ),
                'buttonAccept' => $this->translator->trans('neti-next-language-detector.modal.buttonAccept'),
                'buttonDecline' => $this->translator->trans('neti-next-language-detector.modal.buttonDecline'),
                'name' => $foundCountry->getTranslated()['name'],
                'locale' => $localeCode,
                'language' => $foundLanguage,
                'country' => $foundCountry,
                'priority' => $domainPriority,
            ];
        }

        if (!isset($netiLanguageDetector['languages'][$targetDomain->getSalesChannel()?->getCountry()?->getId()])) {
            return null;
        }

        return $netiLanguageDetector;
    }

    private function getValidHeaderAcceptLanguage(Request $request): ?string
    {
        $acceptLanguage = $request->headers->get('Accept-Language');

        return \is_string($acceptLanguage) ? $acceptLanguage : null;
    }

    private function getUserLocaleLanguage(string $acceptLanguage): ?string
    {
        /** @var empty|string[] $userLocales */
        /** @phpstan-ignore-next-line */
        $userLocales = explode(',', $acceptLanguage);

        return $userLocales !== [] && isset($userLocales[0]) ? $userLocales[0] : null;
    }

    private function getCurrentLocalEntity(SalesChannelContext $salesChannelContext): ?LocaleEntity
    {
        $languageId = $salesChannelContext->getLanguageId();
        $context = $salesChannelContext->getContext();
        $currentLanguage = $this->languageDetectorService->getLanguageById($languageId, $context);
        if (!$currentLanguage instanceof LanguageEntity) {
            return null;
        }

        $currentLocale = $currentLanguage->getLocale();
        if (!$currentLocale instanceof LocaleEntity) {
            return null;
        }

        return $currentLocale;
    }

    private function addLogEntry(Request $request, string $message, array $parameters = [], array $debugIPs = []): void
    {
        $clientIP = $request->getClientIp();
        if (\in_array($clientIP, $debugIPs, true)) {
            $this->logger->debug(
                $message,
                \array_merge([
                    'IP' => $clientIP,
                    'class' => __CLASS__,
                ], $parameters),
            );
        }
    }

    /**
     * @throws IpLocationApiError
     */
    private function getLocationByIp(?string $userIp): string
    {
        $client = new Client();
        $apiUrl = 'https://api.iplocation.net/';
        $response = null;

        try {
            if (!\filter_var($userIp, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE)) {
                throw (new IpLocationApiError('Invalid or private IP address'))->setIpAddress($userIp);
            }

            $response = $client->request('GET', $apiUrl, [
                'query' => [
                    'cmd' => 'ip-country',
                    'ip' => $userIp,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw (new IpLocationApiError('Response invalid'))
                    ->setIpAddress($userIp)
                    ->setResponse($response);
            }

            /** @var array $locationData */
            $locationData = json_decode($response->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);

            if (
                !isset($locationData['country_code2'])
                || !\is_string($locationData['country_code2'])
                || $locationData['country_code2'] === ''
                || $locationData['country_code2'] === '-'
            ) {
                throw (new IpLocationApiError('Country could not be identified'))
                    ->setIpAddress($userIp)
                    ->setResponse($response);
            }

            return $locationData['country_code2'];
        } catch (\Throwable $e) {
            throw (new IpLocationApiError($e->getMessage()))
                ->setIpAddress($userIp)
                ->setResponse($response);
        }
    }
}
