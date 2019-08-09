<?php
/**
 * Created by PhpStorm.
 * User: rocky
 * Date: 2019-04-16
 * Time: 20:39
 */

namespace buildView;


use library\Controller;
use library\logic\Logic;
use library\logic\Page;
use think\Db;
use think\db\Query;
use think\Model;
use think\model\Relation;
use think\model\relation\BelongsTo;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * Class Filter
 * @package app\common\tools\service
 * @method $this eq($fields, $request = 'get') eq等于查询
 * @method $this like($fields, $request = 'get') like模糊查询
 * @method $this dateBetween($fields, $request = 'get') dateBetween日期区间查询
 * @method $this findIn($fields, $request = 'get') where FIND_IN_SET
 * @method $this in($fields, $request = 'get') whereIn查询
 */
class FilterSearch
{
    //模型
    protected $model;
    //当前模型db
    protected $db;
    //请求request对象
    protected $request;
    //关联数据库
    protected $relationModel;
    //方法
    protected $method = ['like', 'eq', 'dateBetween', 'findIn', 'in'];

    /**
     * Filter constructor.
     * @param $model 模型或表名
     */
    public function __construct($model)
    {

        if ($model instanceof Model) {
            $this->model = $model;
            $this->db = $this->model->db();
        }elseif($model instanceof Query){
            $this->db = $model;
            $this->model = $model->getModel();
        } else {
            $this->db = Db::name($model);
        }
        $this->request = request();
    }

    private function filterField($method, $fields, $request = 'get')
    {
        $data = $this->request->$request();
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $dbField = $this->getField($field);
                switch ($method) {
                    case 'like':
                        $this->db->whereLike($dbField, "%$data[$field]%");
                        break;
                    case 'eq':
                        $this->db->where($dbField, $data[$field]);
                        break;
                    case 'dateBetween':
                        list($start, $end) = explode(' - ', $data[$field]);
                        $this->db->whereBetween($dbField, ["{$start} 00:00:00", "{$end} 23:59:59"]);
                        break;
                    case 'findIn':
                        $this->db->where("FIND_IN_SET($data[$field],$dbField)");
                        break;
                    case 'in':
                        $this->db->whereIn($dbField, $data[$field]);
                        break;

                }
            }
        }
        return $this;
    }
    protected function getField($field){
        if($field[0] == '_'){
            return substr($field,1);
        }
        return $field;
    }
    // 下划线转驼峰
    private function camelize($uncamelized_words, $separator = '_')
    {
        $uncamelized_words = $separator . str_replace($separator, " ", strtolower($uncamelized_words));
        return ltrim(str_replace(" ", "", ucwords($uncamelized_words)), $separator);
    }

    /**
     * 关联查询
     * @param $relation_method 关联方法
     * @param $callback
     * @return $this
     * @throws \think\exception\DbException
     */
    public function relationWhere($relation_method, $callback)
    {
        if (method_exists($this->model, $relation_method)) {
            $relation = $this->model->$relation_method();
            if ($relation instanceof Relation) {
                $sql = $this->model->hasWhere($relation_method)->buildSql();
                $relation_table = $relation->getTable();
                $sqlArr = explode('ON ', $sql);
                $str = array_pop($sqlArr);
                preg_match_all("/`(.*)`/U", $str, $arr);
                if ($relation instanceof BelongsTo || $relation instanceof HasMany) {
                    $foreignKey = $arr[1][1];
                    $pk = $arr[1][3];
                }
                if ($relation instanceof HasOne) {
                    $pk = $arr[1][1];
                    $foreignKey = $arr[1][3];
                }
                if ($callback instanceof \Closure) {
                    $class = 'app\\common\\model\\' . $this->camelize($relation_table);
                    if (class_exists($class)) {
                        $this->relationModel = new self(new $class);
                    } else {
                        $this->relationModel = new self($relation_table);
                    }
                    call_user_func($callback, $this->relationModel);
                }
                $relationSql = $this->relationModel->query()->buildSql();

                $res = strpos($relationSql, 'WHERE');
                if ($res !== false) {
                    if ($relation instanceof HasMany) {
                        $sql = $this->relationModel->query()->whereRaw("{$relation_table}.{$pk}={$this->db->getTable()}.{$foreignKey}")->buildSql();
                    } elseif($relation instanceof BelongsTo) {
                        $sql = $this->relationModel->query()->whereRaw("{$pk}={$this->db->getTable()}.{$foreignKey}")->buildSql();
                    }else if($relation instanceof HasOne){
                        $sql = $this->relationModel->query()->whereRaw("{$foreignKey}={$this->db->getTable()}.{$pk}")->buildSql();
                    }

                    $this->db->whereExists($sql);
                }
            }
        }
        return $this;
    }

    //返回db对象
    public function query()
    {
        return $this->db;
    }

    public function __call($name, $arguments)
    {
        if (in_array($name, $this->method)) {
            array_unshift($arguments, $name);
            call_user_func_array([$this, 'filterField'], $arguments);
        } else {
            if (method_exists($this->db, $name)) {
                call_user_func_array([$this->db, $name], $arguments);
            }
        }
        return $this;
    }
   
}