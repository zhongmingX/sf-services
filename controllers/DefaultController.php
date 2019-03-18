<?php

namespace service\controllers;
use common\components\CommonFun;
use yii\web\Controller;


class DefaultController extends BaseController {
    public function actionIndex() {
	retrun \Yii::$end("error!");
    }
}
