<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 地图
// +----------------------------------------------------------------------
namespace app\controller;


class Map extends Common
{
    //地图搜索
    public function searchFormMap(){
        if(request()->isAjax()){
            $callback = input('param.callback'); //回调函数名
            $keyword = input('param.keywords');
            $lat = input('param.lat');
            $lng = input('param.lng');

            if (empty($keyword) || empty($lat) || empty($lng)) {
                if ($callback) {
                    return $this->jsonpResponse(['status' => 0, 'msg' => '参数错误'],$callback);
                }
                return json(['status' => 0, 'msg' => '参数错误']);
            }

            $mapqq = new \app\common\MapQQ();
            $results = $mapqq->searchNearbyPlace($keyword, ['type' => 'city', 'lat' =>$lat, 'lng' => $lng], 1000, 1);

            if ($results['status'] == 1) {
                if (empty($results['data']) && isset($results['cluster'])) {
                    if ($callback) {
                        return $this->jsonpResponse(['status' => 0, 'msg' => '请输入详细地址'],$callback);
                    }
                    return json(['status' => 0, 'msg' => '请输入详细地址']);
                }
                if ($callback) {
                    return $this->jsonpResponse(['status' => 1, 'data' =>$results['data']], $callback);
                }
                return json(['status' => 1, 'data' => $results['data']]);
            }

            $msg = isset($results['message']) ? $results['message'] : $results['msg'];
            if ($callback) {
                return $this->jsonpResponse(['status' => 0, 'msg' =>$msg], $callback);
            }
            return json(['status' => 0, 'msg' => $msg]);
        }
    }

    //生成JSONP格式的响应
    private function jsonpResponse($data,$callback)
    {
        //设置内容类型为 application/javascript
        header('Content-Type: application/javascript');
        return $callback . '(' . json_encode($data) . ');';
    }
}