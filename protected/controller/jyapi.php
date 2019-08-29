<?php
// error_reporting(E_ALL || ~E_NOTICE);
class jyapi extends spController
{
    function __construct(){
        parent::__construct();
        spClass("trades");
		$sso_user = check();
        if ($sso_user == true) {
            $this->uid = $sso_user['uid'];
            $this->mid = $sso_user['mid'];
            $this->bRate=(bRate*100).'%';
            $this->sRate=(sRate*100).'%';
        }
    }	
    function index(){ //首页
		// dump(get_client_ip());
		//------最近成交记录直接输出-----begin
		// $deal_data = new_deal_trade(0,$typeid?$typeid:0,50);
		//------最近成交记录直接输出-----end
		
		//------公告-----begin
        $announce_ret = spClass('pub_announce')->find(array('id'=>49));
		unset($announce_ret['content']);
		unset($announce_ret['introduction']);
        $announce_ret['update_time'] = date("Y/m/d",strtotime($announce_ret['update_time']));	
		$this->announce_ret=$announce_ret;
		//------公告-----end
		
        // $this->hq_list=$hq_list;		
        // $this->deal_data=$deal_data;
		$this->hq_data = cache_s('cm_typeid_411104_hq');//品种行情相关参数值缓存数据
        $this->cm_nav ='jy';
		$this->typeid_price = typeid_price(411104);
		$this->typeid ='411104';
		$this->display("amui/index/my_index_new_api.html");
    }
}
