define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'level/egg_give/index' + location.search,
                    add_url: 'level/egg_give/add',
                    edit_url: 'level/egg_give/edit',
                    del_url: 'level/egg_give/del',
                    multi_url: 'level/egg_give/multi',
                    import_url: 'level/egg_give/import',
                    table: 'egg_give',
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
                        {field: 'id', title: __('Id'),operate:false},
                        {field: 'level', title: __('Level'),operate:false},
                        {field: 'kind_id', title: __('Kind_id')},
                        {field: 'number', title: __('Number'),operate:false},
                        {field: 'eggkind.name', title: __('Eggkind.name'), operate: 'LIKE'},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
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