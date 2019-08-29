<?php
class autoTrading extends Model{
}
//---------------------------后台自动买入专用----------------
function auto_buy($typeid,$zhibao){
    //取得必须值
    $now=date("Y-m-d H:i:s");
    $ip=get_client_ip();
    //连接数据库---------------------\\
    $a = new pan_trade();
	
	//20171102新增 按平台 + 质保
	//这里的成交逻辑与前台页面显示的委托盘逻辑是相反的
	$find_sql = " and 1=1";
	$find_sql_buy = " and 1=1";
	
	// if($pingtai==0){
		// $find_sql_buy .= " and pingtai='1,2,3'";
	// }
	// if($pingtai>0){
		// $find_sql_buy .= " and FIND_IN_SET($pingtai,pingtai)";
		// $find_sql .= " and FIND_IN_SET($pingtai,pingtai)";
	// }		
	if($zhibao==0){
		$find_sql_buy .= " and (zhibao=0)"; 
	}		
	if($zhibao>=1){
		$find_sql_buy .= " and (zhibao<=$zhibao)";
		$find_sql .= " and zhibao>=$zhibao";
	}		
	
    //获取最高买价-------------------------------\\
	$re = array();
    $buy_info="select * from cmpai.pan_trade where status_1=0 and status_2=1 and typeid={$typeid} and expire_time > '{$now}' $find_sql_buy order by price desc limit 1";
    $re=$a->findSql($buy_info);
    if(false ==$re || $re[0]['id']==0) {
		echo '无相关的买单';
        return;
    }
    $id=$re[0]['id'];
    $uid=$re[0]['uid'];
    $price=$re[0]['price'];
    $number=$re[0]['number'];
    $deal=$re[0]['deal_num'];
    $typeid=$re[0]['typeid'];
    $order_time=$re[0]['order_time'];  //委托时间
		
	//*****---------加原子缓存判断->为了避免在后台操作时，用户发起并发请求----begin
	$key_buy_id_name = 'trade_cancel_order_buyer_buy_id_'.$id;//当前订单操作买入取消缓存
	if(false === cache_a($key_buy_id_name,time(),30)){
		echo '缓存写入失败，用户在操作取消买入中...买家ID:'.$uid.',订单ID:'.$id.' - ';
		return;
	}
	//*****---------加原子缓存判断->为了避免在后台操作时，用户发起并发请求----end
	
    //获取符合要求的卖单-----------------\\
    $sel_sale_sql="select * from cmpai.pan_trade where status_1=0 and status_2=0 and typeid=$typeid and price <=$price and expire_time > '{$now}' $find_sql order by price asc,zhibao asc,pingtai asc,order_time asc";
    $res=$a->findSql($sel_sale_sql);
	//test
	// echo '<br/>buy_id='.$id.'-pingtai'.$re[0]['pingtai'].'--zhibao'.$re[0]['zhibao'].'--<br/>';
	// foreach ($res as $k => $v) {
		// echo '<br/>sale_id='.$v['id'].'-pingtai'.$v['pingtai'].'--zhibao'.$v['zhibao'].'--<br/>';
	// }
	// var_dump($res);
	// return $res;
    if(!empty($res)) { 
		
		//------------限制用户并发请求操作域名相关、买家----------begin
		$domain_action_uid_in = 'domain_action_uid_'.$uid;
		if(false === cache_a($domain_action_uid_in,time(),10)){
			echo '买家ID：'.$uid.'，正在操作域名，当前线程已返回!订单ID:'.$id.' - ';
			return;
		}	
		//------------限制用户并发请求操作域名相关、买家----------end	
		
        //买家需要个数
        $num=$number-$deal;
        //卖家已成交个数
        $buy_deal=$deal;
		write("\r\n【*****后台成交----------买家UID：$uid - 买委托订单ID：$id - 买家需要个数：$num 执行开始】-------------------begin");
        foreach ($res as $k => $v) {
			$key_sale_id_name = 'trade_cancel_order_buyer_sale_id_'.$v['id'];//当前订单操作卖出取消缓存
			if(false === cache_a($key_sale_id_name,time(),10)){
				echo '缓存写入失败，用户在操作取消卖出中...卖家ID:'.$v['uid'].'订单ID:'.$v['id'].'已尝试跳过此订单 - ';
				continue;	
			}
			//------------限制用户并发请求操作域名相关、卖家----------begin
			$domain_action_uid_out = 'domain_action_uid_'.$v['uid'];
			if(false === cache_a($domain_action_uid_out,time(),10) && $uid!=$v['uid']){ //避免卖家与买家是同一ID，会有冲突！
				echo '卖家ID：'.$v['uid'].'，正在操作域名，订单ID:'.$v['id'].'已尝试跳过此订单 - ';
				continue;
			}	
			//------------限制用户并发请求操作域名相关、卖家----------end				
			$sql_sw = false;
			$a = new pan_trade();
			$a->runSql("SET AUTOCOMMIT=0");
			$a->runSql('BEGIN');
            //委托卖价的个数刚好满足买入个数
            $freeN=$v['number']-$v['deal_num'];  //发布总数-已成交的数目
            $time=date("Y-m-d H:i:s",time());
			$a->findSql("select * from cmpai.pan_trade where id=".$v['id']." FOR UPDATE");//*****上锁单行ID委托订单表---卖家订单表----防止取消订单并发抢		
            if($freeN >$num){  //剩余卖 > 剩余买 ,买全数成交，卖部分成交
                //比较委托时间，来确定成交价格
				$deal_status = 1; //成交类型 1=卖出 2=买入
                if($v['order_time'] < $order_time){
                    $dealM=$v['price'];
					$deal_status = 2;
                }else{
                    $dealM=$price;
                }
                $total_price=bcmul($dealM,$num);
				
                //扣除买家财务金额
                $in_price=bcmul($num,$price);//买家扣除的冻结金额，要单独以买入的单价算总额，否则账目会对不上，特别是撤销时的冻结金额就会出错
                auto_price($a,$uid,$total_price,1,$typeid,$num,$in_price);

                //处理卖家财务金额
                auto_price($a,$v['uid'],$total_price,0,$typeid,$num);
				
                //委托卖出记录
                //更新已成交数目
                $Ndeal=$num+$v['deal_num'];
                //处理委托交易表
                $sale_sql="update cmpai.pan_trade set status_1=0,bargain_time='$time',deal_num=$Ndeal where id=".$v['id']." and uid=".$v['uid'];
                $a->runSql($sale_sql);
                user_log($v['uid'],604,$ip,'后台成交[委托卖订单（'.$v['id'].'）]：卖家'.$v['uid'].'成功以单价'.$dealM.'元卖出'.$num."个".$typeid."域名");

                //域名所有人--改名
                $note=auto_domain_uid($a,$uid,$v['uid'],$num,$typeid,$id,$v['id'],$dealM,$deal_status);
				
				//已成交记录插入数据-显示个数版 ---20180417
				$ssql="insert into cmpai.pan_deal_trade_api(`deal_num`,`deal_price`,`tot_price`,`deal_time`,`typeid`,`sta`) values($num,$dealM,$total_price,'$time',$typeid,$deal_status)";
				$a->runSql($ssql);					

                //给卖家 在已成交委托交易表中插入数据
                $dsql="insert into cmpai.pan_deal_trade(`uid`,`tid`,`deal_num`,`deal_price`,`tot_price`,`deal_time`,`deal_uid`,`typeid`,`sta`,`note`) values(".$v['uid'].",".$v['id'].",$num,$dealM,$total_price,'$time',$uid,$typeid,0,'$note')";
                $a->runSql($dsql);

                //------------------------------
                //-----------处理买家----------------------
                //买家已成交的数目
                $buy_deal=$buy_deal+$num;
                $buy_sql = "update cmpai.pan_trade set status_1=1,bargain_time='$time',deal_num=$buy_deal where id=$id ";

                $a->runSql($buy_sql);
                user_log($uid,603,$ip,'后台成交[委托买订单（'.$id.'）]：买家'.$uid.'成功以单价'.$dealM.'元购买了'.$num.'域名'.$typeid.'个');


                //给买家 已成交委托交易表中插入数据
                $bdsql="insert into cmpai.pan_deal_trade(`uid`,`tid`,`deal_num`,`deal_price`,`tot_price`,`deal_time`,`deal_uid`,`typeid`,`sta`,`note`) values(".$uid.",".$id.",$num,$dealM,$total_price,'$time',".$v['uid'].",$typeid,1,'$note')";
                $a->runSql($bdsql);
				
				//---邮件和短信---begin		
				auto_send(1,$typeid,$uid,$price,$number,$dealM,$num,$note,$total_price,$id);//买家
				auto_send(0,$typeid,$v['uid'],$v['price'],$v['number'],$dealM,$num,$note,$total_price,$v['id']);//卖家
				//---邮件和短信---end	
				
				$sql_sw = true;
				if($sql_sw===true){
					$a->runSql('COMMIT');
				}else{
					$a->runSql('ROLLBACK');
					write('以上一步骤系统事务出错，已回滚?');
					json_s(array('status'=>205,'msg'=>'系统事务出错，请稍候重试。','del_cache_a'=>'cm_auto_buy'));
				}
				cache_a($key_sale_id_name,null);//删除订单ID缓存--卖
				cache_a($domain_action_uid_out,null);//删除卖家操作域名缓存				
                $num=0;
                break;
            }else{
                //卖  <=  剩余买   ,卖全数成交，买部分成交
                //处理委托卖
                if($num >=$freeN) {
                    //比较委托时间，来确定成交价格
					$deal_status = 1; //成交类型 1=卖出 2=买入
                    if($v['order_time'] < $order_time){
                        $dealM=$v['price'];
						$deal_status = 2;
                    }else{
                        $dealM=$price;
                    }

                    $total_price=bcmul($dealM,$freeN);
                    $num = $num - $freeN;  //剩余需买数目
                    $deal_num = $freeN + $v['deal_num'];  //委托卖数目全部成交
                    //扣除买家财务金额
					$in_price=bcmul($freeN,$price);//买家扣除的冻结金额，要单独以买入的单价算总额，否则账目会对不上，特别是撤销时的冻结金额就会出错
					auto_price($a,$uid,$total_price,1,$typeid,$freeN,$in_price);					

                    //处理卖家财务金额
                    auto_price($a,$v['uid'],$total_price,0,$typeid,$freeN);
					
                    $sale_sql = "update cmpai.pan_trade set status_1=1,bargain_time='$time',deal_num=$deal_num where id=".$v['id']." and uid=".$v['uid'];
                    $a->runSql($sale_sql);
                    user_log($v['uid'],604,$ip,'后台成交[委托卖订单（'.$v['id'].'）]：卖家'.$v['uid'].'成功以单价'.$dealM.'元卖出'.$freeN.'个'.$typeid.'域名');
                    write("后台成交-委托卖订单".$v['id']."全部成交。");


                    //域名所有人--改名
                    $note=auto_domain_uid($a,$uid,$v['uid'],$freeN,$typeid,$id,$v['id'],$dealM,$deal_status);
					
                    //已成交记录插入数据-显示个数版 ---20180417
                    $ssql="insert into cmpai.pan_deal_trade_api(`deal_num`,`deal_price`,`tot_price`,`deal_time`,`typeid`,`sta`) values($freeN,$dealM,$total_price,'$time',$typeid,$deal_status)";
                    $a->runSql($ssql);					

                    //给卖家 在已成交委托交易表中插入数据
                    $ssql="insert into cmpai.pan_deal_trade(`uid`,`tid`,`deal_num`,`deal_price`,`tot_price`,`deal_time`,`deal_uid`,`typeid`,`sta`,`note`) values(".$v['uid'].",".$v['id'].",$freeN,$dealM,$total_price,'$time',$uid,$typeid,0,'$note')";
                    $a->runSql($ssql);

                    //------------------------------
                    //-----------处理买家----------------------
                    $buy_deal +=$freeN;

                    $status_1=$num==0?1:0;
                    $buy_sql = "update cmpai.pan_trade set status_1=$status_1,bargain_time='$time',deal_num=$buy_deal where id=$id";

                    $a->runSql($buy_sql);
                    user_log($uid,603,$ip,'后台成交[委托买订单（'.$id.'）]：买家'.$uid.'成功以单价'.$dealM.'元购买了'.$freeN.'域名'.$typeid.'个');

                    //给买家 已成交委托交易表中插入数据
                    $bdsql="insert into cmpai.pan_deal_trade(`uid`,`tid`,`deal_num`,`deal_price`,`tot_price`,`deal_time`,`deal_uid`,`typeid`,`sta`,`note`) values(".$uid.",".$id.",$freeN,$dealM,$total_price,'$time',".$v['uid'].",$typeid,1,'$note')";
                    $a->runSql($bdsql);

                    //---邮件和短信---begin
                    auto_send(1,$typeid,$uid,$price,$number,$dealM,$freeN,$note,$total_price,$id);//买家
                    auto_send(0,$typeid,$v['uid'],$v['price'],$v['number'],$dealM,$freeN,$note,$total_price,$v['id']);//卖家
                    //---邮件和短信---end
					
					$sql_sw = true;
					if($sql_sw===true){
						$a->runSql('COMMIT');
					}else{
						$a->runSql('ROLLBACK');
						write('以上一步骤系统事务出错，已回滚?');
						json_s(array('status'=>205,'msg'=>'系统事务出错，请稍候重试。','del_cache_a'=>'cm_auto_buy'));
					}
                    //买家所需域名数已全数购买
					cache_a($key_sale_id_name,null);//删除订单ID缓存--卖
					cache_a($domain_action_uid_out,null);//删除卖家操作域名缓存					
                    if($num==0){
                        break;
                    }
                }
            }
        }
		cache_a($domain_action_uid_in,null);//删除买家操作域名缓存
        if($num==0){
            $msg="委托买订单($id)全数成交！\r\n\r\n";
            echo $msg;
            write($msg);
        }else if($num >0){
            $msg="委托买订单($id)还有$num 未成交！\r\n\r\n";;
			echo $msg;
            write($msg);
        }else{
            $msg="后台处理成交-系统错误！\r\n\r\n";
			echo $msg;
            write($msg);
        }
		//处理成交数据的缓存---begin
		
		//---清空Api接口数据---begin
		$cache_key = 'new_deal_trade_data_all_domain_api';
		cache_s($cache_key,null);	
		//---清空Api接口数据---end
		
		$domain_typeid = domain_typeid();
		foreach($domain_typeid as $keyid){ 
			cache_s('new_deal_trade_count_data_typeid_'.$keyid,null);//按品种ID清空成交缓存
			cache_s('new_deal_trade_data_typeid_'.$keyid,null);//按品种ID清空成交缓存
		} 	
		//处理成交数据的缓存---end
		write("【*****后台成交----------买家UID：$uid - 买ID：$id - 买家需要个数：$num 执行结束】-------------------end\r\n");
    }else{
		echo '买单要求价格:'.$price.'，当前无符合价格的卖单!-';
	}
	cache_a($key_buy_id_name,null); //删除订单ID缓存--买

}


//处理委托交易时的财务
/**
 * function auto_price
 * @param $uid
 * @param $price
 * @param $sta  1 买，扣钱   0卖，去掉手续费入账
 * @param $typeid  域名编号
 * @param  $num  交易域名个数
 * RATE  手续费
 * in_price  买家需要扣除的冻结金额
 * return void
 */
function auto_price($a,$uid,$price,$sta,$typeid,$num,$in_price=''){
    $ip=get_client_ip();
    $sFund=sFund;
    $bFund=bFund;
    $sRate=sRate;
    $bRate=bRate;
    $dname=auto_get_name($a,$typeid);
    $note=(($sta==0)?'卖出':'买入').($dname."".$num.'个');
	// if($typeid==411104 || $typeid==411109)$sRate = 0.02;
    $y=date("Y",time());
    $m=date("m",time());
    $d=date("d",time());
    $utime=date('Y-m-d H:i:s',time());

    //生成订单号，YmdHis+uid+一个随机数
    $order_id=date("YmdHis").$uid.mt_rand(100000,999999);

    $deal_time=date("H:i:s",time());
    if($sta==0){  //卖家，入账
        $reg = $a->findSql("select balance,draw,fund from ykjhqcom.lib_member_account where uid=$uid FOR UPDATE");
		
        //处理手续费
		$aa=bcmul($price,(1-sRate)); //卖家入账金额
        $bb=bcsub($price,$aa); //卖家扣除金额（手续费）
		if($bb<0.01)$bb=0.01;
		
		//增加指定用户UID卖家免手续费 20170425
		$free_sql = ("select uid,exp_time from share_user.pub_user_free where uid=$uid");
		$free_ret = $a->findSql($free_sql);
		$now_time = date("Y-m-d H:i:s");
		if($free_ret[0]['exp_time']>$now_time){
			$aa = $price; //卖家入账金额
			$bb = 0; //0手续费
		}
		
        //如果卖家账户不存在，新建
        if($reg==false){
            $balance=$aa;
            $draw=0;  //不可提现
            $fund=bcmul($price,$sFund);
            $a->runSql("insert into ykjhqcom.lib_member_account(`uid`,`balance`,`draw`,`fund`,`update_time`) values($uid,$balance,$draw,$fund,'$utime')");
        }else {
            $balance = bcadd($reg[0]['balance'],$aa);
            $fund=bcadd($reg[0]['fund'],bcmul($price,$sFund));
            //更改卖家账上余额
            $update_money_sql = "update ykjhqcom.lib_member_account set balance=$balance,fund=$fund where uid=$uid ";
            $a->runSql($update_money_sql);
        }
        //平台手续费入账
        //查询平台余额
        $ping=$a->findSql("select balance from ykjhqcom.lib_member_account where uid='88888' FOR UPDATE");
        $acc=bcadd($ping[0]['balance'],$bb);
        $upd="update ykjhqcom.lib_member_account set balance=$acc where uid=88888";
        $a->runSql($upd);

        //财务变化流水表   ----卖家入款流水记录
        $brow="insert into ykjhqcom.lib_member_records (`uid`,`order_id`,`type`,`amount`,`ip`,`deal_time`,`note`,`balance`,`y`,`m`,`d`) values($uid,$order_id,501,$aa,'$ip','".time()."','$note(扣除手续费{$bb}元)',$balance,$y,$m,$d)";
        $a->runSql($brow);

        //增加资金流水账单（平台总流水）  --【用户资金走向】
        $ins="insert into cmpai.pan_plat_property(uid,property,deal_time,Y,m,d) values($uid,$aa,'$deal_time',$y,$m,$d)";
        $a->runSql($ins);

        //平台资金流水账单（平台总流水）---【平台入账】
        $plat_ins="insert into cmpai.pan_plat_property(uid,property,deal_time,Y,m,d) values(88888,$bb,'$deal_time',$y,$m,$d)";
        $a->runSql($plat_ins);

        //日志
        user_log($uid,606,$ip,'【卖家资产】卖家：'.$uid.'入账'.$aa.'元，扣除手续费'.$bb.'元，续费资金为'.$fund.'元，账户余额'.$balance.'元');
        user_log(88888,607,$ip,'【平台资产】平台：'.$uid.'卖家委托卖域名交易完成，平台收入（手续费）'.$bb.'元，账户余额'.$acc.'元');
		
		//---处理当前卖家，是有推荐人的情况---begin
		$revenue_sharing = bcmul($bb,0.3);//算出交易分成 (手续费的30%)
		if($revenue_sharing>0){
			$pub_ecommend = new pub_ecommend();
			$pub_ecommend_log = new pub_ecommend_log();
			$ret = $pub_ecommend->find(array('reg_uid'=>$uid));
			$ecommend_uid = $ret['ecommend_uid'];
			if($ecommend_uid>0){
				$pub_ecommend_log->create(array('reg_uid'=>$uid,'ecommend_uid'=>$ecommend_uid,'revenue_sharing'=>$revenue_sharing,'note'=>'平台手续费'.$bb.'元，推荐人获得分成'.$revenue_sharing.'元。'));
				//查询出推荐人的余额
				 $ecommend_ret = $a->findSql("select balance,draw,fund from ykjhqcom.lib_member_account where uid=$ecommend_uid FOR UPDATE");
				//如果推荐人账户不存在，新建
				if($ecommend_ret==false){
					$balance=$revenue_sharing;
					$a->runSql("insert into ykjhqcom.lib_member_account(`uid`,`balance`,`draw`,`fund`,`update_time`) values($ecommend_uid,$balance,0,0,'$utime')");
				}else {
					$balance = bcadd($ecommend_ret[0]['balance'],$revenue_sharing);
					//更改卖家账上余额
					$update_money_sql = "update ykjhqcom.lib_member_account set balance=$balance where uid=$ecommend_uid ";
					$a->runSql($update_money_sql);
				}
				//生成订单号，YmdHis+uid+一个随机数
				$order_id = 'FC'.date("YmdHis").$ecommend_uid.mt_rand(100000,999999);					
				//财务变化流水表   ----推荐人入款流水记录
				$lib_member_records = new lib_member_records();
				$lib_member_records->create(array('uid'=>$ecommend_uid,'order_id'=>$order_id,'type'=>501,'amount'=>$revenue_sharing,'ip'=>$ip,'deal_time'=>time(),'note'=>'交易分成'.$revenue_sharing.'元','balance'=>$balance,'y'=>$y,'m'=>$m,'d'=>$d));			
			}
		}	
		//---处理当前卖家，是有推荐人的情况---end			

    }else {
        //买家，扣掉
        //查询买家【账户】
        $reg = $a->findSql("select balance,freeze_money,draw,fund from ykjhqcom.lib_member_account where uid=$uid FOR UPDATE");

        //处理买家的余额，和委托买入时的冻结余额
        $balance=bcsub($reg[0]['balance'],$price);   //账户余额扣掉花费
        $new_freeze = bcsub($reg[0]['freeze_money'],$in_price); //冻结金额 去掉已经交易成功的 ---161221改
        $fund=bcadd($reg[0]['fund'],bcmul($price,$bFund));   //续费资金
        $draw=bcsub($reg[0]['draw'],$price);               //不可提现金额

        //更改买家账户信息
        $upd_money_sql = "update ykjhqcom.lib_member_account set balance=$balance,freeze_money=$new_freeze,draw=$draw,fund=$fund where uid=$uid ";
        $a->runSql($upd_money_sql);

        //财务变化流水表 ---  [买家消费记录]
        $pay=0-$price;   //消费前加个 - 符号

        $brow="insert into ykjhqcom.lib_member_records (`uid`,`order_id`,`type`,`amount`,`ip`,`deal_time`,`note`,`balance`,`y`,`m`,`d`) values($uid,$order_id,500,$pay,'$ip','".time()."','$note',$balance,$y,$m,$d)";
        $a->runSql($brow);
		
        //增加资金流水账单
        //买家消费
        $ins="insert into cmpai.pan_plat_property(uid,property,deal_time,Y,m,d) values($uid,$pay,'$deal_time',$y,$m,$d)";
        $a->runSql($ins);

        //日志
        user_log($uid,605,$ip,'【买家资产】买家：'.$uid.'用户账户扣除购买域名费用'.$price.'元，解冻资金'.$in_price.'元，执行前查冻结总金额'.$reg[0]['freeze_money'].'元->执行此条时冻结总金额'.$new_freeze.'，账户余额'.$balance.'元，不可提现金额减少'.$price.',目前为'.$draw.'元。');
    }

}

//交易中实际域名用户转换
//$uid  新用户id (买)
//$u1   旧用户id （卖）
//$num  个数
//$id   （委托买）
//$s_id   （委托卖）
function auto_domain_uid($a,$uid,$u1,$num,$typeid,$id,$s_id,$price,$deal_status){
	write("【后台域名易主-执行开始】-------------------begin");
    $ip=get_client_ip();
    $now=date("Y-m-d H:i:s");
    //通过委托卖订单，选出还在出售中的域名数组列表与ID
    $d_sql="select id,domain,domain_id from cmpai.pan_deal_domain where tid = $s_id and status=0 and uid=$u1 limit $num ";
    $domain_ret = $a->findSql($d_sql);
	write('[后台]查卖委托订单：'.$s_id.'，SQL语句：'.$a->dumpSql());
	$ids = array();
	$domains = array();
	foreach($domain_ret as $v) {
		$ids[] = $v['id'];
		$domains[] = $v['domain'];
		$domain_id[] = $v['domain_id']; //后加，以前的无，备用！
		$domains_a[] = "'".$v['domain']."'";
	}
	$ids_str = implode(',',$ids);
	$domain_id_str = implode(',',$domain_id);
	$domain_str = implode(',',$domains);
	$domain_str_a = implode(',',$domains_a); //加了'	
	
    if(count($ids) != $num ){
		$a->runSql('ROLLBACK');
        write("【后台域名易主-系统错误1】，委托买$id --- 委托卖".$s_id."，成交 $num 个域名，修改域名：$domain_str，"."数目对不上。");
		exit('【后台域名易主-系统错误1（原因）】：成交了'.$num.'个域名，但数目对不上!');
    }
    //修改pan_deal_domain 域名状态
    $u_deal_sql="update cmpai.pan_deal_domain set status=1,deal_uid=$uid,deal_time='{$now}',deal_price='{$price}',deal_tid={$id},deal_status={$deal_status} where id in($ids_str)";
    $a->runSql($u_deal_sql);
	$update_deal_domain_1 = $a->affectedRows();
	write('[后台域名易主]更新pan_deal_domain表 status=1,deal_uid=买家UID时，SQL语句：'.$a->dumpSql());
	write('[后台域名易主]更新pan_deal_domain表 status=1,deal_uid=买家UID时，影响行数：'.$update_deal_domain_1);	
	
	//修改pan_domain_in 域名状态
    $usql="update cmpai.pan_domain_in set uid={$uid},upd_time='{$now}',locked=0 where domain in($domain_str_a) and uid=$u1 and locked=2";
    $a->runSql($usql);
	// write($usql);
	$update_locked_0 = $a->affectedRows();
	write('[后台域名易主]更新pan_domain_in表 locked=0 and uid=新UID时，SQL语句：'.$a->dumpSql());
	write('[后台域名易主]更新pan_domain_in表 locked=0 and uid=新UID时，影响行数：'.$update_locked_0);	
	write('[后台域名易主]实际表(pan_domain_in)域名id为（'.$domain_id_str.'），(pan_deal_domain)表域名id为（'.$ids_str.'）的域名所有人由'.$u1.'修改为'.$uid.',数目为'.$num.'个,域名为：'.$domain_str);
    
	if($update_deal_domain_1 != $update_locked_0){
		$a->runSql('ROLLBACK');
        write("【后台域名易主-系统错误2】，委托买$id --- 委托卖$s_id，"."成交 $num 个域名，修改域名：$domain_str，"."数目对不上。");
        write("【后台域名易主-系统错误2（原因）】，pan_deal_domain更新行数：$update_deal_domain_1 ，pan_domain_in更新行数：$update_locked_0，"."数目对不上。");
		exit('[后台域名易主]错误2：成交了'.$num.'个域名，但数目对不上!');		
	}
	user_log($uid,612,$ip,'【域名易主】：实际表(pan_domain_in)域名id为（'.$domain_id_str.'），(pan_deal_domain)表域名id为（'.$ids_str.'）的域名所有人由'.$u1.'修改为'.$uid.',数目为'.$num.'个,域名为：'.$domain_str);
	
	//-----最次预检测，再次验证域名是否已经转移成功-----begin
	$pan = new pan_domain_in();
	$pan_in_count = $pan->findCount("uid = $uid and locked=0 and domain in($domain_str_a)"); //查买家 locked=0数量
	write('***预检测，再次验证域名是否已经转移成功，买家-查询SQL语句：'.$pan->dumpSql());
	write('***预检测，再次验证域名是否已经添加成功，买家-查询返回总数='.$pan_in_count);
	if($pan_in_count != $update_deal_domain_1){ //如果不相等
		write('[后台域名易主]错误3：最次预检测，再次验证域名是否已经转移成功，但---买家---数目对不上!');			
		exit('[后台域名易主]错误3：最次预检测，再次验证域名是否已经转移成功，但---买家---数目对不上!');			
	}
	$pan_out_count = $pan->findCount("uid = $u1 and locked=2 and domain in($domain_str_a)"); //查卖家 locked=2数量
	write('***预检测，再次验证域名是否已经转移成功，卖家-查询SQL语句：'.$pan->dumpSql());
	write('***预检测，再次验证域名是否已经添加成功，卖家-查询返回总数='.$pan_out_count);
	if($pan_out_count != 0){ //等于0才是正确的
		write('[后台域名易主]错误3：最次预检测，再次验证域名是否已经转移成功，但---卖家---数目对不上!');			
		exit('[后台域名易主]错误3：最次预检测，再次验证域名是否已经转移成功，但---卖家---数目对不上!');			
	}	
	//-----最次预检测，再次验证域名是否已经转移成功-----end	
	
	write("【后台域名易主-执行结束】-------------------end");
    return $domain_str;
}
//*****一口价域名到期下架
function auto_ykj($is_score=0){ 
	// $is_score = 0;
	$ip = get_client_ip();
	$now_time = time();
	$now_time_str = date("Y-m-d H:i:s");
	$pan_domain_ykj = new pan_domain_ykj();
	$pan_domain_in = new pan_domain_in(); // 域名实盘表
	$sql="select id,domain_id from cmpai.pan_domain_ykj where status=1 and is_score>={$is_score} and sy_time<='{$now_time_str}'";
	$ret = $pan_domain_ykj->findSql($sql);
	$count = count($ret);
	if(!$ret){
		echo '暂无一口价到期的域名 is_score='.$is_score;
		return;
	}	
	
	//------------限制系统全部用户请求操作域名相关----------begin
	$domain_action = 'domain_action';
	if(false === cache_a($domain_action,'system',20)){
		echo '当前有用户在操作域名中，线程已返回!';
		return;	
	}	
	//------------限制系统全部用户请求操作域名相关----------end				
	
	foreach($ret as $v) {
			$domain_id_arr[]=(int)$v['domain_id'];
			$ykj_id_arr[]=(int)$v['id'];
	}
	$domain_id_ids = implode(',',$domain_id_arr);
	$ykj_id_ids = implode(',',$ykj_id_arr);

	$sp = new pan_domain_ykj();
	$sql_sw = false;
	$sp->runSql("SET AUTOCOMMIT=0");
	$sp->runSql('BEGIN'); //开启事务		
		$pan_domain_ykj->update("id in ($ykj_id_ids) and status=1",array('status'=>4));
		$update_ykj_row = $pan_domain_ykj->affectedRows(); //影响行数
		if($update_ykj_row!=$count){
			exit('错误提示：系统更新数量与实际不符');						
		}
		//***特别注意：如果是在停放中的域名，是不需要更新状态的***
		if($is_score==1){
			//停放中的域名
			$pan_domain_in->update("locked=9 and id in ($domain_id_ids)",array('locked'=>9,'upd_time'=>$now_time_str));
			$update_domain_row = $pan_domain_in->affectedRows(); //影响行数				
		}else{
			$pan_domain_in->update("locked=11 and id in ($domain_id_ids)",array('locked'=>0,'upd_time'=>$now_time_str));
			$update_domain_row = $pan_domain_in->affectedRows(); //影响行数
		}
		if($update_domain_row!=$count){
			exit('错误提示：系统更新域名数量与实际不符');				
		}							
	user_log(88888, 1604, $ip, "【系统自动一口价到期下架 is_score=$is_score 】：$count 条记录，域名编号：$domain_id_ids ，一口价编号：$ykj_id_ids");
	$sql_sw = true;
	if(false===$sql_sw){
		$sp->runSql('ROLLBACK'); //回滚事务
		exit('系统处理出错，已回滚事务。');
	}else{
		$sp->runSql('COMMIT'); //提交事务
		echo '一口价域名到期下架成功，数量：'.$count;
		cache_a($domain_action,null);//删除限制系统全部用户请求操作域名相关并发缓存
		return true;
	}		
}
//待续费
/**
 * 检查pan_domain_in的域名到期时间，修改状态为3
 */
function auto_renew(){
			$ip=get_client_ip();
			$now=date("Y-m-d H:i:s");
			// $expire=time()+24*3600*expire_limit;
			$expire=time()+24*3600;
			$expire_time=date("Y-m-d",$expire);
			$sql_sw = false;
			$pan=new pan_domain_in();
			//先不进事务，查询是否有快到期的域名---begin
			$sql="select count(*) from cmpai.pan_domain_in where expire_time <= '{$expire_time}' and locked in(0,1,2)"; //正常、锁定、出售中
			$res=$pan->findSql($sql);
			$count=$res[0]['count(*)'];
			if($count==0){
				echo '快到期的域名数量:'.$count.'---';
				return;
			}else{
				echo '快到期的域名数量>1='.$count;
			}	
			//先不进事务，查询是否有快到期的域名---end

			//------------限制系统全部用户请求操作域名相关----------begin
			$domain_action = 'domain_action';
			if(false === cache_a($domain_action,'system',20)){
				echo '当前有用户在操作域名中，线程已返回!';
				return;	
			}	
			//------------限制系统全部用户请求操作域名相关----------end	
			write("【后台过期待续费下架-执行开始】-------------------begin");
			$pan->runSql("set autocommit=0");
			$pan->runSql('begin');
			
			//---------修改状态为正常 或者为锁定 的域名-----begin
			$sql="select id,domain from cmpai.pan_domain_in where (locked = 0 or locked=1) and expire_time <= '{$expire_time}' FOR UPDATE";
			$ret = $pan->findSql($sql);
			if($ret){
				//组合出--需要的值---
				$ids = array();
				$domains = array();
				foreach($ret as $v) {
					$ids[] = $v['id'];
					$domains[] = $v['domain'];
				}
				$ids_str = implode(',',$ids);		
				$domains_str = implode(',',$domains);			

				if(count($ids)!=count($domains)){
					exit('[正常或锁定]ID跟域名数目不相等!');
				}
				write("\r\n");
				write('[后台过期待续费下架[正常或锁定]]数量：'.count($ids));
				if(!empty($ids)) {
					$u_sql = "update cmpai.pan_domain_in set locked=3,upd_time='{$now}' where id in($ids_str)";
					$pan->runSql($u_sql);
					write('[后台过期待续费下架[正常或锁定]]更新pan_domain_in表 locked=3，执行sql：'.$pan->dumpSql());
					$update_pan_domain_in_0_1 = $pan->affectedRows();//影响行数
					write('[后台过期待续费下架[正常或锁定]]更新pan_domain_in表 locked=3，影响行数：'.$update_pan_domain_in_0_1);
					if($update_pan_domain_in_0_1!=count($ids)){
						write('[后台过期待续费下架[正常或锁定]]更新pan_domain_in表 locked=3，影响行数：'.$update_pan_domain_in_0_1.'，与实际数量不相等，'.count($ids));
						exit('[正常或锁定]更新行数与实际数据不相等!');
					}
					
					//-----最次预检测，再次验证是否已经变更待续费状态成功
					$pan_domain_in = new pan_domain_in();
					$pan_domain_count = $pan_domain_in->findCount("locked = 3 and id in($ids_str)");
					
					if($pan_domain_count != count($ids)){
						write("【后台过期待续费下架[正常或锁定]-系统错误1】pan_domain_in表，预验证--数目对不上。");
						exit("【后台过期待续费下架[正常或锁定]-系统错误1】pan_domain_in表，预验证--数目对不上。");
					}		
					user_log(88888, 615, $ip, "【系统自动修改(正常和锁定)[正常或锁定]】：".count($ids)."条记录，编号为：".$ids_str."，域名为：".$domains_str." 。");
				}
			}
			//----------修改状态为正常 或者为锁定 的域名-----end
			
			//--------------------修改状态为出售中的域名-----begin
			$sql1 = "select id,domain from cmpai.pan_domain_in where locked=2 and expire_time <= '{$expire_time}' FOR UPDATE";
			$ret1 = $pan->findSql($sql1);
			if($ret1){
				//组合出--需要的值---
				$ids = array();
				$domains = array();
				$domains_a = array();
				foreach($ret1 as $v) {
					$ids[] = $v['id'];
					$domains[] = $v['domain'];
					$domains_a[] = "'".$v['domain']."'";
				}
				$ids_str = implode(',',$ids);		
				$domains_str = implode(',',$domains);			
				$domains_a_str = implode(',',$domains_a);//加了'		
				
				if(count($ids)!=count($domains)){
					exit('[出售中]ID跟域名数目不相等!');
				}
				write("\r\n");				
				write('[后台过期待续费下架[出售中]]数量：'.count($ids));
				//撤销pan_deal_domain上的出售
				$deal_sql = "select group_concat(tid) as tids from cmpai.pan_deal_domain where domain in($domains_a_str) and status=0 FOR UPDATE";
				$deal_res = $pan->findSql($deal_sql);
				$tids = $deal_res[0]['tids'];
				if(!empty($tids)) {
					//修改pan_deal_domain上的状态
					$u_deal_sql = "update cmpai.pan_deal_domain set status=3,cancel_time='{$now}' where status=0 and domain in($domains_a_str)";
					$pan->runSql($u_deal_sql);
					write('[后台过期待续费下架[出售中]]更新pan_deal_domain表 status=3，执行sql：'.$pan->dumpSql());
					$update_pan_deal_domain = $pan->affectedRows();//影响行数
					write('[后台过期待续费下架[出售中]]更新pan_deal_domain表 status=3，影响行数：'.$update_pan_deal_domain);
					if($update_pan_deal_domain!=count($ids)){
						write('[后台过期待续费下架[出售中]]更新pan_deal_domain表 status=3，影响行数：'.$update_pan_deal_domain.'，与实际数量不相等，'.count($ids));
						exit('[出售中]更新行数与实际数据不相等!');			
					}
					
					//-----最次预检测，再次验证是否已经变更状态成功-----begin
					$pan_deal_domain = new pan_deal_domain();
					$pan_deal_domain_count = $pan_deal_domain->findCount("status = 3 and tid in($tids)");
					if($pan_deal_domain_count != count($ids)){
						write("【后台过期待续费下架[出售中]-系统错误1】pan_deal_domain表，预验证--数目对不上。");
						exit("【后台过期待续费下架[出售中]-系统错误1】pan_deal_domain表，预验证--数目对不上。");
					}			
					//-----最次预检测，再次验证是否已经变更状态成功-----end
					
					$tids1_1='';
					//撤销pan_trade上的委托
					$tids1 = array_unique(explode(',', $tids));
					$tids1_1 = implode(',', $tids1);
					$u_trade_sql = "update cmpai.pan_trade set status_1=3,cancel_time='{$now}' where id in($tids1_1)";
					$pan->runSql($u_trade_sql);
					write('[后台过期待续费下架[出售中]]更新pan_trade表 status_1=3，执行sql：'.$pan->dumpSql());
					$update_pan_trade = $pan->affectedRows();//影响行数
					write('[后台过期待续费下架[出售中]]更新pan_trade表 status_1=3，影响行数：'.$update_pan_trade);
					if($update_pan_trade!=count($tids1)){
						write('[后台过期待续费下架[出售中]]更新pan_trade表 status_1=3，影响行数：'.$update_pan_trade.'，与实际数量不相等，'.count($tids1));
						exit('[出售中]更新行数与实际数据不相等!');			
					}	
					//-----最次预检测，再次验证是否已经变更状态成功-----begin
					$pan_trade = new pan_trade();
					$pan_trade_count = $pan_trade->findCount("status_1 = 3 and id in($tids1_1)");
					if($pan_trade_count != count($tids1)){
						write("【后台过期待续费下架[出售中]-系统错误2】pan_trade表，预验证--数目对不上。");
						exit("【后台过期待续费下架[出售中]-系统错误2】pan_trade表，预验证--数目对不上。");
					}			
					//-----最次预检测，再次验证是否已经变更状态成功-----end		
					//修改实盘表状态为待续费
					$u_sql1 = "update cmpai.pan_domain_in set locked=3,upd_time='{$now}' where id in($ids_str)";
					$pan->runSql($u_sql1);
					write("\r\n");
					write('[后台过期待续费下架[出售中]]更新pan_domain_in表 locked=3，执行sql：'.$pan->dumpSql());
					$update_pan_domain_in = $pan->affectedRows();//影响行数
					write('[后台过期待续费下架[出售中]]更新pan_domain_in表 locked=3，影响行数：'.$update_pan_domain_in);
					if($update_pan_domain_in!=count($ids)){
						write('[后台过期待续费下架[出售中]]更新pan_domain_in表 locked=3，影响行数：'.$update_pan_domain_in.'，与实际数量不相等，'.count($ids));
						exit('[出售中]更新行数与实际数据不相等!');
					}
					
					//-----最次预检测，再次验证是否已经变更待续费状态成功
					$pan_domain_in = new pan_domain_in();
					$pan_domain_count = $pan_domain_in->findCount("locked = 3 and id in($ids_str)");
					
					if($pan_domain_count != count($ids)){
						write("【后台过期待续费下架[出售中]-系统错误1】pan_domain_in表，预验证--数目对不上。");
						exit("【后台过期待续费下架[出售中]-系统错误1】pan_domain_in表，预验证--数目对不上。");
					}		
					user_log(88888, 615, $ip, "【系统自动修改(出售中)[出售中]】：".count(explode(',', $ids))."条记录，域名编号为：".$ids."，域名为：".$domains_str.",委托订单编号为：".$tids1_1."。");
				}
			}
			//--------------------修改状态为出售中的域名-----begin							

			$sql_sw = true;
			if(false===$sql_sw){
				$pan->runSql('ROLLBACK'); //回滚事务
				write('以上一步骤系统事务出错，已回滚?');
				json_s(array('status'=>205,'msg'=>'--待续费--系统事务出错，请稍候重试。','del_cache_a'=>'cm_auto_buy'));
			}else{
				$pan->runSql('commit'); //提交事务
			}
			cache_a($domain_action,null);//删除限制系统全部用户请求操作域名相关并发缓存
			write("【后台过期待续费-执行结束】-------------------end");
}

//待下架
/**
 * 检查pan_trade的到期时间，撤销pan_deal_domain的出售，改变pan_domain_in的域名状态，修改pan_user_domain的出售个数下架订单
 */
function auto_undersale(){
	$ip = get_client_ip();
	$now = date("Y-m-d H:i:s");
	$pan = new pan_trade();
	//先不进事务，查询是否有快到期的域名---begin
	$sql="select count(*) from cmpai.pan_trade where expire_time <= '{$now}' and status_1=0"; //全部委托订单交易中（包括卖或买）-到期时间小于当前的
	$res=$pan->findSql($sql);
	$count=$res[0]['count(*)'];
	if($count==0){
		echo '到期的交易委单数量:'.$count.'---';
		return;
	}else{
		echo '到期的交易委单数量>1='.$count;
	}			
	//先不进事务，查询是否有快到期的域名---end	
	
	//------------限制系统全部用户请求操作域名相关----------begin
	$domain_action = 'domain_action';
	if(false === cache_a($domain_action,'system',30)){
		echo '当前有用户在操作域名中，线程已返回!';
		return;	
	}	
	//------------限制系统全部用户请求操作域名相关----------end	
	
	//-----下架所有过期的交易状态的委托买-----
	$sql="select id,uid from cmpai.pan_trade where expire_time <= '{$now}' and status_1=0 and status_2=0"; 
	$tid_ret = $pan->findSql($sql);
	//组合出--需要的值---
	$tids = array();
	$uids = array();
	foreach($tid_ret as $v) {
		$tids[] = $v['id'];
		$uids[] = $v['uid'];
	}
	$uids = array_unique($uids); //移除数组中重复的值
	$tids_str = implode(',',$tids);		
	$uids_str = implode(',',$uids);		

	if($tids_str!='' && $tids) {
		$pan->runSql("set autocommit=0"); //---一定要开事务
		$pan->runSql('begin');
		$sql_sw = false;
		write("【后台委单到期下架-卖-执行开始】-------------------begin");
		write("\r\n委托过期卖 pan_trade id:".$tids_str);
		//查询对应的过了委托期限的出售中的域名
		$sql = "select id,domain,uid from cmpai.pan_deal_domain where status=0 and tid in($tids_str) FOR UPDATE";
		$pan_deal_domain_ret = $pan->findSql($sql);
		//组合出--需要的值---
		$ids = array();
		$domains = array();
		$domains_a = array();
		foreach($pan_deal_domain_ret as $v) {
			$ids[] = $v['id'];
			$domains[] = $v['domain'];
			$domains_a[] = "'".$v['domain']."'";//加上'
		}
		$ids_str = implode(',',$ids);		
		$domains_str = implode(',',$domains);		
		$domains_a_str = implode(',',$domains_a);//加上'
		if(count($ids)==0){
			write("后台委单到期下架-卖-系统错误0（原因）ids==0!");
			exit("后台委单到期下架-卖-系统错误0（原因）ids==0!");
		}
		if(count($ids)<count($tids)){
			write("后台委单到期下架-卖-系统错误00（原因）ids<tids!");
			exit("后台委单到期下架-卖-系统错误00（原因）ids<tids!");
		}			
		if (count($ids) != count($domains)) {
		   write("【后台委单到期下架-卖-系统错误1（原因）】id总数：".count($tids)."，域名总数：".count($domains)." 数目对不上！---待下架过期卖 pan_trade id:" . $tids_str. "--domain:" . $domains_str . " 数目不对");
		   exit("后台委单到期下架-卖-系统错误1（原因）数目对不上!");
		}
		//修改 委托表
		$sql = "update cmpai.pan_trade set status_1=3,cancel_time='{$now}' where id in($tids_str)"; //状态变更为取消
		$pan->runSql($sql);
		write('[后台委单到期下架-卖]更新pan_trade表 status_1=3,cancel_time=当前时间时，SQL语句：'.$pan->dumpSql());
		$update_pan_trade_c = $pan->affectedRows();//影响行数
		write('[后台委单到期下架-卖]更新pan_trade表 status_1=3,cancel_time=当前时间时，影响行数：'.$update_pan_trade_c);

		if($update_pan_trade_c != count($tids)){
			$pan->runSql('ROLLBACK');
			write("【后台委单到期下架-卖-系统错误2】，更新pan_trade与实际数目对不上。");
			write("【后台委单到期下架-卖-系统错误2（原因）】，pan_trade更新行数：$update_pan_trade_c ，实际要更新行数：".count($tids)." 数目对不上。");
			exit('后台委单到期下架-卖-系统错误2（原因）数目对不上!');	
		}
		
		//修改 实米交易记录表
		$sql = "update cmpai.pan_deal_domain set status=3,cancel_time='{$now}' where id in($ids_str)"; //状态变更为取消
		$pan->runSql($sql);
		write('[后台委单到期下架-卖]更新pan_deal_domain表 status=3,cancel_time=当前时间时，SQL语句：'.$pan->dumpSql());
		$update_pan_deal_domain_c = $pan->affectedRows();//影响行数
		write('[后台委单到期下架-卖]更新pan_deal_domain表 status=3,cancel_time=当前时间时，影响行数：'.$update_pan_deal_domain_c);
		
		if($update_pan_deal_domain_c != count($ids)){
			$pan->runSql('ROLLBACK');
			write("【后台委单到期下架-卖-系统错误3】，更新pan_deal_domain与实际数目对不上。");
			write("【后台委单到期下架-卖-系统错误3（原因）】，pan_deal_domain更新行数：$update_pan_deal_domain_c ，实际要更新行数：".count($ids)." 数目对不上。");
			exit('后台委单到期下架-卖-系统错误3（原因）数目对不上!');	
		}	
		
		//修改 域名实际表
		$sql = "update cmpai.pan_domain_in set locked=0,upd_time='{$now}' where domain in($domains_a_str)"; //状态变更为正常
		$pan->runSql($sql);
		write('[后台委单到期下架-卖]更新pan_domain_in表 locked=0,upd_time=当前时间时，SQL语句：'.$pan->dumpSql());
		$update_pan_domain_in_c = $pan->affectedRows();//影响行数
		write('[后台委单到期下架-卖]更新pan_domain_in表 locked=0,upd_time=当前时间时，影响行数：'.$update_pan_domain_in_c);
		
		if($update_pan_deal_domain_c != $update_pan_domain_in_c){
			$pan->runSql('ROLLBACK');
			write("【后台委单到期下架-卖-系统错误4】，更新pan_domain_in与pan_deal_domain表数目对不上。");
			write("【后台委单到期下架-卖-系统错误4（原因）】，pan_deal_domain更新行数：$update_pan_deal_domain_c ，pan_domain_in更新行数：".$update_pan_domain_in_c." 数目对不上。");
			exit('后台委单到期下架-卖-系统错误4（原因）数目对不上!');	
		}				
		//-----最次预检测，再次验证是否已经下架成功-----begin
		$pan_deal_domain = new pan_deal_domain();
		$pan_domain_in = new pan_domain_in();
		$pan_trade = new pan_trade();
		$pan_deal_count = $pan_deal_domain->findCount("status = 3 and id in($ids_str)");
		$pan_domain_count = $pan_domain_in->findCount("locked = 0 and domain in($domains_a_str)");
		$pan_trade_count = $pan_trade->findCount("status_1=3 and id in($tids_str)");
		
		if($pan_deal_count != $pan_domain_count){
			$pan->runSql('ROLLBACK');
			write("【后台委单到期下架-卖-系统错误5】，预验证--数目对不上。");
			exit("【后台委单到期下架-卖-系统错误5】，预验证--数目对不上。");
		}			
		if($pan_trade_count != count($tids)){
			$pan->runSql('ROLLBACK');
			write("【后台委单到期下架-卖-系统错误6】，预验证--数目对不上。");
			exit("【后台委单到期下架-卖-系统错误6】，预验证--数目对不上。");
		}				
		//-----最次预检测，再次验证是否已经下架成功-----end
		user_log(88888,616,$ip, "【系统下架委托卖】：修改系统委托表，委托编号:".$tids_str."，修改了".count($tids)."条记录；修改实米交易表，修改域名：".$domains_str."，修改了".count($domains)."条记录。");
		$sql_sw = true;
		if(false===$sql_sw){
			$pan->runSql('ROLLBACK'); //回滚事务
			write('【后台委单到期下架-卖-系统错误（原因）】，SQL事务回滚!');
			cache_a($domain_action,null);//删除限制系统全部用户请求操作域名相关并发缓存
			exit('【后台委单到期下架-卖-系统错误（原因）】，SQL事务回滚!');
		}else{
			$pan->runSql('COMMIT'); //提交事务
			write("【后台委单到期下架-卖-执行结束】-------------------end");
			echo('后台委单到期下架-卖-完成-');
		}			
	}

	//-----下架所有过期的交易状态的委托买-----
	$sql="select * from cmpai.pan_trade where expire_time <= '{$now}' and status_1=0 and status_2=1"; //买单
	$pan_trade_ret = $pan->findSql($sql);
	if($pan_trade_ret){
		$pan->runSql("set autocommit=0"); //---一定要开事务
		$pan->runSql('begin');
		$sql_sw = false;
		write("【后台委单到期下架-买-执行开始】-------------------begin");
		$tids=array();
		$update_account = 0;
		foreach($pan_trade_ret as $kb=>$vb){
			//解除资产冻结
			//下架未成交域名个数
			$unsale_num = $vb['number'] - $vb['deal_num'];
			//下架的域名的冻结总价= 下架未成交个数 * 单价；
			$unsale_price = bcmul($vb['price'],$unsale_num);
			$bs_sql = "select freeze_money from ykjhqcom.lib_member_account where uid=".$vb['uid'].' FOR UPDATE';
			$resbb = $pan->findSql($bs_sql);
			$freeze = bcsub($resbb[0]['freeze_money'],$unsale_price);
			$Bu_sql="update ykjhqcom.lib_member_account set freeze_money=$freeze where uid=".$vb['uid'];
			$pan->runSql($Bu_sql);
			write('【后台委单到期下架-买】更新用户资金表，SQL语句：'.$pan->dumpSql());
			$update_account_c = $pan->affectedRows();//影响行数
			$update_account = $update_account + $update_account_c;
			user_log(88888,616,$ip,"【系统下架委托买】:解除用户（".$vb['uid'].")的冻结资金".$unsale_price."元=单价".$vb['price']."元*".$unsale_num."剩余数量，执行前查冻结总金额".$resbb[0]['freeze_money']."元->执行此条时冻结总金额".$freeze."元，[过期订单号为".$vb['id']."]");
			write('【后台委单到期下架-买】'.":解除用户（".$vb['uid'].")的冻结资金".$unsale_price."元=单价".$vb['price']."元*".$unsale_num."剩余数量，执行前查冻结总金额".$resbb[0]['freeze_money']."元->执行此条时冻结总金额".$freeze."元，[过期订单号为".$vb['id']."]");
			//下架委托
			$tids[]=$vb['id'];
		}
		if($update_account != count($pan_trade_ret)){
			$pan->runSql('ROLLBACK'); //回滚事务
			write('【后台委单到期下架-买-系统错误1】更新用户资金表行数与实际需要更新的行数对不上，用户资金表影响行数：'.$update_account.'-实际要更新的行数：'.count($pan_trade_ret));
			exit('[后台委单到期下架-买-系统错误1（原因）]，更新用户资金表行数与实际需要更新的行数对不上');				
		}
		$tids_str = implode(',',$tids);
		write("委托过期买 pan_trade id:".$tids_str);
		$ubt_sql = "update cmpai.pan_trade set status_1=3,cancel_time='{$now}' where id in($tids_str)";
		$pan->runSql($ubt_sql);
		write("【系统下架委托买】：修改系统pan_trade委托表 status_1=3,cancel_time=当前时间，委托编号：".$tids_str."，影响行数：".$pan->affectedRows()."-SQL语句：".$pan->dumpSql());
		$update_pan_trade_c = $pan->affectedRows();//影响行数
		if($update_pan_trade_c != count($pan_trade_ret)){
			$pan->runSql('ROLLBACK'); //回滚事务
			write('【后台委单到期下架-买-系统错误2】更新pan_trade表行数与实际需要更新的行数对不上，pan_trade表影响行数：'.$update_pan_trade_c.'-实际要更新的行数：'.count($pan_trade_ret));
			exit('[后台委单到期下架-买-系统错误2（原因）]，更新pan_trade表行数与实际需要更新的行数对不上');				
		}			
		user_log(88888,616,$ip,"【系统下架委托买】：修改系统委托表，委托编号：".$tids_str."，修改了".$update_pan_trade_c."条记录");
		$sql_sw = true;
		if(false===$sql_sw){
			$pan->runSql('ROLLBACK'); //回滚事务
			write('【后台委单到期下架-买-系统错误（原因）】，SQL事务回滚!');
			cache_a($domain_action,null);//删除限制系统全部用户请求操作域名相关并发缓存
			exit('【后台委单到期下架-买-系统错误（原因）】，SQL事务回滚!');
		}else{
			$pan->runSql('COMMIT'); //提交事务
			write("【后台委单到期下架-买-执行结束】-------------------end");
			cache_a($domain_action,null);//删除限制系统全部用户请求操作域名相关并发缓存
			echo('后台委单到期下架-买-完成-');
			return;
		}			
	}	
}

//发送邮件和短信
function auto_send($sta,$typeid,$uid,$price,$number,$dealM,$num,$note,$total_price,$vid=''){
    // *************查询委托域名类型*************************\\
	$a=new pan_trade();
    // $dname="select name from cmpai.new_ym_code where code={$typeid}";
    // $dres=$a->findSql($dname);
    // $dname=$dres[0]['name'];
	
	$_n = check_pz($typeid);  
	$dname = $_n[0]['name']; 	
	
    // *************查询email******************************\\
	$a=new lib_member();
    $email="select email from ykjhqcom.lib_member where uid={$uid}";
    $res=$a->findSql($email);
    $email=$res[0]['email'];


    // *************查询短信，mid*****************************\\
	$a=new pub_user();
    $msql="select mobile,mid from share_user.pub_user where uid=".$uid;
    $mres=$a->findSql($msql);
    $mobile=$mres[0]['mobile'];
    $mid=$mres[0]['mid'];

    if($sta==0){
        $sta="【卖出】";
    }else if($sta==1){
        $sta="【买入】";
    }
    $title="用户".$mid."，您委托的".$dname."于".date("Y-m-d H:i:s")."以单价".$dealM.'元成功'.$sta.$num."个。";
	$trade_time = date("Y-m-d H:i:s");
	//---添加站内短信
	$type =  '901';
	$tit  =  $dname.'成功'.$sta.$num.'个，单价'.$dealM.'元';
	$txt  =  '您委托的订单编号：'.$vid.'，以单价'.$dealM.'元成功'.$sta.$dname.''.$num.'个，交易总额'.$total_price.'元，交易时间：'.$trade_time;
	web_msg_send($tit,$type,$uid,$txt);	
	
    /**
     * 发送邮件
     */
	if(!empty($email) && preg_match("/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/", $email)){ //必须判断是否是邮箱
		$mail_content = '您以单价'.$dealM.'元成功'.$sta.$dname.''.$num.'个，交易总额'.$total_price.'元，交易时间：'.$trade_time;
		//---将邮件内容以JSON格式存到数据库---begin
		$contents = array();
		$contents['to'] = array($email);
		$contents['sub'] = array('%email%'=>array($email),'%mid%'=>array($mid),'%vid%'=>array($vid),'%content%'=>array($mail_content));
		$new_content = json_encode($contents);
		//---将邮件内容以JSON格式存到数据库---end		
		$result = send_mail($email,"炒米网(chaomi.cc)委托交易成功",$new_content,6);
        // if ($result) {
            // write("【发送成功-邮件$email】$new_content ");	
        // } else {
			// write("【发送出错-邮件$email】$new_content ");	
        // }		
			
	}
	
    /**
     * 发送短信
     */
	if($typeid>=800000 && $uid!=19538){ //排除掉二级域名、301帐号不发送短信
		if(!empty($mobile) && preg_match("/^1[34578]\d{9}$/", $mobile)){
			$result = send_msg($mobile,$title,6);
		}		
	}
}

//获取域名类型
function auto_get_name($a,$typeid){
	$_n = check_pz($typeid);  
	return $_n[0]['name']; 
}








