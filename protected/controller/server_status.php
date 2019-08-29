<?php
// error_reporting(E_ALL || ~E_NOTICE);
class server_status extends spController
{
    function __construct(){
        parent::__construct();
		// echo md5(date('Y-m-d H',time()).'ChaoMi-Token'); //示例:每小时变一次
		
    }
	function post(){ // 服务器上报状态
		check_token($this->spArgs('token'));//验证权限token
        $post =$this->spArgs();
		if(!$post['server_name'])json_s(array('status'=>201,'msg'=>'ServerName Not Null'));		
		$sp = spClass('lib_server_status');
		$r = $sp->find(array('server_name'=>$post['server_name']));
		$post['uptime'] = time();
		if($r){
			//存在即更新
			$sp->update(array('server_name'=>$post['server_name']),$post);
			json_s(array('status'=>200,'msg'=>'Server Status Update Success'));
		}else{
			$sp->create($post);
			json_s(array('status'=>200,'msg'=>'Server Status Create Success'));
		}
	}	
	function index(){
		$sp = spClass('lib_server_status');
		$time = time() - 600; //少于10分钟
		$ret = $sp->findAll("uptime<{$time}");
		$date = date("Ymd");
		$cache_name = "service_status_".$date;
		if($ret){
			$server_str = "";
			foreach ($ret as $k=>$v) {
				$server_str .= $v['server_name']." - ".$v['update_time']." <br/>";
			}
			$cache_data = cache_s($cache_name);
			if(time() - $cache_data['time'] < 600){
				exit('Time<600<br/>'.$server_str);
			}
			$count = $cache_data['count'];
			if($count > 30){
				exit('Count>30<br/>'.$server_str);
			}			
			$content = array();
			$content['to'] = array('pwpet@qq.com');
			$content['sub'] = array('%content%'=>array($server_str));
			$new_content = json_encode($content);
			send_mail('pwpet@qq.com','【服务器状态】'.count($ret).'台，超过10分钟未返回状态数据!',$new_content,8);
			$content = array();
			$content['to'] = array('394019599@qq.com');
			$content['sub'] = array('%content%'=>array($server_str));
			$new_content = json_encode($content);			
			send_mail('394019599@qq.com','【服务器状态】'.count($ret).'台，超过10分钟未返回状态数据!',$new_content,8);
			cache_s($cache_name,array('time'=>time(),'count'=>$count+1),7200);
			exit($server_str);
		}
		exit('Server Status OK');
	}
}