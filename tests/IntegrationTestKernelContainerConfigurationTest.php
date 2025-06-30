<?php

namespace Tourze\IntegrationTestKernel\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

class IntegrationTestKernelContainerConfigurationTest extends TestCase
{
    private ContainerBuilder $container;
    private IntegrationTestKernel $kernel;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->kernel = new IntegrationTestKernel('test', false);
    }

    public function test_configureContainer_setsFrameworkBasicConfiguration(): void
    {
        // 注册 framework extension
        $this->container->registerExtension(new \Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension());

        // 调用 configureContainer 通过反射
        $reflection = new \ReflectionClass($this->kernel);
        $method = $reflection->getMethod('configureContainer');
        $method->setAccessible(true);
        $method->invoke($this->kernel, $this->container);

        $configs = $this->container->getExtensionConfig('framework');

        $this->assertNotEmpty($configs);
        $mergedConfig = array_merge_recursive(...$configs);

        $this->assertSame('TEST_SECRET', $mergedConfig['secret']);
        $this->assertTrue($mergedConfig['test']);
        $this->assertFalse($mergedConfig['http_method_override']);
        $this->assertTrue($mergedConfig['handle_all_throwables']);
        $this->assertTrue($mergedConfig['php_errors']['log']);
    }


    public function test_configureContainer_setsRouterConfigurationWhenEasyAdminExtensionExists(): void
    {
        // 注册 framework extension
        $this->container->registerExtension(new \Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension());

        // 模拟 easy_admin extension 存在
        $mockExtension = $this->createMock(\Symfony\Component\DependencyInjection\Extension\ExtensionInterface::class);
        $mockExtension->method('getAlias')->willReturn('easy_admin');
        $this->container->registerExtension($mockExtension);

        // 调用 configureContainer
        $reflection = new \ReflectionClass($this->kernel);
        $method = $reflection->getMethod('configureContainer');
        $method->setAccessible(true);
        $method->invoke($this->kernel, $this->container);

        $configs = $this->container->getExtensionConfig('framework');
        $mergedConfig = array_merge_recursive(...$configs);

        $this->assertArrayHasKey('router', $mergedConfig);
        $this->assertStringEndsWith('/config/routes.yaml', $mergedConfig['router']['resource']);
        $this->assertSame('yaml', $mergedConfig['router']['type']);
    }

    public function test_configureContainer_setsDoctrineConfiguration(): void
    {
        // 注册 doctrine extension
        $this->container->registerExtension(new \Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension());

        // 调用 configureContainer
        $reflection = new \ReflectionClass($this->kernel);
        $method = $reflection->getMethod('configureContainer');
        $method->setAccessible(true);
        $method->invoke($this->kernel, $this->container);

        $configs = $this->container->getExtensionConfig('doctrine');

        $this->assertNotEmpty($configs);
        $mergedConfig = array_merge_recursive(...$configs);

        $this->assertArrayHasKey('dbal', $mergedConfig);
        $this->assertArrayHasKey('orm', $mergedConfig);

        $this->assertSame('pdo_sqlite', $mergedConfig['dbal']['driver']);
        $this->assertSame('sqlite:///:memory:', $mergedConfig['dbal']['url']);

        $this->assertTrue($mergedConfig['orm']['auto_generate_proxy_classes']);
        $this->assertFalse($mergedConfig['orm']['controller_resolver']['auto_mapping']);
        $this->assertSame('doctrine.orm.naming_strategy.underscore_number_aware', $mergedConfig['orm']['naming_strategy']);
        $this->assertTrue($mergedConfig['orm']['auto_mapping']);
    }

    public function test_configureContainer_setsDoctrineConfigurationWithEntityMappings(): void
    {
        $entityMappings = [
            'App\\Entity' => '/path/to/entities',
            'Test\\Entity' => '/path/to/test/entities'
        ];

        $kernel = new IntegrationTestKernel('test', false, [], $entityMappings);

        // 注册 doctrine extension
        $this->container->registerExtension(new \Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension());

        // 调用 configureContainer
        $reflection = new \ReflectionClass($kernel);
        $method = $reflection->getMethod('configureContainer');
        $method->setAccessible(true);
        $method->invoke($kernel, $this->container);

        $configs = $this->container->getExtensionConfig('doctrine');
        $mergedConfig = array_merge_recursive(...$configs);

        $this->assertArrayHasKey('mappings', $mergedConfig['orm']);

        $mappings = $mergedConfig['orm']['mappings'];
        $this->assertArrayHasKey('App\\Entity', $mappings);
        $this->assertArrayHasKey('Test\\Entity', $mappings);

        $this->assertSame('attribute', $mappings['App\\Entity']['type']);
        $this->assertSame('/path/to/entities', $mappings['App\\Entity']['dir']);
        $this->assertSame('App\\Entity', $mappings['App\\Entity']['prefix']);
        $this->assertFalse($mappings['App\\Entity']['is_bundle']);
    }

    public function test_configureContainer_doesNotSetFrameworkConfigWhenExtensionNotRegistered(): void
    {
        // 不注册任何 extension

        // 调用 configureContainer
        $reflection = new \ReflectionClass($this->kernel);
        $method = $reflection->getMethod('configureContainer');
        $method->setAccessible(true);
        $method->invoke($this->kernel, $this->container);

        $configs = $this->container->getExtensionConfig('framework');

        $this->assertEmpty($configs);
    }
}
