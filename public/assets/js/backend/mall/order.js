define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'mall/order/index' + location.search,
                    add_url: 'mall/order/add',
                    edit_url: 'mall/order/edit',
                    edit_appeal: 'mall/order/appeal',
                    closed_url: 'mall/order/closed',
                    del_url: 'mall/order/del',
                    multi_url: 'mall/order/multi',
                    import_url: 'mall/order/import',
                    table: 'mall_order',
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
                        {field: 'order_sn', title: '订单号', operate: 'LIKE'},
                        {field: 'user.mobile', title: '买家手机号', operate: 'LIKE'},
                        {field: 'selluser.mobile', title: '卖家手机号', operate: 'LIKE'},
                        {field: 'title', title: __('Title'), operate:false,
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
                        {field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'price', title: __('Price'), operate:false},
                        {field: 'number', title: __('Number'), operate:false},
                        {field: 'rate', title: '手续费', operate:false},
                        {field: 'total_price', title: '总价格', operate:false},
                        {field: 'pay_img', title: '付款截图', operate:false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'recharge_account', title: __('Recharge_account'), operate: false},
                        {field: 'contactor', title: __('Contactor'), operate: false},
                        {field: 'contactor_phone', title: __('Contactor_phone'), operate: false},
                        {field: 'address', title: __('Address'), operate: false},
                        {field: 'express_name', title: __('Express_name'), operate:false},
                        {field: 'express_no', title: __('Express_no'), operate:false}, 
                        {field: 'is_virtual', title: __('Is_virtual'), operate:false, formatter: Table.api.formatter.normal, searchList: {1: '是',0: '否'}}, 
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.normal, searchList: {0: '待付款',1: '完成',2: '待发货 / 充值',3: '待收货',5: '申请退款',6: '确认退款',7: '申诉','-1': '取消'}},
                        {field: 'send_time', title: __('Send_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'received_time', title: __('Received_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'add_time', title: __('Add_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, buttons:[
                            {name: 'appeal', text: '审核', title: '申请申诉/申请退款', icon: 'fa fa-star', classname: 'btn btn-xs btn-success btn-dialog' ,url:$.fn.bootstrapTable.defaults.extend.edit_appeal
                                ,visible:function(row){
                                    if((row.status == 5 && row.sell_user_id == 0)|| row.status == 7){ 
                                        return true; 
                                    }
                                },
                                extend: 'data-area=\'["500px", "450px"]\''
                            },
                            {name: 'edit', text: '发货', title: '发货', icon: 'fa fa-truck', classname: 'btn btn-xs btn-info btn-editone btn-dialog' ,url:$.fn.bootstrapTable.defaults.extend.edit_url,
                                hidden:function(row){
                                    if(row.status==2 && row.sell_user_id==0){
                                        return false;
                                    }else{
                                        return true;
                                    }
                                }
                            },
                            {name: 'closed', text: '取消', title: '取消订单', icon: 'fa fa-close', classname: 'btn btn-xs btn-primary btn-dialog' ,url:$.fn.bootstrapTable.defaults.extend.closed_url,
                                hidden:function(row){
                                    if(row.status==2 && row.sell_user_id==0){
                                        return false;
                                    }else{
                                        return true;
                                    }
                                },
                                extend: 'data-area=\'["500px", "450px"]\''
                            },
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
        appeal: function () {
            Controller.api.bindevent();
        },
        closed: function () {
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