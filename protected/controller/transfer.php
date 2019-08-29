<?php
/*
 * 域名转出模块
 *
 */
class transfer extends spController{
    function __construct(){ 
        parent::__construct();
		$sso_user = check();
        if ($sso_user == true) {
            $this->uid = $sso_user['uid'];
            $this->mid = $sso_user['mid'];
            check_code();
			d404();
        } else {
            re_login();
            exit();
        }
    }
    function apply(){
		//申请域名转出
		$uid = $this->uid;
		$ip = get_client_ip();
		$now_time = time();
		$now_time_str = date("Y-m-d H:i:s");
        $new_ym_code = spClass('new_ym_code');
		$pan_domain_in = spClass('pan_domain_in'); // 域名实盘表
        $pan_transfer = spClass('pan_transfer'); //转出数据表-主
        $pan_transfer_log = spClass('pan_transfer_log'); // 转出数据表-副
        $pan_member_registrar = spClass('pan_member_registrar'); // 注册商帐号表
		
		$from = $this->spArgs('from'); 
		$typeid = intval($this->spArgs('typeid')); //品种ID	
		$transfer_count = intval($this->spArgs('transfer_count')); //转出个数
		
		$registrar_852 = $pan_member_registrar->findAll(array('uid'=>$uid,'website'=>852)); //易名列表
		$registrar_851 = $pan_member_registrar->findAll(array('uid'=>$uid,'website'=>851)); //爱名列表
		$registrar_854 = $pan_member_registrar->findAll(array('uid'=>$uid,'website'=>854)); //万网列表
		$this->registrar_852 = $registrar_852;
		$this->registrar_851 = $registrar_851;
		$this->registrar_854 = $registrar_854;
		
		$registrar_852_id = intval($this->spArgs('registrar_852')); //易名注册商ID
		$registrar_851_id = intval($this->spArgs('registrar_851')); //爱名注册商ID
		$registrar_854_id = intval($this->spArgs('registrar_854')); //万网注册商ID
		
		$ret_code =  $new_ym_code->find(array('code'=>$typeid));
		if($from=='config'){
			$domain_count = $pan_domain_in->findCount(array('typeid'=>$typeid,'uid'=>$uid,'locked'=>0)); //持有当前品种数量并且locked=0(状态正常)
			json_s(array('status'=>200,'typeid'=>$typeid,'name'=>$ret_code['name'],'domain_count'=>(int)$domain_count));
		}
		if($from=='cancel'){
			$id = intval($this->spArgs('id'));
			if(empty($id))json_s(array('status'=>201,'msg'=>'参数ID不能为空'));			
			//------------限制用户并发请求操作域名相关----------begin
			$domain_action_uid = 'domain_action_uid_'.$uid;
			// if(false === cache_a($domain_action_uid,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
			//------------限制用户并发请求操作域名相关----------end	
			//判断相关权限等---begin
			$conditions = "uid = $uid and status = 1 and id = $id";
			$ret_count = $pan_transfer->findCount($conditions);
			if($ret_count!=1)json_s(array('status'=>201,'msg'=>'操作非法，权限不足或已取消'));
			//判断相关权限等---end
			$sp = spClass('pan_domain_in');
			$sql_sw = false;
			$sp->runSql("SET AUTOCOMMIT=0");
			$sp->runSql('BEGIN'); //开启事务	
			
			//判断域名相关状态等---begin
			$conditions = "uid = $uid and status = 1 and id = $id";
			$ret = $pan_transfer->find($conditions);
			$domain_ids = $ret['domain_ids'];
			$conditions = "uid = $uid and locked=4 and id in({$domain_ids})";//状态：转出中 locked=4
			$pan_in_count = $pan_domain_in->findCount($conditions);
			if($pan_in_count!=$ret['domain_count'] || $pan_in_count==0){
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'域名数目不符'));
			}	
			//判断域名相关状态等---end	

			//修改转出表的状态 
			//---改状态值---begin
			$row = array('status'=>4,'deal_time'=>$now_time_str);
			$conditions = "uid = $uid and status = 1 and id = $id";
			$ret = $pan_transfer->update($conditions,$row);
			$pan_transfer_affectedRows = $pan_transfer->affectedRows();
			//---改状态值---end
			//修改用户域名表的状态 
			$conditions = "uid = $uid and locked = 4 and id in({$domain_ids})";
			$row = array('locked'=>0,'upd_time'=>$now_time_str); //修改为正常
			$ret = $pan_domain_in->update($conditions,$row);
			$pan_affectedRows = $pan_domain_in->affectedRows(); //影响行数
			
			if($pan_affectedRows != $pan_in_count || !$pan_transfer_affectedRows){
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'系统处理出错，已回滚事务。'));
			}
			user_log($uid,619,$ip,'【用户'.$uid.'】:成功取消转出域名编号：'.$id.'共'.$pan_in_count.'个域名,已处理共(影响行数)'.$pan_affectedRows.'个');
				//----邮件后台提醒----begin
					$content = array();
					$content['to'] = array('pwpet@qq.com');
					$content['sub'] = array('%content%'=>array('用户MID：'.$this->mid.'，提交了域名转出申请，转出域名数量'.$pan_in_count.'个'));
					$new_content = json_encode($content);
					send_mail('pwpet@qq.com','【炒米后台提醒】有用户提交域名转出！',$new_content,8);
					$content['to'] = array('605466504@qq.com');
					$new_content = json_encode($content);
					send_mail('605466504@qq.com','【炒米后台提醒】有用户提交域名转出！',$new_content,8);							
				//----邮件后台提醒----end			
			$sql_sw = true;
			if(false===$sql_sw){
				$sp->runSql('ROLLBACK'); //回滚事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'系统处理出错，已回滚事务。'));				
			}else{
				$sp->runSql('COMMIT'); //提交事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
				json_s(array('status'=>200,'msg'=>'已成功取消转出'));
			}
		}		
		if($from=='create'){
			$domain_count = $pan_domain_in->findCount(array('typeid'=>$typeid,'uid'=>$uid,'locked'=>0)); //持有当前品种数量并且locked=0(状态正常)
			if($transfer_count>$domain_count){
				json_s(array('status'=>201,'msg'=>'转出个数不能大于当前可用域名数量'.$domain_count.'个'));
			}
			if($transfer_count==0){
				json_s(array('status'=>201,'msg'=>'参数出错'));
			}
			
			// 处理注册商帐号ID----begin
			$ename_reg = $pan_member_registrar->find(array('uid'=>$uid,'website'=>852,'id'=>$registrar_852_id)); //易名
			$am_reg = $pan_member_registrar->find(array('uid'=>$uid,'website'=>851,'id'=>$registrar_851_id)); //爱名
			$ww_reg = $pan_member_registrar->find(array('uid'=>$uid,'website'=>854,'id'=>$registrar_854_id)); //万网
			if(!$ename_reg || !$am_reg || !$ww_reg)json_s(array('status'=>201,'msg'=>'注册商帐号ID参数出错'));
			$domain_website = "";
			$domain_website_ = "";
			$domain_website.= '易名(ID：'.$ename_reg['webid'].')<br/>';
			$domain_website_.= '易名(ID：'.$ename_reg['webid'].') 验证手机或邮箱：'.$ename_reg['webcheck'].'<br/>';
			$domain_website.= '爱名(ID：'.$am_reg['webid'].')<br/>';
			$domain_website_.= '爱名(ID：'.$am_reg['webid'].') 验证手机或邮箱：'.$am_reg['webcheck'].'<br/>';	
			$domain_website.= '万网(ID：'.$ww_reg['webid'].')<br/>';
			$domain_website_.= '万网(ID：'.$ww_reg['webid'].') 验证手机或邮箱：'.$ww_reg['webcheck'].'<br/>';				
			// 处理注册商帐号ID----end
			
			//------------限制用户并发请求操作域名相关----------begin
			$domain_action_uid = 'domain_action_uid_'.$uid;
			if(false === cache_a($domain_action_uid,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
			//------------限制用户并发请求操作域名相关----------end	
			
			$sp = spClass('pan_domain_in');
			$sql_sw = false;
			$sp->runSql("SET AUTOCOMMIT=0");
			$sp->runSql('BEGIN'); //开启事务
			$number = $transfer_count;
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
					'count'=>$number,
					'create_time'=>$now_time_str,
					'domain_count'=>$number,
					'domain_ids'=>$ids_str,
					'domain_str'=>$domain_str_a,
					'domain_website'=>$domain_website,
					'domain_website_'=>$domain_website_,
					'act_ip'=>$ip,
					'status'=>1
			);
			$transfer_id = $pan_transfer->create($row);
			
			//---将域名更新状态：转出中
			$pan_domain_in->update("uid=$uid and id in ($ids_str)",array('locked'=>4,'upd_time'=>$now_time_str));
			$update_domain_row = $pan_domain_in->affectedRows(); //影响行数
			
			if($update_domain_row!=$number){
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
				json_s(array('status'=>300,'msg'=>'系统更新域名数量与实际不符'));				
			}					
			user_log($uid, 1003, $ip, "【用户".$uid."】：申请域名转出（编号".$transfer_id."）域名个数：".$number."，域名ID：".$ids_str.'，域名列表：'.$domain_str);
			
			$sql_sw = true;
			if(false===$sql_sw){
				$sp->runSql('ROLLBACK'); //回滚事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'系统处理出错，已回滚事务。'));				
			}else{
				$sp->runSql('COMMIT'); //提交事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
				json_s(array('status'=>200,'msg'=>'已成功转出'.$number.'个'.$ret_code['name'].'域名'));	
			}		
		}		
        //---前端选择域名类型---begin
        $new_ym_code_r = $new_ym_code->findAll('state=1');		
        //---前端选择域名类型---end
        $this->type_options = $new_ym_code_r; //品种列表
        $this->module = "transfer";
        $this->act = 'transfer_apply';
        $this->display('amui/transfer/apply.html');
    }
    function applyList(){
		//域名停放列表
		$uid = $this->uid;
		$page = intval($this->spArgs('page', 1));
		if($page<=0)$page=1;		
		$pan_transfer = spClass('pan_transfer');
		$ret = $pan_transfer->spPager($page, pgsize)->findAll(array('uid'=>$uid),"id desc");
		//分页开始
        $pager = $pan_transfer->spPager()->getPager();
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
			$r = $new_ym_code->find(array('code'=>$v['typeid']));
			$v['name'] = $r['name'];
			$ret_[] = $v;
		}		
		
        $this->ret = $ret_;
        $this->module = "transfer";
        $this->act = 'transfer_applyList';
        $this->display('amui/transfer/applyList.html');		
    }
}
?>