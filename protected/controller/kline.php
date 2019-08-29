<?php
class kline extends spController
{
    function __construct(){
        parent::__construct();
        spClass("trades");
    }
	function k_v2017(){
        $typeid = intval($this->spArgs('typeid')); //品种ID
        $gid = intval($this->spArgs('gid',0)); //时间段ID
        $types = check_pz($typeid);
        $datas['name'] = $types[0]['name']; //品种名称
        $datas['typeid'] = $typeid; //品种ID
		if(!$types)json_s(array('status'=>201,'msg'=>'不存在该品种ID'));
		if($typeid==411104){
			$cid = 1;
			$hid = 3;
		}
		if($typeid==411107){
			$cid = 1;
			$hid = 7;
		}		
		if($typeid==411109){
			$cid = 1;
			$hid = 9;
		}	
		if($typeid==411113){
			$cid = 1;
			$hid = 13;
		}		
		if($typeid==411101){
			$cid = 1;
			$hid = 1;
		}	
		if($typeid==411102){
			$cid = 1;
			$hid = 2;
		}	
		if($typeid==411103){
			$cid = 1;
			$hid = 4;
		}		
		if($typeid==411106){
			$cid = 1;
			$hid = 6;
		}	
		if($typeid==412102){
			$cid = 11;
			$hid = 2;
		}	
		if($typeid==614101){
			$cid = 13;
			$hid = 1;
		}		
		//---临时调用炒米网主日K线
		$get = spClass('lib_hq_kxian');//引入对应表
		$date = 450;
		$i = -1;
		$Cres = $get->spCache(60)->findSql("SELECT times_str,open_price,close_price,lowest_price,highest_price,trans_price,trans_count FROM ykjhqcom.lib_hq_kxian WHERE cid=$cid and hid=$hid ORDER BY times_unix desc LIMIT $date;");			
		// $Cres = $get->spCache(60)->findSql("SELECT date,open_price,close_price,low_price,high_price,price,count FROM cmpai.pan_hq_date_deal WHERE typeid={$typeid} ORDER BY date desc LIMIT $date;");			
		sort($Cres);
		// $ret_ = array();
		// foreach($Cres as $v) {
			// $t['times_str'] = strtotime($v['date']);
			// $t['open_price'] = $v['open_price'];
			// $t['highest_price'] = $v['high_price'];
			// $t['lowest_price'] = $v['low_price'];
			// $t['close_price'] = $v['close_price'];
			// $t['trans_count'] = $v['count'];
			// unset($v['low_price']);
			// unset($v['low_price']);
			// unset($v['hig_price']);
			// unset($v['price']);
			// unset($v['count']);
			// unset($v['date']);
			// $ret_[] = $t;
		// }
		// $Cres = array();
		// $Cres = $ret_;	
		// $ret = array();
		$k = array();
		foreach ($Cres as $r) {
			$k['times'] = strtotime($r['times_str']).'000';
			$k['times'] = (int)$k['times'];
			if($hid==9 || $hid==10){
				if($r['highest_price']/1.5>$r['lowest_price'])$r['highest_price'] = $r['lowest_price'];
				if($r['close_price']/1.5>$r['lowest_price'])$r['close_price'] = $r['lowest_price'];
				if($r['close_price']>$r['highest_price'])$r['close_price'] = $r['highest_price'];
				if($r['open_price']>$r['highest_price'])$r['open_price'] = $r['highest_price'];						
				if($r['lowest_price']>$r['highest_price'])$r['lowest_price'] = $r['highest_price'];						
			}else{
				if($r['highest_price']/2.5>$r['lowest_price'])$r['highest_price'] = $r['lowest_price'];
				if($r['close_price']/2.5>$r['lowest_price'])$r['close_price'] = $r['lowest_price'];						
				if($r['close_price']>$r['highest_price'])$r['close_price'] = $r['highest_price'];
				if($r['open_price']>$r['highest_price'])$r['open_price'] = $r['highest_price'];
				if($r['lowest_price']>$r['highest_price'])$r['lowest_price'] = $r['highest_price'];						
			}			
			$k['open_price'] = floatval($r['open_price']);
			$k['highest_price'] = floatval($r['highest_price']);
			$k['lowest_price'] = floatval($r['lowest_price']);
			$k['close_price'] = floatval($r['close_price']);
			$k['trans_count'] = floatval($r['trans_count']);
			unset($r['trans_price']);
			unset($r['times_str']);
			if($r['close_price']>0 && $r['open_price']>0 && $r['lowest_price']>0){
				$ret[] = array_values($k);
				// $ret[] = $k;
				$i++;
			}
		}
		$data = array('lines'=>$ret,'trades'=>array(),'depths'=>array('asks'=>array(),'bids'=>array()));
		json_s(array('success'=>true,'name'=>$datas['name'],'data'=>$data));
	
	}
}