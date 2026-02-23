<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 财务 - 支出 custom_file(expend)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class Expend extends Common
{
    public function initialize()
    {
        parent::initialize();
    }

    //列表
    public function index()
    {
        $clist = Db::name('expend_category')->field('id,name')->where('aid', aid)->where('bid', bid)->where('status',
            1)->where('pid', 0)->order('sort desc,id')->select()->toArray();
        $cdata = array();
        foreach ($clist as $c) {
            $cdata[$c['id']] = $c['name'];
        }
        if (request()->isAjax()) {
            $page = input('param.page');
            $limit = input('param.limit');
            if (input('param.field') && input('param.order')) {
                $order = input('param.field').' '.input('param.order');
            } else {
                $order = 'id desc';
            }
            $where = array();
            $where[] = [
                'aid',
                '=',
                aid
            ];
            $where[] = [
                'bid',
                '=',
                bid
            ];
            if (input('param.name')) {
                $where[] = [
                    'remark',
                    'like',
                    '%'.input('param.name').'%'
                ];
            }
            if (input('?param.cid') && input('param.cid') !== '') {
                $where[] = [
                    'cid',
                    '=',
                    input('param.cid')
                ];
            }
            if (input('param.ctime')) {
                $ctime = explode(' ~ ', input('param.ctime'));
                $where[] = [
                    'createtime',
                    '>=',
                    strtotime($ctime[0])
                ];
                $where[] = [
                    'createtime',
                    '<',
                    strtotime($ctime[1]) + 86400
                ];
            }

            $count = 0 + Db::name('expend')->where($where)->count();
            $data = Db::name('expend')->where($where)->page($page, $limit)->order($order)->select()->toArray();
//            dump($data);
//            dd(Db::getLastSql());
            foreach ($data as $k => $v) {
                $data[$k]['cname'] = $cdata[$v['cid']] ? $cdata[$v['cid']] : '未分类';
                if ($data[$k]['createtime']) {
                    $data[$k]['createtime'] = date('Y-m-d H:i:s', $v['createtime']);
                }
            }
            return json([
                'code' => 0,
                'msg' => '查询成功',
                'count' => $count,
                'data' => $data
            ]);
        }
        View::assign('clist', $clist);
        return View::fetch();
    }

    //编辑
    public function edit()
    {
        if (input('param.id')) {
            $info = Db::name('expend')->where('aid', aid)->where('bid', bid)->where('id', input('param.id/d'))->find();
        } else {
            $info = array('id' => '');
        }
        $clist = Db::name('expend_category')->field('id,name')->where('aid', aid)->where('bid', bid)->where('status',
            1)->where('pid', 0)->order('sort desc,id')->select()->toArray();
        View::assign('clist', $clist);
        View::assign('info', $info);
        return View::fetch();
    }

    //保存
    public function save()
    {
        $info = input('post.info/a');
        if ($info['id']) {
            Db::name('expend')->where('aid', aid)->where('bid', bid)->where('id', $info['id'])->update($info);
            \app\common\System::plog('编辑财务支出'.$info['id']);
        } else {
            $info['aid'] = aid;
            $info['bid'] = bid;
            $info['createtime'] = time();
            $info['uid'] = $this->uid;
            $id = Db::name('expend')->insertGetId($info);
            \app\common\System::plog('添加财务支出'.$id);
        }
        return json([
            'status' => 1,
            'msg'    => '操作成功',
            'url'    => (string) url('index')
        ]);
    }

    //删除
    public function del()
    {
        $ids = input('post.ids/a');
        Db::name('expend')->where('aid', aid)->where('bid', bid)->where('id', 'in', $ids)->delete();
        \app\common\System::plog('财务支出删除'.implode(',', $ids));
        return json([
            'status' => 1,
            'msg'    => '删除成功'
        ]);
    }
}