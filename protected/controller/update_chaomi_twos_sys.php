<?php
error_reporting(E_ALL || ~E_NOTICE);
class update_chaomi_twos_sys extends spController
{
    function __construct(){
        parent::__construct();
		// echo md5(date('Y-m-d H',time()).'ChaoMi-Token'); //示例:每小时变一次
		check_token($this->spArgs('token'));//验证权限token
    }	
       
	function create(){
                exit;
		$typeid = intval($this->spArgs('typeid'));
		$tg_typeid_data = array(808001);
		if(!in_array($typeid,$tg_typeid_data)){
			exit('typeid not...');
		}
		$time_h = date('H');
		$time_i = date('i');
		if($time_h==0){
			if($time_i<1 || $time_i>9)exit("TIME: H=0 and i<1 and i>8");
		}else{
			exit('TIME: H!=0');
		}
                
		//--------操作缓存处理-----begin
		$cache_name = 'cm_update_domain_twos_sys_'.$typeid;
		if(false === cache_a($cache_name,time(),10))json_s(array('status'=>208,'msg'=>'操作占用中'));	
		//--------操作缓存处理-----end		
		$date = date("Y-m-d");
		$pan_domain_in = spClass('pan_domain_in'); //域名实盘米
		$pan_domain_twos_date_log = spClass('pan_domain_twos_date_log'); //进度监测表
		$r = $pan_domain_twos_date_log->find(array('typeid'=>$typeid,'date'=>$date));
		$typeid_cond_sql = "typeid={$typeid}";
		if($typeid==808001){
			$typeid_cond_sql = 'typeid in (808001,808002,808008)';
		}
		$skip_uid =array(); // array(19668,19639);//跳过指定的UID
		if(!$r){
			$sql ="select distinct uid from cmpai.pan_domain_in where $typeid_cond_sql and expire_time >= '{$date}' order by uid;";
			$list = $pan_domain_in->findSql($sql);
			$count = 0;
			$tg_count = 0;
			foreach($list as $v) {
				$c = $pan_domain_in->findCount(" $typeid_cond_sql and uid=".$v['uid']." and expire_time >= '{$date}'"); //域名总数量
				if(in_array($v['uid'],$skip_uid)){
					$c = 0;
				}
				$count = $count + $c;
				$_tg_count = get_income_typeid($typeid,$c);
				$tg_count = $tg_count + $_tg_count;
			}					
			// 新建
			$pan_domain_twos_date_log->create(array('typeid'=>$typeid,'date'=>$date,'count'=>$count,'tg_count'=>$tg_count));
		}else{
			cache_a($cache_name,null); //删缓存
			if(!$r['push_time']){
				$pan_domain_twos_date_log->update(array('typeid'=>$typeid,'date'=>$date),array('push_time'=>date("Y-m-d H:i:s")));
				$true_open = '错误';
				if($r['tg_count']==$r['sys_tg_count'])$true_open = '正常';
				//----邮件后台提醒----begin
					$content = array();
					$content['to'] = array('pwpet@qq.com');
					$content['sub'] = array('%content%'=>array('品种糖果：'.auto_get_name($typeid).'，系统操作结果：</br>【'.$true_open.'】count='.$r['count'].' sys_count='.$r['sys_count'].' tg_count='.$r['tg_count'].' sys_tg_count='.$r['sys_tg_count']));
					$new_content = json_encode($content);
					send_mail('pwpet@qq.com','【炒米后台提醒】【'.$true_open.'】品种糖果：'.auto_get_name($typeid).'，系统操作【'.$true_open.'】',$new_content,8);						
				//----邮件后台提醒----end				
			}
			exit('typeid='.$typeid.' today is work : now_count='.$count.' count='.$r['count'].' sys_count='.$r['sys_count']);
		}
		$sql ="select distinct uid from cmpai.pan_domain_in where $typeid_cond_sql and expire_time >= '{$date}' order by uid;";
		$list = $pan_domain_in->findSql($sql);
		foreach($list as $v) {
			if(in_array($v['uid'],$skip_uid)){
				//备注：跳过指定UID
			}else{
				domain_twos_work($typeid,$v['uid']);
			}			
		}		
		cache_a($cache_name,null); //删缓存
		json_s(array('status'=>200,'typeid'=>$typeid,'count'=>$count,"list"=>$list));
	}	
		
}

function domain_twos_work($typeid,$uid){	

	if(!$uid)exit('uid error');
	if(!$typeid)exit('typeid error');
	//--------操作缓存处理-----begin
	$cache_name = 'cm_update_domain_twos_sys_uid_'.$uid.'_typeid_'.$typeid;
	if(false === cache_a($cache_name,time(),20))json_s(array('status'=>208,'msg'=>'操作占用中'));	
	
	//------------限制用户并发请求操作域名相关----------begin
	$domain_action_uid = 'domain_action_uid_'.$uid;
	if(false === cache_a($domain_action_uid,time(),20))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
	//------------限制用户并发请求操作域名相关----------end			
	
	//--------操作缓存处理-----end		
	$date = date("Y-m-d");
	$pan_domain_in = spClass('pan_domain_in'); //域名实盘米
	$pan_domain_twos_date_log = spClass('pan_domain_twos_date_log'); //进度监测表
	$pan_domain_twos_sys_log = spClass('pan_domain_twos_sys_log'); //每个域名当日赠送具体

	$r = $pan_domain_twos_date_log->find(array('typeid'=>$typeid,'date'=>$date));
	if(!$r){
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		cache_a($cache_name,null); //删缓存				
		exit('pan_domain_twos_date_log null');
	}
	if($pan_domain_twos_sys_log->findCount(array('typeid'=>$typeid,'date'=>$date,'uid'=>$uid))>0){
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			cache_a($cache_name,null); //删缓存				
			exit('pan_domain_twos_sys_log typeid+date+uid Count >0');					
	}	
	$typeid_cond_sql = "typeid={$typeid}";
	if($typeid==808001){
		$typeid_cond_sql = 'typeid in (808001,808002,808008)';
	}	
	$domain_count = $pan_domain_in->findCount(" $typeid_cond_sql and uid=$uid and expire_time >= '{$date}'"); //域名持有总数量 
	$tg_count = get_income_typeid($typeid,$domain_count);
	$pan_domain_twos_date_log->update(array('typeid'=>$typeid,'date'=>$date),array('sys_count'=>$domain_count+$r['sys_count'],'sys_tg_count'=>$tg_count+$r['sys_tg_count']));
	
	$tg_typeid = 200001; //糖果品种ID
	$pt_uid = 19668;// 100帐号
	if($tg_count==0){
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		cache_a($cache_name,null); //删缓存				
		echo 'uid='.$uid.',tg_count=0';
		return;
	}
	$sp = spClass('pan_domain_in');
	$sql_sw = false;
	$sp->runSql("SET AUTOCOMMIT=0");	
	$sp->runSql('BEGIN'); //开启事务
	$now_time_str = date("Y-m-d H:i:s");
	
	$row = array('typeid'=>$typeid,'tg_typeid'=>$tg_typeid,'uid'=> $uid,'domain_count'=>$domain_count,'tg_count'=>$tg_count,'date'=>$date); //赠送记录
	$pan_domain_twos_sys_log->create($row);			

	//域名从pt_uid过户到当前用户
	$sql = "select id,domain from cmpai.pan_domain_in where typeid=$tg_typeid and uid = $pt_uid and locked=0 order by id asc LIMIT 0,$tg_count";
	$domain_ret = $pan_domain_in->findSql($sql);
	foreach($domain_ret as $v) {
		$new_arr[]=(int)$v['id'];
	}
	$domain_ids = implode(',',$new_arr);
	if(!$new_arr){
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		cache_a($cache_name,null); //删缓存				
		exit('pt_uid='.$pt_uid.'----error:tg_count=0');				
	}
	//---将域名更新过户
	$pan_domain_in->update("uid=$pt_uid and locked=0 and id in ($domain_ids)",array('uid'=>$uid,'upd_time'=>$now_time_str));
	$update_domain_row = $pan_domain_in->affectedRows(); //影响行数
	if($update_domain_row!=$tg_count){
			//实际更新数据不相等
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			cache_a($cache_name,null); //删缓存				
			exit('update_domain_row != count');			
	}
	user_log($uid,2001,get_client_ip(),'【域名发放】用户：'.$uid.' 自动发放域名记录，域名品种：'.auto_get_name($typeid).'，共'.$domain_count.'个域名，共发放糖果：'.$tg_count.'个，糖果typeid='.$tg_typeid);

	//---添加站内短信---begin
	$type =  '901';
	$tit  =  '持有'.auto_get_name($typeid).'，共'.$domain_count.'个域名，获取奖励：'.$tg_count.'个'.auto_get_name($tg_typeid);
	$txt  =  '【'.$date.'】当日奖励：持有'.auto_get_name($typeid).'，共'.$domain_count.'个域名，获取奖励：'.$tg_count.'个'.auto_get_name($tg_typeid);
	web_msg_send($tit,$type,$uid,$txt);							
	//---添加站内短信---end	
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
	return array('status'=>200,'typeid'=>$typeid,'uid'=>$uid,'count'=>$count,"tg_count"=>$tg_count);
}
//获取域名类型
function auto_get_name($typeid){
    if($typeid==808001){
        return '四声母COM.CN';  // 四声长尾下的所有交易品种都参与分糖果，比例都一样
    }else{
        $ret = check_pz($typeid);
        return $ret[0]['name'];
    }
}
//获取按品种赠送比例
function get_income_typeid($typeid,$count){
		//临时处理
		$tg_count = 0;
		if($typeid==808001){
			$tg_count = intval(($count / 100)) * 1;
		}
//		if($typeid==808008){
//			$tg_count = intval(($count / 10)) * 1;
//		}	
		return $tg_count;
}













