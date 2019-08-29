<?php

/*
 * 财务管理模块 capital
 * 充值 recharge
 * 生成订单 orderMaker
 * 充值订单  rechargeOrder
 * 财务记录  capitalRecord
 * 消费记录  consumerRecord
 * 入款记录  incomeRecord
 */
define("web_md5", "_chaomi_cc");
class capital extends spController {
    function __construct() {
        parent::__construct();
		$sso_user = check();
        if ($sso_user == true) {
            $this->uid = $sso_user['uid'];
            $this->mid = $sso_user['mid'];
        } else {
            re_login();
            exit();
        }
    }
    //我要充值
    function recharge() {
        $uid = $this->uid;
        //判断是否绑定手机号
        $gb_user = spClass('pub_user');
        $r = $gb_user->find(array("uid" => $uid));
        if (($r["mobile"] == "") || (!$r["mobile"])) {
            $this->error('请先绑定手机', '/user/bindMobile');
        }
        $this->module = "capital";
        $this->act = "recharge";
        $this->display("amui/member/am_financial_recharge.html");
    }

    //生成订单
    function orderMaker() {
        //连连支付相关类;
        $llpay = spClass("llpay_class");
		$ip = trim(get_client_ip()); //取得客户端ip
		$uid = $this->uid;
		$mid = $this->mid;
		$pay_type = $this->spArgs("pay_type");// 401连连支付 402支付宝
		header('Content-Type:text/html;charset=utf-8');
        //商户用户唯一编号
        $user_id = $this->uid;

        //商户唯一订单号
        $no_order = $llpay->llpay_orderid($user_id);

        //用户端申请IP
        $userreq_ip = str_replace(".", "_", $ip);

        //商品名称 
        $name_goods = '炒米网预付款';

        //订单描述
        $info_order = '炒米网预付款(ID：'.$mid.')';

        //交易金额 
        $money_order = trim($this->spArgs("w_money_order"));
		$money_order  = bcadd($money_order,0,2);//强制转换成最多只保留两位小数点，防止精度误差	

        if (!is_numeric($money_order)) {
            echo("<script>alert('充值金额不能为空');history.go(-1);</script>");
            exit();
        }

        if ($money_order <1) {
            echo("<script>alert('充值金额最小1元起');history.go(-1);</script>");
            exit();
        }
				//------------限制相同IP并发请求----------begin
				$ip_key = md5($ip);
				$key_name = 'orderMaker_ip_'.$ip_key;
				if(false === cache_a($key_name,time(),3))exit('很抱歉，系统繁忙请稍后3秒后重试!');	
				//------------限制相同IP并发请求----------end
				
				//------------限制相同帐号并发请求----------begin
				$key_name = 'orderMaker_uid_'.$uid;
				if(false === cache_a($key_name,time(),3))exit('很抱歉，系统繁忙请稍后3秒后重试!');		
				//------------限制相同帐号并发请求----------end
				
				//------------限制相同帐号请求次数，防并发撞库----------begin
				$key_name = 'orderMaker_uid_m_'.md5($uid);
				if(cache_s($key_name)>100)exit('很抱歉，请求次数限制，请稍后1小时后再操作!');			
				cache_s($key_name,intval(cache_s($key_name))+1,3600);
				//------------限制相同邮箱请求次数，防并发撞库----------end
		
		// if($money_order==1)$pay_type = 402;
		if($pay_type==402){
			//支付宝充值 
			require_once("include/alipay/alipay.config.php");
			require_once("include/alipay/alipay_submit.class.php");
			/**************************请求参数**************************/
					//商户订单号，商户网站订单系统中唯一订单号，必填
					$out_trade_no = $no_order;
					//订单名称，必填
					$subject = '炒米网预付款';
					//付款金额，必填
					$total_fee = $money_order;
					//商品描述，可空
					$body = '炒米网预付款(ID：'.$mid.')';

			/************************************************************/		
			//构造要请求的参数数组，无需改动
			$parameter = array(
					"service"       => $alipay_config['service'],
					"partner"       => $alipay_config['partner'],
					"seller_id"  => $alipay_config['seller_id'],
					"payment_type"	=> $alipay_config['payment_type'],
					"notify_url"	=> $alipay_config['notify_url'],
					"return_url"	=> $alipay_config['return_url'],
					
					"anti_phishing_key"=>$alipay_config['anti_phishing_key'],
					"exter_invoke_ip"=>$alipay_config['exter_invoke_ip'],
					"out_trade_no"	=> $out_trade_no,
					"subject"	=> $subject,
					"total_fee"	=> $total_fee,
					"body"	=> $body,
					// "enable_paymethod"	=> 'directPay^bankPay^cartoon^cash^creditCardExpress^debitCardExpress',//支付渠道
					"_input_charset"	=> trim(strtolower($alipay_config['input_charset']))
					//其他业务参数根据在线开发文档，添加参数.文档地址:https://doc.open.alipay.com/doc2/detail.htm?spm=a219a.7629140.0.0.kiX33I&treeId=62&articleId=103740&docType=1
					//如"参数名"=>"参数值"	
			);			
			//订单类型 100充值
			$pub_type = 100;
			//订单状态 301待支付
			$pub_status = 301;
			//支付类型 401连连支付 402支付宝
			$pub_pay_type = 402;
			//记录到数据库
			$financial_order = spClass("lib_member_order");
			$financial_order->create(array('order_id' => $no_order, 'uid' => $user_id, 'type' => $pub_type, 'amount' => $money_order, 'time' => time(), 'note' => $subject, 'status' => $pub_status, 'pay_type' => $pub_pay_type));
			//发起请求
			echo '正在跳转到支付页面中，请稍等一会...';
			$alipaySubmit = new AlipaySubmit($alipay_config);
			$html_text = $alipaySubmit->buildRequestForm($parameter,"get", "确认");
			echo $html_text;
			exit();
			
		}
        //获取用户注册时间
        $pub_user = spClass('pub_user');
        $result = $pub_user->find(array('uid' => $user_id));
        if ($result) {
            $user_info_dt_register = date('YmdHis', $result['regtime']);
        } else {
            echo("<script>alert('用户注册时间不能为空');history.go(-1);</script>");
            exit();
        }

        //风险控制参数
        $risk_item = "\"user_info_mercht_userno\":\"" . $user_id . "\",\"user_info_dt_register\":\"" . $user_info_dt_register . "\",\"frms_ware_category\":\"2999\"";

        //获取实名认证数据
        $pan_member_card = spClass('pan_member_card');
        $member_card_result = $pan_member_card->find(array('uid' => $user_id, 'status' => 2));
        if ($member_card_result) {
            $card_status = $member_card_result['status'];
            if ($card_status == 2) {
                $card_no = $member_card_result['card'];
                $card_name = $member_card_result['first_name'] . $member_card_result['last_name'];
                $risk_item = $risk_item . ",\"user_info_full_name\":\"" . $card_name . "\",\"user_info_id_type\":\"0\",\"user_info_id_no\":\"" . $card_no . "\",\"user_info_identify_state\":\"1\",\"user_info_identify_type\":\"3\"";
            }
        }

        //获取用户手机
        $gb_user = spClass('pub_user');
        $gb_user_result = $gb_user->find(array("uid" => $user_id));
        if ($gb_user_result) {
            $user_phone = $gb_user_result['mobile'];
            $risk_item = $risk_item . ",\"user_info_bind_phone\":\"" . $user_phone . "\"";
        }

        $risk_item = "{" . $risk_item . "}";

        //同步回调地址
        $return_url = 'http://my.chaomi.cc/llpay/czreturnurl';

        //异步回调地址
        $notify_url = 'http://my.chaomi.cc/llpay/cznotifyurl';

        //构造要请求的参数数组
        $parameter = array(
            //版本号
            "version" => $llpay->llpay_config['version'],
            //参数字符编码集
            "charset_name" => $llpay->llpay_config['input_charset'],
            //支付交易商户编号
            "oid_partner" => $llpay->llpay_config['oid_partner'],
            //签名方式
            "sign_type" => $llpay->llpay_config['sign_type'],
            //用户端申请IP
            "userreq_ip" => $userreq_ip,
            //证件类型
            "id_type" => $llpay->llpay_config['id_type'],
            //订单有效时间 
            "valid_order" => $llpay->llpay_config['valid_order'],
            //商户用户唯一编号
            "user_id" => $user_id,
            //时间戳
            "timestamp" => $llpay->local_date('YmdHis', time()),
            //商户业务类型 
            "busi_partner" => 101001,
            //商户唯一订单号
            "no_order" => $no_order,
            //商户订单时间 ;
            "dt_order" => $llpay->local_date('YmdHis', time()),
            //商品名称 
            "name_goods" => $name_goods,
            //订单描述
            "info_order" => $info_order,
            //交易金额 
            "money_order" => $money_order,
            //服务器异步通知地址 
            "notify_url" => $notify_url,
            //支付结束回显URL 
            "url_return" => $return_url,
            //风险控制参数 
            "risk_item" => $risk_item
        );

        //订单类型 100充值
        $pub_type = 100;

        //订单状态 301待支付
        $pub_status = 301;

        //支付类型 401连连支付
        $pub_pay_type = 401;

        //记录到数据库
        $financial_order = spClass("lib_member_order");
        $financial_order->create(array('order_id' => $no_order, 'uid' => $user_id, 'type' => $pub_type, 'amount' => $money_order, 'time' => time(), 'note' => $name_goods, 'status' => $pub_status, 'pay_type' => $pub_pay_type));

        //生成要请求给连连支付的参数数组
		echo '正在跳转到支付页面中，请稍等一会...';
        $pp = $llpay->buildRequestForm($parameter, 'post', '确认');
        echo '<div style="display:none;">'.$pp.'</div>';
        exit();
    }

    //充值订单
    function rechargeOrder() {
        $uid = $this->uid;
        $page=intval($this->spArgs('page',1));
        if($page <1) $page=1;
        //排序方式
        $sort = " order by id desc";
        //查询条件
        $condition = ' where uid=' . $uid . ' and type=100 ';
        //查询充值订单
        $rg = spClass('lib_member_order');
        //查询数据
        $rd = $rg->spPager($page,pgsize)->findSql('select * from ykjhqcom.lib_member_order ' . $condition . $sort );
        //分页参数
        $pager=$rg->spPager()->getPager();
        if ($pager['total_page'] > 5) {
            if ($page > 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], $page - 3, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            }
        }
        $this->order = $rd;
        $this->pager = $pager;
        $this->module = "capital";
        $this->act = "rechargeOrder";
        $this->display('amui/member/am_financial_rechargeOrder.html');
    }

    //财务记录
    //$status=500   消费记录
    //$status=501   入款记录
    function capitalRecord() {
        $uid = $this->uid;
        //设置每页显示10条
        $pglimit = pgsize;
        //获取当前页码
        $pgno = intval($this->spArgs('page', 1));
        if($pgno < 1) $pgno=1;

        $sp = spClass('lib_member_records');
		$sp_key = spClass('lib_key');
        //查询条件
        $row=array('uid' => $uid);
        //状态
        $status=$this->spArgs('status',0);
        if($status >0) {
            if ($status == 500) {      //消费记录
                $row['type'] = 500;
            } elseif ($status == 501) {  //入款记录
                $row['type'] = 501;
            }
        }
        $res = $sp->spPager($pgno, $pglimit)->findAll($row, 'id desc');
        $pager = $sp->spPager()->getPager();

        //500 消费 501入款  502冻结  503解冻  504退款
        $lib_key_r = $sp_key->findAll('id>=500 and id<600');
        foreach ($res as $key => $value) {
            foreach ($lib_key_r as $kk => $kv) {
                if ($res[$key]["type"] == $lib_key_r[$kk]["id"]) {
                    $res[$key]["statename"] = $lib_key_r[$kk]["key"];
                    break;
                }
            }
        }
        //分页参数
        if ($pager['total_page'] > 5) {
            if ($pgno > 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], $pgno - 3, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            }
        }

        $this->res = $res;
        $this->pager = $pager;
        $this->status=$status;
        $this->module = "capital";
        $this->act = "capitalRecord";
        $this->display('amui/member/am_financial_accountRecord.html');
    }

    //银行信息
    function bankInfo() {
        $uid = $this->uid;
		$act = trim($this->spArgs("act"));
		if($act=='delete'){	//删除银行卡
			$id = intval($this->spArgs('id'));
			if(!$id || empty($id))json_s(array('status'=>201,'msg'=>'参数ID不能为空'));
			$lib_member_bank = spClass('lib_member_bank');
			$ret = $lib_member_bank->update(array('id' => $id, 'uid' => $uid,'status'=>0),array('status'=>-1));
			$up_count = $lib_member_bank->affectedRows();//影响行数
			if($ret && $up_count>=1){
				json_s(array('status'=>200,'msg'=>'银行卡删除成功'));
			}else{
				json_s(array('status'=>201,'msg'=>'银行卡删除失败，可能权限不足或已删除成功！'));
			}		
		}		
		if($act=='create'){	//添加绑定银行卡
			//查询用户实名;
			$pan_member_card = spClass('pan_member_card');
			$r = $pan_member_card->find(array('uid' => $uid, 'status' => 2));
			if ($r) {
				$sm_name = $r['first_name'] . $r['last_name'];
			} else {
				json_s(array('status'=>201,'msg'=>'请先实名登记后再添加银行卡'));
			}
			$lib_member_bank = spClass('lib_member_bank');
			if($lib_member_bank->findCount(array('uid'=>$uid,'status'=>0))>8)json_s(array('status'=>201,'msg'=>'当前帐号绑定的银行卡数量超限制'));
			$blankname = $this->spArgs('blankname');
			$bankno = $this->spArgs('bankno');
			$rebankno = $this->spArgs('rebankno');
			$blankadder = $this->spArgs('blankadder');
			if ($blankname == '' || $blankname == '0') {
				json_s(array('status'=>201,'msg'=>'开户行不能为空'));
			}
			if ($bankno != $rebankno) {
				json_s(array('status'=>201,'msg'=>'两次输入的卡号不一样'));
			}
			if ($blankadder == '') {
				json_s(array('status'=>201,'msg'=>'开户地址不能为空'));
			}

			//添加数据
			$ret = $lib_member_bank->create(array('uid' => $uid, 'username' => $sm_name, 'bankname' => $blankname, 'bankno' => $bankno, 'bankadder' => $blankadder));
			$up_count = $lib_member_bank->affectedRows();//影响行数			
			if($ret && $up_count>=1){
				json_s(array('status'=>200,'msg'=>'银行卡添加绑定成功'));
			}else{
				json_s(array('status'=>201,'msg'=>'银行卡添加绑定失败'));
			}	
		}		
        //获取当前页码
        $pgno=intval($this->spArgs('page',1));
        if($pgno<1) $pgno=1;

        $lib_member_bank = spClass('lib_member_bank');
        $r = $lib_member_bank->spPager($pgno, pgsize)->findAll(array('uid' => $uid,'status'=>0));

        //分页参数
        $pager = $lib_member_bank->spPager()->getPager();
        if ($pager['total_page'] > 5) {
            if ($pgno > 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], $pgno - 3, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            }
        }

        $this->banks = $r;
        $this->pager=$pager;
        $this->module = "capital";
        $this->act = "cashManage";
        $this->display('amui/member/am_financial_bankInfo.html');
    }

    //银行卡
    function bindBank() {
        $uid = $this->uid;
		$sm_name = '';
		$pan_member_card = spClass('pan_member_card');
		$r = $pan_member_card->find(array('uid' => $uid, 'status' => 2));
		if ($r)$sm_name = $r['first_name'] . $r['last_name'];		
        $this->sm_name = $sm_name;
        $this->module = "capital";
        $this->act = "cashManage";
        $this->display('amui/member/am_financial_bindBank.html');
    }
    //申请提现
    function cashApply() {
        $uid = $this->uid;
		$ip = get_client_ip();
        //查找提现的账号信息
        $lib_member_bank = spClass('lib_member_bank');
        $lib_member_bank_r = $lib_member_bank->findAll(array('uid' => $uid,'status'=>0));
		$act = trim($this->spArgs("act"));
		$_info = spClass('pan_member_info')->find(array('uid'=>$uid));
		if(!$_info['qq'])d301('http://my.chaomi.cc/user/memberInfo?tip=n');
		if($act=='create'){	//添加提现
			if($uid==21836 || $uid==21841)json_s(array('status'=>201,'msg'=>'后台限制此功能'));
			$d_pass = $this->spArgs("d_pass"); //安全码
			if ($d_pass == ""){
				json_s(array('status'=>201,'msg'=>'请输入安全码','ids'=>'#d_pass'));
			}
			//------------限制相同IP并发请求----------begin
			$ip_key = md5($ip);
			$key_name = 'cashApply_ip_'.$ip_key;
			if(false === cache_a($key_name,time(),1))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后1秒后重试!'));	
			//------------限制相同IP并发请求----------end
			
			//------------限制帐号请求次数----------begin
			$key_name = 'cashApply_safeCode_uid_'.$uid;
			if(cache_s($key_name)>20)json_s(array('status'=>205,'msg'=>'很抱歉，请求次数限制，请稍后1小时后再操作'));				
			//------------限制帐号请求次数----------end							
			$d_pass = md5(md5($d_pass . web_md5));
			//首先判断安全码是否正确
			$pan_user_safecode = spClass("pan_user_safecode");
			$pan_user_safecode_r = $pan_user_safecode->find(array("uid" => $uid));
			$p_pass = $pan_user_safecode_r['safecode'];
			if (!$p_pass) {
				json_s(array('status'=>203,'msg'=>'安全码未设置，请先设置','ids'=>'#d_pass'));
			}			
			if ($p_pass !== $d_pass) {
				cache_s($key_name,intval(cache_s($key_name))+1,3600);//输入错误的安全码缓存+1
				json_s(array('status'=>201,'msg'=>'安全码不正确','ids'=>'#d_pass'));
			}
			cache_s($key_name,0,3600);//输入正确的安全码重置为0

			//银行账号信息
			$d_bankid = $this->spArgs("bankid");
			if(!$d_bankid)json_s(array('status'=>201,'msg'=>'银行卡不能为空','ids'=>''));
			$lib_member_bank = spClass('lib_member_bank');
			$lib_member_bank_r = $lib_member_bank->find(array('id' => $d_bankid,'uid'=>$uid,'status'=>0));
			if ($lib_member_bank_r && $lib_member_bank_r['bankno']) {
				$m_bank = $lib_member_bank_r['bankname'];
				$username = $lib_member_bank_r['username'];
				$bankadder = $lib_member_bank_r['bankadder'];				
				$bankno = $lib_member_bank_r['bankno'];				
			}else{
				json_s(array('status'=>201,'msg'=>'银行卡不存在或不属于你添加的','ids'=>''));
			}			
			
			//------------限制系统全部用户请求操作域名相关---判断后台是否在处理操作待续费、待下架----------begin
			$domain_action = 'domain_action';
			cache_a($domain_action,'user',5);
			if(cache_a($domain_action)=='system')json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙，请稍后刷新重试。'));
			//------------限制系统全部用户请求操作域名相关---判断后台是否在处理操作待续费、待下架----------end	
		
			//------------限制用户并发请求操作域名相关----------begin
			$domain_action_uid = 'domain_action_uid_'.$uid;
			if(false === cache_a($domain_action_uid,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
			//------------限制用户并发请求操作域名相关----------end	
						
			$sql_sw = false;
			$sp = spClass('lib_member_account');
			$sp->runSql("SET AUTOCOMMIT=0");
			$sp->runSql('BEGIN');//开启事务				
			//余额\\
            //-----------先查询用户账户---------
            $bal_sql = "select * from ykjhqcom.lib_member_account where uid=$uid FOR UPDATE"; //*****只要是查余额表的，必须前面开事务，并且后面加上 FOR UPDATE 单行锁表
            $res_a = $sp->findSql($bal_sql);			
			$res = $res_a[0];
			$m_zye = 0; //总余额
			$m_kyye = 0; //可用余额
			$m_djje = 0; //冻结金额
			$m_draw = 0; //可提现金额
			$m_no_draw = 0; //不可提现金额
			if ($res) {
				$m_ye = $res['balance']; //总余额
				$m_djje = $res['freeze_money']; //冻结金额
				$m_kyye = $m_ye - $m_djje; //可用余额
				$m_no_draw = $res['draw'] < 0 ? 0 : $res['draw']; //不可提现金额
				// $m_draw = ($m_kyye - $m_no_draw) < 0 ? 0 : ($m_kyye - $m_no_draw); //可提现金额=可用金额-不可提现金额
				$m_draw = $m_kyye; //可提现金额=可用金额-不可提现金额
				$m_draw = bcadd($m_draw,0,2); //可提现金额 强制转换成最多只保留两位小数点，防止精度误差	
			}
			//提现费率
			$m_draw_sxfl = withdraw;
			//余额\\
			$d_draw = $this->spArgs("d_draw"); //提现金额
			// $d_draw = round(floatval($d_draw), 2);
			$d_draw = bcadd($d_draw,0,2);//提现金额 强制转换成最多只保留两位小数点，防止精度误差	
			//判断提现金额是否超限
			if ($d_draw < 100) {//小于100元提现金额时!
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'最小提现金额不能少于100元','ids'=>'#d_draw'));
			}
			if ($d_draw > 100000) {
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'最大提现金额不能大于100000元','ids'=>'#d_draw'));
			}			
			if ($d_draw > $m_draw) {//大于可提现金额时!
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'可提现金额不足，当前最大可提现金额'.$m_draw.'元','ids'=>'#d_draw'));
			}
			//手续费
			$j_draw_sxf = round($d_draw * $m_draw_sxfl, 2);
			//实际到账金额
			$j_sjdz = round($d_draw - $j_draw_sxf, 2);

			$remark = $this->spArgs("d_bz");//备注
			//申请提现操作
			//1.冻结相应金额
			//2.写入提现数据
			//因为有钱提现,所以财务表lib_member_account一定是存在的!
			$draw_row = array("uid" => $uid, "username" => $username, "bank" => $m_bank, "bankno" => $bankno ,"bankadder"=>$bankadder, "draw" => $d_draw, "draw_s" => $j_draw_sxf, "draw_d" => $j_sjdz, "sqdate" => date('Y-m-d H:i:s', time()), "state" => 601, "remark" => $remark);
			$account_sql = "update ykjhqcom.lib_member_account set freeze_money=freeze_money+$d_draw where uid=$uid";
			//1.执行冻结
			$sp->runSql($account_sql);
			//3.执行写入提现数据;
			$lib_member_draw = spClass("lib_member_draw");
			$lib_member_draw->create($draw_row);
			user_log($uid,1101,$ip,"[用户".$uid."]：".'已成功申请提现金额'.$d_draw.'元，扣除手续费'.$j_draw_sxf.'元，实际到账金额'.$j_sjdz.'元，冻结金额'.$d_draw.'元');
			//----邮件后台提醒----begin
				$content = array();
				$content['to'] = array('pwpet@qq.com');
				$content['sub'] = array('%content%'=>array('用户MID：'.$this->mid.'，提交了申请提现，金额：'.$d_draw.'元'));
				$new_content = json_encode($content);
				send_mail('pwpet@qq.com','【炒米后台提醒】有用户提交了申请提现！',$new_content,8);
				$content['to'] = array('605466504@qq.com');
				$new_content = json_encode($content);
				send_mail('605466504@qq.com','【炒米后台提醒】有用户提交了申请提现！',$new_content,8);							
			//----邮件后台提醒----end					
			$sql_sw = true;
			if(false===$sql_sw){
				$sp->runSql('ROLLBACK'); //回滚事务
				json_s(array('status'=>205,'msg'=>'系统事务出错，请稍候重试。','del_cache_a'=>''));
			}else{			
				$sp->runSql('COMMIT');//执行事务
			}			
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			json_s(array('status'=>200,'msg'=>'已成功申请提现金额'.$d_draw.'元，扣除手续费'.$j_draw_sxf.'元，实际到账金额'.$j_sjdz.'元，冻结金额'.$d_draw.'元'));		
		}
		if($act=='delete'){	//取消提现
			$id = intval($this->spArgs("id"));
			if(!$id)json_s(array('status'=>201,'msg'=>'参数ID不能为空'));
			
			//------------限制相同IP并发请求----------begin
			$ip_key = md5($ip);
			$key_name = 'cashApply_ip_'.$ip_key;
			if(false === cache_a($key_name,time(),1))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后1秒后重试!'));	
			//------------限制相同IP并发请求----------end
			
			//------------限制系统全部用户请求操作域名相关---判断后台是否在处理操作待续费、待下架----------begin
			$domain_action = 'domain_action';
			cache_a($domain_action,'user',5);
			if(cache_a($domain_action)=='system')json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙，请稍后刷新重试。'));
			//------------限制系统全部用户请求操作域名相关---判断后台是否在处理操作待续费、待下架----------end				
			
			//------------限制用户并发请求操作域名相关----------begin
			$domain_action_uid = 'domain_action_uid_'.$uid;
			if(false === cache_a($domain_action_uid,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
			//------------限制用户并发请求操作域名相关----------end	
			
			
			//判断提现ID是否是本用户下,并且状态值为601;
			$lib_member_draw = spClass('lib_member_draw');
			$lib_member_draw_r = $lib_member_draw->find(array("id" => $id, "uid" => $uid, "state" => 601));
			if (!$lib_member_draw_r) {
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'提现不存在或已在处理中无法取消'));
			}
			//处理取消提现
			$sql_sw = false;
			$sp = spClass('lib_member_account');
			$sp->runSql("SET AUTOCOMMIT=0");
			$sp->runSql('BEGIN');//开启事务				
			//1.改变提现状态
			//2.冻结金额取消
			$d_draw = $lib_member_draw_r["draw"]; //当前提现金额
			//1.改变提现状态
			$lib_member_draw->update(array('id'=>$id),array('state'=>602));
			//2.冻结金额取消
			$sql = "update ykjhqcom.lib_member_account set freeze_money=freeze_money-$d_draw where uid=$uid";
			$sp->runSql($sql);
			user_log($uid,1101,$ip,"[用户".$uid."]：".'已取消申请提现，已解冻金额'.$d_draw.'元');
			$sql_sw = true;
			if(false===$sql_sw){
				$sp->runSql('ROLLBACK'); //回滚事务
				json_s(array('status'=>205,'msg'=>'系统事务出错，请稍候重试。','del_cache_a'=>''));
			}else{			
				$sp->runSql('COMMIT');//执行事务
			}			
			cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
			json_s(array('status'=>200,'msg'=>'提现成功取消，已解冻金额'.$d_draw.'元'));
		}
        //余额\\
        $member_account = spClass('lib_member_account');
        $res = $member_account->find(array('uid' => $uid));
        $m_zye = 0; //总余额
        $m_kyye = 0; //可用余额
        $m_djje = 0; //冻结金额
        $m_draw = 0; //可提现金额
        $m_no_draw = 0; //不可提现金额
        if ($res) {
            $m_ye = $res['balance']; //总余额
            $m_djje = $res['freeze_money']; //冻结金额
            $m_kyye = $m_ye - $m_djje; //可用余额
            $m_kyye = bcadd($m_kyye,0,2);//强制转换成最多只保留两位小数点，防止精度误差; 
            $m_no_draw = $res['draw'] < 0 ? 0 : $res['draw']; //不可提现金额
            // $m_draw = ($m_kyye - $m_no_draw) < 0 ? 0 : ($m_kyye - $m_no_draw); //可提现金额=可用金额-不可提现金额
            $m_draw = $m_kyye; 
            $m_draw =  bcadd($m_draw,0,2);//强制转换成最多只保留两位小数点，防止精度误差	 
        }
        $m_draw_sxfl = withdraw * 100;
        $this->m_res = $res;
        $this->m_ye = $m_ye;
        $this->m_kyye = $m_kyye;
        $this->m_djje = $m_djje;
        $this->m_draw = $m_draw;
        $this->m_draw_sxf = $m_draw_sxfl;
        $this->m_mindraw = 0.01;
        $this->bank = $lib_member_bank_r;

        $this->module = "capital";
        $this->act = "cashManage";
        $this->display('amui/member/am_financial_cashApply.html');
    }

    //提现列表
    function cashList() {
        $uid = $this->uid;
        //设置每页显示10条
        $pglimit = pgsize;
        //获取当前页码
        $pgno = intval($this->spArgs('page', 1));
        if($pgno<1) $pgno=1;

        $sp = spClass('lib_member_draw');
        $sp_key = spClass('lib_key');
        $res = $sp->spPager($pgno, $pglimit)->findAll(array('uid' => $uid), 'id desc');
        $lib_key_sql = "select id,key from ykjhqcom.lib_key where id>=600 and id<700";
        $lib_key_r = $sp_key->findAll('id>=600 and id<700');
        foreach ($res as $key => $value) {
            foreach ($lib_key_r as $kk => $kv) {
                if ($res[$key]["state"] == $lib_key_r[$kk]["id"]) {
                    $res[$key]["statename"] = $lib_key_r[$kk]["key"];
                    break;
                }
            }
        }
        //分页参数
        $pager = $sp->spPager()->getPager();
        if ($pager['total_page'] > 5) {
            if ($pgno > 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], $pgno - 3, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            }
        }

        $this->res = $res;
        $this->pager = $pager;
        $this->module = "capital";
        $this->act = "cashManage";
        $this->display('amui/member/am_financial_cashList.html');
    }
	function prompt(){
        $uid = $this->uid;		
        $mid = $this->mid;	
		//------------限制相同帐号并发请求----------begin
		$key_name = 'capital_prompt_uid_'.$uid;
		if(false === cache_a($key_name,time(),60))json_s(array('status'=>205,'msg'=>'很抱歉，请稍后60秒再操作!'));		
		//------------限制相同帐号并发请求----------end
		
		//------------限制请求次数----------begin
		$key_name = 'capital_prompt_uid_m_'.$uid;
		if(cache_s($key_name)>5)json_s(array('status'=>205,'msg'=>'很抱歉，请求次数限制，请稍后1小时再操作!'));			
		cache_s($key_name,intval(cache_s($key_name))+1,3600);
		//------------限制请求次数----------end
			//----邮件后台提醒----begin
				$content = array();
				$content['to'] = array('pwpet@qq.com');
				$content['sub'] = array('%content%'=>array('用户MID：'.$mid.'，在前台点击了申请手工入账通知，有可能他已通过支付宝或银行卡转账，请及时处理查账并在后台入款。'));
				$new_content = json_encode($content);
				send_mail('pwpet@qq.com','【炒米后台提醒】有用户可能打款了，请求入账提醒',$new_content,8);
				$content['to'] = array('605466504@qq.com');
				$new_content = json_encode($content);
				send_mail('605466504@qq.com','【炒米后台提醒】有用户可能打款了，请求入账提醒',$new_content,8);							
			//----邮件后台提醒----end	
		json_s(array('status'=>200,'msg'=>'提醒成功，已通知财务。'));	
	}
}
