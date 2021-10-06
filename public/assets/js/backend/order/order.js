define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/order/index' + location.search,
                    add_url: 'order/order/add',
                    edit_url: 'order/order/edit',
                    del_url: 'order/order/del',
                    multi_url: 'order/order/multi',
                    import_url: 'order/order/import',
                    table: 'egg_order',
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
                        {field: 'id', title: __('Id'), operate: false},
                        {field: 'sell_user_id', title: __('Sell_user_id')},
                        {field: 'sell_serial_umber', title: __('Sell_serial_umber'), operate: 'LIKE'},
                        {field: 'sell_mobile', title: __('Sell_mobile'), operate: 'LIKE'},
                        {field: 'order_sn', title: __('Order_sn'), operate: 'LIKE'},
                        {field: 'attestation_name', title: __('Attestation_name'), operate: false},
                        {field: 'attestation_address', title: __('Attestation_address'), operate: false},
                        {field: 'buy_user_id', title: __('Buy_user_id')},
                        {field: 'buy_serial_umber', title: __('Buy_serial_umber'), operate: 'LIKE'},
                        {field: 'buy_mobile', title: __('Buy_mobile'), operate: 'LIKE'},
                        {field: 'pay_img', title: __('Pay_img'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'price', title: __('Price'), operate: false},
                        {field: 'number', title: __('Number'), operate: false},
                        {field: 'rate', title: __('Rate'), operate: false},
                        {field: 'amount', title: __('Amount'), operate: false},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.normal, searchList: {0: '待付款', 1: '完成', 2: '待确认', 3: '申诉', 4: '无效'}},
                        {field: 'note', title: __('Note'), operate: false},
                        {field: 'pay_time', title: __('Pay_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table,buttons: [
                            {name: 'edit', text: '审核', title: '审核', icon: 'fa fa-star', classname: 'btn btn-xs btn-success btn-dialog' ,url:$.fn.bootstrapTable.defaults.extend.edit_url
                                ,visible:function(row){
                                    if(row.status == 3){ 
                                        return true; 
                                    }
                                },
                                extend: 'data-area=\'["500px", "450px"]\''
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