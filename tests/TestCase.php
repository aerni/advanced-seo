<?php

namespace Aerni\AdvancedSeo\Tests;

use Aerni\AdvancedSeo\ServiceProvider;
use Aerni\AdvancedSeo\Tests\Concerns\UseEloquentDriver;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('statamic.editions.pro', true);

        $app['config']->set('advanced-seo', require (__DIR__.'/../config/advanced-seo.php'));

        $app['config']->set('advanced-seo.directory', __DIR__.'/__fixtures__/content/seo');

        if ($this->usesEloquentDriver()) {
            $eloquentDriverConfig = array_merge(
                require (__DIR__.'/../vendor/statamic/eloquent-driver/config/eloquent-driver.php'),
                [
                    'advanced_seo' => [
                        'driver' => 'eloquent',
                        'model' => Eloquent\SeoDefaultModel::class,
                    ],
                ]
            );

            $app['config']->set('statamic.eloquent-driver', $eloquentDriverConfig);
        }
    }

    protected function setUp(): void
    {
        /* Need to copy the correct composer.lock file to the correct location so that Composer::isInstalled() won't fail in the service provider. */
        $this->usesEloquentDriver()
            ? copy(__DIR__.'/__fixtures__/composer.eloquent.lock', __DIR__.'/../vendor/orchestra/testbench-core/laravel/composer.lock')
            : copy(__DIR__.'/__fixtures__/composer.empty.lock', __DIR__.'/../vendor/orchestra/testbench-core/laravel/composer.lock');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        /* Delete the previously copied composer.lock file. */
        unlink(__DIR__.'/../vendor/orchestra/testbench-core/laravel/composer.lock');

        parent::tearDown();
    }

    protected function usesEloquentDriver(): bool
    {
        return isset(array_flip(class_uses_recursive(static::class))[UseEloquentDriver::class]);
    }
}
