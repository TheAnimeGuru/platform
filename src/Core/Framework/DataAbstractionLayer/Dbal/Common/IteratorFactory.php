<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-final - Will be final
 * @final
 */
class IteratorFactory
{
    private Connection $connection;

    private DefinitionInstanceRegistry $registry;

    /**
     * @internal
     */
    public function __construct(Connection $connection, DefinitionInstanceRegistry $registry)
    {
        $this->connection = $connection;
        $this->registry = $registry;
    }

    public function createIterator($definition, ?array $lastId = null, int $limit = 50): IterableQuery
    {
        if (\is_string($definition)) {
            $definition = $this->registry->getByEntityName($definition);
        }

        if (!$definition instanceof EntityDefinition) {
            throw new \InvalidArgumentException('Definition must be an instance of EntityDefinition');
        }

        $entity = $definition->getEntityName();

        $escaped = EntityDefinitionQueryHelper::escape($entity);
        $query = $this->connection->createQueryBuilder();
        $query->from($escaped);
        $query->setMaxResults($limit);

        if ($definition->hasAutoIncrement()) {
            $query->select([$escaped . '.auto_increment', 'LOWER(HEX(' . $escaped . '.id)) as id']);
            $query->andWhere($escaped . '.auto_increment > :lastId');
            $query->addOrderBy($escaped . '.auto_increment');
            $query->setParameter('lastId', 0);

            if ($lastId !== null) {
                $query->setParameter('lastId', $lastId['offset']);
            }

            return new LastIdQuery($query);
        }

        $query->select([$escaped . '.id', 'LOWER(HEX(' . $escaped . '.id))']);
        $query->setFirstResult(0);
        if ($lastId !== null) {
            $query->setFirstResult((int) $lastId['offset']);
        }

        return new OffsetQuery($query);
    }
}
