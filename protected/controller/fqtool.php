<?php
// error_reporting(E_ALL || ~E_NOTICE);
class fqtool extends spController
{
    function __construct(){
        parent::__construct();
		$sso_user = check();
		$this->uid = $sso_user['uid'];
		$this->mid = $sso_user['mid'];			
		
    }
	function index(){
		$error_msg = "";
		$from = trim($this->spArgs('from'));
		$name = trim($this->spArgs('name'));
		$price = intval($this->spArgs('price')); //单价
		$num = intval($this->spArgs('num')); //个数
		$month = trim($this->spArgs('month')); //分期月数
		$time = trim($this->spArgs('time')); //购买日期
		$total = $price * $num;
		$total = bcadd($total,0,2);
		$month_list = array('1'=>'1个月','3'=>'3个月','6'=>'6个月','9'=>'9个月','12'=>'12个月');
		if(!checkDateIsValid($time)){
			$time = date('Y-m-d');
		}
		if($from!='y'){
			if($price==0)$price=330;
			if($num==0)$num=1;
			if($month==0)$month=12;
		}
		if(!$month_list[$month])$error_msg = "还款期限错误，1-12个月间";
		if($price<1 || $price>100000)$error_msg = "购买单价错误，不能小于1元或大于100000元";
		if($num<1 || $num>10000)$error_msg = "购买个数错误，不能小于1个或大于10000个";
		if($error_msg=="" && $from=='y'){
			$sftotal = bcmul($total,0.3); //首付
			$sftotal = bcadd($sftotal,0,2);
			$fqtotal = bcsub($total,$sftotal); //分期金额
			$ret = debx_api($month,$fqtotal,$time);
		}
        $this->sftotal = $sftotal;
        $this->fqtotal = $fqtotal;
        $this->total = $total;
        $this->error_msg = $error_msg;
        $this->month_list = $month_list;
        $this->price = $price;
        $this->num = $num;
        $this->month = $month;
        $this->time = $time;
        $this->ret = $ret;
        $this->cm_nav ='jy';
        $this->display("amui/index/fqtool_index.html");	
	}	
}
function debx_api($dkm,$dkTotal,$time){ //等额本息
	// $dkm     = 60; //贷款月数，20年就是240个月
	// $dkTotal = 10000; //贷款总额
	$dknl    = 0.18;  //贷款年利率
	$emTotal = $dkTotal * $dknl / 12 * pow(1 + $dknl / 12, $dkm) / (pow(1 + $dknl / 12, $dkm) - 1); //每月还款金额
	$emTotal = bcadd($emTotal,0,2);
	$lxTotal = 0; //总利息
	$next_time = $time;
	if(checkDateIsValid($next_time)===false)return false; //日期不正确
	//--判断今日是否是月末最后一天？
	$day = date('d', strtotime($next_time));
	$time_arr = array();
	$fq_list_arr = array();
	for ($i = 0; $i < $dkm; $i++) {
		$lx      = $dkTotal * $dknl / 12;   //每月还款利息
		$lx = bcadd($lx,0,2);
		$em      = $emTotal - $lx;  //每月还款本金
		$em = bcadd($em,0,2);
		array_push($time_arr,$next_time);
		$next_time = fq_time(strtotime($time_arr[$i]),$day);
		if(checkDateIsValid($next_time)===false)return false; //日期不正确
		// echo $next_time;
		// echo "第" . ($i + 1) . "期", " 本金:", $em, " 利息:" . $lx, " 总额:" . $emTotal, "<br />";
		$dkTotal = $dkTotal - $em;
		$lxTotal = $lxTotal + $lx;
		$fq_list_arr[] = array('i'=>$i+1,'date'=>$next_time,'em'=>$em,'lx'=>$lx,'emtotal'=>$emTotal);
	}
	// echo "总利息:" . $lxTotal
	$lxTotal = bcadd($lxTotal,0,2);
	return array('lxtotal'=>$lxTotal,'list'=>$fq_list_arr);
}
function fq_time($time,$day){
	//--输出日期，并判断如果 日 大于或等于 当前月最后一日，即改变 日 为当前月最后一日
	$f_month = fq_month($time);
	$f_day = fq_day($time);
	$next_day = fq_l_day($f_month.'-01');
	$a_day = $f_day;
	if($day>$f_day)$a_day = $day;
	if($day>=$next_day)$a_day = $next_day; 
	return $f_month.'-'.$a_day;
}
function fq_month($time){ //获取下一个月 (年-月)Y-m
	$arr = getdate($time);//判断月份
	$year = $arr['year'];
	$month = $arr['mon']+1;
	if($month>12){
		$year = $year+1;
		$month = 01;
	}
	$day = $arr['mday'];
	if($day>28){
		if($month==2){
			if(($year%4 == 0 && $year%100 != 0) || ($year%400 == 0 )){
				if($day>=29){
					$rq=mktime(date("G", $time), date("i", $time),date("s", $time),$month, 01,$year);
					$rq=date("Y-m", $rq);
				}
			}else{
				if($day>=28){
					$rq=mktime(date("G", $time), date("i", $time),date("s", $time),$month, 01,$year);
					$rq=date("Y-m", $rq);
				}
			  }
		}elseif($month==1||$month==3||$month==5||$month==7||$month==8||$month==10||$month==12){
			if($day>=31){
				$rq=mktime(date("G", $time), date("i", $time),date("s", $time),$month, 01,$year);
				$rq=date("Y-m", $rq);
			}
		}else{
			if($day>=30){
				$rq=mktime(date("G", $time), date("i", $time),date("s", $time),$month, 01,$year);
				$rq=date("Y-m", $rq);
			}
		}
	}
	if(!isset($rq)){
		$rq=mktime(date("G", $time), date("i", $time),date("s", $time),$month, $day,$year);
		$rq=date("Y-m", $rq);
	}
	return $rq;
}
function fq_day($time){ //获取下一个月 (日) d
	$arr = getdate($time);//判断月份
	$year = $arr['year'];
	$month = $arr['mon']+1;
	if($month>12){
		$year = $year+1;
		$month = 01;
	}
	$day = $arr['mday'];
	if($day>28){
		if($month==2){
			if(($year%4 == 0 && $year%100 != 0) || ($year%400 == 0 )){
				if($day>=29){
					$rq=mktime(date("G", $time), date("i", $time),date("s", $time),$month, 01,$year);
					$rq=date("t", $rq);
				}
			}else{
				if($day>=28){
					$rq=mktime(date("G", $time), date("i", $time),date("s", $time),$month, 01,$year);
					$rq=date("t", $rq);
				}
			  }
		}elseif($month==1||$month==3||$month==5||$month==7||$month==8||$month==10||$month==12){
			if($day>=31){
				$rq=mktime(date("G", $time), date("i", $time),date("s", $time),$month, 01,$year);
				$rq=date("t", $rq);
			}
		}else{
			if($day>=30){
				$rq=mktime(date("G", $time), date("i", $time),date("s", $time),$month, 01,$year);
				$rq=date("t", $rq);
			}
		}
	}
	if(!isset($rq)){
		$rq=mktime(date("G", $time), date("i", $time),date("s", $time),$month, $day,$year);
		$rq=date("d", $rq);
	}
	return $rq;
}
function fq_l_day($time){ //--输出当前月最后一天
	$date = date('Y-m-01', strtotime($time));
	return date('d', strtotime("$date +1 month -1 day"));	
}
function checkDateIsValid($time) { //校验日期格式是否正确
    if(date('Y-m-d',strtotime($time))==$time){
        return true;
    }else{
		return false;
	}
}