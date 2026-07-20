<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;


class InvoiceIngestionBundle extends AbstractBundle
{
    protected string $extensionAlias = 'invoice_ingestion';

    /**
     * AbstractBundle::getPath() fait un dirname($file, 2) en supposant que la
     * classe vit dans le src/ propre a un bundle. La notre vit dans
     * src/DependencyInjection/, donc par defaut le chemin remonterait a src/
     * tout entier (l'arbre source de l'appli). On le fixe explicitement.
     */
    public function getPath(): string
    {
        return __DIR__;
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $rootNode = $definition->rootNode();
        if (!$rootNode instanceof ArrayNodeDefinition) {
            throw new \LogicException('Expected the root configuration node to be an array node.');
        }

        $rootNode
            ->children()
                ->arrayNode('clients')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            // `format` nomme une STRATEGIE, pas une extension
                            ->enumNode('format')
                                ->values(['fileExtension', 'csv', 'json'])
                                ->defaultValue('fileExtension')
                            ->end()
                            ->scalarNode('delimiter')
                                ->defaultNull()
                            ->end()
                            ->arrayNode('fields')
                                ->isRequired()
                                ->requiresAtLeastOneElement()
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array<string, mixed> $clients */
        $clients = $config['clients'];

        $builder->setParameter('invoice_ingestion.clients', $clients);
    }
}
