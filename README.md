# buildView
##ThinkPhP 适用于ThinkAdmin后台框架，layui构建视图增删改查。
使用下面的命令来快速创建一个对应模型和控制器
>php think make:admin UserController --model=User
---
表格基本使用
```
use buildView\Grid;
use app\common\model\User;

$grid = new Grid(new User);

//设置标题
$grid->setTitle('列表');

//Thinkphp数据库的链式操作
$grid->model()->where('id',2);

//添加一列，nickname数据库字段
$grid->nickname('昵称');

// 效果和上面一样
$grid->column('nickname', '昵称');

// 图片显示 wdith列宽度，image第一个参数圆角，第二参数图片宽度，第三次参数高度
$grid->nickname('昵称')->width(200)->image(50,200,80);

//列自定义显示
$grid->headimg('微信昵称')->display(function ($val, $data,$html) {
            //$val 当前值
            //$data 当前行数据
            //$html 当前值html
            return $html . '<br>' . $data['nickname'];
        })->image(50);

// 可编辑列
$grid->nickname('昵称')->editor();

// 可开关switch按钮
$grid->nickname('昵称')->switchs();

// 自定义开关switch按钮
$grid->nickname('昵称')->switchs([
    'on'=>['text'=>'开','value'=>'1'],
    'off'=>['text'=>'关','value'=>'-1']
]);

//隐藏操作列
$grid->hideAction();

//隐藏添加按钮
$grid->hideAddButton();

//隐藏表格导出按钮
$grid->hideExportButton();

//隐藏批量删除按钮
$grid->hideDeletesButton();

//隐藏多选框
$grid->hideColumnSelect();

//禁用分页
$grid->hidePage();

//操作列单个隐藏
$grid->actions(function (Actions $actions,$data){
            //$data 当前行数据
            
            //隐藏删除按钮        
           $actions->hideDel();
           //隐藏详情按钮
           $actions->hideDetail();
           //隐藏编辑按钮
           $actions->hideEdit();
 });
 //筛选
 $grid->filter(function ($filter){
             //等于筛选  
             $filter->eq('nickname','昵称');
             //模糊筛选
             $filter->like('nickname','昵称');
             //时间区间筛选
             $filter->dateBetween('create_at','时间');
  });
  // 最后渲染视图
  return $grid->view(); 
```
---
form表单
```
$form = new Form(new User);

//数字框 必选 最小值 最大值
$form->number('num','数量')->required()->min(1)->max(4);

//文本框
$form->text('title','标题');

//富文本框
$form->ckeditor('content','标题');

//图片上传
$form->image('img','图片');

//多图上传
$form->image('img','图片')->multiple(true);

//文件上传
$form->file('img','图片');

//单选下拉框
$form->select('options','图片')->options([
    1=>'第一个',
    2=>'第二个',
]);

//多选下拉框
$form->select('options','图片')->options([
    1=>'第一个',
    2=>'第二个',
])->multiple(true);

//radio
$form->radio('options','图片')->options([
    1=>'第一个',
    2=>'第二个',
]);

```

