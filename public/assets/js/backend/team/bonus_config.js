define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            $(".btn-add").data("area",["800px","400px"]);
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'team/bonus_config/index' + location.search,
                    add_url: 'team/bonus_config/add',
                    edit_url: 'team/bonus_config/edit',
                    del_url: 'team/bonus_config/del',
                    multi_url: 'team/bonus_config/multi',
                    import_url: 'team/bonus_config/import',
                    table: 'bonus_config',
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
                        {field: 'id', title: __('Id'),operate:false},
                        {field: 'level', title: __('Level'), formatter:Table.api.formatter.normal, searchList: {1: '一级农场主', 2: '二级农场主', 3: '三级农场主', 4: '四级农场主'}},
                        {field: 'kind_id', title: __('Kind_id'), formatter:Table.api.formatter.normal, searchList: {1: '白蛋', 2: '铜蛋', 3: '银蛋'}},
                        {field: 'point', title: __('Point'), operate:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            table.on('post-body.bs.table',function(){
                $(".btn-editone").data("area",["800px","400px"]);
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