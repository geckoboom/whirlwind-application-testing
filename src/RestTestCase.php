<?php

declare(strict_types=1);

namespace WhirlwindApplicationTesting;

use DG\BypassFinals;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Whirlwind\App\Application\Application;
use WhirlwindApplicationTesting\Traits\InteractWithFixtures;
use WhirlwindApplicationTesting\Traits\MakesHttpRequests;
use WhirlwindApplicationTesting\Util\ContainerAwareApplication;

abstract class RestTestCase extends TestCase
{
    use InteractWithFixtures;
    use MakesHttpRequests;

    /**
     * @var Application
     */
    protected Application $app;
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;
    protected array $defaultHeaders = [];
    /**
     * @var array
     */
    protected array $serverParams = [];

    /**
     * @return void
     */
    protected function setUp(): void
    {
        BypassFinals::enable();
        parent::setUp();
        $this->serverParams = $_SERVER;
        $this->app = ContainerAwareApplication::createFromInstance($this->createApplication());
        $this->container = $this->app->getContainer();
        $this->setUpTraits();
    }

    /**
     * @return Application
     */
    abstract protected function createApplication(): Application;

    /**
     * @return void
     * @throws Fixture\Exception\InvalidConfigException
     */
    protected function setUpTraits(): void
    {
        $uses = \array_flip(\class_uses($this));

        if (isset($uses[InteractWithFixtures::class])) {
            $this->initFixtures();
        }
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->app, $this->container);
        $_SERVER = $this->serverParams;
    }
}
