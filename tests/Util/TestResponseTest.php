<?php

declare(strict_types=1);

namespace WhirlwindApplicationTesting\Test\Util;

use DG\BypassFinals;
use Laminas\Diactoros\Response\JsonResponse;
use WhirlwindApplicationTesting\Util\AssertableJson;
use WhirlwindApplicationTesting\Util\TestResponse;
use PHPUnit\Framework\TestCase;

class TestResponseTest extends TestCase
{
    /**
     * @var TestResponse
     */
    protected TestResponse $response;

    protected JsonResponse $jsonResponse;

    protected function setUp(): void
    {
        BypassFinals::enable();
        parent::setUp();

        $this->jsonResponse = new JsonResponse([
            'test' => 1,
            'nested' => [
                'test' => 'value',
            ],
            'array' => [
                3.1,
                4.2,
            ],
            'sort' => 'asc',
        ]);

        $this->response = new TestResponse($this->jsonResponse);
    }


    public function testAssertResponseIsSuccessful()
    {
        $this->response->assertResponseIsSuccessful();
    }

    public function testGetJson()
    {
        $expected = [
            'test' => 1,
            'nested' => [
                'test' => 'value',
            ],
            'array' => [
                3.1,
                4.2,
            ],
            'sort' => 'asc',
        ];
        self::assertSame($expected, $this->response->getJson());
    }

    public function testAssertResponseCodeIs()
    {
        $this->response->assertResponseCodeIs(TestResponse::OK);
    }

    public function testAssertResponseCodeIsUnprocessable()
    {
        $response = new TestResponse(new JsonResponse([], TestResponse::UNPROCESSABLE_ENTITY));
        $response->assertResponseCodeIsUnprocessable();
    }

    public function testAssertResponseNotContains()
    {
        $test = [
            'unknown' => 'value',
        ];
        $this->response->assertResponseNotContains(\json_encode($test));
    }

    public function testAssertResponseContainsJsonFragment()
    {
        $expected = [
            'nested' => [
                'test' => 'value',
            ],
        ];
        $this->response->assertResponseContainsJsonFragment($expected);
    }

    public function testAssertResponseCodeIsNotAllowed()
    {
        $response = new TestResponse(new JsonResponse([], TestResponse::METHOD_NOT_ALLOWED));
        $response->assertResponseCodeIsNotAllowed();
    }

    public function testDecodeResponseJson()
    {
        self::assertInstanceOf(AssertableJson::class, $this->response->decodeResponseJson());
    }

    public function testAssertResponseCodeIsOk()
    {
        $this->response->assertResponseCodeIsOk();
    }

    public function testAssertResponseCodeIsAccepted()
    {
        $response = new TestResponse(new JsonResponse([], TestResponse::ACCEPTED));
        $response->assertResponseCodeIsAccepted();
    }

    public function testAssertResponseContainsExactJson()
    {
        $this->response->assertResponseContainsExactJson([
            'nested' => [
                'test' => 'value',
            ],
            'test' => 1,
            'array' => [
                3.1,
                4.2,
            ],
            'sort' => 'asc',
        ]);
    }

    public function testAssertResponseContains()
    {
        $this->response->assertResponseContains(\json_encode(['test' => 'value']));
    }

    public function testAssertResponseCodeIsNotFound()
    {
        $response = new TestResponse(new JsonResponse([], TestResponse::NOT_FOUND));
        $response->assertResponseCodeIsNotFound();
    }

    public function testAssertResponseMatchesJsonType()
    {
        $this->response->assertResponseMatchesJsonType([
            'type' => 'object',
            'properties' => [
                'test' => [
                    'type' => 'integer',
                ],
                'nested' => [
                    'type' => 'object',
                    'properties' => [
                        'test' => [
                            'type' => 'string',
                        ]
                    ]
                ],
                'array' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'number',
                        'minimum' => 0,
                    ]
                ],
                'sort' => [
                    'type' => 'string',
                    'enum' => ['asc', 'desc'],
                ],
            ],
        ]);
    }

    public function testAssertResponseCodeIsUnauthorized()
    {
        $response = new TestResponse(new JsonResponse([], TestResponse::UNAUTHORIZED));
        $response->assertResponseCodeIsUnauthorized();
    }

    public function testAssertResponseIsJson()
    {
        $this->response->assertResponseIsJson();
    }

    public function testAssertHeaderMissing()
    {
        $this->response->assertHeaderMissing('X-Test-Header');
    }

    public function testAssertResponseCodeIsBadRequest()
    {
        $response = new TestResponse(new JsonResponse([], TestResponse::BAD_REQUEST));
        $response->assertResponseCodeIsBadRequest();
    }

    public function testAssertResponseNotContainsExactJson()
    {
        $this->response->assertResponseNotContainsExactJson([
            'nested' => [
                'test' => 'value',
            ],
            'test' => 1,
            'array' => [
                4.2,
                3.1,
            ],
            'sort' => 'asc',
        ]);
    }

    public function testAssertContainsInResponsePath()
    {
        $this->response->assertContainsInResponsePath('$.array.*', [3.1, 4.2]);
    }

    public function testAssertResponseCodeIsNoContent()
    {
        $response = new TestResponse(new JsonResponse([], TestResponse::NO_CONTENT));
        $response->assertResponseCodeIsNoContent();
    }

    public function testAssertResponseCodeIsForbidden()
    {
        $response = new TestResponse(new JsonResponse([], TestResponse::FORBIDDEN));
        $response->assertResponseCodeIsForbidden();
    }

    public function testAssertHeader()
    {
        $this->response->assertHeader('Content-Type', 'application/json');
    }

    public function testAssertResponseCodeIsCreated()
    {
        $response = new TestResponse(new JsonResponse([], TestResponse::CREATED));
        $response->assertResponseCodeIsCreated();
    }

    public function testAssertResponseCount()
    {
        $this->response->assertResponseCount(2, '$.array.*');
    }
}
