<?php

namespace YcheukfMigration\Library;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\Driver\Pdo\Pdo;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use Zend\Db\Metadata\Metadata;
use YcheukfMigration\Library\OutputWriter;
use YcheukfMigration\Model\MigrationVersionTable;

/**
 * Main migration logic
 */
class Migration
{
    const MIGRATION_TABLE = 'migration_version';

    protected $migrationsDir;
    protected $migrationsDirMd5;
    protected $migrationsNamespace;
    protected $adapter;
    /**
     * @var \Zend\Db\Adapter\Driver\ConnectionInterface
     */
    protected $connection;
    protected $metadata;
    protected $migrationVersionTable;
    protected $outputWriter;

    /**
     * @param \Zend\Db\Adapter\Adapter $adapter
     * @param array $config
     * @param \YcheukfMigration\Model\MigrationVersionTable $migrationVersionTable
     * @param OutputWriter $writer
     * @throws MigrationException
     */
    public function __construct(Adapter $adapter, array $config, MigrationVersionTable $migrationVersionTable, OutputWriter $writer = null, $sm=null)
    {
        $this->serviceManager = $sm;
        $this->adapter = $adapter;
        $this->metadata = new Metadata($this->adapter);
        $this->connection = $this->adapter->getDriver()->getConnection();
        $this->migrationsDir = $config['dir'];
        $this->migrationsDirMd5 = md5($config['dir']);
        $this->migrationsNamespace = $config['namespace'];
        $this->migrationVersionTable = $migrationVersionTable;
        $this->outputWriter = is_null($writer) ? new OutputWriter() : $writer;
		self::generateMigrationDir($this->migrationsDir);
        if (is_null($this->migrationsNamespace))
            throw new MigrationException('Unknown namespaces!');


        $this->checkCreateMigrationTable();
    }

	public static function generateMigrationDir($migrationsDir){
        if (is_null($migrationsDir))
            throw new MigrationException('Migrations directory not set!');

        if (!is_dir($migrationsDir)) {
            if (!mkdir($migrationsDir, 0775, true)) {
                throw new MigrationException(sprintf('Failed to create migrations directory %s', $migrationsDir));
            }
        }elseif (!is_writable($migrationsDir)) {
            throw new MigrationException(sprintf('Migrations directory is not writable %s', $migrationsDir));
        }
	}

    /**
     * Create migrations table of not exists
     */
    protected function checkCreateMigrationTable()
    {
        if (strpos($this->connection->getDriverName(), 'mysql') !== false) {
            $sql = <<<TABLE
CREATE TABLE IF NOT EXISTS `%s` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mdir` text NOT NULL ,
  `mdir_md5` varchar(64) NOT NULL ,
  `version` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `version` (`version`),
  KEY `mdir_md5` (`mdir_md5`)
);
TABLE;
        } else {
            $sql = <<<TABLE
CREATE TABLE IF NOT EXISTS "%s" (
  "id"  SERIAL NOT NULL,
  `mdir` text NOT NULL ,
  `mdir_md5` varchar(64) NOT NULL ,
  "version" bigint NOT NULL,
  PRIMARY KEY ("id")
);
TABLE;
        }
        $this->connection->execute(sprintf($sql, Migration::MIGRATION_TABLE));
    }

    /**
     * @return int
     */
    public function getCurrentVersion()
    {
        return $this->migrationVersionTable->getCurrentVersion($this->migrationsDirMd5);
    }

    /**
     * @param int $version target migration version, if not set all not applied available migrations will be applied
     * @param bool $force force apply migration
     * @param bool $down rollback migration
     * @throws MigrationException
     */
    public function migrate($version = null, $force = false, $down = false)
    {
        $migrations = $this->getMigrationClasses($force);

        if (!is_null($version) && !$this->hasMigrationVersions($migrations, $version)) {
			echo sprintf('Migration version %s is not found!', $version);
			return true;
//            throw new MigrationException(sprintf('Migration version %s is not found!', $version));
        }

        $currentMigrationVersion = $this->migrationVersionTable->getCurrentVersion($this->migrationsDirMd5);
        if (!is_null($version) && $version == $currentMigrationVersion && !$force) {
            throw new MigrationException(sprintf('Migration version %s is current version!', $version));
        }

        $this->connection->beginTransaction();
        try {
            if ($version && $force) {
                foreach ($migrations as $migration) {
                    if ($migration['version'] == $version) {
                        // if existing migration is forced to apply - delete its information from migrated
                        // to avoid duplicate key error
                        if (!$down) $this->migrationVersionTable->delete($migration['version'], $this->migrationsDirMd5);
                        $this->applyMigration($migration, $down);
                        break;
                    }
                }
                // target migration version not set or target version is greater than last applied migration -> apply migrations
            } elseif (is_null($version) || (!is_null($version) && $version > $currentMigrationVersion)) {
                foreach ($migrations as $migration) {
                    if ($migration['version'] > $currentMigrationVersion) {
                        if (is_null($version) || (!is_null($version) && $version >= $migration['version'])) {
                            $this->applyMigration($migration);
                        }
                    }
                }
                // target migration version is set -> rollback migration
            } elseif (!is_null($version) && $version < $currentMigrationVersion) {
                $migrationsByDesc = $this->sortMigrationsByVersionDesc($migrations);
                foreach ($migrationsByDesc as $migration) {
                    if ($migration['version'] > $version && $migration['version'] <= $currentMigrationVersion) {
                        $this->applyMigration($migration, true);
                    }
                }
            }

            $this->connection->commit();
        } catch (InvalidQueryException $e) {
            $this->connection->rollback();
            $msg = sprintf('%s: "%s"; File: %s; Line #%d', $e->getMessage(), $e->getPrevious()->getMessage(), $e->getFile(), $e->getLine());
            throw new MigrationException($msg);
        } catch (\Exception $e) {
            $this->connection->rollback();
            $msg = sprintf('%s; File: %s; Line #%d', $e->getMessage(), $e->getFile(), $e->getLine());
            throw new MigrationException($msg);
        }
    }

    /**
     * @param \ArrayIterator $migrations
     * @return \ArrayIterator
     */
    public function sortMigrationsByVersionDesc(\ArrayIterator $migrations)
    {
        $sortedMigrations = clone $migrations;

        $sortedMigrations->uasort(function ($a, $b) {
            if ($a['version'] == $b['version']) {
                return 0;
            }

            return ($a['version'] > $b['version']) ? -1 : 1;
        });

        return $sortedMigrations;
    }

    /**
     * Check migrations classes existence
     *
     * @param \ArrayIterator $migrations
     * @param int $version
     * @return bool
     */
    public function hasMigrationVersions(\ArrayIterator $migrations, $version)
    {
        foreach ($migrations as $migration) {
            if ($migration['version'] == $version) return true;
        }

        return false;
    }

    /**
     * @param \ArrayIterator $migrations
     * @return int
     */
    public function getMaxMigrationVersion(\ArrayIterator $migrations)
    {
        $versions = array();
        foreach ($migrations as $migration) {
            $versions[] = $migration['version'];
        }

        sort($versions, SORT_NUMERIC);
        $versions = array_reverse($versions);

        return count($versions) > 0 ? $versions[0] : 0;
    }

    /**
     * @param bool $all
     * @return \ArrayIterator
     */
    public function getMigrationClasses($all = false)
    {
        $classes = new \ArrayIterator();
        $sModuleDir = __DIR__."/../../../../../../module";
//        var_dump(file_exists($sModuleDir));
        $aMigrationDir = array();
//        if(file_exists($sModuleDir)){
//            foreach (new \DirectoryIterator($sModuleDir) as $fileInfo) {
//                if($fileInfo->isDot()) continue;
//                if(!$fileInfo->isDir()) continue;
//                $sDirTmp = $sModuleDir."/".$fileInfo->getFilename()."/migrations";
//                if(file_exists($sDirTmp)){
//                    $aMigrationDir[] = $sDirTmp;
//                }
//            }
//        
//        }
        $aMigrationDir[] = $this->migrationsDir;
//        var_dump($aMigrationDir);
        foreach($aMigrationDir as $sDirTmp){
            $iterator = new \GlobIterator(sprintf('%s/Version*.php', $sDirTmp), \FilesystemIterator::KEY_AS_FILENAME);
            foreach ($iterator as $item) {
                /** @var $item \SplFileInfo */
                if (preg_match('/(Version(\d+))\.php/', $item->getFilename(), $matches)) {
                    $applied = $this->migrationVersionTable->applied($matches[2], $this->migrationsDirMd5);
                    if ($all || !$applied) {
                        $className = $this->migrationsNamespace . '\\' . $matches[1];

                        if (!class_exists($className))
                            /** @noinspection PhpIncludeInspection */
                            require_once $sDirTmp . '/' . $item->getFilename();

                        if (class_exists($className)) {
                            $reflectionClass = new \ReflectionClass($className);
                            $reflectionDescription = new \ReflectionProperty($className, 'description');

                            if ($reflectionClass->implementsInterface('YcheukfMigration\Library\MigrationInterface')) {
                                $classes->append(array(
                                    'version' => $matches[2],
                                    'class' => $className,
                                    'description' => $reflectionDescription->getValue(),
                                    'applied' => $applied,
                                ));
                            }
                        }
                    }
                }
            }
        }
//var_dump($classes);
//exit;
        $classes->uasort(function ($a, $b) {
            if ($a['version'] == $b['version']) {
                return 0;
            }

            return ($a['version'] < $b['version']) ? -1 : 1;
        });
        return $classes;
    }

    protected function applyMigration(array $migration, $down = false)
    {
        /** @var $migrationObject AbstractMigration */
        $migrationObject = new $migration['class']($this->metadata, $this->outputWriter, $this->serviceManager);

        $this->outputWriter->writeLine(sprintf("Execute migration class %s %s at %s", $migration['class'], $down ? 'down' : 'up', $this->migrationsDir));

        $sqlList = $down ? $migrationObject->getDownSql() : $migrationObject->getUpSql();
		if(count($sqlList)){
			foreach ($sqlList as $sql) {
				$this->outputWriter->writeLine("Execute query:\n\n" . $sql);
				$this->connection->execute($sql);
			}
		}

        if ($down) {
            $this->migrationVersionTable->delete($migration['version'], $this->migrationsDirMd5);
        } else {
            $this->migrationVersionTable->save($migration['version'], $this->migrationsDir);
        }
    }
}
