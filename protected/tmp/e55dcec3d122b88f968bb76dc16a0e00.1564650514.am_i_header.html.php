<?php if(!class_exists("View", false)) exit("no direct access allowed");?><style>
html,body {background: #fff;}
.am-topbar-inverse {background:#fff;border-color:#fff;}
.top-item-tip{font-size:14px;}
.top-item-tip .phone{width: 16px;height: 16px;padding-left: 8px;background:url('//static.chaomi.cc/images/ico-phone-16x16.png')  no-repeat;}
.top-item-tip .weixin{width: 16px;height: 16px;padding-left: 12px;background:url('//static.chaomi.cc/images/ico-weixin-16x16.png')  no-repeat;}
.top-item-tip{float:left; padding-right:8px;}
.top-item-tip .tip {font-size:12px;padding:5px 10px; border-radius:2px; position:absolute;box-shadow:1px 1px 10px 0 #ccc;background:white;z-index:1000000;border:1px solid #dddddd;font-weight:normal;display:none;}
.top-item-tip .tip img{margin-top:-18px;}
.top-item-tip:hover .tip{display:block;}
.tpl-logo-i{width:217px;}
.tpl-banner {background:url('//static.chaomi.cc/images/t-banner-2017.jpg') no-repeat; height:65px; width:250px;margin-left:-20px;margin-top: 10px;}
</style>
<!----------顶部通栏块 begin--------->
<header class="am-topbar am-topbar-inverse">
	<div class="tpl-top-a">
		<div class="am-g tpl-content-wrapper-i">
			<div class="am-fl">
				<div class="top-item-tip">
    			 	<span class="phone"><a href="//app.chaomi.cc/" target="_blank">手机APP</a></span>
    			 	<div class="tip"> 
    			 			<p>炒米APP 苹果 安卓</p>
    			 			<img src="//static.chaomi.cc/qrcode/app.jpg" width="100" height="100" border="0">
    			 	</div>
    			 </div>              	
    			 <div class="top-item-tip">
    			 	<span class="weixin"><a href="#">成交推送</a></span>
    			 	<div class="tip"> 
    			 			<p>实时推送成交数据</p>
    			 			<img src="//static.chaomi.cc/qrcode/chaomicc1.jpg" width="100" height="100" border="0">
    			 	</div>
    			 </div>
			 
     			 <div class="top-item-tip">
    			 	<span class="weixin"><a href="#">日报推送</a></span>
    			 	<div class="tip"> 
    			 			<p>推送炒米日报资讯</p>
    			 			<img src="//static.chaomi.cc/qrcode/chaomicc.jpg" width="100" height="100" border="0">
    			 	</div>
    			 </div>  
				 <div class="top-item-tip">
					QQ群：<a style="margin-left:1px;" target="_blank" href="//shang.qq.com/wpa/qunwpa?idkey=0ab3785940b1383f636dd7a13fb5cfacfe3cb303c52034f40dc01c2920e84ddb">336888</a>		 
    			 </div>	 
			</div>
			<div class="am-fr">
				<?php if ($mid==0) : ?>
				<!--登录前状态 begin-->
				<a href="//my.chaomi.cc/sso/register" target="_blank"><span class="am-icon-user-plus"></span> 注册帐号</a><a href="//my.chaomi.cc/sso/login" target="_blank"><span class="am-icon-sign-in"></span> 会员登录</a>
				<!--登录前状态 end-->
				<?php else : ?>
				<!--登录后状态 begin-->
				<a href="//my.chaomi.cc/member" target="_blank">ID：<?php echo htmlspecialchars($mid, ENT_QUOTES, "UTF-8"); ?></a>
				<a href="//my.chaomi.cc/member" target="_blank"><span class="am-icon-user"></span> 管理中心</a> 
				<a href="//my.chaomi.cc/sso/login_out"><span class="am-icon-power-off"></span> 安全退出</a>
				<!--登录后状态 end-->
				<?php endif; ?>
			</div>
		</div>
	</div>
	<div class="am-g tpl-content-wrapper-i">
	<div class="am-topbar-brand">
		<a href="//www.chaomi.cc/" class="tpl-logo-i">
			<img src="//static.chaomi.cc/images/logo_f_c.jpg" alt="炒米网 - 聪明的米农都在这里！">
		</a>
	</div>
	<div class="tpl-banner am-fl"></div>
	<div class="am-fr">
			<!--a href="#" target="_blank"><img src="#"></a-->
			<!-- <a href="//jy.chaomi.cc/?from=zpic_jy"><img src="//static.chaomi.cc/images/file/17/cm_zpic_6.jpg" border="0" width="498" height="60" alt="" /></a> -->
	</div>
	</div>
</header>
<!----------顶部通栏块 end--------->
<!----------通用导航条 begin--------->
<style>
/*通用导航条*/
.cm-top-tabs{border-bottom:1px solid #e9ecf3;border-top:1px solid #e9ecf3;line-height:43px;margin-top:-15px;clear:both;background:#f5f5f5;margin-bottom:15px;}
.cm-top-tabs-nav{max-width:1200px;background:#f5f5f5;}
.cm-top-tabs li{float:left;padding:0px 25px;}
.cm-top-tabs a{color:#666;}
.cm-top-tabs li:hover{border-bottom:2px solid #F37B1D;color:#666;margin-bottom:-1px;}
.cm-top-tabs li:hover a{color:#666;}
.cm-top-tabs .active,.cm-top-tabs .active:hover{border-bottom:2px solid #F37B1D;margin-bottom:-1px;}
.cm-top-tabs .active a,.cm-top-tabs .active:hover a{color:#666;}
</style>
<div class="cm-top-tabs">
	<ul class="am-g cm-top-tabs-nav">
		<a href="//jy.chaomi.cc/"><li class="am-icon-bar-chart<?php if ($cm_nav=='jy') : ?> active<?php endif; ?>"> 炒米交易</li></a>
		<!--<a href="//jy.chaomi.cc/ykj"><li class="am-icon-y-combinator<?php if ($cm_nav=='domainykj') : ?> active<?php endif; ?>"> 一口价</li></a>-->
		<a href="//jy.chaomi.cc/deal_data"><li class="am-icon-database<?php if ($cm_nav=='deal_data') : ?> active<?php endif; ?>"> 最近成交</li></a>
		<a href="//my.chaomi.cc/announce"><li class="am-icon-volume-up<?php if ($cm_nav=='announce') : ?> active<?php endif; ?>"> 网站公告</li></a>
		<a href="//my.chaomi.cc/member" rel="nofollow"><li class="am-icon-user<?php if ($cm_nav=='member') : ?> active<?php endif; ?>"> 会员中心</li></a>						  
		<a href="//my.chaomi.cc/helps" rel="nofollow"><li class="am-icon-question-circle<?php if ($cm_nav=='helps') : ?> active<?php endif; ?>"> 网站帮助</li></a>
		<a href="//my.chaomi.cc/helps/about" rel="nofollow"><li class="am-icon-phone<?php if ($cm_nav=='helps_about') : ?> active<?php endif; ?>"> 联系我们</li></a>		
	</ul>											
</div>
<!----------通用导航条 end--------->
<div class="am-cf"></div>