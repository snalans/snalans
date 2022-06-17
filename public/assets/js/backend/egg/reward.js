define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/reward/index' + location.search,
                    add_url: 'egg/reward/add',
                    edit_url: 'egg/reward/edit',
                    del_url: 'egg/reward/del',
                    multi_url: 'egg/reward/multi',
                    import_url: 'egg/reward/import',
                    table: 'egg_eliminate_rewards',
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
                        {field: 'kind_id', title: __('Kind_id'), formatter: Table.api.formatter.normal, searchList: {1: '白蛋', 2: '铜蛋', 3: '银蛋', 4: '金蛋', 5: '彩蛋', 6: '红蛋'}},
                        {field: 'number', title: __('Number'), operate:false},
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