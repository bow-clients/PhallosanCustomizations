<?php declare(strict_types=1);

namespace PhallosanCustomizations\AccessoryRequirement\Checkout\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;

class AccessoryRequirementNotFulfilled extends Error
{
    final public const KEY = 'accessory-required-product-missing';

    public function __construct(
        private readonly string $id,
        private readonly ?string $messageKey,
    ) {
        parent::__construct();

        $this->message = \sprintf('The Accessory required product is not in the cart!');
    }

    public static function getSnippetName(string $accessoryRequirementId): string
    {
        return self::KEY . '.' . $accessoryRequirementId;
    }

    public function getId(): string
    {
        return \sprintf('%s-%s', self::KEY, $this->id);
    }

    public function getMessageKey(): string
    {
        return $this->messageKey ?? self::KEY;
    }

    public function getLevel(): int
    {
        return self::LEVEL_WARNING;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function getParameters(): array
    {
        return ['id' => $this->id];
    }
}
