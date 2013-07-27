<?php
namespace YcheukfMigration;

use Zend\Db\Adapter\Driver\ResultInterface;
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

//        $e->getApplication()->getServiceManager()->get('translator');
        $eventManager = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

		/**
		* 注册eventmanger. 用于响应migration 批量迁移命令. 从本事件中读取需要迁移的dbs config
		*/
        $events = StaticEventManager::getInstance();
		$events->attach("YcheukfMigration\Controller\MigrateController", array('_getDbsFromEvent_media','_getDbsFromEvent_default','_getDbsFromEvent_main','_getDbsFromEvent_report'), function($e) {
			$sTriggerName = $e->getName(); // "Example"
			$aReturn = array();
			$aConfig = $e->getTarget()->getServiceLocator()->get('config');
			
			$dbParams = isset($aConfig['master']) ? $aConfig['master'] : $aConfig['db'];
			 $adapter = new \Zend\Db\Adapter\Adapter($dbParams);
			 $result = $adapter->query('SELECT * FROM `dbConfig`', array());
			$resultSet = new ResultSet;
			$resultSet->initialize($result);
			$sDatabaseName = "";
			foreach ($resultSet as $row) {
				switch($sTriggerName){
					case "_getDbsFromEvent_report":
						$aReturn[$row['customerid']] = array(
							'driver'         => 'Pdo',
							'dsn'            => 'mysql:dbname='.$row['reportMasterDbName'].';host='.$row['reportMasterHost'],
							'hostname'		 => $row['reportMasterHost'],
							'database'		 => $row['reportMasterDbName'],
							'username'		 => $row['reportMasterUserName'],
							'password'		 => $row['reportMasterPassword'],
							'driver_options' => array(
								'SET NAMES \'UTF8\''
							)
						);
						$sDatabaseName = $row['reportMasterDbName'];
						if(is_string($sDatabaseName) && !empty($sDatabaseName)){
							$adapter->query('create database if NOT EXISTS '.$sDatabaseName, array());
						}
						break;
					default:
						$aReturn[$row['customerid']] = array(
							'driver'         => 'Pdo',
							'dsn'            => 'mysql:dbname='.$row['masterDbName'].';host='.$row['masterHost'],
							'hostname'		 => $row['masterHost'],
							'database'		 => $row['masterDbName'],
							'username'		 => $row['masterUserName'],
							'password'		 => $row['masterPassword'],
							'driver_options' => array(
								'SET NAMES \'UTF8\''
							)
						);
						$sDatabaseName = $row['masterDbName'];
						if(is_string($sDatabaseName) && !empty($sDatabaseName)){
							$adapter->query('create database if NOT EXISTS '.$sDatabaseName, array());
						}

						break;
				}
			}
			
			return ($aReturn);
		});	
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
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function getServiceConfig()
    {
        return array(
            'invokables' => array(
				'YcheukfMigration\Controller\MigrateController' => 'YcheukfMigration\Controller\MigrateController',
            ),
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
            'Simple Migrations',

            'migration version' => 'Get last applied migration version',

            'migration list [--all]' => 'List available migrations',
            array('--all', 'Include applied migrations'),

            'migration apply [<version>] [--force]' => 'Execute migration',
            array(
                '--force',
                'Force apply migration even if it\'s older than the last migrated. Works only with <version> explicitly set.'
            ),
            array('--down', 'Force apply down migration. Works only with --force flag set.'),

            'migration generate [--migrationdir=]' => 'Generate new migration skeleton class',

            'migration up [<dbkey>] [--migrationdir=] [--dbsfromevent]' => 'apply all up grade migration. ',

            'migration down [<dbkey>] [--migrationdir=] [--dbsfromevent]' => 'apply all down grade migration',
            array(
                '--dbsfromevent',
                'get dbconfigs from eventmanager "_getDbsFromEvent", and run all of them'
            ),
        );
    }
}
