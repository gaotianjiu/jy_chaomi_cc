<?php
/*
 公告模块
 */
class announceController extends BaseController{
    function __construct(){ 
        parent::__construct();
		$sso_user = check();
        if ($sso_user == true) {
            $this->uid = $sso_user['uid'];
            $this->mid = $sso_user['mid'];
        }else{
            $this->uid = 0;
            $this->mid = 0;
        }
    }
    /**
     * 公告列表
     */
    function actionindex(){
		$page = intval($this->spArgs('page', 1));
		if($page<=0)$page=1;
        //排序方式
        $sort = " ORDER BY id desc";
        $pub_announce = spClass('pub_announce');
        $sql = "select * from share_user.pub_announce where status=0" . $sort;
        
        //$ret = $pub_announce->spPager($page, pgsize)->query($sql);
	//分页开始
        //$pager = $pub_announce->spPager()->getPager();
        $ret = $pub_announce->query($sql);
        $pager = $pub_announce->pager($page, 20, 10, 100);
        
        if ($pager['total_page'] > 5) {
            if ($page <= 3) {
                $pager['all_pages'] = array_slice($pager['all_pages'], 0, 5);
            } else {
                $pager['all_pages'] = array_slice($pager['all_pages'], $page - 3, 5);
            }
        }
        $this->pager = $pager;
        //分页结束		
        $this->ret = $ret;
		// var_dump($ret);
        $this->cm_nav = 'announce';
        $this->display('amui/index/announce.html');
    }
    function actionview(){
        
	$id = intval($this->spArgs('id')); 
        $pub_announce = spClass('pub_announce');
        $ret = $pub_announce->find(array('id'=>$id,'status'=>0));
		if(!$ret)d404();
		$ret['content'] = preg_replace('/\/upload_chaomi_static\//','http://cdn-file.chaomi.cc/',$ret['content']);
		$pub_announce->incr(array('id'=>$id), 'hits');
        $this->ret = $ret;
        $this->cm_nav = 'announce';
        $this->display('amui/index/announce_view.html');
    }	
}
?>