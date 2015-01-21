<?php
namespace YcheukfMigration\Model;

use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\TableGateway;

class MigrationVersionTable
{
    /**
     * @var \Zend\Db\TableGateway\TableGateway
     */
    protected $tableGateway;

    public function __construct(TableGateway $tableGateway)
    {
        $this->tableGateway = $tableGateway;
    }

    public function save($version, $mdir)
    {
        $this->tableGateway->insert(array('version' => $version, 'mdir_md5' => md5($mdir), 'mdir' => $mdir));
        return $this->tableGateway->lastInsertValue;
    }

    public function delete($version, $mdir_md5)
    {
        $this->tableGateway->delete(array('version' => $version, 'mdir_md5' => $mdir_md5));
    }

    public function applied($version, $mdir_md5)
    {
        $result = $this->tableGateway->select(array('version' => $version, 'mdir_md5' => $mdir_md5));
        return $result->count() > 0;
    }

    public function getCurrentVersion($mdir_md5)
    {
        $select = new Select($this->tableGateway->getTable());
        $select->where(array('mdir_md5' => $mdir_md5))->order('version DESC')->limit(1);
        $result = $this->tableGateway->selectWith($select);
        if (!$result->count()) return 0;
        return $result->current()->getVersion();
    }
}