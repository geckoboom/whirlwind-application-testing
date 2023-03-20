<?php

declare(strict_types=1);

namespace WhirlwindApplicationTesting\Test;

use DG\BypassFinals;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ResponseFactory;
use League\Container\Container;
use League\Route\Strategy\ApplicationStrategy;
use League\Route\Strategy\JsonStrategy;
use League\Route\Strategy\StrategyAwareInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Whirlwind\Adapter\League\App\Http\LaminasServerRequestFactoryAdapter;
use Whirlwind\Adapter\League\App\Router\LeagueRouterAdapter;
use Whirlwind\App\Application\Application;
use Whirlwind\App\Http\ServerRequestFactoryInterface;
use Whirlwind\App\Router\RouterInterface;
use WhirlwindApplicationTesting\RestTestCase;
use WhirlwindApplicationTesting\Util\ContainerAwareApplication;
use WhirlwindApplicationTesting\Util\TestResponse;

class RestTestCaseTest extends TestCase
{
    /**
     * @var ContainerAwareApplication
     */
    protected $app;
    /**
     * @var RestTestCase
     */
    protected $tester;
    /**
     * @return void
     */
    protected function setUp(): void
    {
        BypassFinals::enable();
        parent::setUp();

        $this->app = $this->createApp();
        $this->tester = $this->getTester($this->app);
        $this->tester->bindInstance(
            ServerRequestFactoryInterface::class,
            LaminasServerRequestFactoryAdapter::class
        );
    }

    private function createApp(): ContainerAwareApplication
    {
        $container = new Container();
        $strategy = (new ApplicationStrategy())->setContainer($container);
        $router = (new LeagueRouterAdapter())->setStrategy($strategy);
        $container->add(RouterInterface::class, $router)->setShared(true);

        return new ContainerAwareApplication($container);
    }

    private function getTester(ContainerAwareApplication $app): RestTestCase
    {
        $tester =  new class () extends RestTestCase {
            /**
             * @return Application
             */
            protected function createApplication(): Application
            {
                return $this->app;
            }

            /**
             * @return void
             */
            public function setUp(): void
            {
                parent::setUp();
            }

            /**
             * @return void
             */
            public function tearDown(): void
            {
                parent::tearDown();
            }

            /**
             * @param Application $app
             */
            public function setApp(Application $app): void
            {
                $this->app = $app;
            }
        };

        $tester->setApp($app);
        $tester->setUp();

        return $tester;
    }

    public function testContainerBindInstance()
    {
        $actual = $this->tester->bindInstance('test', 1);

        self::assertSame($this->app->getContainer()->get('test'), $actual);

        $actual = $this->tester->bindInstance('obj', static function () {
            return new \stdClass();
        });
        self::assertNotSame($this->app->getContainer()->get('obj'), $actual);
    }

    public function testBindSingleton()
    {
        $actual = $this->tester->bindSingleton('obj', static function () {
            return new \stdClass();
        });
        self::assertSame($this->app->getContainer()->get('obj'), $actual);
    }

    public function testContainerMock()
    {
        $actual = $this->tester->mock(TestResponse::class);

        self::assertSame($this->app->getContainer()->get(TestResponse::class), $actual);
    }

    public function testPartialMock()
    {
        $actual = $this->tester->partialMock(TestResponse::class);

        self::assertSame($this->app->getContainer()->get(TestResponse::class), $actual);
    }

    public function testContainerSpy()
    {
        $actual = $this->tester->spy(TestResponse::class);

        self::assertSame($this->app->getContainer()->get(TestResponse::class), $actual);
    }

    public function testMakeGetRequest()
    {
        /** @var RouterInterface $router */
        $router = $this->app->getContainer()->get(RouterInterface::class);
        $query = [
            'test' => 'value',
        ];
        $headers = [
            'X-Test-Header' => 'test',
        ];
        $router->map('GET', '/api/test', function (ServerRequestInterface $request) use ($query, $headers) {
            self::assertSame($query, $request->getQueryParams());
            self::assertTrue(\in_array($headers['X-Test-Header'], $request->getHeader('X-Test-Header')));
            return new JsonResponse(['response' => 'test']);
        });

        $actual = $this->tester->get('/api/test?' . \http_build_query($query), $headers)
            ->getJson();

        $expected = [
            'response' => 'test',
        ];

        self::assertSame($expected, $actual);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->tester->tearDown();
    }

    public function testAddHeader()
    {
        /** @var RouterInterface $router */
        $router = $this->app->getContainer()->get(RouterInterface::class);

        $header = 'Test';

        $router->map('GET', '/api/test', function (ServerRequestInterface $request) {
            return new JsonResponse(['response' => 'test'], 200, $request->getHeaders());
        });

        $this->tester->addHeader('X-Test-Header', $header)
            ->get('/api/test')
            ->assertHeader('X-Test-Header', $header);
    }

    public function testAddHttpAuthentication()
    {
        /** @var RouterInterface $router */
        $router = $this->app->getContainer()->get(RouterInterface::class);

        $router->map('GET', '/api/test', function (ServerRequestInterface $request) {
            return new JsonResponse(['response' => 'test'], 200, $request->getHeaders());
        });

        $this->tester->addHttpAuthentication('test', 'secret')
            ->get('/api/test')
            ->assertHeader('Authorization', \sprintf('Basic %s', \base64_encode('test:secret')));
    }

    public function testAddAuthorizationToken()
    {
        /** @var RouterInterface $router */
        $router = $this->app->getContainer()->get(RouterInterface::class);

        $router->map('GET', '/api/test', function (ServerRequestInterface $request) {
            return new JsonResponse(['response' => 'test'], 200, $request->getHeaders());
        });

        $this->tester->addAuthorizationToken('token')
            ->get('/api/test')
            ->assertHeader('Authorization', 'Bearer token');
    }

    public function testGetJson()
    {
        /** @var RouterInterface $router */
        $router = $this->app->getContainer()->get(RouterInterface::class);

        $router->map('GET', '/api/test', function (ServerRequestInterface $request) {
            return new JsonResponse(['response' => 'test'], 200, $request->getHeaders());
        });

        $this->tester->getJson('/api/test')
            ->assertHeader('Content-Type', 'application/json');
    }

    public function testPost()
    {
        /** @var RouterInterface $router */
        $router = $this->app->getContainer()->get(RouterInterface::class);

        $router->map('POST', '/api/test', function (ServerRequestInterface $request) {
            return new JsonResponse($request->getParsedBody());
        });

        $actual = $this->tester->post('/api/test', ['test' => 'value'])
            ->getJson();

        $expected = [
            'test' => 'value',
        ];
        self::assertSame($expected, $actual);
    }

    public function testPostJson()
    {
        /** @var RouterInterface $router */
        $router = $this->app->getContainer()->get(RouterInterface::class);

        $router->map('POST', '/api/test', function (ServerRequestInterface $request) {
            return new JsonResponse($request->getParsedBody());
        });

        $actual = $this->tester->postJson('/api/test', ['test' => 'value'])
            ->assertHeader('Content-Type', 'application/json')
            ->getJson();

        $expected = [
            'test' => 'value',
        ];
        self::assertSame($expected, $actual);
    }

    public function testPut()
    {
        /** @var RouterInterface $router */
        $router = $this->app->getContainer()->get(RouterInterface::class);

        $router->map('PUT', '/api/test', function (ServerRequestInterface $request) {
            return new JsonResponse($request->getParsedBody());
        });

        $actual = $this->tester->put('/api/test', ['test' => 'value'])
            ->getJson();

        $expected = [
            'test' => 'value',
        ];
        self::assertSame($expected, $actual);
    }

    public function testPutJson()
    {
        /** @var RouterInterface $router */
        $router = $this->app->getContainer()->get(RouterInterface::class);

        $router->map('PUT', '/api/test', function (ServerRequestInterface $request) {
            return new JsonResponse($request->getParsedBody());
        });

        $actual = $this->tester->putJson('/api/test', ['test' => 'value'])
            ->assertHeader('Content-Type', 'application/json')
            ->getJson();

        $expected = [
            'test' => 'value',
        ];
        self::assertSame($expected, $actual);
    }

    public function testPatch()
    {
        /** @var RouterInterface $router */
        $router = $this->app->getContainer()->get(RouterInterface::class);

        $router->map('PATCH', '/api/test', function (ServerRequestInterface $request) {
            return new JsonResponse($request->getParsedBody());
        });

        $actual = $this->tester->patch('/api/test', ['test' => 'value'])
            ->getJson();

        $expected = [
            'test' => 'value',
        ];
        self::assertSame($expected, $actual);
    }

    public function testPatchJson()
    {
        /** @var RouterInterface $router */
        $router = $this->app->getContainer()->get(RouterInterface::class);

        $router->map('PATCH', '/api/test', function (ServerRequestInterface $request) {
            return new JsonResponse($request->getParsedBody());
        });

        $actual = $this->tester->patchJson('/api/test', ['test' => 'value'])
            ->assertHeader('Content-Type', 'application/json')
            ->getJson();

        $expected = [
            'test' => 'value',
        ];
        self::assertSame($expected, $actual);
    }

    public function testDelete()
    {
        /** @var RouterInterface $router */
        $router = $this->app->getContainer()->get(RouterInterface::class);

        $router->map('DELETE', '/api/test', function (ServerRequestInterface $request) {
            return new JsonResponse($request->getParsedBody());
        });

        $actual = $this->tester->delete('/api/test', ['test' => 'value'])
            ->getJson();

        $expected = [
            'test' => 'value',
        ];
        self::assertSame($expected, $actual);
    }

    public function testDeleteJson()
    {
        /** @var RouterInterface $router */
        $router = $this->app->getContainer()->get(RouterInterface::class);

        $router->map('DELETE', '/api/test', function (ServerRequestInterface $request) {
            return new JsonResponse($request->getParsedBody());
        });

        $actual = $this->tester->deleteJson('/api/test', ['test' => 'value'])
            ->assertHeader('Content-Type', 'application/json')
            ->getJson();

        $expected = [
            'test' => 'value',
        ];
        self::assertSame($expected, $actual);
    }

    public function testOptions()
    {
        /** @var RouterInterface $router */
        $router = $this->app->getContainer()->get(RouterInterface::class);

        $router->map('OPTIONS', '/api/test', function (ServerRequestInterface $request) {
            return new JsonResponse($request->getQueryParams());
        });

        $actual = $this->tester->options('/api/test?' . \http_build_query(['test' => 'value']))
            ->getJson();

        $expected = [
            'test' => 'value',
        ];
        self::assertSame($expected, $actual);
    }

    public function testOptionsJson()
    {
        /** @var RouterInterface $router */
        $router = $this->app->getContainer()->get(RouterInterface::class);

        $router->map('OPTIONS', '/api/test', function (ServerRequestInterface $request) {
            return new JsonResponse($request->getQueryParams());
        });

        $actual = $this->tester->optionsJson('/api/test?' . \http_build_query(['test' => 'value']))
            ->assertHeader('Content-Type', 'application/json')
            ->getJson();

        $expected = [
            'test' => 'value',
        ];
        self::assertSame($expected, $actual);
    }

    public function testNotFoundRoute(): void
    {
        $this->tester->get('/api/test')
            ->assertResponseCodeIsNotFound();
    }

    public function testMethodNotAllowedJsonStrategy()
    {
        /** @var RouterInterface&StrategyAwareInterface $router */
        $router = $this->app->getContainer()->get(RouterInterface::class);
        $strategy = (new JsonStrategy(new ResponseFactory()))->setContainer($this->app->getContainer());
        $router->setStrategy($strategy);

        $router->map('GET', '/api/test', function (ServerRequestInterface $request) {
            return new JsonResponse([]);
        });

        $actual = $this->tester->post('/api/test')
            ->assertResponseCodeIsNotAllowed()
            ->getJson();

        $expected = [
            'status_code' => 405,
            'reason_phrase' => 'Method Not Allowed',
        ];
        self::assertSame($expected, $actual);
    }
}
