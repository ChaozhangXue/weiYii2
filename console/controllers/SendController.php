<?php
namespace console\controllers;

use Yii;
use yii\console\Controller;
use common\models\DND;

/**
 * 离线消息对应的功能
 */
class SendController extends Controller
{
    /*
     * 发送模板消息
     * 需要手动执行 http://121.201.108.221/xuechaozhang/weight/frontend/web/index.php?r=index/send-template-msg
     */
    public function actionSendTemplateMsg()
    {
        $weather = $this->actionGetWeather();
        $weather_report = $weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['cond']['txt_d'] . "转" . $weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['cond']['txt_n'];

        if (stripos($weather_report, '雨') !== false) {
            $place = $weather['HeWeather data service 3.0']['0']['basic']['city'];
            $date = $weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['date'];
            $weather = "天气：" . $weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['cond']['txt_d'] . "转"
                . $weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['cond']['txt_n'] . "\n"
                . "温度" . $weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['tmp']['min'] . "-" . $weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['tmp']['max'];

            //1.获取到access token
            $access_token = $this->getAccessToken();
            // 2.组装数组
            $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$access_token}";
            // 3.将数组成json
            $ids = [Yii::$app->params['zhaoOpenId'],Yii::$app->params['xueOpenId']];
            foreach($ids as $id){
                $content = [
                    'touser' => $id,
                    'template_id' => 'LLkHbz2I2HyEdfCyD08O9tad8Jvzb_eHgPtQlNkK02Q',
                    'url' => 'http://www.weather.com.cn/weather/101210106.shtml',
                    'data' => [
                        'place' => [
                            'value' => $place,
                            'color' => '#173177',
                        ],
                        'date' => [
                            'value' => $date,
                            'color' => '#173177',
                        ],
                        'weather' => [
                            'value' => $weather,
                            'color' => '#173177',
                        ],
                    ],
                ];
                $content = json_encode($content);
                $this->http_curl($url, 'post', 'json', $content);
            }
        } else {
            return;
        }
    }
    /*
     * 获取天气
     * 需要手动执行 http://121.201.108.221/xuechaozhang/weight/frontend/web/index.php?r=index/get-weather
     */
    public function actionGetWeather(){
        $ch = curl_init();
        $url = 'http://apis.baidu.com/heweather/weather/free?city=hangzhou';
        $header = array(
            'apikey: ' . Yii::$app->params['baiduApiKey'],
        );
        // 添加apikey到header
        curl_setopt($ch, CURLOPT_HTTPHEADER  , $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 执行HTTP请求
        curl_setopt($ch , CURLOPT_URL , $url);
        $res = curl_exec($ch);
        $res = json_decode($res,true);
        return $res;
    }

    public function http_curl($url, $type='get',$res='json',$arr='')
    {
        //获取imooc
        //1.初始化curl
        $ch = curl_init();
        //2.设置curl的参数
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if($type == 'post'){
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $arr);
        }

        //3.采集
        $output = curl_exec($ch);
        //4.关闭
        curl_close($ch);

        if($res == 'json'){
            return json_decode($output,true);
        }
    }

    /*
     * 三点钟招行提醒
     * 需要手动执行 http://121.201.108.221/xuechaozhang/weight/frontend/web/index.php?r=index/send-template-msg
     */
    public function actionAlert()
    {
        //1.获取到access token
        $access_token = $this->getAccessToken();
        // 2.组装数组
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$access_token}";
        // 3.将数组成json
        $id = Yii::$app->params['zhaoOpenId'];
//        $ids = [Yii::$app->params['zhaoOpenId'],Yii::$app->params['xueOpenId']];
//        foreach($ids as $id){
            $content = [
                'touser' => $id,
                'template_id' => 'hylKxS6wuktvJKqjL1MgM30vhtWxEl7f7rduJBS7G0w',
                'url' => '',
            ];
            $content = json_encode($content);
            $this->http_curl($url, 'post', 'json', $content);
//        }
    }
    /*
     * 获取微信access token
     */
    public function getAccessToken()
    {
        if(isset($_SESSION['access_token']) && $_SESSION['expire_time'] > time()){
            return $_SESSION['access_token'];
        }else{
            //1.请求url地址
            $appid = Yii::$app->params['appid'];
            $appsecret = Yii::$app->params['appSecret'];
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $appsecret;
            //2初始化
            $ch = curl_init();
            //3.设置参数
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            //4.调用接口
            $res = curl_exec($ch);
            //5.关闭curl
            curl_close($ch);
            //        if (curl_errno($ch)) {
            //            var_dump(curl_error($ch));
            //        }
            $arr = json_decode($res, true);
            $access_token = $arr['access_token'];

            //存入session
            $_SESSION['access_token'] = $access_token;
            $_SESSION['expire_time'] = time() + 7200;

            return $arr['access_token'];
        }

    }
}