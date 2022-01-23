<?php

declare(strict_types=1);

namespace WhirlwindApplicationTesting\Traits;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream;
use League\Route\Http\Exception\HttpExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use Whirlwind\App\Application\Application;
use Whirlwind\App\Http\ServerRequestFactoryInterface;
use WhirlwindApplicationTesting\Util\TestResponse;

trait MakesHttpRequests
{
    /**
     * @var array
     */
    protected array $defaultHeaders = [];
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;
    /**
     * @var Application
     */
    protected Application $app;

    /**
     * @param string $name
     * @param $value
     * @return $this
     */
    public function addHeader(string $name, $value): self
    {
        $this->defaultHeaders[$name] = $value;

        return $this;
    }

    /**
     * @param string $username
     * @param string $password
     * @return $this
     */
    public function addHttpAuthentication(string $username, string $password): self
    {
        return $this->addHeader('PHP_AUTH_USER', $username)
            ->addHeader('PHP_AUTH_PW', $password)
            ->addHeader(
                'Authorization',
                \sprintf(
                    'Basic %s',
                    \base64_encode(\sprintf('%s:%s', $username, $password))
                )
            );
    }

    /**
     * @param string $token
     * @param string $type
     * @return $this
     */
    public function addAuthorizationToken(string $token, string $type = 'Bearer'): self
    {
        return $this->addHeader('Authorization', 'Bearer ' . $token);
    }

    /**
     * @param string $uri
     * @param array $headers
     * @return TestResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Throwable
     */
    public function get(string $uri, array $headers = []): TestResponse
    {
        $server = $this->transformHeadersToServerVars($headers);

        return $this->call('GET', $uri, [], [], $server);
    }

    /**
     * @param array $headers
     * @return array
     */
    protected function transformHeadersToServerVars(array $headers): array
    {
        $result = [];
        foreach (\array_merge($this->defaultHeaders, $headers) as $name => $value) {
            $name = \strtr(\strtoupper($name), '-', '_');
            $result[$this->formatServerHeaderKey($name)] = $value;
        }

        return $result;
    }

    /**
     * @param string $name
     * @return string
     */
    protected function formatServerHeaderKey(string $name): string
    {
        $serverCopy = [
            'CONTENT_TYPE',
            'REMOTE_ADDR',
            'PHP_AUTH_USER',
            'PHP_AUTH_PW',
        ];
        $needle = 'HTTP_';
        if (0 !== \strncmp($name, $needle, \strlen($needle)) && !\in_array($name, $serverCopy)) {
            return 'HTTP_' . $name;
        }

        return $name;
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $parameters
     * @param array $files
     * @param array $server
     * @param string|null $content
     * @return TestResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Throwable
     */
    public function call(
        string $method,
        string $uri,
        array $parameters = [],
        array $files = [],
        array $server = [],
        ?string $content = null
    ): TestResponse {
        $server['REQUEST_METHOD'] = \strtoupper($method);
        $server['HTTP_HOST'] = 'localhost';
        $server['REQUEST_URI'] = $uri;

        $query = [];
        $rawQuery = \parse_url($uri, PHP_URL_QUERY);
        if (null !== $rawQuery) {
            \parse_str($rawQuery, $query);
        }
        /** @var ServerRequestFactoryInterface $requestFactory */
        $requestFactory = $this->container->get(ServerRequestFactoryInterface::class);
        $request = $requestFactory::fromGlobals(
            $server,
            $query,
            $parameters,
            null,
            $files
        );

        if (null !== $content) {
            $body = new Stream('php://temp', 'wb+');
            $body->write($content);
            $body->rewind();
            $request = $request->withBody($body)
                ->withParsedBody(\json_decode($content, true));
        }

        try {
            return $this->createTestResponse($this->app->handle($request));
        } catch (\Throwable $e) {
            if ($e instanceof HttpExceptionInterface) {
                $response = new Response();

                return $this->createTestResponse($e->buildJsonResponse($response));
            }

            throw $e;
        }
    }

    /**
     * @param ResponseInterface $response
     * @return TestResponse
     */
    private function createTestResponse(ResponseInterface $response): TestResponse
    {
        return new TestResponse($response);
    }

    /**
     * @param string $uri
     * @param array $headers
     * @return TestResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Throwable
     */
    public function getJson(string $uri, array $headers = []): TestResponse
    {
        return $this->json('GET', $uri, [], $headers);
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return TestResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Throwable
     */
    public function json(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $files = $this->extractFilesFromDataArray($data);

        $content = \json_encode($data);
        $headers = \array_merge(
            [
                'CONTENT_LENGTH' => mb_strlen($content, '8bit'),
                'CONTENT_TYPE' => 'application/json',
                'Accept' => 'application/json',
            ],
            $headers
        );

        return $this->call(
            $method,
            $uri,
            [],
            $files,
            $this->transformHeadersToServerVars($headers),
            $content
        );
    }

    /**
     * @param array $data
     * @return array
     */
    protected function extractFilesFromDataArray(array $data): array
    {
        $files = [];
        foreach ($data as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $files[$key] = $value;
                unset($data[$key]);
            }

            if (\is_array($value)) {
                $files[$key] = $this->extractFilesFromDataArray($value);
                $data[$key] = $value;
            }
        }

        return $files;
    }

    /**
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return TestResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Throwable
     */
    public function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call(
            'POST',
            $uri,
            $data,
            [],
            $this->transformHeadersToServerVars($headers)
        );
    }

    /**
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return TestResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function postJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('POST', $uri, $data, $headers);
    }

    /**
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return TestResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Throwable
     */
    public function put(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call(
            'PUT',
            $uri,
            $data,
            [],
            $this->transformHeadersToServerVars($headers)
        );
    }

    /**
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return TestResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Throwable
     */
    public function putJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('PUT', $uri, $data, $headers);
    }

    /**
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return TestResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Throwable
     */
    public function patch(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call(
            'PATCH',
            $uri,
            $data,
            [],
            $this->transformHeadersToServerVars($headers)
        );
    }

    /**
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return TestResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Throwable
     */
    public function patchJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('PATCH', $uri, $data, $headers);
    }

    /**
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return TestResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Throwable
     */
    public function delete(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call(
            'DELETE',
            $uri,
            $data,
            [],
            $this->transformHeadersToServerVars($headers)
        );
    }

    /**
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return TestResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Throwable
     */
    public function deleteJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('DELETE', $uri, $data, $headers);
    }

    /**
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return TestResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Throwable
     */
    public function options(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call(
            'OPTIONS',
            $uri,
            $data,
            [],
            $this->transformHeadersToServerVars($headers)
        );
    }

    /**
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return TestResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Throwable
     */
    public function optionsJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('OPTIONS', $uri, $data, $headers);
    }
}
