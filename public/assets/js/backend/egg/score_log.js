define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/score_log/index' + location.search,
                    add_url: 'egg/score_log/add',
                    edit_url: 'egg/score_log/edit',
                    del_url: 'egg/score_log/del',
                    multi_url: 'egg/score_log/multi',
                    import_url: 'egg/score_log/import',
                    table: 'egg_score_log',
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
                        {field: 'user.mobile', title: __('User.mobile'), operate: 'LIKE'},
                        {field: 'type', title: __('Type'), formatter: Table.api.formatter.normal, searchList: {1: '团队分红', 2: '积分兑换蛋', 3: '管理员操作'}},
                        {field: 'kind_id', title: __('Kind_id'), formatter: Table.api.formatter.normal, searchList: {1: '白蛋', 2: '铜蛋', 3: '银蛋', 4: '金蛋', 5: '彩蛋'}},
                        {field: 'score', title: __('Score'), operate:'BETWEEN'},
                        {field: 'memo', title: __('Memo'), operate: false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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