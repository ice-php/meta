<?php
declare(strict_types=1);

namespace icePHP;

/**
 * 获取一个表的数据字典描述
 * @param $name string 表名
 * @return array
 * @throws TableException|MysqlException
 */
function meta(string $name): array
{
    return Meta::dictTable($name);
}