define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/valid_mobile/index' + location.search,
                    add_url: 'user/valid_mobile/add',
                    edit_url: 'user/valid_mobile/edit',
                    del_url: 'user/valid_mobile/del',
                    multi_url: 'user/valid_mobile/multi',
                    import_url: 'user/valid_mobile/import',
                    table: 'egg_valid_mobile',
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
                        {field: 'mobile', title: __('Mobile'), operate: 'LIKE'},
                        {field: 'num', title: '验证次数', operate: false},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.toggle, searchList: {1: '开启', 0: '关闭'}},
                        {field: 'valid_time', title: '最近一次验证时间', operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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