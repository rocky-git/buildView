<?php
/**
 * Created by PhpStorm.
 * User: rocky
 * Date: 2019-07-21
 * Time: 15:01
 */

namespace buildView;


use Faker\Provider\File;
use think\facade\Db;
use think\exception\HttpResponseException;
use think\facade\Request;
use think\Model;
use think\model\relation\BelongsToMany;
use think\model\relation\HasMany;

class Nestable extends Field
{
    //模型数据
    protected $data = [];
    //数据库表字段
    protected $tableFields = [];
    //模型
    protected $model;
    //db对象
    protected $db;

    protected $hideAction = false;
    protected $parentId;
    protected $titleField;
    protected $sortField;
    protected $displayClosure = null;
    protected $updateSortData = [];
    protected $deletedData = [];
    protected $filter = null;
    protected $filterCallBack = null;
    /**
     * Form constructor.
     * @param 模型
     */
    public function __construct($model, $parentId, $titleField, $sortFiled = 'sort')
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
        $this->parentId = $parentId;
        $this->titleField = $titleField;
        $this->sortField = $sortFiled;
        $this->nestable = new Field('nestable', '', '', '');
        $this->nestable->setOption('nestableId', 'table_content' . time());
        $this->hideExportButton();
        $this->hideDeletesButton();

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
            $action = Request::post('field');
            Db::startTrans();
            try {
                switch ($action) {
                    case 'buldview_sort_drag':
                        $data = Request::post('data');
                        foreach ($data as $val) {
                            $this->buildUpateSrotData($val);
                        }
                        $res = $this->model->saveAll($this->updateSortData);
                        Db::commit();
                        if ($res) {
                            throw new HttpResponseException(json(['code' => 1, 'msg' => lang('build_view_action_success'), 'data' => []]));
                        } else {
                            throw new HttpResponseException(json(['code' => 0, 'msg' => lang('build_view_action_error'), 'data' => []]));
                        }
                        break;

                    case 'delete':
                        $id = Request::post('id');
                        if (!is_null($this->beforeDel)) {
                            call_user_func($this->beforeDel, $deleteIds);
                        }
                        $this->buildDeleteData($id);
                        array_push($this->deletedData, $id);
                        $deleteIds = $this->deletedData;
                        if (in_array('is_deleted', $this->tableFields)) {
                            if ($deleteIds == 'all') {
                                $res = $this->model->where('1=1')->setField('is_deleted', 1);
                            } else {
                                $res = $this->model->whereIn($this->model->getPk(), $deleteIds)->setField('is_deleted', 1);
                            }
                        } else {
                            if ($deleteIds == 'all') {
                                $res = $this->model->where('1=1')->delete();
                            } else {
                                $res = $this->model->whereIn($this->model->getPk(), $deleteIds)->delete();
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

    protected function buildDeleteData($id)
    {
        $datas = $this->model->where($this->parentId, $id)->select();
        foreach ($datas as $val) {
            $this->deletedData[] = $val[$this->db->getPk()];
            $this->buildDeleteData($val[$this->db->getPk()]);
        }
    }

    protected function buildUpateSrotData($data, $pid = 0)
    {

        static $i = 0;
        $i++;
        $this->updateSortData[] = [
            $this->db->getPk() => $data['id'],
            $this->sortField => $i,
            $this->parentId => $pid
        ];
        if (isset($data['children'])) {
            foreach ($data['children'] as $v) {
                $this->buildUpateSrotData($v, $data['id']);
            }
        }
    }

    function getTree($list, $pid = 0)
    {
        $tree = [];
        if (!empty($list)) {        //先修改为以id为下标的列表
            $newList = [];
            foreach ($list as $k => $v) {
                $newList[$v['id']] = $v;
            }        //然后开始组装成特殊格式
            foreach ($newList as $value) {
                if ($pid == $value[$this->parentId]) {//先取出顶级
                    $tree[] = &$newList[$value['id']];
                } elseif (isset($newList[$value[$this->parentId]])) {//再判定非顶级的pid是否存在，如果存在，则再pid所在的数组下面加入一个字段items，来将本身存进去
                    $newList[$value[$this->parentId]]['children'][] = &$newList[$value['id']];
                }
            }
        }
        return $tree;
    }

    protected function buildNestedArray($nodes = [], $parentId = 0)
    {
        $branch = [];

        if (empty($nodes)) {
            $nodes = $this->db->removeOption('order')->field("*,{$this->db->getPk()} as id,{$this->titleField} as title")->order($this->sortField)->select();

        }
        foreach ($nodes as $node) {
            if ($node[$this->parentId] == $parentId) {

                $children = $this->buildNestedArray($nodes, $node[$this->db->getPk()]);

                if ($children) {
                    $node['children'] = $children;
                }

                $branch[] = $node;
            }
        }

        return $branch;
    }


    //获取当前模型
    public function model()
    {

        return $this->db;
    }

    /**
     * 设置标题
     * @param $title 设置标题
     */
    public function setTitle($title)
    {
        $this->setOption('title', $title);
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

    /**
     * 自定义显示内容
     * @param \Closure $closure
     */
    public function display(\Closure $closure)
    {
        $this->displayClosure = $closure;
    }

    /**
     * 返回构建html
     * @return string
     */
    protected function buildHtml()
    {
        $html = '';
        foreach ($this->data as $val) {
            $html .= $this->buildItem($val);
        }

        return $html;
    }

    //设置from打开窗口方式
    public function setFromOpen($type = 'modal')
    {
        $this->setOption('formOpen', $type);
        $this->nestable->setOption('formOpen', $type);
    }

    /**
     * 构建item元素html
     * @param $data 每行数据
     * @return string
     */
    protected function buildItem($data)
    {
        $itemHtml = "<li class='dd-item' data-id='" . $data['id'] . "'>";
        $action = $this->actionColumn = new Actions('actions_tools', lang('build_view_grid_action'), 'xs');
        $action->hideDetail();
        if (is_null($this->displayClosure)) {
            $resHtml = $data['title'];

        } else {
            $resHtml = call_user_func_array($this->displayClosure, [$data, $action]);
        }
        if (!$this->hideAction) {
            $resHtml .= "<div style=' flex: 1;text-align: right;' >" . $action->render() . "</div>";
        }
        $itemHtml .= "<div class='dd-handle' style='display:flex;align-items: center;'><i class='fa fa-bars'></i>&nbsp;&nbsp;" . $resHtml . "</div>";
        if (isset($data['children'])) {
            $itemHtml .= "<ol class='dd-list'>";
            foreach ($data['children'] as $v) {
                $itemHtml .= $this->buildItem($v);
            }
            $itemHtml .= "</ol>";
        }
        $itemHtml .= "</li>";
        return $itemHtml;
    }
    //过滤
    public function filter($callback)
    {
        if ($callback instanceof \Closure) {
            $this->filter = new Filter($this->db);
            $this->filterCallBack = $callback;
        }
    }
    /*
     * 输出视图
     */
    public function view()
    {
        $this->dataSave();
        if (!is_null($this->filter)) {
            call_user_func($this->filterCallBack, $this->filter);
            $this->setOption('filter', $this->filter->render());
        }
        if (in_array('is_deleted', $this->tableFields)) {
            $this->db->where('is_deleted', 0);
        }
        $build_tree_id = Request::get('build_tree_id');
        if($build_tree_id){
            $children_ids =  $this->getAllNextId($build_tree_id);
            $parent_ids =  $this->getAllParentId($build_tree_id);
            $ids = array_merge($parent_ids,$children_ids);
            array_push($ids,$build_tree_id);
            $nodes = $this->model->whereIn('id',$ids)->select();
        }else{
            $nodes = $this->db->removeOption('order')->field("*,{$this->db->getPk()} as id,{$this->titleField} as title")->order($this->sortField)->select();
        }
        $this->data = $this->getTree($nodes->toArray());
        $this->nestable->setOption('html', rawurlencode($this->buildHtml()));
        $this->setOption('table', $this->nestable->render());
        return $this->render();
    }
    protected function getAllParentId($id,$data=[]){
        $pids = Db::name($this->model->getTable())->where('id',$id)->column($this->parentId);

        if(count($pids)>0){
            foreach($pids as $v){
                $data[] = $v;
                $data = $this->getAllParentId($v,$data); //注意写$data 返回给上级
            }
        }
        if(count($data)>0){
            return $data;
        }else{
            return [];
        }
    }
    protected function getAllNextId($id,$data=[]){
        $pids = Db::name($this->model->getTable())->where($this->parentId,$id)->column('id');
        if(count($pids)>0){
            foreach($pids as $v){
                $data[] = $v;
                $data = $this->getAllNextId($v,$data); //注意写$data 返回给上级
            }
        }
        if(count($data)>0){
            return $data;
        }else{
            return [];
        }
    }
}