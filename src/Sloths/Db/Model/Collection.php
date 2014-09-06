<?php

namespace Sloths\Db\Model;

use Sloths\Db\Sql\Select;
use Sloths\Observer\ObserverTrait;
use Sloths\Misc\ArrayUtils;

/**
 * @method Collection select($columns)
 * @method Collection where($condition, $params = null)
 * @method Collection orWhere($condition, $params = null)
 * @method Collection having($condition, $params = null)
 * @method Collection orHaving($condition, $params = null)
 * @method Collection groupBy($columns)
 * @method Collection orderBy($columns)
 * @method Collection limit(int $limit)
 * @method Collection offset(int $offset)
 */
class Collection implements \Countable, \IteratorAggregate, \JsonSerializable
{
    use ObserverTrait;

    /**
     * @var Select
     */
    protected $select;

    /**
     * @var string
     */
    protected $modelClassName;

    /**
     * @var \PDOStatement
     */
    protected $stmt;

    /**
     * @var int
     */
    protected $count;

    /**
     * @var int
     */
    protected $foundRows;

    /**
     * @var AbstractModel[]
     */
    protected $models;

    /**
     * @param Select|array $data
     * @param $modelClassName
     */
    public function __construct($data, $modelClassName)
    {
        $this->modelClassName = $modelClassName;

        if ($data instanceof Select) {
            $this->select = $data;
        } else {
            $this->setRows($data);
        }
    }

    /**
     * @return string
     */
    public function getModelClassName()
    {
        return $this->modelClassName;
    }

    /**
     * @param $method
     * @param $args
     * @return $this
     */
    public function __call($method, $args)
    {
        call_user_func_array([$this->select, $method], $args);
        return $this;
    }

    /**
     * @return \Sloths\Db\Database
     */
    public function getDatabase()
    {
        $modelClassName = $this->modelClassName;
        return $modelClassName::getDatabase();
    }

    /**
     * @param bool $reload
     * @return $this
     */
    public function load($reload = false)
    {
        if (null === $this->models || $reload) {
            $this->stmt = $this->getDatabase()->run($this->select);

            $rows = $this->stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->triggerEventListener('load', [&$rows]);

            $this->setRows($rows);
        }

        return $this;
    }

    protected function setRows(array $rows)
    {
        $modelClassName = $this->modelClassName;

        $this->models = [];

        foreach ($rows as $index => $row) {
            $this->models[$index] = new $modelClassName($row, $this);
        }

        $this->count = count($rows);
        $this->foundRows = null;
    }

    /**
     * @return $this
     */
    public function reload()
    {
        return $this->load(true);
    }

    /**
     * @param int $index
     * @return null|AbstractModel
     */
    public function getAt($index)
    {
        $this->load();
        return isset($this->models[$index])? $this->models[$index] : null;
    }

    /**
     * @return AbstractModel|null
     */
    public function first()
    {
        return $this->getAt(0);
    }

    /**
     * @param string $name
     * @param string $columnKey
     * @return array
     */
    public function column($name, $columnKey = null)
    {
        return ArrayUtils::column($this->toArray(), $name, $columnKey);
    }

    /**
     * @return array
     */
    public function ids()
    {
        $modelClassName = $this->modelClassName;
        $primaryKeyColumn = $modelClassName::getPrimaryKey();

        return $this->column($primaryKeyColumn);
    }

    /**
     * @param $withRelations
     * @return array
     */
    public function toArray($withRelations = false)
    {
        $this->load();
        $result = [];

        foreach ($this->models as $model) {
            $result[] = $model->toArray($withRelations);
        }

        return $result;
    }

    /**
     * @return int
     */
    public function count()
    {
        $this->load();
        return $this->count;
    }

    /**
     * @return int
     */
    public function foundRows()
    {
        if (null === $this->foundRows) {
            $select = clone $this->select;

            if (!$select->hasSpecInstance('Having')) {
                $select->getSpec('Select')->resetColumns();
                $select->select('COUNT(*)');
            }

            $select->limit(null);

            $this->foundRows = (int) $this->getDatabase()->run($select)->fetchColumn();
        }

        return $this->foundRows;
    }

    /**
     * @return AbstractModel[]|\Traversable
     */
    public function getIterator()
    {
        $this->load();
        return new \ArrayIterator($this->models);
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function callEach($method, array $args = [])
    {
        $this->load();

        foreach ($this->models as $model) {
            call_user_func_array([$model, $method], $args);
        }

        return $this;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function set($name, $value)
    {
        return $this->callEach('set', func_get_args());
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this->set($name, $value);
    }

    /**
     * @return $this
     */
    public function save()
    {
        return $this->callEach('save');
    }

    /**
     * @return $this
     */
    public function delete()
    {
        return $this->callEach('delete');
    }
}