<?php

declare(strict_types=1);

namespace WhirlwindApplicationTesting\Util;

use Psr\Container\ContainerInterface;
use Whirlwind\App\Application\Application;

class ContainerAwareApplication extends Application
{
    /**
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * @param Application $application
     * @return static
     */
    public static function createFromInstance(Application $application): self
    {
        return new self($application->container);
    }
}
