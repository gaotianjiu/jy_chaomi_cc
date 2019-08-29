<?php
class lib_member_reg extends Model
{
  var $pk = "id"; // 每个唯一的标志，可以称为主键
  var $table_name = "lib_member_reg"; // 数据表的名称
    function __construct(){        // 一些操作        
        parent::__construct();       
        $this->_db = spClass('db_mysql', array(            
            array(               
                'driver' => 'mysql',
                'host' =>'redykjhqsdrsql.mysql.rds.aliyuncs.com',                
                'port' => '3306',                
                'login' => 'ykjhqcom',                
                'password' =>  'ZeLiMsJ6bFVwyHddse1',                
                'database' =>  'ykjhqcom',           
                )        
            ), SP_PATH.'/Drivers/mysql.php', TRUE); 
    }
}