<?php
/**
 * Created by PhpStorm.
 * User: rocky
 * Date: 2019-07-21
 * Time: 15:01
 */

namespace buildView;


use app\common\tools\Excel;
use Faker\Provider\File;
use think\exception\HttpResponseException;
use think\facade\Request;
use think\Model;

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
    protected $actionColumn = null;
    protected $toolsArr = [];

    /**
     * Form constructor.
     * @param 模型
     */
    public function __construct($model)
    {
        if ($model instanceof Model) {
            $this->model = $model;
            $this->db = $this->model->db();

            $this->tableFields = $this->model->getTableFields();
        } else {
            abort(999, '不是有效的模型');
        }
        $this->template = 'grid';
        $this->setOption('title', '列表');
        $this->column($model->getPk(), 'id')->hide();
        $this->table = new Field('table', '', '', '');
        $this->table->setOption('tableId', 'table_content' . time());
        $this->setSort();
        $this->actionColumn = new Actions('actions_tools', '操作');
        $this->dataSave();
        $this->importJs();
    }
    private function importJs(){
        $js = file_get_contents(__DIR__ . '/view/admin.js');
        $this->options['import_js'] = $js;
    }
    //数据保存
    private function dataSave()
    {
        if (Request::isPost()) {
            $action = Request::post('field');
            switch ($action) {
                case 'delete':
                    $deleteIds = Request::post('id');
                    if (in_array('is_deleted', $this->tableFields)) {
                        if($deleteIds == 'all'){
                            $res = $this->model->where('1=1')->setField('is_deleted', 1);
                        }else{
                            $res = $this->model->whereIn($this->model->getPk(), $deleteIds)->setField('is_deleted', 1);
                        }

                    } else {
                        if($deleteIds == 'all'){
                            $res = $this->model->where('1=1')->delete();
                        }else{
                            $res = $this->model->whereIn($this->model->getPk(),$deleteIds)->delete();
                        }
                    }
                    if ($res) {
                        throw new HttpResponseException(json(['code' => 1, 'msg' => '删除成功', 'data' => []]));
                    } else {
                        throw new HttpResponseException(json(['code' => 0, 'msg' => '删除失败, 请稍候再试！', 'data' => []]));
                    }
                    break;
                default:
                    $res = $this->model->whereIn($this->model->getPk(), Request::post('id'))->setField($action, Request::post('value'));
                    if ($res) {
                        throw new HttpResponseException(json(['code' => 1, 'msg' => '操作成功', 'data' => []]));
                    } else {
                        throw new HttpResponseException(json(['code' => 0, 'msg' => '操作失败, 请稍候再试！', 'data' => []]));
                    }
            }
        }
    }

    /**
     * 设置iframe中提交的参数
     * @Author: rocky
     * 2019/7/31 17:04
     * @param $url 提交地址
     * @param $val 附加参数
     */
    public function setIframeSubmit($url,$val){
        $this->template = 'iframe';
        $this->table->iframeUrl($url);
        $this->table->iframeValue($val);
    }
    public function getTable(){
        return $this->table;
    }
    //获取当前模型
    public function model()
    {

        return $this->db->getModel();
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
        if (in_array($field, $this->tableFields)) {
            $this->column($field, '排序')->width(100)->style('background-color: #eee;')->editor();
        }
    }

    //过滤
    public function filter($callback)
    {
        if ($callback instanceof \Closure) {
            $this->filter = new Filter($this->db->getModel());
            call_user_func($callback, $this->filter);
            $this->db = $this->filter->db();
        }

    }

    //隐藏多选
    public function hideColumnSelect()
    {
        $this->hideColumnSelect = true;
    }

    public function view()
    {
        if (in_array('is_deleted', $this->tableFields)) {
            $this->db->where('is_deleted', 0);
        }
        if(Request::get('table_sort')){
            $this->db->order(Request::get('field'),Request::get('order'));
        }
        if ($this->isPage) {
            $this->data = $this->db->page(Request::get('page'), Request::get('limit'))->select();
        } else {
            $this->data = $this->db->select();
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
        }
        //导出表格表头
        $excelTitle = [];
        //导出表格数据
        $excelData = [];
        //是否隐藏多选
        if (!$this->hideColumnSelect) {
            $this->tableTitles [] = ['type' => 'checkbox'];
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
                if ($column->field != 'actions_tools' && $column->field != 'id' && $this->issetField($val, $column->field)) {
                    $excelTitle[$column->field] = $column->title;
                    $excelTr[$column->field] = $column->value;
                }
                if(!is_array($column->value)){
                    $totalRowData[$column->field] += $column->value;
                }
            }
            array_push($excelData, $excelTr);

        }
        $totalRow = false;
        foreach ($this->columns as $key => $column) {
            if ($column->totalRow) {
                $column->cols['totalRowText'] = '<span class="layui-badge">合计：'.number_format($totalRowData[$column->field],2).'</span>';
                if (!$totalRow) {
                    $this->table->totalRow(true);
                    $totalRow = true;
                }
            }
            $this->tableTitles[] = $column->cols;
        }
        if (Request::get('table')) {
            throw new HttpResponseException(json(['code' => 0, 'msg' => '操作成功', 'data' => $tableData, 'count' => $this->db->removeOption('page')->removeOption('order')->count()]));
        }
        if (Request::get('export')) {
            Excel::exprot($this->options['title'], $excelTitle, $excelData);
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

    public function actions(\Closure $closure)
    {
        $this->actionColumn->setClosure($closure);
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