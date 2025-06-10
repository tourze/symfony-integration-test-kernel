<?php

namespace Tourze\IntegrationTestKernel;

use Composer\InstalledVersions;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Tourze\BundleDependency\ResolveHelper;

class IntegrationTestKernel extends BaseKernel
{
    public function __construct(string $environment, bool $debug, private readonly array $appendBundles = [])
    {
        parent::__construct($environment, $debug);
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
        return sys_get_temp_dir() . '/var/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/var/log';
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
        if ($container->hasExtension('easy_admin') || $container->hasExtension('routing_auto_loader')) {
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
            $container->prependExtensionConfig('doctrine', [
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
            ]);
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
            $this->createDatabaseSchema();
        }
    }

    private function createDatabaseSchema(): void
    {
        $entityManager = $this->getContainer()->get('doctrine.orm.entity_manager');
        $schemaTool = new SchemaTool($entityManager);

        // 获取所有实体的元数据
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        // 创建数据库表
        $schemaTool->createSchema($metadata);
    }
}
