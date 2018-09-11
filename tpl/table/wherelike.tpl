		
		//拼接 <{$description}>条件
		if(isset($info['{$name}_like']) and $info['{$name}_like']){
			$where['{$name} like']='%'.$info['{$name}_like'].'%';
		}
		if(isset($info['{$name}']) and $info['{$name}']){
			$where['{$name}']=$info['{$name}'];
		}
		