define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            $(".btn-add").data("area",["800px","400px"]);
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/log/index' + location.search,
                    add_url: 'egg/log/add',
                    edit_url: 'egg/log/edit',
                    del_url: 'egg/log/del',
                    multi_url: 'egg/log/multi',
                    import_url: 'egg/log/import',
                    table: 'egg_log',
                }
            });

            var table = $("#table");

            //在普通搜索渲染后
            // table.on('post-common-search.bs.table', function (event, table) {
            //     var form = $("form", table.$commonsearch);
            //     $("input[name='month']", form).addClass("selectpage").data("source", "egg/log/get_month").data("primaryKey", "id").data("field", "name");
            //     Form.events.cxselect(form);
            //     Form.events.selectpage(form);
            // });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), operate:false},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user.serial_number', title: __('User.serial_number'), operate: 'LIKE'},
                        {field: 'user.mobile', title: __('User.mobile'), operate: 'LIKE'},
                        {field: 'hatch_id', title: '窝ID', operate: false},
                        {field: 'kind_id', title: "蛋名称", formatter:Table.api.formatter.normal, searchList: {1: '白蛋', 2: '铜蛋', 3: '银蛋', 4: '金蛋', 5: '彩蛋', 6: '红蛋'}},
                        {field: 'type', title: __('Type'), formatter: Table.api.formatter.normal, searchList: {0: '农场', 1: '订单', 2: '互转', 3: '合成', 4: '管理员操作', 5: '积分兑换', 9: '手续费',10:'农场主等级升级',11:'农场主等级降级',12:'发布商品'}},
                        {field: 'order_sn', title: __('Order_sn'), operate: 'LIKE'},
                        {field: 'before', title: '变动前', operate: false},
                        {field: 'number', title: __('Number'), operate: false,
                            cellStyle:function(value,row,index){
                                let h_css = {}
                                if(parseInt(row.number)>=0){
                                    h_css = {css:{"color":"#0592f0"}};        
                                }else{
                                    h_css = {css:{"color":"#ec7f7f"}};
                                }  
                                return h_css;
                            },
                            formatter:function paramsMatter(value,row,index){
                                var span = document.createElement("span");
                                span.setAttribute("title",value);
                                span.innerHTML = value;
                                return span.outerHTML;
                            }
                        },
                        {field: 'after', title: '变动后', operate: false},
                        {field: 'note', title: __('Note'), operate: false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter:Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table,buttons: [
                            {name: 'edit', text: '修改', title: '添加交易手续费', icon: 'fa fa-pencil', classname: 'btn btn-xs btn-success btn-editone btn-dialog' ,url:$.fn.bootstrapTable.defaults.extend.edit_url,
                                visible:function(row){
                                    if(row.type == 9 && row.user_id == 0){
                                        return true; 
                                    }
                                },
                                extend: 'data-area=\'["800px", "400px"]\''
                            },                            
                        ], events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            $(".btn-rate").on("click",function () {
                Fast.api.ajax({
                   url:'egg/log/get_rate',
                }, function(data, ret){
                    //成功的回调
                    $("#egg1").text(data.egg1);
                    $("#egg2").text(data.egg2);
                    $("#egg3").text(data.egg3);
                    return false;
                }, function(data, ret){
                    //失败的回调
                    layer.msg(ret.msg);
                    return false;
                });

            })

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        },
        today:function(AddDayCount){
            var dd = new Date();
            dd.setDate(dd.getDate() + AddDayCount);
            var y = dd.getFullYear(), m = dd.getMonth()+1, d = dd.getDate();
            if(m < 10){
                m = "0" + m;
            }else{
                m = m;
            }
            if(d < 10){
                d = "0" + d;
            }else{
                d = d;
            }
            return y+"-"+m+"-"+d;
        }
    };
    return Controller;
});