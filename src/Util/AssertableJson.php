<?php

declare(strict_types=1);

namespace WhirlwindApplicationTesting\Util;

use Flow\JSONPath\JSONPath;
use Flow\JSONPath\JSONPathException;
use JsonSchema\Validator;
use PHPUnit\Framework\Assert;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertTrue;

class AssertableJson
{
    /**
     * @var string|\JsonSerializable|array
     */
    protected $json;
    /**
     * @var array|mixed|null
     */
    protected ?array $jsonArray = [];

    /**
     * @param $jsonable
     */
    public function __construct($jsonable)
    {
        $this->json = $jsonable;

        if ($jsonable instanceof \JsonSerializable) {
            $this->jsonArray = $jsonable->jsonSerialize();
        } elseif (\is_array($jsonable)) {
            $this->jsonArray = $jsonable;
        } else {
            $this->jsonArray = \json_decode($jsonable, true);
        }
    }

    /**
     * @param string|null $path
     * @return array|mixed
     * @throws JSONPathException
     */
    public function getJson(?string $path = null)
    {
        if (null === $path) {
            return $this->jsonArray;
        }

        return (new JSONPath($this->jsonArray))->find($path)->getData();
    }

    /**
     * @param int $count
     * @param string|null $path
     * @return $this
     * @throws JSONPathException
     */
    public function assertCount(int $count, ?string $path = null): self
    {
        assertCount(
            $count,
            $this->getJson($path),
            "Failed to assert that the response count matched the expected {$count}"
        );

        return $this;
    }

    /**
     * @param array $json
     * @return $this
     */
    public function assertContainsExactJson(array $json): self
    {
        $actual = $this->reorderAssocKeys((array) $this->jsonArray);
        $expected = $this->reorderAssocKeys($json);

        assertEquals($expected, $actual);

        return $this;
    }

    /**
     * @param array $data
     * @return array
     */
    protected function reorderAssocKeys(array $data): array
    {
        $data = $this->transformToDotKeys($data);
        \ksort($data);

        $result = [];
        foreach ($data as $key => $value) {
            $this->arraySet($result, $key, $value);
        }

        return $result;
    }

    /**
     * @param array $data
     * @param string $prefix
     * @return array
     */
    protected function transformToDotKeys(array $data, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (\is_array($value) && !empty($value)) {
                $result = \array_merge(
                    $result,
                    $this->transformToDotKeys($value, \sprintf('%s%s.', $prefix, $key))
                );
            } else {
                $result[\sprintf('%s%s', $prefix, $key)] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array $array
     * @param $key
     * @param $value
     * @return array
     */
    protected function arraySet(array &$array, $key, $value): array
    {
        if (null === $key) {
            return $value;
        }

        $keys = \explode('.', $key);

        foreach ($keys as $i => $key) {
            if (\count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (! isset($array[$key]) || ! \is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[\array_shift($keys)] = $value;

        return $array;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function assertContainsJson(array $data): self
    {
        $actual = \json_encode($this->sortRecursive((array) $this->jsonArray));
        assertEquals(\json_encode($this->sortRecursive($data)), $actual);

        return $this;
    }

    /**
     * @param array $array
     * @return array
     */
    protected function sortRecursive(array $array): array
    {
        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $array[$key] = $this->sortRecursive($value);
            }
        }

        if ($this->isAssoc($array)) {
            \ksort($array, SORT_REGULAR);
        } else {
            \sort($array, SORT_REGULAR);
        }

        return $array;
    }

    /**
     * @param array $array
     * @return bool
     */
    protected function isAssoc(array $array): bool
    {
        return \array_values($array) !== $array;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function assertContainsJsonFragment(array $data): self
    {
        $actual = \json_encode($this->sortRecursive((array) $this->jsonArray));

        foreach ($this->sortRecursive($data) as $key => $value) {
            $needles = $this->extractSearchNeedles($key, $value);
            assertTrue(
                $this->isStringContainsAnyNeedles($actual, $needles),
                'Unable to find JSON fragment: ' . PHP_EOL . PHP_EOL .
                '[' . \json_encode([$key => $value]) . ']' . PHP_EOL . PHP_EOL .
                'within' . PHP_EOL . PHP_EOL .
                "[{$actual}]."
            );
        }

        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @return string[]
     */
    protected function extractSearchNeedles($key, $value): array
    {
        $needle = \substr(\json_encode([$key => $value]), 1, -1);

        return [
            $needle . ']',
            $needle . '}',
            $needle . ',',
        ];
    }

    /**
     * @param string $haystack
     * @param array $needles
     * @return bool
     */
    protected function isStringContainsAnyNeedles(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ('' !== $needle && \mb_strpos($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function assertNotContainsExactJson(array $data): self
    {
        $actual = \json_encode($this->sortRecursive((array) $this->jsonArray));

        foreach ($this->sortRecursive($data) as $key => $value) {
            $needles = $this->extractSearchNeedles($key, $value);
            if (!$this->isStringContainsAnyNeedles($actual, $needles)) {
                return $this;
            }
        }

        Assert::fail(
            'Found unexpected JSON fragment: ' . PHP_EOL . PHP_EOL .
            '[' . \json_encode($data) . ']' . PHP_EOL . PHP_EOL .
            'within' . PHP_EOL . PHP_EOL .
            "[{$actual}]."
        );
    }

    /**
     * @param array $data
     * @return $this
     */
    public function assertNotContainsJson(array $data): self
    {
        $actual = \json_encode($this->sortRecursive((array) $this->jsonArray));

        foreach ($this->sortRecursive($data) as $key => $value) {
            $needles = $this->extractSearchNeedles($key, $value);
            assertFalse(
                $this->isStringContainsAnyNeedles($actual, $needles),
                'Found unexpected JSON fragment: ' . PHP_EOL . PHP_EOL .
                '[' . \json_encode([$key => $value]) . ']' . PHP_EOL . PHP_EOL .
                'within' . PHP_EOL . PHP_EOL .
                "[{$actual}]."
            );
        }

        return $this;
    }

    /**
     * @param string $path
     * @param $expected
     * @return $this
     * @throws JSONPathException
     */
    public function assertContainsInPath(string $path, $expected): self
    {
        assertEquals($expected, $this->getJson($path));

        return $this;
    }

    /**
     * @param array $jsonSchema
     * @param string|null $jsonPath
     * @return void
     * @throws JSONPathException
     */
    public function assertMatchesJsonType(array $jsonSchema, ?string $jsonPath = null): void
    {
        $actual = $this->getJson($jsonPath);

        $validator = new Validator();

        $validator->validate($actual, $jsonSchema);
        assertTrue(
            $validator->isValid(),
            "Unable response content match json schema:" . PHP_EOL .
            \json_encode($jsonSchema) . PHP_EOL . PHP_EOL .
            "The following errors occurred:" . PHP_EOL . PHP_EOL .
            \json_encode($validator->getErrors())
        );
    }
}
