<?php

namespace Tourze\IntegrationTestKernel\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;

class IntegrationTestKernelEdgeCasesTest extends TestCase
{
    public function test_constructor_withEmptyAppendBundles(): void
    {
        $kernel = new IntegrationTestKernel('test', false, []);

        $bundles = iterator_to_array($kernel->registerBundles());

        // 应该至少包含 FrameworkBundle
        $this->assertInstanceOf(FrameworkBundle::class, $bundles[0]);
        $this->assertCount(1, $bundles);
    }

    public function test_constructor_withEmptyEntityMappings(): void
    {
        $kernel = new IntegrationTestKernel('test', false, [], []);

        $this->assertSame('test', $kernel->getEnvironment());
        $this->assertFalse($kernel->isDebug());
    }

    public function test_constructor_withNullValues(): void
    {
        // 测试构造函数参数的默认值
        $kernel = new IntegrationTestKernel('dev', true);

        $this->assertSame('dev', $kernel->getEnvironment());
        $this->assertTrue($kernel->isDebug());
    }

    public function test_constructor_withSpecialCharactersInEnvironment(): void
    {
        $kernel = new IntegrationTestKernel('test-special_env.1', false);

        $this->assertSame('test-special_env.1', $kernel->getEnvironment());

        // 确保缓存目录路径是有效的
        $cacheDir = $kernel->getCacheDir();
        $this->assertNotEmpty($cacheDir);
        $this->assertStringContainsString('test-special_env.1', $cacheDir);
    }

    public function test_hash_generatesDifferentHashForDifferentEntityMappings(): void
    {
        $kernel1 = new IntegrationTestKernel('test', false, [], ['App\\Entity' => '/path1']);
        $kernel2 = new IntegrationTestKernel('test', false, [], ['App\\Entity' => '/path2']);
        $kernel3 = new IntegrationTestKernel('test', false, [], ['Other\\Entity' => '/path1']);

        $this->assertNotSame($kernel1->getCacheDir(), $kernel2->getCacheDir());
        $this->assertNotSame($kernel1->getCacheDir(), $kernel3->getCacheDir());
        $this->assertNotSame($kernel2->getCacheDir(), $kernel3->getCacheDir());
    }

    public function test_hash_generatesDifferentHashForDifferentAppendBundles(): void
    {
        $kernel1 = new IntegrationTestKernel('test', false, ['Bundle1' => ['all' => true]]);
        $kernel2 = new IntegrationTestKernel('test', false, ['Bundle2' => ['all' => true]]);

        $this->assertNotSame($kernel1->getCacheDir(), $kernel2->getCacheDir());
    }

    public function test_registerBundles_withInvalidBundleClass(): void
    {
        // 测试当 appendBundles 包含无效的 bundle 类时的行为
        $appendBundles = ['NonExistentBundle' => ['all' => true]];
        $kernel = new IntegrationTestKernel('test', false, $appendBundles);

        // 这个测试可能会抛出异常，取决于 ResolveHelper 的实现
        $this->expectException(\Error::class);
        iterator_to_array($kernel->registerBundles());
    }

    public function test_getCacheDir_withVeryLongPath(): void
    {
        $longEnvironment = str_repeat('a', 100);
        $longBundleName = str_repeat('Bundle', 20);
        $appendBundles = [$longBundleName => ['all' => true]];

        $kernel = new IntegrationTestKernel($longEnvironment, false, $appendBundles);

        $cacheDir = $kernel->getCacheDir();

        // 验证路径仍然有效
        $this->assertNotEmpty($cacheDir);
        $this->assertStringContainsString(sys_get_temp_dir(), $cacheDir);
    }

    public function test_getLogDir_withVeryLongPath(): void
    {
        $longEnvironment = str_repeat('b', 100);
        $kernel = new IntegrationTestKernel($longEnvironment, false);

        $logDir = $kernel->getLogDir();

        $this->assertNotEmpty($logDir);
        $this->assertStringContainsString(sys_get_temp_dir(), $logDir);
        $this->assertStringContainsString('/var/log', $logDir);
    }

    public function test_kernel_withComplexEntityMappings(): void
    {
        $entityMappings = [
            'App\\Entity\\User' => '/very/long/path/to/user/entities',
            'App\\Entity\\Product' => '/another/very/long/path/to/product/entities',
            'ThirdParty\\Vendor\\Entity' => '/third/party/path',
            '' => '/empty/namespace/path',  // 测试空命名空间
        ];

        $kernel = new IntegrationTestKernel('test', false, [], $entityMappings);

        // 验证构造成功
        $this->assertSame('test', $kernel->getEnvironment());

        // 验证哈希包含了所有映射信息
        $cacheDir = $kernel->getCacheDir();
        $this->assertNotEmpty($cacheDir);
    }

    public function test_kernel_debugModeAffectsHash(): void
    {
        $kernel1 = new IntegrationTestKernel('prod', true);   // debug=true
        $kernel2 = new IntegrationTestKernel('prod', false);  // debug=false

        $this->assertNotSame($kernel1->getCacheDir(), $kernel2->getCacheDir());
        $this->assertNotSame($kernel1->getLogDir(), $kernel2->getLogDir());
    }

    public function test_kernel_withUnicodeCharacters(): void
    {
        // 测试包含 Unicode 字符的配置
        $entityMappings = ['测试\\实体' => '/测试/路径'];

        $kernel = new IntegrationTestKernel('测试环境', false, [], $entityMappings);

        $this->assertSame('测试环境', $kernel->getEnvironment());

        // 验证哈希生成不会出错
        $cacheDir = $kernel->getCacheDir();
        $this->assertNotEmpty($cacheDir);
    }

    public function test_kernel_withArrayParametersOrder(): void
    {
        // 测试相同数据但不同顺序的数组是否产生不同的哈希
        $mappings1 = ['A' => 'path1', 'B' => 'path2'];
        $mappings2 = ['B' => 'path2', 'A' => 'path1'];

        $kernel1 = new IntegrationTestKernel('test', false, [], $mappings1);
        $kernel2 = new IntegrationTestKernel('test', false, [], $mappings2);

        // JSON 编码应该保持数组顺序，所以哈希应该不同
        $this->assertNotSame($kernel1->getCacheDir(), $kernel2->getCacheDir());
    }
}
