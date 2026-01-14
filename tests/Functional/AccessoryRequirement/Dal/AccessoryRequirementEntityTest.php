<?php declare(strict_types=1);

namespace PhallosanCustomizations\Tests\Functional\AccessoryRequirement\Dal;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\AccessoryRequirementEntity;
use PhallosanCustomizations\Tests\Trait\AccessoryRequirementTrait;
use PhallosanCustomizations\Tests\Trait\ProductTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

class AccessoryRequirementEntityTest extends TestCase
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

    public function testAccessoryRequirementRequiredFields(): void
    {
        try {
            $this->accessoryRequirementUpsert([
                'id' => $this->ids->get('accessory-requirement-1'),
            ]);
        } catch (\Exception $e) {
            static::assertInstanceOf(WriteException::class, $e);

            static::assertStringContainsString(
                'There are 3 error(s) while writing data.',
                $e->getMessage()
            );
            static::assertStringContainsString(
                '[/0/productId] This value should not be blank.',
                $e->getMessage()
            );
            static::assertStringContainsString(
                '[/0/name] This value should not be blank.',
                $e->getMessage()
            );
            static::assertStringContainsString(
                '[/0/requiredProductId] This value should not be blank.',
                $e->getMessage()
            );
        }
    }

    public function testAccessoryRequirementCreation(): void
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

        $criteria = new Criteria([$this->ids->get('accessory-requirement-1')]);
        $criteria->addAssociation('product');
        $criteria->addAssociation('requiredProduct');

        $accessoryRequirementEntity = $this->accessoryRequirementSearch($criteria)->first();

        static::assertInstanceOf(AccessoryRequirementEntity::class, $accessoryRequirementEntity);

        static::assertEquals('Test Accessories', $accessoryRequirementEntity->getName());
        static::assertEquals('Lorem ipsum', $accessoryRequirementEntity->getDescription());
        static::assertEquals($productData['id'], $accessoryRequirementEntity->getProductId());

        static::assertInstanceOf(ProductEntity::class, $accessoryRequirementEntity->getProduct());
        static::assertInstanceOf(ProductEntity::class, $accessoryRequirementEntity->getRequiredProduct());
    }

    public function testAccessoryRequirementProductUniq(): void
    {
        $productData = $this->createTestProduct();

        $accessoryData = [
            'id' => $this->ids->get('accessory-requirement-1'),
            'name' => 'Test Accessories',
            'description' => 'Lorem ipsum',
            'productId' => $productData['id'],
            'requiredProductId' => $this->ids->get('product-1'),
        ];

        $this->accessoryRequirementUpsert($accessoryData);

        $criteria = new Criteria([$this->ids->get('accessory-requirement-1')]);
        $criteria->addAssociation('product');

        $accessoryRequirementEntity = $this->accessoryRequirementSearch($criteria)->first();

        static::assertInstanceOf(AccessoryRequirementEntity::class, $accessoryRequirementEntity);

        static::assertEquals('Test Accessories', $accessoryRequirementEntity->getName());
        static::assertEquals('Lorem ipsum', $accessoryRequirementEntity->getDescription());
        static::assertEquals($productData['id'], $accessoryRequirementEntity->getProductId());

        static::assertInstanceOf(ProductEntity::class, $accessoryRequirementEntity->getProduct());

        static::expectException(UniqueConstraintViolationException::class);

        $this->accessoryRequirementUpsert(
            array_merge($accessoryData, [
                'id' => $this->ids->get('accessory-requirement-2'),
            ])
        );
    }
}
