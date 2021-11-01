define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/kind/index' + location.search,
                    add_url: 'egg/kind/add',
                    edit_url: 'egg/kind/edit',
                    del_url: 'egg/kind/del',
                    multi_url: 'egg/kind/multi',
                    import_url: 'egg/kind/import',
                    table: 'egg_kind',
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
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'ch_image', title: __('Ch_image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'bg_image', title: __('Bg_image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'valid_number', title: __('Valid_number'), operate: false},
                        {field: 'point', title: __('Point'), operate: false},
                        {field: 'price', title: __('Price'), operate: false},
                        {field: 'rate_config', title: __('Rate_config'), operate: false},
                        {field: 'unit', title: __('Unit'), operate: false},
                        {field: 'stock', title: __('Stock'), operate: false},
                        {field: 'weigh', title: __('Weigh'), operate: false},
                        {field: 'status', title: "APP首页是否显示", formatter:Table.api.formatter.toggle, searchList: {1: '显示', 0: '不显示'}},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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