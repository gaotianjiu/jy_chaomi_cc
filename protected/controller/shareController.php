<?php
/*
 * 推广分享模块
 *
 */
class shareController extends BaseController{
    function __construct(){ 
        parent::__construct();
		$sso_user = check();
        if ($sso_user == true) {
            $this->uid = $sso_user['uid'];
            $this->mid = $sso_user['mid'];
            check_code();
        } else {
            re_login();
            exit();
        }
    }
    function actionindex(){
		//搬砖证列表
		$uid = $this->uid;
		$page = intval($this->spArgs('page', 1));
		if($page<=0)$page=1;		
		$pub_user = spClass('pub_user');
		$pub_ecommend = spClass('pub_ecommend');
		$pub_ecommend_log = spClass('pub_ecommend_log');
		$ret = $pub_ecommend->findAll(array('ecommend_uid'=>$uid),"create_time desc","*",array($page,pgsize,10));
		//分页开始
                $pager = $pub_ecommend->page;
        if ($pager['total_page'] > 5) {
            if ($page <= 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], $page - 3, 5);
            }
        }
        $this->pager = $pager;		
		$ret_ = array();
		foreach($ret as $v) {
			$r = $pub_user->find(array('uid'=>$v['reg_uid']));
			$v['reg_mid'] = $r['mid'];
			$k = $pub_ecommend_log->findSql("select sum(revenue_sharing) as num from share_user.pub_ecommend_log where reg_uid = ".$v['reg_uid']);
			$v['revenue_sharing'] = $k[0]['num']?$k[0]['num']:0;
			$ret_[] = $v;
		}		
		$eco_count = $pub_ecommend->findcount(array('ecommend_uid'=>$uid));
        $this->ret = $ret_;
        $this->module = "share";
        $this->act = 'share_index';
        $this->eco_count = $eco_count;
        $this->display('amui/member/am_sahre_index.html');		
    }
}
?>