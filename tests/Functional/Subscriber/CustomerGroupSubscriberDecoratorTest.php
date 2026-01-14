<?php declare(strict_types=1);

namespace PhallosanCustomizations\Tests\Functional\Subscriber;

use PhallosanCustomizations\PhallosanConstants;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

class CustomerGroupSubscriberDecoratorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private IdsCollection $ids;

    private EntityRepository $customerGroupRepository;

    private EntityRepository $seoUrlRepository;

    public function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->customerGroupRepository = $this->getContainer()->get('customer_group.repository');
        $this->seoUrlRepository = $this->getContainer()->get('seo_url.repository');
    }

    public function testCustomerGroupSubscriber(): void
    {
        $salesChannel = $this->createSalesChannel();

        $customSeoUrlPath = 'my-test/seo-url';
        $customerGroupData = [
            'id' => $this->ids->get('customer-group-1'),
            'name' => 'Customer Group 1',
            'registrationActive' => true,
            'registrationTitle' => 'Registration Title',
            'registrationSalesChannels' => [['id' => $salesChannel['id']]],
            'customFields' => [
                PhallosanConstants::CUSTOM_FIELD_CUSTOMER_GROUP_SEO_URL => $customSeoUrlPath,
            ],
        ];

        $this->customerGroupRepository->upsert([$customerGroupData], Context::createDefaultContext());

        $criteria = new Criteria([$this->ids->get('customer-group-1')]);
        $criteria->addAssociation('registrationSalesChannels');

        $customerGroupEntity = $this->customerGroupRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(CustomerGroupEntity::class, $customerGroupEntity);
        static::assertEquals('Customer Group 1', $customerGroupEntity->getName());


        $seoUrlCriteria = new Criteria();
        $seoUrlCriteria->addFilter(new EqualsFilter('salesChannelId', $salesChannel['id']))
            ->addFilter(new EqualsFilter('pathInfo', '/customer-group-registration/' . $customerGroupEntity->getId()));

        /** @var SeoUrlEntity $seoUrlEntity */
        $seoUrlEntity = $this->seoUrlRepository->search($seoUrlCriteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(SeoUrlEntity::class, $seoUrlEntity);
        static::assertEquals($customSeoUrlPath, $seoUrlEntity->getSeoPathInfo());
    }
}
