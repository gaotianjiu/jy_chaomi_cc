<?php
define("web_md5", "_chaomi_cc");
class ykj extends spController {
    function __construct() { 
        parent::__construct(); 
		exit;
		$sso_user = check();
		$this->uid = $sso_user['uid'];
		$this->mid = $sso_user['mid'];				
    }
    //域名一口价列表
    function index() {
		$new_ym_code = spClass('new_ym_code');
        $page = intval($this->spArgs('page', 1));
        if($page <1) $page=1;
		$cond = array();
		$orderField = "id desc";
        //查询条件
        $pan_domain_ykj = spClass('pan_domain_ykj');
        $condition = " where status=1 ";
        $cond=array('domain'=>"",'typeid'=>'','price_px'=>'','is_park'=>'');
        //域名**********模糊查询************
        if(false !=$this->spArgs('domain')){
            $domain=$pan_domain_ykj->escape($this->spArgs('domain'));
            $condition.=" and domain like '%".trim($domain,"'")."%' ";
            $cond['domain']=trim($domain,"'");
        }
        //域名品种
        if(false !=$this->spArgs('typeid')){
            $typeid=intval($this->spArgs('typeid'));
            $condition.=" and typeid=".$typeid." ";
            $cond['typeid']=trim($typeid,"'");
        }
        //是否停放
        if(false !=$this->spArgs('is_park')){
            $is_park = intval($this->spArgs('is_park'));
			if($is_park==1){
				$condition.=" and is_score>0";
			}
			if($is_park==2){
				$condition.=" and is_score=0";
			}			
            $cond['is_park']=$is_park;
        }
        //价格排序
        if(false !=$this->spArgs('price_px')){
            $price_px=intval($this->spArgs('price_px'));
			if($price_px==1){
				$orderField ="sale_price asc";
			}
			if($price_px==2){
				$orderField ="sale_price desc";
			}			
            $cond['price_px'] = $price_px;
        }	
        //到期排序
        if(false !=$this->spArgs('expire_time')){
            $expire_time=intval($this->spArgs('expire_time'));
			if($expire_time==1){
				$orderField ="expire_time asc";
			}
			if($expire_time==2){
				$orderField ="expire_time desc";
			}			
            $cond['expire_time'] = $expire_time;
        }		
        //传递到页面的查询条件
        //查询结束
        $sort = " ORDER BY $orderField ";
        $sql = "select * from cmpai.pan_domain_ykj ".$condition.$sort ;
        $ret = $pan_domain_ykj->spPager($page,20)->findSql($sql);
		$domain_list = array();
		foreach ($ret as $k=>$v) {
			$r = $new_ym_code->spCache(3600)->find(array('code'=>$v['typeid']));
			$v['name'] = $r['name'];
			$domain_list[] = $v;
		}
		
        //分页参数
        $pager=$pan_domain_ykj->spPager()->getPager();
        if ($pager['total_page'] > 5) {
            if ($page > 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], $page - 3, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            }
        }
        $this->pager = $pager;
        //分页结束
        //-----------**品种**----------------------\\
        $dlist = "select code as id,name from cmpai.new_ym_code where state=1";
        $types = $new_ym_code->spCache(3600)->findSql($dlist);
				
        $this->types = $types;
        $this->module = "domainykj";
        $this->act = "domainykj";
        $this->cm_nav = "domainykj";
        $this->cond=$cond;
        $this->ret = $domain_list;
        $this->display('amui/ykj/list.html');
    }
	function buy(){
		//购买一口价
		$uid = $this->uid;
		$ip = get_client_ip();
		$now_time = time();
		$now_time_str = date("Y-m-d H:i:s");
        $new_ym_code = spClass('new_ym_code');
        $pan_parking = spClass('pan_parking');
        $pan_domain_ykj = spClass('pan_domain_ykj');
		$pan_domain_in = spClass('pan_domain_in'); // 域名实盘表
		$pan_domain_ykj_deal = spClass('pan_domain_ykj_deal'); // 域名一口价买入表
		$from = $this->spArgs('from'); 
		$id = intval($this->spArgs('id')); 
		if($from=='info'){
			//按ID查具体一口价详情x1
			if(!$id || empty($id))json_s(array('status'=>201,'msg'=>'参数ID不能为空'));
			$r = $pan_domain_ykj->find(array('id'=>$id));
			if(!$r){
				json_s(array('status'=>201,'msg'=>'权限不足'));
			}
			if($r['status']!=1){
				json_s(array('status'=>201,'msg'=>'当前状态不是出售中'));
			}
			json_s(array('status'=>200,'msg'=>'success','data'=>array('domain'=>$r['domain'],'sale_price'=>number_format($r['sale_price'],2),'_sale_price'=>$r['sale_price'],'sale_type'=>(int)$r['sale_type'])));
		}		
		if($from=='create'){
			//购买一口价x2
			if(!$uid || empty($uid))json_s(array('status'=>201,'msg'=>'请登录会员后再购买'));
			if(!$id || empty($id))json_s(array('status'=>201,'msg'=>'参数ID不能为空'));
			$r = $pan_domain_ykj->find(array('id'=>$id));
			if(!$r){
				json_s(array('status'=>201,'msg'=>'权限不足'));
			}
			if($r['status']!=1){
				json_s(array('status'=>201,'msg'=>'当前状态不是出售中'));
			}	
			$sale_price = $r['sale_price'];
			$_sale_price = $this->spArgs('_sale_price'); 
			$sale_type = $r['sale_type'];
			$old_uid = $r['uid'];
			if($sale_price<=0){
				json_s(array('status'=>201,'msg'=>'单价不能为空'));
			}	
			if($uid==$old_uid){
				json_s(array('status'=>201,'msg'=>'不能购买自己帐号的一口价域名'));
			}			
			if($sale_price!=$_sale_price){
				json_s(array('status'=>201,'msg'=>'域名价格可能有变动，请刷新页面重试！'));
			}				
			if($sale_price>99999999){
				json_s(array('status'=>201,'msg'=>'单价不能大于99999999'));
			}	
			if(!in_array($sale_type,array(1,2))){
				json_s(array('status'=>201,'msg'=>'一口价交易方式参数不存在'));
			}			

			//处理安全码
			$pw = trim($this->spArgs('safecode'));
			if(empty($pw))json_s(array('status'=>201,'msg'=>'交易密码不能为空'));
			$pw = md5(md5($pw . web_md5)); //双重md5加密			
			$pws = spClass('pan_user_safecode')->find(array('uid' => $uid));//密码获取代码
			//------------限制帐号请求验证安全码次数----------begin
			$key_safeCode_name = 'ykj_safeCode_uid_'.$uid;
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
			
			$sp = spClass('pan_domain_ykj');
			$sql_sw = false;
			$sp->runSql("SET AUTOCOMMIT=0");
			$sp->runSql('BEGIN'); //开启事务
				$pan_domain_ykj->update(array('id'=>$id,'uid'=>$old_uid),array('act_note'=>$r['act_note']."[ $now_time_str 一口价购买过户 ]",'status'=>2));
				$update_ykj_row = $pan_domain_ykj->affectedRows(); //影响行数
				if($update_ykj_row!=1){
					cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
					json_s(array('status'=>300,'msg'=>'错误提示：系统更新数量与实际不符'));						
				}
				//***特别注意：如果是在停放中的域名，是不需要更新状态的***
				$domain_id = $r['domain_id'];
				$_r = $pan_domain_in->find(array('id'=>$domain_id));
				$locked = $_r['locked'];
				$sale_type_tmp = '元';
				if($locked==9){
					if($r['sale_type']==1){
						auto_ykj_rmb($old_uid,$uid,$r['domain'],$sale_price,0.1);//处理财务--人民币
					}
					if($r['sale_type']==2){
						auto_ykj_score($old_uid,$uid,$r['domain'],$sale_price,0.1);//处理财务--积分
						$sale_type_tmp = '积分';
					}											
					//停放中的域名
					$pan_domain_in->update("uid=$old_uid and locked=9 and id=$domain_id",array('uid'=>$uid,'locked'=>9,'upd_time'=>$now_time_str));
					$update_domain_row = $pan_domain_in->affectedRows(); //影响行数	
					//过户停放表
					$_r_parking = $pan_parking->find(array('domain_id'=>$domain_id,'status'=>0));
					$pan_parking->update(array('domain_id'=>$domain_id,'status'=>0,'uid'=>$old_uid),array('act_note'=>$_r_parking['act_note']."[ $now_time_str 一口价购买过户 ]",'uid'=>$uid));
					$update_parking_row = $pan_parking->affectedRows(); //影响行数	
					if($update_parking_row!=1){
						cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
						json_s(array('status'=>300,'msg'=>'错误提示：系统过户停放数量与实际不符'));				
					}						
				}else{
					if($r['sale_type']==1){
						auto_ykj_rmb($old_uid,$uid,$r['domain'],$sale_price,0.02);//处理财务--人民币
					}
					if($r['sale_type']==2){
						auto_ykj_score($old_uid,$uid,$r['domain'],$sale_price,0.02);//处理财务--积分
						$sale_type_tmp = '积分';
					}	
					$pan_domain_in->update("uid=$old_uid and locked=11 and id=$domain_id",array('uid'=>$uid,'locked'=>0,'upd_time'=>$now_time_str));
					$update_domain_row = $pan_domain_in->affectedRows(); //影响行数
				}
				if($update_domain_row!=1){
					cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
					json_s(array('status'=>300,'msg'=>'错误提示：系统更新域名数量与实际不符'));				
				}
				$deal_row = array(
							'ykj_id'=>$id,
							'sale_uid'=>$old_uid,
							'uid'=>$uid,
							'typeid'=>$r['typeid'],
							'domain_id'=>$domain_id,
							'domain'=>$r['domain'],
							'buy_type'=>$r['sale_type'],
							'buy_price'=>$sale_price,
							'pingtai'=>$r['pingtai'],
							'expire_time'=>$r['expire_time'],
							'is_score'=>$r['is_score'],
							'create_time'=>$now_time_str,
							'act_ip'=>$ip,
							'act_note'=>"[ $now_time_str 一口价购买成功 ]"
				);
				$deal_id = $pan_domain_ykj_deal->create($deal_row);
				if($deal_id<=0){
					cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
					json_s(array('status'=>300,'msg'=>'错误提示：系统成交域名数量与实际不符'));				
				}	
				$domain = $r['domain'];
				
				//------添加推送到成交列表------begin
				if($r['sale_type']==1){
					//人民币交易的才推送
					$pan_deal_domain = spClass("pan_deal_domain");
					$deal_domain_row = array(
							'domain'=>$domain,
							'domain_id'=>$domain_id,
							'uid'=>88888,
							'tid'=>$id,
							'time'=>$now_time_str,
							'price'=>$r['sale_price'],
							'deal_uid'=>$uid,
							'deal_tid'=>$deal_id,
							'deal_time'=>$now_time_str,
							'deal_price'=>$sale_price,
							'status'=>1,
							'deal_status'=>2,
							'typeid'=>$r['typeid'],
					);
					$deal_domain_id = $pan_deal_domain->create($deal_domain_row);
					if($deal_domain_id<=0){
						cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
						json_s(array('status'=>300,'msg'=>'错误提示：推送成交域名数量与实际不符'));				
					}
					//处理成交数据的缓存---begin
					
					//---清空Api接口数据---begin
					$cache_key = 'new_deal_trade_data_all_domain_api';
					cache_a($cache_key,null);	
					//---清空Api接口数据---end
					cache_a('new_deal_trade_data_typeid_all',null);
					cache_a('new_deal_trade_data_typeid_'.$r['typeid'],null);//按品种ID清空成交缓存
					//处理成交数据的缓存---end					
				}
			
				//------添加推送到成交列表------end
				
				//---添加站内短信---begin
				$type =  '901';
				$tit  =  "恭喜，成功卖出域名：$domain";
				$txt  =  "恭喜，您发布的一口价域名：$domain 以 $sale_price"."$sale_type_tmp 交易成功，时间：$now_time_str";
				web_msg_send($tit,$type,$old_uid,$txt);	
				//---添加站内短信---begin
				$type =  '901';
				$tit  =  "恭喜，成功购买域名：$domain ";
				$txt  =  "恭喜，您成功购买一口价域名：$domain 价格：".$sale_price.$sale_type_tmp." 时间：$now_time_str";
				web_msg_send($tit,$type,$uid,$txt);					
				//---添加站内短信---end	
				send_mobile_email($old_uid,"炒米网(chaomi.cc)一口价交易成功提醒","您发布的一口价域名：$domain 以【".$sale_price.$sale_type_tmp."】于 $now_time_str 交易成功");					
				
			$sql_sw = true;
			if(false===$sql_sw){
				$sp->runSql('ROLLBACK'); //回滚事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'系统处理出错，已回滚事务。'));				
			}else{
				$sp->runSql('COMMIT'); //提交事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
				json_s(array('status'=>200,'msg'=>'恭喜，域名：'.$_r['domain'].' 购买成功'));	
			}		
		}		
	}
}
//处理财务--积分
function auto_ykj_score($out_uid,$in_uid,$domain,$amount,$sRate){
	$ip = get_client_ip();
	$now_time_str = date("Y-m-d H:i:s");	
	$sp = spClass('pan_member_score');
	$pan_member_score_list = spClass('pan_member_score_list');
	$typeid = '411104';
	//*****--------------操作买家相关处理-----begin
	//-----------查询用户账户---------
	$bal_sql = "select * from cmpai.pan_member_score where uid = $in_uid and typeid=$typeid FOR UPDATE"; //*****只要是查余额表的，必须前面开事务，并且后面加上 FOR UPDATE 单行锁表
	$out_member_score_result = $sp->findSql($bal_sql);		
	$new_amount = $out_member_score_result[0]['balance'] - $amount;
	if($new_amount<=0){
		cache_a('domain_action_uid_'.$in_uid,null);
		json_s(array('status'=>201,'msg'=>'帐号积分不足以支付购买此域名'));
	}	
	if ($out_member_score_result) {
		$out_member_score_sql = "update cmpai.pan_member_score set balance=balance-$amount where uid=$in_uid and typeid=$typeid";
		$sp->runSql($out_member_score_sql);
	} else {
		$sp->create(array('uid' => $in_uid,'typeid'=>$typeid,'balance' => $amount));
	}			
	//准备添加积分流水相关数据,与流水表字段名相同		
	$lsarr = array(
		'uid' => $in_uid,
		'from_uid' => $out_id,
		'typeid' => $typeid,
		'type' => '5',
		'amount' => -$amount,
		'balance' => $new_amount,
		'act_ip' => $ip,
		'create_time' => $now_time_str,
		'note' => "购买一口价域名：$domain "
	);
	//添加积分流水	
	$pan_member_score_list->create($lsarr);					
	//*****--------------操作买家相关处理-----begin
	user_log($in_uid, 1505, $ip,'购买一口价域名：'.$domain.'，积分：'.$amount);

	//*****--------------操作卖家相关处理-----begin
	//-----------查询用户账户---------
	$bal_sql = "select * from cmpai.pan_member_score where uid = $out_uid and typeid=$typeid FOR UPDATE"; //*****只要是查余额表的，必须前面开事务，并且后面加上 FOR UPDATE 单行锁表
	$in_member_score_result = $sp->findSql($bal_sql);	
	//处理手续费
	$aa=bcmul($amount,(1-$sRate)); //卖家入账金额
	$bb=bcsub($amount,$aa); //卖家扣除金额（手续费）	
	$amount = $aa;
	$new_amount = $in_member_score_result[0]['balance'] + $amount;
	if ($in_member_score_result) {
		$in_member_score_sql = "update cmpai.pan_member_score set balance=balance+$amount where uid=$out_uid and typeid=$typeid";
		$sp->runSql($in_member_score_sql);
	} else {
		$sp->create(array('uid' => $out_uid,'typeid'=>$typeid,'balance' => $amount));
	}			
	//准备添加积分流水相关数据,与流水表字段名相同
	$_sxf = "普通类2%";
	if($sRate==0.1)$_sxf = "停放类10%";
	$lsarr = array(
		'uid' => $out_uid,
		'from_uid' => $in_uid,
		'typeid' => $typeid,
		'type' => '5',
		'amount' => $amount,
		'balance' => $new_amount,
		'act_ip' => $ip,
		'create_time' => $now_time_str,
		'note' =>  "卖出一口价域名：$domain (扣除交易手续费 $bb 积分，备注：$_sxf)"
	);
	//添加积分流水
	$pan_member_score_list->create($lsarr);		
	//*****--------------操作卖家相关处理-----begin		
	user_log($out_uid, 1505, $ip,'卖出一口价域名：'.$domain.'，积分：'.$amount);
	return true;
}
//处理财务--人民币
function auto_ykj_rmb($out_uid,$in_uid,$domain,$price,$sRate){
    $ip=get_client_ip();
	$a = spClass('lib_member_account');
    $y=date("Y",time());
    $m=date("m",time());
    $d=date("d",time());
    $utime=date('Y-m-d H:i:s',time());
	
	//---*****处理买家------begin
		//生成订单号，YmdHis+uid+一个随机数
		$order_id='YKJ'.date("YmdHis").$in_uid.mt_rand(100000,999999);
        //买家，扣掉
        //查询买家【账户】
        $reg = $a->findSql("select balance,freeze_money,draw,fund from ykjhqcom.lib_member_account where uid=$in_uid FOR UPDATE");

        //处理买家的余额，和委托买入时的冻结余额
        $balance=bcsub($reg[0]['balance'],$price);   //账户余额扣掉花费
        $draw=bcsub($reg[0]['draw'],$price);               //不可提现金额
		
		if($balance<=0){
			cache_a('domain_action_uid_'.$in_uid,null);
			json_s(array('status'=>201,'msg'=>'余额不足以支付购买此域名费用，请先充值'));
		}
		
        //更改买家账户信息
        $upd_money_sql = "update ykjhqcom.lib_member_account set balance=$balance where uid=$in_uid ";
        $a->runSql($upd_money_sql);

        //财务变化流水表 ---  [买家消费记录]
        $pay=0-$price;   //消费前加个 - 符号
		$note = "'购买一口价域名：$domain '";
        $brow="insert into ykjhqcom.lib_member_records (`uid`,`order_id`,`type`,`amount`,`ip`,`deal_time`,`note`,`balance`,`y`,`m`,`d`) values($in_uid,'$order_id',500,$pay,'$ip','".time()."',$note,$balance,$y,$m,$d)";
        $a->runSql($brow);
		
        //增加资金流水账单
        //买家消费
        $ins="insert into cmpai.pan_plat_property(uid,property,deal_time,Y,m,d) values($in_uid,$pay,'$deal_time',$y,$m,$d)";
        $a->runSql($ins);

        //日志
        user_log($in_uid,605,$ip,'【买家资产】交易一口价域名：'.$domain.'，买家：'.$in_uid.'用户账户扣除费用'.$price.'元，账户余额'.$balance.'元，不可提现金额减少'.$price.',目前为'.$draw.'元。');
	//---*****处理买家------end
	
	//---*****处理卖家------begin
		//生成订单号，YmdHis+uid+一个随机数
		$order_id='YKJ'.date("YmdHis").$out_uid.mt_rand(100000,999999);
		$deal_time=date("H:i:s",time());
        $reg = $a->findSql("select balance,draw,fund from ykjhqcom.lib_member_account where uid=$out_uid FOR UPDATE");
        //处理手续费
		$aa=bcmul($price,(1-$sRate)); //卖家入账金额
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
		$_sxf = "普通类2%";
		if($sRate==0.1)$_sxf = "停放类10%";		
        //财务变化流水表   ----卖家入款流水记录
		$note = "'卖出一口价域名：$domain (扣除手续费 $bb 元，备注：$_sxf)'";
        $brow="insert into ykjhqcom.lib_member_records (`uid`,`order_id`,`type`,`amount`,`ip`,`deal_time`,`note`,`balance`,`y`,`m`,`d`) values($out_uid,'$order_id',501,$aa,'$ip','".time()."',$note,$balance,$y,$m,$d)";
        $a->runSql($brow);

        //增加资金流水账单（平台总流水）  --【用户资金走向】
        $ins="insert into cmpai.pan_plat_property(uid,property,deal_time,Y,m,d) values($out_uid,$aa,'$deal_time',$y,$m,$d)";
        $a->runSql($ins);

        //日志
        user_log($out_uid,606,$ip,'【卖家资产】交易一口价域名：'.$domain.'，卖家：'.$out_uid.'入账'.$aa.'元，扣除手续费'.$bb.'元，账户余额'.$balance.'元');
	//---*****处理卖家------end
		return true;
	
    
}