<?php

function domain_typeid() {
    // return array('all','411104','411107','411109','411113','411101','411102','411103','411106','412102','614101');//全局品种ID，一般用于更新成交缓存等，如添加品种需要添加上数组
    $data = get_type_list();
    $data[] = 'all';
    return $data; //全局品种ID，一般用于更新成交缓存等，如添加品种需要添加上数组
}

function typeid_price($typeid) {
    $arr = array(808001 => 1); //限制品种的最低价
    $price = $arr[$typeid];
    if ($price == 0)
        $price = 0.1;
    return $price;
}

function check() { //保持检测帐号登录的相关状态
    $mid = isset($_COOKIE['CM_MID']) ? intval($_COOKIE['CM_MID']) : 0;
    $uid = isset($_COOKIE['CM_UID']) ? intval($_COOKIE['CM_UID']) : 0;
    $token = isset($_COOKIE['CM_TOKEN']) ? trim($_COOKIE['CM_TOKEN']) : 0;
    //************临时调度指定UID**********
    // if($uid==1){
    // return array('uid'=>1955,'mid'=>1006);
    // }
    //************临时调度指定UID**********

    if (isset($mid) && !empty($mid) && isset($uid) && !empty($uid) && isset($token) && !empty($token)) {
        //----判断对应的登录会员状态缓存---begin
        $cache_name = 'cm_login_user_' . $uid . '_' . $mid; //缓存名值
        $cache_data = cache_s($cache_name);
        if ($cache_data['token_time'] > time() && $cache_data['token'] == $token) { //如果缓存返回值token_time>time()，不查询数据库
            if ($cache_data['cache_time'] < time()) {
                //---删除登录token---begin
                cache_s($cache_name, null);
                $gb_login = new pub_user_login(); //  new pub_user_login();
                $gb_login->delete(array("uid" => $uid));
                //---删除登录token---end
                return false;
            }
            $cache_data['cache_time'] = time() + 1800;
            cache_s($cache_name, $cache_data, 3600 * 24 * 2); //写缓存			
            return array('uid' => $uid, 'mid' => $mid);
        }
        //----判断对应的登录会员状态缓存---end
        $gb = new pub_user(); //new pub_user();
        if ($gb->find(array("uid" => $uid, "mid" => $mid))) {
            $gb_login =new pub_user_login();  // new pub_user_login();
            $r = $gb_login->find(array("uid" => $uid, "token" => $token));
            if ($r['token_time'] > time()) {
                $r['cache_time'] = time() + 1800;
                cache_s($cache_name, $r, 3600 * 24 * 2); //写缓存
                return array('uid' => $uid, 'mid' => $mid);
            }
        }
    }
    return false;
}

function check_out() { //退出帐号登录状态
    $sso_user = check();
    $uid = $sso_user['uid'];
    $mid = $sso_user['mid'];
    if ($mid && $uid) {
        cache_s('cm_login_user_' . $uid . '_' . $mid, null); //删除缓存
        //---删除登录token---begin
        $gb_login = new pub_user_login();
        $gb_login->delete(array("uid" => $uid));
        //---删除登录token---end
    }
    unset($_SESSION['uid']);
    unset($_SESSION['mid']);
    setcookie("CM_MID", '', time() - 600, "/", ".chaomi.cc");
    setcookie("CM_UID", '', time() - 600, "/", ".chaomi.cc");
    setcookie("CM_TOKEN", '', time() - 600, "/", ".chaomi.cc");
}

//重新登陆
function re_login() {
    d301('/sso/login');
}

function d404() { //返回404 
    header("HTTP/1.1 404 Not Found");
    exit;
}

function d301($url) { //301跳转
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: $url");
    exit;
}

function check_code() { //判断用户是否已设安全码
    $sso_user = check();
    $uid = $sso_user['uid'];
    $pan_user_safecode = new pan_user_safecode();
    $find = array('uid' => $uid);
    $r = $pan_user_safecode->find($find);
    $safecode = $r['safecode'];
    if (empty($safecode)) {
        // $this->error('请先设置安全码！', '/user/safeCode');
        d301('/user/safeCode?p=a');
        echo '<script>alert("请先设置安全码！");location.href="' . spUrl('user', 'safeCode') . '"</script>';
        exit();
    }
}

//获取用户注册商名称 
function getDomainSite($id) {
    if ($id == 851) {
        $name = '爱名网';
    } elseif ($id == 852) {
        $name = '易名中国';
    } elseif ($id == 853) {
        $name = '190数交所';
    } elseif ($id == 854) {
        $name = '万网';
    } elseif ($id == 855) {
        $name = '西部数码';
    } elseif ($id == 856) {
        $name = '易域网';
    } elseif ($id == 857) {
        $name = '优名网';
    }
    return ($name);
}

//获取用户注册商名称 反转
function getDomainSiteId($name) {
    if ($name == '爱名网') {
        $id = 851;
    } elseif ($name == '易名中国') {
        $id = 852;
    } elseif ($name == '190数交所') {
        $id = 853;
    } elseif ($name == '万网') {
        $id = 854;
    } elseif ($name == '西部数码') {
        $id = 855;
    } elseif ($name == '易域网') {
        $id = 856;
    } elseif ($name == '优名网') {
        $id = 857;
    }
    return ($id);
}

function send_mobile_email($uid, $email_title, $content) {
    // *************查询email******************************\\
    $lib_member = new lib_member();
    $ret = $lib_member->find(array('uid' => $uid));
    $email = $ret['email'];
    // *************查询手机号，mid*****************************\\
    $pub_user = new pub_user();
    $ret = array();
    $ret = $pub_user->find(array('uid' => $uid));
    $mobile = $ret['mobile'];
    $mid = $ret['mid'];
    $content = '用户' . $mid . '，' . $content;
    if (!empty($email) && preg_match("/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/", $email)) { //必须判断是否是邮箱
        //---将邮件内容以JSON格式存到数据库---begin
        $contents = array();
        $contents['to'] = array($email);
        $contents['sub'] = array('%content%' => array($content));
        $new_content = json_encode($contents);
        //---将邮件内容以JSON格式存到数据库---end		
        send_mail($email, $email_title, $new_content, 20);
    }
    if (!empty($mobile) && preg_match("/^1[34578]\d{9}$/", $mobile)) {
        send_msg($mobile, $content, 20);
    }
}

function send_mail($address, $title, $content, $type = 0) {
    //@type=1 注册 type=2 找回密码 type=3 绑定 type=5 交易 type=0其它
    //发送邮件进队列
    $data = array("email" => $address, "title" => $title, "content" => $content, "ctime" => time(), "ip" => get_client_ip(), "status" => 0, "type" => $type);
    $gb=new lib_member_smail();
    if ($gb->create($data)) {
        return true;
    } else {
        return false;
    }
}

function send_msg($mobile, $title, $type = 0) {
    //@type=1 注册 type=2 找回密码 type=3 绑定 type=5 交易 7=设置中心 type=0其它
    //发送短信进队列
    $data = array("mobile" => $mobile, "title" => $title, "ctime" => time(), "ip" => get_client_ip(), "status" => 0, "type" => $type);
    $gb=lib_member_mobile();
    if ($gb->create($data)) {
        return true;
    } else {
        return false;
    }
}

//公共登录日志
function login_log($uid, $logtype) {
    $uid = intval($uid);
    $logtime = time();
    $logip = trim(get_client_ip());
    $logtype = intval($logtype);
    if (empty($uid))
        return false;
    $log = new pub_user_log(); //引入表
    $data = array("uid" => $uid, "logtime" => $logtime, "logip" => $logip, "logtype" => $logtype);
    $log->create($data);
}

//设置委托时间
function get_wt() {
    $wt = array(
        0 => '一直有效',
        1 => '1天',
        3 => '3天',
        5 => '5天',
        10 => '10天',
    );
    return $wt;
}

/**
 * 专用检测权限token是否正确
 * 生成相关数据、队列操作、后台操作等验证用到
 * @token Token数值
 * return bool
 */
function check_token($token) {
    if ($token != md5(date('Y-m-d H', time()) . 'ChaoMi-Token'))
        return json_s(array('status' => 201, 'msg' => 'Token Is Error')); //每小时变一次
}

/**
 * 检测数值是否为空，返回0
 * @value 数值
 * return 
 */
function null_num($value) {
    if (empty($value))
        return 0;
    if (is_int($value))
        return (int) $value;
    if (is_float($value))
        return (float) $value;
    return $value;
}

/**
 * 检测品种ID是否存在
 * @id 品种ID
 * return array
 */
function check_pz($id) {
  
    $key = 'check_pz_data_040812_id_' . $id;
    $data = cache_s($key);
    if ($data)
        return $data;
    $id = intval($id);
    $gb = new new_ym_code();
    $types = $gb->query("select * from cmpai.new_ym_code where code=$id");
    if ($types)
        $types[0]['name'] = $types[0]['name'] . '(旧)';
    if (!$types) {
        $gb2 = new new_ym_code_twos();
        $types = $gb2->query("select * from cmpai.new_ym_code_twos where two_code=$id");
        if (!$types)
            return array();
        $code = $types[0]['code'];
        $two_name = $types[0]['name'];
        $a =$gb->query("select * from cmpai.new_ym_code where code=$code");
        // $types[0]['name'] = "(".$two_name.")".$a[0]['name']."二级域名";
        $types[0]['name'] = $two_name;
    }
    cache_s($key, $types, 300);
    return $types;
}

/**
 * 列表当前有效的品种
 */
function get_type_list() {
    //列出品种typeid列表
    $data = spClass("new_ym_code_twos")->findAll("state=1", "order_id asc", 'two_code');
    $_data = array();
    foreach ($data as $v) {
        $_data[] = $v['two_code'];
    }
    //$_data[] = 411104;
    //$_data[] = 411109;
    return $_data;
}

/**
 * 输出json
 * @array 数组
 */
function json_s($array) {
    if (!is_array($array))
        return false;
    //-----删除操作锁-----begin
    if(isset($array['del_cache_a'])){
    if ($array['del_cache_a'] != '') {
        //-----事务回滚出错-----begin
        if ($array['status'] == 205)
            write_log_shiwu($array['msg'] . '***' . $array['del_cache_a']);
        //-----事务回滚出错-----end
        cache_a($array['del_cache_a'], null);
        unset($array['del_cache_a']);
    }
    }
    //-----删除操作锁-----end

    header('Content-type:text/json');
    echo json_encode($array);
    exit();
    return;
}

/**
 * 数据原子性锁缓存 读 写 删
 * @cache_name 缓存名字
 * @cache_name 缓存数据
 * @cache_time 缓存时间
  echo cache_a('666',time(),10); 写
  echo cache_a('666'); 读
  echo cache_a('666',null); 删
 */
function cache_a($cache_name, $cache_value = '', $cache_time = 60) {
    return false;
    if (empty($cache_name))
        return false; //缓存名字及缓存数据两者不能为空
    $cache_name = 'lock_' . $cache_name; //前面加上lock_ 防止冲突
    if ('' === $cache_value) { // 获取缓存
        //如果有数据，即key存在返回key数据，无或空返回falue
        $ret = spAccess('r', $cache_name); //缓存数据为空时=读取缓存数据
        if (empty($ret) || $ret === false) {
            return false;
        }
        return $ret;
    } elseif (is_null($cache_value)) { // 删除缓存
        return spAccess('c', $cache_name); //缓存数据为null时=删除缓存
    } else { // 写缓存数据--写锁
        try {
            $ret = spAccess('a', $cache_name, $value = $cache_value, $life_time = $cache_time); //写入缓存
            return $ret;
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * sql事务回滚出错日志
 */
function write_log_shiwu($text) {
    try {
        $dir = 'js/log/';
        $file = $dir . '/' . date("Ymd", time()) . '_shiwu_error.txt';
        if (is_dir($dir)) {
            if (file_exists($file)) {
                
            } else {
                $f = fopen($file, 'w+');
                fclose($f);
            }
            $text = date("Y-m-d H:i:s", time()) . "： \t" . $text . "\r\n";
            file_put_contents($file, $text, FILE_APPEND);
        } else {
            if (mkdir($dir, 0777)) {
                if (file_exists($file)) {
                    
                } else {
                    $f = fopen($file, 'w+');
                    fclose($f);
                }
                $text = date("Y-m-d H:i:s", time()) . "： \t" . $text . "\r\n";
                file_put_contents($file, $text, FILE_APPEND);
            }
        }
    } catch (Exception $e) {
        
    }
}

/**
 * 数据缓存 读 写 删
 * @cache_name 缓存名字
 * @cache_name 缓存数据
 * @cache_time 缓存时间
  echo cache_s('666',time(),10); 写
  echo cache_s('666'); 读
  echo cache_s('666',null); 删
 */
function cache_s($cache_name, $cache_value = '', $cache_time = 60) {
    return false;
//    if (empty($cache_name))
//        return false; //缓存名字及缓存数据两者不能为空
//    if ('' === $cache_value) { // 获取缓存
//        return spAccess('r', $cache_name); //缓存数据为空时=读取缓存数据
//    } elseif (is_null($cache_value)) { // 删除缓存
//        return spAccess('c', $cache_name); //缓存数据为null时=删除缓存
//    } else { // 缓存数据
//        try {
//            $ret = spAccess('w', $cache_name, $value = $cache_value, $life_time = $cache_time); //写入缓存
//            return $ret;
//        } catch (Exception $e) {
//            return false;
//        }
//    }
}

/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv 是否进行高级模式获取（有可能被伪装） 
 * @return mixed
 */
function get_client_ip($type = 0, $adv = true) {
    $type = $type ? 1 : 0;
    static $ip = null;
    if ($ip !== null)
        return $ip[$type];
    if ($adv) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos = array_search('unknown', $arr);
            if (false !== $pos)
                unset($arr[$pos]);
            $ip = trim($arr[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u", ip2long($ip));
    $ip = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[$type];
}

/**
 * 随机产生六位数
 */
function get_randstr($len = 6, $format = 'ALL') {
    switch ($format) {
        case 'ALL':
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-@#~';
            break;
        case 'CHAR':
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-@#~';
            break;
        case 'NUMBER':
            $chars = '0123456789';
            break;
        default:
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-@#~';
            break;
    }
    mt_srand((double) microtime() * 1000000 * getmypid());
    $password = "";
    while (strlen($password) < $len)
        $password .= substr($chars, (mt_rand() % strlen($chars)), 1);
    return $password;
}

/**
 * function web_msg_send   添加站内日志
 * @param $tit     标题
 * @param $type  901 系统发信 902 站内通知
 * @param $uid      用户IP
 * @param $txt  信息内容
 * return  
 */
function web_msg_send($tit, $type, $uid, $txt) {
    //$type 901 系统发信 902 站内通知
    //$status 0未读，1=已读，2=回收站
    $gb_msg = new pub_web_msg();
    $gb_con = new pub_web_msg_content();
    if ($type == 901) {
        $rs = $gb_con->find('', 'content_id desc');
        if ($rs) {
            $content_id = $rs['content_id'] + 1;
        } else {
            $content_id = 100000;
        }
        $rows_msg = array("types" => $type, "title" => $tit, "to_uid" => $uid, "to_time" => time(), "to_time_year" => date('Y'), "is_status" => 0, "content_id" => $content_id);
        $rows_con = array("content_id" => $content_id, "content" => $txt);
        $gb_msg->create($rows_msg);
        $gb_con->create($rows_con);
    } elseif ($type == 902) {
        //待处理---
        $sql = "select max(content_id) from share_user.pub_web_msg_content where content_id < 100000";
        $rs = $gb_con->findSql($sql);
        if ($rs) {
            $content_id = $rs['content_id'] + 1;
        } else {
            $content_id = 1;
        }
        $gb_user = new pub_user();
        $rows_msg = $gb_user->findAll(null, null, " uid as to_uid ");
        foreach ($rows_msg as $key) {
            $rows_msg["types"] = $type;
            $rows_msg["title"] = $tit;
            $rows_msg["to_time"] = time();
            $rows_msg["to_time_year"] = date('Y');
            $rows_msg["is_status"] = 0;
            $rows_msg["content_id"] = $content_id;
        }
        $gb_msg->create($rows_msg);
        $rows_con = array("content_id" => $content_id, "content" => $txt);
        $gb_con->create($rows_con);
    }
}

/**
 * function user_log   操作日志
 * @param $uid     用户id
 * @param $atypes  操作编号  //601委托买，2委托卖，3买成交，4卖成交,5买家资金变动，6卖家资金变动,7平台资金变化,8买家域名数目变化，9卖家域名数目变化
 * @param $ip      IP地址
 * @param $action  行为操作
 * return  void
 */
function user_log($uid, $atype, $ip, $action) {
    $atime = time();
    $y = date("Y", $atime);
    $m = date("m", $atime);
    $d = date("d", $atime);
    $time = date("H:i:s", $atime);
    $sp = new pan_user_log();
    $row = array('uid' => $uid, 'atype' => $atype, 'action' => $action, 'atime' => $atime, 'ip' => $ip, 'pc_wap' => 1, 'y' => $y, 'm' => $m, 'd' => $d, 'time' => $time);
    return $sp->create($row);
}

/**
 * 操作写出文本日志
 */
function write($text) {
    //检查文件是否存在
    $dir = 'js/log/';
    $file = $dir . '/' . date("Ymd", time()) . '.txt';
    if (is_dir($dir)) {
        if (file_exists($file)) {
            
        } else {
            $f = fopen($file, 'w+');
            fclose($f);
        }
        $text = date("Y-m-d H:i:s", time()) . "： \t" . $text . "\r\n";
        file_put_contents($file, $text, FILE_APPEND);
    } else {
        if (mkdir($dir, 0777)) {
            if (file_exists($file)) {
                
            } else {
                $f = fopen($file, 'w+');
                fclose($f);
            }
            $text = date("Y-m-d H:i:s", time()) . "： \t" . $text . "\r\n";
            file_put_contents($file, $text, FILE_APPEND);
        }
    }
}
