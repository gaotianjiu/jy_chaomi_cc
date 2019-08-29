<?php

class helps extends spController
{
    function __construct(){
        parent::__construct();
		$sso_user = check();
        if ($sso_user == true) {
            $this->uid = $sso_user['uid'];
            $this->mid = $sso_user['mid'];
        }
    }
    function index(){
        $pan=spClass('new_type');
        $ids=$pan->findAll(array('parentid'=>0),null,id);
        $counts=count($ids);
        for($i=0;$i<$counts;$i++){
            $sql="select * from new_type WHERE id = ".$ids[$i]['id']." or parentid = ".$ids[$i]['id']." ";
            $tit=$pan->findSql($sql);
            $list[]=$tit;
        }
        $this->count=$counts;
        $this->list=$list;

        for($i=0;$i<$counts;$i++){
            if($ids[$i]['id'] !=123){
                $sql="select * from new_type WHERE id = ".$ids[$i]['id']." or parentid = ".$ids[$i]['id']." ";
                $tite=$pan->findSql($sql);
                $help[]=$tite;
            }

        }
       $this->help=$help;

        for($i=0;$i<$counts;$i++){
            $sql="select * from new_type WHERE parentid = ".$ids[$i]['id']." ";
            $tit=$pan->findSql($sql);
            for($j=0;$j<count($tit);$j++){
                $cpan=spClass('new_information');
                $sql="select * from new_information WHERE type = ".$tit[$j]['id']." ";
                $cont=$cpan->findSql($sql);
                $cont[0]['content']=str_replace("js/upload/information","http://8088.chaomi.cc/js/upload/information",$cont[0]['content']);
                $con[]=$cont;
            }
        }
      
        $this->helpCon=$con;
		$this->cm_nav ='helps';
        $this->display("amui/helpCenter/help.html");
    }

    function about(){
		$this->cm_nav ='helps_about';
        $this->display("amui/helpCenter/about.html");
    }

    function details__baoliu(){
        $pan=spClass('new_type');
        $cpan=spClass('new_information');

        $id=$this->spArgs('id',0);
        $parentid=$pan->find(array('id'=>$id),null,parentid);

        $sql="select * from new_information WHERE type = $id";
        $cont=$cpan->findSql($sql);
        $cont[0]['content']=str_replace("js/upload/information","http://8088.chaomi.cc/js/upload/information",$cont[0]['content']);
        $this->content=$cont;
 
        $this->act=$id;
        $this->module=$parentid['parentid'];
        //用于header头部
        $this->mmm='help';

        $ids=$pan->findAll(array('parentid'=>0),null,id);
        $counts=count($ids);
        for($i=0;$i<$counts;$i++){
            $sql="select * from new_type WHERE id = ".$ids[$i]['id']." or parentid = ".$ids[$i]['id']." ";
            $tit=$pan->findSql($sql);
            $list[]=$tit;
        }
        $this->count=$counts;
        $this->list=$list;
        $this->display("amui/helpCenter/help_details.html");
    }

}