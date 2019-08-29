<?php
// error_reporting(E_ALL || ~E_NOTICE);
class bid extends spController
{
    function __construct(){
        parent::__construct();
    }	
    function create(){
		$post = $this->spArgs();
		// var_dump($post);
		if(!$post)exit;
		$lib_1618_bid_log = spClass("lib_1618_bid_log");
		$r = $lib_1618_bid_log->find(array('product_id'=>$post['product_id'],'pid'=>$post['pid']));
		if($r)exit('pid is in');
		if($lib_1618_bid_log->create($post)){
			exit('success');
		}
    }
	function dlist(){
		$id = intval($this->spArgs('id',1));
		if($this->spArgs('token')!='8888')exit;
		echo '<meta http-equiv="refresh" content="3">';
		echo 'product_id='.$id.'<br/>';
		$sql = "SELECT bid_user_phone,bid_user_account,count(bid_user_account) as num,bid_time from ykjhqcom.lib_1618_bid_log where product_id = {$id} group by bid_user_account order by num desc limit 0,500;";
		$lib_1618_bid_log = spClass("lib_1618_bid_log");
		$ret = $lib_1618_bid_log->findSql($sql);
		foreach($ret as $v) {
			// echo 1;
			$r = $lib_1618_bid_log->find(array('bid_user_account'=>$v['bid_user_account'],'product_id'=>$id),'bid_time desc');
			echo 'ID：'.$v['bid_user_account'].' , 尾号：'.$v['bid_user_phone'].' , 次数：'.$v['num'].' , 最后：'.$r['bid_time'].'<br/>';
		}
		$ret = $lib_1618_bid_log->findAll(array('product_id'=>$id),'pid desc','',50);
		echo '最新出价：<br/>';
		foreach($ret as $v) {
			$num = $lib_1618_bid_log->findCount(array('product_id'=>$id,'bid_user_account'=>$v['bid_user_account']));
			echo 'ID：'.$v['bid_user_account'].' , 尾号：'.$v['bid_user_phone'].' , 次数：'.$num.' , 最后：'.$v['bid_time'].' , pid：'.$v['pid'].'<br/>';
		}		
	}
}