<?php

declare(strict_types=1);

namespace WhirlwindApplicationTesting\Fixture;

use WhirlwindApplicationTesting\Fixture\Exception\InvalidConfigException;

abstract class Fixture implements FixtureInterface, \IteratorAggregate, \ArrayAccess, \Countable
{
    protected array $data = [];

    protected array $depends = [];

    protected string $dataFile;

    /**
     * @param FixtureInterface[] $depends
     */
    public function setDepends(array $depends): void
    {
        $this->depends = $depends;
    }

    /**
     * @return array
     */
    public function getDepends(): array
    {
        return $this->depends;
    }

    /**
     * @param string $dataFile
     */
    public function setDataFile(string $dataFile): void
    {
        $this->dataFile = $dataFile;
    }

    /**
     * @return \Iterator
     */
    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return \count($this->data);
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed|object|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->data[$offset] ?: null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        if (null === $offset) {
            $this->data[] = $value;
        }

        $this->data[$offset] = $value;
    }

    /**
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * @return void
     * @throws InvalidConfigException
     */
    public function load(): void
    {
        $this->data = $this->loadData();
    }

    /**
     * @return array
     * @throws InvalidConfigException
     */
    protected function loadData(): array
    {
        if (!$this->dataFile) {
            return [];
        }

        $dataFile = $this->resolveDataFilePath();
        if (\is_file($dataFile)) {
            return require($dataFile);
        } else {
            throw new InvalidConfigException("Fixture file does not exist: {$this->dataFile}");
        }
    }

    protected function resolveDataFilePath(): string
    {
        return \sprintf(
            '%1$s%2$sdata%2$s%3$s',
            \dirname((new \ReflectionClass($this))->getFileName()),
            DIRECTORY_SEPARATOR,
            $this->dataFile
        );
    }
}
