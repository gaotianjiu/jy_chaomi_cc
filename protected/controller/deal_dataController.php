<?php
class deal_dataController extends BaseController{
	function __construct(){
		parent::__construct();
		spClass("trades");
		$sso_user = check();
		$this->uid = $sso_user['uid'];
		$this->mid = $sso_user['mid'];		
	}	
	function domain_api(){
		//成交数据接口对外Api
		$cache_key = 'new_deal_trade_data_all_domain_api';
		$cache_data = cache_s($cache_key);
		if($cache_data){
			json_s($cache_data);//有缓存先取缓存数据
		}		
		$pan=spClass('pan_deal_domain');
		$sql = "select id,domain,deal_price,deal_time,typeid from cmpai.pan_deal_domain where status=1 and deal_time>'2018-04-27 13:00:00' and typeid>=800000 order by deal_time desc limit 1000";
		$data =$pan->findSql($sql);
		// $_data = array();
		// foreach($data as $r){
			// $r['domain'] = substr_replace($r['domain'],'**','1','2');
			// $_data[] = $r; 		
		// }		
		$data_new = array('status'=>200,'note'=>'Api接口数据实时更新，显示最新1000条记录','domain_list'=>$data);
		// $data_new = array('status'=>200,'note'=>'Api接口数据实时更新，显示最新1000条记录','domain_list'=>'');
		cache_s($cache_key,$data_new,7200);//将成交数据写入缓存->清空缓存由后台控制
		json_s($data_new);		
	}
	function nowApi() {
		//平台成交数据一览
		$cache_key = 'pan_deal_trade_now';
		$data['status'] = 200;
		$CacheData = cache_a('pan_deal_trade_now');
		if($CacheData){
			$CacheData['cache'] = true;
			json_s($CacheData);//有缓存先取缓存数据
		}	
		$pan_deal_trade = spClass('pan_deal_trade');
		//今日
		$date = date("Y-m-d"); 
		$date_sql = "deal_time>='$date'";
		$data['now'] = $date;
		//---今日成交量、成交额----begin
		$sql = "select sum(tot_price),sum(deal_num) from cmpai.pan_deal_trade where sta=0 and $date_sql";
		$ret = $pan_deal_trade->findSql($sql);
		$data['nowp'] = number_format(null_num($ret[0]['sum(tot_price)']));
		$data['nowc'] = number_format(null_num($ret[0]['sum(deal_num)']));
		//---今日日成交量、成交额----end
		
		//昨日
		$date = date("Y-m-d",strtotime('-1 day'));
		$date_t = date("Y-m-d",strtotime('-0 day'));
		$date_sql = "deal_time>='$date' and deal_time<'$date_t'";			
		$data['yet'] = $date;
		//---昨日成交量、成交额----begin
		$sql = "select sum(tot_price),sum(deal_num) from cmpai.pan_deal_trade where sta=0 and $date_sql";
		$ret = $pan_deal_trade->findSql($sql);
		$data['yetp'] = number_format(null_num($ret[0]['sum(tot_price)']));
		$data['yetc'] = number_format(null_num($ret[0]['sum(deal_num)']));
		//---昨日日成交量、成交额----end
		// dump($data);
		
		//---查询出发放的二级域名数据---begin
		$data['twos_data'] = '';	
		$two_typeid = 808001;
		$date = date("Y-m-d"); 
		$pan_domain_twos_date_log = spClass("pan_domain_twos_date_log");	
		$_today_twos = $pan_domain_twos_date_log->findSql("select sum(sys_tg_count) as tg_count from cmpai.pan_domain_twos_date_log where typeid='{$two_typeid}' and date='{$date}'");
		$_all_twos = $pan_domain_twos_date_log->findSql("select sum(sys_tg_count) as tg_count from cmpai.pan_domain_twos_date_log where typeid>=808001 and typeid<=808017");
		$today_twos = null_num($_today_twos[0]['tg_count']);
		$all_twos = null_num($_all_twos[0]['tg_count']);
		$data['twos_data'] .= '今日已发放四声母COM.CN(二级域名)：<span class="font-red">'.$today_twos.'</span>个，累计已发放四声母COM.CN(二级域名)：<span class="font-red">'.$all_twos.'</span>个，已兑换一级域名：<span class="font-red">0</span>个';

		//---查询出发放的二级域名数据---end
		
		
		
		$data['update_time'] = date("Y-m-d H:i:s");
		cache_a('pan_deal_trade_now',$data,10);//写入缓存10秒
		json_s($data);

    }	
	function actionindex() {
		//------最近成交记录直接输出-----begin 
		$deal_data = new_deal_trade(0,isset($typeid)?$typeid:0,50);
		// $deal_data = new_deal_trade_count($typeid?$typeid:0,50);	
		//------最近成交记录直接输出-----end	   
		$this->cm_nav ='deal_data';
	    $this->deal_data=$deal_data;
		$this->display("amui/index/my_deal_data.html");
    }		
	function api() {
		//取出最新成交数据
		$typeid = intval($this->spArgs('typeid'));
		$from = trim($this->spArgs('from'));
		// $ret = new_deal_trade_count($typeid?$typeid:0,50);
		$ret = new_deal_trade(0,$typeid?$typeid:0,50);
		
		if($from=='index'){
			$ret_ = array();
			foreach($ret as $v) {
				$v['deal_time'] = date('m-d H:i',strtotime($v['deal_time']));
				$ret_[] = $v;
			}
			$ret = $ret_;
			// var_dump($ret);
			
		}
		json_s(array('status'=>200,'domainlist'=>$ret));
    }
}	