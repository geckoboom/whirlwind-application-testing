<?php

declare(strict_types=1);

namespace WhirlwindApplicationTesting\Fixture;

use Whirlwind\Infrastructure\Hydrator\Hydrator;
use Whirlwind\Infrastructure\Repository\TableGateway\TableGatewayInterface;
use WhirlwindApplicationTesting\Fixture\Exception\InvalidConfigException;

class EntityFixture extends Fixture
{
    protected Hydrator $hydrator;

    protected TableGatewayInterface $tableGateway;

    protected string $entityClass;

    public function __construct(Hydrator $hydrator, TableGatewayInterface $tableGateway)
    {
        $this->hydrator = $hydrator;
        $this->tableGateway = $tableGateway;
    }

    public function setEntityClass(string $entityClass): void
    {
        $this->entityClass = $entityClass;
    }

    public function load(): void
    {
        $this->data = [];

        if (!$this->entityClass) {
            throw new InvalidConfigException('Entity class required.');
        }

        foreach ($this->loadData() as $alias => $row) {
            $primaryIds = $this->tableGateway->insert($row);
            if (!empty($primaryIds)) {
                $this->data[$alias] = $this->hydrator->hydrate(
                    $this->entityClass,
                    \array_merge($row, $primaryIds)
                );
            } else {
                $this->data[$alias] = $this->hydrator->hydrate($this->entityClass, $row);
            }
        }
    }

    public function unload(): void
    {
        $this->tableGateway->deleteAll([]);
    }

    public function beforeLoad(): void
    {
    }

    public function afterLoad(): void
    {
    }

    public function beforeUnload(): void
    {
    }

    public function afterUnload(): void
    {
    }
}
