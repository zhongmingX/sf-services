<?php

namespace service\controllers;
use common\components\CommonFun;
use common\components\CommonValidate;
use common\components\enum\OrderEnum;
use common\components\Notice;
use common\components\Push;
use common\components\SendSms;
use common\extend\OSS\Common;
use common\models\ExchangePoint;
use common\models\Logs;
use common\models\MembersExtracts;
use common\models\MerchantsAccount;
use common\models\MerchantsExtracts;
use common\models\MerchantsNotice;
use common\models\MiniTemplateinfo;
use common\models\Orders;
use common\models\WeixinFans;
use yii\web\Controller;
use abei2017\wx\Application;
use common\models\MerchantsRemind;
use common\models\OrdersAddress;
use common\models\CommonModel;


class NoticeController extends BaseController {

    //支付消息
    public function actionPayment(){
        if($this->isPost){
            $order_id = \Yii::$app->request->post('order_id');
            $order = Orders::findOne($order_id);
            if(!$order){
                return false;
            }

            //看是否是小程序支付
            if($order->pay_type == OrderEnum::PAY_TYPE_WXMINI){
                return $this->weixinMiniPayment($order_id);
            }

            $type = 'offline_pay';
            if($order->type == OrderEnum::TYPE_SHOP){
                $type = 'online_pay';
                $data = [
                    'productType' => [
                        'value' => '商品'
                    ],
                    'name' => [
                        'value' => $order->title,
                        'color' => '#ff6737'
                    ],
                    'accountType' => [
                        'value' => ''
                    ],
                    'account' => [
                        'value' => CommonFun::doNumber($order->pay_amount).'元'. ' 订单完成获得省币:'.CommonFun::doNumber($order->member_coin)
                    ],
                    'time' => [
                        'value' => date('Y-m-d H:i')
                    ],
                    'remark' => [
                        'value' => '省生活，省出品质生活。'
                    ]
                ];
            }else if ($order->type == OrderEnum::TYPE_EXCHANGE_OFF){
                $point = ExchangePoint::findOne($order->order_obj_id);
                $data = [
                    'first' => [
                        'value' => $order->title,
                        'color' => '#ff6737'
                    ],
                    'keyword1' => [
                        'value' => $order->order_sn
                    ],
                    'keyword2' => [
                        'value' => '省币'.CommonFun::doNumber($order->pay_coin) . ' + ' . CommonFun::doNumber($order->pay_amount).'元'
                    ],
                    'keyword3' => [
                        'value' => $point->name.'-兑换点'
                    ],
                    'keyword4' => [
                        'value' => date('Y-m-d H:i')
                    ],
                    'remark' => [
                        'value' => '省生活，省出品质生活。'
                    ]
                ];
            }else if($order->type == OrderEnum::TYPE_ONLINEOFF_PAY){
                $merchant = MerchantsAccount::findOne($order->order_obj_id);
                $data = [
                    'first' => [
                        'value' => '当前消费获得省币'.CommonFun::doNumber($order->member_coin),
                        'color' => '#ff6737'
                    ],
                    'keyword1' => [
                        'value' => $order->order_sn
                    ],
                    'keyword2' => [
                        'value' => CommonFun::doNumber($order->pay_amount).'元'
                    ],
                    'keyword3' => [
                        'value' => $merchant->name
                    ],
                    'keyword4' => [
                        'value' => date('Y-m-d H:i')
                    ],
                    'remark' => [
                        'value' => '省生活，省出品质生活。'
                    ]
                ];
            }else if($order->type == OrderEnum::TYPE_EXCHANGE){
                $type = 'online_pay';
                $data = [
                    'productType' => [
                        'value' => '兑换商品'
                    ],
                    'name' => [
                        'value' => $order->title,
                        'color' => '#ff6737'
                    ],
                    'accountType' => [
                        'value' => ''
                    ],
                    'account' => [
                        'value' => '省币'.CommonFun::doNumber($order->pay_coin) . ' + '.CommonFun::doNumber($order->pay_amount).'元'
                    ],
                    'time' => [
                        'value' => date('Y-m-d H:i')
                    ],
                    'remark' => [
                        'value' => '省生活，省出品质生活。'
                    ]
                ];
            }
            $weFans = WeixinFans::findOne(['member_id'=>$order->member_id]);
            return Notice::sendWeixin($type, $weFans->openid, $data);
        }
    }

    //微信小程序支付，通知
    function weixinMiniPayment($order_id){
        $sql = "select * from sf_mini_templateinfo where find_in_set(".$order_id.",obj_id) and type=1 and is_send = 0 order by ctime desc limit 1";
        $mini = \Yii::$app->db->createCommand($sql)->queryOne();
        if($mini){
            $ids = explode(',', $mini['obj_id']);
            $title = '';
            $order_sn = [];
            $price = 0;
            $coin = 0;
            foreach ($ids as $id){
                $order = Orders::findOne($id);
                $order_sn[] = strval($order->order_sn);
                if(count($ids) > 1){
                    $title = '多条订单合并支付';
                }else{
                    $title = $order->title;
                }
                $price = CommonFun::doNumber($price, $order->pay_amount);
                $coin = CommonFun::doNumber($coin, $order->pay_coin);
            }

            $data['keyword1'] = [
                'value' => join(',', $order_sn)
            ];
            $data['keyword2'] = [
                'value' => $title
            ];
            $data['keyword3'] = [
                'value' => (($price>0)?$price.'元':'') . ' ' . (($coin>0)?$coin.'省币':'')
            ];
            $data['keyword4'] = ['value'=> '感谢您支付成功,如有疑问请联系客服!'];
            $data['keyword5'] = ['value' => '400-028-2820'];

            $templateId = 'x2vDLLYPu_U_IDJXC99erdrdnH7WZAw4MgNN2vanyc8';
            $app = new Application(['conf'=>\Yii::$app->params['wxmini']]);
            $template = $app->driver("mini.template");

            $res = $template->send($mini['openid'], $templateId, $mini['form_id'], $data, $extra = ['page' => 'pages/index/index']);
            $res = json_decode($res, true);
            if($res['errcode'] == 0){
                $tmp = MiniTemplateinfo::findOne($mini['id']);
                $tmp->is_send = 1;
                $tmp->send_time = time();
                $tmp->save();
                return true;
            }else{
                return $res;
            }
        }
    }

    //发货
    public function actionShipping(){
        if($this->isPost){
            $order_id = \Yii::$app->request->post('order_id');
            $order = Orders::findOne($order_id);
            if(!$order){
                return false;
            }

            $weFans = WeixinFans::findOne(['member_id'=>$order->member_id]);
            if(in_array($order->type, [OrderEnum::TYPE_SHOP, OrderEnum::TYPE_EXCHANGE])){
                if($order->order_status == OrderEnum::ORDER_STATUS_DELIVERY){
                    $data = [
                        'first' => [
                            'value' => $order->title,
                            'color' => '#ff6737'
                        ],
                        'keyword1' => [
                            'value' => $order->order_sn
                        ],
                        'keyword2' => [
                            'value' => $order->shipping_name
                        ],
                        'keyword3' => [
                            'value' => $order->shipping_sn
                        ],
                        'remark' => [
                            'value' => '如有疑问，请致电省生活客服热线：4000282820'
                        ]
                    ];

                    return Notice::sendWeixin('shipping', $weFans->openid, $data);
                }
            }
        }
        return false;
    }

    //给商家发消息
    public function actionMerchant(){
        
        if($this->isPost){
            $order_id = \Yii::$app->request->post('order_id',1);
            $order = Orders::findOne($order_id);
            if(!$order){
                return false;
            }

            if($order->payment_status != OrderEnum::PAY_STATUS_PAYED){
                return false;
            }

            switch ($order->type){
                case OrderEnum::TYPE_SHOP:
                    $data = [
                        'title' => $order->title,
                        'content' => '订单ID：'.$order_id.'请发货',
                        'obj_id' => $order_id
                    ];
                    MerchantsNotice::record($order->order_obj_id, MerchantsNotice::TYPE_ORDER_DELIVERY, $data);
                    break;
                case OrderEnum::TYPE_EXCHANGE:
                    Notice::submitManager('order', $order_id);
                    break;
                case OrderEnum::TYPE_ONLINEOFF_PAY:
                    $data = [
                        'title' => $order->title,
                        'content' => '订单ID：'.$order_id.'已经支付成功',
                        'obj_id' => $order_id
                    ];
                    //记录
                    MerchantsNotice::record($order->order_obj_id, MerchantsNotice::TYPE_ORDER_DELIVERY, $data);
                    
                    //处理推送给商家自定义通知人员*RTS*ADD 2018年10月10日10:02:10
                    $remind = MerchantsRemind::getList(['merchants_id' => $order->order_obj_id]);
                    
                    if(!empty($remind['data'])){
                        $data = $this->getFinancialTemplate($order_id);
                        if($data !== false){
                            foreach ($remind['data'] as $item){
                                $res = Notice::sendWeixin('financial', $item['open_id'], $data);
                            }
                        }
                    }
                    break;
                case OrderEnum::TYPE_EXCHANGE_OFF:
                    $exchange = ExchangePoint::findOne($order->order_obj_id);
                    if($exchange){
                        if($exchange->merchants_id > 0){ //商家有绑定，发送通知
                            $data = [
                                'title' => $order->title,
                                'content' => '兑换订单：'.$order_id.'请查看处理',
                                'obj_id' => $order_id
                            ];
                            MerchantsNotice::record($exchange->merchants_id, MerchantsNotice::TYPE_EXCHANGE, $data);
                        }

                        //通知
                        Notice::submitManager('order',3);
                    }
                    break;
            }

            //通知其它环节，这里以后加钩子

            //预留，商家自定义通知对象， 需要关注微信服务号, 使用用户ID为查出服务号openid

            //财务发送通知
            
            Notice::submitFinancial($order_id);
            return true;
        }
        return false;
    }

    //给用户提现发送通知
    public function actionMemberExtract(){
        if($this->isPost){
            $id = \Yii::$app->request->post('id');
            $memberExtract = MembersExtracts::findOne($id);
            if($memberExtract){
                $type = '';
                $data = [];
                $weFans = WeixinFans::findOne(['member_id'=>$memberExtract->member_id]);
                if($memberExtract->status == MerchantsExtracts::STATUS_AUDIT_OK){
                    $type = 'extract_success';
                    $data = [
                        'first' => [
                            'value' => '您的提现申请已经审核通过',
                            'color' => '#ff6737'
                        ],
                        'keyword1' => [
                            'value' => $memberExtract->amount
                        ],
                        'keyword2' => [
                            'value' => date('Y-m-d', strtotime('+1 day'))
                        ],
                        'remark' => [
                            'value' => '省生活，省出品质生活。 点击【我的余额】了解更多'
                        ]
                    ];
                }else if ($memberExtract->status == MerchantsExtracts::STATUS_AUDIT_NO){
                    $type = 'extract_fail';
                    $data = [
                        'first' => [
                            'value' => '您的提现申请未审核通过',
                            'color' => '#ff6737'
                        ],
                        'keyword1' => [
                            'value' => $memberExtract->amount
                        ],
                        'keyword2' => [
                            'value' => $memberExtract->record
                        ],
                        'remark' => [
                            'value' => '如有疑问，请致电省生活客服热线：4000282820'
                        ]
                    ];
                }

                if($type){
                    return Notice::sendWeixin($type, $weFans->openid, $data);
                }
            }
            return false;

        }
        return false;
    }
    
    /**
     * 获取财务通知模板
     * @param number $orderId
     */
    private function getFinancialTemplate($orderId = 0){
        $order = Orders::findOne($orderId);
        if(!$order){
            return false;
        }
        
        if($order->payment_status != OrderEnum::PAY_STATUS_PAYED){
            return false;
        }
        
        $value = '订单ID:'.$order->id;
        if(in_array($order->type, [OrderEnum::TYPE_EXCHANGE_OFF, OrderEnum::TYPE_EXCHANGE])){
            $value .= ' 兑换点:'.ExchangePoint::getPointName($order->order_obj_id);
            $value .= ' 商品:'.$order->title;
        }else if($order->type == OrderEnum::TYPE_ONLINEOFF_PAY){
            $value .= ' 商家:'.MerchantsAccount::getName($order->order_obj_id);
        }
        
        $data = [
            'first' => [
                'value' => '用户ID:'.$order->member_id.' 使用'.OrderEnum::$PAY_TYPE[$order->pay_type].'支付成功, 请查收',
                'color' => '#ff6737'
            ],
            'keyword1' => [
                'value' => OrderEnum::$TYPES[$order->type],
                'color' => '#ff6737'
            ],
            'keyword2' => [
                'value' => (($order->pay_amount > 0)?'人民币:'.CommonFun::doNumber($order->pay_amount):'') . (($order->pay_coin > 0)?' 省币:'.CommonFun::doNumber($order->pay_coin):'')
            ],
            'keyword3' => [
                'value' => date('Y-m-d H:i:s', $order->pay_ctime)
            ],
            'remark' => [
                'value' => $value
            ]
        ];
        return $data;
    }

    /**
     * 给财务发消息
     */
    public function actionFinancial(){
        if($this->isPost) {
            //订单ID
            $id = \Yii::$app->request->post('id');
            $data = $this->getFinancialTemplate($id);
            if($data == false){
                return false;
            }

            $notices = \Yii::$app->params['notice'];
            if(isset($notices['financial'])){
                foreach ($notices['financial'] as $item){
                    Notice::sendWeixin('financial', $item, $data);
                }
            }
            return true;
        }
    }

    /**
     * 给库管发通知
     */
    public function actionWarehouse(){

    }

    //给管理发消息
    public function actionManager(){
        if($this->isPost){
            $type = \Yii::$app->request->post('type');
            $msg = \Yii::$app->request->post('msg');
            $orderId = \Yii::$app->request->post('orderId',0);
            if(!$type){
                return false;
            }
            $notices = \Yii::$app->params['notice'];
            switch ($type){
                case 'warehouse':
                    foreach ($notices['warehouse'] as $item){
                        $wechat = \Yii::$app->wechat;
                        $wechat->sendText($item, $msg);
                    }
                    return true;
                    break;
                case 'order':
                    foreach ($notices['order'] as $item){
                        if(!empty($orderId)){
                            $data = $this->getManagerTemplate($orderId,1);
                            //$item = 'ooS7e0qDCAirKo-HW95-DynB0v_4';
                            //$item = 'ooS7e0lpKC7DlszwH1iaB0F2hfLM';                            
                            Notice::sendWeixin('manager_order', $item, $data);
                        }else {//兼容未传递orderId的调用 发送客服消息
                            $wechat = \Yii::$app->wechat;
                            $wechat->sendText($item, $msg);
                        }
                    }
                    break;
                case 'financial':
                    foreach ($notices['financial'] as $item){
                        $wechat = \Yii::$app->wechat;
                        $wechat->sendText($item, $msg);
                    }
                    break;
            }
            return true;
        }
        return false;
    }
    
    /**
     * 获取管理员模板内容
     * @param number $orderId
     * @param number $type
     * @return boolean|string[][]
     */
    private function getManagerTemplate($orderId = 0,$type = 0){
        $data = [];
        switch ($type){
            case 0://备用
            default:
                break;
            case 1://订单
                $order = Orders::findOne($orderId);
                if(!$order){
                    return false;
                }
                if($order->payment_status != OrderEnum::PAY_STATUS_PAYED){
                    return false;
                }
                $keyword3 = '用户ID:'.$order->member_id;
                if($order->type == OrderEnum::TYPE_EXCHANGE){
                    $address = $order->address;// OrdersAddress::findOne(['order_id' => $orderId,'member_id' => $order->member_id,'status' => CommonModel::STATUS_ACTIVE]);
                    if(!empty($address)){
                        $keyword3 .= "，{$address->name}，{$address->mobile}，地址：{$address->address}";
                    }
                }

                $keyword1 = '['.OrderEnum::$TYPES[$order->type].']'.$order->title;
                $productInfo = $order->product;
                if(!empty($productInfo)){
                    $keyword1 .='，数量：'.$productInfo[0]['number'];
                }
                
                $data = [
                    'first' => [
                        'value' => '订单编号：'.$order->order_sn,
                        'color' => '#ff6737'
                    ],
                    'keyword1' => [
                        'value' => $keyword1,
                    ],
                    'keyword2' => [
                        'value' => (($order->pay_amount > 0) ? '人民币：'.CommonFun::doNumber($order->pay_amount):'') . (($order->pay_coin > 0)?'省币：'.CommonFun::doNumber($order->pay_coin):'')
                    ],
                    'keyword3' => [
                        'value' => $keyword3,
                    ],
                    'keyword4' => [
                        'value' => OrderEnum::$PAY_TYPE[$order->pay_type],
                    ],
                    'keyword5' => [
                        'value' => $order->comment,
                    ],
                    'remark' => [
                        'value' => '用户订单，请及时处理。'
                    ]
                ];
                break;
        }
        return $data;
    }

    public function actionSubscribe(){
        if($this->isPost) {
            //订单ID
            $id = \Yii::$app->request->post('id');
            $openid = \Yii::$app->request->post('openid');

            if(!$openid){
                return 'openid不存在';
            }

            $model = MerchantsAccount::findOne($id);
            if(!$model){
                return '商家不存在,id:'.$id;
            }

            $data = [
                'first' => [
                    'value' => '恭喜扫码关注成功, 省生活兑兑欢迎您！'
                ],
                'keyword1' => [
                    'value' => $model->name,
                ],
                'keyword2' => [
                    'value' => date('Y-m-d H:i')
                ],
                'remark' => [
                    'value' => '点击快捷结账，在线支付得省币！',
                    'color' => '#ff0000'
                ]
            ];

            return Notice::sendWeixin('qrcode_focus', $openid, $data, ['appid'=>'wxc79ca52b26d4b6d9', 'pagepath'=>'pages/merchant/payment/index?id='.$id]);
            return true;
        }
    }
}
