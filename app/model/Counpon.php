<?php
// JK客户定制

namespace app\model;
use think\facade\Db;
class Counpon
{
    /*
     * 过期优惠券结算
     */
	static function couponExpire(){
        return true;
	}
}