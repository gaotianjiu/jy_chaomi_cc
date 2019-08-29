<?php

/*
 * 域名一口价会员模块
 *
 */
define("web_md5", "_chaomi_cc");

class first extends spController {

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

    function apply() {

        $uid = $this->uid;
        $ip = get_client_ip();
        $now_time = time();
        $now_time_str = date("Y-m-d H:i:s");
        $new_ym_code = spClass('new_ym_code');
        $pan_parking = spClass('pan_parking');
        $pan_domain_ykj = spClass('pan_domain_ykj');
        $pan_domain_in = spClass('pan_domain_in'); // 域名实盘表
        $from = $this->spArgs('from');
        $id = $this->spArgs('id');
        $this->domain_count = 0;
        if ($from == 'check') {
            //在域名管理页面提交到待上架页面
            if (!$id || empty($id))
                json_s(array('status' => 201, 'msg' => '参数ID不能为空'));
            if (is_array($id)) {
                $count = count($id);
                //务必强制转换成数值类型---begin
                foreach ($id as $v) {
                    $new_arr[] = (int) $v;
                }
                //务必强制转换成数值类型---end
                $ids = implode(',', $new_arr);
            } else {
                $ids = (int) $id; //务必强制转换成数值
                $count = 1;
            }
            if (!$ids)
                json_s(array('status' => 201, 'msg' => '参数ID不能为空'));
            if ($count > 50)
                json_s(array('status' => 201, 'msg' => '每次最多可批量一口价50个域名'));

            $_count = $pan_domain_in->findCount("uid=$uid and id in($ids) and locked in(0,9)");
            if ($count != $_count)
                json_s(array('status' => 201, 'msg' => '只能批量发布域名状态正常或停放中的域名'));

            $_ret = $pan_domain_in->find("uid=$uid and id in($ids) and locked in(0,9)");
            $typeid = $_ret['typeid'];
            $locked = $_ret['locked'];
            $_count = $pan_domain_in->findCount("uid=$uid and id in($ids) and locked in(0,9) and typeid={$typeid}");
            if ($count != $_count)
                json_s(array('status' => 201, 'msg' => '每次批量发布的域名必须是相同的域名品种'));

            $_count = $pan_domain_in->findCount("uid=$uid and id in($ids) and locked={$locked}");
            if ($count != $_count)
                json_s(array('status' => 201, 'msg' => '状态正常与停放中的域名不能同一次批量发布'));
            if ($locked == 9) {
                $_count = $pan_domain_ykj->findCount("uid=$uid and domain_id in($ids) and status=1");
                if ($_count > 0)
                    json_s(array('status' => 201, 'msg' => '提示：有' . $_count . '个停放中的域名已在一口价出售中'));
            }
            json_s(array('status' => 200, 'msg' => 'success'));
        }
        if ($from == 'all') {
            //待上架域名页面
            if (!$id || empty($id))
                json_s(array('status' => 201, 'msg' => '参数ID不能为空'));
            if (is_array($id)) {
                $count = count($id);
                //务必强制转换成数值类型---begin
                foreach ($id as $v) {
                    $new_arr[] = (int) $v;
                }
                //务必强制转换成数值类型---end
                $ids = implode(',', $new_arr);
            } else {
                $ids = (int) $id; //务必强制转换成数值
                $count = 1;
            }
            $msg = "";
            if (!$ids)
                $msg = '参数ID不能为空<br/>';
            if ($count > 50)
                $msg = '每次最多可批量一口价50个域名<br/>';
            $this->msg = $msg;
            if ($msg == "") {
                $_ret = $pan_domain_in->findAll("uid=$uid and id in($ids) and locked in(0,9)");
                $ret = array();
                foreach ($_ret as $v) {
                    $r = $new_ym_code->spCache(3600)->find(array('code' => $v['typeid']));
                    $v['name'] = $r['name'];
                    $v['is_score'] = '无';
                    $v['is_locked'] = '-';
                    if ($v['locked'] == 0)
                        $v['is_locked'] = '正常';
                    if ($v['locked'] == 9) {
                        $v['is_locked'] = '停放中';
                        $r = $pan_parking->find(array('domain_id' => $v['id'], 'status' => 0));
                        if ($r)
                            $v['is_score'] = $r['income'] . '积分/天';
                    }
                    $ret[] = $v;
                }
                $this->domain_list = $ret;
                $this->domain_count = count($ret);
            }
        }
        if ($from == 'create') {
            // if($uid!=1)json_s(array('status'=>201,'msg'=>'一口价功能还在开发中'));
            if (!$id || empty($id))
                json_s(array('status' => 201, 'msg' => '参数ID不能为空'));
            if (is_array($id)) {
                $count = count($id);
                //务必强制转换成数值类型---begin
                foreach ($id as $v) {
                    $new_arr[] = (int) $v;
                }
                //务必强制转换成数值类型---end
                $ids = implode(',', $new_arr);
                $ids_arr = $new_arr;
            } else {
                $ids = (int) $id; //务必强制转换成数值
                $count = 1;
            }
            if (!$ids)
                json_s(array('status' => 201, 'msg' => '参数ID不能为空'));
            if ($count > 50)
                json_s(array('status' => 201, 'msg' => '每次最多可批量一口价50个域名'));

            $_count = $pan_domain_in->findCount("uid=$uid and id in($ids) and locked in(0,9)");
            if ($count != $_count)
                json_s(array('status' => 201, 'msg' => '只能批量发布域名状态正常或停放中的域名'));

            $_ret = $pan_domain_in->find("uid=$uid and id in($ids) and locked in(0,9)");
            $typeid = $_ret['typeid'];
            $locked = $_ret['locked'];
            $_count = $pan_domain_in->findCount("uid=$uid and id in($ids) and locked in(0,9) and typeid={$typeid}");
            if ($count != $_count)
                json_s(array('status' => 201, 'msg' => '每次批量发布的域名必须是相同的域名品种'));

            $_count = $pan_domain_in->findCount("uid=$uid and id in($ids) and locked={$locked}");
            if ($count != $_count)
                json_s(array('status' => 201, 'msg' => '状态正常与停放中的域名不能同一次批量发布'));

            if ($locked == 9) {
                $_count = $pan_domain_ykj->findCount("uid=$uid and domain_id in($ids) and status=1");
                if ($_count > 0)
                    json_s(array('status' => 201, 'msg' => '提示：有' . $_count . '个停放中的域名已在一口价出售中'));
            }

            $sale_price = $this->spArgs('sale_price');
            $sale_type = intval($this->spArgs('sale_type'));
            $sale_price = bcadd($sale_price, 0, 2); //强制转换成最多只保留两位小数点，防止精度误差
            $introduction = trim($this->spArgs('introduction'));
            $introduction = preg_replace("/<(.*?)>/", "", $introduction);
            $introduction = strip_tags($introduction);
            $sale_time = intval($this->spArgs('sale_time'));
            if ($sale_price <= 0) {
                json_s(array('status' => 201, 'msg' => '单价不能为空'));
            }
            if (!in_array($sale_type, array(1, 2))) {
                json_s(array('status' => 201, 'msg' => '请选择一口价交易方式'));
            }
            if ($sale_price > 99999999) {
                json_s(array('status' => 201, 'msg' => '单价不能大于99999999'));
            }
            if ($sale_time <= 0) {
                json_s(array('status' => 201, 'msg' => '结束时间不能为空'));
            }
            if ($sale_time > 60) {
                json_s(array('status' => 201, 'msg' => '结束时间不能大于60天'));
            }
            if (strlen($introduction) > 200) {
                json_s(array('status' => 201, 'msg' => '含义备注不能多于200个字符'));
            }
            //处理安全码
            $pw = trim($this->spArgs('safecode'));
            if (empty($pw))
                json_s(array('status' => 201, 'msg' => '交易密码不能为空'));
            $pw = md5(md5($pw . web_md5)); //双重md5加密
            $pws = spClass('pan_user_safecode')->find(array('uid' => $uid)); //密码获取代码
            //------------限制帐号请求验证安全码次数----------begin
            $key_safeCode_name = 'ykj_safeCode_uid_' . $uid;
            if (cache_s($key_safeCode_name) > 30)
                json_s(array('status' => 205, 'msg' => '很抱歉，交易密码验证请求次数限制，请稍后1小时后再操作'));
            //------------限制帐号请求验证安全码次数----------end
            if ($pw != $pws['safecode']) {
                cache_s($key_safeCode_name, intval(cache_s($key_safeCode_name)) + 1, 3600); //输入错误的安全码缓存+1
                json_s(array('status' => 201, 'msg' => '交易密码错误，请注意区分大小写'));
            }
            cache_s($key_safeCode_name, 0, 3600); //输入正确的安全码重置为0
            //处理图形验证码
            $validate = strtolower($this->spArgs('validate')); // 获得前端输入的验证码
            $validate_ = $_SESSION['validate'];
            if ($validate_ == '')
                json_s(array('status' => 209, 'msg' => '请点击重新获取图形验证码'));
            unset($_SESSION['validate']); //不管下面验证是否通过，都要删掉此变量***
            ///----验证码----end
            if ($validate_ != $validate)
                json_s(array('status' => 209, 'msg' => '验证码错误，请重新输入'));
            ///----验证码----end
            //------------限制用户并发请求操作域名相关----------begin
            $domain_action_uid = 'domain_action_uid_' . $uid;
            if (false === cache_a($domain_action_uid, time(), 10))
                json_s(array('status' => 205, 'msg' => '很抱歉，系统队列繁忙，请稍后刷新重试。'));
            //------------限制用户并发请求操作域名相关----------end

            $sp = spClass('pan_domain_ykj');
            $sql_sw = false;
            $sp->runSql("SET AUTOCOMMIT=0");
            $sp->runSql('BEGIN'); //开启事务

            $end_time = date("Y-m-d H:i:s", strtotime("+" . $sale_time . " day"));
            $ykj_row = array();

            foreach ($ids_arr as $v) {
                $domain = '';
                $_r = $pan_domain_in->find(array('id' => $v, 'uid' => $uid));
                $domain = $_r['domain'];
                $pingtai = $_r['pingtai'];
                $expire_time = $_r['expire_time'];
                $_expire_time = $_r['expire_time'] . ' 00:00:00';
                if (!$domain)
                    json_s(array('status' => 201, 'msg' => "错误提示：域名id：$v 获取不到详情域名"));
                //判断处理是否停放
                $is_score = 0;
                if ($locked == 9) {
                    $r = $pan_parking->find(array('domain_id' => $v, 'status' => 0));
                    $is_score = $r['income'];
                }
                //判断处理结束时间
                $sy_time = $end_time;
                if ($end_time > $_expire_time) {
                    $sy_time = $_expire_time;
                }
                $row = array(
                    'uid' => $uid,
                    'typeid' => $typeid,
                    'domain' => $domain,
                    'domain_id' => $v,
                    'introduction' => $introduction,
                    'sale_price' => $sale_price,
                    'sale_type' => $sale_type,
                    'pingtai' => $pingtai,
                    'is_score' => $is_score,
                    'expire_time' => $expire_time,
                    'sy_time' => $sy_time,
                    'create_time' => $now_time_str,
                    'act_ip' => $ip,
                    'status' => 1,
                    'act_note' => '[' . $now_time_str . '提交一口价]'
                );
                $ykj_id = $pan_domain_ykj->create($row);
                if ($ykj_id > 0)
                    $ykj_row[] = $ykj_id;
            }
            if ($count != count($ykj_row)) {
                cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
                json_s(array('status' => 300, 'msg' => '错误提示：系统创建一口价域名订单数与实际不符'));
            }
            //---将域名更新状态：一口价中
            //***特别注意：如果是在停放中的域名，是不需要更新状态的***
            if ($locked == 9) {
                //停放中的域名
                $pan_domain_in->update("uid=$uid and locked=9 and id in ($ids)", array('locked' => 9, 'upd_time' => $now_time_str));
                $update_domain_row = $pan_domain_in->affectedRows(); //影响行数
            } else {
                $pan_domain_in->update("uid=$uid and locked=0 and id in ($ids)", array('locked' => 11, 'upd_time' => $now_time_str));
                $update_domain_row = $pan_domain_in->affectedRows(); //影响行数
            }

            if ($update_domain_row != $count) {
                cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
                json_s(array('status' => 300, 'msg' => '错误提示：系统更新域名数量与实际不符'));
            }
            user_log($uid, 1601, $ip, "【用户" . $uid . "】：上架一口价域名，sale_type = $sale_type 数量：$count 个，locked = $locked ，域名ID列表: $ids , 一口价ID列表：" . implode(',', $ykj_row));

            $sql_sw = true;
            if (false === $sql_sw) {
                $sp->runSql('ROLLBACK'); //回滚事务
                cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
                json_s(array('status' => 201, 'msg' => '系统处理出错，已回滚事务。'));
            } else {
                $sp->runSql('COMMIT'); //提交事务
                cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
                json_s(array('status' => 200, 'msg' => '恭喜，您已成功提交<b> ' . $count . ' </b>个域名到一口价出售中'));
            }
        }
        $this->module = "first";
        $this->act = 'first_apply';
        $this->display('amui/first/apply.html');
    }

    function edit() {
        //编辑一口价
        $uid = $this->uid;
        $ip = get_client_ip();
        $now_time = time();
        $now_time_str = date("Y-m-d H:i:s");
        $new_ym_code = spClass('new_ym_code');
        $pan_parking = spClass('pan_parking');
        $pan_domain_ykj = spClass('pan_domain_ykj');
        $pan_domain_in = spClass('pan_domain_in'); // 域名实盘表
        $from = $this->spArgs('from');
        $id = intval($this->spArgs('id'));
        if ($from == 'info') {
            //按ID查具体一口价详情x1
            if (!$id || empty($id))
                json_s(array('status' => 201, 'msg' => '参数ID不能为空'));
            $r = $pan_domain_ykj->find(array('id' => $id, 'uid' => $uid));
            if (!$r) {
                json_s(array('status' => 201, 'msg' => '权限不足'));
            }
            if ($r['status'] != 1) {
                json_s(array('status' => 201, 'msg' => '当前状态不是出售中'));
            }
            json_s(array('status' => 200, 'msg' => 'success', 'data' => array('domain' => $r['domain'], 'sale_price' => (int) $r['sale_price'], 'sale_type' => (int) $r['sale_type'], 'introduction' => $r['introduction'] ? $r['introduction'] : '')));
        }
        if ($from == 'update') {
            //编辑一口价x2
            if (!$id || empty($id))
                json_s(array('status' => 201, 'msg' => '参数ID不能为空'));
            $r = $pan_domain_ykj->find(array('id' => $id, 'uid' => $uid));
            if (!$r) {
                json_s(array('status' => 201, 'msg' => '权限不足'));
            }
            if ($r['status'] != 1) {
                json_s(array('status' => 201, 'msg' => '当前状态不是出售中'));
            }

            $sale_type = intval($this->spArgs('sale_type'));
            $sale_price = $this->spArgs('sale_price');
            $sale_price = bcadd($sale_price, 0, 2); //强制转换成最多只保留两位小数点，防止精度误差
            $introduction = trim($this->spArgs('introduction'));
            $introduction = preg_replace("/<(.*?)>/", "", $introduction);
            $introduction = strip_tags($introduction);
            if ($sale_price <= 0) {
                json_s(array('status' => 201, 'msg' => '单价不能为空'));
            }
            if ($sale_price > 99999999) {
                json_s(array('status' => 201, 'msg' => '单价不能大于99999999'));
            }
            // if(!in_array($sale_type,array(1,2))){
            // json_s(array('status'=>201,'msg'=>'请选择一口价交易方式'));
            // }
            if (strlen($introduction) > 200) {
                json_s(array('status' => 201, 'msg' => '含义备注不能多于200个字符'));
            }
            //处理安全码
            $pw = trim($this->spArgs('safecode'));
            if (empty($pw))
                json_s(array('status' => 201, 'msg' => '交易密码不能为空'));
            $pw = md5(md5($pw . web_md5)); //双重md5加密
            $pws = spClass('pan_user_safecode')->find(array('uid' => $uid)); //密码获取代码
            //------------限制帐号请求验证安全码次数----------begin
            $key_safeCode_name = 'ykj_safeCode_uid_' . $uid;
            if (cache_s($key_safeCode_name) > 30)
                json_s(array('status' => 205, 'msg' => '很抱歉，交易密码验证请求次数限制，请稍后1小时后再操作'));
            //------------限制帐号请求验证安全码次数----------end
            if ($pw != $pws['safecode']) {
                cache_s($key_safeCode_name, intval(cache_s($key_safeCode_name)) + 1, 3600); //输入错误的安全码缓存+1
                json_s(array('status' => 201, 'msg' => '交易密码错误，请注意区分大小写'));
            }
            cache_s($key_safeCode_name, 0, 3600); //输入正确的安全码重置为0
            //处理图形验证码
            $validate = strtolower($this->spArgs('validate')); // 获得前端输入的验证码
            $validate_ = $_SESSION['validate'];
            if ($validate_ == '')
                json_s(array('status' => 209, 'msg' => '请点击重新获取图形验证码'));
            unset($_SESSION['validate']); //不管下面验证是否通过，都要删掉此变量***
            ///----验证码----end
            if ($validate_ != $validate)
                json_s(array('status' => 209, 'msg' => '验证码错误，请重新输入'));
            ///----验证码----end
            //------------限制用户并发请求操作域名相关----------begin
            $domain_action_uid = 'domain_action_uid_' . $uid;
            if (false === cache_a($domain_action_uid, time(), 10))
                json_s(array('status' => 205, 'msg' => '很抱歉，系统队列繁忙，请稍后刷新重试。'));
            //------------限制用户并发请求操作域名相关----------end

            $sp = spClass('pan_domain_ykj');
            $sql_sw = false;
            $sp->runSql("SET AUTOCOMMIT=0");
            $sp->runSql('BEGIN'); //开启事务
            // $pan_domain_ykj->update(array('id'=>$id,'uid'=>$uid),array('sale_price'=>$sale_price,'sale_type'=>$sale_type,'introduction'=>$introduction,'update_time'=>$now_time_str));
            $pan_domain_ykj->update(array('id' => $id, 'uid' => $uid), array('sale_price' => $sale_price, 'introduction' => $introduction, 'update_time' => $now_time_str));
            $update_ykj_row = $pan_domain_ykj->affectedRows(); //影响行数
            if ($update_ykj_row != 1) {
                cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
                json_s(array('status' => 300, 'msg' => '错误提示：系统更新数量与实际不符'));
            }
            $sql_sw = true;
            if (false === $sql_sw) {
                $sp->runSql('ROLLBACK'); //回滚事务
                cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
                json_s(array('status' => 201, 'msg' => '系统处理出错，已回滚事务。'));
            } else {
                $sp->runSql('COMMIT'); //提交事务
                cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
                json_s(array('status' => 200, 'msg' => '域名：' . $r['domain'] . ' 一口价出售信息已编辑成功'));
            }
        }
        if ($from == 'cancel') {
            //撤销一口价
            if (!$id || empty($id))
                json_s(array('status' => 201, 'msg' => '参数ID不能为空'));
            $r = $pan_domain_ykj->find(array('id' => $id, 'uid' => $uid));
            if (!$r) {
                json_s(array('status' => 201, 'msg' => '权限不足'));
            }
            if ($r['status'] != 1) {
                json_s(array('status' => 201, 'msg' => '当前状态不是出售中'));
            }
            //------------限制用户并发请求操作域名相关----------begin
            $domain_action_uid = 'domain_action_uid_' . $uid;
            if (false === cache_a($domain_action_uid, time(), 10))
                json_s(array('status' => 205, 'msg' => '很抱歉，系统队列繁忙，请稍后刷新重试。'));
            //------------限制用户并发请求操作域名相关----------end
            $domain_id = $r['domain_id'];
            $sp = spClass('pan_domain_ykj');
            $sql_sw = false;
            $sp->runSql("SET AUTOCOMMIT=0");
            $sp->runSql('BEGIN'); //开启事务
            $pan_domain_ykj->update(array('id' => $id, 'uid' => $uid), array('status' => 3));
            $update_ykj_row = $pan_domain_ykj->affectedRows(); //影响行数
            if ($update_ykj_row != 1) {
                cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
                json_s(array('status' => 300, 'msg' => '错误提示：系统更新数量与实际不符'));
            }
            //***特别注意：如果是在停放中的域名，是不需要更新状态的***
            $_r = $pan_domain_in->find(array('id' => $domain_id));
            $locked = $_r['locked'];
            if ($locked == 9) {
                //停放中的域名
                $pan_domain_in->update("uid=$uid and locked=9 and id=$domain_id", array('locked' => 9, 'upd_time' => $now_time_str));
                $update_domain_row = $pan_domain_in->affectedRows(); //影响行数
            } else {
                $pan_domain_in->update("uid=$uid and locked=11 and id=$domain_id", array('locked' => 0, 'upd_time' => $now_time_str));
                $update_domain_row = $pan_domain_in->affectedRows(); //影响行数
            }
            if ($update_domain_row != 1) {
                cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
                json_s(array('status' => 300, 'msg' => '错误提示：系统更新域名数量与实际不符'));
            }
            $sql_sw = true;
            if (false === $sql_sw) {
                $sp->runSql('ROLLBACK'); //回滚事务
                cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
                json_s(array('status' => 201, 'msg' => '系统处理出错，已回滚事务。'));
            } else {
                $sp->runSql('COMMIT'); //提交事务
                cache_a($domain_action_uid, null); //删除用户操作域名并发缓存
                json_s(array('status' => 200, 'msg' => '一口价域名：' . $r['domain'] . ' 已撤销下架成功'));
            }
        }
        d404();
    }

    function applyList() {
        //域名一口价出售列表（会员）
        $uid = $this->uid;
        $page = intval($this->spArgs('page', 1));
        if ($page <= 0)
            $page = 1;
        $pan_domain_ykj = spClass('pan_domain_ykj');
        $pan_domain_in = spClass('pan_domain_in');

        $condition = " where uid=$uid ";
        $cond = array('domain' => "", 'typeid' => '');
        // 1出售中2完成交易3撤销4到期下架
        $status_arr = array(1 => '出售中', 2 => '已成交', 3 => '已撤销', 4 => '已下架');
        $sale_type_arr = array(1 => '人民币', 2 => '积分');
        $is_score_arr = array(1 => '有', 2 => '无');
        //域名**********模糊查询************
        if (false != $this->spArgs('domain')) {
            $domain = $pan_domain_ykj->escape($this->spArgs('domain'));
            $condition .= " and domain like '%" . trim($domain, "'") . "%' ";
            $cond['domain'] = trim($domain, "'");
        }
        //域名品种
        if (false != $this->spArgs('typeid')) {
            $typeid = intval($this->spArgs('typeid'));
            $condition .= " and typeid=" . $typeid . " ";
            $cond['typeid'] = trim($typeid, "'");
        }
        //交易状态
        if (false != $this->spArgs('status')) {
            $status = intval($this->spArgs('status'));
            $condition .= " and status=" . $status . " ";
            $cond['status'] = trim($status, "'");
        }
        //交易方式
        if (false != $this->spArgs('sale_type')) {
            $sale_type = intval($this->spArgs('sale_type'));
            $condition .= " and sale_type=" . $sale_type . " ";
            $cond['sale_type'] = trim($sale_type, "'");
        }
        //是否停放
        if (false != $this->spArgs('is_score')) {
            $is_score = intval($this->spArgs('is_score'));
            if ($is_score == 1) {
                $condition .= " and is_score>0";
            }
            if ($is_score == 2) {
                $condition .= " and is_score=0";
            }
            $cond['is_score'] = $is_score;
        }
        $sort = " ORDER BY id desc";
        $sql = "select * from cmpai.pan_domain_ykj " . $condition . $sort;
        $ret = $pan_domain_ykj->spPager($page, pgsize)->findSql($sql);
        //分页开始
        $pager = $pan_domain_ykj->spPager()->getPager();
        if ($pager['total_page'] > 5) {
            if ($page <= 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], $page - 3, 5);
            }
        }
        $this->pager = $pager;
        $ret_ = array();
        $new_ym_code = spClass('new_ym_code');
        $pan_parking_sys_log = spClass('pan_parking_sys_log'); //具体记录
        foreach ($ret as $v) {
            $r = $new_ym_code->spCache(3600)->find(array('code' => $v['typeid']));
            $v['name'] = $r['name'];
            $v['_is_score'] = '无';
            if ($v['is_score'] > 0) {
                $v['_is_score'] = $v['is_score'] . '积分/天';
            }
            $ret_[] = $v;
        }
        $dlist = "select code as id,name from cmpai.new_ym_code where state=1";
        $types = $new_ym_code->spCache(3600)->findSql($dlist);
        $this->types = $types;
        $this->ret = $ret_;
        $this->module = "first";
        $this->act = 'first_applyList';
        $this->cond = $cond;
        $this->status_arr = $status_arr;
        $this->sale_type_arr = $sale_type_arr;
        $this->is_score_arr = $is_score_arr;
        $this->display('amui/first/applyList.html');
    }

    function applyList_buy() {
        //域名一口价买入列表（会员）
        $uid = $this->uid;
        $page = intval($this->spArgs('page', 1));
        if ($page <= 0)
            $page = 1;
        $pan_domain_ykj_deal = spClass('pan_domain_ykj_deal');
        $pan_domain_in = spClass('pan_domain_in');
        $ret = $pan_domain_ykj_deal->spPager($page, pgsize)->findAll(array('uid' => $uid), "id desc");
        //分页开始
        $pager = $pan_domain_ykj_deal->spPager()->getPager();
        if ($pager['total_page'] > 5) {
            if ($page <= 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], $page - 3, 5);
            }
        }
        $this->pager = $pager;
        $ret_ = array();
        $new_ym_code = spClass('new_ym_code');
        $pan_parking_sys_log = spClass('pan_parking_sys_log'); //具体记录
        foreach ($ret as $v) {
            $r = $new_ym_code->spCache(3600)->find(array('code' => $v['typeid']));
            $v['name'] = $r['name'];
            $v['_is_score'] = '无';
            if ($v['is_score'] > 0) {
                $v['_is_score'] = $v['is_score'] . '积分/天';
            }
            $ret_[] = $v;
        }

        $this->ret = $ret_;
        $this->module = "first";
        $this->act = 'first_applyList_buy';
        $this->display('amui/first/applyList_buy.html');
    }

}

?>