<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Framework\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class HrefPrefixer extends AbstractExtension
{
    public function __construct(
        private RequestStack $requestStack
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('prefixInternalLinks', [$this, 'prefixInternalLinks'], ['is_safe' => ['html']]),
        ];
    }

    public function prefixInternalLinks(string $html): string
    {
        $request = $this->requestStack->getCurrentRequest();
    
        if (!$request) {
            return $html;
        }

        $basePath = $request->attributes->get('sw-sales-channel-base-url') ?? '';

        return preg_replace_callback(
            '/<a\b[^>]*\bhref="([^"]+)"[^>]*>/i',
            function ($matches) use ($basePath) {
                $href = $matches[1];

                if (str_starts_with($href, '*')) {
                    $newHref = substr($href, 1);

                    return str_replace($href, $newHref, $matches[0]);
                }
        
                if (preg_match('#^(https?:)?//#', $href)) {
                    return $matches[0];
                }

                if (strpos($href, '/') !== 0) {
                    return $matches[0];
                }

                if (preg_match('#^/media/#', $href)) {
                    return $matches[0];
                }
        
                $newHref = rtrim($basePath, '/') . '/' . ltrim($href, '/');

                return str_replace($href, $newHref, $matches[0]);
            },
            $html
        ) ?? $html;
    }
}
