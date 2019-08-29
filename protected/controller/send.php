<?php
error_reporting(E_ALL || ~E_NOTICE);
class send extends spController
{
    function __construct(){
        parent::__construct();
		// echo md5(date('Y-m-d H',time()).'ChaoMi-Token'); //示例:每小时变一次
		check_token($this->spArgs('token'));//验证权限token
    }
	function mail(){	
		$act = trim($this->spArgs('act'));
		$get = spClass('lib_member_smail');		
		if('list' == $act){
			//---取出列表---
			$cache_name = 'cm_send_mail';
			// --------操作缓存处理-----begin
			if(false === cache_a($cache_name,time(),10))json_s(array('status'=>205,'msg'=>'Cache 操作占用中'));			
			// --------操作缓存处理-----end
			$ret = $get->findAll(array('status'=>0),"id asc","id,email,title,content,type","5");
			foreach ($ret as $r) {
				$get->update(array('id'=>$r['id']),array('status'=>1,'btime'=>time())); //更新
			}
			cache_a($cache_name,null);
			json_s(array('status'=>200,'count'=>count($ret),'data'=>$ret));
		}
		if('update' == $act){
			//---更新单条数据---程序返回数据后
			$id = intval($this->spArgs('id'));
			$result = trim($this->spArgs('result'));
			if(!$id)json_s(array('stauts'=>201,'msg'=>'Id is Null'));
			$cache_name = 'cm_send_mail_id_'.$id;
			//--------操作缓存处理-----begin
			if(false === cache_a($cache_name,time(),10))json_s(array('status'=>205,'msg'=>'Cache 操作占用中'));			
			//--------操作缓存处理-----end	
			$ret = $get->update(array('id'=>$id),array('status'=>2,'count'=>1,'result'=>$result)); //更新
			cache_a($cache_name,null);
			if($ret){
				json_s(array('stauts'=>200,'msg'=>'update success'));
			}else{
				json_s(array('stauts'=>201,'msg'=>'update error!!!'));
			}
		}
		json_s(array('stauts'=>201,'msg'=>'null'));
	}
	function sms(){	
		$act = trim($this->spArgs('act'));
		$get = spClass('lib_member_mobile');		
		if('list' == $act){
			//---取出列表---
			$cache_name = 'cm_send_sms';
			// --------操作缓存处理-----begin
			if(false === cache_a($cache_name,time(),10))json_s(array('status'=>205,'msg'=>'Cache 操作占用中'));			
			// --------操作缓存处理-----end
			$ret = $get->findAll(array('status'=>0),"id asc","id,mobile,title","5");
			foreach ($ret as $r) {
				$get->update(array('id'=>$r['id']),array('status'=>1,'btime'=>time())); //更新
			}
			cache_a($cache_name,null);
			json_s(array('status'=>200,'count'=>count($ret),'data'=>$ret));
		}
		if('update' == $act){
			//---更新单条数据---程序返回数据后
			$id = intval($this->spArgs('id'));
			$result = trim($this->spArgs('result'));
			if(!$id)json_s(array('stauts'=>201,'msg'=>'Id is Null'));
			$cache_name = 'cm_send_sms_id_'.$id;
			//--------操作缓存处理-----begin
			if(false === cache_a($cache_name,time(),10))json_s(array('status'=>205,'msg'=>'Cache 操作占用中'));			
			//--------操作缓存处理-----end	
			$ret = $get->update(array('id'=>$id),array('status'=>2,'count'=>1,'result'=>$result)); //更新
			cache_a($cache_name,null);
			if($ret){
				json_s(array('stauts'=>200,'msg'=>'update success'));
			}else{
				json_s(array('stauts'=>201,'msg'=>'update error!!!'));
			}
		}
		json_s(array('stauts'=>201,'msg'=>'null'));
	}	
}