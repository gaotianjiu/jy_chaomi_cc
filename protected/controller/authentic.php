<?php

/*
 * 用户认证模块  authentic
 * 实名认证  name_AUT
 * 邮箱认证  email_AUT
 * 银行卡认证 yhk_AUT
 */
class authentic extends spController {

    function __construct() { // 公用
        parent::__construct(); // 这是必须的
		$sso_user = check();
        if ($sso_user == true) {
            $this->uid = $sso_user['uid'];
            $this->mid = $sso_user['mid'];
        } else {
            re_login();
            exit();
        }
    }
    //实名认证
    function name_AUT(){
        $uid = $this->uid;
        $sql = "select status,time,audit_time from cmpai.pan_member_card where uid= '" . $uid . "' order by time desc limit 1";
		$pan_member_card = spClass('pan_member_card');
        $res = $pan_member_card->findSql($sql);
        $this->statu = $res[0]['status'];
        $time = $res[0]['time']; //提交时间
		$time = strtotime($time);
		$this->times = date('Y-m-d H:i:s',$time);
        $time = $res[0]['audit_time'];//审核时间
		$time = strtotime($time);
		$this->audit_time = date('Y-m-d H:i:s',$time);		
		$ip = trim(get_client_ip()); //取得客户端ip 
		$act = trim($this->spArgs("act"));
		if($act=='post' && ($res[0]['status']=='' || $res[0]['status']==-1)){ 		
			//查询是否已经实名认证
			//--是  显示已经实名--\\
			//--否   实名处理--\\
			//处理上传的身份信息
			$firstName = $this->spArgs('firstName');
			$lastName = $this->spArgs('lastName');
			$idCard = $this->spArgs('idCard');
			import('UploadFile.php');
			if (!empty($idCard)) {
				$condition = array(
					'uid' => $uid,
					'first_name' => trim($firstName),
					'last_name' => trim($lastName),
					'card' => trim($idCard),
					'time' => date("Y-m-d H:i:s"),
					'req_ip'=>$ip,
					'status' => 1
				);
				//------------限制相同IP并发请求----------begin
				$ip_key = md5($ip);
				$key_name = 'name_AUT_ip_'.$ip_key;
				if(false === cache_a($key_name,time(),3))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后3秒后重试!'));	
				//------------限制相同IP并发请求----------end
				
				//------------限制相同帐号并发请求----------begin
				$key_name = 'name_AUT_uid_'.$uid;
				if(false === cache_a($key_name,time(),3))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后3秒后重试!!'));	
				//------------限制相同帐号并发请求----------end	
				
				//------------限制相同帐号请求次数----------begin
				$key_name = 'name_AUT_uid_c_'.$uid;
				if(cache_s($key_name)>10)json_s(array('status'=>205,'msg'=>'很抱歉，请求次数限制，请稍后1小时后再操作'));				
				cache_s($key_name,intval(cache_s($key_name))+1,3600);
				//------------限制相同帐号请求次数----------end	
				
				//处理正面身份图片
				if (!empty($_FILES['fileToUpload']) && !empty($_FILES['fileToUpload2'])) {
						$upload = new UploadFile();
						//设置上传文件大小
						$upload->maxSize=1024*1024*5;//最大2M
						//设置上传文件类型
						$upload->allowExts  = explode(',','jpg,gif,png,bmp');
						//设置附件上传目录
						$upload->savePath ='js/upload/';
						if(!$upload->upload()){
							//捕获上传异常
							// $error_msg = $upload->getErrorMsg();
							json_s(array('status'=>201,'msg'=>'图片文件上传出错，请重新提交。'));
						}
						else {
							//取得成功上传的文件信息
							$ret = $upload->getUploadFileInfo();
							if($ret[0]['key']=='fileToUpload'){
								//正面照片
								$condition['front_img'] = $ret[0]['savepath'].$ret[0]['savename'];
							}
							if($ret[1]['key']=='fileToUpload2'){
								//背面照片
								$condition['back_img'] = $ret[1]['savepath'].$ret[1]['savename'];
							}
							if($condition['back_img']!='' && $condition['front_img']!=''){
								$pan_member_card->create($condition);
								//----邮件后台提醒----begin
									$content = array();
									$content['to'] = array('pwpet@qq.com');
									$content['sub'] = array('%content%'=>array('用户MID：'.$this->mid.'，提交了实名认证'));
									$new_content = json_encode($content);
									send_mail('pwpet@qq.com','【炒米后台提醒】有用户提交实名认证！',$new_content,8);
									$content['to'] = array('605466504@qq.com');
									$new_content = json_encode($content);
									send_mail('605466504@qq.com','【炒米后台提醒】有用户提交实名认证！',$new_content,8);							
								//----邮件后台提醒----end								
								json_s(array('status'=>200,'msg'=>'恭喜，实名资料已成功提交，待审核中。'));
							}else{
								json_s(array('status'=>201,'msg'=>'实名资料提交出错，请重新提交。'));
							}	
						}							
				}
			} else {
				json_s(array('status'=>201,'msg'=>'实名申请提交失败！请重新提交！'));
			}
		}
        $this->act='name_AUT';
		$this->module = "user";
        $this->display("amui/member/userManagement/name_AUT.html");
    }
}
