<?php

namespace app\common\library;

/**
 * 验证
 */
class Validate
{

    /**
     * 检测身份证号码
     *
     * @param string $id_card   身份证号码
     */
    public static function check_id_card($id_card)
    {
        $id_card = strtoupper($id_card);
        $regx = "/(^\d{15}$)|(^\d{17}([0-9]|X)$)/";
        
        $arr_split = [];
        if(!preg_match($regx, $id_card)){
            return false;
        }
        
        if(15 == strlen($id_card)){
            // 检查15位
            $regx = "/^(\d{6})+(\d{2})+(\d{2})+(\d{2})+(\d{3})$/";

            @preg_match($regx, $id_card, $arr_split);
            // 检查生日日期是否正确
            $dtm_birth = "19" . $arr_split[2] . '/' . $arr_split[3] . '/' . $arr_split[4];
            
            if(!strtotime($dtm_birth)){                
                return false;
            }else{
                return true;
            }
        }else{
            // 检查18位
            $regx = "/^(\d{6})+(\d{4})+(\d{2})+(\d{2})+(\d{3})([0-9]|X)$/";
            @preg_match($regx, $id_card, $arr_split);
            
            $dtm_birth = $arr_split[2] . '/' . $arr_split[3] . '/' . $arr_split[4];
            
            //检查生日日期是否正确
            if(!strtotime($dtm_birth)) {
                return false;
            }else{
                //检验18位身份证的校验码是否正确。
                //校验位按照ISO 7064:1983.MOD 11-2的规定生成，X可以认为是数字10。
                $arr_int = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
                $arr_ch = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
                $sign = 0;
                
                for ( $i = 0; $i < 17; $i++ ){
                    $b = (int) $id_card[$i];
                    $w = $arr_int[$i];
                    $sign += $b * $w;
                }
                $n = $sign % 11;
                $val_num = $arr_ch[$n];
                
                if ($val_num != substr($id_card,17, 1)){
                    return false;
                }else{
                    return true;
                }
            }
        }
    }
}