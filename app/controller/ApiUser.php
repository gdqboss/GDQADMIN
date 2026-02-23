<?php
// JK客户定制

/*用户相关接口*/
namespace app\controller;
use think\facade\Db;
use Exception;
class ApiUser extends ApiCommon
{
    public function initialize(){
        parent::initialize();
        // 这个接口不需要强制登录，因为它会被未登录用户调用
    }
    
    /**
     * 升级用户为团长
     * @param int $userid 用户ID
     * @return json
     */
    public function upgradeToLeader()
    {
        try {
            $userid = input('param.userid/d', 0);
            
            // 确保用户ID有效
            if($userid <= 0) {
                return $this->json(['status' => 0, 'msg' => '无效的用户ID']);
            }
            
            // 确保aid常量已定义
            if (!defined('aid')) {
                return $this->json(['status' => 0, 'msg' => '系统错误：aid常量未定义']);
            }
            
            // 查询当前用户信息
            $memberInfo = Db::name('member')->where('aid', aid)->where('id', $userid)->find();
            if(!$memberInfo) {
                return $this->json(['status' => 0, 'msg' => '用户不存在']);
            }
            
            // 查询团长级别ID
            $teamLeaderLevel = Db::name('member_level')->where('aid', aid)->where('name', '团长')->find();
            if(!$teamLeaderLevel) {
                return $this->json(['status' => 0, 'msg' => '团长级别不存在']);
            }
            
            // 更新用户级别为团长
            $updateData = [
                'levelid' => $teamLeaderLevel['id'],
                'isdefault' => 0
            ];
            
            // 更新会员信息
            Db::name('member')->where('aid', aid)->where('id', $userid)->update($updateData);
            
            // 记录操作日志
            Db::name('member_levelup_order')->insert([
                'aid' => aid,
                'mid' => $userid,
                'levelid' => $teamLeaderLevel['id'],
                'type' => 'system',
                'remark' => '系统自动升级为团长',
                'createtime' => time()
            ]);
            
            return $this->json(['status' => 1, 'msg' => '升级成功']);
        } catch (Exception $e) {
            // 记录详细错误信息
            \think\facade\Log::error('upgradeToLeader Error: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            // 返回友好错误信息
            return $this->json(['status' => 0, 'msg' => '系统错误：' . $e->getMessage()]);
        }
    }
}
