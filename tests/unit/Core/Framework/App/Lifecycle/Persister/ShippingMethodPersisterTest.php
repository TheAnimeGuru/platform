<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\App\Aggregate\AppShippingMethod\AppShippingMethodDefinition;
use Shopware\Core\Framework\App\Aggregate\AppShippingMethod\AppShippingMethodEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Lifecycle\Persister\ShippingMethodPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\DeliveryTime\DeliveryTimeCollection;
use Shopware\Core\System\DeliveryTime\DeliveryTimeDefinition;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Lifecycle\Persister\ShippingMethodPersister
 */
class ShippingMethodPersisterTest extends TestCase
{
    private const ICON_URL = __DIR__ . '/Icons/TestIcon.png';

    private const APP_ID = '2b0e78aa591e11ee8c990242ac120002';

    private const DEFAULT_LOCALE_ID = '350202b740dd451db69e4bdcb76cd3b4';

    public function testUpdateShippingMethodInstallsTwoNewShippingMethodsWithBasicManifest(): void
    {
        $manifest = $this->getManifest(__DIR__ . '/_fixtures/manifest_basic.xml');
        $context = Context::createDefaultContext();

        $shippingMethodPersister = $this->createShippingMethodPersister();

        $shippingMethodPersister->updateShippingMethods($manifest, self::APP_ID, self::DEFAULT_LOCALE_ID, $context);
    }

    public function testUpdateShippingMethodInstallsOneNewUpdateOneAndDeactivatesOneShippingMethodsWithUpdateManifest(): void
    {
        $manifest = $this->getManifest(__DIR__ . '/_fixtures/update_basic.xml');
        $context = Context::createDefaultContext();

        $appShippingMethodRepositoryMock = $this->createAppShippingMethodRepositoryMockWithExistingAppShippingMethods();

        $shippingMethodRepositoryMock = $this->createMock(EntityRepository::class);
        $shippingMethodRepositoryMock->expects(static::once())->method('upsert');
        $shippingMethodRepositoryMock->expects(static::once())->method('update');

        $shippingMethodPersister = $this->createShippingMethodPersister([
            'shippingMethodRepository' => $shippingMethodRepositoryMock,
            'appShippingMethodRepository' => $appShippingMethodRepositoryMock,
            'appLoader' => $this->createMock(AppLoader::class),
            'mediaService' => $this->createMock(MediaService::class),
        ]);

        $shippingMethodPersister->updateShippingMethods($manifest, self::APP_ID, self::DEFAULT_LOCALE_ID, $context);
    }

    /**
     * @param array<string, mixed> $services
     */
    private function createShippingMethodPersister(array $services = []): ShippingMethodPersister
    {
        $deliveryTime = new DeliveryTimeEntity();
        $deliveryTime->setId('ca565fa321ad4c87a2669161907fc4c8');

        $rule = new RuleEntity();
        $rule->setId('d4bdfbb82f624c7482b4c16599d31a30');

        return new ShippingMethodPersister(
            \array_key_exists('shippingMethodRepository', $services) ? $services['shippingMethodRepository'] : $this->createShippingMethodRepositoryMock(),
            \array_key_exists('appShippingMethodRepository', $services) ? $services['appShippingMethodRepository'] : $this->createAppShippingMethodRepositoryMock(),
            \array_key_exists('ruleRepository', $services) ? $services['ruleRepository'] : $this->createRuleRepositoryMock([$rule]),
            \array_key_exists('deliveryTimeRepository', $services) ? $services['deliveryTimeRepository'] : $this->createDeliveryTimeRepositoryMock([$deliveryTime]),
            \array_key_exists('mediaRepository', $services) ? $services['mediaRepository'] : $this->createMediaRepositoryMock(),
            \array_key_exists('mediaService', $services) ? $services['mediaService'] : $this->createMediaServiceMock(),
            \array_key_exists('appLoader', $services) ? $services['appLoader'] : $this->createAppLoaderMock(),
        );
    }

    /**
     * @param array<ShippingMethodEntity> $entities
     */
    private function createShippingMethodRepositoryMock(array $entities = []): EntityRepository
    {
        if (!empty($entities)) {
            return new StaticEntityRepository(
                [
                    new ShippingMethodCollection($entities),
                ],
                new ShippingMethodDefinition()
            );
        }

        return $this->createMock(EntityRepository::class);
    }

    /**
     * @param array<AppShippingMethodEntity> $entities
     */
    private function createAppShippingMethodRepositoryMock(array $entities = []): EntityRepository
    {
        if (!empty($entities)) {
            return new StaticEntityRepository(
                [
                    new EntityCollection($entities),
                ],
                new AppShippingMethodDefinition()
            );
        }

        $appShippingMethodMock = $this->createMock(EntityRepository::class);
        $appShippingMethodMock->method('search')->willReturn(
            new EntitySearchResult(
                AppShippingMethodEntity::class,
                0,
                new EntityCollection(),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        return $appShippingMethodMock;
    }

    /**
     * @param array<RuleEntity> $entities
     */
    private function createRuleRepositoryMock(array $entities = []): EntityRepository
    {
        return new StaticEntityRepository(
            [
                new RuleCollection($entities),
                new RuleCollection($entities),
            ],
            new RuleDefinition()
        );
    }

    /**
     * @param array<DeliveryTimeEntity> $entities
     */
    private function createDeliveryTimeRepositoryMock(array $entities = []): EntityRepository
    {
        return new StaticEntityRepository(
            [
                new DeliveryTimeCollection($entities),
                new DeliveryTimeCollection($entities),
            ],
            new DeliveryTimeDefinition()
        );
    }

    /**
     * @param array<MediaEntity> $entities
     */
    private function createMediaRepositoryMock(array $entities = []): EntityRepository
    {
        if (!empty($entities)) {
            return new StaticEntityRepository(
                [
                    new MediaCollection($entities),
                ],
                new MediaDefinition()
            );
        }

        $mediaRepositoryMock = $this->createMock(EntityRepository::class);
        $mediaRepositoryMock->method('searchIds')->willReturn(
            new IdSearchResult(
                0,
                [],
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        return $mediaRepositoryMock;
    }

    private function createMediaServiceMock(): MediaService&MockObject
    {
        $mediaServiceMock = $this->createMock(MediaService::class);
        $mediaServiceMock->expects(static::Once())->method('saveFile')->willReturn(self::ICON_URL);

        return $mediaServiceMock;
    }

    private function createAppLoaderMock(): AppLoader&MockObject
    {
        $appLoaderMock = $this->createMock(AppLoader::class);
        $appLoaderMock->expects(static::Once())->method('loadFile')->willReturn(self::ICON_URL);

        return $appLoaderMock;
    }

    private function getManifest(string $file): Manifest
    {
        static::assertTrue(is_file($file));

        return Manifest::createFromXmlFile($file);
    }

    private function createAppShippingMethodRepositoryMockWithExistingAppShippingMethods(): EntityRepository|MockObject
    {
        $shippingMethodOne = new ShippingMethodEntity();
        $shippingMethodOne->setId('40a65bae126c4d9784da11144e1fc9e3');
        $shippingMethodOne->setUniqueIdentifier('shippingMethodOne');
        $shippingMethodOne->setName('shippingMethodOne');

        $appShippingMethodOne = new AppShippingMethodEntity();
        $appShippingMethodOne->setId('0a0dc6f736b84b068ac98eed17ff9ef4');
        $appShippingMethodOne->setShippingMethod($shippingMethodOne);
        $appShippingMethodOne->setIdentifier('shippingMethodOne');

        $shippingMethodTwo = new ShippingMethodEntity();
        $shippingMethodTwo->setId('16cf9ee9b93e413faa20646258014e71');
        $shippingMethodTwo->setUniqueIdentifier('shippingMethodTwo');
        $shippingMethodTwo->setName('shippingMethodTwo');

        $appShippingMethodTwo = new AppShippingMethodEntity();
        $appShippingMethodTwo->setId('ac131e0ec3f3487fa52bd2fb31cfdf64');
        $appShippingMethodTwo->setShippingMethod($shippingMethodTwo);
        $appShippingMethodTwo->setIdentifier('shippingMethodTwo');

        $entityCollection = new EntityCollection([
            $appShippingMethodOne,
            $appShippingMethodTwo,
        ]);

        $entitySearchResultMock = $this->createMock(EntitySearchResult::class);
        $entitySearchResultMock->expects(static::once())->method('getEntities')->willReturn($entityCollection);

        $appShippingMethodRepositoryMock = $this->createMock(EntityRepository::class);
        $appShippingMethodRepositoryMock->expects(static::once())->method('search')->willReturn($entitySearchResultMock);

        return $appShippingMethodRepositoryMock;
    }
}
