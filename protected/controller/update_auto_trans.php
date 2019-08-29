<?php
// error_reporting(E_ALL || ~E_NOTICE);
class update_auto_trans extends spController
{
    function __construct(){
        parent::__construct();
		// echo md5(date('Y-m-d H',time()).'ChaoMi-Token'); //示例:每小时变一次
		check_token($this->spArgs('token'));//验证权限token
		header('Content-Type:text/html;charset=utf-8');
    }	
    function create(){ //首页
		spClass('updating');
		spClass("trades");
		$pan_auto_trans_config = spClass('pan_auto_trans_config');
		$pan_auto_trans_log = spClass("pan_auto_trans_log");
        $typeid = intval($this->spArgs('typeid'));
		if(empty($typeid))json_s(array('status'=>201,'msg'=>'typeid不能为空'));		
		//---配置---begin
		// $config = array(
				// '808001' => array(
					// 'trade_mid' => 301,
					// 'trade_count' => 5, //日交易数上限
					// 'trans_time_min' => 100, //单条交易时间间隔 随机
					// 'trans_time_max' => 200, //单条交易时间间隔 随机
					// 'trans_price_add' => 3, //均价增加 1-n个随机数
					// 'number_min' => 1, //单条交易数量 随机
					// 'number_max' => 2, //单条交易数量 随机
					// 'zhibao' => 6, //单条交易质保 随机
					// 'trade_buy_me_count' => 10, //自己帐号买入别人日交易数上限
					// 'trade_sale_me_count' => 10, //自己帐号卖出别人日交易数上限
					// 'trans_sleep_min' => 10, //单条交易 下单停留时间
					// 'trans_sleep_max' => 20, //单条交易 下单停留时间
					// 'trans_buy_or_sale' => 60, //控制单条交易成交方向【控制买入方向概率】 
				// ),
		// );
		// $data = $config[$typeid];
		$data = $pan_auto_trans_config->find(array('typeid'=>$typeid));
		// var_dump($data);
		// echo $pan_auto_trans_log->dumpSql();
		//---配置---end
		if(empty($data)){
			cm_exit($typeid,'配置顶参数不存在');
		}
		
		$mid_info = spClass("pub_user")->find(array('mid'=>$data['trade_mid']));
		$trade_uid = $mid_info['uid'];
		if(empty($trade_uid)){
			cm_exit($typeid,'trade_uid 交易用户mid，配置参数错误');
		}
			
		if($data['trans_sleep_max']>50){
			cm_exit($typeid,'下单停留时间过长，超过50秒？');
		}

			
		//查询自己帐号买入别人日交易数上限---begin
		
		$s_deal_time = date("Y-m-d")." 00:00:00";
		$e_deal_time = date("Y-m-d")." 23:59:59";
		$time_condition = " and deal_time>='".$s_deal_time."' and deal_time<='".$e_deal_time."'";
		$trade_buy_me_count_sql = "select sum(price) as price,count(id) as count from cmpai.pan_deal_domain WHERE typeid= $typeid and status=1 $time_condition and (deal_uid in ($trade_uid) and uid not in ($trade_uid))";		
		$trade_sale_me_count_sql = "select sum(price) as price,count(id) as count from cmpai.pan_deal_domain WHERE typeid= $typeid and status=1 $time_condition and (uid in ($trade_uid) and deal_uid not in ($trade_uid))";		
		$trade_buy_me_count_ = spClass('pan_deal_domain')->findSql($trade_buy_me_count_sql);
		$trade_buy_me_count = $trade_buy_me_count_[0]['count'];
		$trade_sale_me_count_ = spClass('pan_deal_domain')->findSql($trade_sale_me_count_sql);
		$trade_sale_me_count = $trade_sale_me_count_[0]['count'];
		
		if($data['trade_buy_me_count']<$trade_buy_me_count){
			$note = '自己帐号(mid:'.$data['trade_mid'].')买入用户域名日交易数上限：'.$data['trade_buy_me_count'].'，今日已买入：'.$trade_buy_me_count;
			cm_exit($typeid,$note);
			exit;
		}
		if($data['trade_sale_me_count']<$trade_sale_me_count){
			$note = '自己帐号(mid:'.$data['trade_mid'].')卖出给用户域名日交易数上限：'.$data['trade_sale_me_count'].'，今日已卖出：'.$trade_sale_me_count;
			cm_exit($typeid,$note);
			exit;
		}		
		
		//查询自己帐号买入别人日交易数上限---end
		
		$hq_data = cache_s('cm_typeid_'.$typeid.'_hq');
		
		$trade_count = $data['trade_count']; //日交易数上限
		$zhibao_ = explode(",",$data['zhibao']);//单条交易质保
		$random_keys = array_rand($zhibao_);
		$zhibao = $zhibao_[$random_keys];
		
		$key_trans_time = 'cm_auto_trans_time_typeid_'.$typeid;
		$trans_time = cache_s($key_trans_time);
		if($trans_time<=2){
			$trans_time = mt_rand($data['trans_time_min'],$data['trans_time_max']);//单条交易时间间隔 随机
			cache_s($key_trans_time,$trans_time,$trans_time-1);
		}
		
		if($trade_count==0){
			cm_exit($typeid,'日交易数上限配置参数==0');
		}
		$today_count = $hq_data['count'];//今日成交量
		if($today_count>=$trade_count){
			cm_exit($typeid,'今日成交量已>='.$trade_count.'个');
		}
					
		$ret = $pan_auto_trans_log->find(array('typeid'=>$typeid),'id desc');
		if(time() - strtotime($ret['create_time'])<$trans_time){
			$next_trans_time = date("Y-m-d H:i:s",strtotime($ret['create_time']) + $trans_time);
			$note = '与上一条记录相差少于指定时间，上次随机间隔：'.$trans_time.'，最快下次执行：'.$next_trans_time;
			cm_exit($typeid,$note);
		}
		if(date("H")==0)exit('H==0');

		//---取出买一与卖一的价格，平均值---begin
		
		$buy_ret = find_buy_sale_auto($typeid,1,$limit=5,0,$zhibao);
		$buy_1 = $buy_ret['买1']['price'];
		$sale_ret = find_buy_sale_auto($typeid,0,$limit=5,0,$zhibao);
		$sale_1 = $sale_ret['卖1']['price'];		

		if($buy_1==0 || $sale_1==0){
			cm_exit($typeid,'质保：'.$zhibao.'，买一或卖一不能为0');
		}
		$price_j = ($buy_1 + $sale_1) / 2;
		$price_a = $sale_1 - $price_j;
		$rand_min_price = $price_a - $price_a * 2 + 1;
		$rand_max_price = $price_a - 1;

		$price = $price_j + rand(1,$data['trans_price_add']);
		if($typeid<800000){
			$price = $price_j + rand(1,$data['trans_price_add'])/10;
		}
		
		if($today_count==0){ //当日第一笔成交，单价取平均价偏低一点
			$price = $price_j - rand(1,$data['trans_price_add']);
			if($typeid<800000){
				$price = $price_j - rand(1,$data['trans_price_add'])/10;
			}					
		}
		$price = bcadd($price,0,1);
		if($price>=$sale_1){
			$price = $price_j;
			$price = bcadd($price,0,1);
		}
		if($price<=$buy_1){
			$price = $price_j;
			$price = bcadd($price,0,2);			
		}
		if($typeid>800000){
			$price = intval($price);
		}
		if($price<=$buy_1){
			cm_exit($typeid,'平均值<=买1价');
		}
		if($price>=$sale_1){
			cm_exit($typeid,'平均值>=卖1价');
		}
		if($price==0){
			cm_exit($typeid,'平均值==0');
		}
		
		$number = mt_rand($data['number_min'],$data['number_max']);//单条交易数量 随机

		//---取出买一与卖一的价格，平均值---end
		
		$price_c = $number * $price; //总价
		if($price_c<=0)exit('price_c<=0');
		//-----买家帐号可用余额
		$sp = spClass('lib_member_account');
		$ret = $sp->findSql("select balance,freeze_money from ykjhqcom.lib_member_account where uid=$trade_uid");
		$account = round(($ret[0]['balance'] - $ret[0]['freeze_money']),2);//用户账户可用余额
		if($price_c > $account){
			cm_exit($typeid,'买家帐号可用余额不足，'.$price_c);
		}
		//-----卖家帐号可卖域名数量
		//查询符合要求的域名
		$expire_time = date("Y-m-d",strtotime("+$zhibao month"));
		if($zhibao<=0){
			//判断质保时间，0=小于一个月
			$expire_time = date("Y-m-d",strtotime("+1 month"));
			$cond_expire_time_sql = "expire_time < '{$expire_time}'";
		}else{
			$cond_expire_time_sql = "expire_time >= '{$expire_time}'";
		}		
		if($typeid<800000){
			$cond_expire_time_sql = "1=1";
		}	
		//查询符合要求的域名		
		$sql = "select count(*) from cmpai.pan_domain_in where locked=0 and uid=$trade_uid and typeid=$typeid and $cond_expire_time_sql";
		$res = spClass('pan_domain_in')->findSql($sql);
		$sale_num = round($res[0]['count(*)']);	
		if($number>$sale_num){
			cm_exit($typeid,'卖家帐号可卖域名数量不足，'.$number);
		}
		//----随机买入还是卖出----
		$time_ = 0; //控制买入还是卖出
		$time_str_ = '卖出';
		if(mt_rand(1,100)<$data['trans_buy_or_sale']){
			$time_ = 60;
			$time_str_ = '买入';
		}	
		//--------操作缓存处理-----begin
		$cache_name = 'cm_auto_trans_admin';
		if(false === cache_a($cache_name,time(),50))json_s(array('status'=>208,'msg'=>'操作占用中'));	
		//--------操作缓存处理-----end			
		$note = "[成功]品种：$typeid 自动交易UID：$trade_uid ( mid:".$data['trade_mid']." ) 方向：$time_str_ 个数：$number  单价：$price 卖1：$sale_1 买1：$buy_1 质保：$zhibao 间隔：$trans_time";
		echo $note;
		$pan_auto_trans_log->create(array('typeid'=>$typeid,'note'=>$note));
		// exit;
		//----邮件后台提醒----begin
			$content = array();
			$content['to'] = array('pwpet@qq.com');
			$content['sub'] = array('%content%'=>array($note));
			$new_content = json_encode($content);
			// send_mail('pwpet@qq.com','【炒米后台提醒】'.$hq_data['name'].'自动交易',$new_content,8);						
		//----邮件后台提醒----end		
		
		if($time_==0){
			echo buy_domain_admin($price,$number,$trade_uid,$typeid,$time_,$zhibao);
			sleep(mt_rand($data['trans_sleep_min'],$data['trans_sleep_max']));
			echo sale_domain_admin($price,$number,$trade_uid,$typeid,$zhibao);
		}else{
			echo sale_domain_admin($price,$number,$trade_uid,$typeid,$zhibao);
			sleep(mt_rand($data['trans_sleep_min'],$data['trans_sleep_max']));
			echo buy_domain_admin($price,$number,$trade_uid,$typeid,$time_,$zhibao);
		}
		cache_a($cache_name,null); //删缓存
		cm_exit($typeid,$note);
    }
 
}

function cm_exit($typeid,$note){
	$note .= '----'.date("Y-m-d H:i:s").' ';
	cache_s('cm_trans_return_info_'.$typeid,$note,86400);
	echo $note;
	exit;	
}
//买入时域名处理
function buy_domain_admin($price,$number,$uid,$typeid,$time_=0,$zhibao){
	$key_buy_name = 'trade_buy_uid_'.$uid;//用户正在操作买入 缓存名
    $total_price = bcmul($price,$number); //单价乘以数量
    $now = date("Y-m-d H:i:s", time()+$time_);
    $expire=null;
	//如果委托没有设置到期时间，默认为60天后。
	if($wt == 0){
		$wt = 60;
	}
    if($wt>0) {
        $expire = time() + $wt * 3600 * 24;
        $expire = date("Y-m-d H:i:s", $expire); //委托到期时间
    }	
    $ip = get_client_ip();
	if(false === cache_a($key_buy_name,time(),30))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后重试'));
	
	//------------限制用户并发请求操作域名相关----------begin
	$domain_action_uid = 'domain_action_uid_'.$uid;
	if(false === cache_a($domain_action_uid,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
	//------------限制用户并发请求操作域名相关----------end	
	
    $sp=spClass('lib_member');
	$sql_sw = false;
	$sp->runSql("SET AUTOCOMMIT=0");	
    $sp->runSql('BEGIN'); //开启事务
	$acc = $sp->findSql("select balance,freeze_money from ykjhqcom.lib_member_account where uid=$uid FOR UPDATE"); //*****只要是查余额表的，必须前面开事务，并且后面加上 FOR UPDATE 单行锁表
	//-----判断需要冻结的金额是否足够-----begin
	$total = bcsub($acc[0]['balance'],$acc[0]['freeze_money']);//金额减法	
	if (bccomp($total,$total_price)==-1){
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		json_s(array('status'=>201,'msg'=>'你的资金不足，请充值','del_cache_a'=>$key_buy_name));
	}
	//-----判断需要冻结的金额是否足够-----end
    //用户金额冻结
	$new_freeze= bcadd($total_price,$acc[0]['freeze_money']);//新的冻结金额=当前总价+旧冻结金额
	$upd_money_sql="update ykjhqcom.lib_member_account set freeze_money=$new_freeze where uid=$uid";
	//更新用户冻结金额
	$acc_ret = $sp->findSql($upd_money_sql);
	//更新域名交易委托表
	$ins_arr=array('total_price'=>$total_price,'uid'=>$uid,'typeid'=>$typeid,'number'=>$number,'price'=>$price,'status_1'=>0,'status_2'=>1,'zhibao'=>$zhibao,'order_time'=>$now,'expire_time'=>$expire,'ip'=>$ip);
	//新增买入委托交易
	$pan=spClass('pan_trade');
	$id=$pan->create($ins_arr);
	
	user_log($uid,601,$ip,'买家'.$uid.'插入买委托[委托买订单（'.$id.'）]买'.$number.'个，单价'.$price.'元'.$typeid.'域名'.'记录，冻结资金'.$total_price.'元，执行前查冻结总金额'.$acc[0]['freeze_money'].'元->执行此条时冻结总金额'.$new_freeze.'元');	
	$sql_sw = true;
	if(false===$sql_sw){
		$sp->runSql('ROLLBACK'); //回滚事务
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		json_s(array('status'=>205,'msg'=>'系统事务出错，请稍候重试。','del_cache_a'=>$key_buy_name));
	}else{
		$sp->runSql('COMMIT'); //提交事务
	}
	cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
	cache_a($key_buy_name,null);
	return '恭喜，[买入]挂单委托成功';
}


//域名卖出时处理
function sale_domain_admin($price,$number,$uid,$typeid,$zhibao){
	$key_sale_name = 'trade_sale_uid_'.$uid;//用户正在操作交易 缓存名	
    $now = date("Y-m-d H:i:s", time());
    $expire=null;
	//如果委托没有设置到期时间，默认为27天后。
	if($wt == 0){
		$wt = 27;
	}
    if($wt>0) {
        $expire = time() + $wt * 3600 * 24;
        $expire = date("Y-m-d H:i:s", $expire); //委托到期时间
    }
    $total_price = bcmul($price,$number); //单价乘以数量
    $ip = get_client_ip();
	if(false === cache_a($key_sale_name,time(),30))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后重试'));
	
	//------------限制用户并发请求操作域名相关----------begin
	$domain_action_uid = 'domain_action_uid_'.$uid;
	if(false === cache_a($domain_action_uid,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
	//------------限制用户并发请求操作域名相关----------end	
	
    //查询符合要求的域名
	//20171221新增限制保质
	$cond_expire_time_sql = "expire_time >= '2019-05-01'";
	if($typeid==411104){
		// $cond_expire_time_sql = "expire_time >= '2019-01-01'";
		$_expire_time = date("Y-m-d",strtotime('+1 day'));
		$cond_expire_time_sql = "expire_time >= '".$_expire_time."'";		
	}
	if($typeid==411109){
		$cond_expire_time_sql = "expire_time >= '2021-09-01'";
	}	
	
	$expire_time = date("Y-m-d",strtotime("+$zhibao month"));
	if($zhibao<=0){
		//判断质保时间，0=小于一个月
		$expire_time = date("Y-m-d",strtotime("+1 month"));
		$cond_expire_time_sql = "expire_time < '{$expire_time}'";
	}else{
		$cond_expire_time_sql = "expire_time >= '{$expire_time}'";
	}	
	if($typeid<800000){
		$cond_expire_time_sql = "1=1";
	}		
	$pan = spClass('pan_domain_in');
	$sql_sw = false;
	$pan->runSql("SET AUTOCOMMIT=0");
	$pan->runSql('BEGIN'); //开启事务
	write("\r\n");
	write("\r\n");
	write('自动交易-----用户UID:'.$uid.'插入卖出订单，执行开始!-----');
	//查询用户当前品种域名数目，到期30天后的总数
	$find = "uid=$uid and typeid=$typeid and locked=0 and $cond_expire_time_sql";
	$ret_count = $pan->findCount($find);
	if($ret_count < $number){
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		json_s(array('status'=>201,'msg'=>'域名可卖数量不足','del_cache_a'=>$key_sale_name));
	}
	write('可卖出域名个数：'.$ret_count.' 符合规则 即locked==0数量');
	//获取符合规则的 number 个域名
	$dsql="SELECT id,uid,domain,typeid,expire_time FROM cmpai.pan_domain_in WHERE uid=$uid and typeid=$typeid and locked=0 and $cond_expire_time_sql order by upd_time asc LIMIT {$number} FOR UPDATE";
	$domain_ret = $pan->findSql($dsql);
	write('用户本次执行需要卖个数：'.$number);
	write('查出具体域名列表时语句：'.$pan->dumpSql());
	$ids = array();
	$domains = array();
	foreach($domain_ret as $v) {
		$ids[] = $v['id'];
		$domains[] = $v['domain'];
		$domains_a[] = "'".$v['domain']."'";
	}
	$expire = $domain_ret[0]['expire_time'];
	$expire = date("Y-m-d H:i:s",strtotime($expire)-rand(60,189));
	$ids_str = implode(',',$ids);
	$domain_str = implode(',',$domains);
	$domain_str_a = implode(',',$domains_a); //加了'
	if(count($ids) != count($domains) || count($ids)==0 || $ids_str=='' || $domain_str==''){
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		json_s(array('status'=>201,'msg'=>'系统找不到具体域名，请稍候重试！','del_cache_a'=>$key_sale_name));		
	}
	//------最后验证是否成功，用到-----begin
	$pan_domain_in_locked_0_count_one = $pan->findCount(array('uid'=>$uid,'typeid'=>$typeid,'locked'=>0)); //未卖出前，locked==0 状态正常域名的总数
	//------最后验证是否成功，用到-----end
	
	//修改pan_domain_in表对应的域名为出售状态
	$pan->update("uid=$uid and id in ($ids_str)",array('locked'=>2));
	write('更新pan_domain_in表 locked=2时，SQL语句：'.$pan->dumpSql());
	$update_locked_2 = $pan->affectedRows();
	write('更新pan_domain_in表 locked=2时，影响行数：'.$update_locked_2);
		
	if($update_locked_2 != count($domain_ret)){
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		json_s(array('status'=>201,'msg'=>'系统更新域名状态出错，请稍候重试！','del_cache_a'=>$key_sale_name));		
	}
	
	//新增pan_trade表委托卖域名交易记录
	$pan_trade = spClass('pan_trade');
	$ins_arr = array('total_price'=>$total_price,'uid'=>$uid,'typeid'=>$typeid,'number'=>$number,'price'=>$price,'status_1'=>0,'status_2'=>0,'zhibao'=>$zhibao,'order_time'=>$now,'expire_time'=>$expire,'ip'=>$ip);
	$tid = $pan_trade->create($ins_arr);//返回添加的主键id
	write('添加pan_trade表 插入记录时，SQL语句：'.$pan_trade->dumpSql().'，返回主键id:'.$tid);
	user_log($uid,602,$ip,'[委托卖订单（'.$tid.'）]:用户'.$uid.'插入委托卖域名记录，出售编号为'.$typeid.'的域名'.$number.'个，单价'.$price.'，域名列表：'.$domain_str.',domain_id:'.$ids_str);
	user_log($uid,613,$ip,"用户$uid 修改 $number 个域名，状态为出售中(委托卖订单ID：".$tid.")，域名：".$domain_str.",domain_id:".$ids_str);
	write('添加pan_trade表 插入[委托卖订单（'.$tid.'）]:用户'.$uid.'插入委托卖域名记录，出售编号为'.$typeid.'的域名'.$number.'个，单价'.$price.'，域名列表：'.$domain_str.',domain_id:'.$ids_str);
	
	if(!$tid){
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		json_s(array('status'=>201,'msg'=>'添加域名委托表出错，请稍候重试！','del_cache_a'=>$key_sale_name));		
	}	
	//新增域名委托实米纪录
	$pan_deal_domain = spClass('pan_deal_domain');
	$row = array();
	$pid_c = 0;
	write("\r\n");
	write('开始添加pan_deal_domain表 插入记录----begin');
	foreach($domain_ret as $v) {
		$row = array('uid' => $uid, 'tid' => $tid, 'domain'=>$v['domain'], 'domain_id'=>$v['id'],'time'=>$now,'price'=>$price,'status'=>0,'typeid'=>$typeid);
		$pid = $pan_deal_domain->create($row);
		if($pid>0)$pid_c++;
		write('SQL-'.$pid_c.'，返回主键：'.$pid.' ：'.$pan_deal_domain->dumpSql());
	}
	write('结束添加pan_deal_domain表 插入记录----end');
	write("\r\n");
	if($pid_c != count($domain_ret)){
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		json_s(array('status'=>201,'msg'=>'添加域名记录表出错，请稍候重试！','del_cache_a'=>$key_sale_name));		
	}
	user_log($uid,614,$ip,"用户$uid 插入实米交易记录pan_deal_domain表，".count($domain_ret)."条记录，状态为0出售中，域名：".$domain_str);

	//-----最次预检测，再次验证域名是否已经添加成功-----begin
	$pan_deal_count = $pan_deal_domain->findCount("uid = $uid and tid = $tid and domain in($domain_str_a)");
	write('***预检测，再次验证域名是否已经添加成功，查询SQL语句：'.$pan_deal_domain->dumpSql());
	write('***预检测，再次验证域名是否已经添加成功，查询返回总数='.$pan_deal_count);
	if($pan_deal_count != $update_locked_2){
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		json_s(array('status'=>201,'msg'=>'添加后的域名记录表总数与实际域名数量不相符，请稍候重试！','del_cache_a'=>$key_sale_name));		
	}
	$pan_domain_in_locked_0_count_two = $pan->findCount(array('uid'=>$uid,'typeid'=>$typeid,'locked'=>0)); //假设卖出后，locked==0 状态正常域名的总数
	$pan_domain_in_locked_0_count_new = $pan_domain_in_locked_0_count_one - $pan_domain_in_locked_0_count_two; //求出卖出后，locked==0 状态正常域名的总数(卖出前-卖出后)
	write('***预检测，再次验证locked==0状态正常的域名总数，卖出前'.$pan_domain_in_locked_0_count_one.'-卖出后'.$pan_domain_in_locked_0_count_two.'='.$pan_domain_in_locked_0_count_new);
	if($pan_domain_in_locked_0_count_new != $pan_deal_count || $pan_domain_in_locked_0_count_new<0){
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		json_s(array('status'=>201,'msg'=>'卖出后系统域名数量与实际域名数量不相符，请稍候重试！','del_cache_a'=>$key_sale_name));		
	}		
	//-----最次预检测，再次验证域名是否已经添加成功-----end
	
	$sql_sw = true;
	if(false===$sql_sw){
		$pan->runSql('ROLLBACK'); //回滚事务
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		write('用户UID:'.$uid.'-----插入卖出订单，SQL事务回滚!-----');		
		json_s(array('status'=>205,'msg'=>'系统事务出错，请稍候重试。','del_cache_a'=>$key_sale_name));
	}else{
		$pan->runSql('COMMIT'); //提交事务
		write('用户UID:'.$uid.'-----插入卖出订单，执行结束完成!-----');
	}
	cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
	cache_a($key_sale_name,null);
	return '恭喜，[卖出]挂单委托成功';
}
function find_buy_sale_auto($typeid,$bs,$limit=10,$pingtai,$zhibao){
			if(!check_pz($typeid))return array(); //判断品种是否存在
			$price=array();
			$number=array();
			$now=date("Y-m-d H:i:s");
			$sp=spClass('pan_trade');
			//查询目前5个最低价
			//查询目前5个最低卖价
			$find_sql = " and 1=1";
			if($pingtai>0){
				$find_sql .= " and FIND_IN_SET($pingtai,pingtai)";
			}
			if($zhibao>=1){
				if($bs==1){
					$find_sql .= " and (zhibao<=$zhibao or zhibao=0)"; //如果是买单，需要无论任何质保要求，都加上 不限
				}else{
					$find_sql .= " and zhibao>=$zhibao";
				}
			}	
			if($bs==0) {
				$sql = "select distinct price from cmpai.pan_trade where status_1=0 AND status_2=0 AND typeid = $typeid and expire_time >'{$now}' $find_sql order by price asc limit $limit";
			}else if($bs==1){ //取得当前5个最高买价
				$sql="select distinct price from cmpai.pan_trade where status_1=0 AND status_2=1 AND typeid = $typeid and expire_time >'{$now}' $find_sql order by price desc limit $limit";
			}
			$price_res=$sp->findSql($sql); //print_r($price_res);
			// var_dump($price_res);
			$zhibao = $number = $price = $pingtai = $price_c = array();
			$k = 0;
			if(!empty($price_res)) {
				foreach ($price_res as $c=>$v) {
					$sql="select id,number,deal_num,sum(number-deal_num) as num,price,typeid,pingtai,zhibao from cmpai.pan_trade where status_1=0 AND status_2=$bs AND typeid = $typeid and price=".$v['price']." and expire_time >='{$now}' $find_sql group by pingtai,zhibao limit 1000";
					$ret = $sp->findSql($sql);
					foreach ($ret as $a=>$g) {
						$price[$k]=$v['price'];
						$number[$k] = $g['num'];
						$price_c[$k]=$price[$k]*$number[$k];
						$pt = $zb = '-';
						//处理平台
						if($g['pingtai']=='1'){
							$pt = '易名';
						}elseif($g['pingtai']=='2'){
							$pt = '爱名';	
						}elseif($g['pingtai']=='3'){
							$pt = '阿里';	
						}elseif($g['pingtai']=='1,2'){
							$pt = '易名 爱名';	
						}elseif($g['pingtai']=='1,3'){
							$pt = '易名 阿里';	
						}elseif($g['pingtai']=='2,3'){
							$pt = '爱名 阿里';	
						}else{
							$pt = '不限平台';	
						}	
						//处理质保时间
						if($g['zhibao']==0){
							$zb = '不限质保';
							if($bs==0){
								$zb = '<1个月';
							}
						}else{
							$zhibao_tmp = $g['zhibao'];
							$zb = '≥'.$zhibao_tmp.'个月';	
						}								
						$pingtai[$k]=$pt;
						$zhibao[$k]=$zb;
						$k++;
						
					}
				}
			}
			// var_dump($zhibao);
			if($bs==0){
				$item=array('卖1','卖2','卖3','卖4','卖5','卖6','卖7','卖8','卖9','卖10');
			}
			else{
				$item=array('买1','买2','买3','买4','买5','买6','买7','买8','买9','买10');
			}
			$running_data=array();
			for($i=0;$i<$limit;$i++){
				$running_data[$item[$i]]=array(
					// 'item'=>$item[$i],
					'number'=>$number[$i],
					'price'=>$price[$i],
					'price_c'=>$price_c[$i],
					'pingtai'=>$pingtai[$i],
					'zhibao'=>$zhibao[$i]
				);
			}
			if($bs==0){
				krsort($running_data);
			}
			return $running_data;
}