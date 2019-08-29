<?php
// error_reporting(E_ALL || ~E_NOTICE);
class main extends spController
{
    function __construct(){
        parent::__construct();
        new trades();
		$sso_user = check();
        if ($sso_user == true) {
            $this->uid = $sso_user['uid'];
            $this->mid = $sso_user['mid'];
            $this->bRate=(bRate*100).'%';
            $this->sRate=(sRate*100).'%';
        }
    }	
    function actionindex(){ //首页
		//------行情列表-----begin
		$sp = new new_hq_list();
		//$hq_list = $sp->findAll("",'c_price desc,typeid');
		$hq_list = $sp->findAll("",'typeid asc');
		//------行情列表-----end
		
		//------公告-----begin
        $announce_ret = new pub_announce()->findAll("status=0","id desc");
		unset($announce_ret['content']);
		unset($announce_ret['introduction']);
        $announce_ret['update_time'] = date("Y/m/d",strtotime($announce_ret['update_time']));	
		$this->announce_ret=$announce_ret[0];
		//------公告-----end
		
        $this->hq_list=$hq_list;		

		$typeid = 8008;

        $this->cm_nav ='jy';

		if($this->uid==1){
			$this->display("amui/index/my_index_new_2018.html");
			exit;
		}
		$this->display("amui/index/my_index_new_2018.html");
		
    }
    function actionf(){ //单个品种页
        $typeid = intval($this->spArgs('typeid'));
        $types = check_pz($typeid);
        $pz = $types[0]['name']; //品种名称
		// var_dump($types);
		if(!$types)d404();
		//------公告-----begin
        $announce_ret = new pub_announce()->findAll("status=0","id desc");
		unset($announce_ret['content']);
		unset($announce_ret['introduction']);
        $announce_ret['update_time'] = date("Y/m/d",strtotime($announce_ret['update_time']));	
		$this->announce_ret=$announce_ret[0];
		//------公告-----end		
        $this->pz=$pz;
        $this->type_name=$pz;
        $this->typeid=$typeid;
        $this->hq_data = cache_s('cm_typeid_'.$typeid.'_hq');//品种行情相关参数值缓存数据
		$this->typeid_price = typeid_price($typeid);
        $this->module="detail";
		$this->cm_nav ='jy';
		if($typeid<=800000){
			$this->display('amui/index/my_index_new_twos.html'); //二级域名
			exit;
		}
        $this->display('amui/index/my_index_new.html');
    }
    function wt_price(){//单个品种右则委托价格输出
        $typeid = intval($this->spArgs('typeid'));
		$type = $this->spArgs('type');
		
        $pingtai = intval($this->spArgs('pingtai'));
        $zhibao = intval($this->spArgs('zhibao'));
		if(empty($typeid))json_s(array('status'=>200,'msg'=>'typeid不能为空'));
		if($type=='box'){
			$types = check_pz($typeid);
			$pz = $types[0]['name']; //品种名称			
			$this->type_name=$pz;
			$this->typeid=$typeid;		
			if($typeid<=800000){
				$this->display('amui/index/my_index_wt_list_twos.html'); //二级域名
				exit;
			}			
			$this->display('amui/index/my_index_wt_list.html');
			exit;
		}
		$hq_data = cache_s('cm_typeid_'.$typeid.'_hq');//品种行情相关参数值缓存数据
		// if($type=='all')json_s(array('status'=>200,'buy_list'=>find_buy_sale_all($typeid,1,100,0,0),'sale_list'=>find_buy_sale_all($typeid,0,100,0,0),'hq_list'=>$hq_data));
		if($type=='all')json_s(array('status'=>200,'buy_list'=>find_buy_sale_all($typeid,1,100,0,0),'sale_list'=>find_buy_sale_all($typeid,0,100,0,0),'hq_list'=>$hq_data));
		json_s(array('status'=>200,'buy_list'=>find_buy_sale($typeid,1,5,$pingtai,$zhibao),'sale_list'=>find_buy_sale($typeid,0,5,$pingtai,$zhibao),'hq_list'=>$hq_data));
    }
}
