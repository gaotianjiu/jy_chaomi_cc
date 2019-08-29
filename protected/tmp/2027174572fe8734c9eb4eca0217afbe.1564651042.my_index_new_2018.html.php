<?php if(!class_exists("View", false)) exit("no direct access allowed");?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">	
<title><?php if ($module=='detail') : ?><?php echo htmlspecialchars($type_name, ENT_QUOTES, "UTF-8"); ?> - <?php endif; ?>炒米网 - 域名批量交易平台</title>
<meta name="keywords" content="炒米交易,炒米网,炒米域名<?php if ($module=='detail') : ?>,<?php echo htmlspecialchars($type_name, ENT_QUOTES, "UTF-8"); ?><?php endif; ?>">
<meta name="Description" Content="炒米网交易中心是一个专业的域名批量交易平台，实时挂单委托成交、一口价域名买卖交易、24小时开盘交易、方便快捷 - 聪明的米农都在这里！">
<?php include $_view_obj->compile("amui/am_i_header_css_js.html"); ?>
<script type="text/javascript" src="//cdn.staticfile.org/echarts/3.6.2/echarts.min.js"></script>
<script type="text/javascript" src="//static.chaomi.cc/js/amui/assets/js/chart.js?t=20170703"></script>
<script type="text/javascript" src="//static.chaomi.cc/js/amui/assets/js/jquery.flot.js?=20180518"></script>
</head>
<body class="tpl-bg">
<!----------顶部通栏块 begin--------->
<?php include $_view_obj->compile("amui/am_i_header.html"); ?>
<!----------顶部通栏块 end--------->	
<style>
</style>

	
  <!--div data-am-widget="slider" class="am-slider am-slider-a1" style="max-height: 340px;margin-top:-10px;" data-am-slider='{&quot;directionNav&quot;:false}' >
		  <ul class="am-slides">
			  <li>
					<img src="http://static.chaomi.cc/images/file/17/jy_index_pic_a_1.jpg?t1" style="max-height: 340px;">
			  </li>  			  
		  </ul>
	</div-->

<div style="width:100%;background:#ffffff;padding:0px;margin-top:0px;">
		<div class="am-g" style="width:1200px;font-size:14px;padding-left:10px;">
				<a href="http://my.chaomi.cc/announce/view?id=<?php echo htmlspecialchars($announce_ret['id'], ENT_QUOTES, "UTF-8"); ?>" target="_blank" style="color:#ff6000"><span class="am-icon-volume-up"> 公告：<?php echo htmlspecialchars($announce_ret['title'], ENT_QUOTES, "UTF-8"); ?></span></a>				
		</div>
</div>
<div style="width:100%;margin-bottom:0px;padding-bottom:3px;">
	<div class="tpl-page-container" style="padding:0px;">
				<!----------平台成交数据一览 begin--------->
				
						<div class="am-g tpl-content-wrapper-i" style="margin-bottom:-15px;margin-top:10px;">
							<div class="tpl-portlet-components-i" style="padding-top:10px;padding-bottom:10px;font-size:18px;">
								   今日总成交额 <span class="font-red" id="now-trans-p">-</span> 元，总成交量 <span class="font-red" id="now-trans-c">-</span> 个 <span class="am-margin-left"></span> 昨日总成交额 <span class="font-red" id="yet-trans-p">-</span> 元，总成交量 <span class="font-red" id="yet-trans-c">-</span> 个 
								   <!-- <span style="font-size:12px;margin-left:2.5rem;"><span id="now-uptime"></span></span> -->
								   <!-- <div style="font-size:14px;" id="twos_data">今日已发放</div> -->

							</div>
						</div>
						
				<!----------平台成交数据一览 end--------->	
	
				<!----------品种列表 begin---------->	
					<div class="tpl-page-container" style="padding:0px;">
						<div class="am-g tpl-content-wrapper-i">					
							<div class="am-fl" style="background:#fff;width:100%;padding:0px 0px;margin-top:10px;">
                                <table class="am-table am-table-hover tpl-table-uppercase am-table-striped" id="hq-list" style="border:none;">
									<div style="background: #f5f5f5;padding:10px;font-weight:bold;color:#666;font-size:16px;">
											批量域名交易
                                    </div>													
									<thead style="background: #fff;">
                                        <tr>
                                            <th>域名编码</th>
                                            <th>域名品种</th>
                                            <th>最新成交价</th>
                                            <th>涨跌幅</th>
                                            <th>当前买一价</th>
                                            <th>当前卖一价</th>
                                            <th>今日成交量</th>
                                            <th>今日成交额</th>
                                            <th>3日趋势</th>
                                        </tr>
                                    </thead>
                                    <tbody>
										<?php if(!empty($hq_list)){ $_foreach_v_counter = 0; $_foreach_v_total = count($hq_list);?><?php foreach( $hq_list as $k => $v ) : ?><?php $_foreach_v_index = $_foreach_v_counter;$_foreach_v_iteration = $_foreach_v_counter + 1;$_foreach_v_first = ($_foreach_v_counter == 0);$_foreach_v_last = ($_foreach_v_counter == $_foreach_v_total - 1);$_foreach_v_counter++;?>
											<tr>
											<td style="height:30px;line-height:30px;"><a href="/f/<?php echo htmlspecialchars($v['typeid'], ENT_QUOTES, "UTF-8"); ?>" target="_blank"><?php echo htmlspecialchars($v['typeid'], ENT_QUOTES, "UTF-8"); ?></a></td>
											<td style="height:30px;line-height:30px;"><a href="/f/<?php echo htmlspecialchars($v['typeid'], ENT_QUOTES, "UTF-8"); ?>" target="_blank"><?php echo htmlspecialchars($v['name'], ENT_QUOTES, "UTF-8"); ?></a></td>
											<td style="height:30px;line-height:30px;"<?php if ($v['zdf']>0) : ?> class="font-red"<?php endif; ?><?php if ($v['zdf']<0) : ?> class="font-green"<?php endif; ?>><span style="font-weight:500;font-size:16px;">￥<?php echo htmlspecialchars($v['new_price'], ENT_QUOTES, "UTF-8"); ?></span></td>
											<td style="height:30px;line-height:30px;"<?php if ($v['zdf']>0) : ?> class="font-red"<?php endif; ?><?php if ($v['zdf']<0) : ?> class="font-green"<?php endif; ?>><?php if ($v['zdf']>0) : ?>+<?php endif; ?><?php echo htmlspecialchars($v['zdf'], ENT_QUOTES, "UTF-8"); ?>%</td>
											<td style="height:30px;line-height:30px;"><?php echo htmlspecialchars($v['buy_1'], ENT_QUOTES, "UTF-8"); ?></td>
											<td style="height:30px;line-height:30px;"><?php echo htmlspecialchars($v['sale_1'], ENT_QUOTES, "UTF-8"); ?></td>
											<td style="height:30px;line-height:30px;"><?php echo htmlspecialchars($v['count'], ENT_QUOTES, "UTF-8"); ?></td>
											<td style="height:30px;line-height:30px;"><?php echo htmlspecialchars($v['price'], ENT_QUOTES, "UTF-8"); ?></td>
											<td><div id="placeholder_<?php echo htmlspecialchars($v['typeid'], ENT_QUOTES, "UTF-8"); ?>" style="width:155px;height:30px;"></div></td>
											</tr>
										<?php endforeach; }?>										
                                    </tbody>
                                </table>								
							</div>
						</div>
					</div>
				<!----------品种列表 end---------->	
				
				
				<!----------最近成交记录列表 begin---------->	
					<!-- <div class="tpl-page-container" style="padding:0px;"> -->
						<!-- <div class="am-g tpl-content-wrapper-i">					 -->
							<!-- <div class="am-fl" style="background:#fff;width:100%;padding:0px 0px;margin-top:0px;"> -->
                                <!-- <table class="am-table am-table-hover tpl-table-uppercase" id="deal-data-list"> -->
									<!-- <thead style="background: #f9f9f9;"> -->
										<!-- <tr> -->
											<!-- <th colspan="10"><span style="font-size:14px;">全部域名最新成交记录</span></th> -->

										<!-- </tr> -->
									<!-- </thead>								 -->
									<!-- <thead style="background: #fff;"> -->
                                        <!-- <tr> -->
                                            <!-- <th>成交时间</th> -->
                                            <!-- <th>域名品种</th> -->
                                            <!-- <th>成交单价</th> -->
                                            <!-- <th>成交数量</th> -->
                                            <!-- <th>成交总额</th> -->
                                            <!-- <th>买卖</th> -->
                                        <!-- </tr> -->
                                    <!-- </thead> -->
                                    <!-- <tbody> -->
                                    <!-- </tbody> -->
                                <!-- </table>								 -->
							<!-- </div> -->
						<!-- </div> -->
					<!-- </div> -->
				<!----------最近成交记录列表 end---------->	
		
	</div>	
</div>

<script type="text/javascript">
	
	function mini(){ 
		//小图表
		$.get('/chart/miniApi',function(ret){
		 if(ret.status==200){
				var data = ret.data;	
				$.each(data,function(k,v) {
				try{
					$.plot($("#placeholder_"+v['typeid']), [ {
						shadowSize : 0,
						data :  v.line
						} ], {
						grid : {
							borderWidth : 0
						},
						xaxis : {
							mode : "time",
							ticks : false
						},
						yaxis : {
							tickDecimals : 0,
							ticks : false
						},
						colors : [ '#C33' ]
					});
				}catch(err) {	
					//
				}

				});		
		 }	          
	   }, "json");
	}
	function deal_data(){ 
		//取出最近成交记录数据
		$.get('/deal_data/api',function(ret){
		 if(ret.status==200){
				var domainlist = ret.domainlist;
				$("#deal-data-list tbody").empty();
				if(!domainlist || domainlist==''){
					$("#deal-data-list tbody").append('<tr style="height:20px;"><td colspan="6" style="text-align:center;line-height: 20px;">暂无成交数据</td></tr>'	);
				}				
				$.each(domainlist,function(k,v) {
					var sta = '-';
					if(v['sta']==1) sta = '<span class="font-blue">卖出</span>';
					if(v['sta']==2) sta = '<span class="font-red">买入</span>';			
					str = '<tr><td>'+v['deal_time']+'</td><td>'+v['name']+'</td><td>'+v['deal_price']+'</td><td>'+v['deal_num']+'</td><td>'+v['tot_price']+'</td><td>'+sta+'</td></tr>'
					$("#deal-data-list tbody").append(str);
				});		
		 }	          
	   }, "json");
	}
	function nowApi(){ 
		//平台成交数据一览
		$.get('/deal_data/nowApi',function(ret){
		 if(ret.status==200){
			$('#now-trans-p').text(ret.nowp);
			$('#now-trans-c').text(ret.nowc);
			$('#yet-trans-p').text(ret.yetp);
			$('#yet-trans-c').text(ret.yetc);
			$('#twos_data').html(ret.twos_data);			
			$('#now-uptime').text('更新时间：'+ret.update_time);
		 }	          
	   }, "json");
	}	
	
	$(function () {
		//deal_data();
		nowApi();
		mini();	
		//window.setInterval(deal_data,2000); 
		window.setInterval(nowApi,10000); 
	});

</script>	
<!----------底部通栏块 begin--------->	
<?php include $_view_obj->compile("amui/am_i_footer.html"); ?>	
<!----------底部通栏块 end--------->	
</body>
</html>