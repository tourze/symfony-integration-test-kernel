<?php

namespace Tourze\IntegrationTestKernel;

use Composer\InstalledVersions;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolsException;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Tourze\BundleDependency\ResolveHelper;

/**
 * 测试内核类
 *
 * 用于集成测试的 Symfony 内核实现。
 * 主要功能:
 * - 支持动态添加 Bundle
 * - 支持动态添加实体映射
 * - 自动解析 Bundle 依赖
 * - 提供临时缓存和日志目录
 * - 支持基础框架配置documented class
 */
class IntegrationTestKernel extends BaseKernel
{
    private string $hash;

    /**
     * 构造函数
     *
     * @param string $environment 环境名称,如 'test', 'dev' 等
     * @param bool $debug 是否开启调试模式
     * @param array $appendBundles 要额外加载的 Bundle 配置,格式如:
     *     [
     *         BundleClass::class => ['all' => true],
     *         OtherBundle::class => ['dev' => true]
     *     ]
     * @param array $entityMappings 要额外添加的实体映射配置,格式如:
     *     [
     *         'Tourze\Bundle\Tests\Entity' => __DIR__ . '/../Entity',
     *         'Tourze\Other\Entity' => '/path/to/entity/dir'
     *     ]
     */
    public function __construct(
        string $environment,
        bool $debug,
        private readonly array $appendBundles = [],
        private readonly array $entityMappings = []
    ) {
        parent::__construct($environment, $debug);
        $this->hash = md5(json_encode([
            $environment,
            $debug,
            $this->appendBundles,
            $this->entityMappings,
        ]));
    }

    private function getHash(): string
    {
        return $this->hash;
    }

    public function registerBundles(): iterable
    {
        $baseBundles = array_merge([
            FrameworkBundle::class => ['all' => true],
        ], $this->appendBundles);

        foreach (ResolveHelper::resolveBundleDependencies($baseBundles) as $bundle => $env) {
            yield new $bundle();
        }
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . "/{$this->getHash()}/var/cache/" . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . "/{$this->getHash()}/var/log";
    }

    protected function configureContainer(ContainerBuilder $container): void
    {
        // 基础框架
        if ($container->hasExtension('framework')) {
            $container->prependExtensionConfig('framework', [
                'secret' => 'TEST_SECRET',
                'test' => true,
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => [
                    'log' => true,
                ],
            ]);
            if (InstalledVersions::isInstalled('symfony/uid')) {
                $container->prependExtensionConfig('framework', [
                    'uid' => [
                        'default_uuid_version' => 7,
                        'time_based_uuid_version' => 7,
                    ],
                ]);
            }
            if (InstalledVersions::isInstalled('symfony/validator')) {
                $container->prependExtensionConfig('framework', [
                    'validation' => [
                        'email_validation_mode' => 'html5',
                    ],
                ]);
            }
        }

        // 部分模块，是需要强制开启路由能力的
        if ($container->hasExtension('easy_admin') || $container->hasExtension('routing_auto_loader') || $container->hasExtension('security')) {
            $container->prependExtensionConfig('framework', [
                'router' => [
                    'resource' => __DIR__ . '/../config/routes.yaml',
                    'type' => 'yaml',
                ],
            ]);
        }

        if ($container->hasExtension('security')) {
            $container->prependExtensionConfig('security', [
                //'enable_authenticator_manager' => true,
                'password_hashers' => [
                    InMemoryUser::class => 'auto',
                ],
                'providers' => [
                    'users_in_memory' => [
                        'memory' => [],
                    ],
                ],
                'firewalls' => [
                    'dev' => [
                        'pattern' => '^/(_(profiler|wdt)|css|images|js)/',
                        'security' => false,
                    ],
                    'main' => [
                        'lazy' => true,
                        'provider' => 'users_in_memory',
                    ],
                ],
            ]);
        }

        if ($container->hasExtension('doctrine')) {
            $doctrineConfig = [
                'dbal' => [
                    'driver' => 'pdo_sqlite',
                    'url' => 'sqlite:///:memory:',
                ],
                'orm' => [
                    'auto_generate_proxy_classes' => true,
                    'controller_resolver' => [
                        'auto_mapping' => false,
                    ],
                    'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                    'auto_mapping' => true,
                ]
            ];

            // 添加自定义实体映射配置
            if (!empty($this->entityMappings)) {
                $mappings = [];
                foreach ($this->entityMappings as $namespace => $path) {
                    $mappings[$namespace] = [
                        'type' => 'attribute',
                        'dir' => $path,
                        'prefix' => $namespace,
                        'is_bundle' => false,
                    ];
                }
                $doctrineConfig['orm']['mappings'] = $mappings;
            }

            $container->prependExtensionConfig('doctrine', $doctrineConfig);
        }
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            $this->configureContainer($container);
        });
    }

    public function boot(): void
    {
        // 清空 cache 目录
        @rmdir($this->getCacheDir());

        parent::boot();
        if (isset($this->getContainer()->getParameter('kernel.bundles')['DoctrineBundle'])) {
            try {
                $this->createDatabaseSchema();
            } catch (ToolsException $exception) {
                // 吃掉异常
            }
        }
    }

    private function createDatabaseSchema(): void
    {
        $entityManager = $this->getContainer()->get('doctrine.orm.entity_manager');
        assert($entityManager instanceof EntityManagerInterface);
        $schemaTool = new SchemaTool($entityManager);

        // 获取所有实体的元数据
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        // 创建数据库表
        $schemaTool->createSchema($metadata);
    }
}
