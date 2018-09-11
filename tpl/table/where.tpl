		
		//拼接 <{$description}>条件
		if(isset($info['{$name}']) and $info['{$name}']){
			$where['{$name}']=$info['{$name}'];
		}
