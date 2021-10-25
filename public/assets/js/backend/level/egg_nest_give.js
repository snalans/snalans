define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'level/egg_nest_give/index' + location.search,
                    add_url: 'level/egg_nest_give/add',
                    edit_url: 'level/egg_nest_give/edit',
                    del_url: 'level/egg_nest_give/del',
                    multi_url: 'level/egg_nest_give/multi',
                    import_url: 'level/egg_nest_give/import',
                    table: 'egg_nest_give',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'level', title: __('Level')},
                        {field: 'nest_kind_id', title: __('Nest_kind_id')},
                        {field: 'number', title: __('Number')},
                        {field: 'eggnestkind.name', title: __('Eggnestkind.name'), operate: 'LIKE'},
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