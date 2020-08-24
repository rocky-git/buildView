<?php


namespace buildView;

use library\driver\Qiniu;
use library\File;
use OSS\Core\OssException;
use OSS\OssClient;
use think\Controller;
use think\facade\Cache;
use think\Image;
use think\facade\View;

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
        if (!$file->checkExt(strtolower(sysconf('storage_local_exts'))) && strstr('.', $file->getInfo('name'))) {
            return json(['uploaded' => false, 'error' => ['message' => '文件上传类型受限，请在后台配置']]);
        }
        if ($file->checkExt('php,sh') && strstr('.', $file->getInfo('name'))) {
            return json(['uploaded' => false, 'error' => ['message' => '可执行文件禁止上传到本地服务器']]);
        }
        $width = input('post.width');
        $height = input('post.height');
        $this->safe = boolval(input('safe'));
        $this->uptype = $this->getUploadType();
        //$this->extend = pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);
        $this->extend = strtolower(pathinfo($this->request->post('name'), PATHINFO_EXTENSION));
        $this->extend = $this->extend ? $this->extend : 'tmp';
        if ($width != '' && $height != '') {
            $name = "thumb/" . md5_file($file->getRealPath()) . "{$width}x{$height}." . $this->extend;
            $image = Image::open($file);
            $saveFile = 'upload/' . $name;
			file_exists(dirname($saveFile)) || mkdir(dirname($saveFile), 0755, true);
            $image->thumb($width, $height, Image::THUMB_CENTER)->save($saveFile);
            $fileDatas = file_get_contents($saveFile);
            unlink($saveFile);
        } else {
            $name = md5_file($file->getRealPath()) . '.' . $this->extend;
            $fileDatas = file_get_contents($file->getRealPath());
        }
        if (!empty($this->request->post('chunks'))) {
            if ($this->uptype == 'local') {
                return $this->localChunkUpload($file, $name);
            } elseif ($this->uptype == 'oss') {
                return $this->ossChunkUpload($file, $name);
            }
        }
        $info = File::instance($this->uptype)->save($name, $fileDatas);
        if (is_array($info) && isset($info['url'])) {
            if ($this->uptype == 'local') {
				$this->compressImage($info['file']);
                return json(['uploaded' => true, 'filename' => $this->safe ? $name :$info['key'], 'url' => $info['url']]);
            } else {
                return json(['uploaded' => true, 'filename' => $name, 'url' => $info['url']]);
            }
        } else {
            return json(['uploaded' => false, 'error' => ['message' => '文件处理失败，请稍候再试！']]);
        }
    }
	 /**
     * 压缩图片
     * @param $filename 文件路径
     */
    private function compressImage($filename){
        list($width, $height, $type, $attr) = getimagesize($filename);
        if($type > 1 && $type < 17){
            $extension = image_type_to_extension($type,false);
            $fun = "imagecreatefrom".$extension;
            $image = $fun($filename);
            $image_thump = imagecreatetruecolor($width,$height);
            if($type == 3){
                $alpha = imagecolorallocatealpha($image_thump, 0, 0, 0, 127);
                imagefill($image_thump, 0, 0, $alpha);
                imagesavealpha($image_thump, true);
            }
            imagecopyresampled($image_thump,$image,0,0,0,0,$width,$height,$width,$height);
            imagedestroy($image);
            $funcs = "image".$extension;
            $funcs($image_thump,$filename);
            imagedestroy($image_thump);
        }
    }
    /**
     * 本地分片上传
     * @Author: rocky
     * 2019/9/17 18:47
     * @param $name
     * @return \think\response\Json
     * @throws \OSS\Core\OssException
     * @throws \think\Exception
     */
    protected function localChunkUpload($file, $name)
    {
        $names = str_split(md5($this->request->post('name')), 16);
        $chunks = $this->request->post('chunks');
        $chunk = $this->request->post('chunk');
        if ($chunks == ($chunk + 1)) {
            $file->move("upload/{$names[0]}", "{$names[1]}{$chunk}", true, false);
            set_time_limit(0);
            $put_filename = "upload/{$name}";
            if (file_exists($put_filename)) {
                unlink($put_filename);
            }
            for ($i = 0; $i < $chunks; $i++) {
                $filenameChunk = "upload/{$names[0]}/" . "{$names[1]}{$i}";
                $fileData = file_get_contents($filenameChunk);
                file_exists(dirname($put_filename)) || mkdir(dirname($put_filename), 0755, true);
                $res = file_put_contents($put_filename, $fileData, FILE_APPEND);
            }
            array_map('unlink', glob("upload/{$names[0]}/*"));
            rmdir("upload/{$names[0]}");
            if ($res !== false) {
                if ($width != '' && $height != '') {
                    $image = Image::open($put_filename);
                    $image->thumb($width, $height, Image::THUMB_CENTER)->save($put_filename);
                }
                $info = File::instance($this->uptype)->info($name);
                return json(['uploaded' => true, 'filename' => $info['key'], 'url' => $info['url']]);
            } else {
                return json(['uploaded' => false, 'error' => ['message' => '上传失败']]);
            }
        } else {
            if (($info = $file->move("upload/{$names[0]}", "{$names[1]}{$chunk}", true, false))) {
                return json(['uploaded' => true, 'message' => '分片文件上传成功']);
            }
        }
    }

    /**
     * 阿里云分片上传
     * @Author: rocky
     * 2019/9/17 18:48
     * @throws \OSS\Core\OssException
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    protected function ossChunkUpload($file, $name)
    {
        $keyid = sysconf('storage_oss_keyid');
        $secret = sysconf('storage_oss_secret');
        $bucket = sysconf('storage_oss_bucket');
        $endpoint = sysconf('storage_oss_endpoint');
        $oss = new OssClient($keyid, $secret, $endpoint);
        $chunks = $this->request->post('chunks');
        $chunk = $this->request->post('chunk');
        if ($chunk == 0) {
            $filename = md5($this->request->post('name').time()) . '.' . $this->extend;
            Cache::set('ossUploadName', $filename, 3600 * 6);
            $uploadId = $oss->initiateMultipartUpload($bucket, $filename);
            Cache::set($filename, $uploadId, 3600 * 6);
            Cache::rm($partCacheKey);
        }
        $filename =  Cache::get('ossUploadName');
        $partCacheKey = md5($filename . 'part');
        $uploadId = Cache::get($filename);
        $upOptions = array(
            $oss::OSS_FILE_UPLOAD => $file->getRealPath(),
            $oss::OSS_PART_NUM => ($chunk + 1),
            $oss::OSS_SEEK_TO => 0,
            $oss::OSS_LENGTH => filesize($file->getRealPath()),
            $oss::OSS_CHECK_MD5 => false,
        );

        try {

            $responseUploadPart[] = $oss->uploadPart($bucket, $filename, $uploadId, $upOptions);

            if (Cache::has($partCacheKey)) {
                $responseUploadParts = unserialize(Cache::get($partCacheKey));
                $responseUploadParts = array_merge($responseUploadParts, $responseUploadPart);
                Cache::set($partCacheKey, serialize($responseUploadParts));
            } else {
                Cache::set($partCacheKey, serialize($responseUploadPart));
            }
            if ($chunks != ($chunk + 1)) {
                return json(['uploaded' => true, 'message' => '分片文件上传成功']);
            }
        } catch (OssException $e) {
            return json(['uploaded' => false, 'error' => ['message' => $e->getMessage()]]);
        }
        if ($chunks == ($chunk + 1)) {
            $responseUploadPart = unserialize(Cache::get($partCacheKey));
            $uploadParts = array();
            foreach ($responseUploadPart as $i => $eTag) {
                $uploadParts[] = array(
                    'PartNumber' => ($i + 1),
                    'ETag' => $eTag,
                );
            }
            try {
                // 在执行该操作时，需要提供所有有效的$uploadParts。OSS收到提交的$uploadParts后，会逐一验证每个分片的有效性。当所有的数据分片验证通过后，OSS将把这些分片组合成一个完整的文件。
                $res = $oss->completeMultipartUpload($bucket, $filename, $uploadId, $uploadParts);
                $xml = simplexml_load_string($res['body']);
                $info = json_decode(json_encode($xml), TRUE);
                $url = File::instance('oss')->url($info['Key']);
                return json(['uploaded' => true, 'filename' => $info['Key'], 'url' => $url]);
            } catch (OssException $e) {
                return json(['uploaded' => false,'filename'=>$filename, 'error' => ['message' => $e->getMessage()]]);
            }
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
     * 获取七牛云上传token
     * @Author: rocky
     * 2019/9/17 13:23
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function qiniuToken()
    {
        $token = File::instance('qiniu')->buildUploadToken(null, 3600 * 3);
        $upload = File::instance('qiniu')->upload();
        return json(['upload' => $upload, 'token' => $token]);
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
	 /**
     * 调起百度地图
     * @Author: rocky
     * 2019/12/4 17:39
     * @return mixed
     */
    public function map(){
        $path = __DIR__ . '/view/BaiduMap.html';
        $content = file_get_contents($path);
        $baiduConfig = config('baidu.');
        if(!isset($baiduConfig['ak']) || empty($baiduConfig['ak'])){
            $ak = '6yOCGNRifiDEOO63RIfSODq6YVb0TrLI';
        }else{
            $ak = $baiduConfig['ak'];
        }
        $mark = $this->request->get('mark');
        return View::display($content,['ak'=>$ak,'mark'=>$mark], ['strip_space' => false]);
    }
}
