<?php
class updating extends Model{

}
//查找最新指导价格
function find_price($sta,$typeid){
    //取得指导表的价格
    $sql = "select price from cmpai.new_price where typeid=$typeid order by id desc limit 1";
    $res = new pan_trade()->findSql($sql);
    return $res[0]['price'];
}
//买入时域名处理
function buy_domain($price,$number,$uid,$typeid,$wt=0,$front,$pingtai,$zhibao){
	$key_buy_name = 'trade_buy_uid_'.$uid;//用户正在操作买入 缓存名
    $total_price = bcmul($price,$number); //单价乘以数量
    $now = date("Y-m-d H:i:s", time());
    $expire=null;
	//如果委托没有设置到期时间，默认为60天后。
	if($wt == 0){
		$wt = 365;
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
	
    $sp=new lib_member();
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
	$ins_arr=array('total_price'=>$total_price,'uid'=>$uid,'typeid'=>$typeid,'number'=>$number,'price'=>$price,'status_1'=>0,'status_2'=>1,'order_time'=>$now,'expire_time'=>$expire,'pingtai'=>$pingtai,'zhibao'=>$zhibao,'ip'=>$ip);
	//新增买入委托交易
	$pan=new pan_trade();
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
	json_s(array('status'=>200,'msg'=>'恭喜，[买入]挂单委托成功','del_cache_a'=>$key_buy_name));
}


//域名卖出时处理
function sale_domain($price,$number,$uid,$typeid,$wt=0,$front,$pingtai,$zhibao){
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
	$expire_time = date("Y-m-d",strtotime("+$zhibao month"));
	if($zhibao<=0){
		//判断质保时间，0=小于一个月
		$expire_time = date("Y-m-d",strtotime("+1 month"));
		$cond_expire_time_sql = "expire_time < '{$expire_time}'";
	}else{
		$cond_expire_time_sql = "expire_time >= '{$expire_time}'";
		if($zhibao==1){
			// $cond_expire_time_sql = "expire_time >= '".date("Y-m-d",strtotime("+1 month"))."' and expire_time < '".date("Y-m-d",strtotime("+3 month"))."'";
			$cond_expire_time_sql = "expire_time >= '".date("Y-m-d",strtotime("+1 month"))."'";
		}
		if($zhibao==3){
			// $cond_expire_time_sql = "expire_time >= '".date("Y-m-d",strtotime("+3 month"))."' and expire_time < '".date("Y-m-d",strtotime("+6 month"))."'";
			$cond_expire_time_sql = "expire_time >= '".date("Y-m-d",strtotime("+3 month"))."'";
		}		
		if($zhibao==6){
			// $cond_expire_time_sql = "expire_time >= '".date("Y-m-d",strtotime("+6 month"))."' and expire_time < '".date("Y-m-d",strtotime("+9 month"))."'";
			$cond_expire_time_sql = "expire_time >= '".date("Y-m-d",strtotime("+6 month"))."'";
		}		
		if($zhibao==9){
			// $cond_expire_time_sql = "expire_time >= '".date("Y-m-d",strtotime("+9 month"))."' and expire_time < '".date("Y-m-d",strtotime("+12 month"))."'";
			$cond_expire_time_sql = "expire_time >= '".date("Y-m-d",strtotime("+9 month"))."'";
		}		
		if($zhibao==12){
			$cond_expire_time_sql = "expire_time >= '".date("Y-m-d",strtotime("+12 month"))."'";
		}			
	}
	//处理平台
	if($pingtai==1){
		$cond_pingtai_sql = "pingtai = '易名中国'";
	}elseif($pingtai==2){
		$cond_pingtai_sql = "pingtai = '爱名网'";	
	}elseif($pingtai==3){
		$cond_pingtai_sql = "pingtai = '万网'";	
	}else{
		$cond_pingtai_sql = "pingtai = ''";
	}	
	//新增限制保质
	// $_expire_time = date("Y-m-d",strtotime('+1 month'));
	// $cond_expire_time_sql = "expire_time >= '".$_expire_time."'";
	// $cond_expire_time_sql = "expire_time >= '2019-05-01'";
	// if($typeid==811001){
		// $cond_expire_time_sql = "expire_time >= '2021-09-01'";
	// }	
	if($typeid<800000){
		$cond_expire_time_sql = "1=1";
	}
	// if($typeid==614101 || $typeid==411103 || $typeid==411104){
		// $_expire_time = date("Y-m-d",strtotime('+1 year'));
		// $cond_expire_time_sql = "expire_time >= '".$_expire_time."'";
	// }		
	$pan = new pan_domain_in();
	$sql_sw = false;
	$pan->runSql("SET AUTOCOMMIT=0");
	$pan->runSql('BEGIN'); //开启事务
	write("\r\n");
	write("\r\n");
	write('-----用户UID:'.$uid.'插入卖出订单，执行开始!-----');
	//查询用户当前品种域名数目，到期30天后的总数
	$find = "uid=$uid and typeid=$typeid and locked=0 and $cond_expire_time_sql";
	$ret_count = $pan->findCount($find);
	if($ret_count < $number){
		cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
		json_s(array('status'=>201,'msg'=>'域名可卖数量不足，域名可卖数：'.$ret_count.'个','del_cache_a'=>$key_sale_name));
	}
	write('可卖出域名个数：'.$ret_count.' 符合规则 即locked==0数量');
	//获取符合规则的 number 个域名
	$dsql="SELECT id,uid,domain,typeid,expire_time FROM cmpai.pan_domain_in WHERE uid=$uid and typeid=$typeid and locked=0 and $cond_expire_time_sql order by expire_time asc LIMIT {$number} FOR UPDATE";
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
	if($zhibao>0){
		//处理到期下架，订单到期时间=当前时间+（域名最小到期时间-卖出质保）
		$sy_time = strtotime($expire)-strtotime("+".$zhibao."month");
		$expire = date("Y-m-d H:i:s",time()+$sy_time);		
	}	
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
	$pan_trade = new pan_trade();
	$ins_arr = array('total_price'=>$total_price,'uid'=>$uid,'typeid'=>$typeid,'number'=>$number,'price'=>$price,'status_1'=>0,'status_2'=>0,'pingtai'=>$pingtai,'zhibao'=>$zhibao,'order_time'=>$now,'expire_time'=>$expire,'ip'=>$ip);
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
	$pan_deal_domain = new pan_deal_domain();
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
	json_s(array('status'=>200,'msg'=>'恭喜，[卖出]挂单委托成功','del_cache_a'=>$key_sale_name));
}



