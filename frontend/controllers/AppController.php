<?php
namespace frontend\controllers;

use Yii;
use yii\web\Controller;


/**
 * 奖励接口控制器
 */
class AppController extends Controller{
    /*
     * 周公解梦
     */
    public function actionIndex()
    {
        return $this->renderPartial("jiemeng");
    }

    /*
     * 抽奖页面
     */
    public function actionDraw()
    {
        return $this->renderPartial("draw");
    }
}