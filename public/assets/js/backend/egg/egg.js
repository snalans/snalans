define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/egg/index' + location.search,
                    add_url: 'egg/egg/add',
                    edit_url: 'egg/egg/edit',
                    del_url: 'egg/egg/del',
                    multi_url: 'egg/egg/multi',
                    import_url: 'egg/egg/import',
                    table: 'egg',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                dblClickToEdit:false,
                search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), operate:false},
                        {field: 'user.mobile', title: __('User.mobile'), operate: 'LIKE'},
                        {field: 'kind_id', title: "蛋名称", formatter:Table.api.formatter.normal, searchList: {1: '白蛋', 2: '铜蛋', 3: '银蛋', 4: '金蛋', 5: '彩蛋', 6: '红蛋'}},
                        {field: 'number', title: __('Number'), operate:false, sortable: true},
                        {field: 'freezing', title: __('Freezing'), operate:false},
                        {field: 'hatchable', title: __('Hatchable'), operate:false},
                        {field: 'frozen', title: __('Frozen'), operate:false},
                        {field: 'point', title: __('Point'), operate:false, sortable: true},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });
            table.on('post-body.bs.table',function(){
                $(".btn-editone").data("area",["800px","500px"]);
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