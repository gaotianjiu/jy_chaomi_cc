<?php
error_reporting(E_ALL || ~E_NOTICE);
class update_score_sys extends spController
{
    function __construct(){
        parent::__construct();
		// echo md5(date('Y-m-d H',time()).'ChaoMi-Token'); //示例:每小时变一次
		check_token($this->spArgs('token'));//验证权限token
    }	
	function create(){	
		//发放积分
		exit;
		$typeid = intval($this->spArgs('typeid'));
		if(!$typeid)exit('typeid error');
		$time_h = date('H');
		$time_i = date('i');
		if($time_h==0){
			if($time_i<10 || $time_i>19)exit("TIME: H=0 and i<10 and i>19");
		}else{
			exit('TIME: H!=0');
		}
		// --------操作缓存处理-----begin
		$cache_name = 'cm_update_score_sys_'.$typeid;
		if(false === cache_a($cache_name,time(),10))json_s(array('status'=>208,'msg'=>'操作占用中'));	
		// --------操作缓存处理-----end		
		$date = date("Y-m-d");
		$pan_domain_in = spClass('pan_domain_in'); //域名实盘米
		$pan_score_date_log = spClass('pan_score_date_log'); //进度监测表
		$cond_expire_time_sql = "";
		if($typeid==411104){
			$cond_expire_time_sql = "expire_time >= '2019-01-01'";
			$amount_one = 1; 
		}
		if($typeid==411109){
			// $cond_expire_time_sql = "expire_time >= '2021-09-01'";
		}
		if($amount_one==0)exit('amount_one = 0');
		$r_c = $pan_domain_in->findSql("select count(id) as count from cmpai.pan_domain_in where typeid='{$typeid}' and $cond_expire_time_sql and locked in(0,1)");
		$count = $r_c[0]['count']; //取出当前品种需要发放积分的域名总数量
		$amount = $amount_one * $count; //总价格
		$r = $pan_score_date_log->find(array('typeid'=>$typeid,'date'=>$date));
		if(!$r){
			// 新建
			$pan_score_date_log->create(array('typeid'=>$typeid,'date'=>$date,'count'=>$count,'amount'=>$amount));
		}else{
			cache_a($cache_name,null); //删缓存
			if(!$r['push_time']){
				$pan_score_date_log->update(array('typeid'=>$typeid,'date'=>$date),array('push_time'=>date("Y-m-d H:i:s")));
				$true_open = '错误';
				if($r['count']==$r['sys_count'])$true_open = '正常';
				//----邮件后台提醒----begin
					$content = array();
					$content['to'] = array('pwpet@qq.com');
					$content['sub'] = array('%content%'=>array('域名持有积分：'.auto_get_name($typeid).'，系统操作结果：</br>【'.$true_open.'】count='.$r['count'].' sys_count='.$r['sys_count'].' c_amount='.$r['amount'].' sys_amount='.$r['sys_amount']));
					$new_content = json_encode($content);
					send_mail('pwpet@qq.com','【炒米后台提醒】域名持有积分：'.auto_get_name($typeid).'，系统操作【'.$true_open.'】',$new_content,8);						
				//----邮件后台提醒----end				
			}
			exit('typeid='.$typeid.' today is work : now_count='.$count.' count='.$r['count'].' sys_count='.$r['sys_count']);
		}
		$sql ="select distinct uid from cmpai.pan_domain_in where typeid='{$typeid}' and $cond_expire_time_sql and locked in(0,1) order by uid;";
		$list = $pan_domain_in->findSql($sql);
		foreach($list as $v) {
			score_work($typeid,$v['uid'],$amount_one,$cond_expire_time_sql);
		}		
		cache_a($cache_name,null); //删缓存
		json_s(array('status'=>200,'typeid'=>$typeid,'count'=>$count,"list"=>$list));
	}		
}
function score_work($typeid,$uid,$amount_one,$cond_expire_time_sql){	
	if(!$uid)exit('uid error');
	if(!$typeid)exit('typeid error');
	//--------操作缓存处理-----begin
	$cache_name = 'cm_update_score_sys_uid_'.$uid.'_typeid_'.$typeid;
	if(false === cache_a($cache_name,time(),20))json_s(array('status'=>208,'msg'=>'操作占用中'));	
	
	//------------限制用户并发请求操作域名相关----------begin
	$domain_action_uid = 'domain_action_uid_'.$uid;
	if(false === cache_a($domain_action_uid,time(),20))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
	//------------限制用户并发请求操作域名相关----------end			
	
	//--------操作缓存处理-----end		
	$date = date("Y-m-d");
	$pan_domain_in = spClass('pan_domain_in'); //域名实盘米
	$pan_score_date_log = spClass('pan_score_date_log'); //进度监测表
	$pan_score_sys_log = spClass('pan_score_sys_log'); //每个域名当日发放积分具体
	$r = $pan_score_date_log->find(array('typeid'=>$typeid,'date'=>$date));
	if(!$r){
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		cache_a($cache_name,null); //删缓存				
		exit('pan_score_date_log null');
	}
	if($pan_score_sys_log->findCount(array('typeid'=>$typeid,'date'=>$date,'uid'=>$uid))>0){
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			cache_a($cache_name,null); //删缓存				
			exit('pan_score_sys_log typeid+date+uid Count >0');					
	}		
	$pan_member_score_list = spClass('pan_member_score_list');
	$sp = spClass('pan_member_score');
	$sql_sw = false;
	$sp->runSql("SET AUTOCOMMIT=0");	
	$sp->runSql('BEGIN'); //开启事务
	
	$domain_ret = $pan_domain_in->findSql("select id,uid,domain from cmpai.pan_domain_in where uid={$uid} and typeid='{$typeid}' and $cond_expire_time_sql and locked in(0,1) FOR UPDATE"); //详情的域名列表
	$r_c = $pan_domain_in->findSql("select count(id) as count from cmpai.pan_domain_in where uid={$uid} and typeid='{$typeid}' and $cond_expire_time_sql and locked in(0,1) FOR UPDATE"); //域名总数量
	$count = $r_c[0]['count']; //取出当前品种当前会员UID需要续费的域名总数量
	$c_amount = $amount_one * $count; //总价格
	$pan_score_date_log->update(array('typeid'=>$typeid,'date'=>$date),array('sys_count'=>$count+$r['sys_count'],'sys_amount'=>$c_amount+$r['sys_amount']));

	$pid_c = 0;
	foreach($domain_ret as $v) {
		if($v['uid']!=$uid){
			//防止UID不相等
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			cache_a($cache_name,null); //删缓存				
			exit('domain uid != v[uid]');
		}
		$row = array('typeid'=>$typeid,'uid'=> $uid,'domain_id'=> $v['id'],'domain'=>$v['domain'],'amount'=>$amount_one,'date'=>$date);
		$pid = $pan_score_sys_log->create($row);			
		if($pid>0)$pid_c++;
	}			
	if($pid_c!=$count){
			//实际更新数据不相等
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			cache_a($cache_name,null); //删缓存				
			exit('domain pid != count');			
	}	
	$time1 = time();
	$now_time_str = date("Y-m-d H:i:s");
	if($typeid==411104){
		$expire_time = "，到期2019-01-01后";
	}	
	//**********操作入方积分记录************ begin
            //-----------查询用户账户---------
            $bal_sql = "select * from cmpai.pan_member_score where uid = $uid and typeid=$typeid FOR UPDATE"; //*****只要是查余额表的，必须前面开事务，并且后面加上 FOR UPDATE 单行锁表
            $in_member_score_result = $sp->findSql($bal_sql);		
			$new_amount = $in_member_score_result[0]['balance'] + $c_amount;
			if ($in_member_score_result) {
				$in_member_score_sql = "update cmpai.pan_member_score set balance=balance+$c_amount where uid=$uid and typeid=$typeid";
				$sp->runSql($in_member_score_sql);
			} else {
				$sp->create(array('uid' => $uid,'typeid'=>$typeid,'balance' => $c_amount));
			}			
            //准备添加积分流水相关数据,与流水表字段名相同
            $lsarr = array(
                'uid' => $uid,
                'typeid' => $typeid,
                'type' => '1',
                'amount' => $c_amount,
                'balance' => $new_amount,
                'act_ip' => 'sys',
                'create_time' => $now_time_str,
                'note' => "截至".$now_time_str."，您持有的".auto_get_name($typeid)."(只包含正常".$expire_time.")".$count."个域名，共获得".$c_amount.'积分'
            );
            //添加积分流水
			$pan_member_score_list->create($lsarr);			
        //日志
        user_log($uid,1501,get_client_ip(),'用户：'.$uid.'自动发放域名积分，持有域名品种：'.auto_get_name($typeid).'，一个'.$amount_one.'/天，一共'.$count.'个域名，共：'.$c_amount.'积分'.'，积分余额'.$new_amount);
		
	//**********操作入方积分记录************ end
	
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
	return array('status'=>200,'typeid'=>$typeid,'uid'=>$uid,'count'=>$count,"amount"=>$c_amount,'sql count'=>$pid_c);
}

//获取域名类型
function auto_get_name($typeid){
	$sp = spClass('new_ym_code');
    $ret = $sp->find(array('code'=>$typeid));
    return $ret['name'];
}














