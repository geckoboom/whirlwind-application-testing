<?php

declare(strict_types=1);

namespace WhirlwindApplicationTesting\Traits;

use League\Container\Definition\DefinitionInterface;
use League\Container\DefinitionContainerInterface;
use Mockery\MockInterface;
use Psr\Container\ContainerInterface;

trait InteractWithContainer
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @param string $id
     * @param $concrete
     * @return mixed
     */
    public function bindInstance(string $id, $concrete)
    {
        return $this->bindDefinition($id, $concrete)->getConcrete();
    }

    /**
     * @param string $id
     * @param $concrete
     * @return DefinitionInterface
     */
    private function bindDefinition(string $id, $concrete): DefinitionInterface
    {
        if ($this->container instanceof DefinitionContainerInterface) {
            if ($this->container->has($id)) {
                return $this->container->extend($id)->setConcrete($concrete);
            } else {
                return $this->container->add($id, $concrete);
            }
        }

        throw new \LogicException(
            "Bind definition is only accepted for " . DefinitionContainerInterface::class . ' container'
        );
    }

    /**
     * @param string $id
     * @param $concrete
     * @return mixed
     */
    public function bindSingleton(string $id, $concrete)
    {
        return $this->bindDefinition($id, $concrete)->setShared(true)->getConcrete();
    }

    /**
     * @param string $id
     * @param \Closure|null $mock
     * @return MockInterface
     */
    public function mock(string $id, ?\Closure $mock = null): MockInterface
    {
        return $this->bindInstance($id, \Mockery::mock(...\array_filter(\func_get_args())));
    }

    /**
     * @param string $id
     * @param \Closure|null $mock
     * @return MockInterface
     */
    public function partialMock(string $id, ?\Closure $mock = null): MockInterface
    {
        return $this->bindInstance($id, \Mockery::mock(...\array_filter(\func_get_args()))->makePartial());
    }

    /**
     * @param string $id
     * @param \Closure|null $mock
     * @return MockInterface
     */
    public function spy(string $id, ?\Closure $mock = null): MockInterface
    {
        return $this->bindInstance($id, \Mockery::spy(...\array_filter(\func_get_args())));
    }
}
