define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/user/index',
                    see_url: 'user/user/see',
                    add_url: 'user/user/add',
                    edit_url: 'user/user/edit',
                    status_url: 'user/user/status',
                    del_url: 'user/user/del',
                    multi_url: 'user/user/multi',
                    table: 'user',
                }
            });

            var table = $("#table");

            //在普通搜索渲染后
            table.on('post-common-search.bs.table', function (event, table) {
                var form = $("form", table.$commonsearch);
                $("input[name='level']", form).addClass("selectpage").data("source", "level/user_level_config/index").data("primaryKey", "level").data("field", "title");
                Form.events.cxselect(form);
                Form.events.selectpage(form);
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'user.id',
                searchFormVisible: true,
                dblClickToEdit:false,
                search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), sortable: true},
                        {field: 'mobile', title: __('Mobile'), operate: 'LIKE'},
                        {field: 'serial_number', title: __('Serial_number'), operate: 'LIKE'},
                        {field: 'puser.mobile', title: __('User.mobile'), operate: 'LIKE'},
                        {field: 'level', title: __('Level'), visible:false},
                        {field: 'levels.title', title: __('Level'), operate:false},
                        {field: 'valid_number', title: __('Valid_number')},
                        {field: 'total_valid_number', title: '团队有效值', operate: false},
                        {field: 'team_number', title: '直推人数', operate: false},
                        {field: 'is_attestation', title: __('是否认证'), formatter: Table.api.formatter.normal, searchList: {0: '未认证', 1: '成功',2: '等待审核',3:'失败'}},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.status, searchList: {normal: __('Normal'), hidden: __('Hidden')}},
                        {field: 'operate', title: __('Operate'), table: table,buttons: [
                            {name: 'see', text: '查看', title: '查看详情', icon: 'fa fa-list', classname: 'btn btn-xs btn-primary btn-dialog' ,url:$.fn.bootstrapTable.defaults.extend.see_url},
                            {name: 'edit', text: '修改密码', title: '修改密码', icon: 'fa fa-list', classname: 'btn btn-xs btn-primary btn-editone btn-dialog' ,url:$.fn.bootstrapTable.defaults.extend.edit_url},
                            {name: 'assets', text: '资产', title: '蛋资产', icon: 'fa fa-list', classname: 'btn btn-xs btn-primary btn-dialog' ,url:function(row){
                                return 'egg/egg/index?user.mobile='+row.mobile
                            }},
                            {name: 'charge', text: '收款信息', title: '收款信息', icon: 'fa fa-bank', classname: 'btn btn-xs btn-info btn-dialog' ,url:function(row){
                                return 'user/charge_code/index?user_id='+row.id
                            }},
                            {name: 'level', text: '等级', title: '修改等级', icon: 'fa fa-arrows-v', classname: 'btn btn-xs btn-warning btn-dialog' ,url:function(row){
                                return 'user/user/level?ids='+row.id
                            }},
                            {name: 'edit', text: '认证', title: '认证信息', icon: 'fa fa-list', classname: 'btn btn-xs btn-success btn-dialog' ,url:function(row){
                                return 'user/attestation/index?user_id='+row.id
                            }},
                            {name: 'status', text: '审核', title: '编辑用户状态', icon: 'fa fa-star', classname: 'btn btn-xs btn-success btn-dialog' ,url:$.fn.bootstrapTable.defaults.extend.status_url
                                ,extend: 'data-area=\'["500px", "450px"]\''
                            }
                        ], events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            table.on('post-body.bs.table',function(){
                $(".btn-editone").data("area",["400px","400px"]);
            })

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        see: function () {
            Controller.api.bindevent();
        },
        level: function () {
            Controller.api.bindevent();
        },
        add: function () {
            Controller.api.bindevent();
        },
        charge: function () {
            Controller.api.bindevent();
        },
        assets: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        status: function () {
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