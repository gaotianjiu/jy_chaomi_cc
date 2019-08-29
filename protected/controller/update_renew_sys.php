<?php
error_reporting(E_ALL || ~E_NOTICE);
class update_renew_sys extends spController
{
    function __construct(){
        parent::__construct();
		// echo md5(date('Y-m-d H',time()).'ChaoMi-Token'); //示例:每小时变一次
		check_token($this->spArgs('token'));//验证权限token
    }	
	function tips(){
		//用户域名到期续费提醒
		$day = intval($this->spArgs('day', 3));
		$pan=spClass('pan_domain_in');
		$expire_time = date("Y-m-d",strtotime("+$day day"));
		$now_time = date("Y-m-d");
		$cache_key = 'cm_sys_renew_tips_day_'.date("Ymd").'_d__'.$day;
		if(cache_s($cache_key))exit('cache is');
		$cond_time = "expire_time<='{$expire_time}' and expire_time>='{$now_time}'";
		if($day==0){
			$cond_time = "expire_time<'{$now_time}'";
		}
		$sql="select uid,count(uid) as num from cmpai.pan_domain_in where $cond_time group by uid order by num desc;"; //查出快到期的域名按UID集合列表
		$ret=$pan->findSql($sql);
		$domain_count = 0;
		if($day==0){
			foreach($ret as $v) {
				$uid = $v['uid'];
				$num = $v['num'];
				$type =  '901';
				$tit  =  '提醒：您有'.$num.'个域名已到期';
				$txt  =  '提醒：您有'.$num.'个域名已到期，请马上续费。如域名已到期20天未续费，系统将自动没收，具体域名列表：<a href="/trading/domainList?domain=&pz=0&status=-1&registrar=0&expire=1">查看</a>';
				web_msg_send($tit,$type,$uid,$txt);
				send_mobile_email($uid,"炒米网(chaomi.cc)域名已到期续费提醒",'您有'.$num.'个域名已到期，请马上续费。如域名已到期20天未续费，系统将自动没收。');
				$domain_count += $num;
			}			
		}else{
			foreach($ret as $v) {
				$uid = $v['uid'];
				$num = $v['num'];				
				$type =  '901';
				$tit  =  '提醒：您有'.$num.'个域名即将到期需续费';
				$txt  =  '提醒：您有'.$num.'个域名即将到期需续费，请及时处理续费，以免到期，具体域名列表：<a href="/trading/domainList?domain=&pz=0&status=-1&registrar=0&expire=1">查看</a>';
				web_msg_send($tit,$type,$uid,$txt);
				send_mobile_email($uid,"炒米网(chaomi.cc)域名到期续费提醒",'您有'.$num.'个域名即将到期需续费，请及时处理续费，以免到期。');
				$domain_count += $num;
			}			
		}
		if($domain_count>0){
			//----邮件后台提醒----begin
			$content = array();
			$content['to'] = array('pwpet@qq.com');
			$content['sub'] = array('%content%'=>array('域名续费提醒：day='.$day.'，域名数：'.$domain_count.'，用户数：'.count($ret)));
			$new_content = json_encode($content);
			send_mail('pwpet@qq.com','【炒米后台提醒】域名续费提醒',$new_content,8);									
			//----邮件后台提醒----end	
		}
		cache_s($cache_key,time(),3600*24*3);
		json_s(array('status'=>200,'uid_count'=>count($ret),'domain_count'=>$domain_count));		
	}
	function create(){	
		$typeid = intval($this->spArgs('typeid'));
		if(!$typeid)exit('typeid error');
		$time_h = date('H');
		$time_i = date('i');
		if($time_h==0){
			if($time_i<1 || $time_i>8)exit("TIME: H=0 and i<1 and i>8");
		}else{
			exit('TIME: H!=0');
		}
		exit('free...');
		//--------操作缓存处理-----begin
		$cache_name = 'cm_update_renew_sys_'.$typeid;
		if(false === cache_a($cache_name,time(),10))json_s(array('status'=>208,'msg'=>'操作占用中'));	
		//--------操作缓存处理-----end		
		$date = date("Y-m-d");
		$pan_domain_in = spClass('pan_domain_in'); //域名实盘米
		$pan_renew_price = spClass('pan_renew_price'); //价格表
		$pan_renew_date_log = spClass('pan_renew_date_log'); //进度监测表
		$r_p = $pan_renew_price->find(array('typeid'=>$typeid)); 
		$price = $r_p['price']; //取出当前品种的每日续费价格(一个域名)
		if($price==0)exit('renew price==0');
		$r_c = $pan_domain_in->findSql("select count(id) as count from cmpai.pan_domain_in where typeid='{$typeid}'");
		$count = $r_c[0]['count']; //取出当前品种需要续费的域名总数量
		$c_price = $price * $count; //总价格
		$r = $pan_renew_date_log->find(array('typeid'=>$typeid,'date'=>$date));
		if(!$r){
			// 新建
			$pan_renew_date_log->create(array('typeid'=>$typeid,'date'=>$date,'count'=>$count,'price'=>$c_price));
		}else{
			cache_a($cache_name,null); //删缓存
			if(!$r['push_time']){
				$pan_renew_date_log->update(array('typeid'=>$typeid,'date'=>$date),array('push_time'=>date("Y-m-d H:i:s")));
				$true_open = '错误';
				if($r['count']==$r['sys_count'])$true_open = '正常';
				//----邮件后台提醒----begin
					$content = array();
					$content['to'] = array('pwpet@qq.com');
					$content['sub'] = array('%content%'=>array('品种续费：'.auto_get_name($typeid).'，系统操作结果：</br>【'.$true_open.'】count='.$r['count'].' sys_count='.$r['sys_count'].' price='.$r['price'].' sys_price='.$r['price']));
					$new_content = json_encode($content);
					send_mail('pwpet@qq.com','【炒米后台提醒】品种续费：'.auto_get_name($typeid).'，系统操作【'.$true_open.'】',$new_content,8);						
				//----邮件后台提醒----end				
			}
			exit('typeid='.$typeid.' today is work : now_count='.$count.' count='.$r['count'].' sys_count='.$r['sys_count']);
		}
		$sql ="select distinct uid from cmpai.pan_domain_in where typeid='{$typeid}' order by uid;";
		$list = $pan_domain_in->findSql($sql);
		foreach($list as $v) {
			renew_work($typeid,$v['uid']);
		}		
		cache_a($cache_name,null); //删缓存
		json_s(array('status'=>200,'typeid'=>$typeid,'count'=>$count,"list"=>$list));
	}	
		
}
function renew_work($typeid,$uid){	
	// $uid = intval($this->spArgs('uid'));
	// $typeid = intval($this->spArgs('typeid'));
	if(!$uid)exit('uid error');
	if(!$typeid)exit('typeid error');
	//--------操作缓存处理-----begin
	$cache_name = 'cm_update_renew_sys_uid_'.$uid.'_typeid_'.$typeid;
	if(false === cache_a($cache_name,time(),20))json_s(array('status'=>208,'msg'=>'操作占用中'));	
	
	//------------限制用户并发请求操作域名相关----------begin
	$domain_action_uid = 'domain_action_uid_'.$uid;
	if(false === cache_a($domain_action_uid,time(),20))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
	//------------限制用户并发请求操作域名相关----------end			
	
	//--------操作缓存处理-----end		
	$date = date("Y-m-d");
	$pan_domain_in = spClass('pan_domain_in'); //域名实盘米
	$pan_renew_price = spClass('pan_renew_price'); //价格表
	$pan_renew_date_log = spClass('pan_renew_date_log'); //进度监测表
	$pan_renew_sys_log = spClass('pan_renew_sys_log'); //每个域名当日扣费具体
	$r_p = $pan_renew_price->find(array('typeid'=>$typeid)); 
	$price = $r_p['price']; //取出当前品种的每日续费价格(一个域名)
	if($price==0)exit('renew price==0');
	$r = $pan_renew_date_log->find(array('typeid'=>$typeid,'date'=>$date));
	if(!$r){
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		cache_a($cache_name,null); //删缓存				
		exit('pan_renew_date_log null');
	}
	if($pan_renew_sys_log->findCount(array('typeid'=>$typeid,'date'=>$date,'uid'=>$uid))>0){
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			cache_a($cache_name,null); //删缓存				
			exit('pan_renew_sys_log typeid+date+uid Count >0');					
	}		
	$sp = spClass('lib_member_account');
	$sql_sw = false;
	$sp->runSql("SET AUTOCOMMIT=0");	
	$sp->runSql('BEGIN'); //开启事务	
	
	$domain_ret = $pan_domain_in->findSql("select id,uid,domain from cmpai.pan_domain_in where uid={$uid} and typeid='{$typeid}' FOR UPDATE"); //详情的域名列表
	$r_c = $pan_domain_in->findSql("select count(id) as count from cmpai.pan_domain_in where uid={$uid} and typeid='{$typeid}' FOR UPDATE"); //域名总数量
	$count = $r_c[0]['count']; //取出当前品种当前会员UID需要续费的域名总数量
	$c_price = $price * $count; //总价格
	$pan_renew_date_log->update(array('typeid'=>$typeid,'date'=>$date),array('sys_count'=>$count+$r['sys_count'],'sys_price'=>$c_price+$r['sys_price']));

	$pid_c = 0;
	foreach($domain_ret as $v) {
		if($v['uid']!=$uid){
			//防止UID不相等
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			cache_a($cache_name,null); //删缓存				
			exit('domain uid != v[uid]');
		}
		$row = array('typeid'=>$typeid,'uid'=> $uid,'domain_id'=> $v['id'],'domain'=>$v['domain'],'price'=>$price,'date'=>$date);
		$pid = $pan_renew_sys_log->create($row);			
		if($pid>0)$pid_c++;
	}			
	if($pid_c!=$count){
			//实际更新数据不相等
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			cache_a($cache_name,null); //删缓存				
			exit('domain pid != count');			
	}	
	$time1 = time();
	$time_str = date("Y-m-d H:i:s");
	//**********扣除费用 并添加账务记录************ begin
		//-----------查询用户账户---------
		$bal_sql = "select * from ykjhqcom.lib_member_account where uid = $uid FOR UPDATE"; //*****只要是查余额表的，必须前面开事务，并且后面加上 FOR UPDATE 单行锁表
		$member_Account_result = $sp->findSql($bal_sql);		
		$new_amount = $member_Account_result[0]['balance'] - $c_price;
		if ($member_Account_result) {
			$member_Account_sql = "update ykjhqcom.lib_member_account set balance=$new_amount where uid=$uid";
			$sp->runSql($member_Account_sql);
		} else {
			$sp->create(array('uid' => $uid, 'balance'=>$new_amount));
		}	
		$order_id = 'XF'.date("YmdHis").$uid.mt_rand(100000,999999);
		//准备添加流水相关数据,与流水表字段名相同
		$note = '自动扣除域名续费，域名品种：'.auto_get_name($typeid).'，续费单价'.$price.'/天，一共'.$count.'个域名，扣除您续费：'.$c_price.'元';
		$row = array(
			'uid' => $uid,
			'order_id' => $order_id,
			'type' => '500',
			'amount' => $c_price,
			'ip' => get_client_ip(),
			'deal_time' => $time1,
			'note' => $note,
			'balance' => $new_amount,
			'y' => date("Y", $time1),
			'm' => date("m", $time1),
			'd' => date("d", $time1)
		);
		//添加流水
		$member_records = spClass('lib_member_records');
		$member_records->create($row);		
        //日志
        user_log($uid,618,get_client_ip(),'【买家资产】用户：'.$uid.'自动扣除域名续费，域名品种：'.auto_get_name($typeid).'，续费单价'.$price.'/天，一共'.$count.'个域名，扣除您续费：'.$c_price.'元'.'，账户余额'.$new_amount.'元');
		
	//**********扣除费用 并添加账务记录************ end
	
	$sql_sw = true;
	if(false===$sql_sw){
		$sp->runSql('ROLLBACK'); //回滚事务
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		cache_a($cache_name,null); //删缓存
		json_s(array('status'=>205,'msg'=>'系统事务出错，请稍候重试。'));
	}else{
		$sp->runSql('COMMIT'); //提交事务
		cache_a($cache_name,null); //删缓存			
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
	}		
	// json_s(array('status'=>200,'typeid'=>$typeid,'uid'=>$uid,'count'=>$count,"price"=>$c_price,'sql count'=>$pid_c));
	return array('status'=>200,'typeid'=>$typeid,'uid'=>$uid,'count'=>$count,"price"=>$c_price,'sql count'=>$pid_c);
}

//获取域名类型
function auto_get_name($typeid){
	$sp = spClass('new_ym_code');
    $ret = $sp->find(array('code'=>$typeid));
    return $ret['name'];
}














