<?php

class BaseController extends Controller {

    //public $layout = "layout.html";
    public $layout = "";
    function init() {
        header("Content-type: text/html; charset=utf-8");
        require(APP_DIR . '/protected/include/functions.php'); // 加载全局函数
        require(APP_DIR . '/protected/include/spFunctions.php'); // 加载全局函数
        
        $this->cm_nav='jy';
        $this->module='';
        $this->act='';
        $this->msg_count=0;
    }

    function tips($msg, $url) {
        $url = "location.href=\"{$url}\";";
        echo "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"><script>function sptips(){alert(\"{$msg}\");{$url}}</script></head><body onload=\"sptips()\"></body></html>";
        exit;
    }

    function jump($url, $delay = 0) {
        echo "<html><head><meta http-equiv='refresh' content='{$delay};url={$url}'></head><body></body></html>";
        exit;
    }
    function spArgs($str){
        return arg($str);
    }

    //public static function err404($module, $controller, $action, $msg){
    //	header("HTTP/1.0 404 Not Found");
    //	exit;
    //}
}
