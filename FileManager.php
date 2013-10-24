<?php


class FileManager {
    
    public function find_all_files($dir) 
    { 
        $root = scandir($dir); 
        $result = array();
        foreach($root as $value) 
        { 
            if($value === '.' || $value === '..') {continue;} 
            if(is_file("$dir/$value")) {$result[]="$dir/$value";continue;} 
            foreach($this->find_all_files("$dir/$value") as $value)            
                $result[]=$value;
        } 
        
        return $result; 
    }
    
    public function saveFile($var, $path, $saveToJsonFile) {
                
        if ($saveToJsonFile)
            $fileContent = json_encode($var);
        else {
            $fileContent = '['.date("Y-m-d H:i:s",$var['timestamp'])."]---------------------------------\n";            
            $fileContent .= "- runtime: ".gmdate("H:i:s",$var['runtime'])." hs\n";
            $fileContent .= "- command: {$var['command']}\n";
            
            $filenum=1; //file number tracker
            
            $fileContent .= "-> files: \n";
            if (count($var['files'])==0) $fileContent .= "[!] none\n"; else
            foreach ($var['files'] as $file)  {
                $fileContent .= "[$filenum] in: {$file['input']}\n";
                $fileContent .= "[$filenum] out: {$file['output']}\n";
                $filenum++;
            }
            
            $fileContent .= "-> errors: \n";            
            if (!isset($var['error']) || count($var['error'])==0 ) $fileContent .= "[!] none\n"; else
            foreach ($var['error'] as $file) {                
                $fileContent .= "[$filenum] file: {$file['input']}\n";
                $fileContent .= "[$filenum] err: {$file['error']}";
                $filenum++;
            }
            $fileContent .= "\n";
        }
        
        $ar = fopen($path, "a+");
        fputs($ar, $fileContent);
        fclose($ar);
    }
    
    public function createDirectoryForFile($filepath) {
        $dirpath = $this->getFileDirectory($filepath);
        if (!file_exists($dirpath))
            mkdir($dirpath, 0777, true);
    }
    
    public function getFileDirectory($filepath) {        
        return str_replace("\ ", " ", substr($filepath, 0, strrpos($filepath,'/')));
    }

}
?>