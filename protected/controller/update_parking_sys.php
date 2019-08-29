<?php
error_reporting(E_ALL || ~E_NOTICE);
class update_parking_sys extends spController
{
    function __construct(){
        parent::__construct();
		// echo md5(date('Y-m-d H',time()).'ChaoMi-Token'); //示例:每小时变一次
		check_token($this->spArgs('token'));//验证权限token
    }	
	function create(){	
		exit;
		$typeid = intval($this->spArgs('typeid'));
		$type = 1;
		$score_type = 411104;
		if(!$typeid)exit('typeid error');
		$time_h = date('H');
		$time_i = date('i');
		// if($time_h==0){
			// if($time_i<1 || $time_i>13)exit("TIME: H=0 and i<1 and i>13");
		// }else{
			// exit('TIME: H!=0');
		// }
		//--------操作缓存处理-----begin
		$cache_name = 'cm_update_parking_sys_'.$typeid.'_type_'.$type.'_'.$score_type;
		if(false === cache_a($cache_name,time(),10))json_s(array('status'=>208,'msg'=>'操作占用中'));	
		//--------操作缓存处理-----end		
		$date = date("Y-m-d");
		$pan_parking_date_log = spClass('pan_parking_date_log'); //进度监测表
		$pan_parking = spClass('pan_parking');
		$r_c = $pan_parking->findSql("select count(*) as count from cmpai.pan_parking where status=0 and type=$type and score_type=$score_type and typeid='{$typeid}'");//到期时间待加
		$count = $r_c[0]['count']; //取出当前品种需要发放收益的域名总数量
		$r_c = $pan_parking->findSql("select sum(income) as income from cmpai.pan_parking where status=0 and type=$type and score_type=$score_type and typeid='{$typeid}'");
		$income = $r_c[0]['income']?$r_c[0]['income']:0; //取出当前品种需要发放收益的域名总收益
		$r = $pan_parking_date_log->find(array('type'=>$type,'score_type'=>$score_type,'typeid'=>$typeid,'date'=>$date));
		if(!$r){
			// 新建
			$pan_parking_date_log->create(array('type'=>$type,'score_type'=>$score_type,'typeid'=>$typeid,'date'=>$date,'count'=>$count,'income'=>$income,'sys_count'=>0,'sys_income'=>0));
		}else{
			if(!$r['push_time']){
				$pan_parking_date_log->update(array('type'=>$type,'score_type'=>$score_type,'typeid'=>$typeid,'date'=>$date),array('push_time'=>date("Y-m-d H:i:s")));
				$true_open = '错误';
				if($r['count']==$r['sys_count'])$true_open = '正常';
				//----邮件后台提醒----begin
					$content = array();
					$content['to'] = array('pwpet@qq.com');
					$content['sub'] = array('%content%'=>array('域名停放：'.auto_get_name($typeid)."，type=$type and score_type=$score_type ".'，系统操作结果：</br>【'.$true_open.'】count='.$r['count'].' sys_count='.$r['sys_count'].' income='.$r['income'].' sys_income='.$r['income']));
					$new_content = json_encode($content);
					send_mail('pwpet@qq.com','【炒米后台提醒】域名停放：'.auto_get_name($typeid).'，系统操作【'.$true_open.'】'."type=$type and score_type=$score_type ",$new_content,8);						
				//----邮件后台提醒----end				
			}
			cache_a($cache_name,null); //删缓存
			exit('typeid='.$typeid.' today is work : now_count='.$count.' count='.$r['count'].' sys_count='.$r['sys_count']);
		}
		$sql ="select distinct uid from cmpai.pan_parking where status=0 and type=$type and score_type=$score_type and typeid='{$typeid}' order by uid;";
		$list = $pan_parking->findSql($sql);
		foreach($list as $v) {
			parking_work($typeid,$v['uid'],$type,$score_type);
		}		
		cache_a($cache_name,null); //删缓存
		json_s(array('status'=>200,'typeid'=>$typeid,'count'=>$count,"list"=>$list));
	}	
}		

function parking_work($typeid,$uid,$type,$score_type){	
	if(!$uid)exit('uid error');
	if(!$typeid)exit('typeid error');
	if(!$type)exit('type error');
	if(!$score_type)exit('score_type error');
	//--------操作缓存处理-----begin
	$cache_name = 'cm_update_parking_sys_uid_'.$uid.'_typeid_'.$typeid;
	if(false === cache_a($cache_name,time(),20))json_s(array('status'=>208,'msg'=>'操作占用中'));	
	
	//------------限制用户并发请求操作域名相关----------begin
	$domain_action_uid = 'domain_action_uid_'.$uid;
	if(false === cache_a($domain_action_uid,time(),20))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
	//------------限制用户并发请求操作域名相关----------end			
	
	//--------操作缓存处理-----end		
	$date = date("Y-m-d");
	$pan_parking_date_log = spClass('pan_parking_date_log'); //进度监测表
	$pan_parking = spClass('pan_parking');
	$pan_parking_sys_log = spClass('pan_parking_sys_log'); //具体记录
	$r = $pan_parking_date_log->find(array('typeid'=>$typeid,'date'=>$date));
	if(!$r){
		//判断此选项当日是否创建进度记录？
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		cache_a($cache_name,null); //删缓存				
		exit('pan_parking_date_log null');
	}
	if($pan_parking_sys_log->findCount(array('type'=>$type,'score_type'=>$score_type,'typeid'=>$typeid,'date'=>$date,'uid'=>$uid))>0){
		//判断此选项是否有发放过记录？
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			cache_a($cache_name,null); //删缓存				
			exit('pan_parking_sys_log type+score_type+typeid+date+uid Count >0');					
	}		
	$sp = spClass('pan_member_score');
	$sql_sw = false;
	$sp->runSql("SET AUTOCOMMIT=0");	
	$sp->runSql('BEGIN'); //开启事务	
	
	//查出具体的停放单列表数组，及总数
	$parking_ret = $pan_parking->findSql("select id,uid,income,type,score_type,typeid,domain,domain_id from cmpai.pan_parking where uid={$uid} and status=0 and type=$type and score_type=$score_type and typeid={$typeid} FOR UPDATE"); //详情的停放列表 单个列表
	$parking_count = $pan_parking->findCount(array('type'=>$type,'score_type'=>$score_type,'uid'=>$uid,'typeid'=>$typeid,'status'=>0));
	$r_c = $pan_parking->findSql("select count(*) as count from cmpai.pan_parking where status=0 and type=$type and score_type=$score_type and typeid='{$typeid}' and uid={$uid}");
	$count = $r_c[0]['count']; //取出当前品种当前UID需要发放收益的域名总数量
	$r_c = $pan_parking->findSql("select sum(income) as income from cmpai.pan_parking where status=0 and type=$type and score_type=$score_type and typeid='{$typeid}' and uid={$uid}");
	$income = $r_c[0]['income']; //取出当前品种当前UID需要发放收益的域名总收益	
	$pan_parking_date_log->update(array('type'=>$type,'score_type'=>$score_type,'typeid'=>$typeid,'date'=>$date),array('sys_count'=>$count+$r['sys_count'],'sys_income'=>$income+$r['sys_income']));
	$pid_c = 0;
	foreach($parking_ret as $v) {
		if($v['uid']!=$uid){
			//防止UID不相等
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			cache_a($cache_name,null); //删缓存				
			exit('parking uid != v[uid]');
		}
		$row = array(
				'type'=>$type,
				'score_type'=>$score_type,
				'typeid'=>$typeid,
				'uid'=> $uid,
				'parking_id'=>$v['id'],
				'income'=>$v['income'],
				'date'=>$date,
				'domain'=>$v['domain'],
				'domain_id'=>$v['domain_id']
			);
		$pid = $pan_parking_sys_log->create($row);			
		if($pid>0)$pid_c++;
	}			
	if($pid_c!=$parking_count){
			//实际更新数据不相等
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			cache_a($cache_name,null); //删缓存				
			exit('parking pid != parking_count');			
	}	
	$now_time_str = date("Y-m-d H:i:s");
	//**********发放收益************ begin
		//*****--------------操作入方相关处理-----begin
		//-----------查询用户账户---------
		$amount = $income;
		$touid = $uid;
		$bal_sql = "select * from cmpai.pan_member_score where uid = $touid and typeid=$score_type FOR UPDATE"; //*****只要是查余额表的，必须前面开事务，并且后面加上 FOR UPDATE 单行锁表
		$in_member_score_result = $sp->findSql($bal_sql);		
		$new_amount = $in_member_score_result[0]['balance'] + $amount;
		if ($in_member_score_result) {
			$in_member_score_sql = "update cmpai.pan_member_score set balance=balance+$amount where uid=$touid and typeid=$score_type";
			$sp->runSql($in_member_score_sql);
		} else {
			$sp->create(array('uid' => $touid,'typeid'=>$score_type,'balance' => $amount));
		}			
		//准备添加积分流水相关数据,与流水表字段名相同
		$lsarr = array(
			'uid' => $touid,
			'typeid' => $score_type,
			'type' => '3',
			'amount' => $amount,
			'balance' => $new_amount,
			'act_ip' => 'sys',
			'create_time' => $now_time_str,
			'note' => "截至".$now_time_str."，您停放的".auto_get_name($typeid)."域名，共".$parking_count."个，共获得".$amount."积分"
		);
		//添加积分流水
		$pan_member_score_list = spClass('pan_member_score_list');
		$pan_member_score_list->create($lsarr);			
		//*****--------------操作入方相关处理-----end
        //日志
        user_log($uid,1301,get_client_ip(),'【域名停放】用户：'.$uid.' 自动发放域名停放收益('."type=$type and score_type=$score_type".')，域名品种：'.auto_get_name($typeid).'，共'.$parking_count.'个域名，共发放收益：'.$income.'，积分余额'.$new_amount);
		
	//**********发放收益************ end
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
	return array('status'=>200,'typeid'=>$typeid,'uid'=>$uid,'count'=>$count,"price"=>$c_price,'sql count'=>$pid_c);
}
//获取域名类型
function auto_get_name($typeid){
	$sp = spClass('new_ym_code');
    $ret = $sp->find(array('code'=>$typeid));
    return $ret['name'];
}














