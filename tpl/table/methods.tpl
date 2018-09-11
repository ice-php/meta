 	/**
     * 根据ID获取<{$description}>的值
     * @param int id 主键编号
     * @return {$type} 
     */                            
    public function get{$fName}($id)
    {
        return $this->get('{$name}',$id);
    }                            
    
    /**
     * 根据ID修改<{$description}>的值
     * @param int id 主键编号
     * @param mixed $value 要修改的值
     * @return bool
     */
    public function set{$fName}($id,$value)
    {
        return $this->update(['{$name}'=>$value],$id);
    }
    