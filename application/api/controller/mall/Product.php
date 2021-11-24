<?php
namespace app\api\controller\mall;

use app\common\controller\Api;
use think\Validate;
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
     * @ApiParams   (name="serial_number", type="string", description="卖家编号")
     * @ApiParams   (name="title", type="string", description="商品名称")
     * @ApiParams   (name="cate_id", type="int", description="商品分类ID")
     * @ApiParams   (name="page", type="integer", description="页码")
     * @ApiParams   (name="per_page", type="integer", description="数量")
     * 
     * @ApiReturnParams   (name="id", type="int", description="商品ID")
     * @ApiReturnParams   (name="title", type="string", description="商品名称")
     * @ApiReturnParams   (name="images", type="string", description="商品图片")       
     * @ApiReturnParams   (name="price_str", type="integer", description="产品价格")     
     * @ApiReturnParams   (name="price", type="integer", description="支付价格")        
     * @ApiReturnParams   (name="name", type="string", description="蛋名称")         
     * @ApiReturnParams   (name="avatar", type="string", description="卖家头像")      
     * @ApiReturnParams   (name="serial_number", type="string", description="卖家编号")     
     */
    public function getList()
    {
        $serial_number  = $this->request->get('serial_number',"");
        $title          = $this->request->get('title',"");
        $cate_id        = $this->request->get('cate_id',"");
        $page           = $this->request->get("page",1);        
        $per_page       = $this->request->get("per_page",15);

        $wh = [];
        $wh['p.status'] = 1;
        if(!empty($cate_id)){
            $wh['p.cate_id'] = $cate_id;
        }
        if(!empty($serial_number)){
            $wh['u.serial_number'] = $serial_number;
        }
        if(!empty($title)){
            $wh['p.title'] = ['like',"%".$title."%"];
        }
        $list = Db::name("mall_product")->alias('p')
                    ->field("p.id,p.title,p.images,p.price,ek.name,u.avatar,u.serial_number")
                    ->join("user u","u.id=p.user_id","LEFT")
                    ->join("egg_kind ek","ek.id=p.kind_id","LEFT")
                    ->where($wh)
                    ->order("p.weigh desc,p.add_time desc")
                    ->paginate($per_page)->each(function($item){
                        if(!empty($item['images'])){                            
                            $img_arr = explode(",",$item['images']);
                            $item['image'] = $img_arr[0];
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
     * @ApiReturnParams   (name="images", type="string", description="商品图片")    
     * @ApiReturnParams   (name="price_str", type="integer", description="产品价格")       
     * @ApiReturnParams   (name="price", type="integer", description="支付价格")       
     * @ApiReturnParams   (name="name", type="string", description="蛋名称")         
     * @ApiReturnParams   (name="stock", type="integer", description="商品库存")          
     * @ApiReturnParams   (name="content", type="string", description="商品内容")     
     * @ApiReturnParams   (name="add_time", type="string", description="商品发布时间")        
     * @ApiReturnParams   (name="avatar", type="string", description="卖家头像")    
     * @ApiReturnParams   (name="serial_number", type="string", description="卖家编号")   
     */
    public function getDetail()
    {
        $id = $this->request->post("id","");
        
        $info = Db::name("mall_product")->alias('p')
                    ->field("p.title,p.images,p.price,ek.name,p.stock,p.content,p.add_time,u.avatar,u.serial_number")
                    ->join("user u","u.id=p.user_id","LEFT")
                    ->join("egg_kind ek","ek.id=p.kind_id","LEFT")
                    ->where("p.id",$id)
                    ->find();
        if(!empty($info)){
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
        $list = Db::name("mall_product_cate")
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
     * @ApiReturnParams   (name="price", type="integer", description="支付价格") 
     * @ApiReturnParams   (name="name", type="string", description="蛋名称")          
     * @ApiReturnParams   (name="price_str", type="integer", description="产品价格")       
     */
    public function getSellList()
    {
        $status         = $this->request->get("status",1);  
        $page           = $this->request->get("page",1);        
        $per_page       = $this->request->get("per_page",15);

        $wh = [];
        $wh['p.user_id'] = $this->auth->id;
        $wh['p.status']  = $status;

        $list = Db::name("mall_product")->alias('p')
                    ->field("p.id,p.title,p.images,p.price,ek.name")
                    ->join("egg_kind ek","ek.id=p.kind_id","LEFT")
                    ->where($wh)
                    ->order("p.add_time desc")
                    ->paginate($per_page)->each(function($item){
                        if(!empty($item['images'])){                            
                            $img_arr = explode(",",$item['images']);
                            $item['images'] = $img_arr[0];
                        }
                        $item['price_str'] = $item['price']." ".$item['name'];
                        return $item;
                    });
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
                    ->field("title,images,kind_id,price,stock,status,content")
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
     * @ApiParams   (name="stock", type="string", description="库存")   
     * @ApiParams   (name="content", type="integer", description="内容")     
     */
    public function save()
    {
        $id         = $this->request->post("id","");
        $title      = $this->request->post("title","");
        $images     = $this->request->post("images","");
        $price      = $this->request->post("price",0);
        $kind_id    = $this->request->post("kind_id",1);
        $stock      = $this->request->post("stock",1);
        $content    = $this->request->post("content","");

        if(empty($title) || empty($price) || empty($stock) || !in_array($kind_id,[1,2,3]))
        {            
            $this->error("参数不正确,请检查");
        }

        if($this->auth->status != 'normal' || $this->auth->is_attestation != 1){
            $this->error("账号无效或者未认证");
        }

        $wh = [];
        $wh['user_id'] = $this->auth->id;
        $wh['status']  = ['<>',-1];
        $num = Db::name("mall_product")->where($wh)->count();
        if($num > 10){
            $this->error("不能多于10个商品");
        }

        $data = [];
        $data['title']          = $title;
        $data['images']         = $images;
        $data['price']          = $price;
        $data['kind_id']        = $kind_id;
        $data['stock']          = $stock;
        $data['content']        = $content;
        // $data['status']         = 2;

        if(empty($id))
        {
            $data['user_id']        = $this->auth->id;
            $data['cate_id']        = 1;
            $data['add_time']       = time();
            $rs = Db::name("mall_product")->insertGetId($data);
            if(!empty($rs)){
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
}