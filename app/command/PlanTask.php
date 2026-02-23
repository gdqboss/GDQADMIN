<?php
// JK客户定制



namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class PlanTask extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->addArgument('method', Argument::OPTIONAL);
        $this->setName('plantask')
            ->setDescription('计划任务');
    }
    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);
		ini_set('memory_limit', -1);
        error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED ^E_STRICT ^E_WARNING);
        $t1 = microtime(true);
        //获取cli参数，若参数不为空则单独执行该方法
        $args = $input->getArguments();
        if (!empty($args['method'])) {
            $method = $args['method'];
            $this->$method();
        } else {
            die('无效方法');
        }
        echo sprintf("执行成功，耗时： %f秒<br>", round(microtime(true)-$t1,3));
        echo 'success';exit;
    }

    public static function jiesuanall(){
        $info = Db::name('sysset')->where('name','webinfo')->find();
        $webinfo = json_decode($info['value'],true);
        if($webinfo['jiesuan_fenhong_type']==0){
            return true;
        }
        //文件锁，防止并发执行
        $file_name = ROOT_PATH.'runtime/task_lock.log';
        if(file_exists($file_name)){
            file_put_contents($file_name,date('Y-m-d H:i:s').'任务重复'."\r\n",FILE_APPEND);
            return true;
        }else{
            file_put_contents($file_name,date('Y-m-d H:i:s').'任务开始'."\r\n",FILE_APPEND);
        }
        //开始处理分红
       try {
            //执行分红的结算
            \app\common\Fenhong::jiesuanAll();
            //执行分红的发放
            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();

            foreach($syssetlist as $sysset) {
                $aid = $sysset['aid'];
                $map = [];
                $map[] = ['aid','=',$aid];
                $map[] = ['status','=',0];
                $lists = Db::name('member_fenhonglog')
                    ->where($map)
                    ->where('sendtime_yj>0 && sendtime_yj<'.time())
                    ->select()->toArray();
                if($lists){
                    \app\common\Fenhong::send_now($aid,$lists);
                    //将数据的sendtime_yj改为0，防止异步任务中一直查询未收货状态的数据
                    $ids = array_column($lists,'id');
                    Db::name('member_fenhonglog')->where('id','in',$ids)->where('status',0)->update(['sendtime_yj'=>0]);
                }
            }
        } catch (\Throwable $e) {
            // 请求失败
            writeLog($e, 'plantask');
            unlink($file_name);
            return true;
        }
        //执行完成删除锁文件
        unlink($file_name);
        return true;
    }

    public function day_release_greenscore(){
        }

}