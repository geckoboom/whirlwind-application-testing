<?php

declare(strict_types=1);

namespace WhirlwindApplicationTesting\Util;

use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertNotEquals;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;
use function PHPUnit\Framework\assertTrue;

class TestResponse
{
    public const OK = 200;
    public const CREATED = 201;
    public const ACCEPTED = 202;
    public const NO_CONTENT = 204;

    public const BAD_REQUEST = 400;
    public const UNAUTHORIZED = 401;
    public const FORBIDDEN = 403;
    public const NOT_FOUND = 404;
    public const METHOD_NOT_ALLOWED = 405;
    public const UNPROCESSABLE_ENTITY = 422;

    /**
     * @var ResponseInterface
     */
    protected ResponseInterface $baseResponse;

    /**
     * @param ResponseInterface $baseResponse
     */
    public function __construct(ResponseInterface $baseResponse)
    {
        $this->baseResponse = $baseResponse;
    }

    public function assertResponseIsSuccessful(): self
    {
        $status = $this->baseResponse->getStatusCode();

        assertTrue(
            200 <= $status && 300 > $status,
            $this->statusMessageWithDetails('>=200, <300', $status)
        );

        return $this;
    }

    protected function statusMessageWithDetails($expected, $actual): string
    {
        return "Expected response status code [$expected] but received $actual.";
    }

    public function assertResponseCodeIs(int $status): self
    {
        $message = $this->statusMessageWithDetails($status, $actual = $this->baseResponse->getStatusCode());

        assertSame($status, $actual, $message);

        return $this;
    }

    public function assertResponseCodeIsOk(): self
    {
        return $this->assertResponseCodeIs(self::OK);
    }

    public function assertResponseCodeIsCreated(): self
    {
        return $this->assertResponseCodeIs(self::CREATED);
    }

    public function assertResponseCodeIsAccepted(): self
    {
        return $this->assertResponseCodeIs(self::ACCEPTED);
    }

    public function assertResponseCodeIsNoContent(): self
    {
        return $this->assertResponseCodeIs(self::NO_CONTENT);
    }

    public function assertResponseCodeIsNotFound(): self
    {
        return $this->assertResponseCodeIs(self::NOT_FOUND);
    }

    public function assertResponseCodeIsBadRequest(): self
    {
        return $this->assertResponseCodeIs(self::BAD_REQUEST);
    }

    public function assertResponseCodeIsUnauthorized(): self
    {
        return $this->assertResponseCodeIs(self::UNAUTHORIZED);
    }

    public function assertResponseCodeIsForbidden(): self
    {
        return $this->assertResponseCodeIs(self::FORBIDDEN);
    }

    public function assertResponseCodeIsNotAllowed(): self
    {
        return $this->assertResponseCodeIs(self::METHOD_NOT_ALLOWED);
    }

    public function assertResponseCodeIsUnprocessable(): self
    {
        return $this->assertResponseCodeIs(self::UNPROCESSABLE_ENTITY);
    }

    public function assertHeader(string $name, $value = null): self
    {
        assertTrue(
            $this->baseResponse->hasHeader($name),
            "Header [{$name}] not present on response."
        );

        if (null !== $value) {
            $actual = $this->baseResponse->getHeader($name);
            assertTrue(
                \in_array($value, $actual),
                "Header [{$name}] was found, but value [{$value}] does not exist."
            );
        }

        return $this;
    }

    public function assertResponseIsJson(): self
    {
        $content = $this->baseResponse->getBody()->getContents();
        assertNotEquals('', $content, 'Response is empty');
        \json_decode($content);
        $errorCode = \json_last_error();
        $errorMessage = \json_last_error_msg();

        assertEquals(
            JSON_ERROR_NONE,
            $errorCode,
            \sprintf(
                'Invalid json: %s. System message: %s.',
                $content,
                $errorMessage
            )
        );

        return $this;
    }

    public function assertResponseContains(string $needle, bool $escape = true): self
    {
        $haystack = $this->baseResponse->getBody()->getContents();

        if ($escape) {
            $needle = \htmlspecialchars(
                $needle,
                ENT_QUOTES,
                'UTF-8',
                true
            );
            $haystack = \htmlspecialchars(
                $haystack ?? '',
                ENT_QUOTES,
                'UTF-8',
                true
            );
        }
        assertStringContainsString($needle, $haystack);

        return $this;
    }

    public function assertResponseNotContains(string $needle, bool $escape = true): self
    {
        $haystack = $this->baseResponse->getBody()->getContents();

        if ($escape) {
            $haystack = \htmlspecialchars(
                $this->baseResponse->getBody()->getContents() ?? '',
                ENT_QUOTES,
                'UTF-8',
                true
            );
        }
        assertStringNotContainsString($needle, $haystack);

        return $this;
    }

    public function assertHeaderMissing(string $name): self
    {
        assertFalse($this->baseResponse->hasHeader($name), "Header [{$name}] is present on response.");

        return $this;
    }

    public function getJson(?string $path = null)
    {
        return $this->decodeResponseJson()->getJson($path);
    }

    public function decodeResponseJson(): AssertableJson
    {
        $this->baseResponse->getBody()->rewind();
        $testJson = new AssertableJson($this->baseResponse->getBody()->getContents());

        $decodedResponse = $testJson->getJson();
        if (null === $decodedResponse || false === $decodedResponse) {
            Assert::fail('Invalid JSON was returned from the route.');
        }

        return $testJson;
    }

    public function assertContainsInResponsePath(string $path, $expected): self
    {
        $this->decodeResponseJson()->assertContainsInPath($path, $expected);

        return $this;
    }

    public function assertResponseContainsExactJson(array $data): self
    {
        $this->decodeResponseJson()->assertContainsExactJson($data);

        return $this;
    }

    public function assertResponseContainsJson(array $data): self
    {
        $this->decodeResponseJson()->assertContainsJson($data);

        return $this;
    }

    public function assertResponseContainsJsonFragment(array $data): self
    {
        $this->decodeResponseJson()->assertContainsJsonFragment($data);

        return $this;
    }

    public function assertResponseNotContainsExactJson(array $data): self
    {
        $this->decodeResponseJson()->assertNotContainsExactJson($data);

        return $this;
    }

    public function assertResponseNotContainsJson(array $data): self
    {
        $this->decodeResponseJson()->assertNotContainsJson($data);

        return $this;
    }

    public function assertResponseMatchesJsonType(array $jsonSchema, ?string $jsonPath = null): self
    {
        $this->decodeResponseJson()->assertMatchesJsonType($jsonSchema, $jsonPath);

        return $this;
    }

    public function assertResponseCount(int $count, ?string $path = null): self
    {
        $this->decodeResponseJson()->assertCount($count, $path);

        return $this;
    }
}
