<?php
error_reporting(E_ALL || ~E_NOTICE);
class update_data extends spController
{
    function __construct(){
        parent::__construct();
        spClass("trades");
		// echo md5(date('Y-m-d H',time()).'ChaoMi-Token'); //示例:每小时变一次
		check_token($this->spArgs('token'));//验证权限token
    }
	function type_list(){
		//列出品种typeid列表
		// json_s(get_type_list());
                json_s(array(808001));
		//json_s(array(808001,808002,808008,));
                //json_s(array(808001,200001));
	}
	function auto_buy(){	
		// exit;
		spClass('autoTrading');
		$typeid = intval($this->spArgs('typeid'));
		$zhibao = intval($this->spArgs('zhibao'));
		$find_sql_buy = " and 1=1";
		if($typeid==''){
			echo 'typeid==411104';
			$typeid = 411104;
		}	
		//按质保成交
		if($zhibao==0){
			$find_sql_buy .= " and (zhibao=0)"; 
		}		
		if($zhibao>=1){
			$find_sql_buy .= " and (zhibao<=$zhibao)";
		}			
		//--------操作缓存处理-----begin
		$cache_name = 'cm_auto_buy';
		if(false === cache_a($cache_name,time(),10))json_s(array('status'=>208,'msg'=>'操作占用中'));	
		//--------操作缓存处理-----end		
		$now=date("Y-m-d H:i:s"); //echo $now;
		//auto_ykj(0);//自动下架一口价到期域名(状态一口价中)
		// auto_ykj(1);//自动下架一口价到期域名(状态停放一口价中)
		auto_undersale();  //自动下架
		auto_renew();   //自动变更待续费
		$pan_trade = spClass('pan_trade');
		$types="select typeid from cmpai.pan_trade where status_1=0 and status_2=1 and typeid = $typeid and expire_time>'{$now}' $find_sql_buy";
		$types=$pan_trade->findSql($types);
		if(!$types){
			cache_a($cache_name,null); //删缓存
			exit('id:'.$typeid.'无任何买单!! '.$now);
		}	
		// echo 'id:'.$typeid.'-Go-'.$now.'-'.auto_buy($typeid);
		echo 'id:'.$typeid.'-zhibao:'.$zhibao.'-Go-'.$now.'-'.auto_buy($typeid,$zhibao);
		cache_a($cache_name,null); //删缓存
		exit($now.' Success');
	}
    function hq_date_deal(){ //产生或更新品种行情日成交相关参数值，中间表
		//1-2分钟更新一次
        $typeid = intval($this->spArgs('typeid')); //品种ID
        $dateid = intval($this->spArgs('dateid',0));//时间ID 0今日 1昨日 2前日
        $types = check_pz($typeid);
        $data['name'] = $types[0]['name']; //品种名称
        $data['typeid'] = $typeid; //品种ID
		if(!$types)json_s(array('status'=>201,'msg'=>'不存在该品种ID'));
		$pan_hq_date_deal = spClass('pan_hq_date_deal');
		$pan_deal_trade = spClass('pan_deal_trade');
		if($dateid==0){
			$date = date("Y-m-d"); //今日
			$date_sql = "deal_time>='$date'";
		}else{
			$date = date("Y-m-d",strtotime('-'.$dateid.' day'));
			$date_t = date("Y-m-d",strtotime('-'.($dateid-1).' day'));
			$date_sql = "deal_time>='$date' and deal_time<'$date_t'";			
		}
		$data['date'] = $date;
		//---取日成交量、成交额----begin
		$sql = "select sum(tot_price),sum(deal_num) from cmpai.pan_deal_trade where sta=0 and typeid=$typeid and $date_sql";
		$ret = $pan_deal_trade->findSql($sql);
		$data['price'] = null_num($ret[0]['sum(tot_price)']);//日总成交额
		$data['count'] = null_num($ret[0]['sum(deal_num)']);//日总成交量
		//---取日成交量、成交额----end
		//---取日最高、最低价---begin
		$sql_high = "select deal_price,deal_time from cmpai.pan_deal_trade where sta=0 and typeid=$typeid and $date_sql order by deal_price desc limit 1"; //最高价
		$ret_high = $pan_deal_trade->findSql($sql_high);
		$sql_low = "select deal_price,deal_time from cmpai.pan_deal_trade where sta=0 and typeid=$typeid and $date_sql order by deal_price asc limit 1"; //最低价
		$ret_low = $pan_deal_trade->findSql($sql_low);
		$data['high_price'] = null_num($ret_high[0]['deal_price']);//最高价
		$data['low_price'] = null_num($ret_low[0]['deal_price']);//最低价
		//---取日最高、最低价---end
		//---取日开盘、收盘价---begin
		$sql = "select deal_price,deal_time from cmpai.pan_deal_trade where sta=0 and typeid=$typeid and $date_sql order by deal_time desc limit 1"; //日成交最新价格
		$ret = $pan_deal_trade->findSql($sql);
		$data['open_price'] = null_num($ret[0]['deal_price']); //开盘价 ---要判断是表无数据时，才新建
		$data['close_price'] = null_num($ret[0]['deal_price']); //收盘价 ---每次更新
		//---取日开盘、收盘价---end
		$ret = array();
		$find = array('typeid'=>$typeid,'date'=>$date);
		$ret = $pan_hq_date_deal->find($find); 
		if(!$ret){//判断是否已经存在当日数据记录
			$pan_hq_date_deal->create($data); //不存在即创建
			$data['act'] = 'create';
		}else{
			if($ret['open_price']>0)unset($data['open_price']); //如果已经存在开盘价就不更新值
			$pan_hq_date_deal->update($find,$data); //存在即更新
			$data['act'] = 'update';
		}
		json_s($data);
    }
	function hq_deal(){//产生或更新品种行情最新价、总成交、历史最高 品种页顶部行情参数用到
		//1-2分钟更新一次
        $typeid = intval($this->spArgs('typeid')); //品种ID
        $types = check_pz($typeid);
        $data['name'] = $types[0]['name']; //品种名称
        $data['typeid'] = $typeid; //品种ID
		if(!$types)json_s(array('status'=>201,'msg'=>'不存在该品种ID'));		
		$pan_hq_date_deal = spClass('pan_hq_date_deal');
		$pan_deal_trade = spClass('pan_deal_trade');
		get_cmjy_trans_api();//*****更新中转交易的成交量、成交额到炒米网主站使用
		//---最新价---begin
		$sql = "select deal_price,deal_time from cmpai.pan_deal_trade where sta=0 and typeid=$typeid order by deal_time desc limit 1"; //成交最新价格
		$ret = $pan_deal_trade->findSql($sql);
		$data['new_price'] = null_num($ret[0]['deal_price']);
		//---最新价---end
		//---历史最高价---begin
		$sql = "select * from cmpai.pan_hq_date_deal where typeid=$typeid order by high_price desc limit 1";
		$ret = $pan_hq_date_deal->findSql($sql);
		$data['c_high_price'] = null_num($ret[0]['high_price']);
		//---历史最高价---end
		//---历史成交---begin
		$sql = "select sum(price),sum(count) from cmpai.pan_hq_date_deal where typeid=$typeid";
		$ret = $pan_hq_date_deal->findSql($sql);
		$data['c_price'] = null_num($ret[0]['sum(price)']);//历史成交额
		$data['c_count'] = null_num($ret[0]['sum(count)']);//历史成交量
		//---历史成交---end	
		$ret = array();
		$find = array('typeid'=>$typeid,'date'=>date("Y-m-d"));
		$ret = $pan_hq_date_deal->find($find);
		//---计算涨跌幅 最新价跟开盘价对比---begin
		$data['zdf'] = 0;
		if($ret['open_price']>0 && $data['new_price']>0){
			$data['zdf'] = null_num(round(($data['new_price'] - $ret['open_price']) / $ret['open_price'] * 100,2));		
		}
		//---计算涨跌幅 最新价跟开盘价对比---end
		unset($ret['name']);
		unset($ret['typeid']);
		unset($ret['id']);
		$new_data = array_merge($data,$ret); //合并数组
		if($new_data)cache_s('cm_typeid_'.$typeid.'_hq',$new_data,86400);//品种行情全部参数值写对应缓存
		json_s($new_data);
	}
	function hq_new_deal(){//产生或更新品种行情最新价，曲线图用到
		//2-10分钟更新一次
        $typeid = intval($this->spArgs('typeid')); //品种ID
        $types = check_pz($typeid);
        $data['name'] = $types[0]['name']; //品种名称
        $data['typeid'] = $typeid; //品种ID
		if(!$types)json_s(array('status'=>201,'msg'=>'不存在该品种ID'));		
		$pan_hq_new_deal = spClass('pan_hq_new_deal');
		$pan_deal_trade = spClass('pan_deal_trade');
		$ret = $pan_hq_new_deal->find(array('typeid'=>$typeid),'id desc');
		if(time() - strtotime($ret['time'])<10)json_s(array('status'=>201,'msg'=>'与上一条记录相差少于10秒'));
		//---最新价---begin
		$sql = "select deal_price,deal_time from cmpai.pan_deal_trade where sta=0 and typeid=$typeid order by deal_time desc limit 1"; //成交最新价格
		$rets = $pan_deal_trade->findSql($sql);
		$data['price'] = null_num($rets[0]['deal_price']);
		//---最新价---end
		if($data['price']==$ret['price'] && time() - strtotime($ret['time'])<3600)json_s(array('status'=>201,'msg'=>'与上一条记录价格相等并且少于3600秒'));
		$data['time'] = date("Y-m-d H:i:s", time());
		if($data['price']>0)$pan_hq_new_deal->create($data);
		json_s($data);
	}
	function new_price(){//产生或更新品种行情最佳买卖价格
		//2-10分钟更新一次
        $typeid = intval($this->spArgs('typeid')); //品种ID
        $types = check_pz($typeid);
        $data['name'] = $types[0]['name']; //品种名称
        $data['typeid'] = $typeid; //品种ID
		if(!$types)json_s(array('status'=>201,'msg'=>'不存在该品种ID'));		
		$arr = array(
			'411104'=>array('cid'=>1,'hid'=>3),
			'411107'=>array('cid'=>1,'hid'=>7),
			'411109'=>array('cid'=>1,'hid'=>9),
			'411113'=>array('cid'=>1,'hid'=>13),
			'411101'=>array('cid'=>1,'hid'=>1),
			'411102'=>array('cid'=>1,'hid'=>2),
			'411103'=>array('cid'=>1,'hid'=>4),
			'411106'=>array('cid'=>1,'hid'=>6),
			'412102'=>array('cid'=>11,'hid'=>2),
			'614101'=>array('cid'=>13,'hid'=>1)
			);
		$hq_arr = $arr[$typeid];
		if(!$hq_arr)json_s(array('status'=>201,'msg'=>'不存在该品种数组'));
		$pz_cid = $hq_arr['cid'];
		$pz_hid = $hq_arr['hid'];
		$new_price = spClass('new_price');
		//---取最高价---begin
		$sql = "select highest_price,lowest_price,times_str from ykjhqcom.lib_hq_kxian where cid=$pz_cid and hid=$pz_hid order by id desc limit 1"; //最新价格
		$ret = $new_price->findSql($sql);
		// $data['price'] = null_num($ret[0]['highest_price']);
		$highest_price = null_num($ret[0]['highest_price']);
		$lowest_price = null_num($ret[0]['lowest_price']);
		$data['price'] = ($highest_price + $lowest_price) / 2;
		$data['date'] = null_num($ret[0]['times_str']);
		//---取最高价---end
		$data['time'] = date("Y-m-d H:i:s", time());
		if($new_price->create($data))json_s($data);
	}
	function new_hq(){//产生或更新首页行情表
		//1-3分钟更新一次
        $typeid = intval($this->spArgs('typeid')); //品种ID
        $types = check_pz($typeid);
        $data['name'] = $types[0]['name']; //品种名称
        $data['typeid'] = $typeid; //品种ID
		if(!$types)json_s(array('status'=>201,'msg'=>'不存在该品种ID'));
		//--------操作缓存处理-----begin
		$cache_name = 'new_hq_typeid_'.$typeid;
		if(false === cache_a($cache_name,time(),10))json_s(array('status'=>208,'msg'=>'操作占用中'));	
		//--------操作缓存处理-----end			
		$sp = spClass('new_hq_list');
		$r = $sp->find(array('typeid'=>$typeid));
		$data = $this->spArgs();
		if($r){
			//存在即更新
			$sp->update(array('typeid'=>$typeid),$data);
			json_s(array('status'=>200,'msg'=>'Update Success'));
			cache_a($cache_name,null);
		}else{
			$sp->create($data);
			json_s(array('status'=>200,'msg'=>'Create Success'));
			cache_a($cache_name,null);
		}
	}	
}
function get_cmjy_trans_api(){
	//----用缓存中转交易的成交量、成交额到炒米网主站使用----begin
	$date = date("Y-m-d"); 
	$_date = date("Ymd"); 
	$cache_key = 'api_cmjy_trans_data_'.$_date;
	$data['status'] = 200;
	$data['date'] = $date;
	$pan_deal_trade = spClass('pan_deal_trade');
	$date_sql = "deal_time>='$date'";
	
	$sql = "select sum(tot_price),sum(deal_num) from cmpai.pan_deal_trade where sta=0 and $date_sql";
	$ret = $pan_deal_trade->findSql($sql);
	$data['price'] = null_num($ret[0]['sum(tot_price)']);
	$data['count'] = null_num($ret[0]['sum(deal_num)']);
	var_dump($data);
	cache_s($cache_key,$data,86400);
	//----用缓存中转交易的成交量、成交额到炒米网主站使用----end			
}