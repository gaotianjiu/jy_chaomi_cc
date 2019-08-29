<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ym_team
 *
 * @author Administrator
 */
class ym_team extends spController {

    //put your code here
    function __construct() {
        parent::__construct();
        $sso_user = check();
        if ($sso_user == true) {
            $this->uid = $sso_user['uid'];
            $this->mid = $sso_user['mid'];
        } else {
            re_login();
            exit();
        }
        header("Content-type: text/html; charset=utf-8");
    }

    function team_list() {
        $lib_team_for_user = spClass("lib_team_for_user");
        $rs = $lib_team_for_user->findAll(array("uid" => $this->uid));
        $pan_domain_in = spClass('pan_domain_in');
        $this->module = "domainList";
        $this->act = "team_list";
        $this->status = 1;
        $this->team = $rs;
        $this->display('amui/member/am_team_list.html');
    }

    function team_add() {
        $this->display('amui/member/am_team_add.html');
    }

    function team_add_post() {
        $team_name = $this->spArgs("team_name");
        $lib_team_for_user = spClass('lib_team_for_user');
        $data = array('teamName' => $team_name, "uid" => $this->uid);
        $lib_team_for_user->create($data);
    }

    function team_del() {
        $id = $this->spArgs("id");
        $lib_team_for_user = spClass('lib_team_for_user');
        $rs = $lib_team_for_user->delete(array("id" => $id));
        echo json_encode($rs);
    }

    function set_teamToDomain() {
        $id = $this->spArgs('id');
        $team_id = $this->spArgs('team_id');
        $pan_domain_in = spClass('pan_domain_in');
        $rs = $pan_domain_in->update(array("id" => $id), array('team_id' => $team_id));
        $re[] = $ids;
        $re[] = $team_id;
        $re[] = $rs;
        echo json_encode($re);
    }

    function plfz() {
        $ids = $this->spArgs('ids');
        $team_id = $this->spArgs('team_id');
        $pan_domain_in = spClass('pan_domain_in');
        $rs = $pan_domain_in->update("id in (" . implode(',', $ids) . ')', array('team_id' => $team_id));
        $re[] = $ids;
        $re[] = $team_id;
        $re[] = $rs;
        echo json_encode($re);
    }

}
