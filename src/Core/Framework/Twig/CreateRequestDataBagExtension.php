<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Framework\Twig;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CreateRequestDataBagExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('createRequestDataBag', [$this, 'createRequestDataBag']),
        ];
    }

    public function createRequestDataBag(array $data = []): RequestDataBag
    {
        return new RequestDataBag($data);
    }
}
