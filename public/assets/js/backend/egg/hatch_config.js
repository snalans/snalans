define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            $(".btn-add").data("area",["800px","500px"]);
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/hatch_config/index' + location.search,
                    add_url: 'egg/hatch_config/add',
                    edit_url: 'egg/hatch_config/edit',
                    del_url: 'egg/hatch_config/del',
                    multi_url: 'egg/hatch_config/multi',
                    import_url: 'egg/hatch_config/import',
                    table: 'egg_hatch_config',
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
                        {field: 'eggkind.name', title: __('Eggkind.name'), operate: 'LIKE'},
                        {field: 'hatch_cycle', title: __('Hatch_cycle'), operate:false},
                        {field: 'grow_cycle', title: __('Grow_cycle'), operate:false},
                        {field: 'raw_cycle', title: __('Raw_cycle'), operate:false},
                        {field: 'max', title: __('Max'), operate:false},
                        {field: 'new_time', title: __('启动时间'), operate:false, addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'add_num', title: __('增量数值'), operate:false},
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