define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'mall/product/index' + location.search,
                    add_url: 'mall/product/add',
                    edit_url: 'mall/product/edit',
                    del_url: 'mall/product/del',
                    multi_url: 'mall/product/multi',
                    import_url: 'mall/product/import',
                    table: 'mall_product',
                    dragsort_url:'',
                }
            });

            var table = $("#table");

            //在普通搜索渲染后
            table.on('post-common-search.bs.table', function (event, table) {
                var form = $("form", table.$commonsearch);
                $("input[name='cate_id']", form).addClass("selectpage").data("source", "mall/product_cate/index").data("primaryKey", "id").data("field", "title");
                Form.events.cxselect(form);
                Form.events.selectpage(form);
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                search: false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), operate: false},
                        {field: 'user.mobile', title: __('User.mobile'), operate: 'LIKE'},
                        {field: 'title', title: __('Title'), operate: 'LIKE',
                            cellStyle:function(value,row,index){
                                return {
                                    css:{
                                        "min-width":"80px",
                                        "white-space":"nowrap",
                                        "text-overflow":"ellipsis",
                                        "overflow":"hidden",
                                        "max-width":"220px"
                                    }
                                }
                            },
                            formatter:function paramsMatter(value,row,index){
                                var span = document.createElement("span");
                                span.setAttribute("title",value);
                                span.innerHTML = value;
                                return span.outerHTML;
                            }
                        },
                        {field: 'cate.title', title: "商品分类", operate: false},
                        {field: 'cate_id', title: "商品分类", visible: false},
                        {field: 'images', title: __('Images'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.images},
                        {field: 'price', title: __('Price'), operate:false},
                        {field: 'kind_id', title: "价格单位", formatter: Table.api.formatter.normal, searchList: {0: 'usdt',1: '白蛋',2: '铜蛋',3: '银蛋'}},
                        {field: 'stock', title: __('Stock'), operate: false},
                        {field: 'sell_num', title: __('Sell_num'), operate: false},
                        {field: 'virtual_sales', title: __('Virtual_sales'), operate: false},
                        {field: 'is_virtual', title: __('Is_virtual'), formatter: Table.api.formatter.normal, searchList: {1: '是',0: '否'}},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.normal, searchList: {1: '上架',0: '下架',2: '待审核'}},
                        {field: 'add_time', title: __('Add_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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