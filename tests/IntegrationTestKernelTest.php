<?php

namespace Tourze\IntegrationTestKernel\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

class IntegrationTestKernelTest extends TestCase
{
    public function test_constructor_setsEnvironmentAndDebugCorrectly(): void
    {
        $kernel = new IntegrationTestKernel('test', true);

        $this->assertSame('test', $kernel->getEnvironment());
        $this->assertTrue($kernel->isDebug());
    }

    public function test_constructor_withAppendBundlesAndEntityMappings(): void
    {
        $appendBundles = [DoctrineBundle::class => ['all' => true]];
        $entityMappings = ['App\\Entity' => '/path/to/entities'];

        $kernel = new IntegrationTestKernel('test', false, $appendBundles, $entityMappings);

        $this->assertSame('test', $kernel->getEnvironment());
        $this->assertFalse($kernel->isDebug());
    }

    public function test_registerBundles_includesFrameworkBundle(): void
    {
        $kernel = new IntegrationTestKernel('test', false);

        $bundles = iterator_to_array($kernel->registerBundles());

        $this->assertInstanceOf(FrameworkBundle::class, $bundles[0]);
    }

    public function test_registerBundles_includesAppendBundles(): void
    {
        $appendBundles = [DoctrineBundle::class => ['all' => true]];
        $kernel = new IntegrationTestKernel('test', false, $appendBundles);

        $bundles = iterator_to_array($kernel->registerBundles());

        $bundleClasses = array_map(fn($bundle) => get_class($bundle), $bundles);
        $this->assertContains(FrameworkBundle::class, $bundleClasses);
        $this->assertContains(DoctrineBundle::class, $bundleClasses);
    }

    public function test_getCacheDir_includesHashInPath(): void
    {
        $kernel = new IntegrationTestKernel('test', false);

        $cacheDir = $kernel->getCacheDir();

        $this->assertStringContainsString(sys_get_temp_dir(), $cacheDir);
        $this->assertStringContainsString('/var/cache/test', $cacheDir);
        $this->assertStringContainsString('/', $cacheDir);
    }

    public function test_getLogDir_includesHashInPath(): void
    {
        $kernel = new IntegrationTestKernel('test', false);

        $logDir = $kernel->getLogDir();

        $this->assertStringContainsString(sys_get_temp_dir(), $logDir);
        $this->assertStringContainsString('/var/log', $logDir);
    }

    public function test_getCacheDir_differentForDifferentConfigurations(): void
    {
        $kernel1 = new IntegrationTestKernel('test', false);
        $kernel2 = new IntegrationTestKernel('test', true);
        $kernel3 = new IntegrationTestKernel('prod', false);

        $this->assertNotSame($kernel1->getCacheDir(), $kernel2->getCacheDir());
        $this->assertNotSame($kernel1->getCacheDir(), $kernel3->getCacheDir());
        $this->assertNotSame($kernel2->getCacheDir(), $kernel3->getCacheDir());
    }

    public function test_getLogDir_differentForDifferentConfigurations(): void
    {
        $kernel1 = new IntegrationTestKernel('test', false);
        $kernel2 = new IntegrationTestKernel('test', true);

        $this->assertNotSame($kernel1->getLogDir(), $kernel2->getLogDir());
    }

    public function test_getCacheDir_sameForSameConfiguration(): void
    {
        $kernel1 = new IntegrationTestKernel('test', false);
        $kernel2 = new IntegrationTestKernel('test', false);

        $this->assertSame($kernel1->getCacheDir(), $kernel2->getCacheDir());
    }
}
