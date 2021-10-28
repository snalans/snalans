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
                    del_url: 'user/user/del',
                    multi_url: 'user/user/multi',
                    table: 'user',
                }
            });

            var table = $("#table");

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
                        {field: 'puser.mobile', title: __('User.mobile'), operate: 'LIKE'},
                        {field: 'serial_number', title: __('Serial_number'), operate: 'LIKE'},
                        {field: 'username', title: __('Username'), operate: 'LIKE'},
                        {field: 'mobile', title: __('Mobile'), operate: 'LIKE'},
                        {field: 'level', title: __('Level'), sortable: true},
                        {field: 'score', title: __('Score')},
                        {field: 'valid_number', title: __('Valid_number')},
                        {field: 'total_valid_number', title: '团队有效值', operate: false},
                        {field: 'team_number', title: '直推人数', operate: false},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.status, searchList: {normal: __('Normal'), hidden: __('Hidden')}},
                        {field: 'is_attestation', title: __('是否认证'), formatter: Table.api.formatter.normal, searchList: {0: '未认证', 1: '成功',2: '等待审核',3:'失败'}},
                        {field: 'operate', title: __('Operate'), table: table,buttons: [
                            {name: 'see', text: '查看', title: '查看详情', icon: 'fa fa-list', classname: 'btn btn-xs btn-primary btn-dialog' ,url:$.fn.bootstrapTable.defaults.extend.see_url},
                            {name: 'edit', text: '修改密码', title: '修改密码', icon: 'fa fa-list', classname: 'btn btn-xs btn-primary btn-editone btn-dialog' ,url:$.fn.bootstrapTable.defaults.extend.edit_url},
                            {name: 'assets', text: '资产', title: '蛋资产', icon: 'fa fa-list', classname: 'btn btn-xs btn-primary btn-dialog' ,url:function(row){
                                return 'egg/egg/index?user.mobile='+row.mobile
                            }},
                            {name: 'edit', text: '认证', title: '认证信息', icon: 'fa fa-list', classname: 'btn btn-xs btn-success btn-dialog' ,url:function(row){
                                return 'user/attestation/index?user_id='+row.id
                            }},
                            {
                                name: 'ajax',
                                text: '拉黑',
                                title: '拉黑',
                                classname: 'btn btn-xs btn-primary btn-stop btn-ajax',
                                icon: 'fa fa-stop',
                                confirm: '确认拉黑？',
                                url: 'user/user/change_status',
                                success: function (data, ret) {
                                    table.bootstrapTable('refresh');
                                    //如果需要阻止成功提示，则必须使用return false;
                                    //return false;
                                },
                                hidden:function(row){
                                    if(row.status == 'hidden'){ 
                                        return true; 
                                    }
                                }
                            },{
                                name: 'ajax',
                                text: '恢复',
                                title: '恢复',
                                classname: 'btn btn-xs btn-success btn-stop btn-ajax',
                                icon: 'fa fa-stop',
                                confirm: '确认恢复正常？',
                                url: 'user/user/change_status',
                                success: function (data, ret) {
                                    table.bootstrapTable('refresh');
                                    //如果需要阻止成功提示，则必须使用return false;
                                    //return false;
                                },
                                hidden:function(row){
                                    if(row.status == 'normal'){ 
                                        return true; 
                                    }
                                }
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
        add: function () {
            Controller.api.bindevent();
        },
        assets: function () {
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