<?php
error_reporting(E_ALL || ~E_NOTICE);
class update_income_sys extends spController
{ 
    function __construct(){
        parent::__construct();
		// echo md5(date('Y-m-d H',time()).'ChaoMi-Token'); //示例:每小时变一次
		check_token($this->spArgs('token'));//验证权限token
    }	
    function create(){	
	//收益分成（平台交易费分成）
        exit;
	$typeid = intval($this->spArgs('typeid'));
	$tg_typeid_data = array(200001); //炒米二级域名编码
	if(!in_array($typeid,$tg_typeid_data)){
            exit('typeid not...');
	}
	$time_h = date('H');
	$time_i = date('i');
	if($time_h==0){
            if($time_i<10 || $time_i>20)exit("TIME: H=0 and i<10 and i>20");
	}else{
            exit('TIME: H!=0');
	}        
        $date = date("Y-m-d");
        $date_before = date("Y-m-d",strtotime( "-1 day"));
	$cache_name = 'cm_update_income_sys';
	if(false === cache_a($cache_name,time(),10))json_s(array('status'=>208,'msg'=>'操作占用中'));	
        $pan_income_date_log = spClass('pan_income_date_log'); //进度监测表
	$pan_deal_trade = spClass('pan_deal_trade');
        $pan_domain_in = spClass('pan_domain_in');
        $r_c = $pan_domain_in->findSql("select count(*) as count from cmpai.pan_domain_in where typeid={$typeid} and uid != 19668");
        $count = $r_c[0]['count']; //取出需要发放收益的域名总数量
	$r_log = $pan_income_date_log->find(array('typeid'=>$typeid,'date'=>$date));
	if(!$r_log){            
            $r_deal = $pan_deal_trade->findSql("select sum(tot_price) as price from pan_deal_trade where sta = 0 and deal_time > '$date_before' and deal_time < '$date'");
            $tot_price = $r_deal[0]['price']; //成交总额
            $income = $tot_price * sRate;  //交易费收益总额
            $tg_count = floor($income / 5000000 * 10000) /10000 * $count;
            // 新建
            $pan_income_date_log->create(array('typeid'=>$typeid,'date'=>$date,'income'=>$income,'count'=>$count,'tg_count'=>$tg_count,'sys_count'=>0,'sys_tg_count'=>0));
	}else{
            if(!$r_log['push_time']){
		$pan_income_date_log->update(array('typeid'=>$typeid,'date'=>$date),array('push_time'=>date("Y-m-d H:i:s")));
		$true_open = '错误';
		if($r_log['count']==$r_log['sys_count'])$true_open = '正常';
                    //----邮件后台提醒----begin
                    $content = array();
                    $content['to'] = array('pwpet@qq.com');
                    $content['sub'] = array('%content%'=>array('交易费分红：'.auto_get_name($typeid).'，系统操作结果：</br>【'.$true_open.'】count='.$r_log['count'].' sys_count='.$r_log['sys_count'].' tg_count='.$r_log['tg_count'].' sys_tg_count='.$r_log['sys_tg_count']));
                    $new_content = json_encode($content);
                    send_mail('pwpet@qq.com','【炒米后台提醒】交易费分红：'.auto_get_name($typeid).'，系统操作【'.$true_open.'】',$new_content,8);						
                    //----邮件后台提醒----end				
            }
            cache_a($cache_name,null); //删缓存
            exit('typeid='.$typeid.' today is work : now_count='.$count.' count='.$r_log['count'].' sys_count='.$r_log['sys_count']);
        }	
	$sql ="select distinct uid from cmpai.pan_domain_in where typeid={$typeid} and uid != 19668  order by uid;";
	$list = $pan_domain_in->findSql($sql);
	foreach($list as $v) {
            income_work($typeid,$v['uid']);	
	}		
	cache_a($cache_name,null); //删缓存
	json_s(array('status'=>200,'typeid'=>$typeid,'count'=>$count,"list"=>$list));
    }		
}
function income_work($typeid,$uid){	
	if(!$uid)exit('uid error');
	if(!$typeid)exit('typeid error');
	//--------操作缓存处理-----begin
	$cache_name = 'cm_update_income_uid_'.$uid.'_typeid_'.$typeid;
	//if(false === cache_a($cache_name,time(),20))json_s(array('status'=>208,'msg'=>'操作占用中'));
        //--------操作缓存处理-----end	
	$date = date("Y-m-d");
	$pan_income_date_log = spClass('pan_income_date_log'); //进度监测表  
        $pan_domain_in = spClass('pan_domain_in');
        $pan_member_income_log = spClass('pan_member_income_log'); 
        
	$r = $pan_income_date_log->find(array('typeid'=>$typeid,'date'=>$date));
	if(!$r){
		cache_a($cache_name,null); //删缓存				
		exit('pan_income_date_log null');
	}
        //判断当天是否发放收益
	if($pan_member_income_log->findCount(array('type'=>1,'date'=>$date,'uid'=>$uid))>0){
		//判断此选项是否有发放过记录？
		cache_a($cache_name,null); //删缓存				
		exit('pan_member_income_log type+date+uid Count >0');					
	} 
	$sp = spClass('pan_member_income');
	$sql_sw = false;
	$sp->runSql("SET AUTOCOMMIT=0");	
	$sp->runSql('BEGIN'); //开启事务
        
        $r_c = $pan_domain_in->findSql("select count(*) as count from cmpai.pan_domain_in where typeid={$typeid} and uid={$uid} ");
        $count = $r_c[0]['count']; //取出当前UID域名总数量
        $income = $r['income'];//取出待分红的总交易费
        $tg_count = floor($income / 5000000 * 10000) /10000 * $count; 
	$pan_income_date_log->update(array('typeid'=>$typeid,'date'=>$date),array('sys_count'=>$count+$r['sys_count'],'sys_tg_count'=>$tg_count+$r['sys_tg_count']));
	//**********发放收益************ begin
		//*****--------------操作入方相关处理-----begin
		//-----------查询用户账户---------
		$amount = $tg_count;
		$bal_sql = "select * from cmpai.pan_member_income where uid = $uid FOR UPDATE"; 
                $in_member_income_result = $sp->findSql($bal_sql);		
		$new_amount = $in_member_income_result[0]['balance'] + $amount;
		if ($in_member_income_result) {
			$in_member_income_sql = "update cmpai.pan_member_income set balance=balance+$amount where uid=$uid ";
			$sp->runSql($in_member_income_sql);
		} else {
			$sp->create(array('uid' => $uid,'balance' => $amount));
		}	
                $now_time_str = date("Y-m-d H:i:s");
		//添加流水相关数据
		$lsarr = array(
			'uid' => $uid, 
			'type' => 1, //收益分红
			'amount' => $amount,
			'balance' => $new_amount,
                        'date' => $date ,
			'act_ip' => 'sys', 
			'note' => "截至".$now_time_str."，您持有的".auto_get_name($typeid)."，共".$count."个，共获得".$tg_count."交易费分红"
		);
		//添加流水 
		$pan_member_income_log->create($lsarr);			
		//*****--------------操作入方相关处理-----end
        //日志
        user_log($uid,1302,get_client_ip(),'【域名持有】用户：'.$uid.' 自动发放域名持有分红，域名品种：'.auto_get_name($typeid).'，共'.$count.'个域名，共发放收益：'.$tg_count.'，收益余额'.$new_amount);
		
	//**********发放收益************ end
	$sql_sw = true;
	if(false===$sql_sw){
		$sp->runSql('ROLLBACK'); //回滚事务
		cache_a($cache_name,null); //删缓存
		json_s(array('status'=>205,'msg'=>'系统事务出错，请稍候重试。'));
	}else{
		$sp->runSql('COMMIT'); //提交事务
		cache_a($cache_name,null); //删缓存
	}
	return array('status'=>200,'typeid'=>$typeid,'uid'=>$uid,'count'=>$count,"tg_count"=>$tg_count,);
}
//获取域名类型
function auto_get_name($typeid){
    $ret = check_pz($typeid);
    return $ret[0]['name'];
}











