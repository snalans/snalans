define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            $(".btn-add").data("area",["400px","550px"]);
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/egg_hatch/index' + location.search,
                    add_url: 'egg/egg_hatch/add',
                    edit_url: 'egg/egg_hatch/edit',
                    del_url: 'egg/egg_hatch/del',
                    multi_url: 'egg/egg_hatch/multi',
                    import_url: 'egg/egg_hatch/import',
                    table: 'egg_hatch',
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
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user.mobile', title: __('User.mobile'), operate: 'LIKE'},
                        {field: 'nest_kind_id', title: __('Eggnestkind.name'), formatter: Table.api.formatter.normal, searchList: {1: '白窝', 2: '铜窝', 3: '银窝', 4: '金窝'}},
                        {field: 'hatch_num', title: __('Hatch_num'), operate:false},
                        {field: 'shape', title: __('Shape'), operate:false, formatter: Table.api.formatter.normal, searchList: {0: '蛋', 1: '鸡', 2: '鸡', 5: '无'}},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.normal, searchList: {0: '孵化中', 1: '空闲'}},
                        {field: 'is_reap', title: __('Is_reap'), operate:false, formatter: Table.api.formatter.normal, searchList: {0: '无', 1: '可收获', 2: '可收获'}},
                        {field: 'position', title: __('Position'), operate:false},
                        {field: 'is_buy', title: __('是否购买'), formatter: Table.api.formatter.normal, searchList: {1: __('是'), 0: __('否')}},
                        {field: 'buy_cycle', title: __('剩余周期'), operate:false,sortable:true},
                        {field: 'is_give', title: __('窝里是否体验蛋'), operate:false, formatter: Table.api.formatter.normal, searchList: {0: '否', 1: '是'}},
                        {field: 'is_close', title: __('是否关闭'), searchList: {"1": __('Yes'), "0": __('No')}, formatter: Table.api.formatter.toggle},
                        {field: 'uptime', title: __('Uptime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table,buttons: [
                            {
                                name: 'ajax',
                                text: '清空',
                                title: "删除窝里面的蛋或鸡",
                                classname: 'btn btn-xs btn-info btn-magic btn-ajax',
                                icon: 'fa fa-magic',
                                url: 'egg/egg_hatch/reduction',
                                confirm: '确认清空蛋窝?',
                                success: function (data, ret) {
                                    Layer.alert(ret.msg);
                                    //如果需要阻止成功提示，则必须使用return false;
                                    table.bootstrapTable('refresh');
                                    return false;
                                },
                                error: function (data, ret) {
                                    console.log(data, ret);
                                    Layer.alert(ret.msg);
                                    return false;
                                }
                            }
                        ], events: Table.api.events.operate, formatter: Table.api.formatter.operate}                    
                    ]
                ]
            });
            table.on('post-body.bs.table',function(){
                $(".btn-editone").data("area",["400px","550px"]);
            })

            table.on('load-success.bs.table', function (e, data) {
                //这里我们手动设置底部的值
                $("#ext1").text(data.extend.ext1);
                $("#ext2").text(data.extend.ext2);
                $("#ext3").text(data.extend.ext3);

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