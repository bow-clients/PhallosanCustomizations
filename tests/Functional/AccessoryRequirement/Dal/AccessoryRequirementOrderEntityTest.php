<?php declare(strict_types=1);

namespace PhallosanCustomizations\Tests\Functional\AccessoryRequirement\Dal;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\AccessoryRequirementEntity;
use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirementOrder\AccessoryRequirementOrderEntity;
use PhallosanCustomizations\Tests\Trait\AccessoryRequirementTrait;
use PhallosanCustomizations\Tests\Trait\ProductTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

class AccessoryRequirementOrderEntityTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ProductTrait;
    use AccessoryRequirementTrait;

    private IdsCollection $ids;

    public function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->initProductTrait();
        $this->initAccessoryRequirement();
    }

    public function testAccessoryRequirementOrderRequiredFields(): void
    {
        try {
            $this->accessoryRequirementOrderUpsert([
                'id' => $this->ids->get('accessory-requirement-1'),
            ]);
        } catch (\Exception $e) {
            static::assertInstanceOf(WriteException::class, $e);

            static::assertStringContainsString(
                'There are 2 error(s) while writing data.',
                $e->getMessage()
            );
            static::assertStringContainsString(
                '[/0/accessoryRequirementId] This value should not be blank.',
                $e->getMessage()
            );
            static::assertStringContainsString(
                '[/0/orderNumber] This value should not be blank.',
                $e->getMessage()
            );
        }
    }

    public function testAccessoryRequirementOrderCreation(): void
    {
        $productData = $this->createTestProduct();
        $productData2 = $this->createTestProduct();

        $this->accessoryRequirementUpsert([
            'id' => $this->ids->get('accessory-requirement-1'),
            'name' => 'Test Accessories',
            'description' => 'Lorem ipsum',
            'productId' => $productData['id'],
            'requiredProductId' => $productData2['id'],
        ]);

        $orderNumber = '10000';

        $this->accessoryRequirementOrderUpsert([
            'id' => $this->ids->get('accessory-requirement-order-1'),
            'accessoryRequirementId' => $this->ids->get('accessory-requirement-1'),
            'orderNumber' => $orderNumber,
        ]);

        $criteria = new Criteria([$this->ids->get('accessory-requirement-order-1')]);
        $criteria->addAssociation('accessoryRequirement');
        $criteria->addAssociation('accessoryRequirement.product');
        $criteria->addAssociation('accessoryRequirement.requiredProduct');

        $accessoryRequirementOrderEntity = $this->accessoryRequirementOrderSearch($criteria)->first();

        static::assertInstanceOf(AccessoryRequirementOrderEntity::class, $accessoryRequirementOrderEntity);
        static::assertInstanceOf(AccessoryRequirementEntity::class, $accessoryRequirementOrderEntity->getAccessoryRequirement());

        static::assertEquals('Test Accessories', $accessoryRequirementOrderEntity->getAccessoryRequirement()->getName());
        static::assertEquals($orderNumber, $accessoryRequirementOrderEntity->getOrderNumber());
        static::assertEquals($this->ids->get('accessory-requirement-order-1'), $accessoryRequirementOrderEntity->getId());

        static::assertInstanceOf(ProductEntity::class, $accessoryRequirementOrderEntity->getAccessoryRequirement()->getProduct());
        static::assertInstanceOf(ProductEntity::class, $accessoryRequirementOrderEntity->getAccessoryRequirement()->getRequiredProduct());
    }

    public function testAccessoryRequirementOrderNumberUniq(): void
    {
        $productData = $this->createTestProduct();
        $productData2 = $this->createTestProduct();

        $this->accessoryRequirementUpsert([
            'id' => $this->ids->get('accessory-requirement-1'),
            'name' => 'Test Accessories',
            'description' => 'Lorem ipsum',
            'productId' => $productData['id'],
            'requiredProductId' => $productData2['id'],
        ]);

        $orderNumber = '10000';

        $accessoryOrderData = [
            'id' => $this->ids->get('accessory-requirement-order-1'),
            'accessoryRequirementId' => $this->ids->get('accessory-requirement-1'),
            'orderNumber' => $orderNumber,
        ];

        $this->accessoryRequirementOrderUpsert($accessoryOrderData);

        $criteria = new Criteria([$this->ids->get('accessory-requirement-order-1')]);
        $criteria->addAssociation('accessoryRequirement');
        $criteria->addAssociation('accessoryRequirement.product');
        $criteria->addAssociation('accessoryRequirement.requiredProduct');

        $accessoryRequirementOrderEntity = $this->accessoryRequirementOrderSearch($criteria)->first();

        static::assertInstanceOf(AccessoryRequirementOrderEntity::class, $accessoryRequirementOrderEntity);
        static::assertInstanceOf(AccessoryRequirementEntity::class, $accessoryRequirementOrderEntity->getAccessoryRequirement());

        static::assertEquals('Test Accessories', $accessoryRequirementOrderEntity->getAccessoryRequirement()->getName());
        static::assertEquals($orderNumber, $accessoryRequirementOrderEntity->getOrderNumber());
        static::assertEquals($this->ids->get('accessory-requirement-order-1'), $accessoryRequirementOrderEntity->getId());

        static::assertInstanceOf(ProductEntity::class, $accessoryRequirementOrderEntity->getAccessoryRequirement()->getProduct());
        static::assertInstanceOf(ProductEntity::class, $accessoryRequirementOrderEntity->getAccessoryRequirement()->getRequiredProduct());

        static::expectException(UniqueConstraintViolationException::class);

        $this->accessoryRequirementOrderUpsert(
            array_merge($accessoryOrderData, [
                'id' => $this->ids->get('accessory-requirement-order-2'),
            ])
        );
    }
}
