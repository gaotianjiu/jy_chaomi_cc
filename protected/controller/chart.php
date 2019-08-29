<?php
class chart extends spController
{
    function __construct(){
        parent::__construct();
        spClass("trades");
    }
	function miniApi(){
		$pan_hq_new_deal = spClass('pan_hq_new_deal');
		$type_list = get_type_list();
		$type_list = array(200001,808001,808002,808008);
		$ret = array();
		$date_3 = date("Y-m-d",strtotime('-3 day'));
		foreach ($type_list as $id) {	
			$k = $pan_hq_new_deal->findAll("typeid='{$id}' and time>='{$date_3}'",'time desc','','300');
			sort($k);
			$i = 0;
			$line = array();
			$sep = 0;
			if(count($k)>50){
				$sep = 3;
			}
			if(count($k)>100){
				$sep = 8;
			}			
			foreach ($k as $n) {
				$i++;
				if($i==1 || $i==count($k) || $i%$sep==0)$line[] = array_values(array('i'=>$i,'p'=>$n['price']));				
			}
			if(!$line || count($k)<3){
				$line = array();
				$line[] = array_values(array('i'=>1,'p'=>1));				
				$line[] = array_values(array('i'=>2,'p'=>1));				
				$line[] = array_values(array('i'=>3,'p'=>1));
				foreach ($k as $n) {
					$i++;
					$line[] = array_values(array('i'=>$i+3,'p'=>$n['price']));				
				}
			}			
			$ret[] = array('typeid'=>$id,'line'=>$line);
		}	
		json_s(array('status'=>200,'data'=>$ret));
	}
	function i(){ //曲线图专用
        $typeid = intval($this->spArgs('typeid')); //品种ID
        $gid = intval($this->spArgs('gid',0)); //时间段ID
        $types = check_pz($typeid);
        $data['name'] = $types[0]['name']; //品种名称
        $data['typeid'] = $typeid; //品种ID
		if(!$types)json_s(array('status'=>201,'msg'=>'不存在该品种ID'));		
		$pan_hq_new_deal = spClass('pan_hq_new_deal');
		if($gid==0){
			//近5日
			$date = date("Y-m-d",strtotime('-6 day'));
			$find = "time>='$date'";				
		}
		if($gid==3){
			//近2日
			$date = date("Y-m-d",strtotime('-3 day'));
			$find = "time>='$date'";				
		}		
		if($gid==1){
			//近10日
			$date = date("Y-m-d",strtotime('-11 day'));
			$find = "time>='$date'";				
		}		
		if($gid==2){
			//近15日
			$date = date("Y-m-d",strtotime('-16 day'));
			$find = "time>='$date'";				
		}	
		$find .= "and typeid=$typeid";
		$ret = $pan_hq_new_deal->findAll($find,'time asc');
		$Cret = array();
		foreach ($ret as $r) {	
			$Cret[] = array($r['time'],floatval($r['price']));	
		}	
		json_s(array('status'=>200,'name'=>$data['name'],'data'=>$Cret));
	}
	function k(){
        $typeid = intval($this->spArgs('typeid')); //品种ID
        $gid = intval($this->spArgs('gid',0)); //时间段ID
        $types = check_pz($typeid);
        $data['name'] = $types[0]['name']; //品种名称
        $data['typeid'] = $typeid; //品种ID
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
		// $Cres = $get->spCache(60)->findSql("SELECT times_str,open_price,close_price,lowest_price,highest_price,trans_price,trans_count FROM ykjhqcom.lib_hq_kxian WHERE cid=$cid and hid=$hid ORDER BY times_unix desc LIMIT $date;");			
		$Cres = $get->spCache(60)->findSql("SELECT date,open_price,close_price,low_price,high_price,price,count FROM cmpai.pan_hq_date_deal WHERE typeid={$typeid} ORDER BY date desc LIMIT $date;");			
		sort($Cres);
		$ret_ = array();
		foreach($Cres as $v) {
			$t['times_str'] = $v['date'];
			$t['open_price'] = $v['open_price'];
			$t['close_price'] = $v['close_price'];
			$t['lowest_price'] = $v['low_price'];
			$t['highest_price'] = $v['high_price'];
			$t['trans_price'] = $v['price'];
			$t['trans_count'] = $v['count'];
			unset($v['low_price']);
			unset($v['hig_price']);
			unset($v['price']);
			unset($v['count']);
			unset($v['date']);
			$ret_[] = $t;
		}
		$Cres = array();
		$Cres = $ret_;	
		$ret = array();
		foreach ($Cres as $r) {
			$r['open_price'] = floatval($r['open_price']);
			$r['close_price'] = floatval($r['close_price']);
			$r['lowest_price'] = floatval($r['lowest_price']);
			$r['highest_price'] = floatval($r['highest_price']);
			// if($hid==9 || $hid==10){
				// if($r['highest_price']/1.5>$r['lowest_price'])$r['highest_price'] = $r['lowest_price'];
				// if($r['close_price']/1.5>$r['lowest_price'])$r['close_price'] = $r['lowest_price'];
				// if($r['close_price']>$r['highest_price'])$r['close_price'] = $r['highest_price'];
				// if($r['open_price']>$r['highest_price'])$r['open_price'] = $r['highest_price'];						
				// if($r['lowest_price']>$r['highest_price'])$r['lowest_price'] = $r['highest_price'];						
			// }else{
				// if($r['highest_price']/2.5>$r['lowest_price'])$r['highest_price'] = $r['lowest_price'];
				// if($r['close_price']/2.5>$r['lowest_price'])$r['close_price'] = $r['lowest_price'];						
				// if($r['close_price']>$r['highest_price'])$r['close_price'] = $r['highest_price'];
				// if($r['open_price']>$r['highest_price'])$r['open_price'] = $r['highest_price'];
				// if($r['lowest_price']>$r['highest_price'])$r['lowest_price'] = $r['highest_price'];						
			// }
			$r['trans_price'] = floatval($r['trans_price']);
			$r['trans_count'] = floatval($r['trans_count']);
			$zdf = round(($r['close_price'] - $ret[$i][2]) / $ret[$i][2]*100,2);
			if(empty($zdf))$zdf=0;
			$r['zdf'] = $zdf;
			if($r['close_price']>0 && $r['open_price']>0 && $r['lowest_price']>0){
				$ret[] = array_values($r);
				$i++;
			}
			//(今日收盘 - 昨天收盘) / 昨天收盘
			
		}
		json_s(array('status'=>200,'name'=>$data['name'],'data'=>$ret));
	
	}	
}