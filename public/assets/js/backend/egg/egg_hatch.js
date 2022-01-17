define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/egg_hatch/index' + location.search,
                    add_url: 'egg/egg_hatch/add',
                    edit_url: 'egg/egg_hatch/edit',
                    del_url: 'egg/egg_hatch/del',
                    multi_url: 'egg/egg_hatch/multi',
                    import_url: 'egg/egg_hatch/import',
                    table: 'egg_hatch',
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
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user.serial_number', title: __('User.serial_number'), operate: 'LIKE'},
                        {field: 'user.mobile', title: __('User.mobile'), operate: 'LIKE'},
                        {field: 'eggnestkind.name', title: __('Eggnestkind.name'), operate: false},
                        {field: 'eggkind.name', title: __('Eggkind.name'), operate: false},
                        {field: 'hatch_num', title: __('Hatch_num'), operate:false},
                        {field: 'shape', title: __('Shape'), operate:false, formatter: Table.api.formatter.normal, searchList: {0: '蛋', 1: '鸡', 2: '鸡', 5: '无'}},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.normal, searchList: {0: '孵化中', 1: '空闲'}},
                        {field: 'is_reap', title: __('Is_reap'), operate:false, formatter: Table.api.formatter.normal, searchList: {0: '无', 1: '可收获', 2: '可收获'}},
                        {field: 'position', title: __('Position'), operate:false},
                        {field: 'uptime', title: __('Uptime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'createtime', title: __('Createtime'), table: table,buttons: [
                            {
                                name: 'ajax',
                                text: '清空',
                                title: "删除窝里面的蛋或鸡",
                                classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                icon: 'fa fa-magic',
                                url: 'egg/egg_hatch/reduction',
                                confirm: '确认发送',
                                success: function (data, ret) {
                                    Layer.alert(ret.msg);
                                    //如果需要阻止成功提示，则必须使用return false;
                                    return false;
                                },
                                error: function (data, ret) {
                                    console.log(data, ret);
                                    Layer.alert(ret.msg);
                                    return false;
                                }
                            }
                        ], operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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