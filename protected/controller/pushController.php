<?php
/*
 * push域名模块
 *
 */
define("web_md5", "_chaomi_cc");
class pushController extends BaseController{
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
    function actionapply(){
		//发起PUSH域名
		$uid = $this->uid;
		$mid = $this->mid;
		$ip = get_client_ip();
		$now_time = time();
		$now_time_str = date("Y-m-d H:i:s");
        $pub_user = spClass('pub_user');
        $pan_domain_push = spClass('pan_domain_push');
        $pan_domain_in = spClass('pan_domain_in');
		$id = $this->spArgs('id'); 

		$from = $this->spArgs('from');
		
		if($from=='tq'){
			//快速提取域名列表
			$tq_typeid = $this->spArgs('tq_typeid'); 
			$tq_count = intval($this->spArgs('tq_count'));
			if(!$tq_typeid){
				json_s(array('status'=>201,'msg'=>'品种参数不能为空'));
			}
			if($tq_count<1 || $tq_count>200){
				json_s(array('status'=>201,'msg'=>'提取数量不能小于1个或大于200个'));
			}			
			$cond = array('uid'=>$uid,'typeid'=>$tq_typeid,'locked'=>0);
			$count = $pan_domain_in->findCount($cond);
			if($tq_count>$count){
				json_s(array('status'=>201,'msg'=>'提示：需提取数量'.$tq_count.'个，已超出当前可用数量'.$count.'个'));
			}
			$_ret = $pan_domain_in->findAll($cond,"id desc","domain",$tq_count);
			$domain_list = "";
			foreach($_ret as $v) {
				$domain_list .= $v['domain'].'&#13;&#10;';
				
			}
			json_s(array('status'=>200,'domain_list'=>$domain_list));
		}	
		if($from=='check'){
			//在域名管理页面提交到push
			if(!$id || empty($id))json_s(array('status'=>201,'msg'=>'参数ID不能为空'));
			if(is_array($id)){
				$count = count($id);
				//务必强制转换成数值类型---begin
				foreach($id as $v){
					$new_arr[]=(int)$v;
				}
				//务必强制转换成数值类型---end
				$ids = implode(',',$new_arr);
			}else{
				$ids = (int)$id; //务必强制转换成数值
				$count = 1;
			}
			if(!$ids)json_s(array('status'=>201,'msg'=>'参数ID不能为空'));
			if($count>200)json_s(array('status'=>201,'msg'=>'每次最多可批量PUSH不能大于200个域名'));
			
			$_count = $pan_domain_in->findCount("uid=$uid and id in($ids) and locked=0");
			if($count!=$_count)json_s(array('status'=>201,'msg'=>'只能批量PUSH域名状态正常的域名'));
			json_s(array('status'=>200,'msg'=>'success'));
		}		

		if($from=='all'){
			//在域名管理页面提交到push-显示到输入框
			if(!$id || empty($id))json_s(array('status'=>201,'msg'=>'参数ID不能为空'));
			if(is_array($id)){
				$count = count($id);
				//务必强制转换成数值类型---begin
				foreach($id as $v){
					$new_arr[]=(int)$v;
				}
				//务必强制转换成数值类型---end
				$ids = implode(',',$new_arr);
			}else{
				$ids = (int)$id; //务必强制转换成数值
				$count = 1;
			}
			if(!$ids)json_s(array('status'=>201,'msg'=>'参数ID不能为空'));
			if($count>200)json_s(array('status'=>201,'msg'=>'每次最多可批量PUSH不能大于200个域名'));
			
			$_count = $pan_domain_in->findCount("uid=$uid and id in($ids) and locked=0");
			if($count!=$_count)json_s(array('status'=>201,'msg'=>'只能批量PUSH域名状态正常的域名'));
			
				$_ret = $pan_domain_in->findAll("uid=$uid and id in($ids) and locked=0");
				// $ret = array();
				$domain_list = "";
				foreach($_ret as $v) {
					// $ret[] = $v['domain'];
					$domain_list .= $v['domain'].'&#13;&#10;';
					
				}
				$this->domain_list = $domain_list;
		}			
		
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
			// json_s(array('status'=>201,'msg'=>'暂停转移'));
			$note = trim($this->spArgs('note'));
			$to_username = trim($this->spArgs('to_username'));
			//验证是否实名
			$member_info = spClass('pan_member_card')->find(array('uid' => $uid));
			$now_uid_name = $member_info['first_name'].$member_info['last_name'];
			if(!$now_uid_name)json_s(array('status'=>201,'msg'=>'请实名认证后再操作转移'));
			
			$money = trim($this->spArgs('money'));
			$money  = bcadd($money,0,2);//强制转换成最多只保留两位小数点，防止精度误差
			if($money>99999999)json_s(array('status'=>201,'msg'=>'索要的金额不能大于99999999元'));
			
			
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
			
			//----处理domain---begin
			$domain = $this->spArgs('domain');
			if(!$domain || empty($domain))json_s(array('status'=>201,'msg'=>'请输入要转移的域名'));
			if(is_array($domain)){
				$domain_count = count($domain);
				foreach($domain as $v){
					$new_arr[]= $pan_domain_push->escape($v);
				}
				$domain_m_d = implode(',',$new_arr);
			}else{
				$domain_m_d = $pan_domain_push->escape($domain); 
				$domain_count = 1;
			}
			
			if($domain_count<1)json_s(array('status'=>201,'msg'=>'请输入要转移的域名'));
			if($domain_count>200)json_s(array('status'=>201,'msg'=>'一次最多可提交200个域名push请求，请分批次提交'));				
			//----处理domain---end

			$domainArr = array();
			$domainArr = $pan_domain_in->findAll("uid = $uid and locked=0 and domain in($domain_m_d)","","id,uid,domain");
			// var_dump( $domainArr);
			// exit;
			
			if($domain_count != count($domainArr) || !$domainArr){
				$domain_err = "";
				if(is_array($domain)){
					foreach($domain as $v){
						$ret = $pan_domain_in->find(array('domain'=>$v,'uid'=>$uid));
						if(!$ret){
							$domain_err .= "域名：$v 不属于您所有<br/>";
						}else{
							if($ret['locked']!=0){
								$domain_err .= "域名：$v 当前状态暂不支持PUSH<br/>";
							}
						}	
					}
				}else{
					$ret = $pan_domain_in->find(array('domain'=>$domain,'uid'=>$uid));
					if(!$ret){
						$domain_err .= "域名：$domain 不属于您所有<br/>";
					}else{
						if($ret['locked']!=0){
							$domain_err .= "域名：$domain 当前状态暂不支持PUSH<br/>";
						}
					}						
				}				
			}
			if($domain_err!="" && $domain_count != count($domainArr) || !$domainArr){
				json_s(array('status'=>210,'msg'=>$domain_err));				
			}
			
			if($domain_count != count($domainArr)){
				json_s(array('status'=>201,'msg'=>'实际PUSH域名数量与系统数量异常，请检查是否有重复域名(X12)'));
			}
			
			//处理安全码
			$pw = trim($this->spArgs('safecode'));
			if(empty($pw))json_s(array('status'=>201,'msg'=>'交易密码不能为空'));
			$pw = md5(md5($pw . web_md5)); //双重md5加密			
			$pws = spClass('pan_user_safecode')->find(array('uid' => $uid));//密码获取代码
			//------------限制帐号请求验证安全码次数----------begin
			$key_safeCode_name = 'push_safeCode_uid_'.$uid;
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
			
			//------------限制用户并发请求操作域名相关----------begin
			$domain_action_uid = 'domain_action_uid_'.$uid;
			if(false === cache_a($domain_action_uid,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
			//------------限制用户并发请求操作域名相关----------end				
			
						
			$sp = spClass('pan_domain_in');
			$sql_sw = false;
			$sp->runSql("SET AUTOCOMMIT=0");
			$sp->runSql('BEGIN'); //开启事务
						
			//修改pan_domain_in表对应的域名为push中状态 locked=13
			$pan_domain_in->update("uid=$uid and locked=0 and domain in ($domain_m_d)",array('locked'=>13));
			$update_domain_push_count = $pan_domain_in->affectedRows();
			if($update_domain_push_count != $domain_count){
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'系统更新具体域名状态与实际域名数量不相符，请稍候重试！'));				
			}			
			
			//添加数据到push主表
			$domain_id_arr = array();
			foreach ($domainArr as $v) {
					$domain_id_arr[] = $v['id'];
			}									
			$domain_m_ids = implode(',',$domain_id_arr);
			$row = array(
					'send_uid'=>$uid, //发起方
					'accept_uid'=>$touid, //接收方
					'domain_ids'=>$domain_m_ids,//域名id
					'domain_list'=>$domain_m_d,//域名列表
					'domain_count'=>$domain_count,//域名数量
					'money'=>$money,//索要金额
					'note'=>$note,//备注
					'now_sxf'=>0,//手续费
					'create_time'=>$now_time_str,//发起时间
					'status'=>1,
					
			);
			$push_id = $pan_domain_push->create($row);
			if(!$push_id){
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'系统添加push主表数据错误'));					
			}
			
		//------开始处理财务相关-----end
		$price = $money;
		$send_uid = $uid;
		$accept_uid = $touid;
		$r = $pub_user->find(array('uid'=>$send_uid));
		$send_mid = $r['mid'];
		$r = $pub_user->find(array('uid'=>$accept_uid));
		$accept_mid = $r['mid'];			
		//---添加站内短信---begin
		$type =  '901';
		$tit  =  "提示：待接收PUSH域名：$domain_count 个";
		$txt  =  "待接收PUSH域名：$domain_count 个，发起方炒米ID：$send_mid ，对方索要金额 $price 元，对方发起时间：$now_time_str";
		web_msg_send($tit,$type,$accept_uid,$txt);	
		//---添加站内短信---end	
		send_mobile_email($accept_uid,"炒米网(chaomi.cc)待接收PUSH域名提醒","待接收PUSH域名：$domain_count 个，发起方炒米ID：$send_mid ，对方索要金额 $price 元");				
		//---添加站内短信---begin
		$type =  '901';
		$tit  =  "提示：您操作发起PUSH域名：$domain_count 个";
		$txt  =  "您发起PUSH域名：$domain_count 个，接收方炒米ID：$accept_mid ，您向对方索要金额：$price 元";
		web_msg_send($tit,$type,$send_uid,$txt);					
		//---添加站内短信---end	
		send_mobile_email($send_uid,"炒米网(chaomi.cc)发起PUSH域名提醒","您操作发起PUSH域名：$domain_count 个，接收方炒米ID：$accept_mid ，您向对方索要金额：$price 元");		

			
			$sql_sw = true;
			if(false===$sql_sw){
				$sp->runSql('ROLLBACK'); //回滚事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'系统处理出错，已回滚事务。'));				
			}else{
				$sp->runSql('COMMIT'); //提交事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>200,'msg'=>'已成功push域名，请等待对方接收'));	
			}		
		}	

		//列出品种typeid列表
		$twos_data = spClass('new_ym_code_twos')->findAll("state=1","order_id asc",'two_code,name');
		
		foreach ($twos_data as $v) {
				$count = $pan_domain_in->findCount(array('uid'=>$uid,'typeid'=>$v['two_code'],'locked'=>0));		  
				$type_options[] = array('id'=>$v['two_code'],'name'=>$v['name']."[可用数：$count 个]");
		}
		
        $this->type_options = $type_options;
        $this->module = "push";
        $this->act = 'push_apply';
        $this->display('amui/push/push.html');
    }
	function details(){
		//详情页
		$uid = $this->uid;
		$ip = get_client_ip();
		$now_time = time();
		$now_time_str = date("Y-m-d H:i:s");
        $pub_user = spClass('pub_user');
        $pan_domain_push = spClass('pan_domain_push');
        $pan_domain_in = spClass('pan_domain_in');
		$id = intval($this->spArgs('id'));
		if(!$id)json_s(array('status'=>201,'msg'=>'ID参数不能为空'));

		$action = $this->spArgs('action');		
		if($action=='send'){
			//发送方
			$ret = $pan_domain_push->find(array('send_uid'=>$uid,'id'=>$id));
			if(!$ret)json_s(array('status'=>201,'msg'=>'当前PUSH-ID错误，操作权限不足。'));
			$accept_uid = $ret['accept_uid'];
			$a = $pub_user->find(array('uid'=>$accept_uid));
			$ret['accept_mid'] = $a['mid'];						

			$domain_list = str_replace("'","",$ret['domain_list']);
			$domain_list = explode(",",$domain_list);
			$domainArr = array();
			foreach ($domain_list as $v) {
					$r = $pan_domain_in->find(array('domain'=>$v));	
					$k['domain'] = $v;
					$_name = check_pz($r['typeid']);
					$k['name'] = $_name[0]['name'];
					$k['pingtai'] = $r['pingtai'];
					$k['expire_time'] = $r['expire_time'];
					
					$domainArr[] = $k;
			}	
			$this->ret = $ret;
			$this->domain_list = $domainArr;
			$this->module = "push";
			$this->act = 'push_send_list';
			$this->display('amui/push/send_details.html');	
			exit;
		}
		if($action=='accept'){
			//接收方
			$ret = $pan_domain_push->find(array('accept_uid'=>$uid,'id'=>$id));
			if(!$ret)json_s(array('status'=>201,'msg'=>'当前PUSH-ID错误，操作权限不足。'));
			$send_uid = $ret['send_uid'];
			$a = $pub_user->find(array('uid'=>$send_uid));
			$ret['send_mid'] = $a['mid'];
			
			$domain_list = str_replace("'","",$ret['domain_list']);
			$domain_list = explode(",",$domain_list);
			$domainArr = array();
			foreach ($domain_list as $v) {
					$r = $pan_domain_in->find(array('domain'=>$v));	
					$k['domain'] = $v;
					$_name = check_pz($r['typeid']);
					$k['name'] = $_name[0]['name'];
					$k['pingtai'] = $r['pingtai'];
					$k['expire_time'] = $r['expire_time'];
					
					$domainArr[] = $k;
			}	
			$this->ret = $ret;
			$this->domain_list = $domainArr;
			$this->module = "push";
			$this->act = 'push_accept_list';
			$this->display('amui/push/accept_details.html');	
			exit;				
		}		
		json_s(array('status'=>201,'msg'=>'action参数不能为空'));
	}
	function receive(){
		//接收方-确认接收
		$uid = $this->uid;
		$ip = get_client_ip();
		$now_time = time();
		$now_time_str = date("Y-m-d H:i:s");
        $pub_user = spClass('pub_user');
        $pan_domain_push = spClass('pan_domain_push');
        $pan_domain_in = spClass('pan_domain_in');
		$id = intval($this->spArgs('id'));
		if(!$id)json_s(array('status'=>201,'msg'=>'ID参数不能为空'));
		
		$ret = $pan_domain_push->find(array('accept_uid'=>$uid,'id'=>$id));
		if(!$ret)json_s(array('status'=>201,'msg'=>'当前PUSH-ID错误，操作权限不足。'));
		if($ret['status']!=1)json_s(array('status'=>201,'msg'=>'当前PUSH状态错误'));
		$send_uid = $ret['send_uid'];
		$accept_uid = $ret['accept_uid'];
		if($accept_uid!=$uid)json_s(array('status'=>201,'msg'=>'错误：接收方ID与当前ID不相符'));
		
		//处理安全码
		$pw = trim($this->spArgs('safecode'));
		if(empty($pw))json_s(array('status'=>201,'msg'=>'交易密码不能为空'));
		$pw = md5(md5($pw . web_md5)); //双重md5加密			
		$pws = spClass('pan_user_safecode')->find(array('uid' => $uid));//密码获取代码
		//------------限制帐号请求验证安全码次数----------begin
		$key_safeCode_name = 'push_safeCode_uid_'.$uid;
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
		
		//------------删除用户操作当前push并发缓存----------begin
		$push_action_id = 'push_action_id_'.$id;
		if(false === cache_a($push_action_id,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，当前PushID队列被占用中，请稍后刷新重试。'));	
		//------------删除用户操作当前push并发缓存----------end	
					
		//------------限制用户并发请求操作域名相关----------begin
		$domain_action_uid_send = 'domain_action_uid_'.$send_uid;
		if(false === cache_a($domain_action_uid_send,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
		//------------限制用户并发请求操作域名相关----------end	
		
		//------------限制用户并发请求操作域名相关----------begin
		$domain_action_uid_accept = 'domain_action_uid_'.$accept_uid;
		if(false === cache_a($domain_action_uid_accept,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
		//------------限制用户并发请求操作域名相关----------end		

		
		
		$sp = spClass('pan_domain_in');
		$sql_sw = false;
		$sp->runSql("SET AUTOCOMMIT=0");
		$sp->runSql('BEGIN'); //开启事务
					
		//更新pan_domain_in表对应的域名为正常状态 locked=0 ，并将UID过户更新为accept_uid
		$domain_m_d = $ret['domain_list'];
		$domain_count = $ret['domain_count'];
		$pan_domain_in->update("uid=$send_uid and locked=13 and domain in ($domain_m_d)",array('locked'=>0,'uid'=>$accept_uid,'upd_time'=>$now_time_str));
		$update_domain_push_count = $pan_domain_in->affectedRows();
		if($update_domain_push_count != $domain_count){
			cache_a($push_action_id,null);//删除用户操作当前push并发缓存
			cache_a($domain_action_uid_send,null);//删除用户操作域名并发缓存
			cache_a($domain_action_uid_accept,null);//删除用户操作域名并发缓存
			json_s(array('status'=>201,'msg'=>'系统更新具体域名状态/过户与实际域名数量不相符，请稍候重试！'));				
		}			
		//更新pan_domain_push表对应状态 status=2 已接收
		$pan_domain_push->update("send_uid=$send_uid and status=1 and id=$id",array('status'=>2));
		if($pan_domain_push->affectedRows()!=1){
			cache_a($push_action_id,null);//删除用户操作当前push并发缓存
			cache_a($domain_action_uid_send,null);//删除用户操作域名并发缓存
			cache_a($domain_action_uid_accept,null);//删除用户操作域名并发缓存
			json_s(array('status'=>201,'msg'=>'系统更新push主表数量与实际不相符，请稍候重试！'));				
		}
		
		//------开始处理财务相关-----begin
		$money = $ret['money'];
	
		$a = spClass('lib_member_account');
		$y=date("Y",time());
		$m=date("m",time());
		$d=date("d",time());
		$utime= $now_time_str;
		$deal_time = $now_time_str;
		$in_uid = $accept_uid; //入方
		$out_uid = $send_uid; //出方
		$price = $money;//交易金额
		
		//------接收方-------begin
			//生成订单号，YmdHis+uid+一个随机数
			$order_id='PUSH'.date("YmdHis").$in_uid.mt_rand(100000,999999);
			//买家，扣掉
			//查询买家【账户】
			$reg = $a->findSql("select balance,freeze_money,draw,fund from ykjhqcom.lib_member_account where uid=$in_uid FOR UPDATE");

			//处理买家的余额，和委托买入时的冻结余额
			$balance=bcsub($reg[0]['balance'],$price);   //账户余额扣掉花费
			$draw=bcsub($reg[0]['draw'],$price);         //不可提现金额
			
			if($balance<0){
				cache_a($push_action_id,null);//删除用户操作当前push并发缓存
				cache_a($domain_action_uid_send,null);//删除用户操作域名并发缓存
				cache_a($domain_action_uid_accept,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'余额不足以支付对方索要的金额费用，请先充值'));
			}
			
			//更改买家账户信息
			$upd_money_sql = "update ykjhqcom.lib_member_account set balance=$balance where uid=$in_uid ";
			$a->runSql($upd_money_sql);

			//财务变化流水表 ---  [买家消费记录]
			$pay=0-$price;   //消费前加个 - 符号
			$note = "'接收PUSH域名：$domain_count 个'";
			$brow="insert into ykjhqcom.lib_member_records (`uid`,`order_id`,`type`,`amount`,`ip`,`deal_time`,`note`,`balance`,`y`,`m`,`d`) values($in_uid,'$order_id',500,$pay,'$ip','".time()."',$note,$balance,$y,$m,$d)";
			$a->runSql($brow);
			
			//增加资金流水账单
			//买家消费
			$ins="insert into cmpai.pan_plat_property(uid,property,deal_time,Y,m,d) values($in_uid,$pay,'$deal_time',$y,$m,$d)";
			$a->runSql($ins);

			//日志
			user_log($in_uid,2101,$ip,'【买家资产】接收PUSH域名：'.$domain_count.'，接收方：'.$in_uid.'用户账户扣除费用'.$price.'元，账户余额'.$balance.'元，不可提现金额减少'.$price.',目前为'.$draw.'元。');			
				
		//------接收方-------end
		
		//------发送方-------begin
			//生成订单号，YmdHis+uid+一个随机数
			$order_id='PUSH'.date("YmdHis").$out_uid.mt_rand(100000,999999);
			$deal_time=date("H:i:s",time());
			$reg = $a->findSql("select balance,draw,fund from ykjhqcom.lib_member_account where uid=$out_uid FOR UPDATE");
			//处理手续费
			$sxf = $ret['now_sxf'];
			$aa=bcmul($price,(1-$sxf)); //卖家入账金额
			$bb=bcsub($price,$aa); //卖家扣除金额（手续费）
				
			//如果卖家账户不存在，新建
			if($reg==false){
				$balance=$aa;
				$draw=0;  //不可提现
				$fund=0;
				$a->runSql("insert into ykjhqcom.lib_member_account(`uid`,`balance`,`draw`,`fund`,`update_time`) values($out_uid,$balance,$draw,$fund,'$utime')");
			}else {
				$balance = bcadd($reg[0]['balance'],$aa);
				$fund=0;
				//更改卖家账上余额
				$update_money_sql = "update ykjhqcom.lib_member_account set balance=$balance where uid=$out_uid ";
				$a->runSql($update_money_sql);
			}
			//财务变化流水表   ----卖家入款流水记录
			$note = "'发起带价PUSH域名：$domain_count 个，(扣除手续费 $bb 元)'";
			$brow="insert into ykjhqcom.lib_member_records (`uid`,`order_id`,`type`,`amount`,`ip`,`deal_time`,`note`,`balance`,`y`,`m`,`d`) values($out_uid,'$order_id',501,$aa,'$ip','".time()."',$note,$balance,$y,$m,$d)";
			$a->runSql($brow);

			//增加资金流水账单（平台总流水）  --【用户资金走向】
			$ins="insert into cmpai.pan_plat_property(uid,property,deal_time,Y,m,d) values($out_uid,$aa,'$deal_time',$y,$m,$d)";
			$a->runSql($ins);

			//日志
			user_log($out_uid,2101,$ip,'【卖家资产】发起带价PUSH域名：'.$domain_count.'，发起方：'.$out_uid.'入账'.$aa.'元，扣除手续费'.$bb.'元，账户余额'.$balance.'元');			
				
		//------发送方-------end
		//------开始处理财务相关-----end
		$r = $pub_user->find(array('uid'=>$send_uid));
		$send_mid = $r['mid'];
		$r = $pub_user->find(array('uid'=>$accept_uid));
		$accept_mid = $r['mid'];			
		//---添加站内短信---begin
		$type =  '901';
		$tit  =  "提示：已成功接收PUSH域名：$domain_count 个";
		$txt  =  "已成功接收PUSH域名：$domain_count 个，对方炒米ID：$send_mid ，并已向对方支付金额 $price 元，接收时间：$now_time_str";
		web_msg_send($tit,$type,$accept_uid,$txt);	
		//---添加站内短信---end	
		send_mobile_email($accept_uid,"炒米网(chaomi.cc)成功接收PUSH域名提醒","已成功接收PUSH域名：$domain_count 个，对方炒米ID：$send_mid ，并已向对方支付金额 $price 元");				
		//---添加站内短信---begin
		$type =  '901';
		$tit  =  "提示：您发起的PUSH域名：$domain_count 个，已被接收";
		$txt  =  "恭喜，您发起的PUSH域名：$domain_count 个，对方炒米ID：$accept_mid 接收成功，您索要的金额：$price 元已入帐到当前帐号余额(扣除手续费 $bb 元)，对方接收时间：$now_time_str";
		web_msg_send($tit,$type,$send_uid,$txt);					
		//---添加站内短信---end	
		send_mobile_email($send_uid,"炒米网(chaomi.cc)发起的PUSH域名已被接收提醒","您发起的PUSH域名：$domain_count 个，对方炒米ID：$accept_mid 接收成功，您索要的金额：$price 元已入帐到当前帐号余额(扣除手续费 $bb 元)");		
	
		$sql_sw = true;
		if(false===$sql_sw){
			$sp->runSql('ROLLBACK'); //回滚事务
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			cache_a($push_action_id,null);//删除用户操作当前push并发缓存
			json_s(array('status'=>201,'msg'=>'系统处理出错，已回滚事务。'));				
		}else{
			$sp->runSql('COMMIT'); //提交事务
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			cache_a($push_action_id,null);//删除用户操作当前push并发缓存
			json_s(array('status'=>200,'msg'=>'已成功接收push'));	
		}							
		
	}
	function cancel(){
		//取消
		$uid = $this->uid;
		$ip = get_client_ip();
		$now_time = time();
		$now_time_str = date("Y-m-d H:i:s");
        $pub_user = spClass('pub_user');
        $pan_domain_push = spClass('pan_domain_push');
        $pan_domain_in = spClass('pan_domain_in');
		$id = intval($this->spArgs('id'));
		if(!$id)json_s(array('status'=>201,'msg'=>'ID参数不能为空'));

		$action = $this->spArgs('action');		
		if($action=='send'){
			//发送方-取消
			$ret = $pan_domain_push->find(array('send_uid'=>$uid,'id'=>$id));
			if(!$ret)json_s(array('status'=>201,'msg'=>'当前PUSH-ID错误，操作权限不足。'));
			if($ret['status']!=1)json_s(array('status'=>201,'msg'=>'当前PUSH状态错误'));
			
			//------------删除用户操作当前push并发缓存----------begin
			$push_action_id = 'push_action_id_'.$id;
			if(false === cache_a($push_action_id,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，当前PushID队列被占用中，请稍后刷新重试。'));	
			//------------删除用户操作当前push并发缓存----------end	
						
			//------------限制用户并发请求操作域名相关----------begin
			$domain_action_uid = 'domain_action_uid_'.$uid;
			if(false === cache_a($domain_action_uid,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
			//------------限制用户并发请求操作域名相关----------end	
			$sp = spClass('pan_domain_in');
			$sql_sw = false;
			$sp->runSql("SET AUTOCOMMIT=0");
			$sp->runSql('BEGIN'); //开启事务
						
			//修改恢复pan_domain_in表对应的域名为正常状态 locked=0
			$domain_m_d = $ret['domain_list'];
			$domain_count = $ret['domain_count'];
			$pan_domain_in->update("uid=$uid and locked=13 and domain in ($domain_m_d)",array('locked'=>0));
			$update_domain_push_count = $pan_domain_in->affectedRows();
			if($update_domain_push_count != $domain_count){
				cache_a($push_action_id,null);//删除用户操作当前push并发缓存
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'系统更新具体域名状态与实际域名数量不相符，请稍候重试！'));				
			}			
			//更新pan_domain_push表对应状态 status=4
			$pan_domain_push->update("send_uid=$uid and status=1 and id=$id",array('status'=>4));
			if($pan_domain_push->affectedRows()!=1){
				cache_a($push_action_id,null);//删除用户操作当前push并发缓存
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'系统更新push主表数量与实际不相符，请稍候重试！'));				
			}
			
			$sql_sw = true;
			if(false===$sql_sw){
				$sp->runSql('ROLLBACK'); //回滚事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				cache_a($push_action_id,null);//删除用户操作当前push并发缓存
				json_s(array('status'=>201,'msg'=>'系统处理出错，已回滚事务。'));				
			}else{
				$sp->runSql('COMMIT'); //提交事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				cache_a($push_action_id,null);//删除用户操作当前push并发缓存
				json_s(array('status'=>200,'msg'=>'已成功取消push'));	
			}					

		}
		if($action=='accept'){
			//接收方-取消
			$ret = $pan_domain_push->find(array('accept_uid'=>$uid,'id'=>$id));
			if(!$ret)json_s(array('status'=>201,'msg'=>'当前PUSH-ID错误，操作权限不足。'));
			if($ret['status']!=1)json_s(array('status'=>201,'msg'=>'当前PUSH状态错误'));
			$send_uid = $ret['send_uid'];
			
			//------------删除用户操作当前push并发缓存----------begin
			$push_action_id = 'push_action_id_'.$id;
			if(false === cache_a($push_action_id,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，当前PushID队列被占用中，请稍后刷新重试。'));	
			//------------删除用户操作当前push并发缓存----------end	
						
			//------------限制用户并发请求操作域名相关----------begin
			$domain_action_uid = 'domain_action_uid_'.$send_uid;
			if(false === cache_a($domain_action_uid,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
			//------------限制用户并发请求操作域名相关----------end	
			$sp = spClass('pan_domain_in');
			$sql_sw = false;
			$sp->runSql("SET AUTOCOMMIT=0");
			$sp->runSql('BEGIN'); //开启事务
						
			//修改恢复pan_domain_in表对应的域名为正常状态 locked=0
			$domain_m_d = $ret['domain_list'];
			$domain_count = $ret['domain_count'];
			$pan_domain_in->update("uid=$send_uid and locked=13 and domain in ($domain_m_d)",array('locked'=>0));
			$update_domain_push_count = $pan_domain_in->affectedRows();
			if($update_domain_push_count != $domain_count){
				cache_a($push_action_id,null);//删除用户操作当前push并发缓存
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'系统更新具体域名状态与实际域名数量不相符，请稍候重试！'));				
			}			
			//更新pan_domain_push表对应状态 status=3 拒绝
			$pan_domain_push->update("send_uid=$send_uid and status=1 and id=$id",array('status'=>3));
			if($pan_domain_push->affectedRows()!=1){
				cache_a($push_action_id,null);//删除用户操作当前push并发缓存
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'系统更新push主表数量与实际不相符，请稍候重试！'));				
			}
			
			$sql_sw = true;
			if(false===$sql_sw){
				$sp->runSql('ROLLBACK'); //回滚事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				cache_a($push_action_id,null);//删除用户操作当前push并发缓存
				json_s(array('status'=>201,'msg'=>'系统处理出错，已回滚事务。'));				
			}else{
				$sp->runSql('COMMIT'); //提交事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				cache_a($push_action_id,null);//删除用户操作当前push并发缓存
				json_s(array('status'=>200,'msg'=>'已成功取消push'));	
			}					

		}
		json_s(array('status'=>201,'msg'=>'action参数不能为空'));
		
	}
    function actionsend_list(){
		//发送的请求列表
		$uid = $this->uid;
		$page = intval($this->spArgs('page', 1));
		$status = intval($this->spArgs('status'));
		if($page<=0)$page=1;		
		$pan_domain_push = spClass('pan_domain_push');
		$pub_user = spClass('pub_user');
		$cond = array('send_uid'=>$uid);
		if($status>0)$cond['status'] = $status;
		$_ret = $pan_domain_push->spPager($page, pgsize)->findAll($cond,"id desc");
		$ret = array();
		foreach ($_ret as $v) {
			$r = $pub_user->find(array('uid'=>$v['accept_uid']));		
			$v['accept_mid'] = $r['mid'];
			
			$domain_list = str_replace("'","",$v['domain_list']);
			$domain_list = explode(",",$domain_list);
			$domainArr = "";
			$i = 0;
			foreach ($domain_list as $k) {
					$i++;
					if($i<4)$domainArr .= $k."<br>";
			}			
			$v['domain_more'] = $domainArr;
			
			$ret[] = $v;
		}		
		//分页开始
        $pager = $pan_domain_push->spPager()->getPager();
        if ($pager['total_page'] > 5) {
            if ($page <= 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], $page - 3, 5);
            }
        }
        $this->pager = $pager;			
        $this->ret = $ret;
        $this->status = $status;
        $this->module = "push";
        $this->act = 'push_send_list';
        $this->display('amui/push/send_list.html');		
    }
    function actionaccept_list(){
		//收到的请求列表
		$uid = $this->uid;
		$page = intval($this->spArgs('page', 1));
		$status = intval($this->spArgs('status'));
		if($page<=0)$page=1;		
		$pan_domain_push = spClass('pan_domain_push');
		$pub_user = spClass('pub_user');
		$cond = array('accept_uid'=>$uid);
		if($status>0)$cond['status'] = $status;		
		$_ret = $pan_domain_push->spPager($page, pgsize)->findAll($cond,"id desc");
		$ret = array();
		foreach ($_ret as $v) {
			$r = $pub_user->find(array('uid'=>$v['send_uid']));		
			$v['send_mid'] = $r['mid'];
			
			$domain_list = str_replace("'","",$v['domain_list']);
			$domain_list = explode(",",$domain_list);
			$domainArr = "";
			$i = 0;
			foreach ($domain_list as $k) {
					$i++;
					if($i<4)$domainArr .= $k."<br>";
			}			
			$v['domain_more'] = $domainArr;
			
			$ret[] = $v;
		}		

		//分页开始
        $pager = $pan_domain_push->spPager()->getPager();
        if ($pager['total_page'] > 5) {
            if ($page <= 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], $page - 3, 5);
            }
        }
        $this->pager = $pager;			
        $this->ret = $ret;
		$this->status = $status;
        $this->module = "push";
        $this->act = 'push_accept_list';
        $this->display('amui/push/accept_list.html');		
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