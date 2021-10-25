define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'team/team_bonus/index' + location.search,
                    add_url: 'team/team_bonus/add',
                    edit_url: 'team/team_bonus/edit',
                    del_url: 'team/team_bonus/del',
                    multi_url: 'team/team_bonus/multi',
                    import_url: 'team/team_bonus/import',
                    table: 'team_bonus',
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
                        {field: 'bonus_score', title: __('Bonus_score')},
                        {field: 'total_number', title: __('Total_number')},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'last_time', title: __('Last_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'add_time', title: __('Add_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'level', title: __('Level')},
                        {field: 'score', title: __('Score')},
                        {field: 'is_issue', title: __('Is_issue')},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'pay_time', title: __('Pay_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'title', title: __('Title'), operate: 'LIKE'},
                        {field: 'team_rate', title: __('Team_rate'), operate:'BETWEEN'},
                        {field: 'team_score', title: __('Team_score')},
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