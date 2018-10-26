
	/**
	 * 根据ID获取一条记录
	 * @param int $id 编号ID
	 * @return R{$className}
	 */
	public function info($id) // : R{$className}
	{
		return $this->row(self::$fields,$id);
	}

	/**
	* 取一条记录,将父类查询结果实例为Row的对象
	* @param $fields mixed 要查询的字段
	* @param $where mixed 查询条件
	* @param $orderBy mixed 排序
	* @return R{$className}
	*/
	public function row ($fields = null, $where = null, $orderBy = null) // : R{$className}
	{
		$instance = parent::row($fields,$where,$orderBy)->toRecord('R{$className}');
		/**
		 * @var $instance R{$className}
		 */
		return $instance;
	}