<?php

use Krak\SymfonyMessengerAutoScale\AutoScale;
use Krak\SymfonyMessengerAutoScale\Command;
use Krak\SymfonyMessengerAutoScale\DependencyInjection\BuildSupervisorPoolConfigCompilerPass;
use Krak\SymfonyMessengerAutoScale\PoolControl;
use Krak\SymfonyMessengerAutoScale\PoolControlFactory;
use Krak\SymfonyMessengerAutoScale\ProcessManager;
use Krak\SymfonyMessengerAutoScale\ProcessManagerFactory;
use Krak\SymfonyMessengerAutoScale\RaiseAlerts;
use Krak\SymfonyMessengerAutoScale\Supervisor;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->private()
            ->autowire()
            ->autoconfigure()
            ->bind('$receiversById', service('messenger.receiver_locator'))
            ->bind('$supervisorPoolConfigs', service('krak.messenger_auto_scale.supervisor_pool_configs'));

    $services->set('krak.messenger_auto_scale.supervisor_pool_configs', 'array')
        ->factory([BuildSupervisorPoolConfigCompilerPass::class, 'createSupervisorPoolConfigsFromArray']);

    $services->set('krak.messenger_auto_scale.receiver_to_pool_mapping', 'array')
        ->factory([BuildSupervisorPoolConfigCompilerPass::class, 'createReceiverToPoolMappingFromArray']);

    $services->set('krak.messenger_auto_scale.auto_scale.default', AutoScale::class)
        ->factory([Supervisor::class, 'defaultAutoScale']);

    $services->alias(AutoScale::class, 'krak.messenger_auto_scale.auto_scale.default');

    // Process Manager Factory
    $services->set(ProcessManager\SymfonyMessengerProcessManagerFactory::class);
    $services->alias(ProcessManagerFactory::class, ProcessManager\SymfonyMessengerProcessManagerFactory::class);

    // Pool Control Factory
    $services->set(PoolControl\InMemoryPoolControlFactory::class);

    $services->set(PoolControl\PsrSimpleCachePoolControlFactory::class . '.simple_cache', Psr16Cache::class)
        ->args([service(CacheItemPoolInterface::class)]);

    $services->set(PoolControl\PsrSimpleCachePoolControlFactory::class)
        ->args([service(PoolControl\PsrSimpleCachePoolControlFactory::class . '.simple_cache')]);

    $services->alias(PoolControlFactory::class, PoolControl\PsrSimpleCachePoolControlFactory::class);

    // Raise Alerts
    $services->set(RaiseAlerts\PoolBackedUpRaiseAlerts::class);

    $services->set(RaiseAlerts\ChainRaiseAlerts::class)
        ->autoconfigure(false)
        ->args([tagged_iterator('messenger_auto_scale.raise_alerts')]);

    $services->alias(RaiseAlerts::class, RaiseAlerts\ChainRaiseAlerts::class);

    $services->set(Supervisor::class)
        ->tag('monolog.logger', ['channel' => 'messenger_auto_scale']);

    $services->set(Command\ConsumeCommand::class);
    $services->set(Command\AlertCommand::class);
    $services->set(Command\Pool\PauseCommand::class);
    $services->set(Command\Pool\RestartCommand::class);
    $services->set(Command\Pool\ResumeCommand::class);
    $services->set(Command\Pool\StatusCommand::class);
};
