<?php
namespace YcheukfMigration;

use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;
use Zend\EventManager\EventInterface;
use Zend\ModuleManager\Feature\BootstrapListenerInterface;
use Zend\ModuleManager\Feature\ServiceProviderInterface;
use Zend\Mvc\ModuleRouteListener;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Stdlib\Parameters;
use Zend\EventManager\StaticEventManager;
use Zend\Db\Adapter\Adapter;

class Module implements
    AutoloaderProviderInterface,
    ConfigProviderInterface,
    ConsoleUsageProviderInterface,
    ServiceProviderInterface,
    BootstrapListenerInterface
{
    /**
     * @param EventInterface|\Zend\Mvc\MvcEvent $e
     * @return array|void
     */
    public function onBootstrap(EventInterface $e)
    {
        $eventManager = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

		/**
		* attach an event to setDbConfigsFromEvent. 
		  add this code to other module if you need to add some dynamic db config.
		*/
        $events = StaticEventManager::getInstance();
		$events->attach("YcheukfMigration\Controller\MigrateController", array('setDbConfigsFromEvent'), function($e) {
//			var_dump($e->getParams());
			$aReturn = array();
			$aReturn['db'] = array(
				'driver'         => 'Pdo',
				'dsn'            => 'mysql:dbname=zf2test2;host=localhost',
				'username'		 => 'root',
				'password'		 => 'root',
				'driver_options' => array(
					'SET NAMES \'UTF8\''
				)
			);
			$aReturn['db2'] = array(
				'driver'         => 'Pdo',
				'dsn'            => 'mysql:dbname=zf2test3;host=localhost',
				'username'		 => 'root',
				'password'		 => 'root',
				'driver_options' => array(
					'SET NAMES \'UTF8\''
				)
			);
			return ($aReturn);
		});	
//		*/
    }

	public function init(){

	}

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/migration',
                ),
            ),
        );
    }

    public function getServiceConfig()
    {
        return array(
            'factories' => array(
                'YcheukfMigration\Model\MigrationVersionTable' => function (ServiceLocatorInterface $serviceLocator) {
                    /** @var $tableGateway TableGateway */
                    $tableGateway = $serviceLocator->get('YcheukfMigrationVersionTableGateway');
                    $table = new Model\MigrationVersionTable($tableGateway);
                    return $table;
                },
                'YcheukfMigrationVersionTableGateway' => function (ServiceLocatorInterface $serviceLocator) {
                    /** @var $dbAdapter \Zend\Db\Adapter\Adapter */
                    $dbAdapter = $serviceLocator->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\MigrationVersion());
                    return new TableGateway(Library\Migration::MIGRATION_TABLE, $dbAdapter, null, $resultSetPrototype);
                },
            ),
        );
    }

    public function getConsoleUsage(Console $console)
    {
        return array(
            'migration [--all]' ,
			'  List available migrations',

			'migration generate [--dir=]',
			'  Generate new migration ',

			'migration up [<dbkey>] [--dir=] [--dbsfromevent]',
			 '  apply up-grade all migration. ',

			'migration down [<dbkey>] [--dir=] [--dbsfromevent]',
			'  apply down-grade all migration',

			'  ',

            array('--all', 'show all applied migrations'),
            array('<dbkey>', 'the key of db-config-array. mostly is "db"'),
            array('--dir', 'migration dir. default is "default"'),
            array(
                '--dbsfromevent',
                'set db-config-array by event "setDbConfigsFromEvent"'
            ),
        );
    }
}
