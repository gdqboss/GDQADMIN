<?php
// JK客户定制

namespace app\custom;
use think\facade\Db;
class MemberCustom
{
    //检测脱离会员佣金情况，当脱离人员N天内的佣金收益未达到Y元，那么现下级随机N人自动划拨给脱离人员的现上级
    public function help_check($aid,$set=[]){
        }

}