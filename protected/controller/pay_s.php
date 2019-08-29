<?php
// error_reporting(E_ALL || ~E_NOTICE);

//第三方软件辅助收款接口

class pay_s extends spController
{
    function __construct(){
        parent::__construct();
    }	
	
	function push(){
		//推送前台
		$sso_user = check();
        if ($sso_user == true) {
            $uid = $sso_user['uid'];
            $mid = $sso_user['mid'];
        } else {
			json_s(array('status'=>201,'msg'=>'未登录'));
        }		
		$lib_member_pay_s = spClass('lib_member_pay_s');
		$ret = $lib_member_pay_s->find(array('uid'=>$uid,'push_time'=>0));
		if($ret){
			$msg = '您在'.$ret['pay_time'].'付款的'.$ret['amount'].'元已成功入帐!';
			$lib_member_pay_s->update(array('uid'=>$uid,'id'=>$ret['id']),array('push_time'=>time()));
			json_s(array('status'=>200,'msg'=>$msg));
		}else{
			json_s(array('status'=>201,'msg'=>''));
		}
	}
    function alipay_notify(){
		//支付宝异步通知收款结果，由软件请求推送返回
		$post = $this->spArgs();
		//您的收款支付宝帐号
		$alidirect_account = "pay@chaomi.cc";
		//您在www.zfbjk.com的商户ID
		$alidirect_pid = "27027";
		//您在www.zfbjk.com的商户密钥
		$alidirect_key = "6a5d720dc221ad7436b643b4e057a5e4";
		//返回数组		
		// Array
		// (
			// [alipay_account] => pay@chaomi.cc
			// [tenpay_account] => pay@chaomi.cc
			// [tradeNo] => 2015022788888888888
			// [Money] => 10.1
			// [title] => 6368
			// [memo] => chaomi.cc
			// [Gateway] => alipay
			// [Sign] => 6FE84CD50FE0F1E1F6B73970BBD2F56F
			// [Paytime] => 2018-07-03 11:52:34
		// )
		$tradeNo = $post['tradeNo'];//交易单号
		$Money = $post['Money'];//通知付款金额
		$title = $post['title'];//备注ID
		$memo = $post['memo']; //附加参数
		$alipay_account = $post['alipay_account'];
		$Gateway = $post['Gateway'];//付款通道
		$Paytime = $post['Paytime'];//支付时间
		$Sign = $post['Sign'];//签名sign
		$_sign = strtoupper(md5($alidirect_pid . $alidirect_key . $tradeNo . $Money . $title . $memo));
		if($_sign !== strtoupper($Sign)){
			exit('Fail');//Sign签名验证失败
		}
		
		
		$lib_member_pay_s = spClass('lib_member_pay_s');
	
		$mid_info = spClass("pub_user")->find(array('mid'=>$title));
		$uid = $mid_info['uid'];
		if(empty($uid)){
			exit('备注：支付uid不存在');
		}	
		if(strtotime($Paytime)<1530547200){
			exit('时间错误!2018-07-03');
		}

		$pay_info = $lib_member_pay_s->find(array('order_id'=>$tradeNo));
		if($pay_info['status']>0){
			exit('交易单号已存在！');
		}
		
		
		$key_name = 'pay_fuzhu_id_'.$tradeNo;//当前支付订单操作中
		if(false === cache_a($key_name,time(),60)){
			echo '支付订单正在入账中';
			exit();
		}
		
		//------------限制用户并发请求操作域名相关----------begin
		$domain_action_uid = 'domain_action_uid_'.$uid;
		if(false === cache_a($domain_action_uid,time(),10)){
			echo '很抱歉，系统队列繁忙，请稍后刷新重试。';	
			exit();				
		}
		//------------限制用户并发请求操作域名相关----------end			
		$Money = bcadd($Money,0,2);//强制转换成最多只保留两位小数点，防止精度误差
		$sp = spClass('lib_member_account');
		$sql_sw = false;
		$sp->runSql("SET AUTOCOMMIT=0");
		$sp->runSql('BEGIN');//开启事务				
		
		//---添加辅助收款表记录---begin
		$data = array(
			'order_id'=>$tradeNo,
			'uid'=>$uid,
			'pay_type'=>$Gateway,
			'amount'=>$Money,
			'pay_time'=>$Paytime,
			'pay_note'=>$title,
			'note'=>'系统自动入账',
			'status'=>1,
			'push_time'=>0
		);
		$lib_member_pay_s->create($data);
		//---添加辅助收款表记录---end
		
        $orderid = 'fz_'.$tradeNo;//流水订单号
        $trade_no = $tradeNo;//支付宝交易号
		$amount = $Money;
		$userreq_ip = trim(get_client_ip());
		$time1 = strtotime($Paytime);
		$note = '支付宝辅助收款-系统自动入账：'.$amount.'元';
		
		//-----------查询用户账户---------
		$bal_sql = "select * from ykjhqcom.lib_member_account where uid = $uid FOR UPDATE"; //*****只要是查余额表的，必须前面开事务，并且后面加上 FOR UPDATE 单行锁表
		$member_Account_result = $sp->findSql($bal_sql);		
		$new_amount = $member_Account_result[0]['balance'] + $amount;
		if ($member_Account_result) {
			$old_draw = $member_Account_result[0]['draw']; //原不可提现金额小于0时,强制赋值为0
			if ($old_draw < 0) {
				$draw_f = "$amount";
			} else {
				$draw_f = "draw+$amount";
			}
			$member_Account_sql = "update ykjhqcom.lib_member_account set balance=balance+$amount,draw=$draw_f where uid=$uid";
			$sp->runSql($member_Account_sql);
		} else {
			$sp->create(array('uid' => $uid, 'balance' => $amount, 'draw' => $amount));
		}			

		//准备添加流水相关数据,与流水表字段名相同
		$lsarr = array(
			'uid' => $uid,
			'order_id' => $orderid,
			'type' => '501',
			'amount' => $amount,
			'ip' => $userreq_ip,
			'deal_time' => $time1,
			'note' => $note,
			'balance' => $new_amount,
			'y' => date("Y", $time1),
			'm' => date("m", $time1),
			'd' => date("d", $time1)
		);
		//添加流水
		$member_records = spClass('lib_member_records');
		$member_records->create($lsarr);
		//添加订单为已完成状态;
		$member_order = spClass("lib_member_order");
		$order_data = array('order_id' => $orderid, 'uid' => $uid, 'type' => 100, 'other_id'=> $trade_no , 'amount' => $amount, 'time' => $time1,'update_time'=>time(), 'note' => '炒米网预付款', 'status' => 302, 'pay_type' => 402);
		$member_order->create($order_data);	
		//---添加站内信---begin
		$type =  '901';
		$tit  =  '恭喜，充值预付款'.$amount.'元已成功入帐!';
		$txt  =  '支付宝交易单号：'.$trade_no.'，充值金额：'.$amount.'元，本次充值系统已成功自动入账。';
		web_msg_send($tit,$type,$uid,$txt);	
		//---添加站内信---end
		//----邮件后台提醒----begin
			$content = array();
			$content['to'] = array('pwpet@qq.com');
			$content['sub'] = array('%content%'=>array('[辅助]用户UID：'.$uid.'，mid：'.$title.'，通过支付宝辅助充值已入账，金额：'.$amount.'元'));
			$new_content = json_encode($content);
			send_mail('pwpet@qq.com','【炒米后台提醒】[系统自动辅助]有用户提交了充值！',$new_content,8);	
			$content['to'] = array('605466504@qq.com');
			$new_content = json_encode($content);
			send_mail('605466504@qq.com','【炒米后台提醒】[系统自动辅助]有用户提交了充值！',$new_content,8);					
		//----邮件后台提醒----end
		send_mobile_email($uid,"炒米网(chaomi.cc)成功充值".$amount."元提醒","您于".date("Y-m-d H:i:s")."在平台成功充值了".$amount."元。");
				
		
		$sql_sw = true;
		if(false===$sql_sw){
			$sp->runSql('ROLLBACK'); //回滚事务
			cache_a($domain_action_uid,null);
			cache_a($key_name,null);
			exit('系统事务出错!!');
		}else{	
			$sp->runSql('COMMIT');//执行事务
			cache_a($domain_action_uid,null);
			cache_a($key_name,null);			
			exit('Success');//支付成功
		}
			
    }
}