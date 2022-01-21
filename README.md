# Whirlwind Application Tests

Application tests check the integration of all layers of the application (from routing 
to the responses). They are based on PHPUnit framework and have a special workflow:

1. Make a request
2. Test the response

## Requirements

* `PHP 7.4` or higher.

## Installation
The preferred way to install this extension is through composer.

Either run
```
composer require "geckoboom/whirlwind-application-testing" --dev
```
or add
```
 "geckoboom/whirlwind-application-testing": "*"
```
to the require-dev section of your composer.json file.

## Defining a TestCase
To define your REST tests infrastructure you have to create a TestCase class that extends
[RestTestCase](https://github.com/geckoboom/whirlwind-application-testing/blob/master/src/RestTestCase.php)
and implement realization for `createApplication()` method.

The following code defines a whirlwind application TestCase

```php
<?php

namespace Test;

use League\Container\Container;
use Whirlwind\App\Application\Application;
use WhirlwindApplicationTesting\RestTestCase;
use App\Models\User;

abstract class TestCase extends RestTestCase
{
    protected function createApplication(): Application
    {
        $container = new Container();
        // register service providers here
        return new Application($container);
    }
}
``` 

In the above example, we have minimum configuration for making application tests

Now you can make REST tests. Just create new test and extend created earlier TestCase

```php
<?php

namespace Test;

class UsersTest extends TestCase
{
    public function testGetById()
    {   
        $this->addAuthorizationToken('secret')
            ->get('/users/1', ['X-Test-Header' => 'Test'])
            ->assertResponseIsSuccessful()
            ->assertResponseCodeIsOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertResponseContainsJsonFragment(['id' => 1]);
    }
}
```

In the above example, the test add Bearer authorization token and validates if HTTP request was successful,
have 200 status code and contains appropriate header and body fragment in response.

## Making Requests
The TestCase simulates an HTTP client like a browser and makes requests into your Whirlwind application. By default
you can access to the next HTTP methods:
* `get($uri, $headers = [])`
* `getJson($uri, $headers = [])` 
* `post($uri, $data = [], $headers = [])` 
* `postJson($uri, $data = [], $headers = [])` 
* `put($uri, $data = [], $headers = [])` 
* `putJson($uri, $data = [], $headers = [])` 
* `patch($uri, $data = [], $headers = [])` 
* `patchJson($uri, $data = [], $headers = [])` 
* `delete($uri, $data = [], $headers = [])` 
* `deleteJson($uri, $data = [], $headers = [])` 
* `options($uri, $data = [], $headers = [])` 
* `optionsJson($uri, $data = [], $headers = [])` 

If required HTTP method absent in the list above you can make HTTP request via `call()` or `json()` method.

The full signature of the `call()` and `json()` methods
```php
call(
    string $method,
    string $uri,
    array $parameters = [],
    array $files = [],
    array $server = [],
    ?string $content = null
)

json(
    string $method,
    string $uri,
    array $parameters = [],
    array $headers = []
)
```

## Available Response Assertions
* `assertResponseIsSuccessful()` check if status code in range [200, 300)
* `assertResponseCodeIs(int $status)` check if status code equal to `$status`
* `assertResponseCodeIsOk()` check if status code is 200
* `assertResponseCodeIsCreated()` check if status code is 201
* `assertResponseCodeIsAccepted()` check if status code is 202
* `assertResponseCodeIsNoContent()` check if status code is 204
* `assertResponseCodeIsBadRequest()` check if status code is 400
* `assertResponseCodeIsUnauthorized()` check if status code is 401
* `assertResponseCodeIsForbidden()` check if status code is 403
* `assertResponseCodeIsNotFound()` check if status code is 404
* `assertResponseCodeIsNotAllowed()` check if status code is 405
* `assertResponseCodeIsUnprocessable()` check if status code is 422
* `assertHeader(string $name, $value = null)` check if response has header `$name` with value `$value` (if not null)
* `assertResponseIsJson()` check if response contains valid json
* `assertResponseContains(string $needle, bool $escape = true)` check if response json contains string
* `assertResponseNotContains(string $needle, bool $escape = true)` check if response json not contains string
* `assertHeaderMissing(string $name)` check if response has no header with name `$name`
* `assertContainsInResponsePath(string $path, $expected)`
* `assertResponseContainsExactJson(array $data)`
* `assertResponseContainsJsonFragment(array $data)` check if response contains `$data` fragment
* `assertResponseNotContainsExactJson(array $data)`
* `assertResponseMatchesJsonType(array $jsonSchema, ?string $jsonPath = null)` See [justinrainbow/json-schema](https://packagist.org/packages/justinrainbow/json-schema)
* `assertResponseCount(int $count, ?string $jsonPath = null)`

# Fixtures
Fixtures are used to load "fake" set of data into a database for testing purpose. Fixture can depend on other fixtures 
(`WhirlwindApplicationTesting\Fixture\Fixture::$depends` property).

## Defining a Fixture
To define a fixture, just create a new class that implements `WhirlwindApplicationTesting\Fixture\FixtureInterface`.
```php
<?php

namespace Test\Fixture;

use Domain\User\User;
use Whirlwind\Infrastructure\Hydrator\UserHydrator;
use Whirlwind\Infrastructure\Repository\TableGateway\UserTableGateway;
use WhirlwindApplicationTesting\Fixture\EntityFixture;

class UserFixture extends EntityFixture
{
    protected $dataFile = 'users.php';
    protected $entityClass = User::class;
    
    public function __construct(UserHydrator $hydrator, UserTableGateway $tableGateway) 
    {
        parent::__construct($hydrator, $tableGateway);
    }   
}
```
> Tip: Each EntityFixture is about preparing a DB table for testing purpose. You may specify appropriate realization of TableGatewayInterface in constructor as well as a Hydrator realization property. The table name encapsulates in table gateway, while hydrator uses for mapping raw results in WhirlwindApplicationTesting\Fixture\EntityFixture::$entityClass objects

The fixture data for an EntityFixture fixture is usually provided in a file located at `data` directory where fixture 
class is declared. The data file should return an array of data rows to be inserted into the table. For example:
```php
<?php

// tests/Fixture/data/users.php
return [
    [
        'name' => 'user1',
        'email' => 'user1@example.org',
        'password' => bcrypt('secret'),
    ],
    [
        'name' => 'user2',
        'email' => 'user2@example.org',
        'password' => bcrypt('secret'),
    ],
];
```
Dependable fixture can be created by specifying `WhirlwindApplicationTesting\Fixture\Fixture::$depends` property, like
the following
```php
<?php

namespace Test\Fixture;

use Domain\User\UserProfile;
use Whirlwind\Infrastructure\Hydrator\UserHydrator;
use Whirlwind\Infrastructure\Repository\TableGateway\UserTableGateway;
use WhirlwindApplicationTesting\Fixture\EntityFixture;

class UserProfileFixture extends EntityFixture
{
    protected $dataFile = 'user_profiles.php';
    protected $entityClass = UserProfile::class;
    protected $depends = [
        UserFixture::class,
    ];
    
    public function __construct(UserProfileHydrator $hydrator, UserProfileTableGateway $tableGateway) 
    {
        parent::__construct($hydrator, $tableGateway);
    }   
}
```
The dependency allows you to load and unload fixtures in well-defined order. In the above example `UserFixture` will 
always be loaded before `UserProfileFixture`. 

## Using Fixtures
If you are using fixtures in your test code, you need to add `WhirlwindApplicationTesting\Traits\InteractWithFixtures`
trait and implement `fixtures()` method

```php
<?php

namespace Test;

use Domain\Profile\Profile;
use Test\Fixture\UserFixture;
use Test\Fixture\UserProfileFixture;
use WhirlwindApplicationTesting\Traits\InteractWithFixtures;

class UsersTest extends TestCase
{
    use InteractWithFixtures;
    
    public function testGetById()
    {   
        $this->addAuthorizationToken('secret')
            ->get('/users/1', ['X-Test-Header' => 'Test'])
            ->assertResponseIsSuccessful()
            ->assertResponseCodeIsOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertResponseContainsJsonFragment(['id' => 1]);
    }
    
    public function fixtures(): array
    {
         return [
            'users' => UserFixtureClass::class,
            'profiles' => [
                'class' => UserProfileFixture::class,
                'depends' => [
                    UserFixture::class,
                ],
                'dataFile' => 'profile.php',
                'entityClass' => Profile::class,
            ],
        ];
    }
}
```

The fixtures listed in the `fixtures()` method will be automatically loaded before a test is executed.