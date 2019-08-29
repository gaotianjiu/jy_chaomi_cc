<?php

class alipay extends spController {

    function __construct() {
        parent::__construct();
		header("Content-type: text/html; charset=utf-8"); 		
    }
    //支付宝同步回调处理
    function return_url() {
        $pp = $this->tbyzsj();
        //充值操作
        $this->czcz($pp);
        $this->jump("http://my.chaomi.cc/capital/rechargeOrder");
    }

    //支付宝异步回调处理
    function notify_url() {
        $pp = $this->ybyzsj();
        //充值操作
        $this->czcz($pp);
        echo "success";//请不要修改或删除
		exit();
    }

    //同步返回验证数据,并返回数据
    function tbyzsj() {
		//计算得出通知验证结果
		require_once("include/alipay/alipay.config.php");
		require_once("include/alipay/alipay_notify.class.php");			
		$alipayNotify = new AlipayNotify($alipay_config);
		$verify_result = $alipayNotify->verifyReturn();
		if($verify_result) { //验证成功		
			//商户订单号
			$out_trade_no = $_GET['out_trade_no'];
			//支付宝交易号
			$trade_no = $_GET['trade_no'];
			//交易状态
			$trade_status = $_GET['trade_status'];
			if($_GET['trade_status'] == 'TRADE_FINISHED' || $_GET['trade_status'] == 'TRADE_SUCCESS') {
				 $parameter  = array(
					'out_trade_no'=>$out_trade_no,
					'trade_no'=>$trade_no,
					'trade_status'=>$trade_status
				 );
				 return $parameter;
			}else {
			  echo "交易状态错误，trade_status=".$_GET['trade_status'];
			  exit();
			}
		}else {
			echo "签名验证失败";
			exit();
		}
    }
    //异步返回验证数据,并返回数据
    function ybyzsj() {
		require_once("include/alipay/alipay.config.php");
		require_once("include/alipay/alipay_notify.class.php");			
		//计算得出通知验证结果
		$alipayNotify = new AlipayNotify($alipay_config);
		$verify_result = $alipayNotify->verifyNotify();
		if($verify_result) {//验证成功
			//商户订单号
			$out_trade_no = $_POST['out_trade_no'];
			//支付宝交易号
			$trade_no = $_POST['trade_no'];
			//交易状态
			$trade_status = $_POST['trade_status'];
			if($_POST['trade_status'] == 'TRADE_FINISHED' || $_POST['trade_status'] == 'TRADE_SUCCESS') {
				 $parameter  = array(
					'out_trade_no'=>$out_trade_no,
					'trade_no'=>$trade_no,
					'trade_status'=>$trade_status
				 );
				 return $parameter;
			}else{
				echo "fail";
				exit();
			}
		}else {
			//验证失败
			echo "fail";
			exit();
		}
    }

    //充值操作
    function czcz($parameter) {
        //充值订单号和支付宝交易号
        $orderid = $parameter['out_trade_no'];//充值订单号
        $trade_no = $parameter['trade_no'];//支付宝交易号
        $userreq_ip = trim(get_client_ip());
		
        //判断数据库里的支付状态是否已经确认支付
        $member_order = spClass("lib_member_order");
        $member_order_result = $member_order->find(array('order_id' => $orderid));
        if (!$member_order_result) {
            $this->error('支付订单不存在');
            exit();
        }
		
		$key_name = 'pay_czcz_id'.$orderid;//当前支付订单操作中
		if(false === cache_a($key_name,time(),60)){
			echo '支付订单正在入账中';
			$this->jump("http://my.chaomi.cc/capital/rechargeOrder");
			exit();
		}	
		echo '支付订单正在入账中，请稍候等待跳转...';
        $status = $member_order_result['status']; //订单状态
        $uid = $member_order_result['uid']; //用户ID
        $amount = $member_order_result['amount']; //金额
        $time = $member_order_result['time']; //订单时间
        $note = $member_order_result['note'].'(订单号:'.$orderid.')-支付宝交易号:'.$trade_no;; //订单名称
		
			//------------限制系统全部用户请求操作域名相关---判断后台是否在处理操作待续费、待下架----------begin
			$domain_action = 'domain_action';
			cache_a($domain_action,'user',5);
			if(cache_a($domain_action)=='system'){
				echo '很抱歉，系统繁忙，请稍后刷新重试。';
				$this->jump("http://my.chaomi.cc/capital/rechargeOrder");
				exit();	
			}
			//------------限制系统全部用户请求操作域名相关---判断后台是否在处理操作待续费、待下架----------end	
		
			//------------限制用户并发请求操作域名相关----------begin
			$domain_action_uid = 'domain_action_uid_'.$uid;
			if(false === cache_a($domain_action_uid,time(),10)){
				echo '很抱歉，系统队列繁忙，请稍后刷新重试。';	
				$this->jump("http://my.chaomi.cc/capital/rechargeOrder");
				exit();				
			}
			//------------限制用户并发请求操作域名相关----------end	
		 if ($status == 302) {
			 //避免支付平台多次发送结果
			 cache_a($domain_action_uid,null);
		 }
		 
        //添加流水时间
        $time1 = time();
        if ($status == 301) {
            $sp = spClass('lib_member_account');
			$sql_sw = false;
			$sp->runSql("SET AUTOCOMMIT=0");
			$sp->runSql('BEGIN');//开启事务		
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
			//修改订单为已完成状态;
			$member_order->update(array('order_id' => $orderid), array('status' => 302, 'other_id' => $trade_no,'update_time'=>$time1));	
			//---添加站内信---begin
			$type =  '901';
			$tit  =  '恭喜，充值预付款'.$amount.'元已成功入帐!';
			$txt  =  '支付宝充值订单号：'.$orderid.'，充值金额：'.$amount.'元，本次充值已成功入账。';
			web_msg_send($tit,$type,$uid,$txt);	
			//---添加站内信---end
			//----邮件后台提醒----begin
				$content = array();
				$content['to'] = array('pwpet@qq.com');
				$content['sub'] = array('%content%'=>array('用户UID：'.$uid.'，通过支付宝提交了充值已入账，金额：'.$amount.'元'));
				$new_content = json_encode($content);
				send_mail('pwpet@qq.com','【炒米后台提醒】有用户提交了充值！',$new_content,8);	
				$content['to'] = array('605466504@qq.com');
				$new_content = json_encode($content);
				send_mail('605466504@qq.com','【炒米后台提醒】有用户提交了充值！',$new_content,8);					
			//----邮件后台提醒----end
			send_mobile_email($uid,"炒米网(chaomi.cc)成功充值".$amount."元提醒","您于".date("Y-m-d H:i:s")."在平台成功充值了".$amount."元。");
			$sql_sw = true;
			if(false===$sql_sw){
				$sp->runSql('ROLLBACK'); //回滚事务
				write($orderid.'充值出错，事务回滚!!!');
				exit('系统事务出错!!');
			}else{	
				$sp->runSql('COMMIT');//执行事务
				write('支付宝交易号：'.$trade_no.'，网站订单号：'.$orderid.'充值成功，UID：'.$uid.'，入账前总金额'.$member_Account_result[0]['balance'].'，入账金额'.$amount.'，入账后总金额'.$new_amount.'，执行前查总余额'.$member_Account_result[0]['balance'].'元->执行此条时总余额'.$new_amount.'元');
				cache_s($key_name,null);
				cache_a($domain_action_uid,null);
			}			
        }
    }
}
