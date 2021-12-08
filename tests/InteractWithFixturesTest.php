<?php

declare(strict_types=1);

namespace WhirlwindApplicationTesting\Test;

use DG\BypassFinals;
use Hamcrest\Core\IsIdentical;
use League\Container\Container;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use WhirlwindApplicationTesting\Fixture\EntityFixture;
use WhirlwindApplicationTesting\Fixture\Exception\InvalidConfigException;
use WhirlwindApplicationTesting\Fixture\Fixture;
use WhirlwindApplicationTesting\Traits\InteractWithFixtures;

class InteractWithFixturesTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var object&InteractWithFixtures
     */
    protected $tester;

    protected function setUp(): void
    {
        BypassFinals::enable();
        parent::setUp();

        $this->tester = $this->createTester();
    }

    private function createTester(): object
    {
        return new class () {
            use InteractWithFixtures;

            private $fixtureConfig = [];

            public function __construct()
            {
                $this->container = new Container();
            }

            public function fixtures(): array
            {
                return $this->fixtureConfig;
            }

            /**
             * @param array $fixtureConfig
             */
            public function setFixtureConfig(array $fixtureConfig): void
            {
                $this->fixtureConfig = $fixtureConfig;
            }

            public function getContainer(): ContainerInterface
            {
                return $this->container;
            }
        };
    }

    public function testInitFixtures()
    {
        $secondFixture = $this->createFixtureMock(Fixture::class);
        $this->tester->getContainer()->add(
            \get_class($secondFixture),
            $secondFixture
        );

        $depends = [
            \get_class($secondFixture),
        ];

        $dependantFixture = $this->createFixtureMock(EntityFixture::class, $depends);
        $this->tester->getContainer()->add(
            \get_class($dependantFixture),
            $dependantFixture,
        );

        $this->tester->setFixtureConfig([
            'new' => \get_class($secondFixture),
            'test' => [
                'class' => \get_class($dependantFixture),
                'depends' => $depends,
            ],
        ]);
        $this->mockUnloadBehavior($dependantFixture);
        $this->mockUnloadBehavior($secondFixture);
        $this->mockLoadBehaviour($dependantFixture);
        $this->mockLoadBehaviour($secondFixture);
        $this->tester->initFixtures();
    }

    private function createFixtureMock(string $fixtureClass, array $depends = []): MockInterface
    {
        return \Mockery::mock($fixtureClass)
            ->shouldReceive('setDepends')
            ->zeroOrMoreTimes()
            ->with(IsIdentical::identicalTo($depends))
            ->getMock()
            ->shouldReceive('getDepends')
            ->zeroOrMoreTimes()
            ->withNoArgs()
            ->andReturn($depends)
            ->getMock();
    }

    private function mockUnloadBehavior(MockInterface $fixture): void
    {
        $fixture->shouldReceive('beforeUnload')
            ->once()
            ->withNoArgs()
            ->getMock()
            ->shouldReceive('unload')
            ->once()
            ->withNoArgs()
            ->getMock()
            ->shouldReceive('afterUnload')
            ->once()
            ->withNoArgs()
            ->getMock();
    }

    private function mockLoadBehaviour(MockInterface $fixture): void
    {
        $fixture->shouldReceive('beforeLoad')
            ->once()
            ->withNoArgs()
            ->getMock()
            ->shouldReceive('load')
            ->once()
            ->withNoArgs()
            ->getMock()
            ->shouldReceive('afterLoad')
            ->once()
            ->withNoArgs()
            ->getMock();
    }

    public function testInitFixturesClassNotSpecified()
    {
        $this->tester->setFixtureConfig([
            'test' => [
                'depends' => [],
            ],
        ]);
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('You must specify \'class\' for the fixture \'test\'.');
        $this->tester->initFixtures();
    }

    public function testGrabFixture()
    {
        $fixture = $this->createFixtureMock(EntityFixture::class);
        $this->tester->getContainer()->add(
            \get_class($fixture),
            $fixture,
        );
        $this->tester->setFixtureConfig([
            'test' => [
                'class' => \get_class($fixture),
                'depends' => [],
            ],
        ]);
        $actual = $this->tester->grabFixture('test');

        self::assertSame($fixture, $actual);
    }
}
