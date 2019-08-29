<?php
/*
*配置文件
 */

//买家交易手续费
//$bRate = 0.00;
//卖家交易手续费
//$sRate = 0.01;

//提现手续费
//$withdraw = 0.002;

//基金率针对交易手续费而言
//买入基金率
//$bFund = 0.00;
//卖出基金率
//$sFund = 0.5;

//return array(
//    'bRate'=>'0.00',
//    'sRate'=>'0.05',
//    'withdraw'=>'0.002',
//    'bFund'=>'0.003',
//    'sFund'=>'0.00'
//);
bcscale(2); //设置保留两位小数********请勿删除********
define('bRate','0.000');
define('sRate','0.02');//卖出手续费
define('withdraw','0.01');
define('bFund','0.00');
define('sFund','0.00');
define('expire_limit',20);
define('suppose_price',700);
define('admin',88888);
define('pgsize',20);

?>