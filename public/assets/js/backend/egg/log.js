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
                        {field: 'hatch_id', title: '窝ID', operate: 'LIKE'},
                        {field: 'eggkind.name', title: __('Eggkind.name'), operate: 'LIKE'},
                        {field: 'type', title: __('Type'), formatter: Table.api.formatter.normal, searchList: {0: '农场', 1: '订单', 2: '互转', 3: '合成', 4: '管理员操作', 5: '积分兑换', 9: '手续费',10:'农场主等级升级',11:'农场主等级降级'}},
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
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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

            table.on('load-success.bs.table', function (e, data) {
                //这里我们手动设置底部的值
                $("#egg1").text(data.extend.egg1);
                $("#egg2").text(data.extend.egg2);
                $("#egg3").text(data.extend.egg3);

            });

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
        }
    };
    return Controller;
});