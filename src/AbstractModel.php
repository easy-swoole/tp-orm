<?php


namespace EasySwoole\ORM;

use ArrayAccess;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\Db\Result;
use EasySwoole\ORM\Exception\Exception;
use EasySwoole\ORM\Utility\PreProcess;
use EasySwoole\ORM\Utility\Schema\Table;
use JsonSerializable;

/**
 * 抽象模型
 * Class AbstractModel
 * @package EasySwoole\ORM
 */
abstract class AbstractModel implements ArrayAccess, JsonSerializable
{

    protected $lastQueryResult;
    protected $lastQuery;
    private $limit = null;
    private $withTotalCount = false;
    private $fields = "*";

    /** @var Table */
    protected $schemaInfo;
    /**
     * 当前连接驱动类的名称
     * 继承后可以覆盖该成员以指定默认的驱动类
     * @var string
     */
    protected $connectionName = 'default';

    /**
     * 当前的数据
     * @var array
     */
    protected $data;

    /**
     * 模型的原始数据
     * 未应用修改器和获取器之前的原始数据
     * @var array
     */
    protected $originData;

    /**
     * 返回当前模型的结构信息
     * 请为当前模型编写正确的结构
     * @return Table
     */
    abstract protected function schemaInfo(): Table;

    public function getSchemaInfo():Table
    {
        return $this->schemaInfo;
    }

    public function lastQueryResult():?Result
    {
        return $this->lastQueryResult;
    }

    public function lastQuery():?QueryBuilder
    {
        return $this->lastQuery;
    }

    function limit(int $one,?int $two = null)
    {
        if($two !== null){
            $this->limit = [$one,$two];
        }else{
            $this->limit = $one;
        }
        return $this;
    }

    function withTotalCount()
    {
        $this->withTotalCount = true;
        return $this;
    }

    function field($fields)
    {
        if(!is_array($fields)){
            $fields = [$fields];
        }
        $this->fields = $fields;
    }

    function __construct(array $data = [])
    {
        $this->schemaInfo = $this->schemaInfo();
        $this->data = $this->originData = PreProcess::dataFormat($data,$this,true);
    }


    public function getAttr($attrName)
    {
        return $this->data[$attrName] ?? null;
    }


    public function setAttr($attrName, $attrValue):bool
    {
        if(isset($this->getSchemaInfo()->getColumns()[$attrValue])){
            $col = $this->getSchemaInfo()->getColumns()[$attrValue];
            $this->data[$attrName] = PreProcess::dataValueFormat($attrValue,$col);
            return true;
        }else{
            return false;
        }
    }

    public function data(array $data)
    {
        $this->data = $this->originData = PreProcess::dataFormat($data,$this,true);
        return $this;
    }

    public function delete($where = null):?int
    {
        $builder = new QueryBuilder();
        $builder =  PreProcess::mappingWhere($builder,$where,$this);
        $builder->delete($this->getSchemaInfo()->getTable(),$this->limit);
        $this->query($builder);
        return $this->lastQueryResult()->getAffectedRows();
    }

    /*
     * 等于insert
     */
    public function save($notNul = false):?int
    {
        $builder = new QueryBuilder();
        $primaryKey = $this->getSchemaInfo()->getPkFiledName();
        if(empty($primaryKey)){
            throw new Exception('save() needs primaryKey for model '.static::class);
        }
        $rawArray = $this->toArray($notNul);
        if(is_array($this->fields)){
            foreach ($rawArray as $key => $value){
                if(in_array($key,$this->fields)){
                    unset($rawArray[$key]);
                }
            }
        }
        $builder->insert($this->getSchemaInfo()->getTable(),$rawArray);
        if($this->lastQueryResult()->getLastInsertId()){
            $this->data[$primaryKey] = $this->lastQueryResult()->getLastInsertId();
            $this->originData = $this->data;
            return $this->lastQueryResult()->getLastInsertId();
        }
        return null;
    }


    public function get($where = null)
    {
        $modelInstance = new static;
        $builder = new QueryBuilder;
        $builder =  PreProcess::mappingWhere($builder,$where,$modelInstance);
        $builder->getOne($modelInstance->getSchemaInfo()->getTable(),$this->fields);
        $modelInstance->data($this->query($builder)[0]);
        return $modelInstance;
    }

    protected function query(QueryBuilder $builder)
    {
        $this->lastQuery = $builder;
        $con = DbManager::getInstance()->getConnection($this->connectionName);
        try{
            if($con){
                if($this->withTotalCount){
                    $builder->withTotalCount();
                }
                $ret = $con->query($builder);
                $this->lastQueryResult = $ret;
                return $ret->getResult();
            }else{
                throw new Exception("connection : {$this->connectionName} not register");
            }
        }catch (\Throwable $throwable){
            throw $throwable;
        }finally{
            $this->reset();
        }
    }

    public function all($where = null):array
    {
        $builder = new QueryBuilder;
        $builder = PreProcess::mappingWhere($builder,$where,$this);
        $builder->get($this->getSchemaInfo()->getTable(),$this->limit,$this->fields);
        $results = $this->query($builder);
        $resultSet = [];
        if (is_array($results)) {
            foreach ($results as $result) {
                $resultSet[] = new static($result);
            }
        }
        return $resultSet;
    }

    public static function create(array $data = []):AbstractModel
    {
        return new static($data);
    }

    /**
     * 更新当前记录
     * @param array $data
     * @param array $where
     */
    public function update(array $data = [], array $where = [])
    {
        // TODO 转为模型的Save操作 -> isUpdate
    }
    /**
     * ArrayAccess Exists
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->getAttr($offset);
    }

    public function offsetSet($offset, $value)
    {
        return $this->setAttr($offset, $value);
    }


    public function offsetUnset($offset)
    {
        return $this->setAttr($offset, null);
    }

    public function jsonSerialize()
    {
        return $this->data;
    }

    public function toArray($notNul = false): array
    {
        if ($notNul) {
            $temp = $this->data;
            foreach ($temp as $key => $value) {
                if ($value === null) {
                    unset($temp[$key]);
                }
            }
            return $temp;
        }
        return $this->data;
    }

    public function __toString()
    {
        return json_encode($this->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    function __set($name, $value)
    {
        $this->setAttr($name, $value);
    }

    function __get($name)
    {
        return $this->getAttr($name);
    }

    protected function reset()
    {
        $this->fields = '*';
        $this->limit = null;
        $this->withTotalCount = false;
    }
}