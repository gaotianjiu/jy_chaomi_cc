<?php

class trades extends Model {
    
}

//日总成交概况
function day_trade() {
    $data = array();
    $now = date("Y-m-d") . " 00:00:00";
    //日总成交量，日总成交额
    $sql = "select sum(tot_price),sum(deal_num) from cmpai.pan_deal_trade where sta=0 and deal_time >'{$now}'";
    //日各品种成交额
    $psql = "select sum(tot_price),b.name,a.typeid from cmpai.pan_deal_trade a left join new_ym_code b on a.typeid=b.code where a.sta=0 and a.deal_time >'{$now}' group by typeid";
    $gb = new pan_deal_trade();
    $res = $gb->spCache(60)->findSql($sql);
    $data['dayp'] = nullnum($res[0]['sum(tot_price)']);  //日总成交额
    $data['dayn'] = nullnum($res[0]['sum(deal_num)']);   //日总成交量
    return $data;
}

//最新成交域名价格--个数版---20180417
function new_deal_trade_count($typeid = 0, $limit = 50) {
    //指定特定品种
    $keyid = 'all';
    $condition = '1=1';
    if ($typeid > 0) {
        $condition .= " and typeid =$typeid";
        $keyid = $typeid;
    }
    $cache_key = 'new_deal_trade_count_data_typeid_' . $keyid;
    $cache_data = cache_s($cache_key);
    if ($cache_data) {
        return $cache_data; //有缓存先取缓存数据
    }
    $pan = new pan_deal_trade_api();
    $sql = "select * from cmpai.pan_deal_trade_api where $condition order by deal_time desc limit $limit";
    $data = $pan->findSql($sql);
    $_data = array();
    foreach ($data as $r) {
        $_n = check_pz($r['typeid']);
        $r['name'] = $_n[0]['name'];
        $_data[] = $r;
    }
    cache_s($cache_key, $_data, 300); //将成交数据写入缓存->清空缓存由后台控制
    return $_data;
}

//最新成交域名价格
function new_deal_trade($id = 0, $typeid = 0, $limit = 30) {
    //指定排序
    $sort = '';
    $condition = '';
    if ($id > 0) {  //单desc  双asc  1成交时间 3数目  5单价  7总价
        switch ($id) {
            case 1:
                $sort = "order by deal_time desc";
                $bargain_time_id = 2;
                break;
            case 2:
                $sort = "order by deal_time";
                $bargain_time_id = 1;
                break;
            case 3:
                $sort = "order by deal_num desc";
                $number_id = 4;
                break;
            case 4:
                $sort = "order by deal_num";
                $number_id = 3;
                break;
            case 5:
                $sort = "order by deal_price desc";
                $price_id = 6;
                break;
            case 6:
                $sort = "order by deal_price";
                $price_id = 5;
                break;
            default:
                break;
        }
    }
    //指定特定品种
    $keyid = 'all';
    if ($typeid > 0) {
        $condition = " and typeid =$typeid";
        $keyid = $typeid;
    }
    $cache_key = 'new_deal_trade_data_typeid_' . $keyid;
    $cache_data = cache_s($cache_key);
    if ($cache_data) {
        return $cache_data; //有缓存先取缓存数据
    }
    $pan = new pan_deal_trade();
    // $sql="select a.name,b.deal_price,b.deal_time,b.domain,b.deal_status from cmpai.new_ym_code a,cmpai.pan_deal_domain b where a.code=b.typeid and b.status=1 $condition order by b.deal_time desc limit $limit";
    $sql = "select tid,typeid,deal_price,deal_time,domain,deal_status,deal_tid from cmpai.pan_deal_domain where status=1 $condition and deal_time>'2018-04-27 13:00:00' order by deal_time desc limit $limit";
    // if($sort!=''){
    // $sql="select * from ($sql)t $sort";
    // }
    $data = $pan->query($sql);
    $_data = array();
    foreach ($data as $r) {
        $_n = check_pz($r['typeid']);
        $r['name'] = $_n[0]['name'];
        // if($r['typeid']>800000){
        // $r['domain'] = substr_replace($r['domain'],'**','1','2');
        // }else{
        // $r['domain'] = substr_replace($r['domain'],'*','0','1');
        // }
        $r['zb'] = '-';
        if ($r['deal_time'] >= '2018-05-22 16:00:00') {
            if ($r['deal_status'] == 1) {
                $gb = new pan_trade();
                $a = $gb->find(array('id' => $r['tid']));
                if ($a['zhibao'] == 0) {
                    $zb = '<1个月';
                } else {
                    $zb = '≥' . $a['zhibao'] . '个月';
                }
            }
            if ($r['deal_status'] == 2) {
                $gb = new pan_trade();
                $a = $gb->find(array('id' => $r['deal_tid']));
                if ($a['zhibao'] == 0) {
                    $zb = '不限质保';
                } else {
                    $zb = '≥' . $a['zhibao'] . '个月';
                }
            }
            $r['zb'] = $zb;
        }
        // if($r['typeid']==208001){
        // $r['domain'] = '****.com.cn';
        // }
        $_data[] = $r;
    }
    cache_s($cache_key, $_data, 7200); //将成交数据写入缓存->清空缓存由后台控制
    return $_data;
}

/**
 * find_buy_sale 单个品种买卖价格查询
 * @access  public
 * @params  $typeid  域名类型+后缀
 * @params  $bs  0卖，1买
 * return  array
 */
function find_buy_sale($typeid, $bs, $limit = 10, $pingtai, $zhibao) {
    if (!check_pz($typeid))
        return array(); //判断品种是否存在
    $price = array();
    $number = array();
    $now = date("Y-m-d H:i:s");
    $sp = new pan_trade();
    //查询目前5个最低价
    //查询目前5个最低卖价
    $find_sql = " and 1=1";
    if ($pingtai > 0) {
        $find_sql .= " and FIND_IN_SET($pingtai,pingtai)";
    }
    if ($zhibao >= 1) {
        if ($bs == 1) {
            $find_sql .= " and (zhibao<=$zhibao or zhibao=0)"; //如果是买单，需要无论任何质保要求，都加上 不限
        } else {
            $find_sql .= " and zhibao>=$zhibao";
        }
    }
    if ($bs == 0) {
        $sql = "select distinct price from cmpai.pan_trade where status_1=0 AND status_2=0 AND typeid = $typeid and expire_time >'{$now}' $find_sql order by price asc limit $limit";
    } else if ($bs == 1) { //取得当前5个最高买价
        $sql = "select distinct price from cmpai.pan_trade where status_1=0 AND status_2=1 AND typeid = $typeid and expire_time >'{$now}' $find_sql order by price desc limit $limit";
    }
    $price_res = $sp->findSql($sql); //print_r($price_res);
    // var_dump($price_res);
    $zhibao = $number = $price = $pingtai = $price_c = array();
    $k = 0;
    if (!empty($price_res)) {
        foreach ($price_res as $c => $v) {
            $sql = "select id,number,deal_num,sum(number-deal_num) as num,price,typeid,pingtai,zhibao from cmpai.pan_trade where status_1=0 AND status_2=$bs AND typeid = $typeid and price=" . $v['price'] . " and expire_time >='{$now}' $find_sql group by pingtai,zhibao limit 1000";
            $ret = $sp->findSql($sql);
            foreach ($ret as $a => $g) {
                $price[$k] = $v['price'];
                $number[$k] = $g['num'];
                $price_c[$k] = $price[$k] * $number[$k];
                $pt = $zb = '-';
                //处理平台
                if ($g['pingtai'] == '1') {
                    $pt = '易名';
                } elseif ($g['pingtai'] == '2') {
                    $pt = '爱名';
                } elseif ($g['pingtai'] == '3') {
                    $pt = '阿里';
                } elseif ($g['pingtai'] == '1,2') {
                    $pt = '易名 爱名';
                } elseif ($g['pingtai'] == '1,3') {
                    $pt = '易名 阿里';
                } elseif ($g['pingtai'] == '2,3') {
                    $pt = '爱名 阿里';
                } else {
                    $pt = '不限平台';
                }
                //处理质保时间
                if ($g['zhibao'] == 0) {
                    $zb = '不限质保';
                    if ($bs == 0) {
                        $zb = '<1个月';
                    }
                } else {
                    $zhibao_tmp = $g['zhibao'];
                    $zb = '≥' . $zhibao_tmp . '个月';
                }
                $pingtai[$k] = $pt;
                $zhibao[$k] = $zb;
                $k++;
            }
        }
    }
    // var_dump($zhibao);
    if ($bs == 0) {
        $item = array('卖1', '卖2', '卖3', '卖4', '卖5', '卖6', '卖7', '卖8', '卖9', '卖10');
    } else {
        $item = array('买1', '买2', '买3', '买4', '买5', '买6', '买7', '买8', '买9', '买10');
    }
    $running_data = array();
    for ($i = 0; $i < $limit; $i++) {
        $running_data[$item[$i]] = array(
            // 'item'=>$item[$i],
            'number' => $number[$i],
            'price' => $price[$i],
            'price_c' => $price_c[$i],
            'pingtai' => $pingtai[$i],
            'zhibao' => $zhibao[$i]
        );
    }
    if ($bs == 0) {
        krsort($running_data);
    }
    return $running_data;
}

/**
 * find_buy_sale 单个品种买卖价格查询
 * @access  public
 * @params  $typeid  域名类型+后缀
 * @params  $bs  0卖，1买
 * return  array
 */
function find_buy_sale_all($typeid, $bs, $limit = 10, $pingtai, $zhibao) {
    if (!check_pz($typeid))
        return array(); //判断品种是否存在
    $price = array();
    $number = array();
    $now = date("Y-m-d H:i:s");
    $sp = new pan_trade();
    //查询目前5个最低价
    //查询目前5个最低卖价
    $find_sql = " and 1=1";
    if ($pingtai > 0) {
        $find_sql .= " and FIND_IN_SET($pingtai,pingtai)";
    }
    if ($zhibao >= 1) {
        if ($bs == 1) {
            $find_sql .= " and (zhibao>=$zhibao or zhibao=0)"; //如果是买单，需要无论任何质保要求，都加上 不限
        } else {
            $find_sql .= " and zhibao>=$zhibao";
        }
    }
    if ($bs == 0) {
        $sql = "select distinct price from cmpai.pan_trade where status_1=0 AND status_2=0 AND typeid = $typeid and expire_time >'{$now}' $find_sql order by price asc limit $limit";
    } else if ($bs == 1) { //取得当前5个最高买价
        $sql = "select distinct price from cmpai.pan_trade where status_1=0 AND status_2=1 AND typeid = $typeid and expire_time >'{$now}' $find_sql order by price desc limit $limit";
    }
    $price_res = $sp->findSql($sql); //print_r($price_res);
    // var_dump($price_res);
    $zhibao = $number = $price = $pingtai = $price_c = array();
    $k = 0;
    if (!empty($price_res)) {
        foreach ($price_res as $c => $v) {
            $sql = "select id,number,deal_num,sum(number-deal_num) as num,price,typeid,pingtai,zhibao from cmpai.pan_trade where status_1=0 AND status_2=$bs AND typeid = $typeid and price=" . $v['price'] . " and expire_time >='{$now}' $find_sql group by pingtai,zhibao limit 1000";
            $ret = $sp->findSql($sql);
            foreach ($ret as $a => $g) {
                $price[$k] = $v['price'];
                $number[$k] = $g['num'];
                $price_c[$k] = $price[$k] * $number[$k];
                $pt = $zb = '-';
                //处理平台
                if ($g['pingtai'] == '1') {
                    $pt = '易名';
                } elseif ($g['pingtai'] == '2') {
                    $pt = '爱名';
                } elseif ($g['pingtai'] == '3') {
                    $pt = '阿里';
                } elseif ($g['pingtai'] == '1,2') {
                    $pt = '易名 爱名';
                } elseif ($g['pingtai'] == '1,3') {
                    $pt = '易名 阿里';
                } elseif ($g['pingtai'] == '2,3') {
                    $pt = '爱名 阿里';
                } else {
                    $pt = '不限平台';
                }
                //处理质保时间
                if ($g['zhibao'] == 0) {
                    $zb = '不限质保';
                    if ($bs == 0) {
                        $zb = '<1个月';
                    }
                } else {
                    $zhibao_tmp = $g['zhibao'];
                    $zb = '≥' . $zhibao_tmp . '个月';
                }
                $pingtai[$k] = $pt;
                $zhibao[$k] = $zb;
                $k++;
            }
        }
    }
    // var_dump($zhibao);
    if ($bs == 0) {
        $item = array('卖1', '卖2', '卖3', '卖4', '卖5', '卖6', '卖7', '卖8', '卖9', '卖10');
    } else {
        $item = array('买1', '买2', '买3', '买4', '买5', '买6', '买7', '买8', '买9', '买10');
    }
    $running_data = array();
    for ($i = 0; $i < $limit; $i++) {
        if ($price[$i] > 0) {
            $running_data[$i + 1] = array(
                // 'item'=>$item[$i],
                'number' => $number[$i],
                'price' => $price[$i],
                'price_c' => $price_c[$i],
                'pingtai' => $pingtai[$i],
                'zhibao' => $zhibao[$i]
            );
        }
    }
    if ($bs == 0) {
        krsort($running_data);
    }
    return $running_data;
}

//最近成交价格
function new_trade($id = 0, $typeid = 0, $limit = 30) {
    //指定排序
    $sort = '';
    if ($id > 0) {  //单desc  双asc  1成交时间 3数目  5单价  7总价
        switch ($id) {
            case 1:
                $sort = "order by deal_time desc";
                $bargain_time_id = 2;
                break;
            case 2:
                $sort = "order by deal_time";
                $bargain_time_id = 1;
                break;
            case 3:
                $sort = "order by deal_num desc";
                $number_id = 4;
                break;
            case 4:
                $sort = "order by deal_num";
                $number_id = 3;
                break;
            case 5:
                $sort = "order by deal_price desc";
                $price_id = 6;
                break;
            case 6:
                $sort = "order by deal_price";
                $price_id = 5;
                break;
            case 7:
                $sort = "order by tot_price desc";
                $total_price_id = 8;
                break;
            case 8:
                $sort = "order by tot_price ";
                $total_price_id = 7;
                break;
            default:
                break;
        }
    }

    //指定特定品种
    if ($typeid > 0) {
        $condition = " and b.typeid =$typeid";
    }
    $pan = new pan_deal_trade();
    $sql = "select a.name,b.deal_num,b.deal_price,b.tot_price,b.deal_time from cmpai.new_ym_code a,cmpai.pan_deal_trade b where a.code=b.typeid and b.sta=0 $condition order by b.deal_time desc limit $limit";
    if ($sort != '') {
        $sql = "select * from ($sql)t $sort";
    }
    $reg = $pan->findSql($sql);
    return $reg;
}

function nullnum($a) {
    return empty($a) ? 0 : $a;
}

function date_0($num) {
    if ($num <= 9) {
        $num = '0' . intval($num);
    }
    return $num;
}
