define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/membership/index' + location.search,
                    add_url: 'user/membership/add',
                    edit_url: 'user/membership/edit',
                    del_url: 'user/membership/del',
                    multi_url: 'user/membership/multi',
                    import_url: 'user/membership/import',
                    table: 'membership_chain',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                search: false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), operate: false},
                        {field: 'serial_number', title: __('User.serial_number'), operate: false},
                        {field: 'mobile', title: __('User.mobile'), operate: false},
                        {field: 'fu.mobile', title: __('User.mobile'), visible: false},
                        {field: 'fu.is_attestation', title: __('是否认证'), visible: false, formatter: Table.api.formatter.normal, searchList: {0: '未认证', 1: '成功',2: '等待审核',3:'失败'}},
                        {field: 'fu.status', title: __('Status'), visible: false, formatter: Table.api.formatter.status, searchList: {normal: __('Normal'), hidden: __('Hidden')}},
                        {field: 'valid_number', title: '个人有效值', operate: false},
                        {field: 'total', title: '团队有效值(不含个人)', operate: false},
                        {field: 'is_attestation', title: __('是否认证'), operate: false, formatter: Table.api.formatter.normal, searchList: {0: '未认证', 1: '成功',2: '等待审核',3:'失败'}},
                        {field: 'status', title: __('Status'), operate: false, formatter: Table.api.formatter.status, searchList: {normal: __('Normal'), hidden: __('Hidden')}},
                        {field: 'operate', title: __('Operate'), table: table,buttons: [
                            {name: 'charge', text: '查看明细', title: '会有列表', icon: 'fa fa-list', classname: 'btn btn-xs btn-info btn-addtabs' ,url:function(row){
                                return 'user/user?puser.mobile='+row.mobile
                            }},
                        ], events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
        charge: function () {
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