<?php

namespace service\controllers;
use common\components\CommonFun;
use common\components\CommonValidate;
use common\components\SendSms;
use common\extend\OSS\Common;
use common\models\Logs;
use yii\web\Controller;


class CommonController extends BaseController {

    //发送短信
    public function actionSms(){
        if($this->isPost){
            $type = \Yii::$app->request->post('type');
            $phone = \Yii::$app->request->post('phone');
            $request = \Yii::$app->request->post('request', []);
            $ext = \Yii::$app->request->post('ext', []);

            //处理手机号
            if(is_string($phone)){
                if(CommonValidate::isPhone($phone)){
                    $phone = [$phone];
                }else{
                    return false;
                }
            }

            if(!is_array($request) || empty($request)){
                return 'request not array.';
            }

            if(!is_array($ext)){
                $ext = [$ext];
            }

            $sms = new SendSms(true);
            return $sms->sendSms($type, 2, $phone, $request, $ext);
        }
        return false;
    }

    //商家与用户提现短信通知
    public function actionExtractSms(){
        if($this->isPost){
            $name = \Yii::$app->request->post('name');
            $phone = \Yii::$app->request->post('phone');
            $ext = \Yii::$app->request->post('ext', []);

            //处理手机号
            if(is_string($phone)){
                if(CommonValidate::isPhone($phone)){
                    $phone = [$phone];
                }else{
                    return false;
                }
            }

             if(CommonFun::utf8_strlen($name) < 4){
                 return false;
             }

            if(!is_array($ext)){
                $ext = [$ext];
            }

            $sms = new SendSms(true);
            return $sms->sendExtractSms($phone, $name, $ext);
        }
        return false;
    }

    public function actionEmail(){

    }

    public function actionWeixin(){

    }

    //操作日志
    public function actionLogs(){
        $type = \Yii::$app->request->post('type', 0);
        $operation_id = \Yii::$app->request->post('operation_id', 0);
        $operation_name = \Yii::$app->request->post('operation_name', '');
        $operation_account = \Yii::$app->request->post('operation_account', '');
        $record = \Yii::$app->request->post('record', '');
        $ext_content = \Yii::$app->request->post('ext_content', '');

        if(!in_array($type, [Logs::TYPE_MEMBER, Logs::TYPE_MERCHANTS, Logs::TYPE_PLATFORM])){
            return 'type error!';
        }

        $model = new Logs();
        $model->type = $type;
        $model->operation_id = $operation_id;
        $model->operation_account = $operation_account;
        $model->operation_name = $operation_name;
        $model->record = $record;
        $model->ext_content = ($ext_content)?json_encode($ext_content):'';
        $model->ctime = time();

        if($model->save()){
            return true;
        }
        return false;
    }

}
