<?php
/*
 * 域名停放模块
 *
 */
define("web_md5", "_chaomi_cc"); 
class parking extends spController{
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
    function apply(){
		//申请域名停放
		$uid = $this->uid;
		$ip = get_client_ip();
		$now_time = time();
		$now_time_str = date("Y-m-d H:i:s");
        $new_ym_code = spClass('new_ym_code');
		$pan_parking_config = spClass('pan_parking_config'); // 停放配置表
		$pan_domain_in = spClass('pan_domain_in'); // 域名实盘表
        $pan_parking = spClass('pan_parking'); // 停放数据表
		$from = $this->spArgs('from'); 
		
		$typeid = intval($this->spArgs('typeid')); //品种ID	
		$cycle_time = intval($this->spArgs('cycle_time')); //时间周期
		$parking_count = intval($this->spArgs('parking_count')); //停放个数
		if($from=='config'){
			$ret = $pan_parking_config->find(array('type'=>1,'score_type'=>411104,'typeid'=>$typeid,'cycle_time'=>$cycle_time));
			if(!$ret['income']){
				json_s(array('status'=>201,'msg'=>'当前品种暂未开放域名停放服务'));
			}
			if($typeid==411104){
				$expire_time = '2019-01-01';
				$cond_expire_time_sql = "expire_time >= '$expire_time'";
				$_expire_time = date("Y-m-d",strtotime("+$cycle_time day"));
				if($_expire_time>$expire_time){
					$cond_expire_time_sql = "expire_time >= '$_expire_time'";
				}
			}	
			$kt_count = $ret['count'] - $ret['sys_count'];
			$domain_list = $pan_domain_in->findSql("select id,domain,expire_time,pingtai,locked from cmpai.pan_domain_in where typeid=$typeid and uid =$uid and locked=0 and $cond_expire_time_sql order by expire_time asc LIMIT 0,5"); //持有品种列表
			json_s(array('status'=>200,'typeid'=>$typeid,'name'=>$ret['name'],'domain_list'=>$domain_list,'income'=>$ret['income'],'cycle_time'=>$ret['cycle_time'],'kt_count'=>$kt_count));
		}
		if($from=='create'){
			if(time()<1515589200){
				if($uid!=1)json_s(array('status'=>201,'msg'=>'域名停放将于 2018年1月10日 21:00 开放'));
			}
			//验证是否实名
			$member_info = spClass('pan_member_card')->find(array('uid' => $uid));
			$now_uid_name = $member_info['first_name'].$member_info['last_name'];
			// if(!$now_uid_name)json_s(array('status'=>201,'msg'=>'请实名认证后再操作停放'));
			
			$ret_con = $pan_parking_config->find(array('type'=>1,'score_type'=>411104,'typeid'=>$typeid,'cycle_time'=>$cycle_time));
			if(!$ret_con['income']){
				json_s(array('status'=>201,'msg'=>'当前品种暂未开放域名停放服务'));
			}
			if($typeid==411104){
				$expire_time = '2019-01-01';
				$cond_expire_time_sql = "expire_time >= '$expire_time'";
				$_expire_time = date("Y-m-d",strtotime("+$cycle_time day"));
				if($_expire_time>$expire_time){
					$cond_expire_time_sql = "expire_time >= '$_expire_time'";
				}
			}			
			//---获取域名ID数组----begin
			$id = $this->spArgs('id');		
			if(is_array($id)){
				$parking_count = count($id);
				//务必强制转换成数值类型---begin
				foreach($id as $v){
					$new_arr[]=(int)$v;
				}
				//务必强制转换成数值类型---end
				$ids = implode(',',$new_arr);
				$ids_arr = $new_arr;
			}else{
				json_s(array('status'=>201,'msg'=>'您还没有选择任何域名'));
			}			
			//---获取域名ID数组----end
			
			//------------限制用户并发请求操作停放相关----------begin
			$parking_action_all = 'parking_action_all_type_'.$typeid.'_'.$cycle_time;
			if(false === cache_a($parking_action_all,time(),2))json_s(array('status'=>205,'msg'=>'很抱歉，('.$ret_con['name'].'停放'.$cycle_time.'天周期)系统队列较繁忙，请重试。'));	
			//------------限制用户并发请求操作停放相关----------end	
			
			$conditions = "uid=$uid and locked=0 and typeid=$typeid and id in(".$ids.") and $cond_expire_time_sql";
			$domain_count = $pan_domain_in->findCount($conditions);
			$_domain_count = $pan_domain_in->findCount("uid=$uid and typeid=$typeid");//当前用户持有品种的数量
			$_pan_parking = $pan_parking->findCount("uid=$uid and typeid=$typeid and status=0");//当前用户品种正在停放的数量
			if($domain_count!=$parking_count){
				json_s(array('status'=>201,'msg'=>'提示：操作失败，选中的域名与实际不符'));
			}			
			$kt_count = $ret_con['count'] - $ret_con['sys_count'];
			if($parking_count>$domain_count){
				json_s(array('status'=>201,'msg'=>'提示：停放域名个数不能大于当前可用域名数，可用：'.$domain_count.'个'));
			}			
			if($parking_count>$kt_count){
				json_s(array('status'=>201,'msg'=>'提示：停放域名个数不能大于当前剩余可停放域名数，可停数：'.$kt_count.'个'));
			}
			$_parking_count = $_domain_count/2-$_pan_parking;
			// if($parking_count>$_parking_count){
				// json_s(array('status'=>201,'msg'=>'提示：停放域名个数不能大于品种持仓的50%，可停数：'.$_parking_count.'个'));
			// }			
			if($parking_count==0){
				json_s(array('status'=>201,'msg'=>'提示：域名参数出错'));
			}	
			if($parking_count>5){
				json_s(array('status'=>201,'msg'=>'提示：当前开放每次只可申请5个域名停放'));
			}			
			//处理安全码
			$pw = trim($this->spArgs('safecode'));
			if(empty($pw))json_s(array('status'=>201,'msg'=>'交易密码不能为空'));
			$pw = md5(md5($pw . web_md5)); //双重md5加密			
			$pws = spClass('pan_user_safecode')->find(array('uid' => $uid));//密码获取代码
			//------------限制帐号请求验证安全码次数----------begin
			$key_safeCode_name = 'parking_safeCode_uid_'.$uid;
			if(cache_s($key_safeCode_name)>30)json_s(array('status'=>205,'msg'=>'很抱歉，交易密码验证请求次数限制，请稍后1小时后再操作'));				
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
			
			//------------限制用户并发请求操作域名相关----------begin
			$domain_action_uid = 'domain_action_uid_'.$uid;
			if(false === cache_a($domain_action_uid,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
			//------------限制用户并发请求操作域名相关----------end	
			
			$sp = spClass('pan_parking');
			$sql_sw = false;
			$sp->runSql("SET AUTOCOMMIT=0");
			$sp->runSql('BEGIN'); //开启事务
			
			//先更新可用数量
			$pan_parking_config->update(array('type'=>1,'score_type'=>$ret_con['score_type'],'typeid'=>$typeid,'cycle_time'=>$ret_con['cycle_time']),array('sys_count'=>$ret_con['sys_count']+$parking_count));
			
			//-----按比例调用停放-----begin
			$__count = 0;
			if($cycle_time==180){
				// $__count = $parking_count*rand(1,3);
			}
			if($cycle_time==360){
				// $__count = $parking_count*rand(6,8);
			}	
			parking_auto(19641,$typeid,$cycle_time,$__count,$uid,$parking_count);
			//-----按比例调用停放-----end	
			
			$end_time = date("Y-m-d", strtotime("+".$ret_con['cycle_time']." day", strtotime($now_time_str)));
			$parking_row = array();
			foreach($ids_arr as $v) {
				$domain = '';
				$_r = $pan_domain_in->find(array('id'=>$v));
				$domain = $_r['domain'];
				if(!$domain)json_s(array('status'=>201,'msg'=>"错误提示：域名id：$v 获取不到详情域名"));
				$row = array(
						'uid'=>$uid,
						'type'=>1,
						'score_type'=>411104,
						'typeid'=>$typeid,
						'create_time'=>$now_time_str,
						'income'=>$ret_con['income'],
						'cycle_time'=>$ret_con['cycle_time'],
						'end_time'=>$end_time,
						'domain'=>$domain,
						'domain_id'=>$v,
						'act_ip'=>$ip,
						'status'=>0,
						'act_note'=>'['.$now_time_str.'提交停放成功]'
				);
				$parking_id = $pan_parking->create($row);
				if($parking_id>0)$parking_row[] = $parking_id;
			}
			if($parking_count!=count($parking_row)){
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
				json_s(array('status'=>300,'msg'=>'错误提示：系统创建停放域名数量与实际不符'));				
			}				
			//---将域名更新状态：停放中
			$pan_domain_in->update("uid=$uid and locked=0 and id in ($ids)",array('locked'=>9,'upd_time'=>$now_time_str));
			$update_domain_row = $pan_domain_in->affectedRows(); //影响行数
			
			if($update_domain_row!=$parking_count){
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
				json_s(array('status'=>300,'msg'=>'错误提示：系统更新域名数量与实际不符'));				
			}	
			user_log($uid, 1503, $ip, "【用户".$uid."】：".$ret_con['name']."域名停放，每日每个".$ret_con['income']." 类型：积分 ，停放周期".$ret_con['cycle_time']."，域名ID列表: $ids , 停放ID列表：".implode(',',$parking_row));
			
			//---添加站内短信---begin
			$type =  '901';
			$tit  =  '恭喜，您已成功停放'.$parking_count.'个'.$ret_con['name'].'域名';
			$txt  =  "您于".$now_time_str.'成功停放'.$parking_count.'个'.$ret_con['name'].'域名，定期收益：'.$ret_con['income'].'积分/天/个，停放周期：'.$ret_con['cycle_time'].'天。';
			web_msg_send($tit,$type,$uid,$txt);							
			//---添加站内短信---end	
			send_mobile_email($uid,"炒米网(chaomi.cc)域名停放提醒","您于".$now_time_str.'成功停放'.$parking_count.'个'.$ret_con['name'].'域名，定期收益：'.$ret_con['income'].'积分/天/个，停放周期：'.$ret_con['cycle_time'].'天。');

			$sql_sw = true;
			if(false===$sql_sw){
				$sp->runSql('ROLLBACK'); //回滚事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'系统处理出错，已回滚事务。'));				
			}else{
				$sp->runSql('COMMIT'); //提交事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
				json_s(array('status'=>200,'msg'=>'已成功停放'.$number.'个'.$ret_con['name'].'域名'));	
			}		
		}		
        //---前端选择域名类型---begin
        $new_ym_code_r = $new_ym_code->findAll('state=1');		
        //---前端选择域名类型---end
        // $this->type_options = $new_ym_code_r; //品种列表
        $this->type_options = array(array('name'=>'四声母COM.CN','code'=>411104)); //品种列表
        $this->module = "parking";
        $this->act = 'parking_apply';
        $this->display('amui/parking/apply.html');
    }
    function applyList(){
		//域名停放列表
		$uid = $this->uid;
		$page = intval($this->spArgs('page', 1));
		if($page<=0)$page=1;		
		$pan_parking = spClass('pan_parking');
		
        $condition = " where uid=$uid ";
        $cond=array('domain'=>"",'typeid'=>'');
		// 0=停放中 1=已停放完成
		$status_arr = array(1=>'停放中',2=>'已停放完成');
		$cycle_time_arr = array(180=>'180天',360=>'360天');
        //域名**********模糊查询************
        if(false !=$this->spArgs('domain')){
            $domain=$pan_parking->escape($this->spArgs('domain'));
            $condition.=" and domain like '%".trim($domain,"'")."%' ";
            $cond['domain']=trim($domain,"'");
        }
        //域名品种
        if(false !=$this->spArgs('typeid')){
            $typeid=intval($this->spArgs('typeid'));
            $condition.=" and typeid=".$typeid." ";
            $cond['typeid']=trim($typeid,"'");
        }	
        //交易状态
        if(false !=$this->spArgs('status')){
            $status=intval($this->spArgs('status'));
			if($status==1){
				$condition.=" and status=0";
			}
			if($status==2){
				$condition.=" and status=1";
			}			
            $cond['status']=trim($status,"'");
        }	
        //停放周期
        if(false !=$this->spArgs('cycle_time')){
            $cycle_time=intval($this->spArgs('cycle_time'));
            $condition.=" and cycle_time=".$cycle_time." ";
            $cond['cycle_time']=trim($cycle_time,"'");
        }	
		
        $sort = " ORDER BY id desc";
        $sql = "select * from cmpai.pan_parking ".$condition.$sort ;				
		$ret = $pan_parking->spPager($page, pgsize)->findSql($sql);
		//分页开始
        $pager = $pan_parking->spPager()->getPager();
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
		foreach($ret as $v) {
			$r = $new_ym_code->spCache(3600)->find(array('code'=>$v['typeid']));
			$v['name'] = $r['name'];
			$tmp = $pan_parking_sys_log->findSql("select sum(income) as income from cmpai.pan_parking_sys_log where parking_id=".$v['id']);
			$v['income_c_now'] = $tmp[0]['income']?$tmp[0]['income']:0;
			$tmp = $pan_parking_sys_log->findSql("select count(id) as count from cmpai.pan_parking_sys_log where parking_id=".$v['id']);
			$v['time_c_now'] = $tmp[0]['count']?$tmp[0]['count']:0;	
			$v['create_time'] = date("Y-m-d", strtotime($v['create_time']));
			$v['end_time'] = date("Y-m-d", strtotime($v['end_time']));
			$v['name'] = $v['name'];
			if($v['status']==0){
				$_r = spClass('pan_domain_ykj')->find(array('domain_id'=>$v['domain_id'],'status'=>1));
				$v['is_ykj'] = 1;	
				if(!$_r)$v['is_ykj'] = 0;				
			}
			$ret_[] = $v;
		}		
        $dlist = "select code as id,name from cmpai.new_ym_code where state=1";
        $types = $new_ym_code->spCache(3600)->findSql($dlist);			
        $this->types = $types;	
		$this->cond=$cond;
		$this->status_arr=$status_arr;		
		$this->cycle_time_arr=$cycle_time_arr;		
        $this->ret = $ret_;
        $this->module = "parking";
        $this->act = 'parking_applyList';
        $this->display('amui/parking/applyList.html');		
    }
}
function parking_auto($uid,$typeid,$cycle_time,$parking_count,$uid_from,$uid_from_count){
		if($parking_count==0)return;
		$ip = get_client_ip();
		$now_time = time();
		$now_time_str = date("Y-m-d H:i:s");	
		$new_ym_code = spClass('new_ym_code');
		$pan_parking_config = spClass('pan_parking_config'); // 停放配置表
		$pan_domain_in = spClass('pan_domain_in'); // 域名实盘表
		$pan_parking = spClass('pan_parking'); // 停放数据表	
		$ret_con = $pan_parking_config->find(array('type'=>1,'score_type'=>411104,'typeid'=>$typeid,'cycle_time'=>$cycle_time));
		if(!$ret_con['income']){
			json_s(array('status'=>201,'msg'=>'当前品种暂未开放域名停放服务'));
		}	
		if($typeid==411104){
			$expire_time = '2019-01-01';
			$cond_expire_time_sql = "expire_time >= '$expire_time'";
			$_expire_time = date("Y-m-d",strtotime("+$cycle_time day"));
			if($_expire_time>$expire_time){
				$cond_expire_time_sql = "expire_time >= '$_expire_time'";
			}
		}	

		//获取符合规则的 n 个域名
		$dsql="SELECT id FROM cmpai.pan_domain_in WHERE uid=$uid and typeid={$typeid} and locked=0 and $cond_expire_time_sql order by id desc LIMIT {$parking_count} FOR UPDATE";
		$domain_ret = $pan_domain_in->findSql($dsql);
		$ids = array();
		foreach($domain_ret as $v) {
			$ids_arr[] = $v['id'];
		}
		$ids = implode(',',$ids_arr);
		
		$conditions = "uid=$uid and locked=0 and typeid=$typeid and id in(".$ids.") and $cond_expire_time_sql";
		$domain_count = $pan_domain_in->findCount($conditions);
		if($domain_count!=$parking_count){
			// json_s(array('status'=>201,'msg'=>'提示：操作失败，选中的域名与实际不符'));
			json_s(array('status'=>201,'msg'=>'提示：操作失败，201'));
		}			
		$kt_count = $ret_con['count'] - $ret_con['sys_count'];
		if($parking_count>$domain_count){
			// json_s(array('status'=>201,'msg'=>'提示：停放域名个数不能大于当前可用域名数，可用'.$domain_count.'个'));
			json_s(array('status'=>201,'msg'=>'提示：操作失败，202'));
		}			
		if($parking_count>$kt_count){
			// json_s(array('status'=>201,'msg'=>'提示：停放域名个数不能大于当前剩余可停放域名数，可停'.$kt_count.'个'));
			json_s(array('status'=>201,'msg'=>'提示：操作失败，203'));
		}			
		if($parking_count==0){
			json_s(array('status'=>201,'msg'=>'提示：域名参数出错，204'));
		}			
		//------------限制用户并发请求操作域名相关----------begin
		$domain_action_uid = 'domain_action_uid_'.$uid;
		if(false === cache_a($domain_action_uid,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
		//------------限制用户并发请求操作域名相关----------end	
	
		$end_time = date("Y-m-d", strtotime("+".$ret_con['cycle_time']." day", strtotime($now_time_str)));
		$parking_row = array();
		foreach($ids_arr as $v) {
			$domain = '';
			$_r = $pan_domain_in->find(array('id'=>$v));
			$domain = $_r['domain'];
			// if(!$domain)json_s(array('status'=>201,'msg'=>"错误提示：域名id：$v 获取不到详情域名"));
			if(!$domain)json_s(array('status'=>201,'msg'=>'提示：操作失败，205'));
			$row = array(
					'uid'=>$uid,
					'type'=>1,
					'score_type'=>411104,
					'typeid'=>$typeid,
					'create_time'=>$now_time_str,
					'income'=>$ret_con['income'],
					'cycle_time'=>$ret_con['cycle_time'],
					'end_time'=>$end_time,
					'domain'=>$domain,
					'domain_id'=>$v,
					'act_ip'=>'sys',
					'status'=>0,
					'act_note'=>'['.$now_time_str.'(自动)提交停放成功]'
			);
			$parking_id = $pan_parking->create($row);
			if($parking_id>0)$parking_row[] = $parking_id;
		}
		if($parking_count!=count($parking_row)){
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
			// json_s(array('status'=>300,'msg'=>'错误提示：系统创建停放域名数量与实际不符'));				
			json_s(array('status'=>300,'msg'=>'错误提示：操作失败，206'));				
		}				
		//---将域名更新状态：锁定
		$pan_domain_in->update("uid=$uid and locked=0 and id in ($ids)",array('locked'=>9,'upd_time'=>$now_time_str));
		$update_domain_row = $pan_domain_in->affectedRows(); //影响行数
		
		if($update_domain_row!=$parking_count){
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
			// json_s(array('status'=>300,'msg'=>'错误提示：系统更新域名数量与实际不符'));				
			json_s(array('status'=>300,'msg'=>'错误提示：操作失败，207'));				
		}
		$pan_parking_config->update(array('type'=>1,'score_type'=>$ret_con['score_type'],'typeid'=>$typeid,'cycle_time'=>$ret_con['cycle_time']),array('sys_count'=>$ret_con['sys_count']+$parking_count));
		user_log($uid, 1503, $ip, "【用户".$uid."】：".$ret_con['name']."(源UID: $uid_from 提供了 $uid_from_count 个域名停放后，自动域名停放，每日每个".$ret_con['income']." 类型：积分 ，停放周期".$ret_con['cycle_time']."，域名ID列表: $ids , 停放ID列表：".implode(',',$parking_row));		
}
?>