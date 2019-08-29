<?php

/*
 * index 提交申请页面
 * check_apply  处理申请页面，预览弹出框
 * applyList 域名包审核状态预览
 *
 */

class check extends spController {

    function __construct() {
        parent::__construct();
        $sso_user = check();
        if ($sso_user == true) {
            $this->uid = $sso_user['uid'];
            $this->mid = $sso_user['mid'];
            check_code();
        } else {
            re_login();
            exit();
        }
    }

    function apply_bl() {
        $typeid = intval($this->spArgs('typeid')); //品种ID
        $pan_apply_bl = spClass('pan_apply_bl'); // 入盘比例要求
        $pan_apply_log = spClass('pan_apply_log'); // 按比例入盘记录
        $pan_domain_in = spClass('pan_domain_in'); // 域名实盘表
        $ret_bl = $pan_apply_bl->find(array('typeid' => $typeid));
        $date = date('Y-m-d');
        $uid = $this->uid;
        if ($ret_bl['account_count'] == 0) {
            json_s(array('status' => 201, 'msg' => '当前品种暂未开放提交申请转入'));
        }
        $domain_count = $pan_domain_in->findCount(array('typeid' => $typeid, 'uid' => $uid, 'locked' => 5)); //持有当前品种数量 证锁定中
        $ret_apply_log = $pan_apply_log->find(array('typeid' => $typeid, 'date' => $date, 'uid' => $uid)); // 当前品种+当前会员+当日 按比例入盘数据
        $today_ky_count = intval(($domain_count - $ret_apply_log['apply_count'] * $ret_bl['account_count']) / $ret_bl['account_count']); // 计算当日可转入域名的数量，(持有当前品种数量 - 当日已转入数量) / 入盘比例数量
        $today_count = intval($domain_count / $ret_bl['account_count']); // 当日总可用数量额度
        //-----检查是否在规定时间内------begin
        $w = date("w"); //星期几
        $h = date("H"); //小时
        $open = true;
        if ($w == 0 || $w == 6) {
            $open = false;
        } else {
            if ($h >= 16) {
                $open = false;
            }
        }
        // if($open==false){
        // json_s(array('status'=>201,'msg'=>'请在周一至周五16:00前提交转入申请'));
        // }
        //-----检查是否在规定时间内------end
        json_s(array('status' => 200, 'typeid' => $typeid, 'name' => $ret_bl['name'], 'domain_count' => (int) $domain_count, 'today_count' => $today_count, 'today_ky_count' => $today_ky_count, 'apply_count' => (int) $ret_bl['apply_count'], 'account_count' => (int) $ret_bl['account_count']));
    }

    function index() {
        $uid = $this->uid;
        //查询用户实名;
        $pan_member_card = spClass('pan_member_card');
        $r = $pan_member_card->find(array('uid' => $uid, 'status' => 2));
        if (!$r) {
            d301('http://my.chaomi.cc/authentic/name_AUT');
        }
        $_info = spClass('pan_member_info')->find(array('uid' => $uid));
        if (!$_info['qq'])
            d301('http://my.chaomi.cc/user/memberInfo?tip=n');
        $from = $this->spArgs('from');
        if ($from == 'twotype') {
            $typeid = $this->spArgs('typeid');
            // $two_type = spClass('new_ym_code_twos')->findAll(array('code' => $typeid,'state'=>1),"order_id asc");
            $two_type = spClass('new_ym_code_twos')->findAll(array('code' => $typeid, 'state' => 1, 'two_code' => 808001), "order_id asc");
            if ($typeid != 411104) {
                $two_type = spClass('new_ym_code_twos')->findAll(array('code' => $typeid, 'state' => 1), "order_id asc");
            }
            if ($uid == 19538) {
                $two_type = spClass('new_ym_code_twos')->findAll(array('code' => $typeid, 'state' => 1), "order_id asc");
            }
            // $two_type = spClass('new_ym_code_twos')->findAll("code=$typeid and state=1 and two_code in (808001,808008,808018)","order_id asc");
            json_s(array('status' => 200, 'two_type' => $two_type));
        }
        //取出当前用户的绑定的注册商平台
        $result = spClass('pan_member_registrar')->findAll(array('uid' => $uid));
        $registrar_ret = array();
        foreach ($result as $k => $v) {
            $registrar_ret[$v['id']] = getDomainSite($v['website']) . '(ID:' . $v['webid'] . ')';
        }
        $this->domain_web_options = $registrar_ret; //注册商ID列表
        //$this->type_id = $new_ym_code[0]['id'];
        //$this->next_time = date("Y-m-d",strtotime('+12 month'));
        //$this->uid=$this->uid;
        $this->module = "domainList";
        $this->act = 'applyList';
        // $this->display('member/domain/apply.html');
        $this->display('amui/member/am_apply.html');
    }

    //提交转入域名
    function check_apply() {
        // json_s(array('status'=>201,'msg'=>'入盘规则正在进行调整中，当前已暂停域名入盘。'));
        //----------框架自带，过滤防注入示例-----B
        // $name = $u->escape($this->spArgs('name'));
        // $name = trim($name,"'"); // trim掉前后的单引号
        // $condition = "name LIKE '%$name%'";
        //----------框架自带，过滤防注入示例-----E
        //获取前台传递数据
        $uid = $this->uid;
        $domains = htmlspecialchars($this->spArgs('domains'));
        $website_nid = intval($this->spArgs('website_nid')); //绑定的注册商平台和ID编号
        //---查出对应的注册商平台名称及ID --- begin
        $db_registrar = spClass('pan_member_registrar');
        $result = $db_registrar->find(array('id' => $website_nid, 'uid' => $this->uid));
        if ($result) {
            $pingtai = getDomainSite($result['website']) . '(ID:' . $result['webid'] . ')';
        } else {
            json_s(array('status' => 201, 'msg' => '请先绑定注册商平台及帐号'));
        }
        //---查出对应的注册商平台名称及ID --- end
        //$new_ym_code = spClass('new_ym_code');
        //$dtype = $new_ym_code->findBy('code', $typeid);
        //处理传递过来的域名
        $domains = urldecode($domains);
        $order = array("\r\n", "\n", "\r",);
        $domains = str_replace($order, '&', $domains);
        $domains = explode('&', $domains);

        //----------------------提交域名详情------------------------\\
        //---------------------------------------------------------\\
        $domains = array_flip($domains);

        $allnum = count($domains);
        if ($allnum > 200)
            json_s(array('status' => 201, 'msg' => '转入' . $allnum . '个域名，一次最多可提交200个域名，请分批提交'));

        //符合规则的域名
        $num = $allnum;
        $domain_in = array();
        //不符合规则的域名
        $domain_error = array();
        $domain_error_tit = '';
        //----------------------检测域名，类型，后缀，是否重复，不予提交----------------------\\
        //-----------------------------------------------------------\\
        //判断品种加品类的判断 $t_str品种字符 $t_flag 1开，2尾
        // $error = compare_domains_type($domains, $typeid, $t_str, $t_flag);
        // $num = $error['num'];
        // $domain_error = $error['domain_error'];
        //$domain_error_tit = $error['domain_error_tit'];
        // $domain_in = $error['domain_in'];
        //$domain_zip_name = $num . '个' . $dtype['name'];
        $domain_zip_name = $num . '个' . $uid;
        $domain_zip_time = date("Y-m-d H:i:s");
        if ($num == $allnum) {
            //----检测是否满足比按入盘的数量----begin
            // if($allnum>$today_ky_count)json_s(array('status'=>201,'msg'=>'提交转入域名数量有误，您当日可用入盘额度'.$today_ky_count.'个'));
            //----检测是否满足比按入盘的数量----end

            $cache_name = 'cm_apply_post_uid_' . $uid;
            // --------操作缓存处理-----begin
            if (false === cache_a($cache_name, time(), 10))
                json_s(array('status' => 205, 'msg' => '操作占用中，请稍等十秒'));

            //开始将数据添加入表
            $domain_name = $domain_zip_name; //N个+品种名字
            //$typeid = $dtype['code']; //品种ID
            $typeid = $two_typeid; //品类ID
            // $error = array('id' => 0, 'msg' => null);
            if (!empty($domains) && $domain_name != '') {
                $pan_domain_zip_sh = spClass('pan_domain_zip_sh');
                $domain_zip = array('domain_zip' => $domain_name, 'time' => date("Y-m-d H:i:s"), 'typeid' => 0, 'pingtai' => $pingtai, 'counts' => $allnum, 'audit_status' => 1, 'uid' => $this->uid);
                if ($sign = $pan_domain_zip_sh->create($domain_zip)) {
                    //域名入审核表
                    $pan_domain_sh = spClass('pan_domain_sh');
                    $domain_sh = array();

                    foreach ($domains as $k => $v) {
                        $domain_sh[] = array('uid' => $this->uid, 'domain_zip_id' => $sign, 'domain' => strtolower($k), 'domain_website' => $pingtai, 'typeid' => 0, 'audit_status' => 0);
                    }
                    $pan_domain_sh->createAll($domain_sh);
                    //----邮件后台提醒----begin
                    $content = array();
                    $content['to'] = array('pwpet@qq.com');
                    $content['sub'] = array('%content%' => array('用户MID：' . $this->mid . '，提交了域名入盘，域名数量' . $allnum . '个。'));
                    $new_content = json_encode($content);
                    send_mail('pwpet@qq.com', '【炒米后台提醒】有用户提交域名入盘！', $new_content, 8);
                    $content['to'] = array('605466504@qq.com');
                    $new_content = json_encode($content);
                    send_mail('605466504@qq.com', '【炒米后台提醒】有用户提交域名入盘！', $new_content, 8);
                    //----邮件后台提醒----end
                    cache_a($cache_name, null);
                    json_s(array('status' => 200, 'msg' => $domain_name . '已提交转入成功，待审核中'));
                } else {
                    cache_a($cache_name, null);
                    json_s(array('status' => 201, 'msg' => '数据处理出错，请重新提交'));
                }
            } else {
                cache_a($cache_name, null);
                json_s(array('status' => 201, 'msg' => '数据处理出错，请重新提交'));
            }
            echo json_encode($error);
        } else {
            foreach ($domain_error as $k => $v) {
                $domain_error_str .= $v . '<br/>';
            }
            json_s(array('status' => 202, 'msg' => "转入" . $allnum . "个" . $suffix . "域名，其中有" . ($allnum - $num) . "个域名不符合规则，请检查后再提交", 'error_tit' => $domain_error_tit));
        }
    }

    function check_apply_old() {
        // json_s(array('status'=>201,'msg'=>'入盘规则正在进行调整中，当前已暂停域名入盘。'));
        //----------框架自带，过滤防注入示例-----B
        // $name = $u->escape($this->spArgs('name'));
        // $name = trim($name,"'"); // trim掉前后的单引号
        // $condition = "name LIKE '%$name%'";
        //----------框架自带，过滤防注入示例-----E
        //获取前台传递数据
        $uid = $this->uid;
        $domains = htmlspecialchars($this->spArgs('domains'));
        $two_typeid = intval($this->spArgs('typeid')); //品类ID
        $website_nid = intval($this->spArgs('website_nid')); //绑定的注册商平台和ID编号
        //---查出对应的注册商平台名称及ID --- begin
        $db_registrar = spClass('pan_member_registrar');
        $result = $db_registrar->find(array('id' => $website_nid, 'uid' => $this->uid));
        if ($result) {
            $pingtai = getDomainSite($result['website']) . '(ID:' . $result['webid'] . ')';
        } else {
            json_s(array('status' => 201, 'msg' => '请先绑定注册商平台及帐号'));
        }
        //---查出对应的注册商平台名称及ID --- end
        $two_typeid = intval($this->spArgs('two_typeid')); //品类ID
        if ($uid != 19538) {
            // $tmp_data = array(808001,809001,810001,811001);
            $tmp_data = array(808001);
            if (!in_array($two_typeid, $tmp_data)) {
                json_s(array('status' => 201, 'msg' => '品种不符合'));
            }
        }
        $two_type = spClass('new_ym_code_twos');
        $tt = $two_type->findBy('two_code', $two_typeid);
        $t_str = $tt['str'];  //品种字符
        $t_flag = $tt['flag']; //1开，2尾
        $typeid = $tt['code']; //主品种ID
        $t_name = $tt['name']; //品类名称
        //$new_ym_code = spClass('new_ym_code');
        //$dtype = $new_ym_code->findBy('code', $typeid);
        //处理传递过来的域名
        $domains = urldecode($domains);
        $order = array("\r\n", "\n", "\r",);
        $domains = str_replace($order, '&', $domains);
        $domains = explode('&', $domains);

        //----------------------提交域名详情------------------------\\
        //---------------------------------------------------------\\
        $domains = array_filter($domains);
        $allnum = count($domains);
        if ($allnum > 200)
            json_s(array('status' => 201, 'msg' => '转入' . $allnum . '个域名，一次最多可提交200个域名，请分批提交'));

        //*************-------------------按比例入盘数据----------begin
        //-----检查是否在规定时间内------begin
        // $w = date("w"); //星期几
        // $h = date("H"); //小时
        // $open = true;
        // if($w==0 || $w==6 || $w==2 || $w==4){
        // $open = false;
        // }else{
        // if($h>=16){
        // $open = false;
        // }
        // }
        // if($open==false){
        // json_s(array('status'=>201,'msg'=>'请在周一至周五16:00前提交转入申请'));
        // }
        //-----检查是否在规定时间内------end
        // $pan_apply_bl = spClass('pan_apply_bl'); // 入盘比例要求
        // $pan_apply_log = spClass('pan_apply_log'); // 按比例入盘记录
        // $pan_domain_in = spClass('pan_domain_in'); // 域名实盘表
        // $ret_bl = $pan_apply_bl->find(array('typeid'=>$typeid));
        // $date = date('Y-m-d');
        // $uid = $this->uid;
        // if($ret_bl['account_count']==0){
        // json_s(array('status'=>201,'msg'=>'当前品种暂未开放提交申请转入'));
        // }
        // $domain_count = $pan_domain_in->findCount(array('typeid'=>$typeid,'uid'=>$uid,'locked'=>5)); //持有当前品种数量 证锁定中的总数
        // $ret_apply_log = $pan_apply_log->find(array('typeid'=>$typeid,'date'=>$date,'uid'=>$uid)); // 当前品种+当前会员+当日 按比例入盘数据
        // $today_ky_count = intval(($domain_count -  $ret_apply_log['apply_count'] * $ret_bl['account_count']) / $ret_bl['account_count']); // 计算当日可转入域名的数量，(持有当前品种数量 - 当日已转入数量) / 入盘比例数量
        // $today_count = intval($domain_count / $ret_bl['account_count']); // 当日总可用数量额度
        // if($today_count==0){
        // json_s(array('status'=>201,'msg'=>'您暂未满足'.$ret_bl['name'].'域名按比例入盘条件'));
        // }
        // if($today_ky_count==0){
        // json_s(array('status'=>201,'msg'=>'当日总额度'.$today_count.'个，当日剩余额度'.$today_ky_count.'个'));
        // }
        //*************-------------------按比例入盘数据----------end
        //符合规则的域名
        $num = 0;
        $domain_in = array();
        //不符合规则的域名
        $domain_error = array();
        $domain_error_tit = '';
        //----------------------检测域名，类型，后缀，是否重复，不予提交----------------------\\
        //-----------------------------------------------------------\\
        //判断品种加品类的判断 $t_str品种字符 $t_flag 1开，2尾
        $error = compare_domains_type($domains, $typeid, $t_str, $t_flag);
        $num = $error['num'];
        $domain_error = $error['domain_error'];
        $domain_error_tit = $error['domain_error_tit'];
        $domain_in = $error['domain_in'];
        //$domain_zip_name = $num . '个' . $dtype['name'];
        $domain_zip_name = $num . '个' . $t_name;
        $domain_zip_time = date("Y-m-d H:i:s");
        if ($num == $allnum) {
            //----检测是否满足比按入盘的数量----begin
            // if($allnum>$today_ky_count)json_s(array('status'=>201,'msg'=>'提交转入域名数量有误，您当日可用入盘额度'.$today_ky_count.'个'));
            //----检测是否满足比按入盘的数量----end

            $cache_name = 'cm_apply_post_uid_' . $uid;
            // --------操作缓存处理-----begin
            if (false === cache_a($cache_name, time(), 10))
                json_s(array('status' => 205, 'msg' => '操作占用中，请稍等十秒'));

            //开始将数据添加入表
            $domain_name = $domain_zip_name; //N个+品种名字
            //$typeid = $dtype['code']; //品种ID
            $typeid = $two_typeid; //品类ID
            // $error = array('id' => 0, 'msg' => null);
            if (!empty($domains) && $domain_name != '' && $typeid != '') {
                $pan_domain_zip_sh = spClass('pan_domain_zip_sh');
                $domain_zip = array('domain_zip' => $domain_name, 'time' => date("Y-m-d H:i:s"), 'typeid' => 0, 'pingtai' => $pingtai, 'counts' => $allnum, 'audit_status' => 1, 'uid' => $this->uid);
                if ($sign = $pan_domain_zip_sh->create($domain_zip)) {
                    //域名入审核表
                    $pan_domain_sh = spClass('pan_domain_sh');
                    $domain_sh = array();
                    foreach ($domains as $k => $v) {
                        $domain_sh[] = array('uid' => $this->uid, 'domain_zip_id' => $sign, 'domain' => strtolower($v), 'domain_website' => $pingtai, 'typeid' => 0, 'audit_status' => 0);
                    }
                    $pan_domain_sh->createAll($domain_sh);

                    //----按比例入库，添加入库的数量----begin
                    // $ret_apply_log = $pan_apply_log->find(array('typeid'=>$typeid,'date'=>$date,'uid'=>$uid)); // 当前品种+当前会员+当日 按比例入盘数据
                    // $new_apply_count = $ret_apply_log['apply_count'] + $allnum;
                    // $new_note = $ret_apply_log['note'].'['.date("Y-m-d H:i:s").'用户前台提交转入'.$allnum.'个域名，比例'.$ret_bl['apply_count'].':'.$ret_bl['account_count'].']';
                    // if($ret_apply_log){
                    // $pan_apply_log->update(array('typeid'=>$typeid,'date'=>$date,'uid'=>$uid),array('apply_count'=>$new_apply_count,'note'=>$new_note));
                    // }else{
                    // $pan_apply_log->create(array('typeid'=>$typeid,'name'=>$dtype['name'],'date'=>$date,'uid'=>$uid,'apply_count'=>$new_apply_count,'note'=>$new_note));
                    // }
                    //----按比例入库，添加入库的数量----end
                    //----邮件后台提醒----begin
                    $content = array();
                    $content['to'] = array('pwpet@qq.com');
                    $content['sub'] = array('%content%' => array('用户MID：' . $this->mid . '，提交了域名入盘，域名数量' . $allnum . '个。'));
                    $new_content = json_encode($content);
                    send_mail('pwpet@qq.com', '【炒米后台提醒】有用户提交域名入盘！', $new_content, 8);
                    $content['to'] = array('605466504@qq.com');
                    $new_content = json_encode($content);
                    send_mail('605466504@qq.com', '【炒米后台提醒】有用户提交域名入盘！', $new_content, 8);
                    //----邮件后台提醒----end
                    cache_a($cache_name, null);
                    json_s(array('status' => 200, 'msg' => $domain_name . '已提交转入成功，待审核中'));
                } else {
                    cache_a($cache_name, null);
                    json_s(array('status' => 201, 'msg' => '数据处理出错，请重新提交'));
                }
            } else {
                cache_a($cache_name, null);
                json_s(array('status' => 201, 'msg' => '数据处理出错，请重新提交'));
            }
            echo json_encode($error);
        } else {
            foreach ($domain_error as $k => $v) {
                $domain_error_str .= $v . '<br/>';
            }
            json_s(array('status' => 202, 'msg' => "转入" . $allnum . "个" . $suffix . "域名，其中有" . ($allnum - $num) . "个域名不符合规则，请检查后再提交", 'error_tit' => $domain_error_tit));
        }
    }

    /**
     * 用户转入列表
     */
    function applyList() {
        $page = intval($this->spArgs('page', 1));
        $status = $this->spArgs('status');
        if ($page <= 0)
            $page = 1;
        //排序方式
        $orderField = $this->spArgs('orderField', 'id ');
        $sort = " ORDER BY id desc";
        $conditions = " WHERE 1 = 1 ";
        if ($status)
            $conditions .= " and audit_status='" . (int) $status . "' ";
        $pan_domain_zip_sh = spClass('pan_domain_zip_sh');
        $conditions .= "and uid = " . $this->uid;
        $sql = "select * from cmpai.pan_domain_zip_sh " . $conditions . $sort;
        $domain_zip = $pan_domain_zip_sh->spPager($page, pgsize)->findSql($sql);
        //分页开始
        $pager = $pan_domain_zip_sh->spPager()->getPager();
        if ($pager['total_page'] > 5) {
            if ($page <= 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], $page - 3, 5);
            }
        }
        $this->pager = $pager;
        //分页结束
        $this->domain_zip = $domain_zip;
        $this->orderField = $orderField;
        $this->uid = $this->uid;
        $this->module = "domainList";
        $this->act = 'applyList';
        $this->status = $status;
        // $this->display('member/domain/applyList.html');
        $this->display('amui/member/am_applyList.html');
    }

    function applyId() { //具体某个ID下的域名包内容
        $id = intval($this->spArgs('id'));
        if (empty($id))
            d404();
        $pan_domain_zip_sh = spClass('pan_domain_zip_sh');
        $ret = $pan_domain_zip_sh->find(array('id' => $id));
        $ret_id = array();
        if ($ret && $ret['uid'] == $this->uid) { //验证是否存在该ID域名包及是否属于当前UID下
            $pan_domain_sh = spClass('pan_domain_sh');
            $ret_id = $pan_domain_sh->findAll(array('domain_zip_id' => $id));
        } else {
            d404();
        }
        $this->domain_ret = $ret;
        $this->domain_ret_id = $ret_id;
        $this->module = "domainList";
        $this->act = 'applyList';
        $this->display('amui/member/am_applyId.html');
    }

}

function compare_domains_type($domains, $typeid, $t_str = '', $t_flag = 0) {
    if ($typeid == 411104)
        $type = 4104; //通过品种ID定对应值 四声COM.CN
    if ($typeid == 411107)
        $type = 4107; //通过品种ID定对应值 四声NET.CN
    if ($typeid == 411109)
        $type = 4109; //通过品种ID定对应值 四声WANG
    if ($typeid == 411110)
        $type = 4110; //通过品种ID定对应值 四声TOP
    if ($typeid == 411112)
        $type = 4112; //通过品种ID定对应值 四声XIN
    if ($typeid == 411113)
        $type = 4113; //通过品种ID定对应值 四声VIP

    if ($typeid == 411101)
        $type = 4101; //通过品种ID定对应值 四声COM
    if ($typeid == 411102)
        $type = 4102; //通过品种ID定对应值 四声CN
    if ($typeid == 411103)
        $type = 4103; //通过品种ID定对应值 四声NET
    if ($typeid == 411106)
        $type = 4106; //通过品种ID定对应值 四声CC

    if ($typeid == 412102)
        $type = 4302; //通过品种ID定对应值
    if ($typeid == 614101)
        $type = 6201; //通过品种ID定对应值


    $type1 = substr($type, 0, 1);  //个数  ---第一位
    $type2 = substr($type, 1, 1);    //类型 ---第二位
    $type3 = (int) substr($type, 2);   //后缀 ---后二位
    //定义返回错误数组
    $error = array(0 => false, 'num' => 0, 'domain_in' => array(), 'domain_error' => array(), 'domain_error_tit' => '');
    $num = $len = 0;  //符合规则的域名数目
    $match = $match1 = '';   //匹配域名的规则
    $domain_type = array(
        1 => '[bcdfghjklmnpqrstwxyz]', //声母
        2 => '[12356789]', //数字不含0,4
        3 => '[aeiovu]', //字母
        4 => '[0-9]'   //数字
    );
    $len = $type1;
    if ($type2 == 3) {
        $match = '/[a-z]{' . $type1 . '}/i';
        $match1 = "/$domain_type[$type2]/i";
    } else {
        $match = '/' . $domain_type[$type2] . '{' . $type1 . '}/i';
    }
    $domain_suffix = array(
        1 => 'com',
        2 => 'cn',
        3 => 'net',
        6 => 'cc',
        4 => 'com.cn',
        7 => 'net.cn',
        9 => 'wang',
        10 => 'top',
        12 => 'xin',
        13 => 'vip'
    );
    $suffix = $domain_suffix[$type3];
    //-------------------------------------------------//
    //--------------先检测是否有重复的域名----------------//
    $count = count($domains);
    $new_domain = array_unique($domains);
    $count1 = count($new_domain);
    if ($count == $count1) {
        //-------检测域名的类型和后缀是否符合规则------------//
        foreach ($domains as $k => $v) {
            $v_type = substr($v, 0, strpos($v, '.'));
            $v_suffix = substr($v, (strpos($v, '.') + 1));
            preg_match($match, $v_type, $v_result);
            // var_dump($v_result);
            // var_dump($suffix);
            // var_dump($v_suffix);
            if ($v_result[0] && $suffix == $v_suffix && strlen($v_type) == $len) {
                if ($type2 == 3) {
                    if (preg_match($match1, $v_type, $v_result1)) {
                        $error['domain_in'][] = $v;
                        $num++; //echo $num;
                    } else {
                        $error[0] = 'true';
                        $error['domain_error'][] = $v;
                        $error['domain_error_tit'] .= $v . ',  ';
                    }
                } else {
                    //这里加入品类的判断
                    $t_len = strlen($t_str);
                    if ($t_flag == 1) { //头开
                        $t_r = substr($v_type, 0, $t_len);
                    } elseif ($t_flag == 2) { //结尾
                        $t_r = substr($v_type, $t_len * -1, $t_len);
                    } else { //普通
                        $t_r = "";
                    }
                    if ($t_str == $t_r) {
                        $error['domain_in'][] = $v;
                        $num++; //echo $num;
                    } else {
                        $error[0] = 'true';
                        $error['domain_error'][] = $v;
                        $error['domain_error_tit'] .= $v . ',  ';
                    }
                }
                //$error[0]='false';
                //return $error;
            } else {
                $error[0] = 'true';
                $error['domain_error'][] = $v;
                $error['domain_error_tit'] .= $v . ',  ';
            }
        }
        $error['num'] = $num;
        $error['domain_error_tit'] .= '类型不符、品类不符、后缀不符或者长度不符';
        return $error;
    } else {
        $error[0] = 'true';
        $error['domain_error_tit'] = "有重复的域名，请去掉重复的域名<br/>";
        //$error['count']=$count;
        $error['num'] = $count1;
        $err_same = array_count_values($domains);
        foreach ($err_same as $k => $v) {
            if ($v >= 2) {
                $error['domain_error'][] = $k;
                $error['domain_error_tit'] .= $k . "出现了" . $v . "次<br/>";
            } else {
                $error['domain_in'][] = $k;
            }
        }
        return $error;
    }
}

?>