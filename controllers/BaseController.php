<?php

namespace service\controllers;
use common\components\CommonFun;
use common\models\WeixinFans;
use Yii;
use common\models\Config;
use common\components\CommonValidate;

class BaseController extends \yii\rest\Controller {
    public $layout = false;

    public $isGet = false;
    public $isPost = false;
    public $isAjax = false;

    public $token = null;

    
    public $pageSize = 10;
    public $pageNum = 1;
    public $offset = 0;

    public $service_url;
    
    public function init() {
        $this->isAjax = CommonValidate::isAjax();
        $this->isPost = CommonValidate::isPost();
        $this->isGet = CommonValidate::isGet();

        $key = CommonFun::md5('xxooxxoo');
        //认证
        $this->token = Yii::$app->request->headers->get('token');
        if($key !== $this->token){
            $this->resultData("Token failure or error.", 400);
            Yii::$app->end();
        }

        $this->service_url = \Yii::$app->params['serviceUrl'];
        parent::init();
    }

    public function behaviors() {
        $behaviors = parent::behaviors();
        return $behaviors;
    }

    public function beforeAction($action)
    {
        parent::beforeAction($action);
        Yii::$app->params['basic'] = Config::getConfigs('basic'); //配置
        return $action;
    }

    /**
     * 返回数据
     * @param $data
     * @param $code
     * @param $message
     */
    public function resultData($data, $code = 200, $message = ''){
        Yii::$app->response->statusCode = $code;
        Yii::$app->response->statusText = $message;
        Yii::$app->response->data = $data;
    }
}
