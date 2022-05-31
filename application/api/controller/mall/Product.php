<?php
namespace app\api\controller\mall;

use app\common\controller\Api;
use think\Validate;
use think\Config;
use think\Cookie;
use think\Db;

/**
 * 商品接口
 * @ApiWeigh   (38)
 */
class Product extends Api
{
    protected $noNeedLogin = ["getList","getDetail","getCateList"];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 获取商品列表
     *
     * @ApiMethod (GET)
     * @ApiParams   (name="title", type="string", description="商品名称或者卖家编号")
     * @ApiParams   (name="cate_id", type="int", description="商品分类ID")
     * @ApiParams   (name="page", type="integer", description="页码")
     * @ApiParams   (name="per_page", type="integer", description="数量")
     * 
     * @ApiReturnParams   (name="id", type="int", description="商品ID")
     * @ApiReturnParams   (name="title", type="string", description="商品名称")
     * @ApiReturnParams   (name="image", type="string", description="商品图片")     
     * @ApiReturnParams   (name="sell_num", type="integer", description="商品销量") 
     * @ApiReturnParams   (name="egg_image", type="string", description="蛋图片")       
     * @ApiReturnParams   (name="price_str", type="integer", description="产品价格")             
     * @ApiReturnParams   (name="nest_kind_id", type="integer", description="窝类型ID 0=不是窝")      
     * @ApiReturnParams   (name="price", type="integer", description="支付价格")        
     * @ApiReturnParams   (name="name", type="string", description="蛋名称")         
     * @ApiReturnParams   (name="avatar", type="string", description="卖家头像")      
     * @ApiReturnParams   (name="serial_number", type="string", description="卖家编号")     
     */
    public function getList()
    {
        $title          = $this->request->get('title',"");
        $cate_id        = $this->request->get('cate_id',"");
        $page           = $this->request->get("page",1);        
        $per_page       = $this->request->get("per_page",15);

        $is_order = "p.weigh desc,p.add_time desc";
        $wh = [];
        $wh['p.status'] = 1;
        $wh['p.stock']  = ['>',0];
        if(!empty($cate_id)){
            $wh['p.cate_id'] = $cate_id;
            if($cate_id == 1){
                if(Cookie::has('is_order')){
                    $is_order = Cookie::get('is_order');
                }else{
                    $mtr = mt_rand(1,6);
                    if($mtr == 1){
                        $is_order = "p.add_time desc";
                    }else if($mtr == 2){
                        $is_order = "p.add_time asc";
                    }else if($mtr == 3){
                        $is_order = "p.sell_num desc";
                    }else if($mtr == 4){
                        $is_order = "p.sell_num asc";
                    }else if($mtr == 5){
                        $is_order = "p.title desc";
                    }else if($mtr == 6){
                        $is_order = "p.title asc";
                    }                     
                    Cookie::set('is_order',$is_order,3600);
                }
            }
        }
        if(!empty($title)){
            $wh['p.title'] = ['like',"%".$title."%"];
        }
        $whs = [];
        if(!empty($title)){
            $whs['u.serial_number'] = $title;
        }
        $list = Db::name("mall_product")->alias('p')
                    ->field("p.id,p.title,p.images,(p.sell_num+p.virtual_sales) as sell_num,p.price,ek.name,ek.image as egg_image,u.avatar,u.serial_number,p.nest_kind_id")
                    ->join("user u","u.id=p.user_id","LEFT")
                    ->join("egg_kind ek","ek.id=p.kind_id","LEFT")
                    ->where($wh)
                    ->whereOr($whs)
                    ->order($is_order)
                    ->paginate($per_page)->each(function($item){
                        if(!empty($item['images'])){                            
                            $img_arr = explode(",",$item['images']);
                            $item['image'] = cdnurl($img_arr[0], false);
                            unset($item['images']);
                        }
                        if(empty($item['serial_number'])){
                            $item['serial_number'] = '平台';
                        }
                        $item['price_str'] = $item['price']." ".$item['name'];
                        return $item;
                    });
        $this->success('',$list);
    }

    /**
     * 获取商品详情
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="id", type="integer", description="商品ID") 
     * 
     * @ApiReturnParams   (name="title", type="integer", description="商品名称")
     * @ApiReturnParams   (name="image", type="string", description="商品首图")  
     * @ApiReturnParams   (name="images", type="string", description="商品图片")     
     * @ApiReturnParams   (name="sell_num", type="integer", description="商品销量")  
     * @ApiReturnParams   (name="egg_image", type="string", description="蛋图片")    
     * @ApiReturnParams   (name="price_str", type="integer", description="产品价格")       
     * @ApiReturnParams   (name="price", type="integer", description="支付价格")       
     * @ApiReturnParams   (name="name", type="string", description="蛋名称")         
     * @ApiReturnParams   (name="stock", type="integer", description="商品库存")    
     * @ApiReturnParams   (name="is_virtual", type="integer", description="是否虚拟商品 0=否 1=是")           
     * @ApiReturnParams   (name="nest_kind_id", type="integer", description="窝类型ID 0=不是窝")           
     * @ApiReturnParams   (name="content", type="string", description="商品内容")     
     * @ApiReturnParams   (name="add_time", type="string", description="商品发布时间")        
     * @ApiReturnParams   (name="avatar", type="string", description="卖家头像")    
     * @ApiReturnParams   (name="serial_number", type="string", description="卖家编号")   
     */
    public function getDetail()
    {
        $id = $this->request->post("id","");
        
        $info = Db::name("mall_product")->alias('p')->cache(true,300)
                    ->field("p.title,p.images,(p.sell_num+p.virtual_sales) as sell_num,p.price,ek.name,ek.image as egg_image,p.stock,p.is_virtual,p.content,p.add_time,u.avatar,u.serial_number,p.nest_kind_id")
                    ->join("user u","u.id=p.user_id","LEFT")
                    ->join("egg_kind ek","ek.id=p.kind_id","LEFT")
                    ->where("p.id",$id)
                    ->find();
        if(!empty($info)){
            if(!empty($info['images'])){                            
                $img_arr = explode(",",$info['images']);
                $info['image'] = cdnurl($img_arr[0], false);
            }
            $info['price_str'] = $info['price']." ".$info['name'];
            $info['add_time']  = date("Y-m-d H:i",$info['add_time']);
        }
        $this->success('',$info);
    }

    /**
     * 获取商品分类列表
     * @ApiMethod (GET)
     */
    public function getCateList()
    {
        $list = Db::name("mall_product_cate")->cache(true,300)
                ->field(["status","weigh"],true)
                ->where("status",1)
                ->order("weigh","DESC")
                ->select();
        $this->success('',$list);
    }


    /**
     * 获取发布的商品列表
     *
     * @ApiMethod (GET)
     * @ApiParams   (name="status", type="integer", description="状态 0=下架 1=上架")
     * @ApiParams   (name="page", type="integer", description="页码")
     * @ApiParams   (name="per_page", type="integer", description="数量")
     * 
     * @ApiReturnParams   (name="id", type="int", description="商品ID")
     * @ApiReturnParams   (name="title", type="string", description="商品名称")
     * @ApiReturnParams   (name="images", type="string", description="商品图片")      
     * @ApiReturnParams   (name="sell_num", type="integer", description="商品销量")  
     * @ApiReturnParams   (name="egg_image", type="string", description="蛋图片")     
     * @ApiReturnParams   (name="price", type="integer", description="支付价格") 
     * @ApiReturnParams   (name="status", type="integer", description="状态 1=在售 2=审核中 0=下架") 
     * @ApiReturnParams   (name="name", type="string", description="蛋名称")          
     * @ApiReturnParams   (name="price_str", type="integer", description="产品价格")    
     * @ApiReturnParams   (name="on_num", type="integer", description="在售数量")     
     * @ApiReturnParams   (name="off_num", type="integer", description="下架数量")        
     */
    public function getSellList()
    {
        $status         = $this->request->get("status",1);  
        $page           = $this->request->get("page",1);        
        $per_page       = $this->request->get("per_page",15);

        $wh = [];
        $wh['p.user_id'] = $this->auth->id;
        $wh['p.status']  = $status==1?['>',0]:0;

        $list = Db::name("mall_product")->alias('p')
                    ->field("p.id,p.title,p.images,p.sell_num,p.price,p.status,ek.name,ek.image as egg_image")
                    ->join("egg_kind ek","ek.id=p.kind_id","LEFT")
                    ->where($wh)
                    ->order("p.add_time desc")
                    ->paginate($per_page)->each(function($item){
                        if(!empty($item['images'])){                            
                            $img_arr = explode(",",$item['images']);
                            $item['images'] = cdnurl($img_arr[0], false);
                        }          
                        $item['price_str'] = $item['price']." ".$item['name'];
                        return $item;
                    });        
        $list = json_encode($list);
        $list = json_decode($list,1);

        $wh = [];
        $wh['user_id'] = $this->auth->id;
        $wh['status'] = ['>',0];
        $list['on_num'] = Db::name("mall_product")->where($wh)->count();
        $wh = [];
        $wh['user_id'] = $this->auth->id;
        $wh['status'] = 0;
        $list['off_num'] = Db::name("mall_product")->where($wh)->count();

        $this->success('',$list);
    }

    /**
     * 获取在售商品详情
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="id", type="integer", description="商品ID") 
     * 
     * @ApiReturnParams   (name="title", type="integer", description="商品名称")
     * @ApiReturnParams   (name="images", type="string", description="商品图片")      
     * @ApiReturnParams   (name="kind_id", type="integer", description="蛋类型ID")       
     * @ApiReturnParams   (name="price", type="integer", description="支付价格")       
     * @ApiReturnParams   (name="stock", type="integer", description="商品库存")      
     * @ApiReturnParams   (name="sell_num", type="integer", description="商品销量")     
     * @ApiReturnParams   (name="status", type="integer", description="状态 0=下架 1=上架  2=审核")          
     * @ApiReturnParams   (name="content", type="string", description="商品内容")    
     */
    public function getSellDetail()
    {
        $id = $this->request->post("id","");
        
        $wh = [];
        $wh['id']       = $id;
        $wh['user_id']  = $this->auth->id;
        $info = Db::name("mall_product")
                    ->field("title,images,kind_id,price,stock,sell_num,status,content")
                    ->where($wh)
                    ->find();

        $this->success('',$info);
    }

    /**
     * 删除商品
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="id", type="integer", description="商品ID")
     */
    public function del()
    {
        $id           = $this->request->post("id","");

        $wh = [];
        $wh['id']       = $id;
        $wh['user_id']  = $this->auth->id;
        $rs = Db::name("mall_product")->where($wh)->update(['status'=>-1]);
        if($rs){
            $this->success("删除成功");
        }else{
            $this->error("删除失败,请重试");
        }
    }
    
    /**
     * 添加/编辑商品
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="id", type="integer", description="商品ID（编辑时候传）")
     * @ApiParams   (name="title", type="string", description="商品名称")      
     * @ApiParams   (name="images", type="string", description="图片")        
     * @ApiParams   (name="kind_id", type="integer", description="蛋类型ID")   
     * @ApiParams   (name="price", type="string", description="支付价格")
     * @ApiParams   (name="mobile", type="string", description="手机号")   
     * @ApiParams   (name="stock", type="int", description="库存")   
     * @ApiParams   (name="content", type="integer", description="内容")     
     */
    public function save()
    {
        $id         = $this->request->post("id","");
        $title      = $this->request->post("title","");
        $images     = $this->request->post("images","");
        $price      = $this->request->post("price",0);
        $kind_id    = $this->request->post("kind_id",1);
        $mobile     = $this->request->post("mobile","");
        $stock      = 1;//$this->request->post("stock",1);
        $content    = $this->request->post("content","");

        if(empty($title) || $stock<=0 || !in_array($kind_id,[1,2,3]))
        {            
            $this->error("参数不正确,请检查");
        }
        if($price < 1){
            $this->error("价格不能小于1");
        }

        if($this->auth->status != 'normal' || $this->auth->is_attestation != 1){
            $this->error("账号无效或者未认证");
        }

        if(empty($mobile)){
            $this->error("手机号不能为空");
        }

        $issue_number = Config::get("site.issue_number");
        if($issue_number > 0 && $this->auth->valid_number < $issue_number){
            $this->error("有效值未达到标准，不能发布商品");
        }

        $release_num = Config::get("site.release_num",0);
        if($release_num > 0){            
            $wh = [];
            $wh['user_id'] = $this->auth->id;
            $wh['kind_id'] = 1;
            $egg_info = Db::name("egg")->where($wh)->find();
            if($release_num > ($egg_info['number']-$egg_info['freezing'])){
                $this->error("白蛋资产不够，不能发布商品");
            }
        }

        $wh = [];
        $wh['user_id'] = $this->auth->id;
        $wh['status']  = ['<>',-1];
        $num = Db::name("mall_product")->where($wh)->count();
        if($num > 3){
            $this->error("发布商品已经上限");
        }

        $data = [];
        $data['title']          = $title;
        $data['images']         = $images;
        $data['price']          = $price;
        $data['kind_id']        = $kind_id;
        $data['stock']          = $stock;
        $data['mobile']         = $mobile;
        $data['content']        = $content;
        $data['status']         = 2;

        if(empty($id))
        {
            $data['user_id']        = $this->auth->id;
            $data['cate_id']        = 1;
            $data['add_time']       = time();
            $rs = Db::name("mall_product")->insertGetId($data);
            if(!empty($rs)){
                if($release_num > 0){   
                    $wh = [];
                    $wh['user_id'] = $this->auth->id;
                    $wh['kind_id'] = 1;
                    Db::name("egg")->where($wh)->setDec('number',$release_num);
                    //写入日志
                    Db::name("egg_log")->insert(['user_id'=>$this->auth->id,'kind_id'=>1,'type'=>12,'order_sn'=>$rs,'number'=>-$release_num,'before'=>$egg_info['number'],'after'=>($egg_info['number']-$release_num),'note'=>"发布商品减少",'createtime'=>time()]);
                }

                $this->success("添加成功");
            } else{
                $this->error("添加失败,请重试");
            } 
        } else{
            $wh = [];
            $wh['id']       = $id;
            $wh['user_id']  = $this->auth->id;
            $rs = Db::name("mall_product")->where($wh)->update($data);
            if(!empty($rs)){
                $this->success("更新成功");
            } else{
                $this->error("更新失败,请重试");
            } 
        }

    }


    /**
     * 商品上下架
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="id", type="integer", description="商品ID")
     * @ApiParams   (name="status", type="integer", description="状态 0=下架 1=上架")    
     */
    public function updateOnOff()
    {
        $id         = $this->request->post("id","");
        $status     = $this->request->post("status/d",0);

        if(empty($id) || !in_array($status,[0,1]))
        {            
            $this->error("参数不正确,请检查");
        }

        if($this->auth->status != 'normal' || $this->auth->is_attestation != 1){
            $this->error("账号无效或者未认证");
        }

        $wh = [];
        $wh['id']      = $id;
        $wh['user_id'] = $this->auth->id;
        $info = Db::name("mall_product")->where($wh)->find();
        if(empty($info)){
            $this->error("商品不存在");
        }
        if($info['status'] == $status){
            $rs = true;
        }else{
            $rs = Db::name("mall_product")->where("id",$id)->update(['status'=>$status]);
        }
        if($rs){
            $this->success("更新成功");
        } else{
            $this->error("更新失败,请重试");
        } 
    }
}