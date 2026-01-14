<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Framework\Twig;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SalutationExtension extends AbstractExtension
{
    private Context $context;

    public function __construct(
        private EntityRepository $salutationRepository
    ) {
        $this->context = Context::createDefaultContext();
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('getSalutations', [$this, 'getSalutations']),
        ];
    }

    public function getSalutations(?Context $context = null): EntitySearchResult
    {
        if ($context === null) {
            $context = $this->context;
        }
        $criteria = new Criteria();
        $criteria->addAssociation('translations');

        return $this->salutationRepository->search($criteria, $context);
    }
}
