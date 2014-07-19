<?php
class Trackback_Template {
    var $vars; 

    function Trackback_Template($file = null, $prefix = '') {
        $this->file = ($prefix ? $prefix . '/' : '') . $file;
		$this->prefix = $prefix;
    }

    function set($name, $value) {
        $this->vars[$name] = is_object($value) ? $value->fetch() : $value;
    }
	
	function template($file = null) {
		$language = ereg_replace( '[\\|/]', '', getLanguageName());
		$this->file = (file_exists(($this->prefix ? $this->prefix . '/' : '') . $language.'.'.$file))? ($this->prefix ? $this->prefix . '/' : '') . $language.'.'.$file: ($this->prefix ? $this->prefix . '/' : '') . $file;
	}

    function fetch($file = null) {
        if(!$file) $file = $this->file;
		else ($prefix ? $prefix . '/' : '') . $file;
		
		if ($file != null)
		{
	        if (is_array($this->vars)) extract($this->vars);          
        
			ob_start();
	        include($file);
	        $contents = ob_get_contents();
	        ob_end_clean();
        
			return $contents;
		}
    }
}
