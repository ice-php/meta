		
		//拼接 <{$description}>条件
		if(isset($info['{$name}_begin']) and $info['{$name}_begin']){
			$where['{$name} >']=$info['{$name}_begin'];
		}		
		if(isset($info['{$name}_end']) and $info['{$name}_end']){
			$where['{$name} <']=$info['{$name}_end'];
		}		
		