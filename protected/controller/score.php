<?php
/*
 * 积分模块
 *
 */
define("web_md5", "_chaomi_cc");
class score extends spController{
    function __construct(){ 
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
	function hd(){
		exit;
		//临时用，初始送1000积分
		$pan_member_score_list = spClass('pan_member_score_list');
		$sp = spClass('pan_member_score');
		if($this->spArgs('act')=='list'){
			$member = spClass('pan_member_card')->findAll(array('status'=>2),'','uid');
			json_s($member);
		}
		$touid = intval($this->spArgs('uid'));
		if(!$touid)exit;
		$amount = 1000;
		$typeid = 411104;
		$cache_key = 'score_hd_n_touid_'.$touid;
		if(cache_s($cache_key))exit('touid_'.$touid.' is in');
		//*****--------------操作入方相关处理-----begin
		//-----------查询用户账户---------
		$bal_sql = "select * from cmpai.pan_member_score where uid = $touid and typeid=$typeid FOR UPDATE"; //*****只要是查余额表的，必须前面开事务，并且后面加上 FOR UPDATE 单行锁表
		$in_member_score_result = $sp->findSql($bal_sql);		
		$new_amount = $in_member_score_result[0]['balance'] + $amount;
		if ($in_member_score_result) {
			$in_member_score_sql = "update cmpai.pan_member_score set balance=balance+$amount where uid=$touid and typeid=$typeid";
			$sp->runSql($in_member_score_sql);
		} else {
			$sp->create(array('uid' => $touid,'typeid'=>$typeid,'balance' => $amount));
		}			
		//准备添加积分流水相关数据,与流水表字段名相同
		$now_time_str = date("Y-m-d H:i:s");
		$lsarr = array(
			'uid' => $touid,
			'typeid' => $typeid,
			'type' => '2',
			'amount' => $amount,
			'balance' => $new_amount,
			'act_ip' => 'sys',
			'create_time' => $now_time_str,
			'note' => '获得赠送积分'
		);
		//添加积分流水
		$pan_member_score_list->create($lsarr);	
		//---添加站内短信---begin
		$type =  '901';
		$tit  =  '恭喜，您获得了1000个积分';
		$txt  =  '恭喜，您获得了1000个积分，积分有什么用？详情：http://my.chaomi.cc/announce';
		web_msg_send($tit,$type,$touid,$txt);						
		//---添加站内短信---end	
		send_mobile_email($touid,"炒米网(chaomi.cc)积分变动提醒",'恭喜您获得了1000个积分，积分有什么用？积分可以兑换域名哦！');			
		//*****--------------操作入方相关处理-----begin		
	
		$sql_sw = true;
		if(false===$sql_sw){
			$sp->runSql('ROLLBACK'); //回滚事务
			cache_a($score_action_uid,null);//删除用户操作积分并发缓存
			json_s(array('status'=>201,'msg'=>'系统处理出错，已回滚事务。'));				
		}else{
			$sp->runSql('COMMIT'); //提交事务
			cache_a($score_action_uid,null);//删除用户操作积分并发缓存
			cache_s($cache_key,time(),86400);
			json_s(array('status'=>200,'msg'=>'已成功赠送'));	
		}		
	}
    function push(){
		//积分转移
		$uid = $this->uid;
		$mid = $this->mid;
		$ip = get_client_ip();
		$now_time = time();
		$now_time_str = date("Y-m-d H:i:s");
        $pub_user = spClass('pub_user');
		$pan_member_score = spClass('pan_member_score');
		$pan_member_score_lock = spClass('pan_member_score_lock');
		$from = $this->spArgs('from');
		$typeid = 411104;
		$tp_arr = array(411104=>'积分');
		
		$rb_ = $pan_member_score->find(array('uid'=>$uid,'typeid'=>$typeid));
		$balance = $rb_['balance']?$rb_['balance']:0;
		$this->balance = $balance;
		if($from=='tomid_info'){
			//----查询判断对方mid信息是否有误----begin
			$tomid = $this->spArgs('tomid');
			if(!is_numeric($tomid) || $tomid<=0)json_s(array('status'=>201,'msg'=>'请输入正确的对方会员ID'));
			$r = $pub_user->find(array('mid'=>$tomid));
			if(!$r)json_s(array('status'=>201,'msg'=>'请输入正确的对方会员ID'));
			if(!$r['mobile'])json_s(array('status'=>201,'msg'=>'对方会员帐号未绑定手机号'));
			$touid = $r['uid'];
			if($uid==$touid)json_s(array('status'=>201,'msg'=>'会员ID不能是当前帐号ID'));
			$mobile = substr_replace($r['mobile'],'****',3,4);  
			$member_info = spClass('pan_member_card')->find(array('uid' => $touid,'status'=>2));
			$to_uid_name = $member_info['first_name'].$member_info['last_name'];
			if(!$to_uid_name)json_s(array('status'=>201,'msg'=>'对方会员帐号未实名认证，不能转移!'));				
			$member_info_i = spClass('pan_member_info')->find(array('uid' => $touid));
			$member_qq = 'QQ：未填';
			if($member_info_i['qq']){
				$member_qq = 'QQ：'.substr_qq($member_info_i['qq']);
			}
			json_s(array('status'=>200,'msg'=>'手机：'.$mobile.' 姓名：'.substr_cut($to_uid_name).' '.$member_qq));
			//----查询判断对方mid信息是否有误----end
		}
		if($from=='create'){
			json_s(array('status'=>201,'msg'=>'暂停转移'));
			$to_username = trim($this->spArgs('to_username'));
			//验证是否实名
			$member_info = spClass('pan_member_card')->find(array('uid' => $uid));
			$now_uid_name = $member_info['first_name'].$member_info['last_name'];
			if(!$now_uid_name)json_s(array('status'=>201,'msg'=>'请实名认证后再操作转移'));

			//----查询判断对方mid信息是否有误----begin
			$tomid = $this->spArgs('tomid');
			if(!is_numeric($tomid) || $tomid<=0)json_s(array('status'=>201,'msg'=>'请输入正确的对方会员ID'));
			$r = $pub_user->find(array('mid'=>$tomid));
			if(!$r)json_s(array('status'=>201,'msg'=>'请输入正确的对方会员ID'));
			if(!$r['mobile'])json_s(array('status'=>201,'msg'=>'对方会员帐号未绑定手机号'));
			$touid = $r['uid'];
			if($uid==$touid)json_s(array('status'=>201,'msg'=>'会员ID不能是当前帐号ID'));
			$member_info = spClass('pan_member_card')->find(array('uid' => $touid,'status'=>2));
			$to_uid_name = $member_info['first_name'].$member_info['last_name'];
			if(!$to_uid_name)json_s(array('status'=>201,'msg'=>'对方会员帐号未实名认证'));
			//如果用户选择校验对方的姓名
			if($to_username){
				if($to_username!=$to_uid_name){
					json_s(array('status'=>201,'msg'=>'对方会员ID与当前校验的姓名不一致'));
				}
			}
			//----查询判断对方mid信息是否有误----end
			
			//查询出锁定中的积分----begin
			$rs_ = $pan_member_score_lock->findSql("select sum(amount) as release_balance from cmpai.pan_member_score_lock where uid = $uid and typeid=$typeid and release_time>'$now_time_str'");
			$release_balance = $rs_[0]['release_balance'];
			//查询出锁定中的积分----end
			
			$rb_ = $pan_member_score->find(array('uid'=>$uid,'typeid'=>$typeid));
			$balance = $rb_['balance'];	//当前积分品种总数量
			$tp_name = $tp_arr[$typeid];
			if(!$tp_name)json_s(array('status'=>201,'msg'=>'品种参数错误'));
			//处理判断流水积分
			if($balance<=0)json_s(array('status'=>201,'msg'=>'当前帐号可用'.$tp_name.'不足'));
			$amount = $this->spArgs('amount');
			if(!is_numeric($amount) || $amount<=0)json_s(array('status'=>201,'msg'=>'转移'.$tp_name.'数量参数错误'));
			if($amount>$balance)json_s(array('status'=>201,'msg'=>'转移'.$tp_name.'数量大于当前可用数量'));
			if($amount>$balance-$release_balance)json_s(array('status'=>201,'msg'=>'安全提示：有'.$release_balance.$tp_name.'系统冻结1小时中'));

			//处理安全码
			$pw = trim($this->spArgs('safecode'));
			if(empty($pw))json_s(array('status'=>201,'msg'=>'交易密码不能为空'));
			$pw = md5(md5($pw . web_md5)); //双重md5加密			
			$pws = spClass('pan_user_safecode')->find(array('uid' => $uid));//密码获取代码
			//------------限制帐号请求验证安全码次数----------begin
			$key_safeCode_name = 'score_safeCode_uid_'.$uid;
			if(cache_s($key_safeCode_name)>30)json_s(array('status'=>205,'msg'=>'很抱歉，安全码验证请求次数限制，请稍后1小时后再操作'));				
			//------------限制帐号请求验证安全码次数----------end	
			if ($pw != $pws['safecode']) {
				cache_s($key_safeCode_name,intval(cache_s($key_safeCode_name))+1,3600);//输入错误的安全码缓存+1
				json_s(array('status'=>201,'msg'=>'交易密码错误，请注意区分大小写'));
			}
			cache_s($key_safeCode_name,0,3600);//输入正确的安全码重置为0
			
			//处理图形验证码
			$validate = strtolower($this->spArgs('validate')); // 获得前端输入的验证码
			$validate_ = $_SESSION['validate'];
			if($validate_=='')json_s(array('status'=>209,'msg'=>'请点击重新获取图形验证码'));
			unset($_SESSION['validate']); //不管下面验证是否通过，都要删掉此变量***
			///----验证码----end
			if($validate_!=$validate)json_s(array('status'=>209,'msg'=>'验证码错误，请重新输入'));
			///----验证码----end	
			
			//------------限制用户并发请求操作积分相关----------begin
			$score_action_uid = 'score_action_uid'.$uid;
			if(false === cache_a($score_action_uid,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
			//------------限制用户并发请求操作积分相关----------end	
			
			$sp = spClass('pan_member_score');
			$sql_sw = false;
			$sp->runSql("SET AUTOCOMMIT=0");
			$sp->runSql('BEGIN'); //开启事务
			$pan_member_score_list = spClass('pan_member_score_list');
			
			//积分转移后，接收方锁定积分一段时间
			$release_time = date("Y-m-d H:i:s",time()+3600);
			$pan_member_score_lock->create(array('uid'=>$touid,'typeid'=>$typeid,'amount'=>$amount,'create_time'=>$now_time_str,'release_time'=>$release_time));
			
			//*****--------------操作出方相关处理-----begin
            //-----------查询用户账户---------
            $bal_sql = "select * from cmpai.pan_member_score where uid = $uid and typeid=$typeid FOR UPDATE"; //*****只要是查余额表的，必须前面开事务，并且后面加上 FOR UPDATE 单行锁表
            $out_member_score_result = $sp->findSql($bal_sql);		
			$new_amount = $out_member_score_result[0]['balance'] - $amount;
			if ($out_member_score_result) {
				$out_member_score_sql = "update cmpai.pan_member_score set balance=balance-$amount where uid=$uid and typeid=$typeid";
				$sp->runSql($out_member_score_sql);
			} else {
				$sp->create(array('uid' => $uid,'typeid'=>$typeid,'balance' => $amount));
			}			
            //准备添加积分流水相关数据,与流水表字段名相同		
            $lsarr = array(
                'uid' => $uid,
                'from_uid' => $touid,
                'typeid' => $typeid,
                'type' => '2',
                'amount' => -$amount,
                'balance' => $new_amount,
                'act_ip' => $ip,
                'create_time' => $now_time_str,
                'note' => '转移您的'.$tp_name.'：'.$amount.'给对方，会员ID：'.$tomid.' ('.substr_cut($to_uid_name).')'
            );
            //添加积分流水	
			$pan_member_score_list->create($lsarr);		
			//---添加站内短信---begin
			$type =  '901';
			$tit  =  '您已转移'.$amount.$tp_name.'给会员ID'.$tomid;
			$txt  =  '您已转移'.$tp_name.'：'.$amount.'给对方，会员ID：'.$tomid.' ('.substr_cut($to_uid_name).')';
			web_msg_send($tit,$type,$uid,$txt);						
			//---添加站内短信---end	
			send_mobile_email($uid,"炒米网(chaomi.cc)积分变动提醒",'您已转移'.$tp_name.'：'.$amount.'给对方，会员ID：'.$tomid.' ('.substr_cut($to_uid_name).')');				
			//*****--------------操作出方相关处理-----begin
			
			//*****--------------操作入方相关处理-----begin
            //-----------查询用户账户---------
            $bal_sql = "select * from cmpai.pan_member_score where uid = $touid and typeid=$typeid FOR UPDATE"; //*****只要是查余额表的，必须前面开事务，并且后面加上 FOR UPDATE 单行锁表
            $in_member_score_result = $sp->findSql($bal_sql);		
			$new_amount = $in_member_score_result[0]['balance'] + $amount;
			if ($in_member_score_result) {
				$in_member_score_sql = "update cmpai.pan_member_score set balance=balance+$amount where uid=$touid and typeid=$typeid";
				$sp->runSql($in_member_score_sql);
			} else {
				$sp->create(array('uid' => $touid,'typeid'=>$typeid,'balance' => $amount));
			}			
            //准备添加积分流水相关数据,与流水表字段名相同
            $lsarr = array(
                'uid' => $touid,
                'from_uid' => $uid,
                'typeid' => $typeid,
                'type' => '2',
                'amount' => $amount,
                'balance' => $new_amount,
                'act_ip' => $ip,
                'create_time' => $now_time_str,
                'note' => '对方转给您：'.$amount.$tp_name.'，会员ID：'.$mid.' ('.substr_cut($now_uid_name).')'
            );
            //添加积分流水
			$pan_member_score_list->create($lsarr);	
			//---添加站内短信---begin
			$type =  '901';
			$tit  =  '会员'.$mid.'转移'.$amount.$tp_name.'给您';
			$txt  =  '对方转给您：'.$amount.$tp_name.'，会员ID：'.$mid.' ('.substr_cut($now_uid_name).')';
			web_msg_send($tit,$type,$touid,$txt);						
			//---添加站内短信---end	
			send_mobile_email($touid,"炒米网(chaomi.cc)积分变动提醒",'对方转给您：'.$amount.$tp_name.'，会员ID：'.$mid.' ('.substr_cut($now_uid_name).')');			
			//*****--------------操作入方相关处理-----begin		
			
			user_log($uid, 1502, $ip,'[出]会员ID：'.$uid.'操作转移积分['.$tp_name.']：'.$amount.'给对方，[入]会员ID：'.$touid);

			$sql_sw = true;
			if(false===$sql_sw){
				$sp->runSql('ROLLBACK'); //回滚事务
				cache_a($score_action_uid,null);//删除用户操作积分并发缓存
				json_s(array('status'=>201,'msg'=>'系统处理出错，已回滚事务。'));				
			}else{
				$sp->runSql('COMMIT'); //提交事务
				cache_a($score_action_uid,null);//删除用户操作积分并发缓存	
				json_s(array('status'=>200,'msg'=>'已成功转移'));	
			}		
		}		
        $this->type_options = array(array('name'=>'积分','code'=>411104));
        $this->module = "score";
        $this->act = 'score_push';
        $this->display('amui/score/push.html');
    }
    function dlist(){
		//积分流水列表
		$uid = $this->uid;
		$page = intval($this->spArgs('page', 1));
		if($page<=0)$page=1;		
		$pan_member_score_list = spClass('pan_member_score_list');
		$ret = $pan_member_score_list->spPager($page, pgsize)->findAll(array('uid'=>$uid),"id desc");
		//分页开始
        $pager = $pan_member_score_list->spPager()->getPager();
        if ($pager['total_page'] > 5) {
            if ($page <= 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], $page - 3, 5);
            }
        }
        $this->pager = $pager;			
        $this->ret = $ret;
        $this->module = "score";
        $this->act = 'score_list';
        $this->display('amui/score/list.html');		
    }
}
function substr_cut($user_name){
    $strlen     = mb_strlen($user_name, 'utf-8');
    $lastStr     = mb_substr($user_name, -1, 1, 'utf-8');
    if($strlen == 2){
        return '*'.$lastStr;
    }else{
        return '**'. $lastStr;
    }
}
function substr_qq($user_name){
    $strlen     = mb_strlen($user_name, 'utf-8');
    $firstStr = mb_substr($user_name, 0, 2, 'utf-8');  
    $lastStr     = mb_substr($user_name, -2, 2, 'utf-8');
    if($strlen < 5){
        return '*'.$lastStr;
    }else{
        return $firstStr.str_repeat('*', $strlen-4). $lastStr;
    }
}
?>