<?php declare(strict_types=1);

namespace PhallosanCustomizations\Framework\Cookie;

use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;

class TrustpilotCookieProvider implements CookieProviderInterface
{
    private const singleCookie = [
        'snippet_name' => 'cookie.trustpilot.name',
        'snippet_description' => 'cookie.trustpilot.description',
        'cookie' => 'trustpilot',
        'value' => '1',
    ];

    public function __construct(
        private readonly CookieProviderInterface $originalService
    ) {
    }

    public function getCookieGroups(): array
    {
        return array_merge(
            $this->originalService->getCookieGroups(),
            [self::singleCookie]
        );
    }
}
