<?php
/**
 * {$tableDescription}({$name}) 表对象基类
 * @auth 蓝冰大侠
 */
declare(strict_types=1);

use icePHP\Table;

use function icePHP\page;

class T{$baseName} extends Table{
    /**
     * 单例句柄
     * @var T{$className}
     */
    protected static $handle;
    
    /**
     * 构造方法
     * @throws \Exception
     */
    protected function __construct ()
    {
        parent::__construct('{$name}');
    }
    
    /**
     * 单例化
     *
     * @return T{$className}
     */
    public static function instance ()
    {
        return parent::instance();
    }

    //主键字段
    public static $primaryKey="{$primaryKey}";

    //列表默认行数
    public static $_pageSize=10;

    {$methods}    
	//本表常用字段列表
	public static $fields={$fieldsName};

	{$fieldsContent}
	{$enumContent}

	/**
	 * 根据搜索条件构造查询条件
	 * @param array $info 搜索条件
	 * @return array 查询条件
	 */
	private function searchWhere(array $info): array
	{
		//初始化搜索条件
        $where=[];
		{$searchWhere}

		//返回查询条件
		return $where;
	}

	/**
	 * 分页搜索及列表功能,此功能需要开发人员进行完善
	 * @param array $info 搜索条件 
	 * @return array[R{$className}]
	 * @throws \Exception
	 */
    public function search(array $info) // : array
    {
    	//获取查询条件
		$where=$this->searchWhere($info);
		
		//计数满足条件的用户
		$count = $this->count ( $where );
		
		//让分页控件记录数量及条件
		page(self::$_pageSize,self::$primaryKey,'desc')->count ( $count );
		page()->where ( $info );

		//没有查询到数据
		if(!$count){
			return [];
		}

		//查询
		$list = $this->select ( self::$fields, $where,page()->orderby(), page()->limit () )	;

		//返回查询到的数据 , Result对象
		return $list->toRecords('R{$className}');
    }

	/**
	 * 根据条件,查询需要导出的数据
	 * @param array $info 搜索条件
	 * @param array $fields 要查询的字段列表
	 * @return Iterator
	 * @throws \Exception
	 */
	public function export(array $info,array $fields): Iterator
	{
		//获取查询条件
		$where=$this->searchWhere($info);

		//生成查询句柄
		$handle=$this->selectHandle($fields,$where,page()->orderby());

		while($row=$handle->fetch(PDO::FETCH_ASSOC)){
			yield $row;
		}
	}

	{$info}
}

