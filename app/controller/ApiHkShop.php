<?php
// 港货超市专用控制器
namespace app\controller;
use think\facade\Db;
use Exception;

class ApiHkShop extends ApiCommon
{
    public function initialize()
    {
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
                'remark' => '港货超市登录自动升级为团长',
                'createtime' => time()
            ]);
            
            // 重新获取更新后的用户信息
            $updatedMember = Db::name('member')->where('aid', aid)->where('id', $userid)->find();
            
            return $this->json(['status' => 1, 'msg' => '升级成功', 'userinfo' => $updatedMember]);
        } catch (Exception $e) {
            // 记录详细错误信息
            \think\facade\Log::error('HkShop upgradeToLeader Error: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            // 返回友好错误信息
            return $this->json(['status' => 0, 'msg' => '系统错误：' . $e->getMessage()]);
        }
    }
    
    /**
     * 港货超市专用登录处理
     */
    public function hkLogin()
    {
        try {
            // 获取用户ID
            $userid = input('param.userid/d', 0);
            
            if($userid <= 0) {
                return $this->json(['status' => 0, 'msg' => '无效的用户ID']);
            }
            
            // 确保aid常量已定义
            if (!defined('aid')) {
                return $this->json(['status' => 0, 'msg' => '系统错误：aid常量未定义']);
            }
            
            // 查询用户信息
            $memberInfo = Db::name('member')->where('aid', aid)->where('id', $userid)->find();
            if(!$memberInfo) {
                return $this->json(['status' => 0, 'msg' => '用户不存在']);
            }
            
            // 检查用户头像和昵称
            $needSetProfile = false;
            $errorMsg = '';
            
            // 检查头像
            if(!$memberInfo['headimg'] || strpos($memberInfo['headimg'], '/static/img/touxiang.png') !== false) {
                $needSetProfile = true;
                $errorMsg = '请设置真实头像';
            }
            
            // 检查昵称
            if(!$memberInfo['nickname'] || $memberInfo['nickname'] == '微信用户' || $memberInfo['nickname'] == '关注用户') {
                $needSetProfile = true;
                $errorMsg = '请设置真实昵称';
            }
            
            // 如果需要设置头像和昵称，返回相应状态码
            if($needSetProfile) {
                return $this->json(['status' => 3, 'msg' => $errorMsg, 'login_setnickname' => true]);
            }
            
            // 检查用户等级
            $levelInfo = Db::name('member_level')->where('aid', aid)->where('id', $memberInfo['levelid'])->find();
            $memberInfo['level_name'] = $levelInfo['name'] ?? '';
            $memberInfo['level'] = $levelInfo['id'] ?? 0;
            
            // 如果是尊贵客户，自动升级为团长
            if ($memberInfo['level_name'] == '尊贵客户') {
                // 查询团长级别ID
                $teamLeaderLevel = Db::name('member_level')->where('aid', aid)->where('name', '团长')->find();
                if($teamLeaderLevel) {
                    // 更新用户级别为团长
                    Db::name('member')->where('aid', aid)->where('id', $userid)->update([
                        'levelid' => $teamLeaderLevel['id'],
                        'isdefault' => 0
                    ]);
                    
                    // 记录操作日志
                    Db::name('member_levelup_order')->insert([
                        'aid' => aid,
                        'mid' => $userid,
                        'levelid' => $teamLeaderLevel['id'],
                        'type' => 'system',
                        'remark' => '港货超市登录自动升级为团长',
                        'createtime' => time()
                    ]);
                    
                    // 更新返回的用户信息
                    $memberInfo['levelid'] = $teamLeaderLevel['id'];
                    $memberInfo['level_name'] = '团长';
                    $memberInfo['level'] = 2;
                }
            }
            
            // 对于新用户（等级为1的用户），自动升级为团长
            if ($memberInfo['level'] == 1) {
                // 查询团长级别ID
                $teamLeaderLevel = Db::name('member_level')->where('aid', aid)->where('name', '团长')->find();
                if($teamLeaderLevel) {
                    // 更新用户级别为团长
                    Db::name('member')->where('aid', aid)->where('id', $userid)->update([
                        'levelid' => $teamLeaderLevel['id'],
                        'isdefault' => 0
                    ]);
                    
                    // 记录操作日志
                    Db::name('member_levelup_order')->insert([
                        'aid' => aid,
                        'mid' => $userid,
                        'levelid' => $teamLeaderLevel['id'],
                        'type' => 'system',
                        'remark' => '港货超市新用户自动升级为团长',
                        'createtime' => time()
                    ]);
                    
                    // 更新返回的用户信息
                    $memberInfo['levelid'] = $teamLeaderLevel['id'];
                    $memberInfo['level_name'] = '团长';
                    $memberInfo['level'] = 2;
                }
            }
            
            return $this->json(['status' => 1, 'msg' => '登录成功', 'userinfo' => $memberInfo]);
        } catch (Exception $e) {
            // 记录详细错误信息
            \think\facade\Log::error('HkShop hkLogin Error: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            // 返回友好错误信息
            return $this->json(['status' => 0, 'msg' => '系统错误：' . $e->getMessage()]);
        }
    }
}