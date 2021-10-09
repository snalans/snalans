define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/attestation/index' + location.search,
                    add_url: 'user/attestation/add',
                    edit_url: 'user/attestation/edit',
                    del_url: 'user/attestation/del',
                    multi_url: 'user/attestation/multi',
                    import_url: 'user/attestation/import',
                    table: 'egg_attestation',
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
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user.username', title: __('User.username'), operate: 'LIKE'},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'id_card', title: __('Id_card'), operate: 'LIKE'},
                        {field: 'front_img', title: __('Front_img'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'reverse_img', title: __('Reverse_img'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'hand_img', title: __('Hand_img'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'hands_img', title: __('Hands_img'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'remark', title: __('Remark'), operate: false,},
                        {field: 'operate', title: __('Operate'), table: table,buttons: [
                            {name: 'edit', text: '审核', title: '审核', icon: 'fa fa-star', classname: 'btn btn-xs btn-primary btn-dialog' ,url:$.fn.bootstrapTable.defaults.extend.edit_url
                                ,hidden:function(row){
                                    if(row.user.is_attestation == 1){ 
                                        return true; 
                                    }
                                },
                                extend: 'data-area=\'["400px", "380px"]\''
                            }
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
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});