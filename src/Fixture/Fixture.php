<?php

declare(strict_types=1);

namespace WhirlwindApplicationTesting\Fixture;

use WhirlwindApplicationTesting\Fixture\Exception\InvalidConfigException;

abstract class Fixture implements FixtureInterface, \IteratorAggregate, \ArrayAccess, \Countable
{
    /**
     * @var array
     */
    protected $data = [];
    /**
     * @var array
     */
    protected $depends = [];
    /**
     * @var string
     */
    protected $dataFile;

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

        if (\is_file($this->dataFile)) {
            return require($this->dataFile);
        } else {
            throw new InvalidConfigException("Fixture file does not exist: {$this->dataFile}");
        }
    }
}
