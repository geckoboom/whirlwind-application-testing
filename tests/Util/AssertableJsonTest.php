<?php

declare(strict_types=1);

namespace WhirlwindApplicationTesting\Test\Util;

use DG\BypassFinals;
use WhirlwindApplicationTesting\Util\AssertableJson;
use PHPUnit\Framework\TestCase;

class AssertableJsonTest extends TestCase
{
    /**
     * @var array
     */
    protected array $json = [
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
    /**
     * @var AssertableJson
     */
    protected AssertableJson $object;

    protected function setUp(): void
    {
        BypassFinals::enable();
        parent::setUp();

        $this->object = new AssertableJson($this->json);
    }

    public function testAssertContainsJsonFragment()
    {
        $expected = [
            'nested' => [
                'test' => 'value',
            ],
        ];

        $this->object->assertContainsJsonFragment($expected);
    }

    public function testAssertMatchesJsonType()
    {
        $this->object->assertMatchesJsonType([
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

    /**
     * @param $json
     * @param array $expected
     * @return void
     * @throws \Flow\JSONPath\JSONPathException
     *
     * @dataProvider constructorDataProvider
     */
    public function testCreate($json, array $expected)
    {
        $actual = new AssertableJson($json);
        self::assertSame($expected, $actual->getJson());
    }

    public function constructorDataProvider(): array
    {
        return [
            [
                'json' => \json_encode(['test' => 'value']),
                'expected' => ['test' => 'value'],
            ],
            [
                'json' => new class implements \JsonSerializable {
                    public function jsonSerialize(): array
                    {
                        return ['test' => 'value'];
                    }
                },
                'expected' => ['test' => 'value'],
            ],
            [
                'json' => ['test' => 'value'],
                'expected' => ['test' => 'value'],
            ],
        ];
    }

    public function testAssertCount()
    {
        $this->object->assertCount(2, '$.array.*');
    }

    public function testAssertContainsInPath()
    {
        $this->object->assertContainsInPath('$.array.*', $this->json['array']);
    }

    public function testAssertContainsExactJson()
    {
        $this->object->assertContainsExactJson($this->json);
    }

    public function testAssertNotContainsExactJson()
    {
        $test = [
            'test' => 1,
            'array' => [],
        ];

        $this->object->assertNotContainsExactJson($test);
    }

    public function testGetJson()
    {
        $expected = [
            'test' => 'value',
        ];
        self::assertSame($expected, $this->object->getJson('nested')[0]);
    }
}
