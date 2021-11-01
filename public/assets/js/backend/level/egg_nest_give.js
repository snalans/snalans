define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            $(".btn-add").data("area",["800px","400px"]);
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'level/egg_nest_give/index' + location.search,
                    add_url: 'level/egg_nest_give/add',
                    edit_url: 'level/egg_nest_give/edit',
                    del_url: 'level/egg_nest_give/del',
                    multi_url: 'level/egg_nest_give/multi',
                    import_url: 'level/egg_nest_give/import',
                    table: 'egg_nest_give',
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
                        {field: 'level.title', title: __('Level'),operate:false},
                        {field: 'eggnestkind.name', title: __('Eggnestkind.name'), operate: 'LIKE'},
                        {field: 'number', title: __('Number'),operate:false},
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