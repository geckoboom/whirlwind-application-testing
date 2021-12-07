<?php

declare(strict_types=1);

namespace WhirlwindApplicationTesting\Traits;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use WhirlwindApplicationTesting\Fixture\Exception\InvalidConfigException;
use WhirlwindApplicationTesting\Fixture\Fixture;

trait InteractWithFixtures
{
    private ?array $fixtures = null;
    protected ContainerInterface $container;

    /**
     * @return array
     */
    abstract public function fixtures(): array;

    /**
     * @return void
     * @throws InvalidConfigException
     */
    public function initFixtures(): void
    {
        $this->unloadFixtures();
        $this->loadFixtures();
    }

    /**
     * @return void
     * @throws InvalidConfigException
     */
    public function loadFixtures(): void
    {
        $fixtures = $this->getFixtures();

        foreach ($fixtures as $fixture) {
            $fixture->beforeLoad();
        }

        foreach ($fixtures as $fixture) {
            $fixture->load();
        }
        foreach ($fixtures as $fixture) {
            $fixture->afterLoad();
        }
    }

    /**
     * @return Fixture[]
     * @throws InvalidConfigException
     */
    public function getFixtures(): array
    {
        if (null === $this->fixtures) {
            $this->fixtures = $this->createFixtures($this->fixtures());
        }

        return $this->fixtures;
    }

    protected function createFixtures(array $fixtures): array
    {
        $config = [];
        $aliases = [];

        foreach ($fixtures as $name => $fixture) {
            if (!\is_array($fixture)) {
                $class = \ltrim($fixture, '\\');
                $fixtures[$name] = ['class' => $class];
                $aliases[$class] = \is_int($name) ? $class : $name;
            } elseif (isset($fixture['class'])) {
                $class = \ltrim($fixture['class'], '\\');
                $config[$class] = $fixture;
                $aliases[$class] = $name;
            } else {
                throw new InvalidConfigException(
                    \sprintf('You must specify \'class\' for the fixture \'%s\'.', $name)
                );
            }
        }

        $instances = [];
        $stack = \array_reverse($fixtures);

        while (($fixture = \array_pop($stack)) !== null) {
            if ($fixture instanceof Fixture) {
                $class = \get_class($fixture);
                $name = $aliases[$class] ?? $class;
                unset($instances[$name]);  // unset so that the fixture is added to the last in the next line
                $instances[$name] = $fixture;
            } else {
                $class = \ltrim($fixture['class'], '\\');
                $name = $aliases[$class] ?? $class;
                if (!isset($instances[$name])) {
                    $instances[$name] = false;

                    $stack[] = $fixture = $this->instantiateFixture($fixture);
                    foreach ($fixture->getDepends() as $dep) {
                        // need to use the configuration provided in test case
                        $stack[] = $config[$dep] ?? ['class' => $dep];
                    }
                } elseif ($instances[$name] === false) {
                    throw new InvalidConfigException("A circular dependency is detected for fixture '$class'.");
                }
            }
        }

        return $instances;
    }

    /**
     * @param array $config
     * @return Fixture
     * @throws InvalidConfigException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function instantiateFixture(array $config): Fixture
    {
        $fixture = $this->container->get($config['class']);
        unset($config['class']);

        foreach ($config as $property => $value) {
            $method = 'set' . \ucfirst($property);
            if (!\method_exists($fixture, $method)) {
                throw new InvalidConfigException("Cannot set '$property' property");
            }
            $fixture->$method($value);
        }

        return $fixture;
    }

    /**
     * @return void
     * @throws InvalidConfigException
     */
    public function unloadFixtures(): void
    {
        $fixtures = $this->getFixtures();
        foreach ($fixtures as $fixture) {
            $fixture->beforeUnload();
        }

        foreach ($fixtures as $fixture) {
            $fixture->unload();
        }

        foreach ($fixtures as $fixture) {
            $fixture->afterUnload();
        }
    }

    /**
     * @param string $name
     * @return Fixture
     * @throws InvalidConfigException
     */
    public function grabFixture(string $name): Fixture
    {
        return $this->getFixtures()[$name];
    }
}
