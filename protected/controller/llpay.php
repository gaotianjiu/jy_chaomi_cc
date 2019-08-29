<?php

class llpay extends spController {

    function __construct() {
        parent::__construct();
    }
    //连连支付(炒米盘)在线充值同步回调处理
    function czreturnurl() {
        $pp = $this->tbyzsj();
        //充值操作
        $this->czcz($pp);
        $this->jump("http://my.chaomi.cc/capital/rechargeOrder");
    }

    //连连支付(炒米)在线充值异步回调处理
    function cznotifyurl() {
        $pp = $this->ybyzsj();
        //充值操作
        $this->czcz($pp);
		header("Content-type: text/html; charset=utf-8"); 
        die("{'ret_code':'0000','ret_msg':'交易成功'}"); //请不要修改或删除
    }

    //同步返回验证数据,并返回数据
    function tbyzsj() {
        //商户编号
        $oid_partner = trim($this->spArgs("oid_partner"));
        //签名方式
        $sign_type = trim($this->spArgs("sign_type"));
        //签名
        $sign = trim($this->spArgs("sign"));
        //商户订单时间
        $dt_order = trim($this->spArgs("dt_order"));
        //商户唯一订单号
        $no_order = trim($this->spArgs("no_order"));
        //连连支付支付单号
        $oid_paybill = trim($this->spArgs("oid_paybill"));
        //交易金额
        $money_order = trim($this->spArgs("money_order"));
        //支付结果
        $result_pay = trim($this->spArgs("result_pay"));
        //清算日期
        $settle_date = trim($this->spArgs("settle_date"));
        //订单描述
        $info_order = trim($this->spArgs("info_order"));
        //支付方式
        $pay_type = trim($this->spArgs("pay_type"));
        //银行编号
        $bank_code = trim($this->spArgs("bank_code"));

        //加载连连支付相关类;
        $llpay = spClass("llpay_class");

        echo($oid_partner);
        //对商户号进行对比.
        /*
          if($oid_partner!=$llpay->llpay_config['oid_partner']){
          $this->success('非法请求', spUrl('member', 'index'));
          exit();
          }
         */
        //生成签名结果
        $parameter = array(
            'oid_partner' => $oid_partner,
            'sign_type' => $sign_type,
            'dt_order' => $dt_order,
            'no_order' => $no_order,
            'oid_paybill' => $oid_paybill,
            'money_order' => $money_order,
            'result_pay' => $result_pay,
            'settle_date' => $settle_date,
            'info_order' => $info_order,
            'pay_type' => $pay_type,
            'bank_code' => $bank_code,
        );

        //验证签名是否正确

        if (!$llpay->getSignVeryfy($parameter, $sign)) {
            $this->error('验证签名数据不正确!');
            exit();
        }

        return $parameter;
    }

    //异步返回验证数据,并返回数据
    function ybyzsj() {
        //获取JSON流
        $str = file_get_contents("php://input");
        //加载连连支付分析JSON流
        $json = spClass('llpay_json');
        //加载连连支付相关类;
        $llpay = spClass("llpay_class");
        //分析流数据
        $val = $json->decode($str);
        //商户编号
        $oid_partner = trim($val->{'oid_partner'});
        //签名方式
        $sign_type = trim($val->{'sign_type'});
        //签名
        $sign = trim($val->{'sign'});
        //商户订单时间
        $dt_order = trim($val->{'dt_order'});
        //商户唯一订单号
        $no_order = trim($val->{'no_order'});
        //连连支付支付单号
        $oid_paybill = trim($val->{'oid_paybill'});
        //交易金额
        $money_order = trim($val->{'money_order'});
        //支付结果
        $result_pay = trim($val->{'result_pay'});
        //清算日期
        $settle_date = trim($val->{'settle_date'});
        //订单描述
        $info_order = trim($val->{'info_order'});
        //支付方式
        $pay_type = trim($val->{'pay_type'});
        //银行编号
        $bank_code = trim($val->{'bank_code'});
        //签约协议号
        $no_agree = trim($val->{'no_agree'});
        //证件类型
        $id_type = trim($val->{'id_type'});
        //证件号码
        $id_no = trim($val->{'id_no'});
        //银行账号姓名
        $acct_name = trim($val->{'acct_name'});

        //对商户号进行对比.
        if ($oid_partner != $llpay->llpay_config['oid_partner']) {
            die("{'ret_code':'9999','ret_msg':'交易失败'}");
            exit();
        }

        //生成签名结果
        $parameter = array(
            'oid_partner' => $oid_partner,
            'sign_type' => $sign_type,
            'dt_order' => $dt_order,
            'no_order' => $no_order,
            'oid_paybill' => $oid_paybill,
            'money_order' => $money_order,
            'result_pay' => $result_pay,
            'settle_date' => $settle_date,
            'info_order' => $info_order,
            'pay_type' => $pay_type,
            'bank_code' => $bank_code,
            'no_agree' => $no_agree,
            'id_type' => $id_type,
            'id_no' => $id_no,
            'acct_name' => $acct_name
        );

        //验证签名是否正确
        if (!$llpay->getSignVeryfy($parameter, $sign)) {
            die("{'ret_code':'9999','ret_msg':'交易失败'}");
            exit();
        }

        return $parameter;
    }

    //充值操作
    function czcz($parameter) {
        //充值订单号和连连返回订单号
        $orderid = $parameter['no_order'];
        $oid_paybill = $parameter['oid_paybill'];

        //判断数据库里的支付状态是否已经确认支付
        $member_order = spClass("lib_member_order");

        //加载连连支付相关类;
        $llpay = spClass("llpay_class");

        $userreq_ip = trim(get_client_ip());
        $member_order_result = $member_order->find(array('order_id' => $orderid));
		header("Content-type: text/html; charset=utf-8"); 
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
        $note = $member_order_result['note'].'(订单号:'.$orderid.')-连连支付交易号:'.$oid_paybill; //订单名称
		
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
			$member_order->update(array('order_id' => $orderid), array('status' => 302, 'other_id' => $oid_paybill,'update_time'=>$time1));	
			//---添加站内信---begin
			$type =  '901';
			$tit  =  '恭喜，充值预付款'.$amount.'元已成功入帐!';
			$txt  =  '连连支付充值订单号：'.$orderid.'，充值金额：'.$amount.'元，本次充值已成功入账。';
			web_msg_send($tit,$type,$uid,$txt);	
			//---添加站内信---end
			//----邮件后台提醒----begin
				$content = array();
				$content['to'] = array('pwpet@qq.com');
				$content['sub'] = array('%content%'=>array('用户UID：'.$uid.'，通过连连支付提交了充值已入账，金额：'.$amount.'元'));
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
				write('连连支付交易号：'.$oid_paybill.'，网站订单号：'.$orderid.'充值成功，UID：'.$uid.'，入账前总金额'.$member_Account_result[0]['balance'].'，入账金额'.$amount.'，入账后总金额'.$new_amount.'，执行前查总余额'.$member_Account_result[0]['balance'].'元->执行此条时总余额'.$new_amount.'元');
				cache_s($key_name,null);
				cache_a($domain_action_uid,null);
			}			
        }
    }
}
