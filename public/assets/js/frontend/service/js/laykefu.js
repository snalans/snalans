// 创建一个Socket实例
var protocol = location.protocol === 'https:' ? 'wss://' : 'ws://';
socket = new ReconnectingWebSocket(protocol + uinfo.socket_server);//创建Socket实例 打开Socket 
socket.reconnectInterval = 2000;
//每50秒ping服务器
var heart = "";

socket.onopen = function (res) {
    // 登录
    var login_data = '{"type":"init", "uid":"' + uinfo.id + '", "name" : "' + uinfo.nickname + '", "avatar" : "'
        + uinfo.avatar + '", "group": "' + uinfo.group + '"}';
    socket.send(login_data);
   
    layui.use(['layer'], function () {
        var layer = layui.layer;
        layer.ready(function () {
            layer.msg('连接中...', {time: 2000});
        });
    });
    
    heart = setInterval(function(){
        socket.send('{"type":"ping"}');
    },50000);
    heart
};

// 监听消息
socket.onmessage = function (res) {
    var data = eval("(" + res.data + ")");
    switch (data['message_type']) {
        // 服务端ping客户端
        case 'ping':
            console.log(data.data)
            break;
        // 添加用户
        case 'connect':
            $("audio")[0].play()
            addUser(data.data.user_info);
            break;
        // 显示用户
        case 'show_users':
            showUser(data.data.user_info);
            break;
        // 移除访客到主面板
        case 'delUser':
            delUser(data.data);
            break;
        // 监测聊天数据
        case 'chatMessage':
            showUserMessage(data.data, data.data.content);
            break;
        case 'wait':
            $("#u-" + data.data.to_id).append('<li style="font-size: 1rem;min-height: 0px;text-align: center;padding:0px;">'+data.data.content+'</li>');
            wordBottom();
            break;
        case 'history':
            showChatLog(data);
            break;
    }
};

// 断线重连
socket.onclose = function(err){
    clearInterval(heart)
};

// 监听失败
socket.onerror = function(err){    
    layer.alert('连接失败,请联系管理员', {icon: 2, title: '错误提示'});
};

$(function () {  

    // 监听快捷键发送
    document.getElementById('msg-area').addEventListener('keydown', function (e) {
        if (e.keyCode != 13) return;
        e.preventDefault();  // 取消事件的默认动作
        sendMessage();
    });

    // 点击表情
    var index;
    $("#face").click(function (e) {
        e.stopPropagation();
        layui.use(['layer'], function () {
            var layer = layui.layer;

            var isShow = $(".layui-laykefu-face").css('display');
            if ('block' == isShow) {
                layer.close(index);
                return;
            }
            var height = $(".chat-box").height() - 110;
            layer.ready(function () {
                index = layer.open({
                    type: 1,
                    offset: [height + 'px', $(".layui-side").width() + 'px'],
                    shade: false,
                    title: false,
                    closeBtn: 0,
                    area: '395px',
                    content: showFaces()
                });
            });
        });
    });

    $(document).click(function (e) {
        layui.use(['layer'], function () {
            var layer = layui.layer;
            if (isShow) {
                layer.close(index);
                return false;
            }
        });
    });

    // 发送消息
    $("#send").click(function () {
        $("#user_list").prepend($("li.layui-nav-item.active"))
        sendMessage();
    });

    // hover用户
    $(".layui-unselect li").hover(function () {
        $(this).find('i').show();
    }, function () {
        $(this).find('i').hide();
    });

    // 检测滚动，异步加载更多聊天数据
    $(".chat-box").scroll(function () {
        var top = $(".chat-box").scrollTop();
    });

    $("#search_msg").on("change",function(){
        var mobile = $(this).val();
        $.ajax({
            url:'../../user/user/getUserInfo',
            type:'post',
            data:{"mobile":mobile},
            dataType:'json',
            success:function(data){
                if(data.code == 1){
                    addUser(data.data)
                }else{
                    layer.msg(data.msg)
                }
            }
        })
    })
});

var isShow = false;

// 图片 文件上传
layui.use(['upload', 'layer'], function () {
    var upload = layui.upload;
    var layer = layui.layer;

    // 执行实例
    var uploadInstImg = upload.render({
        elem: '#image' // 绑定元素
        , accept: 'images'
        , exts: 'jpg|jpeg|png|gif'
        , url: '/EGGsovVLOop.php/ajax/upload' // 上传接口
        , done: function (res) {

            sendMessage('img[' + res.data.fullurl + ']');
            showBigPic();
        }
        , error: function () {
            // 请求异常回调
        }
    });

    var uploadInstFile = upload.render({
        elem: '#file' // 绑定元素
        , accept: 'file'
        , exts: 'zip|rar'
        , url: '/EGGsovVLOop.php/ajax/upload' // 上传接口
        , done: function (res) {
            sendMessage('file(' + res.data.src + ')[' + res.msg + ']');
        }
        , error: function () {
            // 请求异常回调
        }
    });
});

// 展示表情数据
function showFaces() {
    isShow = true;
    var alt = getFacesIcon();
    var _html = '<div class="layui-laykefu-face"><ul class="layui-clear laykefu-face-list">';
    layui.each(alt, function (index, item) {
        _html += '<li title="' + item + '" onclick="checkFace(this)"><img src="/assets/js/frontend/service/layui/images/face/' + index + '.gif" /></li>';
    });
    _html += '</ul></div>';

    return _html;
}

// 选择表情
function checkFace(obj) {
    var word = $(".msg-area").val() + ' face' + $(obj).attr('title') + ' ';
    $(".msg-area").val(word).focus();
}

// 发送消息
function sendMessage(sendMsg) {
    var msg = (typeof(sendMsg) == 'undefined') ? $(".msg-area").val() : sendMsg;
    if ('' == msg) {
        layui.use(['layer'], function () {
            var layer = layui.layer;
            return layer.msg('请输入回复内容', {time: 1000});
        });
        return false;
    }

    var word = msgFactory(msg, 'mine', uinfo);
    var uid = $("#active-user").attr('data-id');
    var uname = $("#active-user").attr('data-name');

    socket.send(JSON.stringify({
        type: 'chatMessage',
        data: {to_id: uid, to_name: uname, content: msg, from_name: uinfo.nickname,
            from_id: uinfo.id, from_avatar: uinfo.avatar}
    }));

    $("#u-" + uid).append(word);
    $(".msg-area").val('');
    // 滚动条自动定位到最底端
    wordBottom();
}

// 展示客服发送来的消息
function showUserMessage(uinfo, content) {
    if ($('#f-' + uinfo.id).length == 0) {
        addUser(uinfo);
    }

    // 未读条数计数
    if (!$('#f-' + uinfo.id).hasClass('active')) {
        var num = $('#f-' + uinfo.id).find('span:eq(1)').text();
        if (num == '') num = 0;
        num = parseInt(num) + 1;
        $('#f-' + uinfo.id).find('span:eq(1)').removeClass('layui-badge').addClass('layui-badge').text(num);
    }

    var word = msgFactory(content, 'user', uinfo);
    setTimeout(function () {
        $("#u-" + uinfo.id).append(word);
        // 滚动条自动定位到最底端
        wordBottom();

        showBigPic();
    }, 200);
}

// 消息发送工厂
function msgFactory(content, type, uinfo) {
    var _html = '';
    if ('mine' == type) {
        _html += '<li class="laykefu-chat-mine">';
    } else {
        _html += '<li>';
    }
    _html += '<div class="laykefu-chat-user">';
    _html += '<img src="' + uinfo.avatar + '">';
    if ('mine' == type) {
        _html += '<cite><i>' + getDate() + '</i>' + uinfo.nickname + '</cite>';
    } else {
        _html += '<cite>' + uinfo.name + '<i>' + getDate() + '</i></cite>';
    }
    _html += '</div><div class="laykefu-chat-text">' + replaceContent(content) + '</div>';
    _html += '</li>';

    return _html;
}

// 获取日期
function getDate() {
    var d = new Date(new Date());

    return d.getFullYear() + '-' + digit(d.getMonth() + 1) + '-' + digit(d.getDate())
        + ' ' + digit(d.getHours()) + ':' + digit(d.getMinutes()) + ':' + digit(d.getSeconds());
}

//补齐数位
var digit = function (num) {
    return num < 10 ? '0' + (num | 0) : num;
};

// 滚动条自动定位到最底端
function wordBottom() {
    var box = $(".chat-box");
    box.scrollTop(box[0].scrollHeight);
}

// 切换在线用户
function changeUserTab(obj) {
    obj.addClass('active').siblings().removeClass('active');
    wordBottom();
}

// 显示用户到面板
function showUser(data) {
    data.forEach(function(item){
        addUser(item)
    })
}
function copyText(mobile) {
    const input = document.getElementById('copyText');
    input.setAttribute('value', mobile);
    input.select();
    if (document.execCommand('copy')) {
        document.execCommand('copy');
        $(".copyText").html("复制成功")
        setTimeout(function () {
            $(".copyText").html("")
        },1500)
    }
}
// 添加用户到面板
function addUser(data) {
    var add = true;
    $('.layui-nav-item').each(function(i){
        if(parseInt($(this).attr('data-id'))==data.id) {
            add =  false;
        }
    });
    if(add){
        var _html = '<li ondblclick="copyText(' + data.name + ')" class="layui-nav-item" data-id="' + data.id + '" id="f-' + data.id +
            '" data-name="' + data.name + '" data-avatar="' + data.avatar + '" data-ip="' + data.ip + '">';
        _html += '<img src="' + data.avatar + '">';
        _html += '<span class="user-name">' + data.name + '</span>';
        _html += '<span class="layui-badge" style="margin-left:5px">0</span>';
        _html += '<i class="layui-icon close" onclick="signOut(' + data.id + ')">ဇ</i>';
        _html += '</li>';
    
        // 添加左侧列表
        $("#user_list").append(_html);
    
        // 如果没有选中人，选中第一个
        var hasActive = 0;
        $("#user_list li").each(function(){
            if($(this).hasClass('active')){
                hasActive = 1;
            }
        });
    
        var _html2 = '';
        _html2 += '<ul id="u-' + data.id + '">';
        _html2 += '</ul>';
        // 添加主聊天面板
        $('.chat-box').append(_html2);
    
        if(0 == hasActive){
            $("#user_list").find('li').eq(0).addClass('active').find('span:eq(1)').removeClass('layui-badge').text('');
            $("#u-" + data.id).show();
    
            var id = $(".layui-unselect").find('li').eq(0).data('id');
            var name = $(".layui-unselect").find('li').eq(0).data('name');
            var ip = $(".layui-unselect").find('li').eq(0).data('ip');
            var avatar = $(".layui-unselect").find('li').eq(0).data('avatar');
    
            // 设置当前会话用户
            $("#active-user").attr('data-id', id).attr('data-name', name).attr('data-avatar', avatar).attr('data-ip', ip);
    
            $("#f-user").val(name);
            $("#f-ip").val(ip);
            getChatLog(data.id, 1);
        }
        checkUser();
    }

}
// 强制退出
function signOut(uid) {
    if(confirm("将其禁言？")){        
        socket.send('{"type":"signOut","to_id":"'+ uid +'"}');
    }
}
// 操作新连接用户的 dom操作
function checkUser() {

    $(".layui-unselect").find('li').unbind("click"); // 防止事件叠加
    // 切换用户
    $(".layui-unselect").find('li').bind('click', function () {
        changeUserTab($(this));
        var uid = $(this).data('id');
        var avatar = $(this).data('avatar');
        var name = $(this).data('name');
        var ip = $(this).data('ip');
        // 展示相应的对话信息
        $('.chat-box ul').each(function () {
            if ('u-' + uid == $(this).attr('id')) {
                $(this).addClass('show-chat-detail').siblings().removeClass('show-chat-detail').attr('style', '');
                return false;
            }
        });

        // 去除消息提示
        $(this).find('span').eq(1).removeClass('layui-badge').text('');

        // 设置当前会话的用户
        $("#active-user").attr('data-id', uid).attr('data-name', name).attr('data-avatar', avatar).attr('data-ip', ip);

        // 右侧展示详情
        $("#f-user").val(name);
        $("#f-ip").val(ip);

        getChatLog(uid, 1);

        wordBottom();
    });
}

// 删除用户聊天面板
function delUser(data) {
    $("#f-" + data.id).remove(); // 清除左侧的用户列表
    $('#u-' + data.id).remove(); // 清除右侧的聊天详情
}

// 发送快捷语句
function sendWord(obj) {
    var msg = $(obj).data('word');
    sendMessage(msg);
}

// 获取聊天记录
function getChatLog(uid, page, flag) {
    socket.send('{"type":"chatRecord","to_id":"'+ uid +'","page":"'+ page +'"}');
}

// 显示聊天历史
function showChatLog(res) 
{
    if(res.page * 15 > res.total){
        var _html = '<div class="layui-flow-more">没有更多了</div>';
    }else{
        var _html = '<div class="layui-flow-more"><a href="javascript:;" data-page="' + (parseInt(res.page) + 1)
            + '" onclick="getMore(this)"><cite>更多记录</cite></a></div>';
    }

    var len = res.data.length,show_id=res.to_id;

    for(var i = 0; i < len; i++)
    {
        var v = res.data[len - i - 1];
        if ('mine' == v.type) {
            _html += '<li class="laykefu-chat-mine">';
        } else {
            _html += '<li>';
        }
        _html += '<div class="laykefu-chat-user">';
        _html += '<img src="' + v.from_avatar + '">';
        if ('mine' == v.type) {
            _html += '<cite><i>' + v.time_line + '</i>' + v.from_name + '</cite>';
        } else {
            _html += '<cite>' + v.from_name + '<i>' + v.time_line + '</i></cite>';
        }
        _html += '</div><div class="laykefu-chat-text">' + replaceContent(v.content) + '</div>';
        _html += '</li>';
    }
    setTimeout(function () {
        // 滚动条自动定位到最底端
        if(res.page <= 1){
            $("#u-" + show_id).html(_html);
            wordBottom();
        }else{
            $("#u-" + show_id).prepend(_html);
        }
        showBigPic();
    }, 200);        
    
}

// 显示大图
function showBigPic(){

    $(".layui-laykefu-photos").on('click', function () {
        var src = this.src;
        layer.photos({
            photos: {
                data: [{
                    "alt": "大图模式",
                    "src": src
                }]
            }
            , shade: 0.5
            , closeBtn: 2
            , anim: 0
            , resize: false
            , success: function (layero, index) {

            }
        });
    });
}

// 获取更多的的记录
function getMore(obj){
    $(obj).remove();
    var page = $(obj).attr('data-page');
    var uid = $(".layui-unselect").find('.active').data('id');
    getChatLog(uid, page, 1);
}

