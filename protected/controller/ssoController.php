<?php // error_reporting(E_ALL || ~E_NOTICE);define("web_name", "炒米网");define("web_domain", "my.chaomi.cc");define("web_md5", "_chaomi_cc");class ssoController extends BaseController {    function __construct() {        parent::__construct();            $this->uid = 0;            $this->mid = 0;		$this->cm_nav='member';		$sso_user = check();		if ($sso_user == true) {			$this->uid = $sso_user['uid'];		}			   }    function actionregister() {//手机注册        if (check() == true) {//如果已经登录，就跳转到会员中心            d301('/member');            exit();        }		d301('/sso/register_email');		//---处理有推荐人的情况---begin		$ecommend_mid = intval($this->spArgs("mid")); //推荐人mid		$from_ecommend_mid = intval($this->spArgs("ecommend_mid")); //推荐人mid--表单		if($ecommend_mid)setcookie("CM_E_MID", $ecommend_mid, time() + 3600 * 24 * 30, "/", ".chaomi.cc");		$cookie_ecommend_mid = intval($_COOKIE['CM_E_MID']); //从cookie取出		if($cookie_ecommend_mid){			$ecommend_mid = $cookie_ecommend_mid;		}		if($from_ecommend_mid)$ecommend_mid = $from_ecommend_mid;		//---处理有推荐人的情况---end		$act = trim($this->spArgs("act"));		if($act=='post'){				$get_member = spClass("lib_member");				$gb_user = spClass('pub_user');				$gbc = spClass('pub_user_check');  				$mobile = trim($this->spArgs("mobile"));				$password = trim($this->spArgs("password"));				$codes = intval($this->spArgs("codes"));				$ip = trim(get_client_ip()); //取得客户端ip 				$ctime = time();				if(empty($codes))json_s(array('status'=>201,'msg'=>'请填写正确短信验证码','ids'=>'#codes'));					if(empty($mobile) || !preg_match("/^1[34578]\d{9}$/", $mobile))json_s(array('status'=>201,'msg'=>'请填写正确的手机号码','ids'=>'#mobile'));									if (empty($password) || strlen($password) < 6 || strlen($password) > 16) { 					json_s(array('status'=>201,'msg'=>'6-16个字母+数字组合，不能带有空格、区分大小写。','ids'=>'#password'));				} 				$password = md5(md5($password . web_md5)); //双重md5加密 								//------------限制相同IP并发请求----------begin				$ip_key = md5($ip);				$key_name = 'register_ip_'.$ip_key;				if(false === cache_a($key_name,time(),3))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后3秒后重试!'));					//------------限制相同IP并发请求----------end								//------------限制相同手机号并发请求----------begin				$mobile_key = md5($mobile);				$key_name = 'register_phone_m_'.$mobile_key;				if(false === cache_a($key_name,time(),3))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后3秒后重试!!'));					//------------限制相同手机号并发请求----------end									//------------限制相同手机号请求次数，防并发撞库----------begin				$key_name = 'register_phone_'.$mobile;				if(cache_s($key_name)>10)json_s(array('status'=>205,'msg'=>'很抱歉，请求次数限制，请稍后1小时后再操作'));								cache_s($key_name,intval(cache_s($key_name))+1,3600);				//------------限制相同手机号请求次数，防并发撞库----------end								//----验证手机号是否已经被注册过-----begin				$conditions = array('mobile' => $mobile); 				$result = $gb_user->find($conditions); 				if($result)json_s(array('status'=>201,'msg'=>'手机号已被注册','ids'=>'#mobile'));							//----验证手机号是否已经被注册过-----end								$conditions=array("cname"=>$mobile,"ctype"=>1,"cstr"=>$codes,"status"=>0);  				$ret = $gbc -> find($conditions);				if($ret){					if(time() - $ret['req_time'] > 600)json_s(array('status'=>201,'msg'=>'短信验证码已失效，请重新获取验证码','ids'=>'#codes')); // 10分钟有效期					// if($ip != $ret['req_ip'])json_s(array('status'=>201,'msg'=>'验证码发送IP与当前IP不相同，请重新获取验证码','ids'=>'#codes')); // 发送IP与当前IP是否相等					$row = array("act_time"=>$ctime,"act_ip"=>$ip,"status"=>1); 					$gbc->update($conditions, $row);				}else{					json_s(array('status'=>201,'msg'=>'短信验证码错误','ids'=>'#codes'));					}              				if( $gb_user -> find(array("mobile"=>$mobile))){					json_s(array('status'=>201,'msg'=>'手机号已被注册，请更换!','ids'=>'#mobile'));					} 				if ($get_member->find(array("email" => $mobile))){					json_s(array('status'=>201,'msg'=>'手机号已被注册，请更换!!','ids'=>'#mobile'));					}				//----开始分配UID、MID----begin				$get_member_uid = spClass("lib_member_uid");				$u = $get_member_uid->find(array(""), "id asc"); //取出member_uid表当前uid最小值 分配个UID				$uid = $u['uid'];				$get_member_uid->delete(array("uid" => $uid)); //删除对应请求的uid数值				if($uid==88888){					$uid = 88889;					$get_member_uid->delete(array("uid" => $uid)); //删除对应请求的uid数值				}								$mid = $this->get_mid();				if ($mid == 0){					json_s(array('status'=>201,'msg'=>'注册失败，可重试或联系客服!'));				}				$this->update_mid($mid);				if(false === cache_a('register_uid_'.$uid,time(),5))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍等一会重试!!!'));				if(false === cache_a('register_mid_'.$mid,time(),5))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍等一会重试!!!!'));				//----开始分配UID、MID----end				$data = array("uid" => $uid, "regip" => $ip, "ctime" => $ctime, "email" => $mobile, "password" => $password);				if ($get_member->create($data)) {					$data = array("uid" => $uid, "mid" => $mid, "mobile"=>$mobile, "regtime" => time(), "regip" => $ip);					if($gb_user->create($data)){												//开始登录						$token_time = time() + 3600 * 24 * 1; 						$token = md5(md5($uid . '_' . $password . web_md5 . $token_time)); //产生token   						$gb_login = spClass('pub_user_login'); 						$conditions = array("uid" => $uid); 						if ($gb_login->find($conditions)) { 							$gb_login->update($conditions, array("token" => $token, "token_time" => $token_time)); 						} else { 							$gb_login->create(array("uid" => $uid, "token" => $token, "token_time" => $token_time)); 						}						setcookie("CM_MID", $mid, $token_time, "/", ".chaomi.cc");						setcookie("CM_UID", $uid, $token_time, "/", ".chaomi.cc");						setcookie("CM_TOKEN", $token, $token_time, "/", ".chaomi.cc"); //保存7天  						//---添加站内短信---begin						$type =  '901';						$tit  =  '恭喜您，已成功注册炒米网会员!';						$txt  =  '恭喜您，已成功注册炒米网会员，并绑定了手机号：'.$mobile.'，登录帐号可使用手机号或ID：'.$mid.'，建议用户绑定邮箱：<a href="/user/bindEmail">去绑定</a>';						web_msg_send($tit,$type,$uid,$txt);													//---添加站内短信---end						login_log($uid, 800); //添加登录日志   						user_log($uid,700,$ip,'通过手机号注册帐号成功');   						//---处理有推荐人的情况---begin						if($ecommend_mid){							$ret = $gb_user->find(array('mid'=>$ecommend_mid));							if($ret && $ret['uid'] != $uid){								$pub_ecommend = spClass('pub_ecommend');								$pub_ecommend->create(array('reg_uid'=>$uid,'ecommend_uid'=>$ret['uid']));							}						}						//---处理有推荐人的情况---end												json_s(array('status'=>200,'msg'=>'恭喜，帐号：' . $mobile . ' 注册成功!'));					}				}				exit();			}		        $this->title='会员注册';        $this->ecommend_mid=$ecommend_mid;        $this->display('amui/am_i_reg_phone.html');    }    function actionregister_email() {//邮箱注册        if (check() == true) {//如果已经登录，就跳转到会员中心            d301('/member');            exit();        }		//---处理有推荐人的情况---begin		$ecommend_mid = intval($this->spArgs("mid")); //推荐人mid		$from_ecommend_mid = intval($this->spArgs("ecommend_mid")); //推荐人mid--表单		if($ecommend_mid)setcookie("CM_E_MID", $ecommend_mid, time() + 3600 * 24 * 30, "/", ".chaomi.cc");                if(isset($_COOKIE['CM_E_MID'])){		$cookie_ecommend_mid = intval($_COOKIE['CM_E_MID']); //从cookie取出                }		if(isset($cookie_ecommend_mid)){			$ecommend_mid = $cookie_ecommend_mid;		}		if($from_ecommend_mid)$ecommend_mid = $from_ecommend_mid;		//---处理有推荐人的情况---end		$act = trim($this->spArgs("act"));		if($act=='post'){				$get_member = spClass("lib_member");				$gb_user = spClass('pub_user');				$gbc = spClass('pub_user_check');  				$email = strtolower($this->spArgs("email")); //强制切换小写格式				$password = trim($this->spArgs("password"));				$codes = intval($this->spArgs("codes"));				$ip = trim(get_client_ip()); //取得客户端ip 				$ctime = time();				if(empty($codes))json_s(array('status'=>201,'msg'=>'请填写正确邮件验证码','ids'=>'#codes'));					if(empty($email) || strlen($email) > 50 || !preg_match("/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/", $email))json_s(array('status'=>201,'msg'=>'请填写正确的邮箱','ids'=>'#email'));									if (empty($password) || strlen($password) < 6 || strlen($password) > 16) { 					json_s(array('status'=>201,'msg'=>'6-16个字母+数字组合，不能带有空格、区分大小写。','ids'=>'#password'));				} 				$password = md5(md5($password . web_md5)); //双重md5加密 								//------------限制相同IP并发请求----------begin				$ip_key = md5($ip);				$key_name = 'register_ip_'.$ip_key;				if(false === cache_a($key_name,time(),3))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后3秒后重试!'));					//------------限制相同IP并发请求----------end								//------------限制相同邮箱并发请求----------begin				$email_key = md5($email);				$key_name = 'register_email_m_'.$email_key;				if(false === cache_a($key_name,time(),3))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后3秒后重试!!'));					//------------限制相同邮箱并发请求----------end								//------------限制相同邮箱请求次数，防并发撞库----------begin				$key_name = 'register_email_'.$email_key;				if(cache_s($key_name)>10)json_s(array('status'=>205,'msg'=>'很抱歉，请求次数限制，请稍后1小时后再操作'));								cache_s($key_name,intval(cache_s($key_name))+1,3600);				//------------限制相同邮箱请求次数，防并发撞库----------end								//----验证邮箱是否已经被注册过-----begin				$conditions = array('email' => $email); 				$result = $get_member->find($conditions); 				if($result)json_s(array('status'=>201,'msg'=>'邮箱已被注册','ids'=>'#email'));							//----验证邮箱是否已经被注册过-----end								$conditions=array("cname"=>$email,"ctype"=>1,"cstr"=>$codes,"status"=>0);  				$ret = $gbc -> find($conditions);				if($ret){					if(time() - $ret['req_time'] > 600)json_s(array('status'=>201,'msg'=>'邮件验证码已失效，请重新获取验证码','ids'=>'#codes')); // 10分钟有效期					// if($ip != $ret['req_ip'])json_s(array('status'=>201,'msg'=>'验证码发送IP与当前IP不相同，请重新获取验证码','ids'=>'#codes')); // 发送IP与当前IP是否相等					$row = array("act_time"=>$ctime,"act_ip"=>$ip,"status"=>1); 					$gbc->update($conditions, $row);				}else{					json_s(array('status'=>201,'msg'=>'邮件验证码错误','ids'=>'#codes'));					}              				if ($get_member->find(array("email" => $email))){					json_s(array('status'=>201,'msg'=>'邮箱已被注册，请更换!','ids'=>'#email'));					} 							//----开始分配UID、MID----begin				$get_member_uid = spClass("lib_member_uid");				$u = $get_member_uid->find(array(""), "id asc"); //取出member_uid表当前uid最小值 分配个UID				$uid = $u['uid'];				$get_member_uid->delete(array("uid" => $uid)); //删除对应请求的uid数值				if($uid==88888){					$uid = 88889;					$get_member_uid->delete(array("uid" => $uid)); //删除对应请求的uid数值				}								$mid = $this->get_mid();				if ($mid == 0){					json_s(array('status'=>201,'msg'=>'注册失败，可重试或联系客服!'));				}				$this->update_mid($mid);				if(false === cache_a('register_uid_'.$uid,time(),5))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍等一会重试!!!'));				if(false === cache_a('register_mid_'.$mid,time(),5))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍等一会重试!!!!'));				//----开始分配UID、MID----end				$data = array("uid" => $uid, "regip" => $ip, "ctime" => $ctime, "email" => $email, "password" => $password);				if ($get_member->create($data)) {					$data = array("uid" => $uid, "mid" => $mid, "mobile"=>'', "regtime" => time(), "regip" => $ip);					if($gb_user->create($data)){												//开始登录						$token_time = time() + 3600 * 24 * 1; 						$token = md5(md5($uid . '_' . $password . web_md5 . $token_time)); //产生token   						$gb_login = spClass('pub_user_login'); 						$conditions = array("uid" => $uid); 						if ($gb_login->find($conditions)) { 							$gb_login->update($conditions, array("token" => $token, "token_time" => $token_time)); 						} else { 							$gb_login->create(array("uid" => $uid, "token" => $token, "token_time" => $token_time)); 						}						setcookie("CM_MID", $mid, $token_time, "/", ".chaomi.cc");						setcookie("CM_UID", $uid, $token_time, "/", ".chaomi.cc");						setcookie("CM_TOKEN", $token, $token_time, "/", ".chaomi.cc"); //保存7天 						//---添加站内短信---begin						$type =  '901';						$tit  =  '恭喜您，已成功注册炒米网会员!';						$txt  =  '恭喜您，已成功注册炒米网会员，并绑定了邮箱：'.$email.'，登录帐号可使用邮箱或ID：'.$mid.'，请马上绑定手机号：<a href="/user/bindMobile">去绑定</a>';						web_msg_send($tit,$type,$uid,$txt);													//---添加站内短信---end												login_log($uid, 800); //添加登录日志   						user_log($uid,700,$ip,'通过邮箱注册帐号成功');						//---处理有推荐人的情况---begin						if($ecommend_mid){							$ret = $gb_user->find(array('mid'=>$ecommend_mid));							if($ret && $ret['uid'] != $uid){								$pub_ecommend = spClass('pub_ecommend');								$pub_ecommend->create(array('reg_uid'=>$uid,'ecommend_uid'=>$ret['uid']));							}						}						//---处理有推荐人的情况---end						json_s(array('status'=>200,'msg'=>'恭喜，帐号：' . $email . ' 注册成功!'));					}				}				exit();			}        $this->title='会员注册';        $this->ecommend_mid=$ecommend_mid;        $this->display('amui/am_i_reg_email.html');    }	    function actionlogin() {//登录页面        if (check() == true) {//如果已经登录，就跳转到会员中心            d301('/member');            exit();        }			$act = trim($this->spArgs("act"));		if($act=='post'){				$gb_user = spClass('pub_user');			$gb_member = spClass("lib_member");			$username = trim($this->spArgs("username"));			$password = trim($this->spArgs("password"));			$ip = trim(get_client_ip()); //取得客户端ip 			if(empty($username) || strlen($username) > 50){				json_s(array('status'=>201,'msg'=>'可使用ID/手机号/邮箱登录，请检查输入的帐号是否正确。','ids'=>'#username'));			}			if(strlen($password)<6 || strlen($password) > 16){				json_s(array('status'=>201,'msg'=>'6-16个字母+数字组合，不能带有空格、区分大小写。','ids'=>'#password'));			}				//------------限制相同IP并发请求----------begin			$ip_key = md5($ip);			$key_name = 'login_ip_'.$ip_key;			if(false === cache_a($key_name,time(),3))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后3秒后重试!'));				//------------限制相同IP并发请求----------end						//------------限制相同帐号并发请求----------begin			$username_key = md5($username);			$key_name = 'login_username_m_'.$username_key;			if(false === cache_a($key_name,time(),3))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后3秒后重试!!'));				//------------限制相同帐号并发请求----------end						//------------限制相同帐号请求次数，防并发撞库----------begin			$key_name = 'login_username_'.$username_key;			if(cache_s($key_name)>100)json_s(array('status'=>205,'msg'=>'很抱歉，当前帐号登录请求已被限制，请稍后1小时后再操作'));							cache_s($key_name,intval(cache_s($key_name))+1,3600);			//------------限制相同邮箱请求次数，防并发撞库----------end								$password = md5(md5($password . web_md5)); //双重md5加密			if (is_numeric($username)) {				//***********特殊情况下需要处理**************				//因手机号可重复绑定，如果手机号遇到相同密码时（多），需要待处理				//***********特殊情况下需要处理**************				if (preg_match("/^1[34578]\d{9}$/", $username)) {// 手机登录 								//************** 简单处理一个手机号绑定了多个帐号ID的情况--------begin					//***使用手机号登录的情况下---					$mobile_arr = $gb_user->findAll(array("mobile" => $username),"uid desc","uid,mid","500");					$mobile_count = count($mobile_arr);					if($mobile_count==0)json_s(array('status'=>201,'msg'=>'手机号：'.$username.' 未被注册，请先注册后再登录。','ids'=>'#username'));					if($mobile_count>0){													$mid_str = '';						$repeat = 0;						$r_arr = array();						foreach ($mobile_arr as $s) {							$r = $gb_member->find(array("uid" => $s['uid']));							if($r['password']==$password){								$repeat++;								$mid_str .= 'ID：<b>'.$s['mid'].'</b><br />';								$r_arr = $s; //给变量赋值，如果只匹配到1条时有用到							}						}						//当一个都没有匹配上时，返回密码错误						if($repeat==0)json_s(array('status'=>201,'msg'=>'帐号或密码错误，请检查输入的手机号/密码是否正确。','ids'=>'#password')); 						//当匹配上的数量大于1条时，返回需要ID登录						if($repeat>1)json_s(array('status'=>210,'msg'=>'手机号：<b>'.$username.'</b> 已绑定了'.$mobile_count.'个帐号<br/>以下帐号请使用ID+密码进行登录：<br/>'.$mid_str,'ids'=>''));						if($repeat!=1){							//防止出错							json_s(array('status'=>201,'msg'=>'登录处理一个手机号对多个帐号ID时出错，请联系网站客服处理。','ids'=>'')); 						}												if($repeat==1){							//放行，继续下面登录 变量$r已取出值							$r = $r_arr;//给变量赋值						}					}					//************** 简单处理一个手机号绑定了多个帐号ID的情况--------end				} elseif (strlen($username)<=10) {// ID登录 					$rn = $gb_user->find(array("mid" => $username));					if(!$rn)json_s(array('status'=>201,'msg'=>'帐号ID：'.$username.' 不存在，请检查输入的ID是否正确。','ids'=>'#username'));					$r = $gb_member->find(array("uid" => $rn['uid']));					if($r['password'] != $password)json_s(array('status'=>201,'msg'=>'帐号或密码，请检查输入的帐号ID/密码是否正确。','ids'=>'#password'));				}else{					json_s(array('status'=>201,'msg'=>'可使用ID/手机号/邮箱登录，请检查输入的帐号是否正确。','ids'=>'#username'));				}			} elseif (preg_match("/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/", $username)) {//邮箱登陆 				$r = $gb_member->find(array("email" => $username));				if(!$r)json_s(array('status'=>201,'msg'=>'邮箱：'.$username.' 未被注册，请先注册后再登录。','ids'=>'#username'));				if($r['password'] != $password)json_s(array('status'=>201,'msg'=>'帐号或密码，请检查输入的邮箱/密码是否正确。','ids'=>'#password'));			}else{				json_s(array('status'=>201,'msg'=>'可使用ID/手机号/邮箱登录，请检查输入的帐号是否正确。','ids'=>'#username'));			}			if ($r) {							// if($r['uid']!=1){//------------------------------调试！！！！！！！！！！！！！！					// json_s(array('status'=>201,'msg'=>'系统还在调试中，禁止会员登录！','ids'=>'#username'));				// }					$uid = $r['uid'];				if ($r['status'] == 1) {//如果状态=1，即是被锁定了					json_s(array('status'=>201,'msg'=>'系统提示：帐号：' . $username . ' 被临时锁定中! 请联系网站客服处理。'));				}				$ru = $gb_user->find(array("uid" => $uid));				//--------查总表是否有记录，旧用户对接--------				if (!$ru) {					$mid = $this->get_mid();					$this->update_mid($mid);					if ($mid == 0){						json_s(array('status'=>201,'msg'=>'注册失败，可重试或联系客服!'));					}					if(false === cache_a('register_mid_'.$mid,time(),5))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍等一会重试!!!!'));					$data = array("uid" => $uid, "mid" => $mid, "regtime" => time(), "regip" => $ip);					$gb_user->create($data);				}else {					$mid = $ru['mid'];				}				$token_time = time() + 3600 * 24 * 1;				$token = md5(md5($uid . '_' . $password . web_md5 . $token_time)); //产生token  				$gb_login = spClass('pub_user_login');				$conditions = array("uid" => $uid);				if ($gb_login->find($conditions)) {					$gb_login->update($conditions, array("token" => $token, "token_time" => $token_time));				} else {					$gb_login->create(array("uid" => $uid, "token" => $token, "token_time" => $token_time));				}				setcookie("CM_MID", $mid, $token_time, "/", ".chaomi.cc");				setcookie("CM_UID", $uid, $token_time, "/", ".chaomi.cc");				setcookie("CM_TOKEN", $token, $token_time, "/", ".chaomi.cc"); //保存7天  				login_log($uid, 800); //添加登录日志 				cache_s($key_name,null);//清操作登录帐号次数限制缓存				json_s(array('status'=>200,'msg'=>'帐号ID：'.$mid.' 登录成功，欢迎您！'));			} else {				json_s(array('status'=>201,'msg'=>'提示：账号或密码错误！','ids'=>'#username'));			}		}        $this->title="会员登录";        $this->display('amui/am_i_sso_login.html');    }    //注册用户，分配MID    function get_mid() {        $gb = spClass('pub_user_mid');        $u = $gb->find(array(""), "id asc"); //取出上次MID          $minuid = $u['mid'] + 1;         $minuid = $this->CleanBadNumber($minuid); //过滤4         $mid = 0;        $gbu = spClass('pub_user');        $i = 0;        while (($mid == 0) && ($i < 10)) {            $pu = $gbu->find(array("mid" => $minuid));            if ($pu) {                $minuid = $minuid + 1;                $minuid = $this->CleanBadNumber($minuid); //过滤4             } else {                $mid = $minuid;            }            $i+=1;            //echo $i."|".$minuid.'<br>';        }        return ($mid);    }        //更新分配 MID 值    function update_mid($mid) {        $gb = spClass('pub_user_mid');        $row = array("mid" => $mid);        $gb->update(array(""), $row);    }    function CleanBadNumber($numb){        //排除末尾含三豹子的ID        $i1=substr($numb,-1,1);        $i2=substr($numb,-2,1);        $i3=substr($numb,-3,1);         if(($i1==$i2)&&($i1==$i3)){            $numb=$numb+1;        }         if(($i1==4)&&($i2==5)&&($i3==5)){            $numb=$numb+2;        }        //排除含4的ID        if(eregi(4,$numb)){            $a=$numb;            $n=1;            while ($a > 0 && $n < 100000000) {                 $b = $a % 10;                 $a = floor($a / 10);                  if($b==4){                    $numb = $numb + $n;                    break;                }else{                    $n = $n * 10;                }                }                    }        return($numb);    }    // 注册协议    function actionpackword() {        $this->display("pact.html");    }    function actionfindpsw() {//短信找回密码页面      if (check() == true) {//如果已经登录，就跳转到会员中心            d301('/member');            exit();        }		$act = trim($this->spArgs("act"));		if($act=='post'){				$get_member = spClass("lib_member");				$gb_user = spClass('pub_user');				$gbc = spClass('pub_user_check');  				$mid = intval($this->spArgs("mid"));				$mobile = trim($this->spArgs("mobile"));				$password = trim($this->spArgs("password"));				$codes = intval($this->spArgs("codes"));				$ip = trim(get_client_ip()); //取得客户端ip 				$ctime = time();				if(empty($mid))json_s(array('status'=>201,'msg'=>'请填写正确帐号ID','ids'=>'#mid'));					if(empty($codes))json_s(array('status'=>201,'msg'=>'请填写正确短信验证码','ids'=>'#codes'));					if(empty($mobile) || !preg_match("/^1[34578]\d{9}$/", $mobile))json_s(array('status'=>201,'msg'=>'请填写正确的手机号码','ids'=>'#mobile'));									if (empty($password) || strlen($password) < 6 || strlen($password) > 16) { 					json_s(array('status'=>201,'msg'=>'6-16个字母+数字组合，不能带有空格、区分大小写。','ids'=>'#password'));				} 				$password = md5(md5($password . web_md5)); //双重md5加密 								//------------限制相同IP并发请求----------begin				$ip_key = md5($ip);				$key_name = 'find_ip_'.$ip_key;				if(false === cache_a($key_name,time(),3))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后3秒后重试!'));					//------------限制相同IP并发请求----------end								//------------限制相同手机号并发请求----------begin				$mobile_key = md5($mobile);				$key_name = 'find_phone_m_'.$mobile_key;				if(false === cache_a($key_name,time(),3))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后3秒后重试!!'));					//------------限制相同手机号并发请求----------end									//------------限制相同手机号请求次数，防并发撞库----------begin				$key_name = 'find_phone_'.$mobile;				if(cache_s($key_name)>100)json_s(array('status'=>205,'msg'=>'很抱歉，请求次数限制，请稍后1小时后再操作'));								cache_s($key_name,intval(cache_s($key_name))+1,3600);				//------------限制相同手机号请求次数，防并发撞库----------end								//------------限制相同帐号ID请求次数，防并发撞库----------begin				$key_name = 'find_mid_'.md5($mid);				if(cache_s($key_name)>100)json_s(array('status'=>205,'msg'=>'很抱歉，请求次数限制，请稍后1小时后再操作'));								cache_s($key_name,intval(cache_s($key_name))+1,3600);				//------------限制相同帐号ID请求次数，防并发撞库----------end								//----验证手机号是否与帐号ID有关联-----begin				$conditions = array('mobile' => $mobile,'mid'=>$mid); 				$result = $gb_user->find($conditions); 				if(!$result)json_s(array('status'=>201,'msg'=>'手机号与帐号ID不存在绑定关系，请检查输入的手机号与帐号ID是否正确。','ids'=>'#mobile'));							//----验证手机号是否与帐号ID有关联-----end				$uid = $result['uid'];				$conditions=array("cname"=>$mobile,"ctype"=>2,"cstr"=>$codes,"status"=>0);  				$ret = $gbc -> find($conditions);				if($ret){					if(time() - $ret['req_time'] > 600)json_s(array('status'=>201,'msg'=>'短信验证码已失效，请重新获取验证码','ids'=>'#codes')); // 10分钟有效期					// if($ip != $ret['req_ip'])json_s(array('status'=>201,'msg'=>'验证码发送IP与当前IP不相同，请重新获取验证码','ids'=>'#codes')); // 发送IP与当前IP是否相等					$row = array("act_time"=>$ctime,"act_ip"=>$ip,"status"=>1); 					$gbc->update($conditions, $row);				}else{					json_s(array('status'=>201,'msg'=>'短信验证码错误','ids'=>'#codes'));					}              				if ($get_member->update(array("uid" => $uid), array("password" => $password, "token_time" => '0', "app_token_time" => '0'))) {					user_log($uid,701, $ip, '通过短信找回密码并修改新密码');					//---删除登录token---begin					$gb_login = spClass('pub_user_login');					$gb_login->delete(array("uid" => $uid));					$cache_name = 'cm_login_user_'.$uid.'_'.$mid; // 缓存名值					cache_s($cache_name,null);										//---删除登录token---end					//---添加站内短信---begin					$type =  '901';					$tit  =  '安全提示：帐号修改了新密码!';					$txt  =  '系统安全提示，当前帐号通过短信找回密码功能修改了新密码，操作IP：'.$ip.'，如不是本人操作请联系客服，如是本人操作请忽略本条提示。';					web_msg_send($tit,$type,$uid,$txt);												//---添加站内短信---end						send_mobile_email($uid,"炒米网(chaomi.cc)帐号修改密码提醒","您于".date("Y-m-d H:i:s")."在平台通过短信找回密码功能已成功修改新的登录密码，如是你本人操作请忽略。");					check_out();					json_s(array('status'=>200,'msg'=>'手机号：'.$mobile.' 成功通过短信找回密码，请使用新密码进行登录。','ids'=>''));				}					exit();			}	        $this->title='通过短信找回密码';        $this->display('amui/am_i_sso_findpsw.html');			    }    function actionfindpsw_email() {//邮件找回密码页面      if (check() == true) {//如果已经登录，就跳转到会员中心            d301('/member');            exit();        }		$act = trim($this->spArgs("act"));		if($act=='post'){				$get_member = spClass("lib_member");				$gb_user = spClass('pub_user');				$gbc = spClass('pub_user_check');  				$email = trim($this->spArgs("email"));				$email = strtolower($this->spArgs('email'));				$password = trim($this->spArgs("password"));				$codes = intval($this->spArgs("codes"));				$ip = trim(get_client_ip()); //取得客户端ip 				$ctime = time();				if(empty($codes))json_s(array('status'=>201,'msg'=>'请填写正确短信验证码','ids'=>'#codes'));					if(empty($email) || strlen($email) > 50 || !preg_match("/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/", $email))json_s(array('status'=>201,'msg'=>'请填写正确的邮箱','ids'=>'#email'));								if (empty($password) || strlen($password) < 6 || strlen($password) > 16) { 					json_s(array('status'=>201,'msg'=>'6-16个字母+数字组合，不能带有空格、区分大小写。','ids'=>'#password'));				} 				$password = md5(md5($password . web_md5)); //双重md5加密 								//------------限制相同IP并发请求----------begin				$ip_key = md5($ip);				$key_name = 'find_ip_'.$ip_key;				if(false === cache_a($key_name,time(),3))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后3秒后重试!'));					//------------限制相同IP并发请求----------end								//------------限制相同邮箱并发请求----------begin				$email_key = md5($email);				$key_name = 'find_email_m_'.$email_key;				if(false === cache_a($key_name,time(),3))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后3秒后重试!!'));					//------------限制相同邮箱并发请求----------end									//------------限制相同邮箱请求次数，防并发撞库----------begin				$key_name = 'find_email_'.$email_key;				if(cache_s($key_name)>10)json_s(array('status'=>205,'msg'=>'很抱歉，请求次数限制，请稍后1小时后再操作'));								cache_s($key_name,intval(cache_s($key_name))+1,3600);				//------------限制相同邮箱请求次数，防并发撞库----------end								//----验证手机号是否与帐号ID有关联-----begin				$conditions = array('email' => $email); 				$result = $get_member->find($conditions); 				if(!$result)json_s(array('status'=>201,'msg'=>'邮箱不存在，请检查输入的邮箱是否正确。','ids'=>'#email'));							//----验证手机号是否与帐号ID有关联-----end				$uid = $result['uid'];				$conditions=array("cname"=>$email,"ctype"=>2,"cstr"=>$codes,"status"=>0);  				$ret = $gbc -> find($conditions);				if($ret){					if(time() - $ret['req_time'] > 600)json_s(array('status'=>201,'msg'=>'邮件验证码已失效，请重新获取验证码','ids'=>'#codes')); // 10分钟有效期					// if($ip != $ret['req_ip'])json_s(array('status'=>201,'msg'=>'验证码发送IP与当前IP不相同，请重新获取验证码','ids'=>'#codes')); // 发送IP与当前IP是否相等					$row = array("act_time"=>$ctime,"act_ip"=>$ip,"status"=>1); 					$gbc->update($conditions, $row);				}else{					json_s(array('status'=>201,'msg'=>'邮件验证码错误','ids'=>'#codes'));					}              				if ($get_member->update(array("uid" => $uid), array("password" => $password, "token_time" => '0', "app_token_time" => '0'))) {					user_log($uid,703,$ip,'通过邮件找回密码并修改新密码');					//---删除登录token---begin					$gb_login = spClass('pub_user_login');					$gb_login->delete(array("uid" => $uid));									//---删除登录token---end					//---添加站内短信---begin					$type =  '901';					$tit  =  '安全提示：帐号修改了新密码!';					$txt  =  '系统安全提示，当前帐号通过邮件找回密码功能修改了新密码，操作IP：'.$ip.'，如不是本人操作请联系客服，如是本人操作请忽略本条提示。';					web_msg_send($tit,$type,$uid,$txt);												//---添加站内短信---end						send_mobile_email($uid,"炒米网(chaomi.cc)帐号修改密码提醒","您于".date("Y-m-d H:i:s")."在平台通过邮件找回密码功能已成功修改新的登录密码，如是你本人操作请忽略。");					check_out();					json_s(array('status'=>200,'msg'=>'邮箱：'.$email.' 成功通过邮件找回密码，请使用新密码进行登录。','ids'=>''));				}					exit();			}	        $this->title='通过邮件找回密码';        $this->display('amui/am_i_sso_findpsw_email.html');		    }    function actionfindmid() {//通过短信找回帐号ID      if (check() == true) {//如果已经登录，就跳转到会员中心            d301('/member');            exit();        }		$act = trim($this->spArgs("act"));		if($act=='post'){				$get_member = spClass("lib_member");				$gb_user = spClass('pub_user');				$gbc = spClass('pub_user_check');  				$mobile = trim($this->spArgs("mobile"));				$codes = intval($this->spArgs("codes"));				$ip = trim(get_client_ip()); //取得客户端ip 				$ctime = time();				if(empty($codes))json_s(array('status'=>201,'msg'=>'请填写正确短信验证码','ids'=>'#codes'));					if(empty($mobile) || !preg_match("/^1[34578]\d{9}$/", $mobile))json_s(array('status'=>201,'msg'=>'请填写正确的手机号码','ids'=>'#mobile'));													//------------限制相同IP并发请求----------begin				$ip_key = md5($ip);				$key_name = 'find_ip_'.$ip_key;				if(false === cache_a($key_name,time(),3))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后3秒后重试!'));					//------------限制相同IP并发请求----------end								//------------限制相同手机号并发请求----------begin				$mobile_key = md5($mobile);				$key_name = 'find_phone_m_'.$mobile_key;				if(false === cache_a($key_name,time(),3))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后3秒后重试!!'));					//------------限制相同手机号并发请求----------end									//------------限制相同手机号请求次数，防并发撞库----------begin				$key_name = 'find_phone_'.$mobile;				if(cache_s($key_name)>10)json_s(array('status'=>205,'msg'=>'很抱歉，请求次数限制，请稍后1小时后再操作'));								cache_s($key_name,intval(cache_s($key_name))+1,3600);				//------------限制相同手机号请求次数，防并发撞库----------end								//----验证手机号是否与帐号ID有关联-----begin				$conditions = array('mobile' => $mobile); 				$result = $gb_user->find($conditions); 				if(!$result)json_s(array('status'=>201,'msg'=>'此手机号未绑定任何帐号ID，请检查输入的手机号是否正确。','ids'=>'#mobile'));							//----验证手机号是否与帐号ID有关联-----end				$conditions=array("cname"=>$mobile,"ctype"=>2,"cstr"=>$codes,"status"=>0);  				$ret = $gbc -> find($conditions);				if($ret){					if(time() - $ret['req_time'] > 600)json_s(array('status'=>201,'msg'=>'短信验证码已失效，请重新获取验证码','ids'=>'#codes')); // 10分钟有效期					// if($ip != $ret['req_ip'])json_s(array('status'=>201,'msg'=>'验证码发送IP与当前IP不相同，请重新获取验证码','ids'=>'#codes')); // 发送IP与当前IP是否相等					$row = array("act_time"=>$ctime,"act_ip"=>$ip,"status"=>1); 					$gbc->update($conditions, $row);				}else{					json_s(array('status'=>201,'msg'=>'短信验证码错误','ids'=>'#codes'));					}				$conditions = array('mobile' => $mobile); 				$result = $gb_user->findAll($conditions,"uid desc","mid","500"); 								if ($result) {					$mid_str = '';					foreach ($result as $r) {						$mid_str .= 'ID：<b>'.$r['mid'].'</b><br />';					}										json_s(array('status'=>200,'msg'=>'手机号：<b>'.$mobile.'</b> 已绑定了以下帐号ID：</b><br/>'.$mid_str,'ids'=>''));				}					exit();			}	        $this->title='通过短信找回帐号ID';        $this->display('amui/am_i_sso_findmid.html');			    }	function actionlogin_out(){ //退出登录		check_out();		$from = $this->spArgs('from');		$url = 'http://my.chaomi.cc/';		if($from=='cm')$url = 'http://www.chaomi.cc/';		d301($url);			}    /*     * 邮件接口     */    function sendEmail() {		$validate = strtolower($this->spArgs('validate')); // 获得前端输入的验证码		$validate_ = $_SESSION['validate'];		if($validate_=='')json_s(array('status'=>201,'msg'=>'请点击重新获取图形验证码','ids'=>'#yzm_img'));		unset($_SESSION['validate']); //不管下面验证是否通过，都要删掉此变量***		///----验证码----end		if($validate_!=$validate)json_s(array('status'=>201,'msg'=>'验证码错误，请重新输入','ids'=>'#yzm_img'));		///----验证码----end			        $email = strtolower($this->spArgs('email'));        $ctype = $this->spArgs('ctype');		if(empty($email) || strlen($email) > 50 || !preg_match("/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/", $email))json_s(array('status'=>201,'msg'=>'请填写正确的邮箱','ids'=>'#email'));		        if ($ctype != 1 && $ctype != 2 && $ctype != 3 && $ctype != 5) {//1注册验证，2找回密码，3安全码验证，5绑定验证			json_s(array('status'=>201,'msg'=>'无效的发送请求','ids'=>''));        }        if ($ctype == 1){//注册			/////检测邮箱是否已经被注册/////			$lib_member = spClass("lib_member"); 			$conditions = array('email' => $email); 			$result = $lib_member->find($conditions); 			if($result){				json_s(array('status'=>201,'msg'=>'邮箱已被注册','ids'=>'#email'));			}   						/////检测邮箱是否已经被注册/////			$title = "炒米网(chaomi.cc)注册帐号邮箱验证";        }        if ($ctype == 2){//邮件找回密码			/////检测邮箱是否已经被注册/////			$lib_member = spClass("lib_member"); 			$conditions = array('email' => $email); 			$result = $lib_member->find($conditions); 			if(!$result){				json_s(array('status'=>201,'msg'=>'邮箱不存在，请检查输入的邮箱是否正确。','ids'=>'#email'));			}   						/////检测邮箱是否已经被注册/////			$title = "炒米网(chaomi.cc)找回密码邮箱验证";        }	        if ($ctype == 5){//绑定或更换绑定邮箱			/////检测邮箱是否已经被注册/////			$lib_member = spClass("lib_member"); 			$conditions = array('email' => $email); 			$result = $lib_member->find($conditions); 			if($result){				json_s(array('status'=>201,'msg'=>'邮箱已被绑定','ids'=>'#email'));			}   						/////检测邮箱是否已经被注册/////			$title = "炒米网(chaomi.cc)帐号绑定邮箱验证";        }		        $gb = spClass('pub_user_check');        $time = time() - 3600 * 24; //24小时前内只能发送5条        $sql = "select * from share_user.pub_user_check where cname='".$email."' and ctype=$ctype and req_time >'" . $time . "'";        $row = $gb->findSql($sql);        if (count($row) > 10) {			json_s(array('status'=>201,'msg'=>'当天发送邮件数已经超限！','ids'=>''));        } else {            $time = time() - 60 * 1; //1分钟内不能重复发送            $sql = "select * from share_user.pub_user_check where cname='".$email."' and ctype=$ctype and req_time >'" . $time . "'";            $row = $gb->findSql($sql);            if (count($row) > 0) {				json_s(array('status'=>201,'msg'=>'请不要重复发送邮件，1分钟后再操作！','ids'=>''));            }        }        $code = rand(100000, 999999);         $newrow = array(            'cname' => $email,            'ctype' => $ctype,            'cstr' => $code,			'uid' => $this->uid?$this->uid:'',            'req_time' => time(),            'req_ip' => get_client_ip(),            'status' => 0,        );         $gb = spClass('pub_user_check');        $gb->create($newrow);		//---将邮件内容以JSON格式存到数据库---begin		$content = array();		$content['to'] = array($email);		$content['sub'] = array('%email%'=>array($email),'%times%'=>array(date("Y-m-d H:i:s")),'%codes%'=>array($code));		$new_content = json_encode($content);		//---将邮件内容以JSON格式存到数据库---end		// function send_mail($address, $title, $content,$type=0)		$result=send_mail($email,$title,$new_content,$ctype);        if ($result) {            json_s(array('status'=>200,'msg'=>'邮件验证码已发送至您邮箱：'.$email.' 请注意查收','ids'=>''));        } else {			json_s(array('status'=>201,'msg'=>'发送失败','ids'=>''));        }    }             /*     * 短信接口     */    function sendMsg() {		$validate = strtolower($this->spArgs('validate')); // 获得前端输入的验证码		$validate_ = $_SESSION['validate'];				if($validate_=='')json_s(array('status'=>201,'msg'=>'请点击重新获取图形验证码:'.$validate_,'ids'=>'#yzm_img'));		unset($_SESSION['validate']); //不管下面验证是否通过，都要删掉此变量***		///----验证码----end		if($validate_!=$validate)json_s(array('status'=>201,'msg'=>'验证码错误，请重新输入','ids'=>'#yzm_img'));		///----验证码----end	        $mobile = $this->spArgs('mobile');        $ctype = $this->spArgs('ctype');		if(empty($mobile) || !preg_match("/^1[34578]\d{9}$/", $mobile))json_s(array('status'=>201,'msg'=>'请填写正确的手机号码','ids'=>'#mobile'));		        if ($ctype != 1 && $ctype != 2 && $ctype != 3 && $ctype != 5 && $ctype != 7) {//1注册验证，2找回密码，3安全码验证，5绑定验证 6交易 7设置中心			json_s(array('status'=>201,'msg'=>'无效的发送请求','ids'=>''));        }        if ($ctype == 1){//注册			json_s(array('status'=>201,'msg'=>'暂时关闭手机号注册，请通过邮箱注册','ids'=>''));			/////检测手机号是否已经被注册/////			$get_user = spClass("pub_user"); 			$conditions = array('mobile' => $mobile); 			$result = $get_user->find($conditions); 			if($result){				json_s(array('status'=>201,'msg'=>'手机号已被注册','ids'=>'#mobile'));			}   						/////检测手机号是否已经被注册/////        }        if ($ctype == 2){//短信找回密码			/////检测手机号是否已经被注册/////			$get_user = spClass("pub_user"); 			$conditions = array('mobile' => $mobile); 			$result = $get_user->find($conditions); 			if(!$result){				json_s(array('status'=>201,'msg'=>'手机号不存在，请检查输入的手机号是否正确。','ids'=>'#mobile'));			}   						/////检测手机号是否已经被注册/////        }        if ($ctype == 3){//修改安全码			/////检测手机号是否已经被注册/////			$get_user = spClass("pub_user"); 			$conditions = array('mobile' => $mobile); 			$result = $get_user->find($conditions); 			if(!$result){				json_s(array('status'=>201,'msg'=>'手机号不存在，请检查输入的手机号是否正确。','ids'=>'#mobile'));			}   						/////检测手机号是否已经被注册/////        }		        if ($ctype == 7){//设置中心			/////检测手机号是否已经被注册/////			$get_user = spClass("pub_user"); 			$conditions = array('mobile' => $mobile); 			$result = $get_user->find($conditions); 			if(!$result){				json_s(array('status'=>201,'msg'=>'手机号不存在','ids'=>'#mobile'));			}   						/////检测手机号是否已经被注册/////			        }		        $gb = spClass('pub_user_check');        $time = time() - 3600 * 24; //24小时前内只能发送5条        $sql = "select * from share_user.pub_user_check where cname='".$mobile."' and ctype=$ctype and req_time >'" . $time . "'";        $row = $gb->findSql($sql);        if (count($row) > 10) {			json_s(array('status'=>201,'msg'=>'当天发送短信数已经超限！','ids'=>''));        } else {            $time = time() - 60 * 1; //1分钟内不能重复发送            $sql = "select * from share_user.pub_user_check where cname='".$mobile."' and ctype=$ctype and req_time >'" . $time . "'";            $row = $gb->findSql($sql);            if (count($row) > 0) {				json_s(array('status'=>201,'msg'=>'请不要重复发送短信，1分钟后再操作！','ids'=>''));            }        }        $code = rand(100000, 999999);         $newrow = array(            'cname' => $mobile,            'ctype' => $ctype,            'cstr' => $code,            'uid' => $this->uid?$this->uid:'',            'req_time' => time(),            'req_ip' => get_client_ip(),            'status' => 0,        );         $gb = spClass('pub_user_check');        $gb->create($newrow);		$result=send_msg($mobile,"您好，您的验证码是{$code}，请勿将验证码告诉任何人！",$ctype);        if ($result) {            json_s(array('status'=>200,'msg'=>'短信验证码已发送至您手机号：'.$mobile.' 请注意查收','ids'=>''));        } else {			json_s(array('status'=>201,'msg'=>'发送失败','ids'=>''));        }    }     /*     * 图形验证码接口     */    function yzm() {    		include 'include/yzm/Vcode.class.php';		$code=new Vcode();		//参数true表示 使用中文验证码  默认使用英文验证码		$code->showImage("include/yzm/font/t1.ttf",false);		//获取session  必须在 showImage 方法之后再获取验证码字符串		$_SESSION['validate'] = strtolower($code->code);	}}