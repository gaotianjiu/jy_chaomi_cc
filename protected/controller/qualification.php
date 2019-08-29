<?php
/*
 * 搬砖证模块
 *
 */
class qualification extends spController{
    function __construct(){ 
		d404();
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
		//申请米转证
		$uid = $this->uid;
		$ip = get_client_ip();
		$now_time = time();
		$now_time_str = date("Y-m-d H:i:s");
        $new_ym_code = spClass('new_ym_code');
		$pan_apply_bl = spClass('pan_apply_bl'); // 入盘比例要求
		$pan_domain_in = spClass('pan_domain_in'); // 域名实盘表
        $pan_qualification = spClass('pan_qualification');
		$from = $this->spArgs('from'); //品种ID
		if($from=='bl'){
			$typeid = intval($this->spArgs('typeid')); //品种ID	
			$ret_bl = $pan_apply_bl->find(array('typeid'=>$typeid));
			if($ret_bl['account_count']==0){
				json_s(array('status'=>201,'msg'=>'当前品种暂未开放域名转证服务'));
			}
			$domain_count = $pan_domain_in->findCount(array('typeid'=>$typeid,'uid'=>$uid,'locked'=>0)); //持有当前品种数量并且locked=0(状态正常)
			$now_count = intval($domain_count / $ret_bl['account_count']); // 可转额度
			json_s(array('status'=>200,'typeid'=>$typeid,'name'=>$ret_bl['name'],'domain_count'=>(int)$domain_count,'now_count'=>$now_count,'apply_count'=>(int)$ret_bl['apply_count'],'account_count'=>(int)$ret_bl['account_count']));
		}
		if($from=='create'){
			$typeid = intval($this->spArgs('typeid')); //品种ID	
			$ret_bl = $pan_apply_bl->find(array('typeid'=>$typeid));
			if($ret_bl['account_count']==0){
				json_s(array('status'=>201,'msg'=>'当前品种暂未开放域名转证服务'));
			}
			$domain_count = $pan_domain_in->findCount(array('typeid'=>$typeid,'uid'=>$uid,'locked'=>0)); //持有当前品种数量并且locked=0(状态正常)
			$now_count = intval($domain_count / $ret_bl['account_count']); // 可转额度
			if($now_count==0 || $domain_count < $ret_bl['account_count'])json_s(array('status'=>201,'msg'=>'您当前持有'.$ret_bl['name'].'域名：'.$domain_count.'个，不符合按比例转证要求。'));
			
			//------------限制用户并发请求操作域名相关----------begin
			$domain_action_uid = 'domain_action_uid_'.$uid;
			if(false === cache_a($domain_action_uid,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
			//------------限制用户并发请求操作域名相关----------end	
			
			$sp = spClass('pan_domain_in');
			$sql_sw = false;
			$sp->runSql("SET AUTOCOMMIT=0");
			$sp->runSql('BEGIN'); //开启事务
			$number = $ret_bl['account_count'];
			//获取符合规则的 number 个域名
			$dsql="SELECT id,uid,domain,typeid FROM cmpai.pan_domain_in WHERE uid=$uid and typeid={$typeid} and locked=0 order by id desc LIMIT {$number} FOR UPDATE";
			$domain_ret = $pan_domain_in->findSql($dsql);
			$ids = array();
			$domains = array();
			foreach($domain_ret as $v) {
				$ids[] = $v['id'];
				$domains[] = $v['domain'];
				$domains_a[] = "'".$v['domain']."'";
			}
			$ids_str = implode(',',$ids);
			$domain_str = implode(',',$domains);
			$domain_str_a = implode(',',$domains_a); //加了'
			if(count($ids) != count($domains) || count($ids)!=$number || count($ids)==0 || $ids_str=='' || $domain_str==''){
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'系统找不到具体域名'.$number));			
			}			
			$row = array(
					'uid'=>$uid,
					'typeid'=>$typeid,
					'create_time'=>$now_time_str,
					'status'=>1,
					'domain_count'=>$number,
					'domain_ids'=>$ids_str,
					'domain_str'=>$domain_str_a,
					'act_time'=>$now_time_str,
					// 'is_original'=>1,
					'act_note'=>'['.$now_time_str.'提交米转证成功]'
			);
			$qualification_id = $pan_qualification->create($row);
			
			//---将域名更新状态：锁定
			$pan_domain_in->update("uid=$uid and id in ($ids_str)",array('locked'=>5,'upd_time'=>$now_time_str));
			$update_domain_row = $pan_domain_in->affectedRows(); //影响行数
			
			if($update_domain_row!=$number){
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
				json_s(array('status'=>300,'msg'=>'系统更新域名数量与实际不符'));				
			}					
			user_log($uid, 1201, $ip, "【用户".$uid."】：申请炒米证（编号".$qualification_id."）域名个数：".$number."，域名ID：".$domain_ids.'，域名列表：'.$domain_str);
			
			//---添加站内短信---begin
			$type =  '901';
			$tit  =  '恭喜，您成功获得1张'.$ret_bl['name'].'炒米证';
			$txt  =  "您于".$now_time_str.'成功获得1张'.$ret_bl['name'].'炒米证，编号：'.$qualification_id.'，已锁定域名：'.$number.'个';
			web_msg_send($tit,$type,$uid,$txt);							
			//---添加站内短信---end	
			send_mobile_email($uid,"炒米网(chaomi.cc)成功获取炒米证提醒","您于".$now_time_str.'成功获得1张'.$ret_bl['name'].'炒米证，编号：'.$qualification_id);
				
			$sql_sw = true;
			if(false===$sql_sw){
				$sp->runSql('ROLLBACK'); //回滚事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'系统处理出错，已回滚事务。'));				
			}else{
				$sp->runSql('COMMIT'); //提交事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
				json_s(array('status'=>200,'msg'=>'已成功获得1张['.$ret_bl['name'].']炒米证'));	
			}		
		}		
        //---前端选择域名类型---begin
        $new_ym_code_r = $new_ym_code->findAll('state=1');		
        //---前端选择域名类型---end
        $this->type_options = $new_ym_code_r; //品种列表
        $this->module = "qualification";
        $this->act = 'qualification_apply';
        $this->display('amui/qualification/apply.html');
    }
    function applyList(){
		//搬砖证列表
		$uid = $this->uid;
		$page = intval($this->spArgs('page', 1));
		if($page<=0)$page=1;		
		$pan_qualification = spClass('pan_qualification');
		$ret = $pan_qualification->spPager($page, pgsize)->findAll(array('uid'=>$uid),"id desc");
		//分页开始
        $pager = $pan_qualification->spPager()->getPager();
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
		foreach($ret as $v) {
			$r = $new_ym_code->find(array('code'=>$v['typeid']));
			$v['name'] = $r['name'];
			$ret_[] = $v;
		}		
		
        $this->ret = $ret_;
        $this->module = "qualification";
        $this->act = 'qualification_applyList';
        $this->display('amui/qualification/applyList.html');		
    }
	function outplat(){
		$uid = $this->uid;
		$ip = get_client_ip();
		$now_time = time();
		$now_time_str = date("Y-m-d H:i:s");		
		$pan_domain_in = spClass('pan_domain_in'); // 域名实盘表
        $pan_qualification = spClass('pan_qualification');
		$id = intval($this->spArgs('id'));
		$ret = $pan_qualification->find(array('id'=>$id,'uid'=>$uid));
		if(!$ret){
			json_s(array('status'=>201,'msg'=>'权限不足或不存在'));
		}		
		if($ret['status']!=1){
			json_s(array('status'=>201,'msg'=>'炒米证状态必须是有效，才能申请转出。'));
		}
		if($ret['domain_count']==0 || $ret['domain_ids']==""){
			json_s(array('status'=>201,'msg'=>'炒米证域名数量为空!!!'));
		}
		$ret_count = $pan_domain_in->findCount("uid = $uid and locked=5 and id in(".$ret['domain_ids'].")");
		if($ret['domain_count']!=$ret_count){
			json_s(array('status'=>201,'msg'=>'炒米证域名与实际数据不符!!!'));
		}		
		//------------限制用户并发请求操作域名相关----------begin
		$domain_action_uid = 'domain_action_uid_'.$uid;
		if(false === cache_a($domain_action_uid,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
		//------------限制用户并发请求操作域名相关----------end	
		$sp = spClass('pan_domain_in');
		$sql_sw = false;
		$sp->runSql("SET AUTOCOMMIT=0");
		$sp->runSql('BEGIN'); //开启事务	
		//---将域名更新状态：证转出 locked=7
		$pan_domain_in->update("uid=$uid and locked=5 and id in(".$ret['domain_ids'].")",array('locked'=>7,'upd_time'=>$now_time_str));
		$update_domain_row = $pan_domain_in->affectedRows(); //影响行数	
		if($update_domain_row!=$ret_count){
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			json_s(array('status'=>201,'msg'=>'系统处理出错(更新域名数据不符)，已回滚事务。'));			
		}		
		//---将资格证更新状态：证转出
		$pan_qualification->update(array('id'=>$id,'uid'=>$uid,'status'=>1),array('status'=>4,'act_time'=>$now_time_str,'act_note'=>$ret['act_note'].'['.$now_time_str.'提交证转出成功]'));
		if($pan_qualification->affectedRows()!=1){
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			json_s(array('status'=>201,'msg'=>'系统处理出错(更新证状态不符)，已回滚事务。'));			
		}
		user_log($uid, 1201, $ip, "【用户".$uid."】：提交转出炒米证（编号".$id."）域名个数：".$ret['domain_count']."，域名ID：".$ret['domain_ids'].'，域名列表：'.$ret['domain_str']);	
		$sql_sw = true;
		if(false===$sql_sw){
			$sp->runSql('ROLLBACK'); //回滚事务
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			json_s(array('status'=>201,'msg'=>'系统处理出错，已回滚事务。'));				
		}else{
			$sp->runSql('COMMIT'); //提交事务
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
			json_s(array('status'=>200,'msg'=>'已成功提交转出炒米证，编号：'.$id));	
		}		
	}
}
?>