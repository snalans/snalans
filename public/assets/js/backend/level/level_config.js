define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'level/level_config/index' + location.search,
                    add_url: 'level/level_config/add',
                    edit_url: 'level/level_config/edit',
                    del_url: 'level/level_config/del',
                    multi_url: 'level/level_config/multi',
                    import_url: 'level/level_config/import',
                    table: 'user_level_config',
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
                        {field: 'title', title: __('Title'), operate: 'LIKE'},
                        {field: 'number', title: __('Number')},
                        {field: 'team_number', title: __('Team_number')},
                        {field: 'valid_number', title: __('Valid_number')},
                        {field: 'user_level', title: __('User_level')},
                        {field: 'user_number', title: __('User_number')},
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