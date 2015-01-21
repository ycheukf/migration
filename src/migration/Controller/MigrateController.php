<?php
namespace YcheukfMigration\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;
use Zend\Console\Request as ConsoleRequest;
use YcheukfMigration\Library\Migration;
use YcheukfMigration\Library\MigrationException;
use YcheukfMigration\Library\MigrationSkeletonGenerator;
use YcheukfMigration\Library\OutputWriter;

/**
 * Migration commands controller
 */
class MigrateController extends AbstractActionController
{
    /**
     * @var \YcheukfMigration\Library\Migration
     */
    protected $aDbsConfig;
    protected $migration;
    const MSG_MIGRATIONS_APPLIED = "Migrations applied!\n";
    const DEFAULT_DB_KEY = "db";

    public function onDispatch(MvcEvent $e)
    {
        if (!$this->getRequest() instanceof ConsoleRequest) {
            throw new \RuntimeException('You can only use this action from a console!');
        }
        return parent::onDispatch($e);
    }

    /**
     * Overridden only for PHPDoc return value for IDE code helpers
     *
     * @return ConsoleRequest
     */
    public function getRequest()
    {
        return parent::getRequest();
    }

    /**
     * Get current migration version
     *
     * @return int
     */
    public function versionAction()
    {
        return sprintf("Current version %s\n", $this->getMigration()->getCurrentVersion());
    }
    /**
     * set migration path
     * @author feng
     * @return string
     */
    public function getMigrationConfig()
    {
		$sMigrationdir = $this->getRequest()->getParam('dir', null);
		$config = $this->getServiceLocator()->get('configuration');
		$config['migrations']['dir'] = !is_null($sMigrationdir) ? str_replace("/default", "/".$sMigrationdir, $config['migrations']['dir']) : $config['migrations']['dir'];
		return $config;
    }

    /**
     * List migrations - not applied by default, all with 'all' flag.
     *
     * @return string
     */
    public function listAction()
    {
        $migrations = $this->getMigration()->getMigrationClasses($this->getRequest()->getParam('all'));
        $list = array();
        foreach ($migrations as $m) {
            $list[] = sprintf("%s %s - %s", $m['applied'] ? '-' : '+', $m['version'], $m['description']);
        }
        return (empty($list) ? 'No migrations available.' : implode("\n", $list)) . "\n";
    }

    /**
     * Apply migration
     */
    public function applyAction()
    {
        $version = $this->getRequest()->getParam('version');
        $migrations = $this->getMigration()->getMigrationClasses();
        $currentMigrationVersion = $this->getMigration()->getCurrentVersion();
        $force = $this->getRequest()->getParam('force');

        if (is_null($version) && $force) {
            return "Can't force migration apply without migration version explicitly set.";
        }
        if (!$force && is_null($version) && $currentMigrationVersion >= $this->getMigration()->getMaxMigrationVersion($migrations)) {
            return "";
        }

        try {
            $this->getMigration()->migrate($version, $force, $this->getRequest()->getParam('down'));
            return self::MSG_MIGRATIONS_APPLIED;
        } catch (MigrationException $e) {
            return "YcheukfMigration\\Library\\MigrationException\n" . $e->getMessage() . "\n";
        }
    }

    /**
     * Generate new migration skeleton class
     */
    public function generateSkeletonAction()
    {
        $config = $this->getMigrationConfig();

        $generator = new MigrationSkeletonGenerator($config['migrations']['dir'], $config['migrations']['namespace']);
        $classPath = $generator->generate();

        return sprintf("Generated skeleton class @ %s\n", realpath($classPath));
    }

    /**
     * @return Migration
     */
    protected function getMigration()
    {
        if (!$this->migration) {
            /** @var $adapter \Zend\Db\Adapter\Adapter */
            $adapter = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
            $config = $this->getMigrationConfig();

            $output = null;
            if ($config['migrations']['show_log']) {
                $console = $this->getServiceLocator()->get('console');
                $output = new OutputWriter(function ($message) use ($console) {
                    $console->write($message . "\n");
                });
            }

            /** @var $migrationVersionTable \YcheukfMigration\Model\MigrationVersionTable */
            $migrationVersionTable = $this->getServiceLocator()->get('YcheukfMigration\Model\MigrationVersionTable');

            $this->migration = new Migration($adapter, $config['migrations'], $migrationVersionTable, $output, $this->getServiceLocator());
        }
        return $this->migration;
    }
    /**
     * up migrations - applied all update
     *
	 * @author feng
     * @return string
     */
    public function _getMigrationCommandByDbKey($sDbKey){
		$aConsoleReq = array();
		foreach($this->getRequest()->getParams() as $k=>$v){
			if(is_int($k)){
				$aConsoleReq[] = $v;
				if(in_array($v, array('up', 'down')))$aConsoleReq[] = $sDbKey;
			}
		}
//		$sCommand = "php ".BASE_INDEX_PATH."/".$this->getRequest()->getScriptName()." ".join(" ", $aConsoleReq);
		$sCommand = "php ".BASE_INDEX_PATH."/index.php ".join(" ", $aConsoleReq);
		$this->_debug($sCommand, 'a', 'migration db key');
		return ($sCommand);
	}

    public function checkParam(){
//		if($this->getRequest()->getParam('dbkey', null) == null)
//			throw new MigrationException('missin param <dbkey>');
	}

	public function _debug($a, $b){
		if(class_exists('\YcheukfDebug\Model\Debug'))
			\YcheukfDebug\Model\Debug::dump($a, $b);
	}
    /**
     * up migrations - applied all update
     *
	 * @author feng
     * @return string
     */
    public function upAction()
    {
      try {
			$this->checkParam();
			$this->setDbConfigsFromEvent();
			if(count($this->aDbsConfig) == 1){//single migration
				foreach($this->aDbsConfig as $sKey => $aConfigTmp){
					$this->_echomemo($aConfigTmp);
					$this->_setDbAdapter($aConfigTmp);
					while($sMsg = $this->applyAction()){
						if(self::MSG_MIGRATIONS_APPLIED != $sMsg){
							echo $sMsg;
							break;
						}
					}
				}
//				return "\nDONE\n\n";
			}else{//mult migration
				foreach($this->aDbsConfig as $sKey => $aConfigTmp){
					$sTmp = $this->_getMigrationCommandByDbKey($sKey);
					$this->_echomemo($sTmp);
					system($sTmp);
				}
			}
        } catch (MigrationException $e) {
            return $this->_echofaild($e->getMessage());
        }
    }
    private function _echofaild($sMsg){
		$this->_debug($sMsg, 'migration faild');
		return "====errors:====\n" . $sMsg . "\n\nFAILD!\n\n";
	}
    private function _echomemo($aConfigTmp){
		$this->_debug(json_encode($aConfigTmp), 'migration memo');
	}
	
    /**
     * up migrations - applied all downgrade
     *
	 * @author feng
     * @return string
     */
    public function downAction()
    {
		try{
			$this->checkParam();
			$this->setDbConfigsFromEvent();

			if(count($this->aDbsConfig) == 1){//single migration
				foreach($this->aDbsConfig as $sKey => $aConfigTmp){
					$this->_echomemo($aConfigTmp);
					$this->_setDbAdapter($aConfigTmp);
					$migrations = $this->getMigration()->getMigrationClasses();

					$oParameters = $this->getRequest()->getParams();
					$oParameters->set('force', true);
					$oParameters->set('down', true);
					while(1){
						$sVersion = $this->getMigration()->getCurrentVersion($migrations);
						if(empty($sVersion))break;
						$oParameters->set('version', $sVersion);
						$this->getRequest()->setParams($oParameters);
						$sMsg = $this->applyAction();

						if(self::MSG_MIGRATIONS_APPLIED != $sMsg){
							echo $sMsg;
							break;
						}
					}
				}
//				return "\nDONE\n\n";
			}else{//mult migration
				foreach($this->aDbsConfig as $sKey => $aConfigTmp){
					$sTmp = $this->_getMigrationCommandByDbKey($sKey);
					$this->_echomemo($sTmp);
					system($sTmp);
				}
			}
        } catch (MigrationException $e) {
            return $this->_echofaild($e->getMessage());
        }
	}
    /**
     * @author feng
     * @return Migration
     */
    protected function setDbConfigsFromEvent()
    {
		$aReturn = array();
		$sDirName = $this->getRequest()->getParam('dir', "default");
		$bDbsfromevent = $this->getRequest()->getParam('dbsfromevent');
		if($bDbsfromevent){
			$aTriggerRes = $this->getEventManager()->triggerUntil(__FUNCTION__, $this, array($this->getRequest()), function($v) {
					return ($v instanceof Response);
			});
			$aReturn = $aTriggerRes->last();
//			var_dump($aReturn);
			if(!is_null($aReturn)){
				$sDb = $sDb = $this->getRequest()->getParam('dbkey');
				if(!is_null($sDb)){
					if(isset($aReturn[$sDb]))
						$aReturn = array($sDb=>$aReturn[$sDb]);
					else
						throw new MigrationException('Unknown param <dbkey>:'. $sDb." from dbsarray:".json_encode($aReturn));
				}
			}
			else
				throw new MigrationException('can not trigger any event:'.__FUNCTION__);
		}else{
			$sDb = $this->getRequest()->getParam('dbkey', self::DEFAULT_DB_KEY);
			$aConfig = $this->getServiceLocator()->get('Config');
			if(isset($aConfig[$sDb]))
				$aReturn[$sDb] = $aConfig[$sDb];
			else
				throw new MigrationException('Unknown param <dbkey>:'. $sDb);
	
		}
		$this->aDbsConfig = $aReturn;
    }

    /**
	 * send a param 'aMigrationsDbConfig' to Zend\Db\Adapter\Adapter for setting dbconfig
     * @author feng
     * @return Migration
     */
    protected function _setDbAdapter($aMigrationsDbConfig)
    {
		$oParameters = $this->getRequest()->getParams();
		$oParameters->set('aMigrationsDbConfig', $aMigrationsDbConfig);
		$this->getRequest()->setParams($oParameters);
		$this->getServiceLocator()->setAllowOverride(true);
		$this->getServiceLocator()->setFactory('Zend\Db\Adapter\Adapter', function($sm)use($aMigrationsDbConfig){
//			$aConfig['master'] = $oRe->getParam('aMigrationsDbConfig');
			$aConfig['master'] = $aMigrationsDbConfig;
			$adapter = new \BjyProfiler\Db\Adapter\ProfilingAdapter(array(
				'driver'    => 'pdo',
				'dsn'       => $aConfig['master']['dsn'],
				'username'  => $aConfig['master']['username'],
				'password'  => $aConfig['master']['password'],
				'driver_options' => $aConfig['master']['driver_options'],
			));

			$adapter->setProfiler(new \BjyProfiler\Db\Profiler\Profiler);
			$adapter->injectProfilingStatementPrototype($aConfig['master']['driver_options']);
            return $adapter;
		}, true);
	}
}
