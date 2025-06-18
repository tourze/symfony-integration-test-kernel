<?php

namespace Tourze\IntegrationTestKernel\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

/**
 * 测试 IntegrationTestKernel 的回调功能
 *
 * 主要测试 containerConfigurator 和 containerBuilder 回调
 */
class IntegrationTestKernelCallbackTest extends TestCase
{
    public function test_containerConfigurator_callback_is_called(): void
    {
        $configuratorCalled = false;
        $containerConfigurator = function (ContainerBuilder $container, IntegrationTestKernel $kernel) use (&$configuratorCalled) {
            $configuratorCalled = true;
            $this->assertInstanceOf(ContainerBuilder::class, $container);
            $this->assertInstanceOf(IntegrationTestKernel::class, $kernel);
            
            // 添加自定义配置
            $container->setParameter('test.custom_parameter', 'custom_value');
        };

        $kernel = new IntegrationTestKernel(
            'test_' . uniqid(), // 使用唯一环境避免缓存
            false,
            [],
            [],
            $containerConfigurator
        );

        $kernel->boot();
        
        $this->assertTrue($configuratorCalled, 'Container configurator callback should be called');
        $this->assertSame('custom_value', $kernel->getContainer()->getParameter('test.custom_parameter'));
    }

    public function test_containerBuilder_callback_is_called(): void
    {
        $builderCalled = false;
        $containerBuilder = function (ContainerBuilder $container, IntegrationTestKernel $kernel) use (&$builderCalled) {
            $builderCalled = true;
            $this->assertInstanceOf(ContainerBuilder::class, $container);
            $this->assertInstanceOf(IntegrationTestKernel::class, $kernel);
            
            // 添加自定义服务定义
            $definition = new Definition(\stdClass::class);
            $definition->setPublic(true);
            $container->setDefinition('test.custom_service', $definition);
        };

        $kernel = new IntegrationTestKernel(
            'test_' . uniqid(),
            false,
            [],
            [],
            null,
            $containerBuilder
        );

        $kernel->boot();
        
        $this->assertTrue($builderCalled, 'Container builder callback should be called');
        $this->assertTrue($kernel->getContainer()->has('test.custom_service'));
        $this->assertInstanceOf(\stdClass::class, $kernel->getContainer()->get('test.custom_service'));
    }

    public function test_both_callbacks_are_called(): void
    {
        $configuratorCalled = false;
        $builderCalled = false;
        
        $containerConfigurator = function (ContainerBuilder $container) use (&$configuratorCalled) {
            $configuratorCalled = true;
            $container->setParameter('test.from_configurator', 'configurator_value');
        };

        $containerBuilder = function (ContainerBuilder $container) use (&$builderCalled) {
            $builderCalled = true;
            $container->setParameter('test.from_builder', 'builder_value');
        };

        $kernel = new IntegrationTestKernel(
            'test_' . uniqid(),
            false,
            [],
            [],
            $containerConfigurator,
            $containerBuilder
        );

        $kernel->boot();
        
        $this->assertTrue($configuratorCalled, 'Configurator should be called');
        $this->assertTrue($builderCalled, 'Builder should be called');
        $this->assertSame('configurator_value', $kernel->getContainer()->getParameter('test.from_configurator'));
        $this->assertSame('builder_value', $kernel->getContainer()->getParameter('test.from_builder'));
    }

    public function test_callbacks_can_access_kernel_properties(): void
    {
        $environment = null;
        $debug = null;
        
        $containerConfigurator = function (ContainerBuilder $container, IntegrationTestKernel $kernel) use (&$environment, &$debug) {
            $environment = $kernel->getEnvironment();
            $debug = $kernel->isDebug();
        };

        $kernel = new IntegrationTestKernel(
            'production_' . uniqid(),
            true,
            [],
            [],
            $containerConfigurator
        );

        $kernel->boot();
        
        $this->assertStringStartsWith('production_', $environment);
        $this->assertTrue($debug);
    }

    public function test_null_callbacks_do_not_cause_errors(): void
    {
        $kernel = new IntegrationTestKernel(
            'test_' . uniqid(),
            false,
            [],
            [],
            null,
            null
        );

        // 不应该抛出任何异常
        $kernel->boot();
        
        // 验证内核正常启动
        $this->assertNotNull($kernel->getContainer());
        $this->assertTrue($kernel->getContainer()->has('kernel'));
    }

    public function test_callbacks_affect_cache_hash(): void
    {
        // 没有回调的内核
        $kernel1 = new IntegrationTestKernel('test', false);
        
        // 有 configurator 回调的内核
        $kernel2 = new IntegrationTestKernel(
            'test',
            false,
            [],
            [],
            function () {}
        );
        
        // 有 builder 回调的内核
        $kernel3 = new IntegrationTestKernel(
            'test',
            false,
            [],
            [],
            null,
            function () {}
        );
        
        // 有两个回调的内核
        $kernel4 = new IntegrationTestKernel(
            'test',
            false,
            [],
            [],
            function () {},
            function () {}
        );

        // 缓存目录应该不同，因为 hash 包含了回调的存在与否
        $this->assertNotSame($kernel1->getCacheDir(), $kernel2->getCacheDir());
        $this->assertNotSame($kernel1->getCacheDir(), $kernel3->getCacheDir());
        $this->assertNotSame($kernel1->getCacheDir(), $kernel4->getCacheDir());
        $this->assertNotSame($kernel2->getCacheDir(), $kernel3->getCacheDir());
        $this->assertNotSame($kernel2->getCacheDir(), $kernel4->getCacheDir());
        $this->assertNotSame($kernel3->getCacheDir(), $kernel4->getCacheDir());
    }

    public function test_containerConfigurator_can_modify_extension_config(): void
    {
        $containerConfigurator = function (ContainerBuilder $container) {
            // 添加自定义参数
            $container->setParameter('test.custom_locale', 'zh_CN');
            $container->setParameter('test.custom_config', [
                'enabled' => true,
                'value' => 'test_value',
            ]);
        };

        $kernel = new IntegrationTestKernel(
            'test_' . uniqid(),
            false,
            [],
            [],
            $containerConfigurator
        );

        $kernel->boot();
        
        // 验证配置生效
        $container = $kernel->getContainer();
        $this->assertTrue($container->hasParameter('test.custom_locale'));
        $this->assertSame('zh_CN', $container->getParameter('test.custom_locale'));
        $this->assertTrue($container->hasParameter('test.custom_config'));
        $this->assertSame(['enabled' => true, 'value' => 'test_value'], $container->getParameter('test.custom_config'));
    }

    public function test_containerBuilder_can_modify_container(): void
    {
        $containerBuilder = function (ContainerBuilder $container) {
            // 直接设置参数，不使用 CompilerPass
            $container->setParameter('test.builder_param', 'builder_value');
            
            // 添加标签到已存在的服务
            if ($container->hasDefinition('kernel')) {
                $container->getDefinition('kernel')->addTag('test.tagged');
            }
        };

        $kernel = new IntegrationTestKernel(
            'test_' . uniqid(),
            false,
            [],
            [],
            null,
            $containerBuilder
        );

        $kernel->boot();
        
        $this->assertTrue($kernel->getContainer()->hasParameter('test.builder_param'));
        $this->assertSame('builder_value', $kernel->getContainer()->getParameter('test.builder_param'));
    }
}