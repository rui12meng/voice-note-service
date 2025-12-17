<?php
namespace Ad\Service;

use Cassandra\Exception\TruncateException;

/**
 * 广告服务
 * @author lichenkai@okay.cn
 * $Id: ad.php 2019-11-02 10:09:07 lichenkai $
 */

class Ad
{
    private $_daoAdPutInfoModel;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        $this->_daoAdPutInfoModel = \Lsf\Loader::model('DaoOkayAdPutInfo',TRUE);
    }

    /**
     * 广告推荐
     * @param  int     $productId
     * @param  string  $pageSign
     * @param  int     $limit
     * @return mixed
     */
    public function AdRecommend($productId, $pageSign = '', $limit = 10){
        $columns = [
            'id',                       // 广告投放id
            'pic_url',                  // 图片url
            'location_url',             // 跳转url
            'page_sign',                // 页面id
            'show_type',                // 展示类型（1-浮层）
            'position_type',            // 位置类型（1-居中 2-右下）
            'is_close',                 // 是否可关闭（0-否 1-是）
            'close_continue_visible'    // 关闭持续可见（0-否 1-是）
        ];
        $condition = ['page_sign' => $pageSign];
        $adInfo = $this->_daoAdPutInfoModel->effectiveAd($productId, implode(',', $columns), $condition, $limit);
        // 数据库查询失败
        if($adInfo === FALSE){
            return -1;
        }
        // 本期端上未支持同页面多位置的显示，所以暂时先只按照page_sign去重
        if(!empty($adInfo)){
            $checkArr = [];
            foreach($adInfo as $key => $value){
                if(isset($value['page_sign']) && !empty($value['page_sign'])){
                    // 已存在则剔除
                    if(isset($checkArr[$value['page_sign']])){
                        unset($adInfo[$key]);
                        continue;
                    }else{
                        $checkArr[$value['page_sign']] = 1;
                    }
                // 数据异常
                }else{
                    \Lsf\Loader::plugin('Log')->error(9011500, ['put_info' => $value]);
                }
            }
        }
        return $adInfo;
    }
}