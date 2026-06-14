if (typeof vemedia_js_flag !== 'undefined' && vemedia_js_flag == "page") {
    jQuery("#vemedia-upload-one").unbind('click').bind('click', sys_ajax);
}

function sys_ajax() {
    jQuery.ajax({
        type: "POST",
        async: true,
        url: vemedia_ajax_url,
        data: {
            action: 'vemedia_upload_one',
            post_id: post_id
        },
        success: function (res) {
            console.log(res.slice(0, -1));
            alert('替换成功');
        },
        error: function (err) {
            console.log(err);
            alert('替换失败');
        }
    });
}
