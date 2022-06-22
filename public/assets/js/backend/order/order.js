define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/order/index' + location.search,
                    add_url: 'order/order/add',
                    edit_url: 'order/order/edit',
                    pay_url: 'order/order/pay',
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
                        {field: 'sell_mobile', title: __('Sell_mobile'), operate: 'LIKE'},
                        {field: 'order_sn', title: __('Order_sn'), operate: 'LIKE'},
                        {field: 'attestation_type', title: __('Attestation_type'), formatter: Table.api.formatter.normal, searchList: {0:'-',1: '支付宝', 2: '微信',3: '钱包',4: '银行卡'}},
                        {field: 'attestation_account', title: __('Attestation_account'), operate: false},
                        {field: 'attestation_image', title: __('Attestation_image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'buy_mobile', title: __('Buy_mobile'), operate: 'LIKE'},
                        {field: 'pay_img', title: __('Pay_img'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'price', title: __('Price'), operate: false, sortable:true},
                        {field: 'number', title: __('Number'), operate: false, sortable:true},
                        {field: 'rate', title: __('Rate'), operate: false},
                        {field: 'amount', title: __('Amount'), operate: false, sortable:true},
                        {field: 'status', title: __('Status'), operate: false, formatter: Table.api.formatter.normal, searchList: {0: '待付款', 1: '完成', 2: '待确认', 3: '申诉', 4: '撤单', 5: '挂单',6:'退款'}},
                        {field: 'refund_status', title:'退款类型',formatter:function(value,row,index){
                            if(row.status == 6){
                                return row.refund_status==1?'超时未付款取消':'申诉退款';
                            }
                            return '';
                        }},
                        {field: 'note', title: __('Note'), operate: false},
                        {field: 'sale_time', title: '出售时间', operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime,width:'140',sortable:true},
                        {field: 'pay_time', title: __('Pay_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime,width:'140',sortable:true},
                        {field: 'over_time', title: "确认收款时间", operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime,width:'140',sortable:true},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime,width:'140',sortable:true},
                        {field: 'operate', title: __('Operate'), table: table,buttons: [
                            {name: 'pay', text: '去支付', title: '上传支付凭证', icon: 'fa fa-star', classname: 'btn btn-xs btn-success btn-dialog' ,url:$.fn.bootstrapTable.defaults.extend.pay_url
                                ,visible:function(row){
                                    if(row.status == 0 &&( row.kind_id==5 || row.kind_id==6)){ 
                                        return true; 
                                    }
                                },
                                extend: 'data-area=\'["500px", "650px"]\''
                            },
                            {name: 'edit', text: '审核', title: '审核', icon: 'fa fa-star', classname: 'btn btn-xs btn-success btn-dialog' ,url:$.fn.bootstrapTable.defaults.extend.edit_url
                                ,visible:function(row){
                                    if(row.status == 3){ 
                                        return true; 
                                    }
                                },
                                extend: 'data-area=\'["500px", "450px"]\''
                            },
                            {
                                name: 'ajax',
                                text: '撤单',
                                title: "取消订单",
                                classname: 'btn btn-xs btn-danger btn-magic btn-ajax',
                                icon: 'fa fa-magic',
                                visible:function(row){
                                    if(row.status == 5){ 
                                        return true; 
                                    }
                                },
                                url: 'order/order/cancel',
                                confirm: '确认取消订单?',
                                success: function (data, ret) {
                                    Layer.alert(ret.msg);
                                    //如果需要阻止成功提示，则必须使用return false;
                                    table.bootstrapTable('refresh');
                                    return false;
                                },
                                error: function (data, ret) {
                                    Layer.alert(ret.msg);
                                    return false;
                                }
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
        pay: function () {
            
            $(".check_pay").on("click",function () {
                $("#charge_code").val($(this).attr("data-type"))
            })

            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        cancel: function () {
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