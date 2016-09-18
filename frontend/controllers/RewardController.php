<?php
namespace frontend\controllers;

use Yii;
use yii\web\Controller;


/**
 * 奖励接口控制器
 */
class RewardController extends Controller{
    public function actionIndex()
    {
        return $this->renderPartial("apply");
    }
}