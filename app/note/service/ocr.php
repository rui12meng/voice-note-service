<?php
namespace Note\Service;

/**
 * Ocr识别服务
 * @author mengrui
 * $Id: ocr.php $
 */

class Ocr
{
    private $_ocrAliYunService;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        $this->_ocrAliYunService = \Lsf\Loader::service('OcrAliYun' , true);
    }

    /**
     *  OCR 识别流程
     * @param string $fileUrl
     * @param string $platFrom
     * @return array 解析后的 OCR 结果
     */
    public function imageOcr($fileUrl, $platFrom = 'aliYun')
    {
        //获取url 扩展名
        $ext = pathinfo($fileUrl, PATHINFO_EXTENSION);


        switch ($platFrom){
            case 'aliYun':
                $result = $this->ocr_aliYun($fileUrl);
                break;
            default:
                $result = $this->ocr_volC($fileUrl);
                break;
        }
        if($result === false){
            return -1;
        }
        return $result;
    }

    protected function ocr_volC($fileUrl){

    }

    protected function ocr_aliYun($fileUrl){
        $result = $this->_ocrAliYunService->Ocr($fileUrl);
        if($result === false){ //AliYun OCR SDK Error
            return -1;
        }
        return $result;
    }


}