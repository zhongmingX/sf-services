<?php

$params = array_merge(
        require(__DIR__ . '/../../common/config/params.php'), require(__DIR__ . '/params.php')
);

return [
    'id' => 'shenglife-api',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'service\controllers',
    'defaultRoute' => 'default',
    'bootstrap' => ['log'],
    'modules' => [

    ],
    'components' => [
        'user' => [
            'identityClass' => false,
            'enableAutoLogin' => false,
            'loginUrl' => null
        ],
        'request' => [
//            'csrfParam' => 'oskc_92~0',
            'enableCsrfValidation' => false,
            'cookieValidationKey' => 'EJBy8WRohzpqJY7BTurjQaft2NV-g1cA',
            'enableCookieValidation' => false,
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ]
        ],


//        'response' => [
//            'class' => 'yii\web\Response',
//            'on beforeSend' => function ($event) {
//                $response = $event->sender;
//                $response->data = [
//                    'code' => $response->getStatusCode(),
//                    'data' => $response->data,
//                    'message' => $response->statusText
//                ];
//                $response->format = yii\web\Response::FORMAT_JSON;
//            },
//        ],

        'urlManager' => [
            /*
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            */
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'suffix'=>'.html',
            /*
            'rules' => [
                ['class' => 'yii\rest\UrlRule', 'controller' => 'common'],
                ['class' => 'yii\rest\UrlRule', 'controller' => 'merchants'],
                ['class' => 'yii\rest\UrlRule', 'controller' => 'default']
            ],
            */
        ],
//        'errorHandler' => [
//            'class' => 'service\controllers\handler\ErrorHandler'
//        ],

    ],
    'params' => $params,
];
