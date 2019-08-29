<?php

class llpay_class extends Model {

    public $llpay_config;

    public function __construct() {
        //商户编号是商户在连连钱包支付平台上开设的商户号码，为18位数字，如：201306081000001016
        $this->llpay_config['oid_partner'] = '201607211000984227';

        //MD5安全检验码，以数字和字母组成的字符
        $this->llpay_config['key'] = 'YDohe12NezCbI9BQVVT0hPUCzdkIfR8qwYrDi5fPBf7HIz4gUtOtybI2M84naf2a';

        //秘钥格式注意不能修改（左对齐，右边有回车符）
        $this->llpay_config['RSA_PRIVATE_KEY'] = '-----BEGIN RSA PRIVATE KEY-----
MIICXgIBAAKBgQDsiYleZSU6GTmdwravVSUQ4jNDPcxf+ONb8wMDkyOwRrvLXd38
9W0GS9WjJuLWuWG+GLbVNkXsIWt4XB01a3VlCSwDLu/v+TMACpTbMdka+IMRHrhp
NRLGC4wmF9dfz9qsNnfxlEhyyCTrT3WT++ntNvQGo0eY/jjq0gNGpa3eEQIDAQAB
AoGBAI5fbp6xtMmWm1Z49+rrDgduj7p+fQCbV4Zl7D9/ljCpMqoDEvYKZ5gtlya/
5jOmm82HJK2LIGUz7HMixrX7u0lwBqJfE1GwLpvKcLaBmrhwmWwvuQcV452UoHi1
xThtNtellz133Lu6/6/AUlJs2V+9othG+YS/0KWVNTFsx1d9AkEA+paEdVzkHFVZ
cestrEgRXne8TIdPFsevoLiXxUkx8sGvLrIVCLhUrMuqSHR06VIy4nkMUz+mdjPY
Zwep5a/pdwJBAPGlVXcThLRf4VJN8ir+mmlJsu10J7IDpvRrzXdEp+S/S3z/WNk+
XN058VcMUVqXSGB+2/RzDjbdAHfhH6eAVrcCQQDyQKMLRuMr1QMkk2RTIlTQS9bY
8RJvhlpueSYYTtufwMjXzsdw56rMZhRX+WWwzotsz/MvY+BMc3CoogsIhtifAkAd
4m2caVyLFiR+kkc1zAD6rnOjbC8Qk+UM61wguOvunT3PuqCZVV4Uufq/4jUZeAYq
cOXw6F3EqTZKnRvWEkgnAkEAl2F52pxs6gZbAb5dcwrwo4iyxR5brtKBoPLPEXq0
e2bEIlvDgCYQCJ8AoiE0qI8RWd4DghYkaF3ZNhdWxyZI7A==
-----END RSA PRIVATE KEY-----';

        //连连支付公匙
        $this->llpay_config['LLPAY_RSA_PUBLIC_KEY'] = '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCSS/DiwdCf/aZsxxcacDnooGph3d2JOj5GXWi+
q3gznZauZjkNP8SKl3J2liP0O6rU/Y/29+IUe+GTMhMOFJuZm1htAtKiu5ekW0GlBMWxf4FPkYlQ
kPE0FtaoMP3gYfh+OwI+fIRrpW3ySn3mScnc6Z700nU/VYrRkfcSCbSnRwIDAQAB
-----END PUBLIC KEY-----';

        //版本号
        $this->llpay_config['version'] = '1.0';

        //防钓鱼ip 可不传或者传下滑线格式
        $this->llpay_config['userreq_ip'] = '10_10_246_110';

        //证件类型
        $this->llpay_config['id_type'] = '0';

        //签名方式 不需修改
        $this->llpay_config['sign_type'] = strtoupper('RSA');

        //订单有效时间  分钟为单位，默认为10080分钟（7天）
        $this->llpay_config['valid_order'] = "10080";

        //字符编码格式 目前支持 gbk 或 utf-8
        $this->llpay_config['input_charset'] = strtolower('utf-8');

        //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
        $this->llpay_config['transport'] = 'http';

        //服务器异步通知页面路径
        $this->llpay_config['notify_url'] = "http://my.chaomi.cc/llpay/notify_url.php";
        //需http://格式的完整路径，不能加?id=123这类自定义参数
        //页面跳转同步通知页面路径
        $this->llpay_config['return_url'] = "http://my.chaomi.cc/llpay/return_url.php";

        //连连支付提交URL
        $this->llpay_config['$llpay_gateway_new'] = 'https://cashier.lianlianpay.com/payment/bankgateway.htm';
        //$this->llpay_config['risk_item']="{\"user_info_mercht_userno\":\"\",\"user_info_dt_register\":\"\",\"frms_ware_category\":\"1002\",\"user_info_bind_phone\":\"13922833160\"}";
    }

    /**
     * 建立请求，以表单HTML形式构造（默认）
     * @param $para_temp 请求参数数组
     * @param $method 提交方式。两个值可选：post、get
     * @param $button_name 确认按钮显示文字
     * @return 提交表单HTML文本
     */
    function buildRequestForm($para_temp, $method, $button_name) {
        //待请求参数数组
        $para = $this->buildRequestPara($para_temp);
        //风控值去斜杠
        $para['risk_item'] = stripslashes($para['risk_item']);
        $sHtml = "<form id='llpaysubmit' name='llpaysubmit' action='" . $this->llpay_config['$llpay_gateway_new'] . "' method='" . $method . "'>";
        $sHtml .= "<input type='hidden' name='version' value='" . $para['version'] . "'/>";
        $sHtml .= "<input type='hidden' name='charset_name' value='" . $para['charset_name'] . "'/>";
        $sHtml .= "<input type='hidden' name='oid_partner' value='" . $para['oid_partner'] . "'/>";
        $sHtml .= "<input type='hidden' name='user_id' value='" . $para['user_id'] . "'/>";
        $sHtml .= "<input type='hidden' name='timestamp' value='" . $para['timestamp'] . "'/>";
        $sHtml .= "<input type='hidden' name='sign_type' value='" . $para['sign_type'] . "'/>";
        $sHtml .= "<input type='hidden' name='sign' value='" . $para['sign'] . "'/>";
        $sHtml .= "<input type='hidden' name='busi_partner' value='" . $para['busi_partner'] . "'/>";
        $sHtml .= "<input type='hidden' name='no_order' value='" . $para['no_order'] . "'/>";
        $sHtml .= "<input type='hidden' name='dt_order' value='" . $para['dt_order'] . "'/>";
        $sHtml .= "<input type='hidden' name='name_goods' value='" . $para['name_goods'] . "'/>";
        $sHtml .= "<input type='hidden' name='info_order' value='" . $para['info_order'] . "'/>";
        $sHtml .= "<input type='hidden' name='money_order' value='" . $para['money_order'] . "'/>";
        $sHtml .= "<input type='hidden' name='notify_url' value='" . $para['notify_url'] . "'/>";
        $sHtml .= "<input type='hidden' name='url_return' value='" . $para['url_return'] . "'/>";
        $sHtml .= "<input type='hidden' name='userreq_ip' value='" . $para['userreq_ip'] . "'/>";
        //$sHtml .= "<input type='hidden' name='url_order' value='" . $para['url_order'] . "'/>";
        $sHtml .= "<input type='hidden' name='valid_order' value='" . $para['valid_order'] . "'/>";
        //$sHtml .= "<input type='hidden' name='bank_code' value='" . $para['bank_code'] . "'/>";
        //$sHtml .= "<input type='hidden' name='pay_type' value='" . $para['pay_type'] . "'/>";
        //$sHtml .= "<input type='hidden' name='no_agree' value='" . $para['no_agree'] . "'/>";
        //$sHtml .= "<input type='hidden' name='shareing_data' value='" . $para['shareing_data'] . "'/>";
        $sHtml .= "<input type='hidden' name='risk_item' value='" . $para['risk_item'] . "'/>";
        $sHtml .= "<input type='hidden' name='id_type' value='" . $para['id_type'] . "'/>";
        //$sHtml .= "<input type='hidden' name='id_no' value='429004198603040596'/>";
        //$sHtml .= "<input type='hidden' name='acct_name' value='王青'/>";
        //$sHtml .= "<input type='hidden' name='flag_modify' value='" . $para['flag_modify'] . "'/>";
        //$sHtml .= "<input type='hidden' name='card_no' value='" . $para['card_no'] . "'/>";
        //$sHtml .= "<input type='hidden' name='back_url' value='" . $para['back_url'] . "'/>";
        //submit按钮控件请不要含有name属性
        $sHtml = $sHtml . "<input type='submit' value='" . $button_name . "'></form>";
        $sHtml = $sHtml . "<script>document.forms['llpaysubmit'].submit();</script>";
        return $sHtml;
    }

    //根据用户ID生成订单号
    function llpay_orderid($uid) {
        //时间组成的字符串
        $timeString = date("Y", strtotime("now")) . date("m", strtotime("now")) . date("d", strtotime("now")) . date("H", strtotime("now")) . date("i", strtotime("now")) . date("s", strtotime("now"));
        $rand6num = $this->randStr(6, 'NUMBER');
        return $timeString . $uid . $rand6num;
    }

    //生成随机数
    function randStr($len = 6, $format = 'ALL') {
        switch ($format) {
            case 'ALL':
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-@#~';
                break;
            case 'CHAR':
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-@#~';
                break;
            case 'NUMBER':
                $chars = '0123456789';
                break;
            default :
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-@#~';
                break;
        }

        //随机数种子;
        mt_srand((double) microtime() * 1000000 * getmypid());

        $password = "";

        while (strlen($password) < $len)
            $password.=substr($chars, (mt_rand() % strlen($chars)), 1);

        return $password;
    }

    //格式化时间戳
    function local_date($format, $time = NULL) {
        if ($time === NULL) {
            $time = gmtime();
        } elseif ($time <= 0) {
            return '';
        }
        return date($format, $time);
    }

    /**
     * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
     * @param $para 需要拼接的数组
     * return 拼接完成以后的字符串
     */
    function createLinkstring($para) {
        $arg = "";
        while (list ($key, $val) = each($para)) {
            $arg.=$key . "=" . $val . "&";
        }
        //去掉最后一个&字符
        $arg = substr($arg, 0, count($arg) - 2);
        //file_put_contents("log.txt","转义前:".$arg."\n", FILE_APPEND);
        //如果存在转义字符，那么去掉转义
        if (get_magic_quotes_gpc()) {
            $arg = stripslashes($arg);
        }
        //file_put_contents("log.txt","转义后:".$arg."\n", FILE_APPEND);
        return $arg;
    }

    /**
     * 生成签名结果
     * @param $para_sort 已排序要签名的数组
     * return 签名结果字符串
     */
    function buildRequestMysign($para_sort) {
        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr = $this->createLinkstring($para_sort);
        $mysign = "";
        switch (strtoupper(trim($this->llpay_config['sign_type']))) {
            case "MD5" :
                $mysign = md5Sign($prestr, $this->llpay_config['key']);
                break;
            case "RSA" :
                $mysign = $this->RsaSign($prestr, $this->llpay_config['RSA_PRIVATE_KEY']);
                break;
            default :
                $mysign = "";
        }
        //file_put_contents("log.txt","签名:".$mysign."\n", FILE_APPEND);
        return $mysign;
    }

    /**
     * 生成要请求给连连支付的参数数组
     * @param $para_temp 请求前的参数数组
     * @return 要请求的参数数组
     */
    function buildRequestPara($para_temp) {
        //除去待签名参数数组中的空值和签名参数
        $para_filter = $this->paraFilter($para_temp);
        //对待签名参数数组排序
        $para_sort = $this->argSort($para_filter);
        //生成签名结果
        $mysign = $this->buildRequestMysign($para_sort);
        //签名结果与签名方式加入请求提交参数组中
        $para_sort['sign'] = $mysign;
        $para_sort['sign_type'] = strtoupper(trim($this->llpay_config['sign_type']));
        foreach ($para_sort as $key => $value) {
            //echo($key . '=>'. $value .'<br>');
            $para_sort[$key] = $value;
        }
        //exit();
        //echo $para_sort['sign'];
        //return $this->createLinkstring($para_sort);
        return $para_sort;
        //return urldecode(json_encode($para_sort));
    }

    /**
     * 获取返回时的签名验证结果
     * @param $para_temp 通知返回来的参数数组
     * @param $sign 返回的签名结果
     * @return 签名验证结果
     */
    function getSignVeryfy($para_temp, $sign) {
        //除去待签名参数数组中的空值和签名参数
        $para_filter = $this->paraFilter($para_temp);

        //对待签名参数数组排序
        $para_sort = $this->argSort($para_filter);

        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr = $this->createLinkstring($para_sort);

        $isSgin = false;
        switch (strtoupper(trim($this->llpay_config['sign_type']))) {
            case "MD5" :
                $isSgin = md5Verify($prestr, $sign, $this->llpay_config['key']);
                break;
            case "RSA" :
                $isSgin = $this->Rsaverify($prestr, $sign);
                break;
            default :
                $isSgin = false;
        }
        return $isSgin;
    }

    /*     * RSA签名
     * $data签名数据(需要先排序，然后拼接)
     * 签名用商户私钥，必须是没有经过pkcs8转换的私钥
     * 最后的签名，需要用base64编码
     * return Sign签名
     */

    function Rsasign($data, $priKey) {
        //转换为openssl密钥，必须是没有经过pkcs8转换的私钥
        $res = openssl_get_privatekey($priKey);

        //调用openssl内置签名方法，生成签名$sign
        openssl_sign($data, $sign, $res, OPENSSL_ALGO_MD5);

        //释放资源
        openssl_free_key($res);

        //base64编码
        $sign = base64_encode($sign);
        //file_put_contents("log.txt","签名原串:".$data."\n", FILE_APPEND);
        return $sign;
    }

    /*     * RSA验签
     * $data待签名数据(需要先排序，然后拼接)
     * $sign需要验签的签名,需要base64_decode解码
     * 验签用连连支付公钥
     * return 验签是否通过 bool值
     */

    function Rsaverify($data, $sign) {
        //读取连连支付公钥文件
        $pubKey = $this->llpay_config['LLPAY_RSA_PUBLIC_KEY'];
        //转换为openssl格式密钥
        $res = openssl_get_publickey($pubKey);

        //调用openssl内置方法验签，返回bool值
        $result = (bool) openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_MD5);

        //释放资源
        openssl_free_key($res);

        //返回资源是否成功
        return $result;
    }

    /**
     * 除去数组中的空值和签名参数
     * @param $para 签名参数组
     * return 去掉空值与签名参数后的新签名参数组
     */
    function paraFilter($para) {
        $para_filter = array();
        while (list ($key, $val) = each($para)) {
            if ($key == "sign" || $val == "")
                continue;
            else
                $para_filter[$key] = $para[$key];
        }
        return $para_filter;
    }

    /**
     * 对数组排序
     * @param $para 排序前的数组
     * return 排序后的数组
     */
    function argSort($para) {
        ksort($para);
        reset($para);
        return $para;
    }

    //获取用户真实IP
    function getip() {
        if (getenv("HTTP_CLIENT_IP") && strcasecmp(getenv("HTTP_CLIENT_IP"), "unknown"))
            $ip = getenv("HTTP_CLIENT_IP");
        else
        if (getenv("HTTP_X_FORWARDED_FOR") && strcasecmp(getenv("HTTP_X_FORWARDED_FOR"), "unknown"))
            $ip = getenv("HTTP_X_FORWARDED_FOR");
        else
        if (getenv("REMOTE_ADDR") && strcasecmp(getenv("REMOTE_ADDR"), "unknown"))
            $ip = getenv("REMOTE_ADDR");
        else
        if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown"))
            $ip = $_SERVER['REMOTE_ADDR'];
        else
            $ip = "255.255.255.0";
        return ($ip);
    }

}
