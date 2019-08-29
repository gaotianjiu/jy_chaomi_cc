<?php

define("web_md5", "_chaomi_cc");

class trading extends spController {

    function __construct() {
        parent::__construct();
//---特定操作方法不用检测是否登录---begin
        global $__controller;
        global $__action;
        $this->bRate = (bRate * 100) . '%';
        $this->sRate = (sRate * 100) . '%';
        if ($__action == 'wt_data') {
            $sso_user = check();
            $this->uid = $sso_user['uid'];
            $this->mid = $sso_user['mid'];
            return;
        }
//---特定操作方法不用检测是否登录---end
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

//域名详细列表 -- 显示个数版
    function domainList_park() {
        $uid = $this->uid;
// if($uid==1)$uid=19637; //测试用
//查询条件
        $pan_domain_in = spClass('pan_domain_in');
        $new_ym_code = spClass('new_ym_code');
//域名状态**** -1全部 0正常 1锁定 2出售中 3待续费 4转出中 5资格证锁定 6后台锁定 7证转出 9停放锁定
        $DomainCount = $pan_domain_in->findCount(array('uid' => $uid)); //当前UID全部域名总数
        $condition = "select typeid,count(typeid) as num from cmpai.pan_domain_in where uid=$uid group by typeid order by num desc LIMIT 0,30;";
        $ret = $pan_domain_in->findSql($condition);
        $ret_l = array();
        foreach ($ret as $k => $v) {
            $tmp = $new_ym_code->find(array('code' => $v['typeid']));
            $v['name'] = $tmp['name'];
            $v['locked_0_num'] = $pan_domain_in->findCount(array('uid' => $uid, 'typeid' => $v['typeid'], 'locked' => 0)); //正常数量
            $v['locked_9_num'] = $pan_domain_in->findCount(array('uid' => $uid, 'typeid' => $v['typeid'], 'locked' => 9)); //停放锁定
            $v['renew_status'] = 1;
            if ($v['typeid'] == 411104)
                $v['renew_status'] = 0;
            $ret_l[] = $v;
        }
        $this->DomainCount = $DomainCount ? $DomainCount : 0;
        $this->ret = $ret_l;
        $this->module = "trading";
        $this->act = "domainList";
        $this->display('amui/member/am_domainList_n.html');
    }

//域名详细列表
    function domainList() {
        $uid = $this->uid;
        $page = intval($this->spArgs('page', 1));
        if ($page < 1)
            $page = 1;

//排序方式
        $order = (int) $this->spArgs('order', 0);
        $orderField = "a.id desc";
        if ($order == 1) { //品种升序
            $orderField = "a.typeid asc";
        } elseif ($order == 2) { //品种降序
            $orderField = "a.typeid desc";
        } elseif ($order == 3) {  //到期时间升序
            $orderField = "a.expire_time asc";
        } elseif ($order == 4) {  //到期时间降序
            $orderField = "a.expire_time desc";
        } elseif ($order == 5) {  //状态升序
            $orderField = "a.locked asc";
        } elseif ($order == 6) {  //状态降序
            $orderField = "a.locked desc";
        }
        $this->order = $order;
        $sort = " ORDER BY $orderField ";
//排序结束
//查询条件
        $pan_domain_in = spClass('pan_domain_in');
        $condition = " where 1=1 and a.uid=$uid and a.typeid>=300000";
        $cond = array('domain' => "", 'pz' => '', 'expire' => '', 'registrar' => '', 'status' => -1);

        if ($this->spArgs('txt') == 'all') {
            $list = $pan_domain_in->findAll(array('uid' => $uid));
            foreach ($list as $r) {
                echo $r['domain'] . '</br>';
            }
            exit;
        }
//域名**********模糊查询************
        if (false != $this->spArgs('domain')) {
            $domain = $pan_domain_in->escape($this->spArgs('domain'));
            $domain = trim($domain, "'");
            $domain_fix = $this->spArgs('domain_fix');
            if (empty($domain_fix)) {
                $condition .= " and a.domain like '%$domain%' ";
            } else {
                switch (implode('', $domain_fix)) {
                    case '1,2':
                        $condition .= " and (a.domain like '$domain%' or a.domain like '$domain%' )";
                        break;
                    case '1':
                        $condition .= " and a.domain like '$domain%' ";
                        break;
                    case '2':
                        $condition .= " and a.domain like '%$domain' ";
                        break;
                }
            }
            $cond['domain'] = $domain;
            $cond['domain_fix'] = $domain_fix;
        }
        if (false != $this->spArgs('paichu_key')) {
            $paichu_key = $this->spArgs('paichu_key');
            $paichu_key_fix = $this->spArgs('paichu_key_fix');
            if (empty($paichu_key_fix)) {
                $condition .= " and a.domain not like '%$paichu_key%' ";
            } else {
                switch (implode('', $paichu_key_fix)) {
                    case '1,2':
                        $condition .= " and (a.domain not like '$paichu_key%' and  a.domain not like '$paichu_key%' )";
                        break;
                    case '1':
                        $condition .= " and a.domain not  like '$paichu_key%' ";
                        break;
                    case '2':
                        $condition .= " and a.domain not like '%$paichu_key' ";
                        break;
                }
            }
            $cond['paichu_key'] = $paichu_key;
            $cond['paichu_key_fix'] = $paichu_key_fix;
        }
//域名品种
        /*  由是tag替代了。
          if (false != $this->spArgs('pz')) {
          $pz = intval($this->spArgs('pz'));
          $condition .= " and a.typeid=" . $pz . " ";
          $cond['pz'] = trim($pz, "'");
          }

         */
//域名过期时间
        if (false != $this->spArgs('zhuce_start')) {
            $zhuce_start = $this->spArgs('zhuce_start');
            $condition .= " and a.expire_time >= '" . $zhuce_start . "' ";
            $cond['zhuce_start'] = $zhuce_start;
        }


        if (false != $this->spArgs('expri_end')) {
            $expri_end = $this->spArgs('expri_end');
            $condition .= " and a.expire_time <= '" . $expri_end . "' ";
            $cond['expri_end'] = $expri_end;
        }

//域名注册时间
        if (false != $this->spArgs('zhuce_start')) {
            $zhuce_start = $this->spArgs('zhuce_start');
            $condition .= " and a.register_time >= '" . $zhuce_start . "' ";
        }
        $cond['zhuce_start'] = $zhuce_start;

        if (false != $this->spArgs('zhuce_end')) {
            $zhuce_end = $this->spArgs('zhuce_end');
            $condition .= " and a.register_time <= '" . $zhuce_end . "' ";
        }
        $cond['zhuce_end'] = $zhuce_end;
//域名注册商
        if (false != $this->spArgs('registrar')) {
            $registrar = intval($this->spArgs('registrar'));
// $websites = array('1'=>'易名中国','2'=>'爱名网','3'=>'190数交所','4'=>'万网','5'=>'西部数码','6'=>'易域网','7'=>'优名网');
            $websites = array('1' => '易名中国', '2' => '爱名网', '3' => '190数交所', '4' => '万网', '5' => '西部数码');
            $pt = $websites[$registrar];
            if ($pt)
                $condition .= " and a.pingtai = '" . $pt . "' ";
            $cond['registrar'] = $registrar;
        }
//域名状态**** -1全部 0正常 1锁定 2出售中 3待续费 4转出中 5资格证锁定 6后台锁定 7证转出
        $status = (int) $this->spArgs('status', -1);
        if ($status >= 0) {
            $status = $this->spArgs('status', 0);
            $condition .= " and a.locked = " . $status . " ";
            $cond['status'] = $status;
        } else {
            $condition .= " and a.locked <= 15 ";
        }
        //域名长度范围
        if (false != $this->spArgs('domain_len1')) {
            $domain_len1 = intval($this->spArgs('domain_len1', 0));
            if ($domain_len1 > 0) {
                $condition .= " and INSTR(domain, '.')>=$domain_len1";
            }
            $cond['domain_len1'] = $domain_len1;
        }

        if (false != $this->spArgs('domain_len2')) {
            $domain_len2 = intval($this->spArgs('domain_len2', 0));

            if ($domain_len2 >= $domain_len1) {
                $condition .= "  and INSTR(domain, '.')<=$domain_len2 ";
            }

            $cond['domain_len2'] = $domain_len2;
        }
        //自定义分组
        $team_select = (int) $this->spArgs('team_select', 0);
        if ($team_select > 0) {
            $condition .= "  and team_id= $team_select ";
        }
        $cond['team_select'] = $team_select;
        // 标签（即分类）
        $tag = $this->spArgs('tag');
        if (false !== $tag && !empty($tag)) {
            $tags = implode(',', $tag);
            $condition .= "  and a.id in (select did from pan_domain_in_tags where tid in( $tags))";
        }
        $cond['tag'] = $tag;
        //后缀
        $suffix = $this->spArgs('suffix');
        if (false != $suffix) {
            $condition .= "  and a.id in (select did from pan_domain_in_tags where suffix_id = $suffix)";
        }
        $cond['suffix'] = $suffix;

//传递到页面的查询条件
        $pagesize = $this->spArgs('pagesize');
        if ($pagesize == false) {
            $pagesize = 30;
        }
        $cond['pagesize'] = $pagesize;
        $this->cond = $cond;
        //echo $condition;
//查询结束
        $ssdf = "select a.*,c.name from cmpai.pan_domain_in a "
                . " left join cmpai.new_ym_code c on a.typeid=c.code "
                . $condition . $sort;
// $this->reggg = $pan_domain_in->spPager($page,pgsize)->findSql($ssdf);

        $reggg = $pan_domain_in->spPager($page, $pagesize)->findSql($ssdf);
        $ret = array();
        foreach ($reggg as $r) {
            if ($r['locked'] == 0)
                $r['is_ykj'] = 1;
            $_n = check_pz($r['typeid']);
            $r['name'] = $_n[0]['name'];
            if ($r['typeid'] < 20000)
                $r['is_twos'] = 1;
// if($r['locked']==9){
// $_r = spClass('pan_domain_ykj')->find(array('domain_id'=>$r['id'],'status'=>1));
// if(!$_r)$r['is_ykj'] = 1;
// }
            $ret[] = $r;
        }
        $this->reggg = $ret;
//分页参数
        $pager = $pan_domain_in->spPager()->getPager();
        if ($pager['total_page'] > 5) {
            if ($page > 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], $page - 3, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            }
        }
        $this->pager = $pager;
//分页结束
//----------------选择框---------------------\\
//-----------**品种**----------------------\\
// $dlist = "select code as id,name from cmpai.new_ym_code where state=1";
// $types = spClass('pan_domain_types')->findSql($dlist);
//列出品种typeid列表
        $twos_data = spClass('new_ym_code_twos')->findAll("state=1", "order_id asc", 'two_code,name');
        foreach ($twos_data as $v) {
            $types[] = array('id' => $v['two_code'], 'name' => $v['name']);
        }
//------------**注册商**-------------------\\
// $websites = array('1'=>'易名中国','2'=>'爱名网','3'=>'190数交所','4'=>'万网','5'=>'西部数码','6'=>'易域网','7'=>'优名网');
        $websites = array('1' => '易名中国', '2' => '爱名网', '3' => '190数交所', '4' => '万网', '5' => '西部数码');
//---------------用户分组---------------谢顺晚增加\\
        $lib_team_for_user = spClass("lib_team_for_user");
        $rs_team = $lib_team_for_user->findAll(array('uid' => $this->uid));
        $this->rs_team = $rs_team;
//-------------------用户分组结束----------------\\
//------------------取用户的有的域名后缀-----------谢顺晚增加\\
        $lib_ym_suffix = spClass("lib_ym_suffix");
        $suffix_sql = "select id,name from lib_ym_suffix where id in( select suffix_id  from pan_domain_in_tags where did in (select id from pan_domain_in where uid=$uid) ) ";
        $suffix_rec = $lib_ym_suffix->findSql($suffix_sql);
        $this->suffix_rec = $suffix_rec;
//-------------取用户户有点域名后缀结束------------------------\\
//-----------------取用户已有域名的标签（分类）----------------谢顺晚增加\\
        $lib_ym_tag = spClass('lib_ym_tag');
        $lib_ym_tag_sql = "select * from lib_ym_tag where id in(select tid from pan_domain_in_tags where did in (select id from pan_domain_in where uid=$uid))";
        $lib_ym_tag_rec = $lib_ym_tag->findSql($lib_ym_tag_sql);
        $this->lib_ym_tag_rec = $lib_ym_tag_rec;

//------------------取用户已有域名的标签结束-------------------\\

        $this->websites = $websites;
        $this->types = $types;

        $this->module = "domainList";
        $this->act = "domainList";
        // if($uid==1){
        // $this->display('amui/member/am_domainList_test.html');
        // exit;
        // }
        $this->display('amui/member/am_domainList.html');
    }

    //域名买入交易
    function trade_buy() {
        spClass('updating');
        $uid = $this->uid;
        if ($uid == 21836 || $uid == 21841)
            json_s(array('status' => 201, 'msg' => '后台限制此功能'));
        // if($uid!=1 && $uid!=19538)json_s(array('status'=>201,'msg'=>'系统调整中'));
        $front = $this->spArgs('front', 1);
        $number = intval($this->spArgs('number'));
        $price = trim($this->spArgs('price'));
        $price = bcadd($price, 0, 2); //强制转换成最多只保留两位小数点，防止精度误差
        $zhibao = intval($this->spArgs('zhibao')); //质保
        // $pingtai_id = $this->spArgs('pingtai');//平台
        // if(!$pingtai_id || empty($pingtai_id))json_s(array('status'=>201,'msg'=>'平台要求：请至少选择一个以上'));
        // $pingtai = '';
        // if(is_array($pingtai_id)){
        // foreach($pingtai_id as $v){
        // $pingtai.=(int)$v.',';
        // }
        // }else{
        // $pingtai = (int)$pingtai_id;
        // }
        // $pingtai = substr($pingtai, 0, -1); //去掉最后的,

        $zhibao_arr = array(0, 1, 3, 6, 12);
        if (!in_array($zhibao, $zhibao_arr))
            json_s(array('status' => 201, 'msg' => '到期要求选项参数出错'));

        // $pingtai_arr =array('3','2','1','1,2,3','1,2','1,3','2,3');
        // if(!in_array($pingtai,$pingtai_arr))json_s(array('status'=>201,'msg'=>'平台要求选项参数出错'));
        // if($price<1){
        // json_s(array('status'=>201,'msg'=>'买入单价错误，不能低于1元'));
        // }
        if ($number < 1) {
            json_s(array('status' => 201, 'msg' => '买入数量错误，不能低于1个'));
        }
        if ($number > 5000) {
            json_s(array('status' => 201, 'msg' => '单条委单买入数量不能超过5000个，请分批提交！'));
        }
        $pw = trim($this->spArgs('password'));
        if (empty($pw))
            json_s(array('status' => 201, 'msg' => '交易密码不能为空'));
        $pw = md5(md5($pw . web_md5)); //双重md5加密
        $typeid = intval($this->spArgs('typeid'));
        if ($price > 50 && $typeid < 800000)
            json_s(array('status' => 201, 'msg' => '【系统提醒】当前交易的域名为二级域名，当前价格可能已过高，请核对。'));
        // if($typeid==411102)json_s(array('status'=>201,'msg'=>'四声母CN品种暂未开放交易'));
        // if($typeid==411104 && time() < 1900955200)json_s(array('status'=>201,'msg'=>'暂未开放交易'));
        $wt = intval($this->spArgs('wt'));
        if (!($number && $number != "" && $price && $price != "" && $typeid && $typeid != "" && $pw && $pw != "")) {
            json_s(array('status' => 202, 'msg' => '非法操作'));
        }
        if ($number <= 0) {
            json_s(array('status' => 202, 'msg' => '无效操作'));
        }
        //密码获取代码
        $pws = spClass('pan_user_safecode')->find(array('uid' => $uid));

        //------------限制帐号请求验证安全码次数----------begin
        $key_safeCode_name = 'trade_safeCode_uid_' . $uid;
        if (cache_s($key_safeCode_name) > 20)
            json_s(array('status' => 205, 'msg' => '很抱歉，安全码验证请求次数限制，请稍后1小时后再操作'));
        //------------限制帐号请求验证安全码次数----------end
        //------------限制帐号操作交易次数----------begin
        $key_trade_count_name = 'trade_count_uid_' . $uid;
        if (cache_s($key_trade_count_name) > 300)
            json_s(array('status' => 205, 'msg' => '很抱歉，操作交易请求太频繁，请稍后1小时后再操作'));
        cache_s($key_trade_count_name, intval(cache_s($key_trade_count_name)) + 1, 3600); //操作交易缓存+1
        //------------限制帐号操作交易次数----------end
        //------------限制系统全部用户请求操作域名相关---判断后台是否在处理操作待续费、待下架----------begin
        $domain_action = 'domain_action';
        cache_a($domain_action, 'user', 5);
        if (cache_a($domain_action) == 'system')
            json_s(array('status' => 205, 'msg' => '很抱歉，系统繁忙，请稍后刷新重试。'));
        //------------限制系统全部用户请求操作域名相关---判断后台是否在处理操作待续费、待下架----------end

        if ($pw != $pws['safecode']) {
            cache_s($key_safeCode_name, intval(cache_s($key_safeCode_name)) + 1, 3600); //输入错误的安全码缓存+1
            json_s(array('status' => 201, 'msg' => '交易密码错误'));
        }
        cache_s($key_safeCode_name, 0, 3600); //输入正确的安全码重置为0
        //比较委托价格
        $sprice = typeid_price($typeid);
        $min = bcmul($sprice, 1); //金额乘法
        $max = bcmul($sprice, 100000); //金额乘法
        // if($price < $min || $price > $max){
        if (bccomp($price, $min) == -1) { //金额对比
            json_s(array('status' => 201, 'msg' => '买入价不符合规则，不能低于：' . $min . '元'));
        }
        if (bccomp($price, $max) == 1) { //金额对比
            json_s(array('status' => 201, 'msg' => '买入价不符合规则，不能高于：' . $max . '元'));
        }
        buy_domain($price, $number, $uid, $typeid, $wt, $front, $pingtai, $zhibao); //开始->买入流程
    }

    //域名卖出交易
    function trade_sale() {
        spClass('updating');
        $uid = $this->uid;
        if ($uid == 21836 || $uid == 21841)
            json_s(array('status' => 201, 'msg' => '后台限制此功能'));
        // if($this->uid!=1 && $uid!=19538)json_s(array('status'=>201,'msg'=>'系统调整中'));
        $front = $this->spArgs('front', 1);
        $number = intval($this->spArgs('number')); //必须正整数
        $price = trim($this->spArgs('price'));
        $price = bcadd($price, 0, 2); //强制转换成最多只保留两位小数点，防止精度误差
        $zhibao = intval($this->spArgs('zhibao')); //质保
        // $pingtai_id = (int)$this->spArgs('pingtai');//平台
        // if(!$pingtai_id || empty($pingtai_id))json_s(array('status'=>201,'msg'=>'所在平台：请至少选择一个以上'));
        // $pingtai = $pingtai_id;
        // if($pingtai>=4 || $pingtai==0)json_s(array('status'=>201,'msg'=>'所在平台选项参数出错'));
        $zhibao_arr = array(1, 3, 6, 12);
        //if(!in_array($zhibao,$zhibao_arr))json_s(array('status'=>201,'msg'=>'到期时间选项参数出错'));
        // if($price<1){
        // json_s(array('status'=>201,'msg'=>'卖出单价错误，不能低于1元'));
        // }
        if ($number < 1) {
            json_s(array('status' => 201, 'msg' => '卖出数量错误，不能低于1个'));
        }
        if ($number > 5000) {
            json_s(array('status' => 201, 'msg' => '单条委单卖出数量不能超过5000个，请分批提交！'));
        }
        $wt = intval($this->spArgs('wt'));
        $pw = $this->spArgs('password');
        if (empty($pw))
            json_s(array('status' => 201, 'msg' => '交易密码不能为空'));
        $pw = md5(md5($pw . web_md5)); //双重md5加密
        $typeid = intval($this->spArgs('typeid'));
        if ($price > 50 && $typeid < 800000)
            json_s(array('status' => 201, 'msg' => '【系统提醒】当前交易的域名为二级域名，当前价格可能已过高，请核对。'));
        // if($typeid==411102)json_s(array('status'=>201,'msg'=>'四声母CN品种暂未开放交易'));
        // if($typeid==411104 && time() < 1900955200)json_s(array('status'=>201,'msg'=>'暂未开放交易'));
        if (!($number && $number != "" && $price && $price != "" && $typeid && $typeid != "" && $pw && $pw != "")) {
            json_s(array('status' => 202, 'msg' => '非法操作'));
        }
        //密码获取代码
        $pws = spClass('pan_user_safecode')->find(array('uid' => $uid));

        //------------限制帐号请求验证安全码次数----------begin
        $key_safeCode_name = 'trade_safeCode_uid_' . $uid;
        if (cache_s($key_safeCode_name) > 20)
            json_s(array('status' => 205, 'msg' => '很抱歉，安全码验证请求次数限制，请稍后1小时后再操作'));
        //------------限制帐号请求验证安全码次数----------end
        //------------限制帐号操作交易次数----------begin
        $key_trade_count_name = 'trade_count_uid_' . $uid;
        if (cache_s($key_trade_count_name) > 300)
            json_s(array('status' => 205, 'msg' => '很抱歉，操作交易请求太频繁，请稍后1小时后再操作'));
        cache_s($key_trade_count_name, intval(cache_s($key_trade_count_name)) + 1, 3600); //操作交易缓存+1
        //------------限制帐号操作交易次数----------end
        //------------限制系统全部用户请求操作域名相关---判断后台是否在处理操作待续费、待下架----------begin
        $domain_action = 'domain_action';
        cache_a($domain_action, 'user', 5);
        if (cache_a($domain_action) == 'system')
            json_s(array('status' => 205, 'msg' => '很抱歉，系统繁忙，请稍后刷新重试。'));
        //------------限制系统全部用户请求操作域名相关---判断后台是否在处理操作待续费、待下架----------end

        if ($pw != $pws['safecode']) {
            cache_s($key_safeCode_name, intval(cache_s($key_safeCode_name)) + 1, 3600); //输入错误的安全码缓存+1
            json_s(array('status' => 201, 'msg' => '交易密码错误'));
        }
        cache_s($key_safeCode_name, 0, 3600); //输入正确的安全码重置为0
        //比较委托价格
        // $sprice = find_price(1,$typeid);
        $sprice = typeid_price($typeid);
        $min = bcmul($sprice, 1); //金额乘法
        $max = bcmul($sprice, 100000); //金额乘法
        // if($price < $min || $price > $max){
        if (bccomp($price, $min) == -1) { //金额对比
            json_s(array('status' => 201, 'msg' => '卖出价不符合规则，不能低于：' . $min . '元'));
        }
        if (bccomp($price, $max) == 1) { //金额对比
            json_s(array('status' => 201, 'msg' => '卖出价不符合规则，不能高于：' . $max . '元'));
        }
        sale_domain($price, $number, $uid, $typeid, $wt, $front, $pingtai, $zhibao); //开始->卖出流程
    }

    //委托订单列表
    function orderList() {
        $uid = $this->uid;
        $status = intval($this->spArgs('status'));
        $page = intval($this->spArgs('page'));
        if ($page <= 0)
            $page = 1;
        //注意******实际数据库表：status_2=1是买入 status_2=0是卖出
        //注意******前台调用：status=1是买入 status=2是卖出
        $find = '1=1';
        if (1 == $status)
            $find = 'status_2=1';
        if (2 == $status)
            $find = 'status_2=0'; //方便前台交互，将卖出的status定义为2
        if ($find == '1=1')
            $status = ''; //前台选项tabs判断用到
// $sql = "select a.name, b.* from cmpai.new_ym_code a, cmpai.pan_trade b where $find and a.code = b.typeid and b.uid = $uid order by b.status_1 asc, b.order_time desc";
        $sql = "select * from cmpai.pan_trade where $find and uid = $uid order by status_1 asc, order_time desc";
        $ret = spClass('pan_trade')->spPager($page, pgsize)->findSql($sql);
        $ret_ = array();
        foreach ($ret as $r) {
            $pt = $zb = '-';
            //处理平台
            if ($r['pingtai'] == '1') {
                $pt = '易名';
            } elseif ($r['pingtai'] == '2') {
                $pt = '爱名';
            } elseif ($r['pingtai'] == '3') {
                $pt = '阿里云';
            } elseif ($r['pingtai'] == '1,2') {
                $pt = '易名 爱名';
            } elseif ($r['pingtai'] == '1,3') {
                $pt = '易名 阿里云';
            } elseif ($r['pingtai'] == '2,3') {
                $pt = '爱名 阿里云';
            } else {
                $pt = '不限平台';
            }
            //处理质保时间
            if ($r['zhibao'] == 0) {
                $zb = '不限质保';
                if ($r['status_2'] == 0) {
                    $zb = '<1个月';
                }
            } else {
                $zhibao_tmp = $r['zhibao'];
                $zb = '≥' . $zhibao_tmp . '个月';
            }
            // if($r['order_time']>='2017-11-02')$r['pt_zb'] = $pt.' '.$zb;
            if ($r['order_time'] >= '2017-11-02')
                $r['pt_zb'] = $zb;
            $_n = check_pz($r['typeid']);
            $r['name'] = $_n[0]['name'];
            $ret_[] = $r;
        }
        //分页开始
        $pager = spClass('pan_trade')->spPager()->getPager();
        if ($pager['total_page'] > 5) {
            if ($page <= 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], $page - 3, 5);
            }
        }

        $this->pager = $pager;
        //分页结束
        $this->module = "trading";
        $this->act = "orderList";
        $this->ret = $ret_;
        $this->status = $status;
        $this->display('amui/member/am_trade_orderList.html');
    }

    //委托订单取消
    function cancel_order_buyer() {  //异步修改订单
        $uid = $this->uid;
        // if($uid!=1 && $uid!=19538)json_s(array('status'=>201,'msg'=>'系统繁忙，临时维护中'));
        $key_buy_name = 'trade_cancel_order_buyer_buy_uid_' . $uid;
        $key_sale_name = 'trade_cancel_order_buyer_sale_uid_' . $uid;
        $now = date("Y-m-d H:i:s", time());
        $id = intval($this->spArgs('id'));
        if (empty($id) || empty($uid)) {
            json_s(array('status' => 201, 'msg' => '参数缺失，请稍后重试'));
        }
        //A
        $y = date("Y", time());
        $m = date("m", time());
        $d = date("d", time());
        $note = "订单取消";
        $deal_time = date("H:i:s", time());
        $ip = get_client_ip();
        //A+

        $pan = spClass('pan_trade');
        //查询订单信息
        $id_sql = "select * from cmpai.pan_trade where id = $id and uid = " . $uid;
        $detail = $pan->findSql($id_sql);
        if (!$detail)
            json_s(array('status' => 201, 'msg' => '该委托订单不存在或不属于你的订单'));
        if ($detail[0]['status_1'] != 0)
            json_s(array('status' => 201, 'msg' => '当前委托订单状态不支持撤销，请刷新页面重试！'));
        $uid = $detail[0]['uid'];
        $freeN = $detail[0]['number'] - $detail[0]['deal_num']; //总数目-已成交数目
        // $total_price = $detail[0]['price'] * $freeN;
        $price = $detail[0]['price'];
        $total_price = bcmul($price, $freeN); //单价乘以剩下的数量=已冻结的金额
        $typeid = $detail[0]['typeid'];

        //------------限制系统全部用户请求操作域名相关---判断后台是否在处理操作待续费、待下架----------begin
        $domain_action = 'domain_action';
        cache_a($domain_action, 'user', 5);
        if (cache_a($domain_action) == 'system')
            json_s(array('status' => 205, 'msg' => '很抱歉，系统繁忙，请稍后刷新重试。'));
        //------------限制系统全部用户请求操作域名相关---判断后台是否在处理操作待续费、待下架----------end
        //委托买订单
        if ($detail[0]['status_2'] == 1) {
            //--------判断后台是否在操作此订单中---------begin
            $key_buy_id_name = 'trade_cancel_order_buyer_buy_id_' . $id; //当前订单操作买入取消缓存
            if (false === cache_a($key_buy_id_name, time(), 30))
                json_s(array('status' => 205, 'msg' => '很抱歉，该订单被系统队列占用中，请稍后重试'));
            //--------判断后台是否在操作此订单中---------end
            //------------限制用户并发请求操作域名相关----------begin
            $domain_action_uid = 'domain_action_uid_' . $uid;
            if (false === cache_a($domain_action_uid, time(), 10))
                json_s(array('status' => 205, 'msg' => '很抱歉，系统队列繁忙，请稍后刷新重试。'));
            //------------限制用户并发请求操作域名相关----------end

            if (false === cache_a($key_buy_name, time(), 30))
                json_s(array('status' => 205, 'msg' => '很抱歉，系统繁忙请稍后重试'));
            $sp = spClass('lib_member_account');
            $sql_sw = false;
            $sp->runSql("SET AUTOCOMMIT = 0");
            $sp->runSql('BEGIN'); //开启事务
            //解冻用户冻结金额
            //-----------先查询用户账户---------
            $bal_sql = "select balance, freeze_money from ykjhqcom.lib_member_account where uid = $uid FOR UPDATE"; //*****只要是查余额表的，必须前面开事务，并且后面加上 FOR UPDATE 单行锁表
            $res = $sp->findSql($bal_sql);
            //-----------处理用户余额，冻结金额----------
            // $freeze = $res[0]['freeze_money'] - $total_price;
            $freeze = bcsub($res[0]['freeze_money'], $total_price); //金额减法
            $upd_bal_sql = "update ykjhqcom.lib_member_account set freeze_money = $freeze where uid = $uid";
            $acc_ret = $sp->runSql($upd_bal_sql);
            $pan->findSql("select * from cmpai.pan_trade where id = $id FOR UPDATE"); //*****上锁单行ID订单表-----防止后台成交时并发抢
            //取消订单
            $order_sql = "update cmpai.pan_trade set status_1 = 2, cancel_time = '$now' where id = $id";
            $can_ret = $pan->runSql($order_sql);
            //操作日志
            user_log($uid, 610, $ip, '买家' . $uid . '委托买订单（' . $id . '）取消，解冻冻结资金' . $total_price . '元=单价' . $price . '*剩余数量' . $freeN . '，执行前查冻结总金额' . $res[0]['freeze_money'] . '元->执行此条时冻结总金额' . $freeze . '元');
            $sql_sw = true;
            if (false === $sql_sw) {
                $sp->runSql('ROLLBACK'); //回滚事务
                json_s(array('status' => 205, 'msg' => '系统事务出错，请稍候重试。', 'del_cache_a' => $key_buy_name));
            } else {
                $sp->runSql('COMMIT'); //执行事务
            }
            cache_a($key_buy_id_name, null); //删除正在操作订单缓存
            cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
            json_s(array('status' => 200, 'msg' => '[买入]委托订单撤销成功', 'del_cache_a' => $key_buy_name));
        }
        //委托卖订单
        else if ($detail[0]['status_2'] == 0) {
            //--------判断后台是否在操作此订单中---------begin
            $key_sale_id_name = 'trade_cancel_order_buyer_sale_id_' . $id; //当前订单操作卖出取消缓存
            if (false === cache_a($key_sale_id_name, time(), 30))
                json_s(array('status' => 205, 'msg' => '很抱歉，该订单被系统队列占用中，请稍后重试'));
            //--------判断后台是否在操作此订单中---------end
            if (false === cache_a($key_sale_name, time(), 30))
                json_s(array('status' => 205, 'msg' => '很抱歉，系统繁忙请稍后重试'));

            //------------限制用户并发请求操作域名相关----------begin
            $domain_action_uid = 'domain_action_uid_' . $uid;
            if (false === cache_a($domain_action_uid, time(), 10))
                json_s(array('status' => 205, 'msg' => '很抱歉，系统队列繁忙，请稍后刷新重试。'));
            //------------限制用户并发请求操作域名相关----------end

            $sp = spClass('pan_domain_in');
            $sql_sw = false;
            $sp->runSql("SET AUTOCOMMIT = 0");
            $sp->runSql('BEGIN'); //开启事务

            write("\r\n");
            write("\r\n");
            write('-----用户UID:' . $uid . '取消卖出委托订单（' . $id . '），执行开始!-----');

            //------最后验证是否成功，用到-----begin
            $pan_domain_in_locked_0_count_one = $sp->findCount(array('uid' => $uid, 'typeid' => $typeid, 'locked' => 0)); //未撤销卖出前，locked==0 状态正常域名的总数
            //------最后验证是否成功，用到-----end
            //取消订单 更新pan_trade表
            $pan_trade = spClass("pan_trade");
            $pan_trade->update(array('id' => $id, 'uid' => $uid), array('status_1' => 2, 'cancel_time' => $now));
            write('更新pan_trade表 status_1=2时，SQL语句：' . $pan_trade->dumpSql());
            $update_status_1_2 = $pan_trade->affectedRows();
            write('更新pan_trade表 status_1=2时，影响行数：' . $update_status_1_2);

            //查询pan_deal_domain 查询出卖出中剩余总数 及 域名列表 ID列表
            $pan_deal_domain = spClass("pan_deal_domain");
            $pan_count = $pan_deal_domain->findCount(array('uid' => $uid, 'tid' => $id, 'status' => 0)); //卖出中剩余总数
            write('卖出剩余域名个数：' . $pan_count . '个，pan_trade查到的剩余个数：' . $freeN . '个');
            if ($pan_count != $freeN) {
                cache_a($key_sale_id_name, null); //删除正在操作订单缓存
                cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
                json_s(array('status' => 201, 'msg' => '系统查询到域名与实际域名数量不相符，请稍候重试！', 'del_cache_a' => $key_sale_name));
            }
            $domain_ret = $pan_deal_domain->findAll(array('uid' => $uid, 'tid' => $id, 'status' => 0), '', 'id,uid,domain');
            write('查出具体域名列表时语句：' . $pan_deal_domain->dumpSql());

            //----域名数组 与 域名以,分割成字符串
            $ids = array();
            $domains = array();
            foreach ($domain_ret as $v) {
                $ids[] = $v['id'];
                $domains[] = "'" . $v['domain'] . "'";
            }
            $ids_str = implode(',', $ids);
            $domain_str = implode(',', $domains);

            if (count($ids) != count($domains) || count($ids) != $pan_count || count($ids) == 0 || $ids_str == '' || $domain_str == '') {
                cache_a($key_sale_id_name, null); //删除正在操作订单缓存
                cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
                json_s(array('status' => 201, 'msg' => '系统查询具体域名与实际数量不相符，请稍候重试！', 'del_cache_a' => $key_sale_name));
            }

            //修改pan_deal_domain
            $pan_deal_domain->update("uid = $uid and id in($ids_str)", array('status' => 2, 'cancel_time' => $now));
            write('更新pan_deal_domain表 status=2时，SQL语句：' . $pan_deal_domain->dumpSql());
            $update_deal_domain_2 = $pan_deal_domain->affectedRows();
            write('更新pan_deal_domain表 status=2时，影响行数：' . $update_deal_domain_2);

            if (count($ids) != $update_deal_domain_2) {
                cache_a($key_sale_id_name, null); //删除正在操作订单缓存
                cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
                json_s(array('status' => 201, 'msg' => '系统更新具体域名状态与实际域名数量不相符，请稍候重试！', 'del_cache_a' => $key_sale_name));
            }

            //修改pan_domain_in表对应的域名为正常状态
            $pan_domain_in = spClass('pan_domain_in');
            $pan_domain_in->update("uid = $uid and domain in ($domain_str)", array('locked' => 0));
            write('更新pan_domain_in表 locked=2时，SQL语句：' . $pan_domain_in->dumpSql());
            $update_locked_0 = $pan_domain_in->affectedRows();
            write('更新pan_domain_in表 locked=2时，影响行数：' . $update_locked_0);
            write('更新pan_domain_in表 locked=2时，影响域名：' . $domain_str);

            if ($update_deal_domain_2 != $update_locked_0) {
                cache_a($key_sale_id_name, null); //删除正在操作订单缓存
                cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
                json_s(array('status' => 201, 'msg' => '系统更新具体域名状态为正常时与实际域名数量不相符，请稍候重试！', 'del_cache_a' => $key_sale_name));
            }
            //操作日志
            user_log($uid, 611, $ip, '卖家' . $uid . '委托卖订单（' . $id . '）取消，解开锁定的出售' . $typeid . '域名剩余数目' . $freeN . '个，更新行数' . $update_locked_0 . '域名列表：' . $domain_str);

            //-----最次预检测，再次验证域名是否已经添加成功-----begin
            $pan_domain_in_locked_0_count_two = $sp->findCount(array('uid' => $uid, 'typeid' => $typeid, 'locked' => 0)); //假设撤销卖出后，locked==0 状态正常域名的总数
            $pan_domain_in_locked_0_count_new = $pan_domain_in_locked_0_count_two - $pan_domain_in_locked_0_count_one; //求出撤销卖出后，locked==0 状态正常域名的总数(撤销卖出后 - 撤销卖出前)
            write('***预检测，再次验证locked==0状态正常的域名总数，撤销卖出后' . $pan_domain_in_locked_0_count_two . '-撤销卖出前' . $pan_domain_in_locked_0_count_one . '=' . $pan_domain_in_locked_0_count_new);
            if ($pan_domain_in_locked_0_count_new != $update_deal_domain_2 || $pan_domain_in_locked_0_count_new < 0) {
                cache_a($key_sale_id_name, null); //删除正在操作订单缓存
                cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
                json_s(array('status' => 201, 'msg' => '撤销卖出后系统域名数量与实际域名数量不相符，请稍候重试！', 'del_cache_a' => $key_sale_name));
            }
            //-----最次预检测，再次验证域名是否已经添加成功-----end

            $sql_sw = true;
            if (false === $sql_sw) {
                $sp->runSql('ROLLBACK'); //回滚事务
                json_s(array('status' => 205, 'msg' => '系统事务出错，请稍候重试。', 'del_cache_a' => $key_sale_name));
            } else {
                $sp->runSql('COMMIT'); //执行事务
                write('-----用户UID:' . $uid . '取消卖出委托订单（' . $id . '），执行结束完成!-----');
            }
            cache_a($key_sale_id_name, null); //删除正在操作订单缓存
            cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
            json_s(array('status' => 200, 'msg' => '[卖出]委托订单撤销成功', 'del_cache_a' => $key_sale_name));
        }
        json_s(array('status' => 200, 'msg' => '当前委托订单状态不支持撤销，请刷新页面重试！'));
    }

    //订单查询开始
    function find_order() {
        $this->module = "trading";
        $this->act = "find_order";
        $uid = $this->uid;

        //排序
        $sort = " order by deal_time desc ";
        //传递的参数
        $status = (int) ($this->spArgs('status', -1));
        $typeid = intval($this->spArgs('type'));
        $pan = spClass('pan_deal_trade');
        $start_time = $pan->escape($this->spArgs('start_time', ''));
        $end_time = $pan->escape($this->spArgs('end_time', ''));
        $start_time = trim($start_time, "'");
        $end_time = trim($end_time, "'");

        $condition = " where b.uid = $uid ";
        //判断传递的参数
        if ($status == 0 || $status == 1 || $status == 2) {
            if ($status == 2)
                $status = 0;
            $condition .= "and b.sta = $status ";
        }
        if (FALSE != $typeid) {
            $condition .= " and b.typeid = $typeid ";
        }
        if (FALSE != $start_time) {
            $condition .= " and b.deal_time >= '$start_time'";
        }
        if (FALSE != $end_time) {
            $end_time_stamp = strtotime($end_time) + 24 * 3600 - 1;
            $end = date("Y-m-d H:i:s", $end_time_stamp);
            $condition .= " and b.deal_time <= '$end' ";
        }
        // $sql = "select b.*, c.price, c.number, c.order_time, a.name, c.pingtai, c.status_2, c.zhibao from cmpai.pan_deal_trade b "
        $sql = "select b.id, b.deal_num, b.deal_price, b.tid, b.sta, b.typeid, b.deal_time, c.price, c.number, c.order_time, a.name, c.pingtai, c.status_2, c.zhibao from cmpai.pan_deal_trade b "
                . " left join cmpai.pan_trade c on b.tid = c.id "
                . " left join cmpai.new_ym_code a on a.code = b.typeid "
                . $condition . $sort;
        // echo $sql;
        $reg = $pan->spPager($this->spArgs('page', 1), pgsize)->findSql($sql);
        // var_dump($reg);

        $ret = array();
        foreach ($reg as $r) {
            $pt = $zb = '-';
            //处理平台
            if ($r['pingtai'] == '1') {
                $pt = '易名';
            } elseif ($r['pingtai'] == '2') {
                $pt = '爱名';
            } elseif ($r['pingtai'] == '3') {
                $pt = '阿里云';
            } elseif ($r['pingtai'] == '1,2') {
                $pt = '易名 爱名';
            } elseif ($r['pingtai'] == '1,3') {
                $pt = '易名 阿里云';
            } elseif ($r['pingtai'] == '2,3') {
                $pt = '爱名 阿里云';
            } else {
                $pt = '不限平台';
            }
            //处理质保时间
            if ($r['zhibao'] == 0) {
                $zb = '不限质保';
                if ($r['status_2'] == 0) {
                    $zb = '<1个月';
                }
            } else {
                $zhibao_tmp = $r['zhibao'];
                $zb = '≥' . $zhibao_tmp . '个月';
            }
            // if($r['order_time']>='2017-11-02')$r['pt_zb'] = $pt.' '.$zb;
            if ($r['order_time'] >= '2017-11-02')
                $r['pt_zb'] = $zb;
            $_n = check_pz($r['typeid']);
            $r['name'] = $_n[0]['name'];
            $ret[] = $r;
        }

        //分页
        $pager = $pan->spPager()->getPager();
        $Rpage = $this->spArgs('page');
        if ($pager['total_page'] > 5) {
            if ($Rpage <= 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], $Rpage - 3, 5);
            }
        }

        $dlist = "select two_code as id, name from cmpai.new_ym_code_twos where state = 1 order by `order_id` asc";
        $types = spClass('pan_domain_types')->findSql($dlist);
        //$types[] = array('id'=>411104,'name'=>'四声母COM.CN一级域名');
        //$types[] = array('id'=>411109,'name'=>'四声母WANG一级域名');

        $this->types = $types;
        $this->status = $status == 0 ? 2 : $status;
        $this->type = $typeid;

        $this->start_time = $start_time;
        $this->end_time = $end_time;
        $this->reg = $ret;
        $this->pager = $pager;
        $this->display('amui/member/am_trade_dealList.html');
    }

    //订单查询结束
    //----------域名委托购买页用到的数据----------begin
    function wt_data() {
        $uid = intval($this->uid);
        $typeid = intval($this->spArgs('typeid'));
        $pingtai = intval($this->spArgs('pingtai'));
        $zhibao = (int) $this->spArgs('zhibao');
        $recom_price = 0; //指导价格
        $account = 0; //余额
        $sale_num = 0; //可卖数量
        //-----typeid查品种名字
        $types = check_pz($typeid);
        $pz_name = $types[0]['name']; //品种名字

        $sRate = $this->sRate;
        $bRate = $this->bRate;
        // if($typeid==411104 || $typeid==411101 || $typeid==411102 || $typeid==411109)$sRate = '1%';
        //20171101新增----按平台+到期时间取出域名数量
        //-----帐号可卖域名数量
        $from = trim($this->spArgs('from'));
        if ($from == 'number') {
            if ($typeid) {
                $expire_time = date("Y-m-d", strtotime("+$zhibao month"));
                if ($zhibao == 0) {
                    //判断质保时间，0=小于一个月
                    $expire_time = date("Y-m-d", strtotime("+1 month"));
                    $cond_expire_time_sql = "expire_time < '{$expire_time}'";
                } else {
                    $cond_expire_time_sql = "expire_time >= '{$expire_time}'";
                    if ($zhibao == 1) {
                        // $cond_expire_time_sql = "expire_time >= '".date("Y-m-d",strtotime("+1 month"))."' and expire_time < '".date("Y-m-d",strtotime("+3 month"))."'";
                        $cond_expire_time_sql = "expire_time >= '" . date("Y-m-d", strtotime("+1 month")) . "'";
                    }
                    if ($zhibao == 3) {
                        $cond_expire_time_sql = "expire_time >= '" . date("Y-m-d", strtotime("+3 month")) . "'";
                    }
                    if ($zhibao == 6) {
                        $cond_expire_time_sql = "expire_time >= '" . date("Y-m-d", strtotime("+6 month")) . "'";
                    }
                    if ($zhibao == 9) {
                        $cond_expire_time_sql = "expire_time >= '" . date("Y-m-d", strtotime("+9 month")) . "'";
                    }
                    if ($zhibao == 12) {
                        $cond_expire_time_sql = "expire_time >= '" . date("Y-m-d", strtotime("+12 month")) . "'";
                    }
                }
                if ($zhibao < 0) {
                    json_s(array('status' => 200, 'name' => $pz_name ? $pz_name : '', 'sale_num' => 0));
                }
                //处理平台
                if ($pingtai == 1) {
                    $cond_pingtai_sql = "pingtai = '易名中国'";
                } elseif ($pingtai == 2) {
                    $cond_pingtai_sql = "pingtai = '爱名网'";
                } elseif ($pingtai == 3) {
                    $cond_pingtai_sql = "pingtai = '万网'";
                } else {
                    $cond_pingtai_sql = "pingtai = ''";
                }

                $expire_time = date("Y-m-d", strtotime('+6 month'));
                // $sql = "select count(*) from cmpai.pan_domain_in where locked = 0 and uid = $uid and typeid = $typeid and $cond_expire_time_sql and $cond_pingtai_sql";
                $sql = "select count(*) from cmpai.pan_domain_in where locked = 0 and uid = $uid and typeid = $typeid and $cond_expire_time_sql";
                $res = spClass('pan_domain_in')->findSql($sql);
                $sale_num = round($res[0]['count(*)']);
                json_s(array('status' => 200, 'name' => $pz_name ? $pz_name : '', 'sale_num' => $sale_num));
            }
        }

        //-----品种指定价格
        try {
            $today = date("Y-m-d");
            $sql = "select price from cmpai.new_price where typeid = $typeid order by id desc limit 1";
            $res = spClass('pan_trade')->findSql($sql);
            $recom_price = round($res[0]['price']);
        } catch (Exception $e) {
            //echo "message:" . $e->getMessage();
        }
        if (empty($uid)) { //如果不是登录会员的情况下,直接返回指导价即可
            json_s(array('status' => 200, 'is_user' => 0, 'name' => $pz_name ? $pz_name : '', 'recom_price' => $recom_price ? $recom_price : 0, 'account' => 0, 'sale_num' => 0, 'sRate' => $sRate, 'bRate' => $bRate, 'orderlist' => ''));
        }
        //-----我的委托订单列表
        $find = '';
        if ($typeid)
            $find = "and typeid = $typeid";
        // $sql = "select a.name, b.* from cmpai.new_ym_code a, cmpai.pan_trade b where a.code = b.typeid and b.uid = $uid and b.status_1 = 0 $find order by b.bargain_time, b.order_time desc limit 50";
        $sql = "select * from cmpai.pan_trade where uid = $uid and status_1 = 0 $find order by bargain_time, order_time desc limit 50";
        $ret = array();
        $order_list = array();
        $ret = spClass('pan_trade')->findSql($sql);
        foreach ($ret as $r) {
            unset($r['status_1']);
            unset($r['ip']);
            unset($r['cancel_time']);
            if (!$r['bargain_time'])
                $r['bargain_time'] = '';
            unset($r['expire_time']);
            unset($r['domain_type_id']);
            unset($r['domain_suffix_id']);
            $pt = $zb = '-';
            //处理平台
            if ($r['pingtai'] == '1') {
                $pt = '易名';
            } elseif ($r['pingtai'] == '2') {
                $pt = '爱名';
            } elseif ($r['pingtai'] == '3') {
                $pt = '阿里云';
            } elseif ($r['pingtai'] == '1,2') {
                $pt = '易名 爱名';
            } elseif ($r['pingtai'] == '1,3') {
                $pt = '易名 阿里云';
            } elseif ($r['pingtai'] == '2,3') {
                $pt = '爱名 阿里云';
            } else {
                $pt = '不限平台';
            }
            //处理质保时间
            if ($r['zhibao'] == 0) {
                $zb = '不限质保';
                if ($r['status_2'] == 0) {
                    $zb = '<1个月';
                }
            } else {
                $zhibao_tmp = $r['zhibao'];
                $zb = '≥' . $zhibao_tmp . '个月';
            }
            // $r['pt_zb'] = $pt.' '.$zb;
            $r['pt_zb'] = $zb;
            if ($r['order_time'] < '2017-11-02')
                $r['pt_zb'] = '-';
            $_n = check_pz($r['typeid']);
            $r['name'] = $_n[0]['name'];
            $order_list[] = $r;
        }
        //-----我的历史委托订单列表
        $find = '';
        if ($typeid)
            $find = "and typeid = $typeid";
        // $sql = "select a.name, b.* from cmpai.new_ym_code a, cmpai.pan_trade b where a.code = b.typeid and b.uid = $uid and b.status_1>0 $find order by b.order_time desc limit 10";
        $sql = "select * from cmpai.pan_trade where uid = $uid and status_1>0 $find order by order_time desc limit 10";
        $ret = array();
        $history_order_list = array();
        $ret = spClass('pan_trade')->findSql($sql);
        foreach ($ret as $r) {
            unset($r['ip']);
            unset($r['cancel_time']);
            if (!$r['bargain_time'])
                $r['bargain_time'] = '';
            unset($r['expire_time']);
            unset($r['domain_type_id']);
            unset($r['domain_suffix_id']);
            $pt = $zb = '-';
            //处理平台
            if ($r['pingtai'] == '1') {
                $pt = '易名';
            } elseif ($r['pingtai'] == '2') {
                $pt = '爱名';
            } elseif ($r['pingtai'] == '3') {
                $pt = '阿里云';
            } elseif ($r['pingtai'] == '1,2') {
                $pt = '易名 爱名';
            } elseif ($r['pingtai'] == '1,3') {
                $pt = '易名 阿里云';
            } elseif ($r['pingtai'] == '2,3') {
                $pt = '爱名 阿里云';
            } else {
                $pt = '不限平台';
            }
            //处理质保时间
            if ($r['zhibao'] == 0) {
                $zb = '不限质保';
                if ($r['status_2'] == 0) {
                    $zb = '<1个月';
                }
            } else {
                $zhibao_tmp = $r['zhibao'];
                $zb = '≥' . $zhibao_tmp . '个月';
            }
            // $r['pt_zb'] = $pt.' '.$zb;
            $r['pt_zb'] = $zb;
            if ($r['order_time'] < '2017-11-02')
                $r['pt_zb'] = '-';
            $_n = check_pz($r['typeid']);
            $r['name'] = $_n[0]['name'];
            $history_order_list[] = $r;
        }
        //-----帐号可用余额
        try {
            $sp = spClass('lib_member_account');
            $ret = $sp->findSql("select balance, freeze_money from ykjhqcom.lib_member_account where uid = $uid");
            $account = round(($ret[0]['balance'] - $ret[0]['freeze_money']), 2); //用户账户可用余额
        } catch (Exception $e) {
            //echo "message:" . $e->getMessage();
        }
        //-----帐号可卖域名数量
        try {
            if ($typeid) {
                $_expire_time = date("Y-m-d", strtotime('+1 day'));
                $cond_expire_time_sql = "expire_time >= '" . $_expire_time . "'";
                // $cond_expire_time_sql = "expire_time >= '2019-05-01'";
                if ($typeid == 811001) { //四声WANG
                    $cond_expire_time_sql = "expire_time >= '2021-09-01'";
                }
                if ($typeid == 614101 || $typeid == 411103 || $typeid == 411104) {
                    $_expire_time = date("Y-m-d", strtotime('+1 year'));
                    $cond_expire_time_sql = "expire_time >= '" . $_expire_time . "'";
                }
                $sql = "select count(*) from cmpai.pan_domain_in where locked = 0 and uid = $uid and typeid = $typeid and $cond_expire_time_sql";
                $res = spClass('pan_domain_in')->findSql($sql);
                $sale_num = round($res[0]['count(*)']);
            }
        } catch (Exception $e) {
            //echo "message:" . $e->getMessage();
        }


        //-----------质保可卖数选项
        $zb = array();
        // $pan_domain_in = spClass('pan_domain_in');
        //判断质保时间，0=小于一个月
        // $expire_time = date("Y-m-d",strtotime("+1 month"));
        // $cond_expire_time_sql = "expire_time < '{$expire_time}'";
        // $zb['0'] = $pan_domain_in->findCount("uid = $uid and locked = 0 and typeid = $typeid and $cond_expire_time_sql");
        // $cond_expire_time_sql = "expire_time >= '".date("Y-m-d",strtotime("+1 month"))."' and expire_time < '".date("Y-m-d",strtotime("+3 month"))."'";
        // $zb['1'] = $pan_domain_in->findCount("uid = $uid and locked = 0 and typeid = $typeid and $cond_expire_time_sql");
        // $cond_expire_time_sql = "expire_time >= '".date("Y-m-d",strtotime("+3 month"))."' and expire_time < '".date("Y-m-d",strtotime("+6 month"))."'";
        // $zb['3'] = $pan_domain_in->findCount("uid = $uid and locked = 0 and typeid = $typeid and $cond_expire_time_sql");
        // $cond_expire_time_sql = "expire_time >= '".date("Y-m-d",strtotime("+6 month"))."' and expire_time < '".date("Y-m-d",strtotime("+9 month"))."'";
        // $zb['6'] = $pan_domain_in->findCount("uid = $uid and locked = 0 and typeid = $typeid and $cond_expire_time_sql");
        // $cond_expire_time_sql = "expire_time >= '".date("Y-m-d",strtotime("+12 month"))."'";
        // $zb['12'] = $pan_domain_in->findCount("uid = $uid and locked = 0 and typeid = $typeid and $cond_expire_time_sql");
        //----取出当前会员UID，域名成交消息推送-------begin
        // $pan_deal_trade = spClass('pan_deal_trade');
        // $pan_deal_trade_ret = $pan_deal_trade->find(array('is_push'=>0,'uid'=>$uid));
        // $push_msg = 0;
        // if($pan_deal_trade_ret){
        // $pan_deal_trade->update(array('id'=>$pan_deal_trade_ret['id']),array('is_push'=>1));
        // 2017-07-03 18:29:11<br/>以单价445元成功买入了3个四声母COM.CN域名
        // if($pan_deal_trade_ret['sta']==1)$sta_text = '买入';
        // if($pan_deal_trade_ret['sta']==0)$sta_text = '卖出';
        // $typeid_r = spClass('new_ym_code')->find(array('code'=>$pan_deal_trade_ret['typeid']));
        // $push_msg = $pan_deal_trade_ret['deal_time'].'<br />以单价'.$pan_deal_trade_ret['deal_price'].'元'.$sta_text.''.$pan_deal_trade_ret['deal_num'].'个'.$typeid_r['name'].'域名';
        // }
        //----取出当前会员UID，域名成交消息推送-------end
        json_s(array('status' => 200, 'is_user' => 1, 'name' => $pz_name ? $pz_name : '', 'zb' => $zb, 'recom_price' => $recom_price ? $recom_price : 0, 'account' => $account ? $account : 0, 'sale_num' => $sale_num ? $sale_num : 0, 'sRate' => $sRate, 'bRate' => $bRate, 'orderlist' => $order_list ? $order_list : '', 'history_orderlist' => $history_order_list ? $history_order_list : '', 'push_msg' => $push_msg));
    }

    //----------域名委托购买页用到的数据----------begin
}
