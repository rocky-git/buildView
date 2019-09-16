<?php



namespace buildView;

use library\File;
use think\Controller;
use think\Image;

/**
 * 后台插件管理
 * Class Plugs
 * @package app\admin\controller\api
 */
class Plugs extends Controller
{
    /**
     * Plupload 插件上传文件
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function plupload()
    {

        if (!($file = $this->getUploadFile()) || empty($file)) {
            return json(['uploaded' => false, 'error' => ['message' => '文件上传异常，文件可能过大或未上传']]);
        }
        if (!$file->checkExt(strtolower(sysconf('storage_local_exts'))) && strstr('.',$file->getInfo('name'))) {
            return json(['uploaded' => false, 'error' => ['message' => '文件上传类型受限，请在后台配置']]);
        }
        if ($file->checkExt('php,sh') && strstr('.',$file->getInfo('name'))) {
            return json(['uploaded' => false, 'error' => ['message' => '可执行文件禁止上传到本地服务器']]);
        }
        $width = input('post.width');
        $height = input('post.height');
        $this->safe = boolval(input('safe'));
        $this->uptype = $this->getUploadType();
        //$this->extend = pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);
        $this->extend = strtolower(pathinfo($this->request->post('name'), 4));
        $this->extend = $this->extend ? $this->extend : 'tmp';
        if($width!=''&&$height!=''){
            $name = "thumb/". md5_file($file->getRealPath())."{$width}x{$height}." .$this->extend ;
            $image = Image::open($file);
            $saveFile = 'upload/'.$name;
            $image->thumb($width,$height,Image::THUMB_CENTER)->save($saveFile);
            $fileDatas = file_get_contents($saveFile);
            unlink($saveFile);
        }else{
            $name = md5_file($file->getRealPath()).'.'.$this->extend;
            $fileDatas = file_get_contents($file->getRealPath());
        }
        $names = str_split(md5($this->request->post('name')), 16);
        if (!empty($this->request->post('chunks'))) {
            $chunks = $this->request->post('chunks');
            $chunk = $this->request->post('chunk');
            if ($chunks == ($chunk + 1)) {
                $file->move("upload/{$names[0]}", "{$names[1]}{$chunk}", true, false);
                set_time_limit(0);
                $put_filename = "upload/{$name}";
                if(file_exists($put_filename)){
                    unlink($put_filename);
                }
                for ($i = 0; $i < $chunks; $i++) {
                    $filenameChunk = "upload/{$names[0]}/" . "{$names[1]}{$i}";
                    $fileData = file_get_contents($filenameChunk);
                    file_exists(dirname($put_filename)) || mkdir(dirname($put_filename), 0755, true);
                    $res = file_put_contents($put_filename, $fileData,FILE_APPEND);
                }

                array_map('unlink',glob("upload/{$names[0]}/*"));
                rmdir("upload/{$names[0]}");
                if ($res !== false) {
                    if($width!=''&&$height!=''){
                        $image = Image::open($put_filename);
                        $image->thumb($width,$height,Image::THUMB_CENTER)->save($put_filename);
                    }
                    $info = File::instance($this->uptype)->info($name.$ext);
                    return json(['uploaded' => true, 'filename' => $name.$ext, 'url' => $this->safe ? $info['key'] :  $info['url']]);
                }else{
                    return json(['uploaded' => false, 'error' => ['message' => '上传失败']]);
                }
            } else {
                if (($info = $file->move("upload/{$names[0]}", "{$names[1]}{$chunk}", true, false))) {
                    return json(['code' => 203, 'msg' => '分片文件上传成功']);
                }
            }
        }
        $info = File::instance($this->uptype)->save($name,$fileDatas, $this->safe);
        if (is_array($info) && isset($info['url'])) {
            if($this->uptype == 'local'){
                return json(['uploaded' => true, 'filename' => $name, 'url' => $this->safe ? $info['key'] : $info['url']]);
            }else{
                return json(['uploaded' => true, 'filename' => $name, 'url' => $this->safe ? $name : $info['url']]);
            }
        } else {
            return json(['uploaded' => false, 'error' => ['message' => '文件处理失败，请稍候再试！']]);
        }
    }

    /**
     * 获取文件上传方式
     * @return string
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    private function getUploadType()
    {
        $this->uptype = input('uptype');
        if (!in_array($this->uptype, ['local', 'oss', 'qiniu'])) {
            $this->uptype = sysconf('storage_type');
        }
        return $this->uptype;
    }

    /**
     * 获取本地文件对象
     * @return \think\File
     */
    private function getUploadFile()
    {
        try {
            return $this->request->file('file');
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }
    }

}
