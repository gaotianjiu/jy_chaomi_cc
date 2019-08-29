<?php

date_default_timezone_set('PRC');

//<m><c><a>分别指代modules，controller，action。
//其他的<单词>，都是_GET的参数名称。
$config = array(
    'rewrite' => array(
        'f/<typeid>'=>'main/f',
        'deal_data'=>"deal_data/index",
        '<c>$'=>"<c>/index",     
        'announce/view\?id=<id>$'=>"announce/view",        
        'sso/login\?act=<act>$'=>"sso/login",   

        
        
        'admin/index.html' => 'admin/main/index',
        'admin/<c>_<a>.html' => 'admin/<c>/<a>',
        '<m>/<c>/<a>' => '<m>/<c>/<a>',
        '<c>/<a>' => '<c>/<a>',
        '/' => 'main/index',
        
    ),
);

$domain = array(
    "my.chaomi.cc" => array(// 调试配置
        'debug' => 1,
        'mysql' => array(
            'MYSQL_HOST' => '127.0.0.1',
            'MYSQL_PORT' => '3306',
            'MYSQL_USER' => 'cmpai_user',
            'MYSQL_DB' => 'cmpai',
            'MYSQL_PASS' => 'Q3b095bGPYzWbJ4SFfP',
            'MYSQL_CHARSET' => 'utf8',
        ),
        "G_SP" => array('sp_cache' => __DIR__. "/tmp", //为兼容老版的一些函数增加的参数。
            'sp_app_id' => ''
        ) 
    ), 
    "speedphp.com" => array(//线上配置
        'debug' => 0,
        'mysql' => array(),
    ),
    
    
);
// 为了避免开始使用时会不正确配置域名导致程序错误，加入判断
//if (empty($domain[$_SERVER["HTTP_HOST"]]))
//    die("配置域名不正确，请确认" . $_SERVER["HTTP_HOST"] . "的配置是否存在！");
return $domain["my.chaomi.cc"] + $config;

//return $domain[$_SERVER["HTTP_HOST"]] + $config;
