define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/number_log/index' + location.search,
                    add_url: 'egg/number_log/add',
                    edit_url: 'egg/number_log/edit',
                    del_url: 'egg/number_log/del',
                    multi_url: 'egg/number_log/multi',
                    import_url: 'egg/number_log/import',
                    table: 'egg_valid_number_log',
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
                        {field: 'user_id', title: __('User_id'), operate:false},
                        {field: 'user.serial_number', title: __('User.serial_number'), operate: 'LIKE'},
                        {field: 'user.mobile', title: __('User.mobile'), operate: 'LIKE'},
                        {field: 'origin_user_id', title: __('Origin_user_id')},
                        {field: 'type', title: __('Type'), formatter: Table.api.formatter.status, searchList: {1: '孵化', 2: '购买蛋', 3: '管理员'}},
                        {field: 'number', title: __('Number'), operate:false},
                        {field: 'before', title: __('Before'), operate:false},
                        {field: 'after', title: __('After'), operate:false},
                        {field: 'note', title: __('Note'), operate:false},
                        {field: 'add_time', title: __('Add_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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