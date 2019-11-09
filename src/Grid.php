<?php
/**
 * Created by PhpStorm.
 * User: rocky
 * Date: 2019-07-21
 * Time: 15:01
 */

namespace buildView;


use Faker\Provider\File;
use think\Db;
use think\exception\HttpResponseException;
use think\facade\Request;
use think\Model;
use think\model\relation\BelongsToMany;
use think\model\relation\HasMany;

class Grid extends Field
{
    //模型数据
    protected $data = [];
    //列
    protected $columns = [];
    //是否分页
    protected $isPage = true;
    //数据库表字段
    protected $tableFields = [];
    //模型
    protected $model;
    //db对象
    protected $db;
    protected $filter = null;
    //关联字段链接
    protected $realtionMethodArr = [];
    protected $table = null;
    protected $hideAction = false;
    protected $hideColumnSelect = false;
    protected $tableTitles = [];
    protected $tableColRow = 1;
    protected $actionColumn = null;
    protected $toolsArr = [];
    protected $beforeDel = null;
    protected $sortField = 'sort';

    /**
     * Form constructor.
     * @param 模型
     */
    public function __construct($model)
    {
        if ($model instanceof Model) {
            $this->model = $model;
            $this->db = $model->db();
            $this->tableFields = $this->model->getTableFields();
        } else {
            abort(999, '不是有效的模型');
        }

        $this->template = 'grid';
        $this->setOption('title', lang('build_view_grid_list'));

        $this->column($model->getPk(), 'id')->hide();
        $this->table = new Field('table', '', '', '');
        $this->table->setOption('tableId', 'table_content' . time());
        $this->setSort();
        $this->actionColumn = new Actions('actions_tools', lang('build_view_grid_action'));


    }

    /**
     * 设置表头行数
     * @Author: rocky
     * 2019/11/9 11:39
     * @param $row 行数
     */
    public function setTableColRow($row){
        $this->tableColRow = $row;
    }
    /**
     * 设置表格样式
     * @Author: rocky
     * 2019/11/9 11:31
     * @param string $skin 样式
     */
    public function setTableSkin($skin = 'row '){
        $this->table->setOption('tableSkin',$skin);
    }
    /**
     * 设置分页每页限制
     * @Author: rocky
     * 2019/11/6 14:01
     * @param $limit
     */
    public function setPageLimit($limit){
        $this->table->setOption('pageLimit',$limit);
    }
    public function actions(\Closure $closure)
    {
        $this->actionColumn->setClosure($closure);
    }

    //删除前回调
    public function deling(\Closure $closure)
    {
        $this->beforeDel = $closure;
    }

    //数据操作
    private function dataSave()
    {
        if (Request::isPost()) {
            $this->model->setQuery(null);
            $action = Request::post('field');
            Db::startTrans();
            try {
                switch ($action) {
                    case 'buldview_sort':
                        $id = Request::post('id');
                        $value = Request::post('value');
                        $res = $this->model->where($this->model->getPk(),$id)->setField($this->sortField,$value);
                        Db::commit();
                        if ($res) {
                            throw new HttpResponseException(json(['code' => 1, 'msg' => lang('build_view_action_success'), 'data' => []]));
                        } else {
                            throw new HttpResponseException(json(['code' => 0, 'msg' => lang('build_view_action_error'), 'data' => []]));
                        }
                        break;
                    case 'delete':
                        $deleteIds = Request::post('id');
                        if (!is_null($this->beforeDel)) {
                            call_user_func($this->beforeDel, $deleteIds);
                        }

                        if (in_array('is_deleted', $this->tableFields)) {
                            if ($deleteIds == 'all') {
                                $res = $this->model->where('1=1')->setField('is_deleted', 1);
                            } else {
                                $res = $this->model->whereIn($this->model->getPk(), $deleteIds)->setField('is_deleted', 1);
                            }
                        } else {
                            if ($deleteIds == 'all') {
                                $res = $this->deleteHasManyData(0);
                            } else {
                                $res = $this->deleteHasManyData($deleteIds);
                            }
                        }
                        Db::commit();
                        if ($res) {
                            throw new HttpResponseException(json(['code' => 1, 'msg' => lang('build_view_action_del_success'), 'data' => []]));
                        } else {
                            throw new HttpResponseException(json(['code' => 0, 'msg' => lang('build_view_action_error'), 'data' => []]));
                        }
                        break;
                    default:
                        $updateData = Request::except('id', 'post');
                        $res = $this->model->whereIn($this->model->getPk(), Request::post('id'))->update($updateData);
                        Db::commit();
                        if ($res) {
                            throw new HttpResponseException(json(['code' => 1, 'msg' => lang('build_view_action_success'), 'data' => []]));
                        } else {
                            throw new HttpResponseException(json(['code' => 0, 'msg' => lang('build_view_action_error'), 'data' => []]));
                        }
                }
            } catch (Exception $e) {
                Db::rollback();
                throw new HttpResponseException(json(['code' => 0, 'msg' => lang('build_view_action_error'), 'data' => []]));
            }
        }
    }

    /**
     * 删除包含一对多关联数据
     * @Author: rocky
     * 2019/8/20 13:56
     * @throws \ReflectionException
     */
    private function deleteHasManyData($deleteIds)
    {
        $reflection = new \ReflectionClass($this->model);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        $className = $reflection->getName();
        $manyRelations = [];
        $relation = [];
        foreach ($methods as $method) {
            if ($method->class == $className) {
                $name = $method->name;
                $p = new \ReflectionMethod($method->class, $name);
                if ($p->getNumberOfParameters() == 0) {
                    if ($this->model->$name() instanceof HasMany) {
                        array_push($relation, $name);
                    } elseif ($this->model->$name() instanceof BelongsToMany) {
                        array_push($manyRelations, $name);
                    }
                }
            }
        }
        if (count($relation) > 0 || count($manyRelations) > 0) {
            if ($deleteIds == 0) {
                $deleteIds = $this->model->column('id');
                foreach ($deleteIds as $deleteId) {
                    $db = $this->model->get($deleteId, $relation);
                    $res = $db->together($relation)->delete();
                    foreach ($manyRelations as $manyRelation) {
                        $db->$manyRelation()->detach();
                    }
                }
            } else {
                $deleteIds = explode(',', $deleteIds);
                foreach ($deleteIds as $deleteId) {
                    if (!empty($deleteId)) {
                        $db = $this->model->get($deleteId, $relation);
                        $res = $db->together($relation)->delete();
                        foreach ($manyRelations as $manyRelation) {
                            $db->$manyRelation()->detach();
                        }
                    }
                }
            }
        } else {
            if ($deleteIds == 0) {
                $res = $this->model->where('1=1')->delete();
            } else {
                $res = $this->model->whereIn($this->model->getPk(), $deleteIds)->delete();
            }
        }
        return $res;
    }

    /**
     * 设置iframe中提交的参数
     * @Author: rocky
     * 2019/7/31 17:04
     * @param $type 提交模式
     * @param $url 提交地址
     * @param $val 附加参数
     */
    public function setIframe($type = 'submit', $url = '', $val = '')
    {
        $this->template = 'iframe';
        $this->table->iframeType($type);
        $this->table->iframeUrl($url);
        $this->table->iframeValue($val);
    }

    public function getTable()
    {
        return $this->table;
    }

    //获取当前模型
    public function model()
    {

        return $this->db;
    }

    //设置from打开窗口方式
    public function setFromOpen($type = 'modal')
    {
        $this->setOption('formOpen', $type);
        $this->table->setOption('formOpen', $type);
    }

    /**
     * 设置标题
     * @param $title 设置标题
     */
    public function setTitle($title)
    {
        $this->setOption('title', $title);
    }

    //关闭分页
    public function hidePage()
    {
        $this->isPage = false;
        $this->table->setOption('hidePage', true);
    }

    //隐藏添加按钮
    public function hideAddButton()
    {
        $this->setOption('hideAddButton', true);
    }

    //隐藏导出按钮
    public function hideExportButton()
    {
        $this->setOption('hideExportButton', true);
    }

    //隐藏批量删除
    public function hideDeletesButton()
    {
        $this->setOption('hideDeletesButton', true);
    }

    //隐藏操作列
    public function hideAction()
    {
        $this->hideAction = true;
    }

    //开启排序
    public function setSort($field = 'sort')
    {
        $this->sortField = $field;
        if (in_array($field, $this->tableFields)) {
            $this->column($field, "<button id='sortButton' class='layui-btn layui-btn-xs layui-btn-normal'style='width:46px' title='" . lang('build_view_grid_sort') . "' type='button'>" . lang('build_view_grid_sort') . "</button")
                ->width(80)
                ->style('')
                ->display(function ($val, $data) {
                    return "<input type='text' data-table-sort='true' value='{$val}' data-id='{$data["id"]}' class='layui-input text-center' style='padding-left:0px;' onkeyup=\"value=value.replace(/[^\d]/g,'')\" onblur=\"value=value.replace(/[^\d]/g,'')\">";
                })->setColsRow($this->tableColRow);
        }
    }

    //过滤
    public function filter($callback)
    {
        if ($callback instanceof \Closure) {
            $this->model->setQuery($this->db);
            $this->filter = new Filter($this->db);
            call_user_func($callback, $this->filter);
        }
    }

    //隐藏多选
    public function hideColumnSelect()
    {
        $this->hideColumnSelect = true;
    }

    public function view()
    {
        $this->dataSave();
        if (in_array('is_deleted', $this->tableFields)) {
            $this->db->where('is_deleted', 0);
        }
        if (Request::get('table_sort')) {
            $field = urldecode(Request::get('field'));
            $order = Request::get('order');
            $sql = $this->db->removeOption('order')->orderRaw("{$field} {$order}");
        }
        if (Request::get('export')) {
            switch (Request::get('export_type')) {
                case 'all':
                    $this->data = $this->model->select();
                    break;
                case 'page':
                    $this->data = $this->db->page(Request::get('page'), Request::get('limit'))->select();

                    break;
                case 'select':
                    $this->data = $this->model->whereIn('id', Request::get('ids'))->select();
                    break;
            }
        } else {
            if ($this->isPage) {
                $this->data = $this->db->page(Request::get('page'), Request::get('limit'))->select();
            } else {
                $this->data = $this->db->select();
            }
        }
        //导出表格表头
        $excelTitle = [];
        //导出表格数据
        $excelData = [];
        //是否隐藏多选
        if (!$this->hideColumnSelect) {
            $this->tableTitles[0][] = ['type' => 'checkbox','rowspan'=>$this->tableColRow];
        }
        //是否隐藏操作列
        if (!$this->hideAction) {
            array_push($this->columns, $this->actionColumn);
        }
        $tableData = [];
        $totalRowData = [];
        foreach ($this->data as $index => &$val) {
            foreach ($this->columns as $column) {
                $column->setData($val);
                $tableData[$index][$column->field] = $column->render();
                if (($column->field != 'actions_tools' && $column->field != 'id' && $this->issetField($val, $column->field)) || $column->excelData) {
                    if (!$column->excelClose) {
                        $excelTitle[$column->field] = $column->title;
                        if (empty($column->excelData)) {
                            $excelTr[$column->field] = $column->value;
                        } else {
                            $excelTr[$column->field] = $column->excelData;
                        }
                    }
                }
                if (!is_array($column->value)) {
                    $totalRowData[$column->field] += $column->value;
                }
            }
            array_push($excelData, $excelTr);

        }
        $totalRow = false;
        foreach ($this->columns as $key => $column) {
            if ($column->totalRow) {
                $column->cols['totalRowText'] = '<span class="layui-badge">合计：' . number_format($totalRowData[$column->field], 2) . '</span>';
                if (!$totalRow) {
                    $this->table->totalRow(true);
                    $totalRow = true;
                }
            }

            $this->tableTitles[$column->colsRow][] = $column->cols;

            $this->tableTitles = array_values($this->tableTitles);
            //halt(json_encode($this->tableTitles));
        }
        if (Request::get('table')) {
            throw new HttpResponseException(json(['code' => 0, 'msg' => '操作成功', 'data' => $tableData, 'count' => $this->db->removeOption('page')->removeOption('order')->count()]));
        }
        if (Request::get('export')) {
            if (empty($excelData)) {
                die('<script>alert("导出数据不能为空");history.back()</script>');
            }
            Excel::export($this->options['title'], $excelTitle, $excelData);
        }
        if (!is_null($this->filter)) {
            $this->setOption('filter', $this->filter->render());
        }

        $this->table->setOption('toolbar', implode('', $this->toolsArr));
       
        $this->table->name(json_encode($this->tableTitles));
        $this->setOption('table', $this->table->render());
        return $this->render();
    }

    private function issetField($data, $field)
    {
        $fields = explode('.', $field);
        if (!is_array($data)) {
            $data = $data->toArray();
        }
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                return true;
            }
        }
        return false;
    }

    //头像昵称列
    public function userInfo($headimg = 'headimg', $nickname = 'nickname', $label = '会员信息')
    {
        array_push($this->realtionMethodArr, $headimg);
        $headimg = implode('.', $this->realtionMethodArr);
        array_pop($this->realtionMethodArr);
        array_push($this->realtionMethodArr, $nickname);
        $nickname = implode('.', $this->realtionMethodArr);
        $this->realtionMethodArr = [];
        return $this->column($headimg, $label)->display(function ($val, $data, $html) use ($nickname) {
            return $html . '<br>' . $this->array_get($nickname, $data);
        })->image(50);
    }

    private function array_get($name, $data)
    {
        foreach (explode('.', $name) as $segment) {
            $data = $data[$segment];
        }
        return $data;
    }

    public function __call($name, $arguments)
    {
        array_push($this->realtionMethodArr, $name);
        if (count($arguments) > 0) {
            $label = array_shift($arguments);
            $name = implode('.', $this->realtionMethodArr);
            $this->realtionMethodArr = [];
            return $this->column($name, $label);
        }
        return $this;
    }


    /**
     * 设置列
     * @Author: rocky
     * 2019/7/25 16:20
     * @param $field 字段
     * @param $label 标签
     * @return Column
     */
    public function column($field, $label)
    {
        $column = new Column($field, $label);

        array_push($this->columns, $column);
        return $column;
    }

    public function addTools($val)
    {
        if ($val instanceof Button) {
            $this->toolsArr[] = $val->render();
        } else {
            $this->toolsArr[] = $val;
        }
        return $this;
    }

}