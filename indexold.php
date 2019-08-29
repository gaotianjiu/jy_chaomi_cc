<?php

//opcache_reset();
define("SP_PATH", dirname(__FILE__) . "/SpeedPHP");
define("APP_PATH", dirname(__FILE__));
$spConfig = array(
    'controller_path' => APP_PATH . '/controller/' . basename(__FILE__, ".php"),
    'allow_trace_onrelease' => true, // 是否允许在部署模式下输出调试信息
    'dispatcher_error' => "header('HTTP/1.0 404 Not Found');exit();",
    "db" => array(
        'driver' => 'mysqli',
        'host' => '127.0.0.1',
        'login' => 'cmpai_user',
        'password' => 'Q3b095bGPYzWbJ4SFfP',
        'database' => 'cmpai',
    ),
    'mode' => 'debug',
    'view' => array(
        'dispatcher_error' => "import(APP_PATH.'/404.html');exit();",
        'enabled' => TRUE, // 开启视图
        'config' => array(
            'template_dir' => APP_PATH . '/tpl', // 模板目录
            'compile_dir' => APP_PATH . '/tmp', // 编译目录
            'cache_dir' => APP_PATH . '/tmp', // 缓存目录
            'left_delimiter' => '<{', // smarty左限定符
            'right_delimiter' => '}>', // smarty右限定符
            'debugging' => false
        ),
    ),
    'launch' => array(// 加入挂靠点，以便开始使用Url_ReWrite的功能

        'router_prefilter' => array(
            array('spUrlRewrite', 'setReWrite'), // 对路由进行挂靠，处理转向地址
        ),
        'function_url' => array(
            array("spUrlRewrite", "getReWrite"), // 对spUrl进行挂靠，让spUrl可以进行Url_ReWrite地址的生成
        ),
        'function_access' => array(
            array("spAccessCache", "memcache"),
        ),
    ),
    'ext' => array(
        'spAccessCache' => array(
            'memcache_host' => '127.0.0.1', // memcache服务器地址
            'memcache_port' => '11211', // memcache服务器端口
        ),
        // 以下是Url_ReWrite的设置
        'spUrlRewrite' => array(
            'hide_default' => false, // 隐藏默认的main/index名称，但这前提是需要隐藏的默认动作是无GET参数的
            'args_path_info' => false, // 地址参数是否使用path_info的方式，默认否
            'sep' => '/',
            'suffix' => '', // 生成地址的结尾符
            'map' => array(
                'f' => 'main@f',
                'p' => 'main@wt_price',
            ),
            'args' => array(
                'f' => array('typeid'),
                'p' => array('typeid'),
            ),
        ),
    ),
);

require(SP_PATH . "/SpeedPHP.php");
import('cons.php'); // 加载全局函数
import('functions.php'); // 加载全局函数
spRun(); // SpeedPHP 3新特性

