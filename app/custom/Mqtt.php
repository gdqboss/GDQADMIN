<?php
// JK客户定制

// custom_file(lot_cerberuse)
/**
 * Created by PhpStorm.
 * User: Gold
 * Date: 2023/6/8
 * Time: 14:04
 */
namespace app\custom;
require ('extend/phpMQTT.php');
class Mqtt{
     public  $server = '';
     public  $username = '';
     public  $password = '';
     public  $port = 1883;
     public  $client_id = '';
     public function __construct()
     {
         //查询数据库配置，进行重置更新
         $time = date('Ymd');
         $this->server = '43.143.96.187';     // change if necessary
         $this->port = 1883;                     // change if necessary
         $this->username = 'admin';                   // set your username
         $this->password = '1234567';                   // set your password
         $this->client_id = 'phpMQTT-'.$time; // make sure this is unique for connecting to sever - you could use uniqid()
       
         
     }
     //发送消息
     public function publish($topic,$content){
         $mqtt = new \phpMQTT($this->server, $this->port, $this->client_id);
         if ($mqtt->connect(false, NULL, $this->username, $this->password)) {
          $mqtt->publishAndWaitForMessage($topic, $content, 2);
             
         }else{
             return false;
         }
         $mqtt->close();
     }
     //订阅
     public function subscribe($topic){
         $mqtt = new \phpMQTT($this->server, $this->port, $this->client_id);
         if ($mqtt->connect(false, NULL, $this->username, $this->password)) {
             $topics[$topic] = array('qos' => 2, 'function' => 'procMsg');
             $mqtt->subscribe($topics);

         }else{
             return false;
         }
         $mqtt->close();
     }
    function procMsg($msg) {
        \think\facade\Log::write(json_encode($msg,JSON_UNESCAPED_UNICODE));
    }
}