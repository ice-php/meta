<?php
declare(strict_types=1);

namespace icePHP;

/**
 * 元 操作
 * User: 蓝冰
 * Date: 2016/5/16
 * Time: 9:31
 */
class Meta
{
    /**
     * 输出头
     */
    static private function header()
    {
        header('content-type:text/html;charset=utf-8');
    }

    /**
     * 数据类型转换成可读格式
     * @param $field array 字段描述
     * @return string 类型
     */
    public static function mapType(array $field): string
    {
        $type = $field['type'];
        if (in_array($type, ['int', 'bigint', 'mediumint', 'smallint', 'tinyint', 'year'])) {
            return '整数';
        }
        if (in_array($type, ['float', 'decimal', 'double'])) {
            return '浮点';
        }
        if (in_array($type, ['char', 'varchar'])) {
            return '文本(' . $field['maxLength'] . ')';
        }
        if (in_array($type, ['binary', 'varbinary', 'blob', 'longblob', 'mediumblob', 'tinyblob'])) {
            return '二进制(' . $field['maxLength'] . ')';
        }
        if ('enum' == $type) {
            return '枚举:' . implode(',', $field['enums']);
        }
        if ('set' == $type) {
            return '集合:' . implode(',', $field['sets']);
        }
        if ('bit' == $type) {
            return '是否';
        }
        if (in_array($type, ['text', 'mediumtext', 'longtext', 'tinytext'])) {
            return '大文本';
        }
        if ('datetime' == $type) {
            return '日期时间';
        }
        if ('time' == $type) {
            return '时间';
        }
        if ('timestamp' == $type) {
            return '时间戳';
        }
        if ('date' == $type) {
            return '日期';
        }

        trigger_error('无法识别的数据类型:' . json($type), E_USER_ERROR);
        return '';
    }

    /**
     * 获取全部数据库
     * @return array
     */
    public static function dictDatabases(): array
    {
        //取全部数据库
        $dbsConfig = configDefault('', 'database');
        if (!$dbsConfig) {
            trigger_error('缺少数据库配置(database)', E_USER_ERROR);
        }
        $dbs = [];

        foreach ($dbsConfig as $v) {
            if (isset($v['connect'])) {
                $dbs[] = $v['connect'];
            }
        }
        return $dbs;
    }

    /**
     * 获取指定库有的全部表的描述 信息
     * @return array
     */
    public static function dictTables(): array
    {
        //全部表
        $all = [];

        //取全部数据库
        $dbs = self::dictDatabases();

        //逐个数据库查看
        foreach ($dbs as $config) {
            //连接数据库
            $connect = Mysql::connectDatabase($config);

            //取指定数据库全部表
            $statement = $connect->query(Mysql::createShowTables());
            $tables = $statement->fetchAll(\PDO::FETCH_ASSOC);

            //逐个 表处理
            foreach ($tables as $table) {
                $name = array_values($table)[0];

                //取表的描述信息
                $statement = self::dictTable($name);


                if ($statement) {
                    $statement['database'] = $config['database'];
                    $all[] = $statement;
                }
            }
        }

        return $all;
    }

    /**
     * 获取一个表的数据字典描述
     * @param $name string 表名
     * @return array
     */
    public static function dictTable(string $name): array
    {
        //取单个表结构
        $meta = table($name)->meta();
        $primaryKey = table($name)->getPrimaryKey();

        return [
            'name' => $name,
            'desc' => $primaryKey ? $meta[$primaryKey]['description'] : '本表无主键',
            'meta' => $meta
        ];
    }

    /**
     * 取模板文件原始内容
     * @param  $name string 相对tpl目录的路径文件名
     * @return string
     */
    private static function getTpl(string $name): string
    {
        // 构造所在目录(当前模块,当前控制器,当前动作
        $path = dirname(__DIR__) . '/tpl/';
        return file_get_contents($path . $name . '.tpl');
    }

    static private $root;

    public static function setRoot(string $root)
    {
        self::$root = $root;
    }

    /**
     * 生成一个表的记录子类
     * @param array $info 表信息
     */
    static private function recordInstance(array $info): void
    {
        // 表名
        $name = $info['name'];

        // 将表名中的下划线转换为大写字母
        // 这是表对象类名
        $className = formatter($name);

        // 文件名(不带后缀)
        $fileName = lcfirst($className);

        // 构造文件物理路径
        $path = self::$root . 'program/record/' . $fileName . '.record.php';;

        //本文件不许覆盖
        if (is_file($path)) {
            return;
        }

        //生成子类文件内容
        $content = Template::replace(self::getTpl('record/instance'), [
            'description' => $info['desc'],
            'className' => $className,
            'name' => $name,
        ]);

        // 显示生成进度
        echo 'created record instance class of ' . $className . '<br/>' . "\r\n";

        //覆盖文件
        write($path, $content);
    }

    /**
     * 生成一个表的记录基类
     * @param array $info 表信息
     */
    static private function recordBase(array $info): void
    {
        // 表名
        $name = $info['name'];

        // 将表名中的下划线转换为大写字母
        // 这是表对象类名
        $className = formatter($name);

        //基类名称
        $baseName = $className . 'Base';

        // 文件名(不带后缀)
        $fileName = lcfirst($baseName);

        // 构造文件物理路径
        $path = self::$root . 'program/record/base/';
        makeDir($path);

        //文件路径
        $path .= $fileName . '.record.php';

        // 读取所有字段
        $fields = table($name)->meta();

        $fieldsContent = '';
        $fieldsName = [];

        //字段名称方法的代码
        $fieldsNameCode = '';

        //所有枚举字段的代码
        $enumContent = '';

        //每一个字段的数据类型
        $fieldsType = [];

        //逐个字段处理
        foreach ($fields as $field) {
            $type = self::getType($field['type']);
            $fieldsContent .= Template::replace(self::getTpl('record/field'), [
                'fieldName' => $field['name'],
                'type' => $type,
                'description' => $field['description'] ?: $field['name']
            ]);
            $fieldsName[] = $field['name'];

            //计算此字段的三种数据
            list($fContent, $eContent) = self::recordField($field);
            $fieldsNameCode .= $fContent;
            $enumContent .= $eContent;

            //字段与类型的对应
            $fieldsType[] = "'{$field['name']}'=>'$type'";
        }

        $content = Template::replace(self::getTpl('record/record'), [
            'description' => $info['desc'],
            'className' => $className,
            'baseName' => $baseName,
            'tableName' => $name,
            'fields' => $fieldsContent,
            'fieldsName' => "['" . implode("','", $fieldsName) . "'] ",
            'primaryKey' => table($name)->getPrimaryKey(),
            'fieldsNameContent' => $fieldsNameCode,
            'enumContent' => $enumContent,
            'fieldsType' => '[' . implode(',', $fieldsType) . ']'
        ]);

        // 显示生成进度
        echo 'created record base class of ' . $className . '<br/>';

        //覆盖文件
        write($path, $content);
    }

    /**
     * 根据字段的type(数据库),计算PHP中的数据类型
     * @param string $type 数据库中的type
     * @return string PHP中的数据类型:string/int/float
     */
    static private function getType(string $type): string
    {
        if (in_array($type, ['longtext', 'ProductX', 'varchar', 'char', 'enum', 'date', 'datetime', 'time', 'mediumtext'])) {
            return 'string';
        }
        if (in_array($type, ['timestamp', 'tinyint', 'bigint'])) {
            return 'int';
        }
        if ('decimal' == $type) {
            return 'float';
        }
        return 'string';
    }

    /**
     * 计算表基类中的字段的表现
     * @param array $f 字段信息
     * @return array[字段内容,枚举内容,搜索条件]
     */
    static private function recordField(array $f): array
    {
        //构造字段名称方法的代码
        $fieldsContent = Template::replace(self::getTpl('record/fieldConstant'), [
            'fieldName' => ucfirst($f['name']),
            'field' => $f['name'],
            'description' => $f['description']
        ]);

        $enumContent = '';
        if ('enum' == $f['type']) {
            $enumContent .= "\r\n\t" . '//字段[' . $f['description'] . ']的枚举值' . "\r\n";
            foreach ($f['enums'] as $enum) {
                $enumContent .= Template::replace(self::getTpl('record/enumConstant'), [
                    'field' => $f['name'],
                    'value' => str_replace(' ', '_', trim($enum, "'")),
                    'description' => $f['description']
                ]);
            }
        }

        return [$fieldsContent, $enumContent];
    }

    /**
     * 计算表基类中的字段的表现
     * @param array $f 字段信息
     * @return array[字段内容,枚举内容,搜索条件]
     */
    static private function tableField(array $f): array
    {
        //构造字段名称方法的代码
        $fieldsContent = Template::replace(self::getTpl('table/field'), [
            'fieldName' => ucfirst($f['name']),
            'field' => $f['name'],
            'description' => $f['description']
        ]);

        $enumContent = '';

        if ('enum' == $f['type']) {
            $enumContent .= "\r\n\t" . '//字段[' . $f['description'] . ']的枚举值' . "\r\n";
            foreach ($f['enums'] as $enum) {
                $enumContent .= Template::replace(self::getTpl('table/enum'), [
                    'field' => $f['name'],
                    'value' => str_replace(' ', '_', trim($enum, "'")),
                    'description' => $f['description']
                ]);
            }
            $enumContent .= Template::replace(self::getTpl('table/enumList'), [
                'field' => $f['name'],
                'list' => "'" . implode("', '", $f['enums']) . "'",
            ]);
        }

        // 构造此字段的条件
        if (in_array($f['type'], ['varchar', 'char', 'ProductX'])) {
            // 字符串,使用构造Like条件
            $searchWhere = Template::replace(self::getTpl('table/wherelike'), [
                'description' => $f['name'],//$f ['description'],
                'name' => $f ['name']
            ]);
        } elseif (in_array($f ['name'], ['created', 'updated']) or ($f ['name'] != 'id' and in_array($f ['type'], ['int', 'float']))) {
            // 整数不是ID,用Between条件
            $searchWhere = Template::replace(self::getTpl('table/wherebetween'), [
                'description' => $f['name'],//$f ['description'],
                'name' => $f ['name']
            ]);
        } else {
            // 其它用等于条件
            $searchWhere = Template::replace(self::getTpl('table/where'), [
                'description' => $f['name'],//$f ['description'],
                'name' => $f ['name']
            ]);
        }
        // 这个字段排除
        //if ('id' == $f ['name']) {
        // ID字段的注释即是表注释
        //$tableDescription = $f ['description'];
        //}

        return [$fieldsContent, $enumContent, $searchWhere];
    }

    /**
     * 生成一个表的基类
     * @param $info array 表信息
     */
    static private function tableBase(array $info): void
    {
        //表名
        $name = $info['name'];

        //子类名与基类名
        $className = formatter($name);
        $baseName = $className . 'Base';

        // 文件名(不带后缀)
        $fileName = lcfirst($baseName);

        // 构造基类文件物理路径
        $path = self::$root . 'program/table/base/';
        makeDir($path);

        //基类文件名
        $path .= $fileName . '.table.php';

        // 显示生成进度
        echo 'created table base class of ' . $baseName . '<br/>';

        // 读取所有字段
        $fields = table($name)->meta();

        // 所有Getter的方法
        $methods = "";

        // 拼接字段名列表
        $fieldsName = [];

        // 拼接构造搜索搜索条件
        $searchWhere = "";

        //字段名称方法的代码
        $fieldsContent = '';

        //所有枚举字段的代码
        $enumContent = '';

        //全部字段的数据类型
        $fieldsType = [];

        // 每个字段
        foreach ($fields as $field) {
            $fieldsName[] = $field['name'];

            //计算此字段的三种数据
            list($fContent, $eContent, $sWhere) = self::tableField($field);

            $fieldsContent .= $fContent;
            $enumContent .= $eContent;
            $searchWhere .= $sWhere;

            //字段与类型的对应
            $type = self::getType($field['type']);
            $fieldsType[] = "'{$field['name']}'=>'$type'";
        }

        $content = Template::replace(self::getTpl('table/class'), [
            // 表注释
            'tableDescription' => $info['desc'],

            //子类名
            'className' => $className,

            // 表基类名
            'baseName' => $baseName,

            // 表名
            'name' => $name,

            // 所有Getter方法
            'methods' => $methods,

            // Search中的搜索条件
            'searchWhere' => $searchWhere,

            // 获取一条记录的方法
            'info' => Template::replace(self::getTpl('table/info'), ['className' => $className]),

            // 所有字段
            'fieldsName' => "['" . implode("','", $fieldsName) . "'] ",

            //字段的数据类型
            'fieldsType' => '[' . implode(',', $fieldsType) . ']',

            //字段名方法
            'fieldsContent' => $fieldsContent,

            //枚举字段取值的代码
            'enumContent' => $enumContent,

            //主键字段名
            'primaryKey' => table($name)->getPrimaryKey()
        ]);

        //覆盖文件
        write($path, $content);
    }

    /**
     * 生成表对象实例
     * @param $info array 表信息
     */
    static private function tableInstance(array $info): void
    {
        // 将表名中的下划线转换为大写字母
        // 这是表对象类名
        $className = formatter($info['name']);

        // 文件名(不带后缀)
        $fileName = lcfirst($className);

        //如果表名有中文,则保存到ALL文件中
        $hasCN = hasCN($fileName);
        if ($hasCN) {
            $fileName = 'ALL';
        }

        // 构造文件物理路径,可能是系统表对象,也可能是普通表对象,都检查一下
        $path = self::$root . 'program/table/' . $fileName . '.table.php';

        // 如果文件存在,就不动了
        if (is_file($path)) {
            return;
        }

        // 显示生成进度
        echo 'created table instance class of ' . $className . '<br/>' . "\r\n";

        $content = Template::replace(self::getTpl('table/instance'), [
            // 表注释
            'tableDescription' => $info['desc'],

            // 模型类名
            'className' => $className,

            //表名
            'name' => $info['name'],
        ]);

        write($path, $content);
    }

    /**
     * 自动创建表对象
     */
    static public function table(): void
    {
        self::header();

        // 取所有表
        $tables = table()->showTables();

        // 逐个 表检查
        foreach ($tables as $t) {
            // 表名
            $name = array_values($t)[0];

            //表信息
            $info = self::dictTable($name);

            //构造一个表的基类
            self::tableBase($info);

            //构造一个表的子类
            self::tableInstance($info);
        }//结束每个表的循环
    }

    /**
     * 自动创建表对象
     */
    static public function record(): void
    {
        self::header();

        // 取所有表
        $tables = table()->showTables();

        // 逐个 表检查
        foreach ($tables as $t) {
            // 表名
            $name = array_values($t)[0];

            //表信息
            $info = self::dictTable($name);

            //构造记录基类
            self::recordBase($info);

            //构造记录子类
            self::recordInstance($info);
        }
    }

    /**
     * 自动创建一个表对象
     * @param string $table 表名
     */
    static public function tableOne(string $table): void
    {
        self::header();

        //表信息
        $info = self::dictTable($table);

        // 字段信息
        $fields = array_values($info)[2];

        if (empty($fields)) {
            exit('表名称错误，或者此表没有任何字段');
        }

        //构造一个表的基类
        self::tableBase($info);

        //构造一个表的子类
        self::tableInstance($info);
    }

    /**
     * 自动创建一个记录对象
     * @param string $table 表名
     */
    static public function recordOne(string $table): void
    {
        self::header();

        //表信息
        $info = self::dictTable($table);

        // 字段信息
        $fields = array_values($info)[2];

        if (empty($fields)) {
            exit('表名称错误，或者此表没有任何字段');
        }

        //构造记录基类
        self::recordBase($info);

        //构造记录子类
        self::recordInstance($info);
    }

    /**
     * 删除表数据
     */
    public static function deleteTableData(): void
    {
        self::header();

        // 取所有表
        $tables = table()->showTables();

        // 逐个 表检查
        foreach ($tables as $t) {
            // 表名
            $name = array_values($t)[0];
            if (in_array($name, ['addressArea', 'addressCity', 'addressCountry', 'addressProvince', 'adminFunc'])) {
                echo "{$name} Table Needn't Delete. " . '<br/>';
                continue;
            }
            if (in_array($name, ['adminUser', 'adminGroup', 'adminUserGroup'])) {
                table($name)->delete(['id !=' => 1]);
                echo "{$name} Table Delete Success. " . '<br/>';
                continue;
            }
            if ($name == 'adminGroupFunc') {
                table($name)->delete(['groupId !=' => 1]);
                echo "{$name} Table Delete Success. " . '<br/>';
                continue;
            }
            table($name)->execute("DELETE" . " FROM {$name}");
            if (in_array($name, ['atBranch', 'user'])) {
                table($name)->execute("ALTER" . " TABLE {$name} auto_increment = 10001");
                echo "{$name} Table Delete Success. And Set Auto Increment From 10001 Start." . '<br/>';
            } else {
                table($name)->execute("ALTER" . " TABLE {$name} auto_increment=1");
                echo "{$name} Table Delete Success. And Set Auto Increment From 1 Start." . '<br/>';
            }
        }
    }

    /**
     * 数据库升级后的对比
     * @param $configSrc array 新库(源)的连接配置
     * @param $configTrg array 旧库(目标)的连接配置
     */
    public static function compare(array $configSrc, array $configTrg): void
    {
        //连接两个数据库,获取全部表名
        $conSrc = Mysql::connectDatabase($configSrc);
        $conTrg = Mysql::connectDatabase($configTrg);
        $tablesSrc = $conSrc->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        $tablesTrg = $conTrg->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);

        //新增的表,
        $added = array_diff($tablesSrc, $tablesTrg);
        if ($added) {
            dump($added, '新增的表');
        }

        //删除的表
        $deleted = array_diff($tablesTrg, $tablesSrc);
        if ($deleted) {
            dump($deleted, '删除的表');
        }

        //相同的表
        $same = array_intersect($tablesSrc, $tablesTrg);
        foreach ($same as $table) {
            $metaSrc = $conSrc->query('desc ' . $table)->fetchAll(\PDO::FETCH_ASSOC);
            $metaTrg = $conTrg->query('desc ' . $table)->fetchAll(\PDO::FETCH_ASSOC);

            //详细比较一个表
            self::compareTable($table, $metaSrc, $metaTrg);
        }
    }

    /**
     * 比较一个表
     * @param $table string 表名
     * @param $metaSrc array 新表(源)
     * @param $metaTrg array  旧表(目标)
     */
    private static function compareTable($table, array $metaSrc, array $metaTrg): void
    {
        //源表全部字段
        $fieldsSrc = [];
        foreach ($metaSrc as $f) {
            $fieldsSrc[$f['Field']] = $f;
        }

        //目标表全部字段
        $fieldsTrg = [];
        foreach ($metaTrg as $f) {
            $fieldsTrg[$f['Field']] = $f;
        }

        //新增的字段
        $added = [];
        foreach ($fieldsTrg as $fieldName => $fieldConfig) {
            if (!isset($fieldsSrc[$fieldName])) {
                $added[] = $fieldConfig;
                unset($fieldsTrg[$fieldName]);
            }
        }
        if ($added) {
            dump($added, '表:' . $table . ' 删除字段:');
        }

        //删除 的字段
        $deleted = [];
        foreach ($fieldsSrc as $fieldName => $fieldConfig) {
            if (!isset($fieldsTrg[$fieldName])) {
                $deleted[] = $fieldConfig;
                unset($fieldsSrc[$fieldName]);
            }
        }
        if ($deleted) {
            dump($deleted, '表:' . $table . ' 新增字段:');
        }

        //相同的字段
        foreach ($fieldsSrc as $fieldName => $fieldSrc) {
            $fieldTrg = $fieldsTrg[$fieldName];
            //有差别
            if (array_diff_assoc($fieldSrc, $fieldTrg)) {
                dump(['源' => $fieldSrc, '目标' => $fieldTrg], '表:' . $table . ' 字段:' . $fieldName . ' 发生变化');
            }
        }
    }

    /**
     * 修复数据库中的所有表
     */
    public static function repair(): void
    {
        self::header();

        // 取所有表
        $tables = table()->tablesStatus();

        // 逐个表修改
        foreach ($tables as $t) {
            // 表名
            $name = $t['Name'];

            //InnoDB,不进行修改
            if ($t['Engine'] == 'InnoDB') {
                echo "InnoDB needn't repair. table:" . $name . '<br/>';
                continue;
            }

            //显示修复进度,注意,必须是query~~~~,因为exec会出错
            echo "REPAIR Table:" . $name . ' ......';
            table($name)->query('REPAIR TABLE ' . $name);
            echo ' Done!<br/>';
        }//结束每个表的循环
    }

    /**
     * 将所有MyISAM修改为InnoDB
     */
    public static function innodb(): void
    {
        self::header();

        // 取所有表
        $tables = table()->tablesStatus();

        // 逐个表修改
        foreach ($tables as $t) {
            // 表名
            $name = $t['Name'];

            //InnoDB,不进行修改
            if ($t['Engine'] != 'MyISAM') {
                continue;
            }

            //将表修改为Innodb
            echo "ALTER Engine Table:" . $name . ' ......';
            table($name)->execute('ALTER ' . ' TABLE ' . $name . ' ENGINE=INNODB');
            echo ' Done!<br/>';
        }//结束每个表的循环
    }
}