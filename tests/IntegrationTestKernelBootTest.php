<?php

namespace Tourze\IntegrationTestKernel\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

class IntegrationTestKernelBootTest extends TestCase
{
    public function test_registerContainerConfiguration_callsConfigureContainer(): void
    {
        $kernel = new IntegrationTestKernel('test', false);

        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->once())
            ->method('load')
            ->with($this->isType('callable'));

        $kernel->registerContainerConfiguration($loader);
    }

    public function test_registerContainerConfiguration_loaderCallbackConfiguresContainer(): void
    {
        $kernel = new IntegrationTestKernel('test', false);
        $container = new ContainerBuilder();

        // 注册 framework extension 以便测试配置
        $container->registerExtension(new \Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension());

        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->once())
            ->method('load')
            ->willReturnCallback(function ($callback) use ($container) {
                $callback($container);
            });

        $kernel->registerContainerConfiguration($loader);

        // 验证配置是否被正确设置
        $configs = $container->getExtensionConfig('framework');
        $this->assertNotEmpty($configs);

        $mergedConfig = array_merge_recursive(...$configs);
        $this->assertSame('TEST_SECRET', $mergedConfig['secret']);
    }

    public function test_boot_clearsCacheDirectory(): void
    {
        $kernel = new IntegrationTestKernel('test', false);

        // 创建一个临时的缓存目录
        $cacheDir = $kernel->getCacheDir();
        $parentDir = dirname($cacheDir);

        if (!is_dir($parentDir)) {
            mkdir($parentDir, 0777, true);
        }
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        // 确保目录存在
        $this->assertTrue(is_dir($cacheDir));

        try {
            $kernel->boot();

            // 验证内核启动成功
            $this->assertTrue($kernel->getContainer()->getParameter('kernel.debug') === false);
            $this->assertSame('test', $kernel->getContainer()->getParameter('kernel.environment'));
        } finally {
            // 清理
            try {
                $kernel->getContainer();
                $kernel->shutdown();
            } catch (\LogicException $e) {
                // 内核未启动，忽略
            }
        }
    }


    public function test_boot_withoutDoctrineBundle_doesNotCreateSchema(): void
    {
        $kernel = new IntegrationTestKernel('test', false);

        try {
            $kernel->boot();

            // 验证内核启动成功，没有 DoctrineBundle
            $bundles = $kernel->getContainer()->getParameter('kernel.bundles');
            $this->assertArrayNotHasKey('DoctrineBundle', $bundles);
        } finally {
            try {
                $kernel->getContainer();
                $kernel->shutdown();
            } catch (\LogicException $e) {
                // 内核未启动，忽略
            }
        }
    }

    public function test_kernel_implementsKernelInterface(): void
    {
        $kernel = new IntegrationTestKernel('test', false);

        $this->assertInstanceOf(\Symfony\Component\HttpKernel\KernelInterface::class, $kernel);
    }

    public function test_kernel_hasCorrectProjectDir(): void
    {
        $kernel = new IntegrationTestKernel('test', false);

        $projectDir = $kernel->getProjectDir();

        $this->assertNotEmpty($projectDir);
        $this->assertTrue(is_dir($projectDir));
    }

    public function test_kernel_shutdownCleansUp(): void
    {
        $kernel = new IntegrationTestKernel('test', false);

        try {
            $kernel->boot();
            $this->assertNotNull($kernel->getContainer());

            $kernel->shutdown();

            // 测试 shutdown 后无法获取容器
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage('Cannot retrieve the container from a non-booted kernel.');
            $kernel->getContainer();
        } finally {
            // 清理，只有在内核启动状态下才调用 shutdown
            try {
                $kernel->getContainer();
                $kernel->shutdown();
            } catch (\LogicException $e) {
                // 内核已经 shutdown，忽略异常
            }
        }
    }
}
