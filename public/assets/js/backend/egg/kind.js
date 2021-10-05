define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            $(".btn-add").data("area",["400px","400px"]);
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/kind/index' + location.search,
                    add_url: 'egg/kind/add',
                    edit_url: 'egg/kind/edit',
                    del_url: 'egg/kind/del',
                    multi_url: 'egg/kind/multi',
                    import_url: 'egg/kind/import',
                    table: 'egg_kind',
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
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });
            table.on('post-body.bs.table',function(){
                $(".btn-editone").data("area",["400px","400px"]);
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