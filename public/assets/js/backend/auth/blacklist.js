define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'auth/blacklist/index' + location.search,
                    add_url: 'auth/blacklist/add',
                    edit_url: 'auth/blacklist/edit',
                    del_url: 'auth/blacklist/del',
                    multi_url: 'auth/blacklist/multi',
                    import_url: 'auth/blacklist/import',
                    table: 'blacklist',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                search: false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'),operate:false},
                        {field: 'type', title: __('Type'), formatter: Table.api.formatter.normal, searchList: {0: '手机号',1: '支付宝',2: '微信',3:'钱包',4:'银行卡',5:'钱包',6:'身份证'}},
                        {field: 'param', title: __('Param'), operate: 'LIKE'},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            $("#c-blacklist").on("click", function() {
                layer.confirm('确认获取黑名单信息？', {
                    btn: ['确定','取消'] //按钮
                }, function(index){
                    layer.close(index);
                    Fast.api.ajax({
                       url:'auth/blacklist/getInfo',
                    }, function(data, ret){
                        table.bootstrapTable('refresh');
                    })
                }, function(){
                    layer.close();
                });
                
            })
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