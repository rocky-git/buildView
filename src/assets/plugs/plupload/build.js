require.config({
    paths: {
        'plupload': ['/vendor/build-view/plugs/plupload/plupload.full.min'],
        'Base64': ['/vendor/build-view/plugs/plupload/base64'],
        'md5': ['/vendor/build-view/plugs/plupload/md5']
    }
});
define(['plupload', 'Base64', 'md5'], function (plupload) {
    window.plupload = plupload;
    return function (element, InitHandler, UploadedHandler, CompleteHandler) {
        var indexCount = 0;
        var $element = $(element), index = 0;
        if ($element.data('uploader')) return $element.data('uploader');
        var uploadUrl = '';
        var token = '';
        let loader = new plupload.Uploader({
            multi_selection: $element.attr('data-multiple') > 0,
            multipart_params: {
                safe: $element.attr('data-safe') || '0',
                uptype: $element.attr('data-uptype') || '',
                width: $element.attr('data-width'),
                height: $element.attr('data-height'),
            },
            drop_element: $element.get(0),
            browse_button: $element.get(0),
            url: 'buildview/upload',
            chunk_size: 0,
            runtimes: 'html5,flash,silverlight,html4',
            file_data_name: $element.attr('data-name') || 'file',
            flash_swf_url: baseRoot + 'plugs/plupload/Moxie.swf',
            silverlight_xap_url: baseRoot + 'plugs/plupload/Moxie.xap',
            filters: [{title: 'files', extensions: $element.attr('data-type') || '*'}],
        });
        if (typeof InitHandler === 'function') {
            loader.bind('Init', InitHandler);
        } else {
            loader.bind('Init', function (up) {

                if ($element.attr('data-uptype') == 'qiniu') {
                    $.post("buildview/qiniuToken", function (data) {
                        uploadUrl = data.upload
                        token = data.token
                    });
                }
            });
        }
        loader.bind('BeforeUpload', function (up, file) {
            localStorage.removeItem(file.name);
            if ($element.attr('data-uptype') == 'qiniu') {
                var chunk_size = 4 * 1024 * 1024;
            } else {
                var chunk_size = 2 * 1024 * 1024;
            }
            if (file.size > chunk_size) {
                up.setOption({
                    chunk_size: chunk_size
                })
                if ($element.attr('data-uptype') == 'qiniu') {
                    up.setOption({
                        url: uploadUrl + "/mkblk/" + chunk_size,
                        multipart: false,
                        required_features: "chunks",
                        headers: {
                            Authorization: "UpToken " + token
                        },
                    })
                }
            }
        });
        loader.bind('ChunkUploaded', function (up, file, info) {
            if (parseInt(info.status) === 200) {
                try {
                    var res = JSON.parse(info.response);
                } catch (e) {
                    up.stop();
                    $.msg.error('上传失败');
                    return false;
                }
                if ($element.attr('data-uptype') == 'qiniu') {
                    var leftSize = info.total - info.offset;
                    var chunk_size = up.getOption && up.getOption("chunk_size");
                    if (leftSize < chunk_size) {
                        up.setOption({
                            url: uploadUrl + "/mkblk/" + leftSize
                        });
                    }
                    up.setOption({
                        headers: {
                            Authorization: "UpToken " + token
                        }
                    });
                    // 更新本地存储状态
                    var localFileInfo = JSON.parse(localStorage.getItem(file.name)) || [];
                    localFileInfo[indexCount] = {
                        ctx: res.ctx,
                        time: new Date().getTime(),
                        offset: info.offset,
                        percent: file.percent
                    };
                    indexCount++;
                    localStorage.setItem(file.name, JSON.stringify(localFileInfo));
                }else{
                    if (!res.uploaded) {
                        up.stop();
                        $.msg.error(res.info || res.error.message || '文件上传出错！');
                    }
                }
            } else {
                up.stop();
                $.msg.error('上传失败');
            }

        });
        loader.bind('FilesAdded', function () {
            loader.start();
            index = $.msg.loading('上传进度 <span data-upload-progress></span>');
        });
        loader.bind('UploadProgress', function (up, file) {
            $('[data-upload-progress]').html(file.percent + '%');
        });
        loader.bind('FileUploaded', function (up, file, res) {
            if (parseInt(res.status) === 200) {
                if ($element.attr('data-uptype') == 'qiniu' && up.getOption("chunk_size") > 0) {
                    var ctx = []
                    var id = file.id
                    var local = JSON.parse(localStorage.getItem(file.name))
                    for (var i = 0; i < local.length; i++) {
                        ctx.push(local[i].ctx)
                    }
                    var index = file.name.lastIndexOf(".");
                    var suffix = file.name.substr(index + 1);
                    var filename = hex_md5(file.name + new Date().getTime()) + '.' + suffix;
                    filename = Base64.encode(filename);
                    $.ajax({
                        url: uploadUrl + '/mkfile/' + file.size + '/key/' + filename,
                        type: "POST",
                        headers: {
                            Authorization: "UpToken " + token,
                            "content-type": "text/plain",
                        },
                        data: ctx.join(","),
                        success: function (ret) {
                            if (typeof UploadedHandler === 'function') {
                                UploadedHandler(ret.filename, ret.url);
                            } else {
                                var field = $element.data('field') || 'file';
                                $('[name="' + field + '"]').val(ret.url).trigger('change');
                            }
                        }, fail: function () {
                            $.msg.error('上传失败');
                        }
                    })
                } else {
                    try {
                        var ret = JSON.parse(res.response);
                    } catch (e) {
                        up.stop();
                        $.msg.error('上传失败');
                        return false;
                    }
                    if (ret.uploaded) {
                        if (typeof UploadedHandler === 'function') {
                            UploadedHandler(ret.filename, ret.url);
                        } else {
                            var field = $element.data('field') || 'file';
                            $('[name="' + field + '"]').val(ret.url).trigger('change');
                        }
                    } else {
                        $.msg.error(ret.info || ret.error.message || '文件上传出错！');
                    }
                }
            }
        });
        loader.bind('Error', function () {
            $.msg.error('上传失败');
        });
        loader.bind('UploadComplete', function () {
            $.msg.close(index), $element.html($element.data('html'));
            if (typeof CompleteHandler === 'function') {
                CompleteHandler();
            }
        });
        $element.data('html', $element.html()), loader.init();
        return $element.data('uploader', loader), loader;
    }
});