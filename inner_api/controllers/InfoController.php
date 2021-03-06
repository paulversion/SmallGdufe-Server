<?php
/**
 * User: xiaoguang
 * Date: 2017/2/7
 */

namespace app\inner_api\controllers;

use app\inner_api\utils\InfoParser;
use app\inner_api\utils\JwParser;
use yii;
use yii\web\Response;

class InfoController extends BaseController
{
    const REDIS_IDS_PRE = 'in:';    //ids通用cookie
    // const REDIS_INFO_PRE = 'my:';   //信息门户的cookie，因信息门户自身部分功能太少，目前保留不用，省内存
    use JwParser;

    protected $expire = 1800;//半小时
    use InfoParser;

    /**
     * 登陆统一登陆IDS系统，获取iPlanetDirectoryPro cookie后可访问各系统
     * @param $sno
     * @param $pwd
     * @return null|string 返回cookie的value: iPlanetDirectoryPro 或 null[密码错误]
     */
    private function loginIdsSys($sno, $pwd)
    {
        $curl = $this->newCurl();
        $data = [
            'IDToken0' => '',
            'IDToken1' => $sno,
            'IDToken2' => $pwd,
            'IDButton' => 'Submit',
            'goto' => base64_encode($this->urlConst['base']['info']),
            'encoded' => 'true',
            'inputCode' => '',
            'gx_charset' => 'UTF-8',
        ];
        $curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $curl->setOpt(CURLOPT_FOLLOWLOCATION, false);
        // $curl->post('http://localhost/2.php', $data);
        $curl->post($this->urlConst['info']['ids'], $data);
        $idsCookie = $curl->getCookie($this->idsCookieKey);
        // var_dump($curl->responseCookies);
        // var_dump($curl->response);
        return $idsCookie;
    }

    /**
     * 获取信息门户右边的基本信息（校园卡余额、借阅图书数等）
     * @param $sno
     * @param $pwd
     * @return string 信息门户官方json格式字符串
     */
    public function actionInfoTips($sno, $pwd)
    {
        if (empty($sno) || empty($pwd)){
            return $this->getReturn(Error::accountEmpty,'',[]);
        }
        $idsCookie = $this->getIdsCookie($sno, $pwd);
        if(empty($idsCookie)){
            return $this->getReturn(Error::passwordError,'',[]);
        }
        return $this->getReturn(Error::success,'',$this->getInfoTips($idsCookie,$sno));
    }

    /**
     * 获取信息门户tab标签的素拓信息，APP当前检查这个接口来验证登陆
     * @param $sno
     * @param $pwd
     * @return string json
     */
    public function actionFewSztz($sno, $pwd)
    {
        if (empty($sno) || empty($pwd)){
            return $this->getReturn(Error::accountEmpty,'',[]);
        }
        if($this->isSystemCrashed($this->urlConst['base']['ids'])) {
            return $this->getReturn(Error::idsSysError,'',[]);
        }
        $idsCookie = $this->getIdsCookie($sno, $pwd);
        if(empty($idsCookie)){
            return $this->getReturn(Error::passwordError,'',[]);
        }
        return $this->getReturn(Error::success,'',$this->getFewSztz($idsCookie));
    }

    public function actionTest()
    {
        return $this->getReturn(Error::success,'',$this->parseFewSztz(file_get_contents('F:\\Desktop\\sutuo16.html')));
    }

    /**
     * 实际请求信息门户首页的提醒信息，暂不用信息门户专属cookie
     * @param $idsCookie
     * @param $sno
     * @return string
     */
    private function getInfoTips($idsCookie, $sno)
    {
        $curl = $this->newCurl();
        // $cache = Yii::$app->cache->get(self::REDIS_INFO_PRE . $sno);
        // if ($cache) {
        //     $curl->setCookie($this->comCookieKey,$cache);
        // }
        $curl->setCookie($this->idsCookieKey,$idsCookie);
        $curl->setReferer($this->urlConst['base']['info']);
        $curl->post($this->urlConst['info']['tips']);
        // $infoCookie = $curl->getCookie($this->comCookieKey);
        // Yii::$app->cache->set(self::REDIS_INFO_PRE . $sno, $infoCookie, $this->expire);
        return $this->parseInfoTips($curl->response);
    }

    /**
     * 实际请求信息门户处的素拓信息并解析有用信息成json
     * @param $idsCookie
     * @return string
     */
    private function getFewSztz($idsCookie)
    {
        $curl = $this->newCurl();
        $curl->setCookie($this->idsCookieKey,$idsCookie);
        $curl->setReferer($this->urlConst['base']['info']);
        $curl->get($this->urlConst['info']['sztz']);
        return $this->parseFewSztz($curl->response);
    }

    /**
     * 获取IDS系统cookie，若有缓存则取，否则登陆
     * @param $sno
     * @param $pwd
     * @return null|string IDS系统的cookie
     */
    protected function getIdsCookie($sno, $pwd)
    {
        $cache = Yii::$app->cache->get(self::REDIS_IDS_PRE . $sno);
        if ($cache) {
            return $cache;
        }
        $idsCookie = $this->loginIdsSys($sno, $pwd);
        if(empty($idsCookie)){
            return null;
        }
        Yii::$app->cache->set(self::REDIS_IDS_PRE . $sno, $idsCookie, $this->expire);
        return $idsCookie;
    }


}