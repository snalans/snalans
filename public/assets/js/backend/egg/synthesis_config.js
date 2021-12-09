define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            $(".btn-add").data("area",["400px","450px"]);
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/synthesis_config/index' + location.search,
                    add_url: 'egg/synthesis_config/add',
                    edit_url: 'egg/synthesis_config/edit',
                    del_url: 'egg/synthesis_config/del',
                    multi_url: 'egg/synthesis_config/multi',
                    import_url: 'egg/synthesis_config/import',
                    table: 'egg_synthesis_config',
                }
            });

            var table = $("#table");

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
                        {field: 'eggkind.name', title: __('Eggkind.name'), operate: 'LIKE'},
                        {field: 'cheggkind.name', title: __('Cheggkind.name'), operate: 'LIKE'},
                        {field: 'number', title: __('Number'), operate:false},
                        {field: 'per_reward', title: __('Per_reward'), operate:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });
            table.on('post-body.bs.table',function(){
                $(".btn-editone").data("area",["400px","450px"]);
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
        }
    };
    return Controller;
});