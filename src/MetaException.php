<?php
declare(strict_types=1);

namespace icePHP;

class MetaException extends \Exception{
    //无法识别的数据类型
    const TYPE_UNKNOWN=1;

    //缺少数据库配置(database)
    const DATABASE_CONFIG_MISS=2;
}