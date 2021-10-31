define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/charge_code/index' + location.search,
                    add_url: 'user/charge_code/add',
                    edit_url: 'user/charge_code/edit',
                    del_url: 'user/charge_code/del',
                    multi_url: 'user/charge_code/multi',
                    import_url: 'user/charge_code/import',
                    table: 'egg_charge_code',
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
                        {field: 'user_id', title: __('User_id'),visible:false},                                                {field: 'user.mobile', title: __('User.mobile'), operate: 'LIKE'},
                        {field: 'type', title: __('Type'), formatter: Table.api.formatter.normal, searchList: {1: '支付宝',2: '微信',3:'钱包',4:'银行卡'}},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'mobile', title: __('Mobile'), operate: 'LIKE'},
                        {field: 'open_bank', title: __('Open_bank'), operate: false},
                        {field: 'account', title: __('Account'), operate: 'LIKE'},
                        {field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'add_time', title: __('Add_time'), addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });
            table.on('post-body.bs.table',function(){
                $(".btn-editone").data("area",["450px","800px"]);
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