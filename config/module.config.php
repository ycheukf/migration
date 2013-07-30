<?php

return array(
    'migrations' => array(
        'dir' => dirname(__FILE__) . '/../../../../migrations/default',
        'namespace' => 'YcheukfMigration\Migration',
        'show_log' => true
    ),
    'console' => array(
        'router' => array(
            'routes' => array(
                'migration-version' => array(
                    'type' => 'simple',
                    'options' => array(
                        'route' => 'migration version [--env=] [--dir=]',
                        'defaults' => array(
                            'controller' => 'YcheukfMigration\Controller\Migrate',
                            'action' => 'version'
                        )
                    )
                ),
                'migration-list' => array(
                    'type' => 'simple',
                    'options' => array(
                        'route' => 'migration [--env=] [--all] [--dir=]',
                        'defaults' => array(
                            'controller' => 'YcheukfMigration\Controller\Migrate',
                            'action' => 'list'
                        )
                    )
                ),
                'migration-apply' => array(
                    'type' => 'simple',
                    'options' => array(
                        'route' => 'migration apply [<version>] [--env=] [--force] [--down] [--dir=]',
                        'defaults' => array(
                            'controller' => 'YcheukfMigration\Controller\Migrate',
                            'action' => 'apply'
                        )
                    )
                ),
                'migration-generate' => array(
                    'type' => 'simple',
                    'options' => array(
                        'route' => 'migration generate [--env=] [--dir=]',
                        'defaults' => array(
                            'controller' => 'YcheukfMigration\Controller\Migrate',
                            'action' => 'generateSkeleton'
                        )
                    )
                ),
                'migration-up' => array(//feng 2013/6/13
                    'type' => 'simple',
                    'options' => array(
                        'route' => 'migration up [<dbkey>] [--offset=] [--dir=] [--dbsfromevent]',
                        'defaults' => array(
                            'controller' => 'YcheukfMigration\Controller\Migrate',
                            'action' => 'up'
                        )
                    )
                ),
                'migration-down' => array(//feng 2013/6/13
                    'type' => 'simple',
                    'options' => array(
                        'route' => 'migration down [<dbkey>] [--offset=] [--dir=] [--dbsfromevent]',
                        'defaults' => array(
                            'controller' => 'YcheukfMigration\Controller\Migrate',
                            'action' => 'down'
                        )
                    )
                )
            )
        )
    ),
    'controllers' => array(
        'invokables' => array(
            'YcheukfMigration\Controller\Migrate' => 'YcheukfMigration\Controller\MigrateController'
        ),
    ),
    'view_manager' => array(
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
);
