<?php
namespace frontend\controllers;

use Yii;
use yii\web\Controller;


/**
 * Site controller
 */
class IndexController extends Controller
{
    public function beforeAction()
    {
        return true;
    }

    public function actionIndex()
    {
        //获得参数 signature nonce token timestamp echostr
        $nonce = $_REQUEST['nonce'];
        $token = 'share_time';
        $timestamp = $_REQUEST['timestamp'];
        $echostr = isset($_REQUEST['echostr']) ? $_REQUEST['echostr'] : '';
        $signature = $_REQUEST['signature'];
        //形成数组，然后按字典序排序
        $array = array($nonce, $timestamp, $token);
        sort($array);
        //拼接成字符串,sha1加密 ，然后与signature进行校验
        $str = sha1(implode($array));
        if ($str == $signature && $echostr) {
            //第一次接入weixin api接口的时候
            echo $echostr;
            exit;
        } else {
            $this->reponseMsg();
        }
    }

    //接收事件推送并回复
    public function reponseMsg()
    {
        //1.获取到微信推送过来post数据（xml格式）
        $postArr = $GLOBALS['HTTP_RAW_POST_DATA'];
        //2.处理消息类型，并设置回复类型和内容
        $postObj = simplexml_load_string($postArr);

//        file_put_contents("/tmp/weixin.log",$postObj->EventKey,FILE_APPEND);
//        $this->responseText($postObj,$postObj);
        //判断该数据包是否是订阅的事件推送
        if (strtolower($postObj->MsgType) == 'event') {
            //如果是关注 subscribe 事件
            if (strtolower($postObj->Event) == 'subscribe') {
                //回复用户消息(纯文本格式)
                $toUser = $postObj->FromUserName;
                $fromUser = $postObj->ToUserName;
                $time = time();
                $msgType = 'text';
                $content = '欢迎关注我们的微信公众账号,您的到来让我们蓬荜生辉';
                $template = "<xml>
							<ToUserName><![CDATA[%s]]></ToUserName>
							<FromUserName><![CDATA[%s]]></FromUserName>
							<CreateTime>%s</CreateTime>
							<MsgType><![CDATA[%s]]></MsgType>
							<Content><![CDATA[%s]]></Content>
							</xml>";
                $info = sprintf($template, $toUser, $fromUser, $time, $msgType, $content);
                echo $info;die;
            }

            //菜单的click事件
            if (strtolower($postObj->Event) == 'click') {
                switch (strtolower($postObj->EventKey)){
                    case 'exercise_start':
                        if(Yii::$app->redis->exists("start_time")){
                            $content = "上次锻炼未完成，清先点击锻炼结束";
                        }else{
                            $content = "==================\n锻炼开始，开始计时";
                            Yii::$app->redis->set("start_time",time());
                        }
                        break;
                    case 'exercise_end':
                        if(Yii::$app->redis->exists("start_time")){
                            $exercise_time = time() - Yii::$app->redis->get("start_time");
                            $min = floor($exercise_time/60);
                            $sec = $exercise_time%60;
                            Yii::$app->redis->del("start_time");
                            $content = "锻炼结束，本次锻炼时长：{$min}分{$sec}秒，获得金币{$min}个！\n==================";
                            Yii::$app->redis->incrby("coin", $min);
                        }else{
                            $content = "锻炼尚未开始，请点击锻炼开始";
                            Yii::$app->redis->set("start_time",time());
                        }

                        break;
                    case 'weather':
                        $content = $this->checkWeather();
                        break;
                    case 'trick':
                        $content = $this->actionShowTrick();
                        break;
                    case 'my_coin':
                        $content = $this->getCoin();
                        break;
                    case 'online_shop':
                        $content = "网上商城暂未开放";
                        break;
                    case 'sign':
                        $content = $this->actionSign();
                        break;
                    case 'draw':
                        $content = $this->actionDraw();
                        break;
                    case 'star':
                        $content = $this->actionStar("狮子座");
                        break;
                    case 'laugh':
                        $content = $this->actionLaughTime($postObj);
                        break;
                    case 'jiemeng':
                        $content = "请切换到输入框输入 ‘薛神解梦 关键字’ 即可解梦。";
                        break;
                }

                $this->responseText($postObj,$content);
            }

//            //菜单的click事件
//            if (strtolower($postObj->Event == 'view')) {
//                if (strtolower($postObj->EventKey == 'item1')) {
//                    $content = "tiaozhuan";
//                }
//                if (strtolower($postObj->EventKey == 'song')) {
//                    $content = "tiaozhuan2";
//                }
//                $this->responseText($postObj,$content);
//            }
        }

        if (strtolower($postObj->MsgType) == 'text'){
            $star = ['白羊座','金牛座','双子座','巨蟹座','狮子座','处女座','天秤座','天蝎座','人马座','山羊座','水瓶座','双鱼座 '];
            if(in_array(trim($postObj->Content),$star)){
                $content = $this->actionStar(trim($postObj->Content));
            }elseif(stripos(trim($postObj->Content), '薛神解梦') !== false){
                //解梦
                $word = explode(' ',trim($postObj->Content));
                $content = $this->actionDream($word['1']);
            }else{
                $question = str_replace(' ','',trim($postObj->Content));
                $content = $this->robotAnswer($question);
            }

            $this->responseText($postObj,$content);
        }
    }

    /*
     * 解梦
     * 手动执行 http://121.201.108.221/xuechaozhang/weight/frontend/web/index.php?r=index/dream
     */
    public function actionDream($word){
        $ch = curl_init();
        $url = "http://apis.baidu.com/txapi/dream/dream?word={$word}";
        $header = array(
            'apikey: '.Yii::$app->params['baiduApiKey'],
        );
        // 添加apikey到header
        curl_setopt($ch, CURLOPT_HTTPHEADER  , $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 执行HTTP请求
        curl_setopt($ch , CURLOPT_URL , $url);
        $res = curl_exec($ch);
        $res = json_decode($res,true);
        if($res['code'] == 200){
            $content = "薛神解梦时刻：\n 关键字：{$res['newslist']['0']['title']}\n类型：{$res['newslist']['0']['type']}\n梦境揭晓时刻：\n{$res['newslist']['0']['result']}";
        }else{
            $content = "薛神功力有限，这个梦不好解";
        }
        return $content;
    }

    /*
     * 冷笑话
     * 手动执行 http://121.201.108.221/xuechaozhang/weight/frontend/web/index.php?r=index/laugh-time
     */
    public function actionLaughTime($postObj){
        $type = rand(0,1);
        $page = rand(0,1450);
        $id = rand(0,19);
        if($type == 1){
            $joke_type = 'joke_text';
        }else{
            $joke_type = 'joke_pic';
        }
        $ch = curl_init();
        $url = "http://apis.baidu.com/showapi_open_bus/showapi_joke/{$joke_type}?page={$page}";
        $header = array(
            'apikey: '.Yii::$app->params['baiduApiKey'],
        );
        // 添加apikey到header
        curl_setopt($ch, CURLOPT_HTTPHEADER  , $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 执行HTTP请求
        curl_setopt($ch , CURLOPT_URL , $url);
        $res = curl_exec($ch);
        $res = json_decode($res,true);
        if($joke_type == 'joke_text'){
            $content = "薛神冷笑话时刻来临：\n".$res['showapi_res_body']['contentlist'][$id]['text'];
            return $content;
        }else{
            $arr = array(
                array(
                    'title' => "薛神升级版图文冷笑话来临：\n" . $res['showapi_res_body']['contentlist'][$id]['title'] ,
                    'description' => $res['showapi_res_body']['contentlist'][$id]['title'],
                    'picUrl' => $res['showapi_res_body']['contentlist'][$id]['img'],
                    'url' => $res['showapi_res_body']['contentlist'][$id]['img'],
                ),
            );
            $this->responseNews($postObj,$arr);
        }
    }

    /*
     * 星座运势
     * 手动执行 http://121.201.108.221/xuechaozhang/weight/frontend/web/index.php?r=index/star
     */
    public function actionStar($cons_name){
        $ch = curl_init();
        $url = "http://apis.baidu.com/bbtapi/constellation/constellation_query?consName={$cons_name}&type=today";
        $header = array(
            'apikey:'.Yii::$app->params['baiduApiKey'],
        );
        // 添加apikey到header
        curl_setopt($ch, CURLOPT_HTTPHEADER  , $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 执行HTTP请求
        curl_setopt($ch , CURLOPT_URL , $url);
        $res = curl_exec($ch);
        $res = json_decode($res,true);

        $content = "{$res['datetime']}\n{$cons_name}运势:\n速配星座：{$res['QFriend']}\n综合指数：{$res['all']}\n幸运色：{$res['color']}\n健康指数：{$res['health']}\n爱情指数：{$res['love']}\n财运指数：{$res['money']}\n工作指数：{$res['work']}\n总结：{$res['summary']}\n温馨提示:切换到输入栏输入十二星座中的一个的名字，就能获取对应星座运势。";
        return $content;
    }



    /*
     * 签到功能
     * 手动执行 http://121.201.108.221/xuechaozhang/weight/frontend/web/index.php?r=index/sign
     */
    public function actionSign(){
        $last_sign_date = Yii::$app->redis->get("sign_date");
        if(time() - $last_sign_date >= 24*3600){
            //超过一天，可以签到
            Yii::$app->redis->incrby("coin", 1);
            $content = date("Y年m月d日")."签到成功，获得1金币，请继续保持";
            Yii::$app->redis->set("sign_date",time());
        }else{
            $content = date("Y年m月d日")."已经签到过了，请不要作弊，保持正确的游戏态度哦";
        }
        return $content;
    }

    /*
     * 抽奖功能
     * 手动执行 http://121.201.108.221/xuechaozhang/weight/frontend/web/index.php?r=index/draw
     */
    public function actionDraw(){
        $last_draw_date = Yii::$app->redis->get("draw_date");
//        $last_draw_date = Yii::$app->redis->get("count");
        if($last_draw_date < 7){
            $a = rand(1,10000);
            $content = date("Y年m月d日")."抽奖结果\n中奖号码展示如下：\n 特等奖：listen one day，8888\n一等奖：go out eating，9000-9025\n二等奖：one movie，100-125\n三等奖：5金币，5000-5099\n安慰奖：1金币，6666-6888\n本次抽奖结果：{$a}\n";

            if($a == '8888'){
                $content .= "恭喜你获得最牛逼的特等奖";
            }elseif($a >='9000' && $a<='9025'){
                $content .= "恭喜你,你终于可以出去吃一顿饭啦";
            }elseif($a >='100' && $a<='125'){
                $content .= "恭喜你,你可以自选一部电影出去看啦";
            }elseif($a >='5000' && $a<='5099'){
                $content .= "恭喜你,斩获宝贵的5金币";
                Yii::$app->redis->incrby("coin", 5);
            }elseif($a >='6666' && $a<='6888'){
                $content .= "勉强获得安慰奖，1金币，再接再厉";
                Yii::$app->redis->incrby("coin", 1);
            }else{
                $content .= "很遗憾，请相信明天会更好";
            }
            Yii::$app->redis->set("draw_date",date("Ymd"));
//            Yii::$app->redis->incr("count");
        }else{
            $content = date("Y年m月d日")."已经抽奖过了，作弊是会被发现的哦";
//            $content = "抽了七次了 还想抽啊 脸呢？";
        }

        return $content;
    }

    /*
     * 自动答复功能
     * 需要手动执行 http://121.201.108.221/xuechaozhang/weight/frontend/web/index.php?r=index/robot-answer
     */
    public function robotAnswer($info){
        $ch = curl_init();
        $key = '879a6cb3afb84dbf4fc84a1df2ab7319';
        $id = 'eb2edb736';
        $url = "http://apis.baidu.com/turing/turing/turing?key={$key}&info={$info}&userid={$id}";
        $header = array(
            'apikey:' . Yii::$app->params['baiduApiKey'],
        );
        // 添加apikey到header
        curl_setopt($ch, CURLOPT_HTTPHEADER  , $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 执行HTTP请求
        curl_setopt($ch , CURLOPT_URL , $url);
        $res = curl_exec($ch);

        $res = json_decode($res,true);
        return $res['showtext'];
    }

    /*
     * 群发消息接口
     * 需要手动执行 http://121.201.108.221/xuechaozhang/weight/frontend/web/index.php?r=index/send-msg-all
     */
    public function actionSendMsgAll(){
//        1.获取全区access token
        $access_token = $this->getAccessToken();
//        2.组装群发接口
        $url = "https://api.weixin.qq.com/cgi-bin/message/mass/preview?access_token={$access_token}";

        $content = [
            'touser' => 'oZUvEw_SiWiifl0PHGme7STl_q7I',//用户的open_id
            'text' => [
                'content' => urlencode("七夕快乐"),
            ],
            "msgtype" => "text",
        ];
        $postJson = urldecode(json_encode($content));
        $res = $this->http_curl($url, 'post', 'json', $postJson);
        print_r($res);
    }


    private function getCoin(){
        $coin = Yii::$app->redis->get("coin");
        return "您的金币数：{$coin}个";
    }

    private function checkWeather(){
        $weather = $this->actionGetWeather();
        $content = $weather['HeWeather data service 3.0']['0']['basic']['city'] . $weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['date'] ."天气预报\n" . "温度："
            .$weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['tmp']['min'] . "-" .$weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['tmp']['max']
            . "\n" . "天气情况：" . $weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['cond']['txt_d'] . "转"
            . $weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['cond']['txt_n'];
        return $content;
    }

    /*
     * 获取脑经急转弯
     * 需要手动执行 http://121.201.108.221/xuechaozhang/weight/frontend/web/index.php?r=index/show-trick
     */
    public function actionShowTrick(){
        $ch = curl_init();
        $url = 'http://apis.baidu.com/txapi/naowan/naowan';
        $header = array(
            'apikey: '. Yii::$app->params['baiduApiKey'],
        );
        // 添加apikey到header
        curl_setopt($ch, CURLOPT_HTTPHEADER  , $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 执行HTTP请求
        curl_setopt($ch , CURLOPT_URL , $url);
        $res = curl_exec($ch);
        $res = json_decode($res,true);
//        Yii::$app->redis->hset("trick", $res['newslist'][0]['id'], $res['newslist'][0]['result']);
//        $content = $res['newslist'][0]['quest'] . "\n". "<a href='http://121.201.108.221/xuechaozhang/weight/frontend/web/index.php?r=index/show-trick&id='".$res['newslist'][0]['id'] ."'>查看答案</a>";
        $content = $res['newslist'][0]['quest'] . "\n答案：". $res['newslist'][0]['result'];
        return $content;
    }

    /*
     * 获取脑经急转弯
     * 需要手动执行 http://121.201.108.221/xuechaozhang/weight/frontend/web/index.php?r=index/show-answer
     */
    public function actionShowAnswer(){
        $id = $_GET['id'];
        $answer = Yii::$app->redis->hget("trick", $id);
        return $this->renderPartial("answer", ['answer' => $answer]);
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

//    /*
//     * 发送模板消息
//     * 需要手动执行 http://121.201.108.221/xuechaozhang/weight/frontend/web/index.php?r=index/send-template-msg
//     */
//    public function actionSendTemplateMsg(){
//        $weather = $this->actionGetWeather();
//        $weather_report = $weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['cond']['txt_d'] . "转" . $weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['cond']['txt_n'];
//
//        if(stripos($weather_report, '雨') !== false){
//            $place = $weather['HeWeather data service 3.0']['0']['basic']['city'];
//            $date = $weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['date'];
//            $weather = "天气：" . $weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['cond']['txt_d'] . "转"
//                . $weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['cond']['txt_n'] ."\n"
//                . "温度" . $weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['tmp']['min'] . "-" .$weather['HeWeather data service 3.0']['0']['daily_forecast']['0']['tmp']['max'];
//
//            //1.获取到access token
//            $access_token = $this->getAccessToken();
//            // 2.组装数组
//            $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$access_token}";
//            // 3.将数组成json
//            $content = [
//                'touser' => Yii::$app->params['zhaoOpenId'],
//                'template_id' => 'LLkHbz2I2HyEdfCyD08O9tad8Jvzb_eHgPtQlNkK02Q',
//                'url' => 'http://www.weather.com.cn/weather/101210106.shtml',
//                'data' => [
//                    'place' => [
//                        'value' => $place,
//                        'color' => '#173177',
//                    ],
//                    'date' => [
//                        'value' => $date,
//                        'color' => '#173177',
//                    ],
//                    'weather' => [
//                        'value' => $weather,
//                        'color' => '#173177',
//                    ],
//                ],
//            ];
//            $content = json_encode($content);
//            file_put_contents();
//            $this->http_curl($url,'post','json', $content);
//        }else{
//            return;
//        }
//    }

    /*
     * 获取用户网络授权
     * 需要手动执行 http://121.201.108.221/xuechaozhang/weight/frontend/web/index.php?r=index/get-user-info
     */
    public function actionGetUserInfo(){
        //1.获取code
        $appid = Yii::$app->params['appid'];
        $redirect_uri = urlencode(Yii::$app->params['redirectUri'] . '/xuechaozhang/weight/frontend/web/index.php?r=index/get-user-openid');
        $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$appid}&redirect_uri={$redirect_uri}&response_type=code&scope=snsapi_base&state=123";
        header('location:' .$url );
    }

    /*
     * 获取用户网络授权时用户的code
     */
//    public function actionGetWxCode(){
//
//    }

    /*
     * 获取用户网络授权时用户的openid
     */
    public function actionGetUserOpenid(){
        //2.获取到网络授权的access_token
        $appid = Yii::$app->params['appid'];
        $app_secret = Yii::$app->params['appSecret'];
        $code = $_GET['code'];
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$appid}&secret={$app_secret}&code={$code}&grant_type=authorization_code ";
        //3.拉取用户的openid
        $ret = $this->http_curl($url, 'get');
//        if(isset($ret['access_token'])){
//
//        }
        print_r($ret);
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

    public function actionGetServerIp()
    {
        $accessToken = "d86GmERWJuuAbeJ-9OASV7jEPfG0tuGlVYf2rd3hsWYvFgoL1pZpUqpxGcG82mRa9osrtup_HJn07bzkek44p1bBDCY50q-XnDDTFQE2PEiDxclxYpMwbgLPHmNaL61FKWTaADAUGD";
        $url = "https://api.weixin.qq.com/cgi-bin/getcallbackip?access_token=" . $accessToken;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        curl_close($ch);
//        if (curl_errno($ch)) {
//            var_dump(curl_error($ch));
//        }
        $arr = json_decode($res, true);
        echo "<pre>";
        var_dump($arr);
        echo "</pre>";
    }

    public function responseNews($postObj ,$arr){
        $toUser = $postObj->FromUserName;
        $fromUser = $postObj->ToUserName;
        $template = "<xml>
					<ToUserName><![CDATA[%s]]></ToUserName>
					<FromUserName><![CDATA[%s]]></FromUserName>
					<CreateTime>%s</CreateTime>
					<MsgType><![CDATA[%s]]></MsgType>
					<ArticleCount>".count($arr)."</ArticleCount>
					<Articles>";
        foreach($arr as $k=>$v){
            $template .="<item>
						<Title><![CDATA[".$v['title']."]]></Title> 
						<Description><![CDATA[".$v['description']."]]></Description>
						<PicUrl><![CDATA[".$v['picUrl']."]]></PicUrl>
						<Url><![CDATA[".$v['url']."]]></Url>
						</item>";
        }

        $template .="</Articles>
					</xml> ";
        echo sprintf($template, $toUser, $fromUser, time(), 'news');
    }

    // 回复单文本
    public function responseText($postObj,$content){
        $template = "<xml>
		<ToUserName><![CDATA[%s]]></ToUserName>
		<FromUserName><![CDATA[%s]]></FromUserName>
		<CreateTime>%s</CreateTime>
		<MsgType><![CDATA[%s]]></MsgType>
		<Content><![CDATA[%s]]></Content>
		</xml>";
        //注意模板中的中括号 不能少 也不能多
        $fromUser = $postObj->ToUserName;
        $toUser   = $postObj->FromUserName;
        $time     = time();
        $msgType  = 'text';
        echo sprintf($template, $toUser, $fromUser, $time, $msgType, $content);
    }

    //回复微信用户的关注事件
    public function responseSubscribe($postObj, $arr){

        $this->responseNews($postObj,$arr);
    }

    /*
     * 自定义菜单接口
     * 需要手动执行 http://121.201.108.221/xuechaozhang/weight/frontend/web/index.php?r=index/defined-item
     */
    public function actionDefinedItem(){
        //创建微信菜单
        //目前微信接口的调用都是通过curl post或者get的
        header('content-type:text/html;charset=utf-8');

        $accessToken = $this->getAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token={$accessToken}";
//        $url = "https://api.weixin.qq.com/cgi-bin/menu/trymatch?access_token={$accessToken}";
        $postArr = [
            'button' => [
                [
                    'name' => urlencode('生活'),
                    'sub_button' => [
                        [
                            'name'=> urlencode('锻炼开始'),
                            'type'=> 'click',
                            'key'=> 'exercise_start',
                        ],
                        [
                            'name'=> urlencode('锻炼结束'),
                            'type'=> 'click',
                            'key'=> 'exercise_end',
                        ],
                        [
                            'name'=> urlencode('奖励申请'),
                            'type'=> 'view',
                            'url'=> 'http://121.201.108.221/xuechaozhang/weight/frontend/web/index.php?r=reward/index',
                        ]
                    ]
                ],
                [
                    'name'=> urlencode('品质'),
                    'sub_button' => [
                        [
                            'name'=> urlencode('杭州天气'),
                            'type'=> 'click',
                            'key'=> 'weather',
                        ],
                        [
                            'name'=> urlencode('薛神解梦'),
                            'type'=> 'click',
                            'url'=> 'jiemeng',
                        ],
                        [
                            'name'=> urlencode('脑经急转弯'),
                            'type'=> 'click',
                            'key'=> 'trick',
                        ],
                        [
                            'name'=> urlencode('薛神冷笑话'),
                            'type'=> 'click',
                            'key'=> 'laugh',
                        ],
                        [
                            'name'=> urlencode('薛神解星座'),
                            'type'=> 'click',
                            'key'=> 'star',
                        ],

                    ]
                ],
                [
                    'name'=> urlencode('About Me'),
                    'sub_button' => [
                        [
                            'name'=> urlencode('我的签到'),
                            'type'=> 'click',
                            'key'=> 'sign',
                        ],
                        [
                            'name'=> urlencode('我的抽奖'),
                            'type'=> 'click',
                            'key'=> 'draw',
                        ],
                        [
                            'name'=> urlencode('我的金币'),
                            'type'=> 'click',
                            'key'=> 'my_coin',
                        ],
                        [
                            'name'=> urlencode('我的商城'),
                            'type'=> 'click',
                            'key'=> 'online_shop',
                        ]
                    ]
                ]
            ],
        ];
        $postJson = urldecode(json_encode($postArr));
        $res = $this->http_curl($url,'post','json',$postJson);
        print_r($res);die;
    }
}
