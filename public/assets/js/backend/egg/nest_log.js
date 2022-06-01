define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/nest_log/index' + location.search,
                    add_url: 'egg/nest_log/add',
                    edit_url: 'egg/nest_log/edit',
                    del_url: 'egg/nest_log/del',
                    multi_url: 'egg/nest_log/multi',
                    import_url: 'egg/nest_log/import',
                    table: 'egg_nest_log',
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
                        {field: 'user.mobile', title: '用户手机号', operate: 'LIKE'},
                        {field: 'nest_kind_id', title: "窝类型", formatter:Table.api.formatter.normal, searchList: {1: '白窝', 2: '铜窝', 3: '银窝', 4: '金窝'}},
                        {field: 'type', title: __('Type'),formatter:Table.api.formatter.normal, searchList: {0:'农场主升级赠送', 1: '注册', 2: '直推', 3: '商城'}},
                        {field: 'number', title: __('Number'),operate:false},
                        {field: 'note', title: __('Note'), operate: false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime}
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