<?php
class yk extends spController {

    function __construct() { // 公用
        parent::__construct(); // 这是必须的
		$sso_user = check();
        if ($sso_user == true) {
            $this->uid = $sso_user['uid'];
            $this->mid = $sso_user['mid'];
        } else {
            re_login();
            exit();
        }
    }

    function index() {//盈亏管理
        $uid = $this->uid;
        $cid = intval($this->spArgs("cid"));
        $hid = intval($this->spArgs("hid"));


        $r = yk_list($uid);
        $datas_c = $r['list'];
        $datas = $datas_c['data'];
        $lists = $datas_c['list'];
        if ($datas['dr_yk'] > 0) {
            $datas['dr_yk'] = "<span class='font-red'>+" . get_money_2($datas['dr_yk']) . "</span>";
        } else if ($datas['dr_yk'] == 0) {
            $datas['dr_yk'] = "--";
        } else {
            $datas['dr_yk'] = "<span class='font-black'>" . get_money_2($datas['dr_yk']) . "</span>";
        }
        //--

        if ($datas['lj_yk'] > 0) {
            $datas['yk_bl'] = "<span class='font-red'>+" . round($datas['lj_yk'] / $datas['z_cb'] * 100, 2) . "%</span>";
        } else if ($datas['yk_bl_int'] == 0) {
            $datas['yk_bl'] = "--";
        } else {
            $datas['yk_bl'] = "<span class='font-black'>" . round($datas['lj_yk'] / $datas['z_cb'] * 100, 2) . "%</span>";
        }
        //--\\
        if ($datas['lj_yk'] > 0) {
            $datas['lj_yk'] = "<span class='font-red'>+" . get_money_2($datas['lj_yk']) . "</span>";
        } else if ($datas['lj_yk'] == 0) {
            $datas['lj_yk'] = "--";
        } else {
            $datas['lj_yk'] = "<span class='font-black'>" . get_money_2($datas['lj_yk']) . "</span>";
        }

        if ($datas['z_cb'] > 0) {
            $datas['z_cb'] = get_money_2($datas['z_cb']);
        } else {
            $datas['z_cb'] = '--';
        }

        if ($datas['dq_sz'] > 0) {
            $datas['dq_sz'] = get_money_2($datas['dq_sz']);
        } else {
            $datas['dq_sz'] = "--";
        }
        if ($datas['count'] > 0) {
            $datas['count'] = number_format($datas['count']);
        } else {
            $datas['count'] = '--';
        }
        foreach ($lists as $r) {
            if ($r['dr_yk'] > 0) {
                $r['dr_yk'] = "<span class='am-badge  am-badge-warning'>+" . get_money_2($r['dr_yk']) . "</span>";
            } else if ($r['dr_yk'] == 0) {
                $r['dr_yk'] = "--";
            } else {
                $r['dr_yk'] = "<span class='am-badge '>" . get_money_2($r['dr_yk']) . "</span>";
            }

            if ($r['lj_yk'] > 0) {
                $r['lj_yk'] = "<span class='am-badge  am-badge-warning'>+" . get_money_2($r['lj_yk']) . "</span>";
            } else if ($r['lj_yk'] == 0) {
                $r['lj_yk'] = "--";
            } else {
                $r['lj_yk'] = "<span class='am-badge'>" . get_money_2($r['lj_yk']) . "</span>";
            }

            if ($r['yk_bl_int'] > 0) {
                $r['yk_bl'] = "<span class='am-badge  am-badge-warning'>" . $r['yk_bl'] . "</span>";
            } else if ($r['yk_bl_int'] == 0) {
                $r['yk_bl'] = "--";
            } else {
                $r['yk_bl'] = "<span class='am-badge'>" . $r['yk_bl'] . "</span>";
            }
            $r['dq_sz'] = get_money_2($r['dq_sz']);
            if ($r['ym_count'] > 0)
                $list[] = $r;
        }
        $this->datas = $datas;
        $this->lists = $list;
        $this->module = "tools";
        $this->act = "yk";
        //$this->display("member/yk.html");
        $this->display('amui/member/am_yk_index.html');
    }

    function d() {
        //盈亏列表
        $uid = $this->uid;
        $act = intval($this->spArgs("act"));
        $cid = intval($this->spArgs("cid"));
        $hid = intval($this->spArgs("hid"));

        $this->catname = get_cid($cid) . get_hid($hid); //品种名字
        $this->cid = $cid;
        $this->hid = $hid;
        $this->act = $act;
        $yk = yk_cid($uid, $cid, $hid);
        $this->yk_list = $yk['list'];
        $datas = $yk['data'];
        //--------判断 红 绿色---------//
        if ($datas['dr_yk'] > 0) {
            $datas['dr_yk'] = "<span class='font-red'>+" . get_money_2($datas['dr_yk']) . "</span>";
        } else if ($datas['dr_yk'] == 0) {
            $datas['dr_yk'] = "--";
        } else {
            $datas['dr_yk'] = "<span class='font-black'>" . get_money_2($datas['dr_yk']) . "</span>";
        }

        if ($datas['lj_yk'] > 0) {
            $datas['lj_yk'] = "<span class='font-red'>+" . get_money_2($datas['lj_yk']) . "</span>";
        } else if ($datas['lj_yk'] == 0) {
            $datas['lj_yk'] = "--";
        } else {
            $datas['lj_yk'] = "<span class='font-black'>" . get_money_2($datas['lj_yk']) . "</span>";
        }

        if ($datas['yk_bl_int'] > 0) {
            $datas['yk_bl'] = "<span class='font-red'>" . $datas['yk_bl'] . "</span>";
        } else if ($datas['yk_bl_int'] == 0) {
            $datas['yk_bl'] = "--";
        } else {
            $datas['yk_bl'] = "<span class='font-black'>" . $datas['yk_bl'] . "</span>";
        }

        if ($datas['dq_sz'] > 0) {
            $datas['dq_sz'] = get_money_2($datas['dq_sz']);
        } else {
            $datas['dq_sz'] = '--';
        }
        $datas['z_cb'] = get_money_2($datas['z_cb']);
        $datas['dg_cb'] = get_money_2($datas['dg_cb']);
        if ($datas['z_cb'] == -0) {
            $datas['z_cb'] = '--';
            $datas['dg_cb'] = '--';
            $datas['ym_count'] = '--';
        }
        $this->yk_data = $datas;
        $this->module = "tools";
        $this->act = "yk";
        //$this->display("member/yk_list.html");
        $this->display('amui/member/am_yk_list.html');
    }

    function type() {
        //添加关注盈亏品种操作
        $uid = $this->uid;
        // header("Content-type:text/json");
        $act = trim($this->spArgs("act"));
        $cid = intval($this->spArgs("cid"));
        $hid = intval($this->spArgs("hid"));
        $count = intval($this->spArgs("count"));
        $price = intval($this->spArgs("price"));
        if ($act == 'add' || $act == 'del' || $act == 'list' || $act == 'all' || $act == "index") {
            
        } else {
            exit_json(201, "操作类型错误");
        }

        if ($act == 'index') {//总体盈亏 首页调用
            $res = yk_list($uid);
            echo json_encode($res['index']);
            exit();
        }
        if ($act == 'all') {//总体盈亏
            $res = yk_list($uid);
            echo json_encode($res['list']);
            exit();
        }
        if ($cid == 0 || $hid == 0 || $cid > 16 || $hid > 12)
            exit_json(201, "此域名品种不存在");
        if ($cid <= 13 || ($cid > 13 && $hid <= 6)) {
            
        } else {
            exit_json(201, "此域名品种不存在");
        }
        if ($hid == 12) {
            if ($cid == 1 || $cid == 2 || $cid == 3 || $cid == 4 || $cid == 5 || $cid == 6 || $cid == 8 || $cid == 9 || $cid == 10) {
                
            } else {
                exit_json(201, "此域名品种不存在");
            }
        }
        if ($act == 'add') {
            $res = add($uid, $cid, $hid, $count, $price);
        }
        if ($act == 'del') {
            $res = del($uid, $cid, $hid, $count, $price);
        }
        if ($act == 'list') {
            $res = yk_cid($uid, $cid, $hid);
            echo json_encode($res);
            exit();
        }
        if ($act == 'clear') {//清空盈亏
            $res = clear($uid, $cid, $hid);
        }
        exit($res);
    }

}

function yk_list($uid) {
    $get = spClass('lib_member_yk'); //引入表
    $res = $get->findAll(array("uid" => $uid), "ym_count desc");
    $ljyk = $ykbl = $dryk = $dqsz = $zcb = $count = 0;
    foreach ($res as $r) {
        $s = yk_cid($uid, $r['cid'], $r['hid']);
        $s = $s['data'];
        //-------相关计算 头部显示---------//
        $ljyk = $ljyk + $s['lj_yk'];
        $ykbl = $ykbl + $s['yk_bl_int'];
        $dryk = $dryk + $s['dr_yk'];
        $dqsz = $dqsz + $s['dq_sz'];
        $zcb = $zcb + $s['z_cb'];
        $count = $count + $s['ym_count'];
        //-------相关计算 头部显示---------\\
        $k['name'] = get_cid($r['cid']) . get_hid($r['hid']); //品种名字
        // $k['code'] = get_code($r['cid'],$r['hid']);//品种代码
        $k['cid'] = $r['cid'];
        $k['hid'] = $r['hid'];
        $k['dg_cb'] = $s['dg_cb']; //单个成本
        $k['dq_price'] = $s['dq_price']; //最新价格

        $k['dr_yk'] = $s['dr_yk']; //当日盈亏
        $k['ym_count'] = $s['ym_count']; //持仓数量

        $k['lj_yk'] = $s['lj_yk']; //累计盈亏
        $k['yk_bl'] = $s['yk_bl']; //盈亏比率
        $k['yk_bl_int'] = $s['yk_bl_int']; //盈亏比率
        $k['dq_sz'] = $s['dq_sz']; //当前市值
        $json[] = $k;
        $res = $json;
    }
    $ykbl_int = $ykbl;
    if ($ykbl > 0)
        $ykbl = "+" . $ykbl;
    $ykbl = $ykbl . "%";
    $data = array("lj_yk" => $ljyk, "yk_bl" => $ykbl, "yk_bl_int" => $ykbl_int, "dr_yk" => $dryk, "dq_sz" => $dqsz, "z_cb" => $zcb, "count" => $count);
    $data_index = array("status" => 200, "dr_yk" => $dryk, "lj_yk" => $ljyk, "dq_sz" => $dqsz);
    $data_list = array("status" => 200, "data" => $data, "list" => $res);
    if ($dqsz == 0) {
        $data_index = array("status" => 201, "msg" => '无相关持仓记录信息');
        $data_list = array("status" => 201, "msg" => '无相关持仓记录信息');
    }
    return array("list" => $data_list, "index" => $data_index);
}

function yk_cid($uid, $cid, $hid) {//某品种详细盈亏
    $get = spClass('lib_member_yk'); //引入表
    $get_log = spClass('lib_member_yk_log'); //引入表
    $r = spClass("lib_hq")->find(array("cid" => $cid, "hid" => $hid));
    $zt_price = $r['zt_lowprice']; //昨天均价
    $dq_price = $r['price']; //当前价格
    $r = $get->find(array("uid" => $uid, "cid" => $cid, "hid" => $hid));
    // if(empty($r))exit_json(201,'无相关持仓记录信息');
    if (empty($r))
        return False;
    $ym_count = $r['ym_count']; //当前持仓数量
    $ztsz = $r['ym_count'] * $zt_price; //昨天总市值
    $dqsz = $r['ym_count'] * $dq_price; //当前总市值
    $dryk = $dqsz - $ztsz; //当日盈亏
    $dgcb = $r['ym_price']; //单个成本
    $zcb = $r['ym_count'] * $r['ym_price']; //总成本
    $ljyk = $dqsz - $zcb; //累计盈亏=总市值 - 总成本
    $ykbl = $dq_price - $dgcb; //盈亏比率 = （当前价格-单个成本）/单个成本*100%
    $ykbl_int = round(($ykbl / $dgcb) * 100, 2);
    $ykbl = round(($ykbl / $dgcb) * 100, 2);
    if ($ym_count <= 0)
        $ykbl_int = 0;
    if ($ykbl > 0)
        $ykbl = "+" . $ykbl;
    $ykbl = $ykbl . "%";
    //----------处理卖出或买入交易列表--------//
    $res = $get_log->findAll(array("uid" => $uid, "cid" => $cid, "hid" => $hid), "ctime desc", "", "100");
    foreach ($res as $r) {
        $k['ctime'] = date("Y-m-d H:i:s", $r['ctime']);
        if ($r['type'] == 1)
            $k['type'] = '买入';
        if ($r['type'] == 2)
            $k['type'] = '卖出';
        $k['count'] = intval($r['ym_count']);
        $k['price'] = intval($r['ym_price']);
        $json[] = $k;
        $res = $json;
    }
    //----------处理卖出或买入交易列表--------\\
    $name = get_cid($cid) . get_hid($hid);
    $data = array("name" => $name, "ym_count" => intval($ym_count), "dq_sz" => intval($dqsz), "dr_yk" => intval($dryk), "lj_yk" => intval($ljyk), "yk_bl" => $ykbl, "yk_bl_int" => $ykbl_int, "z_cb" => intval($zcb), "dg_cb" => intval($dgcb), "dq_price" => intval($dq_price));
    return array("status" => 200, "data" => $data, "list" => $res);
}

function add($uid, $cid, $hid, $count, $price) {//买入操作
    $get = spClass('lib_member_yk'); //引入表
    $get_log = spClass('lib_member_yk_log'); //引入表
    if ($count <= 0)
        return exit_json(201, "买入的域名数量不能小于或等于0个");
    if ($price <= 0)
        return exit_json(201, "买入的域名单价不能小于或等于0元");
    //--------取出已买入的数量------//
    $find = array("uid" => $uid, "cid" => $cid, "hid" => $hid); //查询是否存在
    $r = $get->find($find);
    $ym_count = $r['ym_count']; //当前持仓数量 未操作前
    $ym_price = $r['ym_price']; //当前持仓单个成本 未操作前
    //--------检测是否超过品种数量上限------//
    $cid_count = get_ym_count($cid); //当前品种总数量
    $new_count = $count + $ym_count; //新的持有数量
    if ($count > $cid_count)
        return exit_json(201, "买入的域名数量不能大于当前品种的总数量，该品种最多域名总数量为: $cid_count 个。");
    if ($new_count > $cid_count)
        return exit_json(201, "买入的域名数量加目前持有的域名数量不能大于当前品种的总数量，当前持有该品种域名数量为: $ym_count 个，该品种最多总数量为: $cid_count 个");
    //--------三，操作更新到到数库表------//
    //（现有数量*现在的单个成本+买入数量*买入单价）/(现有数量+买入数量) = 单个成本
    if (empty($r)) {// 如果已有记录即存在更新
        $yk = $get->create(array("uid" => $uid, "cid" => $cid, "hid" => $hid, "ym_count" => $new_count, "ym_price" => $price)); //盈亏主表
    } else {
        $new_price = ($ym_count * $ym_price + $count * $price) / ($ym_count + $count);
        $yk = $get->update(array("uid" => $uid, "cid" => $cid, "hid" => $hid), array("ym_count" => $new_count, "ym_price" => $new_price)); //盈亏主表
    }
    $yk_log = $get_log->create(array("uid" => $uid, "cid" => $cid, "hid" => $hid, "type" => 1, "ym_count" => $count, "ym_price" => $price, "ctime" => time(), "ip" => get_client_ip(), "pc_wap" => 2)); //盈亏副表
    if ($yk && $yk_log) {
        return exit_json(200, "添加买入成功，数量 $count 个，单价 $price 元。");
    } else {
        return exit_json(201, "添加买入错误，系统更新出错。");
    }
}

function del($uid, $cid, $hid, $count, $price) {//卖出操作
    $get = spClass('lib_member_yk'); //引入表
    $get_log = spClass('lib_member_yk_log'); //引入表
    if ($count <= 0)
        return exit_json(201, "卖出的域名数量不能小于或等于0个");
    if ($price <= 0)
        return exit_json(201, "卖出的域名单价不能小于或等于0元");
    //--------取出已买入的数量------//
    $find = array("uid" => $uid, "cid" => $cid, "hid" => $hid); //查询是否存在
    $r = $get->find($find);
    $ym_count = $r['ym_count']; //当前持有数量 未操作前
    if ($ym_count <= 0)
        return exit_json(201, "当前你未持有该品种域名，请先添加买入。");
    //--------检测是否超过当前品种持有域名数量------//
    if ($count > $ym_count)
        return exit_json(201, "卖出的域名数量不能大于持有数量，该品种域名你当前持有: $ym_count 个。");
    $new_count = $ym_count - $count; //新的持有数量
    //--------三，操作更新到到数库表------//
    //卖出时候更新剩余数量为(现有数量-卖出数量)就得了，单个成本不变
    if ($count == $ym_count) {//当卖出数量等于持有数量时，清空掉对应的主表和副表数据
        $d_z = $get->delete(array("uid" => $uid, "cid" => $cid, "hid" => $hid));
        $d_f = $get_log->delete(array("uid" => $uid, "cid" => $cid, "hid" => $hid));
        if ($d_z && $d_f) {
            return exit_json(200, "添加卖出成功，数量 $count 个，单价 $price 元，相关卖入卖出记录已清空。");
        }
    }
    $yk = $get->update(array("uid" => $uid, "cid" => $cid, "hid" => $hid), array("ym_count" => $new_count)); //盈亏主表
    $yk_log = $get_log->create(array("uid" => $uid, "cid" => $cid, "hid" => $hid, "type" => 2, "ym_count" => $count, "ym_price" => $price, "ctime" => time(), "ip" => get_client_ip(), "pc_wap" => 2)); //盈亏副表
    if ($yk && $yk_log) {
        return exit_json(200, "添加卖出成功，数量 $count 个，单价 $price 元。");
    } else {
        return exit_json(201, "添加卖出错误，系统更新出错。");
    }
}

function clear($uid, $cid, $hid) {//清空操作
    $get = spClass('lib_member_yk'); //引入表
    $get_log = spClass('lib_member_yk_log'); //引入表
    $find = array("uid" => $uid, "cid" => $cid, "hid" => $hid);
    $yk = $get->delete($find);
    $yk_log = $get_log->delete($find);
    if ($yk && $yk_log) {
        return exit_json(200, '品种相关盈亏信息已清空');
    } else {
        return exit_json(201, '品种相关盈亏信息清空失败，请稍候重试。');
    }
}

function exit_json($status, $msg) {//返回输出json数据
    echo json_encode(array("status" => $status, "msg" => $msg));
    exit();
}

function get_cid($id) {//获取域名类型 模板调用
    $id = intval($id);

    if ($id === 1) {

        return '四声母';
    } elseif ($id === 2) {

        return '五数字';
    } elseif ($id === 3) {

        return '三声母';
    } elseif ($id === 4) {

        return '四数字';
    } elseif ($id === 5) {

        return '三字母';
    } elseif ($id === 6) {

        return '五数字不带04';
    } elseif ($id === 8) {

        return '四数字不带04';
    } elseif ($id === 7) {

        return '三杂';
    } elseif ($id === 9) {

        return '三数字';
    } elseif ($id === 10) {

        return '三数字不带04';
    } elseif ($id === 11) {

        return '四字母';
    } elseif ($id === 12) {

        return '六数字';
    } elseif ($id === 13) {

        return '六数字不带04';
    } elseif ($id === 14) {

        return '五声母';
    } elseif ($id === 15) {

        return '七数字';
    } elseif ($id === 16) {

        return '八数字';
    }
}

function get_hid($id) {//获取后辍类型 模板调用
    $id = intval($id);

    if ($id === 1) {

        return 'COM';
    } elseif ($id === 2) {

        return 'CN';
    } elseif ($id === 3) {

        return 'COM.CN';
    } elseif ($id === 4) {

        return 'NET';
    } elseif ($id === 5) {

        return 'ORG';
    } elseif ($id === 6) {

        return 'CC';
    } elseif ($id === 7) {//以下20151207新增后辍
        return 'NET.CN';
    } elseif ($id === 8) {

        return 'BIZ';
    } elseif ($id === 9) {

        return 'WANG';
    } elseif ($id === 10) {

        return 'TOP';
    } elseif ($id === 11) {

        return 'ORG.CN';
    }
}

function get_money_2($money) {//转换以亿或万单位 两位 负数
    if ($money > 0) {

        return get_money_wy($money);
    } else {

        $money = $money - $money - $money; //转换成正数

        return '-' . get_money_wy($money);
    }
}

function get_money_wy($money) {//转换以亿或万单位 两位 负数
    if ($money >= 100000000) {

        return sprintf("%.2f亿", $money / 100000000);
    } else if ($money >= 10000) {

        return sprintf("%.2f万", $money / 10000);
    } else {

        return $money;
    }
}

function get_ym_count($cid) {//取某品种域名总数
    $data = array(1 => 160000, 2 => 100000, 3 => 8000, 4 => 10000, 5 => 17576, 6 => 32768, 7 => 28080, 8 => 4096, 9 => 1000, 10 => 512, 11 => 456976, 12 => 1000000, 13 => 262144, 14 => 3200000, 15 => 10000000, 16 => 100000000);

    $count = $data[$cid];

    return $count;
}
