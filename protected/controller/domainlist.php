<?php
define("web_md5", "_chaomi_cc");
class domainlist extends spController {
    function __construct() { 
        parent::__construct(); 
		$sso_user = check();
		$this->uid = $sso_user['uid'];
		$this->mid = $sso_user['mid'];				
    }
    //域名详细列表
    function index() {
		d404();
        $from = trim($this->spArgs('from'));
        $page = intval($this->spArgs('page', 1));
        if($page <1) $page=1;

        //排序方式
        $orderField = $this->spArgs('order', 'a.apply_time desc');
        $sort = " ORDER BY $orderField ";
        //排序结束

        //查询条件
        $pan_domain_in = spClass('pan_domain_in');
        $condition = " where 1=1 ";
        $cond=array('domain'=>"",'typeid'=>'','expire'=>'','registrar'=>'','status'=>-1);
        //域名**********模糊查询************
        if(false !=$this->spArgs('domain')){
            $domain=$pan_domain_in->escape($this->spArgs('domain'));
            $condition.=" and a.domain like '%".trim($domain,"'")."%' ";
            $cond['domain']=trim($domain,"'");
        }
        //域名品种
        if(false !=$this->spArgs('typeid')){
            $typeid=intval($this->spArgs('typeid'));
            $condition.=" and a.typeid=".$typeid." ";
            $cond['typeid']=trim($typeid,"'");
        }

        //传递到页面的查询条件
        $this->cond=$cond;
        //查询结束
        $sql = "select a.*,c.name from cmpai.pan_domain_in a "
            . " left join cmpai.new_ym_code c on a.typeid=c.code "
            . $condition . $sort ;
        $ret = $pan_domain_in->spPager($page,20)->findSql($sql);
		// var_dump($ret);
		// $domain_list = $ret;
		$domain_list = array();
		$pub_user = spClass('pub_user');
		foreach ($ret as $k=>$v) {
			// $pub_user_r = $pub_user->find(array('uid' => $v['uid']));
			// $v['mid'] = $pub_user_r['mid'];
			if($v['apply_time'])$v['apply_time'] = date("Y-m-d",strtotime($v['apply_time']));
			$domain_list[] = $v;
		}
		
        //分页参数
        $pager=$pan_domain_in->spPager()->getPager();
        if ($pager['total_page'] > 5) {
            if ($page > 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], $page - 3, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            }
        }
        $this->pager = $pager;
        //分页结束
		if($from=='api'){
			json_s(array('count'=>(int)$pager['total_count']));
		}
        //----------------选择框---------------------\\
        //-----------**品种**----------------------\\
        $dlist = "select code as id,name from cmpai.new_ym_code where state=1";
        $types = spClass('pan_domain_types')->findSql($dlist);
        //------------**注册商**-------------------\\
		$websites = array('1'=>'易名中国','2'=>'爱名网','3'=>'190数交所','4'=>'万网','5'=>'西部数码','6'=>'易域网','7'=>'优名网');
		
		//取当前用户，正在提交转入中的域名总数	
        $pan_domain_zip_sh = spClass('pan_domain_zip_sh');
		$apply_count = array();
		$cond_f = '';
		if($typeid>1)$cond_f = " and typeid='{$typeid}'";
        $apply_ret = $pan_domain_zip_sh->findSql("select sum(counts) as num from cmpai.pan_domain_zip_sh where audit_status=1 $cond_f"); 
        $apply_count['status_1_count'] = $apply_ret[0]['num']; //待审核入库域名总数
        $apply_ret = $pan_domain_zip_sh->findSql("select sum(counts) as num from cmpai.pan_domain_zip_sh where audit_status=2 $cond_f and (TO_DAYS( NOW( ) ) - TO_DAYS( time )) =1"); //昨天
        $apply_count['zt_status_2_count'] = $apply_ret[0]['num']; //昨天成功入库数量

        $apply_ret = $pan_domain_zip_sh->findSql("select sum(counts) as num from cmpai.pan_domain_zip_sh where audit_status=2 $cond_f and to_days(time) = to_days(now()) "); //今天
        $apply_count['status_2_count'] = $apply_ret[0]['num']; //今天成功入库数量
		$time_ = date("Y-m-d",strtotime("-2 day"));
        $apply_ret = $pan_domain_zip_sh->findSql("select count(*) as num from cmpai.pan_domain_outplat where status=5 $cond_f and deal_time>='{$time_}'"); 
        $apply_count['out_status_5_count'] = $apply_ret[0]['num']; //近三日出库数量
		
        $this->apply_count=$apply_count;
        $this->websites=$websites;
        $this->types = $types;
        $this->module = "domainlist";
        $this->act = "domainlist";
        $this->cm_nav = "domainlist";
        $this->ret = $domain_list;
        $this->display('amui/index/domainlist.html');
    }

 
}