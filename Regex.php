<?php
class Regex {
    
    private static $regex = null;
    
    public function __construct($regexParam) {
        self::$regex = $regexParam;
    }
    
    public function applyOnStringArray($stringArray) {
        $filtered_results = array();
        
        foreach ($stringArray as $key => $value) {
            if (preg_match(self::$regex, $value))
                $filtered_results[] = $value;
        }
        
        return $filtered_results;
    }

    
}
?>