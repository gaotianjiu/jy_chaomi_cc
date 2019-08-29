<?php
/*
 * 域名二级解析模块
 *
 */
ini_set('display_errors', 'On');
define("web_md5", "_chaomi_cc"); 
class tows extends spController{
    public $typeid_cost;
    public $self_user;
    function __construct(){ 
        parent::__construct();
	//exit;
	$sso_user = check();
        if ($sso_user == true) {
            $this->uid = $sso_user['uid'];
            $this->mid = $sso_user['mid'];
            check_code();
        } else {
            re_login();
            exit();
        }
        //内部自己帐号免解析费，免兑换费
        $this->self_user=array(19538,19637,19641,19639);//(301-304用户)
        //主品种对应 解析费，解析数量，后缀 暂时页面定义，以后再加到数据库管理
        $this->typeid_cost=array(
            808001=>array(
                'name'=>'四声母COM.CN',
                'cost'=>0,     //解析费用
                'number'=>1000,  //解析or兑换比例 1：number
                'dh1'=>0,       //随机兑换费用
                'dh2'=>300,     //指定兑换费用
                'suffix'=>'.com.cn' //后缀
            ),
            809001=>array(
                'name'=>'四声母NET',
                'cost'=>0,     //解析费用
                'number'=>1000,  //解析or兑换比例 1：number
                'dh1'=>0,       //随机兑换费用
                'dh2'=>300,     //指定兑换费用
                'suffix'=>'.net' //后缀
            ),	
            810001=>array(
                'name'=>'四字母CN',
                'cost'=>0,     //解析费用
                'number'=>1000,  //解析or兑换比例 1：number
                'dh1'=>0,       //随机兑换费用
                'dh2'=>300,     //指定兑换费用
                'suffix'=>'.cn' //后缀
            ),	
            811001=>array(
                'name'=>'四声母WANG',
                'cost'=>0,     //解析费用
                'number'=>1000,  //解析or兑换比例 1：number
                'dh1'=>0,       //随机兑换费用
                'dh2'=>300,     //指定兑换费用
                'suffix'=>'.wang' //后缀
            )				
        ); 

    }
 
    function apply(){
		//申请域名解析
		$uid = $this->uid;
		if($uid!=19641)exit();
		$ip = get_client_ip();
		$now_time = time();
		$now_time_str = date("Y-m-d H:i:s");
                $new_ym_code = spClass('new_ym_code');
		$pan_domain_in = spClass('pan_domain_in'); // 域名实盘表
                $pan_two = spClass('pan_domain_twos'); // 二级域名解析表
                $pan_two_data = spClass('pan_domain_twos_data');// 二级域名详情
                $two_code = spClass('new_ym_code_twos'); //二级品种表
		$from = $this->spArgs('from'); 
		$typeid = intval($this->spArgs('typeid')); //主品种
		if(empty($typeid)){
			// $typeid=808001;
		}
                $two_typeid = intval($this->spArgs('two_typeid')); //二级品种                 
		$typeid_cost = $this->typeid_cost;
		if($from=='gettype'){
			$two_type = $two_code->findAll(array('code' => $typeid,'state'=>1,'two_code'=>208001));
			// $two_type = $two_code->findAll(array('state'=>1,'two_code'=>208001));
			if($typeid!=808001){
				$two_type = $two_code->findAll(array('code' => $typeid,'state'=>1),"order_id asc");
			}
			json_s(array('status'=>200,'two_type'=>$two_type));
		}
		if($from=='config'){
                        $tt = $two_code->find(array('two_code' => $two_typeid));
                        $t_str = $tt['str'];  //品种字符
                        $t_flag = $tt['flag']; //1开，2尾
                        $typeid = $tt['code']; //主品种ID
                        $suffix = $typeid_cost[$typeid]['suffix']; //后缀
                        if($t_flag==1){ //字符开头
                            $tows_dn = $t_str.'**'.$suffix;
                            $where_str =  " and left(domain,2)='".$t_str."' "; 
                        }elseif($t_flag==2){ //字符结尾
                            $tows_dn = '**'.$t_str.$suffix;
                            $where_str = " and substring(domain,3)='".$t_str.$suffix."' "; 
                        }else{ //普货
                            $tows_dn = '****'.$suffix;
                            $where_str = " ";                             
                        }                        
			$ret = $typeid_cost[$typeid];
			if(!$ret){
				json_s(array('status'=>201,'msg'=>'当前品种暂未开放域名解析服务'));
			}
                        //长尾有效期必须满一年
			if($typeid>=808001){
                           $_expire_time = date("Y-m-d",strtotime("+1 day"));
                           $where_str = $where_str . " and expire_time >= '$_expire_time'";
			}
                        $sql = "select id,domain,expire_time,pingtai,locked from cmpai.pan_domain_in";
                        $sql = $sql . " where typeid=$typeid and uid =$uid and locked=0 $where_str order by expire_time asc LIMIT 0,3";
						// echo $sql;
			$domain_list = $pan_domain_in->findSql($sql); //可解析品种列表
                        json_s(array('status'=>200,'typeid'=>$typeid,'name'=>$ret['name'],'domain_list'=>$domain_list,'cost'=>$ret['cost'],'number'=>$ret['number'],'tows_dn'=>$tows_dn));
		}
		if($from=='create'){
                        // if($uid!=1 && $uid!=19641)json_s(array('status'=>201,'msg'=>'待开放'));			
			//验证是否实名
			$member_info = spClass('pan_member_card')->find(array('uid' => $uid));
			$now_uid_name = $member_info['first_name'].$member_info['last_name'];
			if(!$now_uid_name)json_s(array('status'=>201,'msg'=>'请实名认证后再操作解析'));
                        
                        $tt = $two_code->find(array('two_code' => $two_typeid));
			if(!$tt){
				json_s(array('status'=>201,'msg'=>'请先选择解析品种！'));
			}
                        $t_str = $tt['str'];  //品种字符
                        $t_flag = $tt['flag']; //1开，2尾
                        $typeid = $tt['code']; //主品种ID
                        $suffix = $typeid_cost[$typeid]['suffix']; //后缀
                        if($t_flag==1){ //字符开头
                            $tows_dn = $t_str.'**'.$suffix;
                            $where_str =  " and left(domain,2)='".$t_str."' "; 
                        }elseif($t_flag==2){ //字符结尾
                            $tows_dn = '**'.$t_str.$suffix;
                            $where_str = " and substring(domain,3)='".$t_str.$suffix."' "; 
                        }else{ //普货
                            $tows_dn = '****'.$suffix;
                            $where_str = " ";                             
                        }  
			$ret = $typeid_cost[$typeid];
			if(!$ret){
				json_s(array('status'=>201,'msg'=>'当前品种暂未开放域名解析服务'));
			}
			if($typeid>=808001){
                           $_expire_time = date("Y-m-d",strtotime("+1 day"));
                           $where_str = $where_str . " and expire_time >= '$_expire_time'";
			}		
			//---获取域名ID数组----begin
			$id = $this->spArgs('id');	
			if(is_array($id)){
				$tows_count = count($id);
				//务必强制转换成数值类型---begin
				foreach($id as $v){
					$new_arr[]=(int)$v;
				}
				//务必强制转换成数值类型---end
				$ids = implode(',',$new_arr);
				$ids_arr = $new_arr;
			}else{
				json_s(array('status'=>201,'msg'=>'您还没有选择任何域名'));
			}			
			//---获取域名ID数组----end
			
			//------------限制用户并发请求操作解析相关----------begin
			$tows_action_all = 'tows_action_all_type_'.$typeid;
			if(false === cache_a($tows_action_all,time(),10))json_s(array('status'=>205,'msg'=>'解析系统队列较繁忙，请重试。'));	
			//------------限制用户并发请求操作解析相关----------end	
			$conditions = "uid=$uid and locked=0 and typeid=$typeid and id in(".$ids.") $where_str ";
			$domain_count = $pan_domain_in->findCount($conditions);
			if($domain_count!=$tows_count){
				json_s(array('status'=>201,'msg'=>'提示：操作失败，选中的域名与实际不符'));
			}			
			if($tows_count>$domain_count){
				json_s(array('status'=>201,'msg'=>'提示：解析域名个数不能大于当前可用域名数，可用：'.$domain_count.'个'));
			}						
			if($tows_count==0){
				json_s(array('status'=>201,'msg'=>'提示：域名参数出错'));
			}	
			if($tows_count>3){
				json_s(array('status'=>201,'msg'=>'提示：当前开放每次可申请3个域名解析'));
			}			
			//处理安全码
			$pw = trim($this->spArgs('safecode'));
			if(empty($pw))json_s(array('status'=>201,'msg'=>'交易密码不能为空'));
			$pw = md5(md5($pw . web_md5)); //双重md5加密			
			$pws = spClass('pan_user_safecode')->find(array('uid' => $uid));//密码获取代码
			//------------限制帐号请求验证安全码次数----------begin
			$key_safeCode_name = 'tows_safeCode_uid_'.$uid;
			if(cache_s($key_safeCode_name)>30)json_s(array('status'=>205,'msg'=>'很抱歉，交易密码验证请求次数限制，请稍后1小时后再操作'));				
			//------------限制帐号请求验证安全码次数----------end	
			if ($pw != $pws['safecode']) {
				cache_s($key_safeCode_name,intval(cache_s($key_safeCode_name))+1,3600);//输入错误的安全码缓存+1
				json_s(array('status'=>201,'msg'=>'交易密码错误，请注意区分大小写'));
			}
			cache_s($key_safeCode_name,0,3600);//输入正确的安全码重置为0
			
			//处理图形验证码
			$validate = strtolower($this->spArgs('validate')); // 获得前端输入的验证码
			$validate_ = $_SESSION['validate'];
			if($validate_=='')json_s(array('status'=>209,'msg'=>'请点击重新获取图形验证码'));
			unset($_SESSION['validate']); //不管下面验证是否通过，都要删掉此变量***
			///----验证码----end
			if($validate_!=$validate)json_s(array('status'=>209,'msg'=>'验证码错误，请重新输入'));
			///----验证码----end				
			
			//------------限制用户并发请求操作域名相关----------begin
			$domain_action_uid = 'domain_action_uid_'.$uid;
			if(false === cache_a($domain_action_uid,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
			//------------限制用户并发请求操作域名相关----------end	
			$sp = spClass('pan_domain_tows');
			$sql_sw = false;
			$sp->runSql("SET AUTOCOMMIT=0");
			$sp->runSql('BEGIN'); //开启事务
			//解析***********************************
                        $split_domain = array();
			foreach($ids_arr as $v) {
                            $domain = '';
                            $_r = $pan_domain_in->find(array('id'=>$v));
                            $domain = $_r['domain'];
                            $t_row = array();
                            if(!$domain)json_s(array('status'=>201,'msg'=>"错误提示：域名id：$v 获取不到详情域名"));
				$t_row = array(
                                    'uid'=>$uid,
                                    'type'=>1,
                                    'old_typeid'=>$typeid,
                                    'new_typeid'=>$two_typeid,
                                    'domain'=>$domain,
                                    'd_count'=>$ret['number'], 
                                    'create_time'=>$now_time_str,
                                    'cost'=>$ret['cost'],  
                                    'note'=>'[解析成功]',
                                    'act_ip'=>$ip
                                );
                                //二级域名解析记录
                                $split_id = $pan_two->create($t_row);
                                if($split_id>0){
                                    $split_domain[] = $domain;
                                }
                                $td_row = array();
                                for ($i = 1; $i <= $ret['number']; $i++) {
                                    $td_row[] = array(
                                        'uid'=>$uid,
                                        'tid'=>$split_id,
                                        'two_type'=>$two_typeid,
                                        'old_domain'=>$domain,
                                        'new_domain'=>'--.'.$tows_dn, 
                                        'update_time'=>$now_time,
                                        'state'=>0
                                    ); 
                                }
                                //二级域名详情记录
                                $pan_two_data->createAll($td_row);   
                        } 
			//---  把批量生成的二级域名前缀更新为ID
                        $sql = "update pan_domain_twos_data  set new_domain = replace(new_domain,'--',id) where uid=$uid and left(new_domain,2)='--' and state = 0 ";
                        $pan_two_data->runSql($sql); 
			$two_data_rows = $pan_two_data->affectedRows();
			if($two_data_rows!= ($tows_count * $ret['number'])){
                            cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
                            json_s(array('status'=>300,'msg'=>'错误提示：生成二级域名数量与实际不符'));				
			}
			if($tows_count!=count($split_domain)){
                            cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
                            json_s(array('status'=>300,'msg'=>'错误提示：解析域名数量与实际不符'));				
			}
                        //二级域名入盘
                        $expire_time= date( "Y-m-d",strtotime("10 year" ));  
                        $in_sql = "select $uid as uid , new_domain as domain , two_type as typeid  , '$now_time_str' as upd_time , "
                                . " 'chaomi' as pingtai , 1 as status , '$expire_time' as  expire_time , 0 as locked ,"
                                . " '$now_time_str' as apply_time ,  '$now_time_str' as update_time from pan_domain_twos_data where uid = $uid and state = 0 "; 
                        $in_rows = $pan_two_data->findSql($in_sql); 
                        $pan_domain_in->createAll($in_rows); 
			if($two_data_rows!= count($in_rows)){
                            cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
                            json_s(array('status'=>300,'msg'=>'错误提示：入盘二级域名数量与解析二级域名数量不符'));				
			}
                        //入盘成功，改变状态
                        $sql = "update pan_domain_twos_data  set state = 1 where uid=$uid and state = 0 ";
                        $pan_two_data->runSql($sql); 
                        
			//---将域名回收：UID = 305
			$pan_domain_in->update("uid=$uid and locked=0 and id in ($ids)",array('uid'=>19668, 'locked'=>9 ,'upd_time'=>$now_time_str));
			$update_domain_row = $pan_domain_in->affectedRows(); //影响行数
			
                        if($update_domain_row!=$tows_count){
                            cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
                            json_s(array('status'=>300,'msg'=>'错误提示：系统回收域名数量与实际不符'));				
			}
                        $jx_cost = $tows_count * $ret['cost'];
                        if(in_array($uid, $this->self_user)){
                            $jx_cost = 0; // 内部账户解析费为0
                        }
                        if($jx_cost>0){
                            //**********扣除费用 并添加账务记录************ begin
                            $sp = spClass('lib_member_account');
                            //-----------查询用户账户---------
                            $bal_sql = "select * from ykjhqcom.lib_member_account where uid = $uid FOR UPDATE"; //*****只要是查余额表的，必须前面开事务，并且后面加上 FOR UPDATE 单行锁表
                            $member_Account_result = $sp->findSql($bal_sql);
                            $new_amount = $member_Account_result[0]['balance'] - $jx_cost ;
                            if( $new_amount < 0 ){
                                cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
                                json_s(array('status'=>300,'msg'=>'错误提示：余额不足，请先充值！'));	    
                            }
                            $member_Account_sql = "update ykjhqcom.lib_member_account set balance=$new_amount where uid=$uid";
                            $sp->runSql($member_Account_sql);                        
                            $order_id = 'XF'.date("YmdHis").$uid.mt_rand(100000,999999);
                            //准备添加流水相关数据,与流水表字段名相同
                            $note = '自动扣除域名解析费，域名品种：'.$ret['name'].'，单价'.$ret['cost'].'/个，一共'.$tows_count.'个域名，扣除您续费：'.$jx_cost.'元';
                            $row = array(
                                    'uid' => $uid,
                                    'order_id' => $order_id,
                                    'type' => '500',
                                    'amount' => $jx_cost,
                                    'ip' => $ip,
                                    'deal_time' => $now_time,
                                    'note' => $note,
                                    'balance' => $new_amount,
                                    'y' => date("Y", $now_time),
                                    'm' => date("m", $now_time),
                                    'd' => date("d", $now_time)
                            );
                            //添加流水
                            $member_records = spClass('lib_member_records');
                            $member_records->create($row);	
                            //日志
                            user_log($uid,618,$ip,'【用户资产】用户：'.$uid.'自动扣除域名解析费，域名品种：'.$ret['name'].'，单价'.$ret['cost'].'/个，一共'.$tows_count.'个域名，扣除您续费：'.$jx_cost.'元'.'，账户余额'.$new_amount.'元');
                            //**********扣除费用 并添加账务记录************ end
                        }
                   

			user_log($uid, 1503, $ip, "【用户".$uid."】：".$ret['name']."域名解析，解析域名列表：".implode(',',$split_domain));
			//---添加站内短信---begin
			$type =  '901';
			$tit  =  '恭喜，您已成功解析'.$tows_count.'个'.$ret['name'].'域名';
			$txt  =  "您于".$now_time_str.'成功解析'.$tows_count.'个'.$ret['name'].'域名，解析域名列表：'.implode(',',$split_domain);
			web_msg_send($tit,$type,$uid,$txt);							
			//---添加站内短信---end	
			send_mobile_email($uid,"炒米网(chaomi.cc)域名解析提醒","您于".$now_time_str.'成功解析'.$tows_count.'个'.$ret['name'].'域名。');
                        
			$sql_sw = true;
			if(false===$sql_sw){
				$sp->runSql('ROLLBACK'); //回滚事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
				json_s(array('status'=>201,'msg'=>'系统处理出错，已回滚事务。'));				
			}else{
				$sp->runSql('COMMIT'); //提交事务
				cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
				json_s(array('status'=>200,'msg'=>'已成功解析'.$tows_count.'个'.$ret['name'].'域名'));	
			}		
		}		
        //---前端选择域名类型---begin
        $new_ym_code_r = $new_ym_code->findAll('state=1');		
        //---前端选择域名类型---end
        // $this->type_options = $new_ym_code_r; //品种列表
        $this->type_options = array(array('name'=>'四声母COM.CN','code'=>808001),array('name'=>'四声母NET','code'=>809001),array('name'=>'四字母CN','code'=>810001),array('name'=>'四声母WANG','code'=>811001)); //品种列表
        $this->module = "tows";
        $this->act = 'tows_apply';
        $this->display('amui/tows/apply.html');
    }
    function applyList(){
	//二级域名列表
	$uid = $this->uid;
        
	$page = intval($this->spArgs('page', 1));
	if($page<=0)$page=1;
        if($page>50)$page=50;
        
        $pan_domain_in = spClass('pan_domain_in');
		
        $condition = " where uid=$uid and typeid = 200001 ";
        $cond=array('domain'=>"",'typeid'=>'');
 
        //域名**********模糊查询************
        if(false !=$this->spArgs('domain')){
            $domain=$pan_domain_in->escape($this->spArgs('domain'));
            $condition.=" and domain like '%".trim($domain,"'")."%' ";
            $cond['domain']=trim($domain,"'");
        }
        //域名品种
        if(false !=$this->spArgs('typeid')){
            $typeid=intval($this->spArgs('typeid'));
            $condition.=" and typeid=".$typeid." ";
            $cond['typeid']=trim($typeid,"'");
        }	
        $pagesize = 50;
        $begin_n = ($page-1)*$pagesize;        
        $sort = " ORDER BY id desc limit ".$begin_n.",".$pagesize;
        $sql = "select * from cmpai.pan_domain_in ".$condition.$sort ;				
	$ret = $pan_domain_in->findSql($sql);
        $sum_sql = "select count(*) as n from cmpai.pan_domain_in ".$condition;	
        $sum_r = $pan_domain_in->findSql($sum_sql);
        $total_count = $sum_r[0]['n'];
        $total_page = ceil($total_count / $pagesize); 
        if($page>1){
            $prev_page = $page -1;
        } else{
            $prev_page = 1;
        }
        if($page == $total_page){
            $next_page = $total_page;
        }else{
            $next_page = $page +1;
        }
        if($page < 6){
            for($pn=0;$pn<10;$pn++){
                $all_pages[$pn] = $pn +1;
                if($pn >= $total_page -1){
                    break;
                }
            }
        }else{
            for($pn=$page-5;$pn<$page+5;$pn++){
                $all_pages[$pn] = $pn +1;
                if(($pn >= $total_page -1)||($pn>=49)){
                    break;
                }
            } 
        }
        $pager=array(
            'total_count' =>$total_count,
            'page_size' => $pagesize,
            'total_page' => $total_page,
            'first_page' => 1,
            'prev_page' => $prev_page,
            'next_page' => $next_page,
            'last_page' => $total_page,
            'current_page' => $page,
            'all_pages' => $all_pages 
        );
        $this->pager = $pager;		
	$ret_ = array();
	$two_code = spClass('new_ym_code_twos'); 
        $dlist = "select two_code as id,name from cmpai.new_ym_code_twos where state=1 and two_code<=800000";
        $types = $two_code->spCache(3600)->findSql($dlist);        
        //索引数组转成关联数组页面可直接对列表进行替换
        foreach ($types as $k=>$v) {
            $types_[$v["id"]]=$v["name"];
        } 
        $status_arr = array('0'=>'正常','1'=>'锁定中','2'=>'委单中','13'=>'PUSH中');
        $this->status_arr=$status_arr;
        $this->type_options =$types_;
	$this->cond=$cond; 		
        $this->ret = $ret;
        $this->module = "tows";
        $this->act = 'tows_applyList';
        $this->display('amui/tows/applyList.html');		
    }
    
    function domain(){
	//域名兑换池
        exit();
	$page = intval($this->spArgs('page', 1));
	if($page<=0)$page=1;		
        $pan_two = spClass('pan_domain_twos');

        $condition = " where type=1 ";
        $cond=array('domain'=>"",'typeid'=>'');
 
        //域名**********模糊查询************
        if(false !=$this->spArgs('domain')){
            $domain=$pan_two->escape($this->spArgs('domain'));
            $condition.=" and domain  like '%".trim($domain,"'")."%' ";
            $cond['domain']=trim($domain,"'");
        }
        //域名品种
        if(false !=$this->spArgs('typeid')){
            $typeid=intval($this->spArgs('typeid'));
            $condition.=" and old_typeid=".$typeid." ";
            $cond['typeid']=trim($typeid,"'");
        }	 
        $sort = " ORDER BY id desc";
        $sql = "select * from cmpai.pan_domain_twos ".$condition.$sort ;				
	$ret = $pan_two->spPager($page, pgsize)->findSql($sql);
		//分页开始
        $pager = $pan_two->spPager()->getPager();
        if ($pager['total_page'] > 5) {
            if ($page <= 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], $page - 3, 5);
            }
        }
        $this->pager = $pager;		
	$ret_ = array();
	$two_code = spClass('new_ym_code_twos'); 
        $dlist = "select two_code as id,name from cmpai.new_ym_code_twos where state=1 and two_code in(808001,809001,810001,811001)";
        $types = $two_code->findSql($dlist);  
        foreach ($types as $k=>$v) {
            $types_[$v["id"]]=$v["name"];
        }
        $this->type_options =$types_; 
	$this->cond=$cond; 		
        $this->ret = $ret;
        $this->module = "tows";
        $this->act = 'tows_domain';
        $this->display('amui/tows/domain.html');		
    }
    
    function change(){
	//兑换域名
	$uid = $this->uid;
        if($uid!=19641)exit();
        $two_code = spClass('new_ym_code_twos'); 
        $pan_domain_twos = spClass('pan_domain_twos');
        $pan_domain_in = spClass('pan_domain_in');
	$typeid_cost = $this->typeid_cost;
        $did = trim($this->spArgs('did'));
        if($did){
            $pt=$pan_domain_twos->find(array("id"=>$did,"type"=>1));
            $domain = $pt["domain"];
            if($domain){
                //域名存在才能进行指定兑换，否则走随机兑换
                $two_typeid = $pt["new_typeid"]; 
                $typeid = $pt["old_typeid"]; 
                $para=array("uid"=>$uid,"typeid"=>$two_typeid,"locked"=>0);
                $sum=$pan_domain_in->findCount($para);
                $dlist = "select two_code as id,name from cmpai.new_ym_code_twos where two_code = $typeid and state=1";
                $types = $two_code->spCache(3600)->findSql($dlist);
                $this->did=$did;
                $this->domain=$domain;
                $this->sum=$sum;
                $this->number=$typeid_cost[$typeid]["number"];
                $this->cost=$typeid_cost[$typeid]["dh2"];
                $this->types=$types;
                $this->module = "tows";
                $this->act = 'tows_change';
                $this->display('amui/tows/change_one.html');                exit;
            }
        }        
        $from = $this->spArgs('from');  
        if($from=="getnumb"){
            $two_typeid=intval($this->spArgs('typeid'));
            $typearr = $two_code->find(array( 'code' =>$two_typeid));
            $typeid = $typearr["two_code"]; 
            $para=array("uid"=>$uid,"typeid"=>$typeid,"locked"=>0);
            $sum=$pan_domain_in->findCount($para);
            json_s(array('status'=>200,'sum'=>$sum,'number'=>$typeid_cost[$two_typeid][number],'dh1'=>$typeid_cost[$two_typeid][dh1],'dh2'=>$typeid_cost[$two_typeid][dh2]));
        } 
        $dlist = "select two_code as id,name from cmpai.new_ym_code_twos where state=1 and two_code in(808001,809001,810001,811001)";
        $types = $two_code->findSql($dlist);        
        foreach ($types as $k=>$v) {
            $types_[$v["id"]]=$v["name"];
        }
        $this->type_options =$types_; 
        $this->module = "tows";
        $this->act = 'tows_change';
        $this->display('amui/tows/change.html');	
    }
    function changesub(){
	//提交兑换
	$uid = $this->uid;
        if($uid!=19641)exit();
        $did = $this->spArgs('did');
        $two_type = $this->spArgs('two_type');  //兑换品种
        $dh_number = $this->spArgs('dh_number');//兑换数量
        $dd_counts = $this->spArgs('dd_counts');//扣除数量
        $dh_cost = $this->spArgs('dh_cost');    //兑换费用
        if($dh_number>3){
              json_s(array('status'=>204,'msg'=>'错误提示：单次兑换域名数量不能大于3个，如需兑换多个，请分批提交'));
		}	
        $ip = get_client_ip();
        $timestamp = time();
	$now_time = date("Y-m-d H:i:s");  
        $two_code = spClass('new_ym_code_twos'); 
        $pan_domain_twos = spClass('pan_domain_twos');
        $pan_domain_twos_data = spClass('pan_domain_twos_data');
        $pan_domain_twos_out = spClass('pan_domain_twos_out'); 
        $pan_domain_twos_data_out = spClass('pan_domain_twos_data_out');
        $pan_domain_in = spClass('pan_domain_in');
        $pan_domain_outplat = spClass('pan_domain_outplat');
			//处理安全码
			$pw = trim($this->spArgs('safecode'));
			if(empty($pw))json_s(array('status'=>201,'msg'=>'交易密码不能为空'));
			$pw = md5(md5($pw . web_md5)); //双重md5加密
			$pws = spClass('pan_user_safecode')->find(array('uid' => $uid));//密码获取代码
			//------------限制帐号请求验证安全码次数----------begin
			$key_safeCode_name = 'tows_safeCode_uid_'.$uid;
			if(cache_s($key_safeCode_name)>30)json_s(array('status'=>205,'msg'=>'很抱歉，交易密码验证请求次数限制，请稍后1小时后再操作'));				
			//------------限制帐号请求验证安全码次数----------end
			if ($pw != $pws['safecode']) {
				cache_s($key_safeCode_name,intval(cache_s($key_safeCode_name))+1,3600);//输入错误的安全码缓存+1
				json_s(array('status'=>201,'msg'=>'交易密码错误，请注意区分大小写'));
			}
			cache_s($key_safeCode_name,0,3600);//输入正确的安全码重置为0
			
			//处理图形验证码
			$validate = strtolower($this->spArgs('validate')); // 获得前端输入的验证码
			$validate_ = $_SESSION['validate'];
			if($validate_=='')json_s(array('status'=>209,'msg'=>'请点击重新获取图形验证码'));
			unset($_SESSION['validate']); //不管下面验证是否通过，都要删掉此变量***
			///----验证码----end
			if($validate_!=$validate)json_s(array('status'=>209,'msg'=>'验证码错误，请重新输入'));
			///----验证码----end 
       
        $typearr = $two_code->find(array( 'code' =>$two_type));
        $typeid = $two_type;  //主品种CODE
		$two_type = $typearr['two_code'];
        $typeid_cost = $this->typeid_cost[$typeid]; 
        $type_number = $typeid_cost["number"]; //兑换比例   
        $type_price = $typeid_cost["dh1"]; //随机兑换价格 
        $dtype = 0;//兑换类型，默认随机

        if($did){//指定兑换
            $type_price = $typeid_cost["dh2"]; //指定兑换价格  
            $dtype = 1;
            $pt=$pan_domain_twos->find(array("id"=>$did,"type"=>1));
            $domains[]= array("tid" =>$pt["id"],"uid"=>$uid,"type"=>$dtype,"domain"=>$pt["domain"],"code_id"=>$typeid,"two_code"=>$two_type,"d_count"=>$type_number,"d_cost"=>$type_price,"act_ip"=>$ip,"act_time"=>$now_time) ; 
            if($pt){
                $two_typeid = $pt["new_typeid"]; 
                if($two_typeid!=$two_type || $dh_number!=1){
                    json_s(array('status'=>204,'msg'=>'错误提示：指定兑换的域名参数不匹配'));
                }
            }else{
                json_s(array('status'=>204,'msg'=>'错误提示：指定兑换的域名不存在'));
            }            
        }else{//随机兑换
            $domains = $pan_domain_twos->findAll(array("new_typeid"=>$two_type,"type"=>1),"id asc","id as tid ,$uid as uid,$dtype as type,domain,$typeid as code_id,$two_type as two_code,$type_number as d_count,$type_price as d_cost,'$ip' as act_ip,'$now_time' as act_time",$dh_number); 
        }  

        if($dd_counts!=$dh_number*$type_number){
            json_s(array('status'=>204,'msg'=>'错误提示：兑换所要扣除二级域名数量与页面显示不符'));
        }
        if($dh_cost!=$dh_number*$type_price){
            json_s(array('status'=>204,'msg'=>'错误提示：兑换所需费用与页面显示不符'));
        }
        if(!is_array($domains))json_s(array('status'=>204,'msg'=>"错误提示：提取域名错误")); 
        
	//------------限制用户并发请求操作域名begin----------
	$domain_action_uid = 'domain_action_uid_'.$uid;
	if(false === cache_a($domain_action_uid,time(),10))json_s(array('status'=>205,'msg'=>'很抱歉，系统队列繁忙，请稍后刷新重试。'));	
	//------------限制用户并发请求操作域名end----------
	$sp = spClass('pan_domain_twos_out');
	$sql_sw = false;
	$sp->runSql("SET AUTOCOMMIT=0");
	$sp->runSql('BEGIN'); //开启事务
        //开始兑换==================
	foreach($domains as $v) {
            $out_id = $pan_domain_twos_out->create($v);  
            $out_domain = $v['domain'];
            $out_domain_arr[]=$v['domain']; //一级域名列表 
            $out_data_row = array();
            //实盘获取对应比例的二级域名
            $two_domains = $pan_domain_in->findAll(array("uid"=>$uid,"typeid"=>$two_type,"locked"=>0),"id asc","uid,typeid,domain,pingtai,pingtai as domain_website,'$now_time' as time,'$now_time' as deal_time,5 as status",$type_number);
            if(count($two_domains)!=$type_number){
                cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
                json_s(array('status'=>204,'msg'=>'错误提示：账户内没有足够的二级域名兑换'));
            }
            //二级域名提米记录
            $pan_domain_outplat->createAll($two_domains); 
            //删除实盘二级域名
            foreach ($two_domains as $v) {
                $del_domain[]=$v["domain"];   
                $out_data_row[]= array(  
                    'uid'=>$uid,
                    'tid'=>$out_id,
                    'two_type'=>$two_type,
                    'domain'=>$out_domain,
                    'two_domain'=>$v["domain"], 
                    'act_time'=>$now_time,
                    'state'=>1
                ); 
            }
            $domains_str = "'".implode("','", $del_domain)."'"; 
            $sql = "delete from cmpai.pan_domain_in where uid=$uid and typeid=$two_type and locked=0 and domain in ($domains_str) "; 
            $pan_domain_in->runSql($sql);
            $del_rows = $pan_domain_in->affectedRows();
            if($del_rows!=$type_number){
                cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
                json_s(array('status'=>204,'msg'=>'错误提示：实盘没有足够的二级域名让扣除'));
            }
            //实盘处理完毕
            
            $pan_domain_twos_data_out->createAll($out_data_row);
            if(count($out_data_row)!=$type_number){
                cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
                json_s(array('status'=>204,'msg'=>'错误提示：扣除二级域名与实际不符'));   
            }            
               
            //标记已提米
            $sql = "update cmpai.pan_domain_twos_data set state = 2 where new_domain in ($domains_str) "; 
            $pan_domain_twos_data->runSql($sql); 
            $sql = "update cmpai.pan_domain_twos set type = 2 where domain='$out_domain'";
            $pan_domain_twos->runSql($sql);
        } 
        
        //---从域名池提域名
        $out_domains_str = "'".implode("','", $out_domain_arr)."'"; 
        $pan_domain_in->update("uid=19668 and locked=9 and domain in ($out_domains_str)",array('uid'=>$uid, 'locked'=>0 ,'upd_time'=>$timestamp));
	$update_domain_row = $pan_domain_in->affectedRows(); //影响行数
        if($update_domain_row!=$dh_number){
            cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
            json_s(array('status'=>204,'msg'=>'错误提示：兑换域名数量与实际不符'));				
	}
        //兑换完成
        if(in_array($uid, $this->self_user)){
            $dh_cost = 0; // 内部账户兑换费为0
        }
        //处理财务数据
        if($dh_cost > 0){
            //**********扣除费用 并添加账务记录************ begin
            $sp = spClass('lib_member_account');
            //-----------查询用户账户---------
            $bal_sql = "select * from ykjhqcom.lib_member_account where uid = $uid FOR UPDATE"; //*****只要是查余额表的，必须前面开事务，并且后面加上 FOR UPDATE 单行锁表
            $member_Account_result = $sp->findSql($bal_sql);
            $new_amount = $member_Account_result[0]['balance'] -$dh_cost ;
            if( $new_amount < 0 ){
                cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
                json_s(array('status'=>204,'msg'=>'错误提示：余额不足，请先充值！'));	    
            }
            $member_Account_sql = "update ykjhqcom.lib_member_account set balance=$new_amount where uid=$uid";
            $sp->runSql($member_Account_sql);                        
            $order_id = 'XF'.date("YmdHis").$uid.mt_rand(100000,999999);
            //准备添加流水相关数据,与流水表字段名相同
            $note = '自动扣除域名兑换费，兑换品种：'.$typeid_cost["name"].'，单价'.$type_price.'/个，一共'.$dh_number.'个域名，扣除您续费：'.$dh_cost.'元';
                        $row = array(
                                'uid' => $uid,
                                'order_id' => $order_id,
                                'type' => '500',
                                'amount' => $dh_cost,
                                'ip' => $ip,
                                'deal_time' => $timestamp,
                                'note' => $note,
                                'balance' => $new_amount,
                                'y' => date("Y", $timestamp),
                                'm' => date("m", $timestamp),
                                'd' => date("d", $timestamp)
                        );
            //添加流水
            $member_records = spClass('lib_member_records');
            $member_records->create($row);	
            //日志
            user_log($uid,618,$ip,'【用户资产】用户：'.$uid.'自动扣除域名兑换费，域名品种：'.$typeid_cost["name"].'，单价'.$type_price.'/个，一共'.$dh_number.'个域名，扣除您续费：'.$dh_cost.'元'.'，账户余额'.$new_amount.'元');
            user_log($uid, 1503, $ip, "【用户".$uid."】：".$ret['name']."域名兑换，兑换域名列表：".implode(',',$out_domain_arr)); 
        }
        //---添加站内短信---begin
        $type =  '901';
        $tit  =  '恭喜，您已成功兑换'.$dh_number.'个'.$typeid_cost["name"].'域名';
        $txt  =  "您于".$now_time.'成功兑换'.$dh_number.'个'.$typeid_cost["name"].'域名，兑换域名列表：'.implode(',',$out_domain_arr);
        web_msg_send($tit,$type,$uid,$txt);							
        //---添加站内短信---end	
        send_mobile_email($uid,"炒米网(chaomi.cc)域名兑换提醒","您于".$now_time.'成功兑换'.$dh_number.'个'.$typeid_cost["name"].'域名。');
                        
	$sql_sw = true;
	if(false===$sql_sw){
            $sp->runSql('ROLLBACK'); //回滚事务
            cache_a($domain_action_uid,null);//删除用户操作域名并发缓存
            json_s(array('status'=>201,'msg'=>'系统处理出错，已回滚事务。'));				
	}else{
            $sp->runSql('COMMIT'); //提交事务
            cache_a($domain_action_uid,null);//删除用户操作域名并发缓存	
            json_s(array('status'=>200,'msg'=>'已成功解析'.$tows_count.'个'.$ret['name'].'域名'));	
	}  

    }
    
    function inList(){
	//解析记录
        exit();
	$uid = $this->uid;
	$page = intval($this->spArgs('page', 1));
	if($page<=0)$page=1;
        $pan_domain_twos = spClass('pan_domain_twos');
		
        $condition = " where uid=$uid";
        $cond=array('domain'=>"",'typeid'=>'');
 
        //域名**********模糊查询************
        if(false !=$this->spArgs('domain')){
            $domain=$pan_domain_twos->escape($this->spArgs('domain'));
            $condition.=" and domain like '%".trim($domain,"'")."%' ";
            $cond['domain']=trim($domain,"'");
        }
        //域名品种
        if(false !=$this->spArgs('typeid')){
            $new_typeid=intval($this->spArgs('typeid'));
            $condition.=" and new_typeid=".$new_typeid." ";
            $cond['typeid']=trim($new_typeid,"'");
        }	 
        $sort = " ORDER BY id desc";
        $sql = "select * from cmpai.pan_domain_twos ".$condition.$sort ;				
	$ret = $pan_domain_twos->spPager($page, pgsize)->findSql($sql);
		//分页开始
        $pager = $pan_domain_twos->spPager()->getPager();
        if ($pager['total_page'] > 5) {
            if ($page <= 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], $page - 3, 5);
            }
        }
        $this->pager = $pager;		
	$ret_ = array();
	$two_code = spClass('new_ym_code_twos'); 
        $dlist = "select two_code as id,name from cmpai.new_ym_code_twos where state=1 and two_code<800000";
        $types = $two_code->spCache(3600)->findSql($dlist);        
        //索引数组转成关联数组页面可直接对列表进行替换
        foreach ($types as $k=>$v) {
            $types_[$v["id"]]=$v["name"];
        } 
        $this->type_options =$types_;
	$this->cond=$cond; 		
        $this->ret = $ret;
        $this->module = "tows";
        $this->act = 'tows_inList';
        $this->display('amui/tows/inList.html');		
    }
    function outList(){
	//兑换记录
        exit();
	$uid = $this->uid;
	$page = intval($this->spArgs('page', 1));
	if($page<=0)$page=1;
        $pan_domain_twos_out = spClass('pan_domain_twos_out');
		
        $condition = " where uid=$uid";
        $cond=array('domain'=>"",'typeid'=>'');
 
        //域名**********模糊查询************
        if(false !=$this->spArgs('domain')){
            $domain=$pan_domain_twos_out->escape($this->spArgs('domain'));
            $condition.=" and domain like '%".trim($domain,"'")."%' ";
            $cond['domain']=trim($domain,"'");
        }
        //域名品种
        if(false !=$this->spArgs('typeid')){
            $two_code=intval($this->spArgs('typeid'));
            $condition.=" and two_code=".$two_code." ";
            $cond['typeid']=trim($two_code,"'");
        }	 
        $sort = " ORDER BY id desc";
        $sql = "select * from cmpai.pan_domain_twos_out ".$condition.$sort ;				
	$ret = $pan_domain_twos_out->spPager($page, pgsize)->findSql($sql);
		//分页开始
        $pager = $pan_domain_twos_out->spPager()->getPager();
        if ($pager['total_page'] > 5) {
            if ($page <= 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], $page - 3, 5);
            }
        }
        $this->pager = $pager;		
	$ret_ = array();
	$two_code = spClass('new_ym_code_twos'); 
        $dlist = "select two_code as id,name from cmpai.new_ym_code_twos where state=1 and two_code in(808001,809001,810001,811001)";
        $types = $two_code->spCache(3600)->findSql($dlist);        
        //索引数组转成关联数组页面可直接对列表进行替换
        foreach ($types as $k=>$v) {
            $types_[$v["id"]]=$v["name"];
        } 
        $dhtype=array("0"=>"随机兑换","1"=>"指定兑换");
        $this->dhtype=$dhtype;
        $this->type_options =$types_;
	$this->cond=$cond; 		
        $this->ret = $ret;
        $this->module = "tows";
        $this->act = 'tows_outList';
        $this->display('amui/tows/outList.html');		
    }
    //收益分红
    //$status=1   收益分红
    //$status=2   转到余额
    function income(){
        $uid = $this->uid;
        //设置每页显示10条
        $pglimit = pgsize;
        //获取当前页码
        $pgno = intval($this->spArgs('page', 1));
        if($pgno < 1) $pgno=1;
        $sp = spClass('pan_member_income_log');
        
        $status=$this->spArgs('status',0);
        $row=array('uid' => $uid);
        if($status >0) { 
            $row['type'] = $status;
        }
        $res = $sp->spPager($pgno, $pglimit)->findAll($row, 'id desc');
        $pager = $sp->spPager()->getPager();
        //分页参数
        if ($pager['total_page'] > 5) {
            if ($pgno > 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], $pgno - 3, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            }
        }
        $this->res = $res;
        $this->pager = $pager;
        $this->status=$status;
        $this->module = "tows";
        $this->act = 'tows_income';
        $this->display('amui/tows/income.html');
    }
    //分红收益转余额
    function toBalance(){
        $uid = $this->uid;        
        $act = trim($this->spArgs("act")); 
        $pan_member_income = spClass("pan_member_income");
        if($act=='sub'){
            $ip = get_client_ip();
            $d_pass = $this->spArgs("d_pass"); //安全码
            if ($d_pass == ""){
		json_s(array('status'=>201,'msg'=>'请输入安全码','ids'=>'#d_pass'));
            }
			//------------限制相同IP并发请求----------begin
			$ip_key = md5($ip);
			$key_name = 'toBalance_ip_'.$ip_key;
			if(false === cache_a($key_name,time(),1))json_s(array('status'=>205,'msg'=>'很抱歉，系统繁忙请稍后1秒后重试!'));	
			//------------限制相同IP并发请求----------end
			
			//------------限制帐号请求次数----------begin
			$key_name = 'toBalance_safeCode_uid_'.$uid;
			if(cache_s($key_name)>20)json_s(array('status'=>205,'msg'=>'很抱歉，请求次数限制，请稍后1小时后再操作'));				
			//------------限制帐号请求次数----------end							
			$d_pass = md5(md5($d_pass . web_md5));
			//首先判断安全码是否正确
			$pan_user_safecode = spClass("pan_user_safecode");
			$pan_user_safecode_r = $pan_user_safecode->find(array("uid" => $uid));
			$p_pass = $pan_user_safecode_r['safecode'];
			if (!$p_pass) {
				json_s(array('status'=>203,'msg'=>'安全码未设置，请先设置','ids'=>'#d_pass'));
			}			
			if ($p_pass !== $d_pass) {
				cache_s($key_name,intval(cache_s($key_name))+1,3600);//输入错误的安全码缓存+1
				json_s(array('status'=>201,'msg'=>'安全码不正确','ids'=>'#d_pass'));
			}
			cache_s($key_name,0,3600);//输入正确的安全码重置为0
            
        }
        $r = $pan_member_income->find(array("uid" => $uid));
        if($r){
            $balance = $r['balance'];
        }else{
            $balance = 0;
        }
        $this->balance = $balance;
        $this->maxDraw = floor($balance*100)/100;
        $this->module = "tows";
        $this->act = 'tows_toBalance';
        $this->display('amui/tows/toBalance.html'); 
    } 
}
?>