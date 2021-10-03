define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'news/news/index' + location.search,
                    add_url: 'news/news/add',
                    edit_url: 'news/news/edit',
                    del_url: 'news/news/del',
                    multi_url: 'news/news/multi',
                    import_url: 'news/news/import',
                    table: 'egg_news',
                }
            });

            var table = $("#table");

            //在普通搜索渲染后
            table.on('post-common-search.bs.table', function (event, table) {
                var form = $("form", table.$commonsearch);
                $("input[name='eggnewstype.name']", form).addClass("selectpage").data("source", "news/news_type/index").data("primaryKey", "id").data("field", "name").data("weigh", "id desc");
                Form.events.cxselect(form);
                Form.events.selectpage(form);
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), operate: false},
                        {field: 'eggnewstype.name', title: __('Eggnewstype.name'), formatter: Table.api.formatter.search},
                        {field: 'title', title: __('Title'), operate: 'LIKE'},
                        {field: 'description', title: __('Description'), operate: false},
                        {field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'url', title: __('Url'), operate: false, formatter: Table.api.formatter.url},
                        {field: 'weigh', title: __('Weigh'), operate: false},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.normal, searchList: {0: '关闭', 1: '正常'}}, 
                        {field: 'add_time', title: __('Add_time'), addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
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