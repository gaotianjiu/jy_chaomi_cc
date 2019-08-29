<?php
// error_reporting(E_ALL || ~E_NOTICE);
class test extends spController
{
    function __construct(){
        parent::__construct();
		$sso_user = check();
        if ($sso_user == true) {
            $this->uid = $sso_user['uid'];
            $this->mid = $sso_user['mid'];
            $this->bRate=(bRate*100).'%';
            $this->sRate=(sRate*100).'%';
        }
    }	
    function index(){ //首页
		$data[] = 'all';
		$data = get_type_list();
		var_dump($data);
    }
}
