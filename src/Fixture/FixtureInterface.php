<?php

declare(strict_types=1);

namespace WhirlwindApplicationTesting\Fixture;

interface FixtureInterface
{
    /**
     * @return void
     */
    public function beforeLoad(): void;
    /**
     * @return void
     */
    public function load(): void;
    /**
     * @return void
     */
    public function afterLoad(): void;

    /**
     * @return void
     */
    public function beforeUnload(): void;
    /**
     * @return void
     */
    public function unload(): void;
    /**
     * @return void
     */
    public function afterUnload(): void;
}
