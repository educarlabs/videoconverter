<?php
require 'FileManager.php';
require 'Regex.php';
/*   
 
 *** Video Converter ***
 
  Ejemplos Invocaciones
  
  -i /Users/gustavo/Downloads/1Test/filepaths.txt -o /Users/gustavo/conver --parseFiles
  -i /Users/gustavo/Downloads/1Test/1/Funcionales/OficiosCursodeterminacionesT01C07_SD_PA-PP-10060pa-pp-10601.mp4 -o /Users/gustavo/convert
  -i /Users/gustavo/Downloads/1Test/1 -o /Users/gustavo/Downloads/1Test/to --regex /.*(mp4|flv|avi)$/
  -i /Users/gustavo/Downloads/1Test/1/Funcionales/OficiosCursodeterminacionesT01C07_SD_PA-PP-10060pa-pp-10601.mp4 -o /Users/gustavo/conver --regex /.*(avi|flv)$/
  -i /Users/gustavo/original -o /Users/gustavo/original_convertidos

 * Casos:

- Parseo desde directorio (con regex default)

php VideoConverter.php -i /repositorio/conectate/desktop/ -o /repositorio/conectate/movil/

- Parseo desde directorio CON regex especificado.

php VideoConverter.php -i /media/usbdrive/ -o /repositorio/conectate/movil  --regex /.*(mp4|flv|avi)$/

- Parseo desde UN UNICO ARCHIVO DE VIDEO (omite --regex o configuracion default)

php VideoConverter.php -i /media/usbdrive/zamba.avi -o /repositorio/conectate/movil 

- Parseo desde lista de archivos (omite regex default)

php VideoConverter.php -i /media/usbdrive/archivos_a_procesar.txt -o /repositorio/conectate/movil  --inputFileList

- Parseo desde lista de archivos APLICA regex por parametro

php VideoConverter.php -i /media/usbdrive/archivos_a_procesar.txt -o /repositorio/conectate/movil  --inputFileList --regex /.*(mp4|flv|avi)$/

*/

$converter = new Converter($argv);
$converter->processFiles();


/**
* Converter
*
* Clase que provee mecanismos de conversión de video (mútiples fuentes) especificados 
* desde argumentos de consola.
* 
* @author   Gustavo Gramajo <gustavo@pigmalionstudios.com.ar>
* @version  v1.0
* @access   public
*/
class Converter {
            
    //Nombres de parámetros de consola
    const INPUT = "i";
    const OUTPUT = "o";    
    const PARSE_FILES = "parseFiles";
    const REGEX = "regex"; 
    const JOBFILE = "jobfile"; 
    const VALOR_NULO = "";

    //Helpers / Funcionalidad anexa    
    private static $regexer;
    private static $filePaths;
    private static $fileManager;
    
    //Valores de parámetros
    private static $input_folder_path;
    private static $output_path;
    private static $path_filePaths;
    private static $full_command;
    private static $config;    
    private static $regexvalue;
    private static $jobfilevalue;
      
   
    /*
    * Constructor
    *
    * @param (string) argumentos desde consola
    * @return (null)
    */
    public function __construct($raw_args) {
        self::$fileManager = new FileManager();
        self::$full_command = "";
        
        foreach ($raw_args as $key => $value)
            self::$full_command .= $value . " ";
        
        self::$full_command = substr(self::$full_command, 0, strlen(self::$full_command) - 1);
                               
        $rutaIniFile = preg_replace("/\.[0-9a-z]{1,5}$/", ".ini", realpath( __FILE__ )); //archivo ini = mismo nombre ejecutable pero c/extension .ini        
        if (!is_readable($rutaIniFile)) {
            echo "Error: el archivo de configuración  $rutaIniFile no existe o no es accesible\n";
            exit(1); 
        }
        self::$config = parse_ini_file($rutaIniFile); //parsing de config (ini)
        
        $this->parseArgs(); //parseo de args desde commandline
        
        print_r("Conversión de Videos v1.0 \n[usando config: " . $rutaIniFile . 
                " | ffmpeg: " . self::$config["ruta_ffmpeg"] . " | perfil: " . self::$config["command_template"] . " | output: " . self::$output_path . "]\n\n"); 
        

        $this->sanityCheck();
        
        $this->setFilePaths();
    }
            
    
    /*
    * Checkea sanidad de los parámetros que se utilizarán
    *
    * @param (nul) 
    * @return (boolean) true o exit
    * @author jparedes
    */       
    private function sanityCheck() {
 
        // INI -----------------
        if (!is_executable(self::$config["ruta_ffmpeg"])) {
            echo "Error: el parámetro 'ruta_ffmpeg' establecido en la configuración (ini) no es un ejecutable válido \n";
            exit(1);            
        }                
        
        if (!is_writable(self::$config['jobs_dir']) ) {
            
            $condition = (empty(self::$jobfilevalue)) ? array('fatal'=>true,'msg'=>'Error') : array('fatal'=>false,'msg'=>'Warning');
            
            echo "{$condition['msg']}: el parámetro 'jobs_dir' establecido en la configuración (ini) no existe o no tiene permisos de escritura \n";                                           
            if ($condition['fatal']) exit(1); 
        
        } else self::$config['jobs_dir']=rtrim(self::$config['jobs_dir'],DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR; //normalizo 
        
        if (!is_dir(self::$config["error_log_dir"])) {
            echo "Error: el parámetro 'error_log_dir' establecido en la configuración (ini) no es un directorio válido \n";
            exit(1);
        } else self::$config['error_log_dir']=rtrim(self::$config['error_log_dir'],DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR; //normalizo 
        
        // Console Args  -----------------
        
         if (empty(self::$input_folder_path)) {
            print_r("Error: El parametro -" . self::INPUT . " no se ha hallado. Se puede obtener informacion de uso invocando este script con -h como parametro \n");
            exit(1);
        }

        if (empty(self::$output_path)) {
            print_r("Error: El parametro -" . self::OUTPUT . " no se ha hallado. Se puede obtener informacion de uso invocando este script con -h como parametro \n");
            exit(1);
        }                       
        
        if (!empty(self::$jobfilevalue)) {

            //Verifico que el parametro no sea un directorio
            if (is_dir(self::$jobfilevalue)) {
                print_r("Error: El parámetro " . self::JOBFILE . " no puede ser un directorio\n");
                exit(1);
            }
            
            //En el caso que sea un archivo, verificar si se poseen permisos de escritura.
            if (is_file(self::$jobfilevalue) && !is_writable(self::$jobfilevalue)) {
                print_r("Error: El archivo " . self::$jobfilevalue . " no posee permisos de escritura\n");
                exit(1);
            }
            
            //Si el archivo no existe verifico que el directorio que lo contendrá posee permisos d escritura
            if (!is_writable(dirname(self::$jobfilevalue))) {
                print_r("Error: El directorio " . dirname(self::$jobfilevalue) . " no posee permisos de escritura\n");
                exit(1);
            } 
                       
        }               
        
        return true; //smooth :)        

    }
    
    /*
    * Setea paths de archivos en función de los parámetros de configuración y 
    * establecidos a través de linea de comandos.
    *
    * @param (nul) 
    * @return (null)
    */
    private function setFilePaths() {
        self::$filePaths = self::VALOR_NULO;
        
        if (self::$regexvalue == self::VALOR_NULO) 
            self::$regexvalue = self::$config["regex"];
        
        self::$regexer = new Regex(self::$regexvalue);
        
        if (self::$path_filePaths != self::VALOR_NULO) {
            $rel_file_paths = $this->parseFilePaths_File();
            self::$filePaths = self::$regexer->applyOnStringArray($rel_file_paths);
        }
        else {            
            if (file_exists(self::$input_folder_path)) {
                if (is_dir(self::$input_folder_path)) {
                    $rel_file_paths = self::$fileManager->find_all_files(self::$input_folder_path);
                    self::$filePaths = self::$regexer->applyOnStringArray($rel_file_paths);
                }
                else {
                    self::$filePaths = array(self::$input_folder_path);
                    self::$input_folder_path = self::$fileManager->getFileDirectory(self::$input_folder_path);
                }
            }
            else {                
                print_r("Error: se ha suministrado como input la ruta \"" . self::$input_folder_path . "\", la cual hace referencia a un archivo o directorio inexistente. \n");
                exit(1);                
            }
        }
                
    }    

    
    /*
    * Calcula los paths de archivos a procesar en base al input self::$path_filePaths
    *
    * @param (null) 
    * @return (array) directorios 
    */
    private function parseFilePaths_File() {
        $rel_file_paths = array(); 

        if (!file_exists(self::$path_filePaths)) {
             print_r("Error al parsear el archivo " . self::$path_filePaths . ": archivo inexistente \n");
             exit(1);
        }
        if (is_dir(self::$path_filePaths)) {
             print_r("Error al parsear el archivo " . self::$path_filePaths . ": la ruta apunta a un directorio \n");
             exit(1);
        }   

        self::$input_folder_path = self::$fileManager->getFileDirectory(self::$path_filePaths);
        $filepaths_FILE = fopen(self::$path_filePaths, "r");

        while (($buffer = fgets($filepaths_FILE)) !== false) {
             $buffer = str_replace(array("\r", "\n"), '', $buffer);
             $rel_file_paths[] = self::$input_folder_path . "/" . $buffer;
        }

        fclose($filepaths_FILE);
            
        return $rel_file_paths;
    }
    
    /*
    * Procesa los archivos a convertir
    *
    * @param (null) 
    * @return (null)
    */
    public function processFiles() {        
        $timestamp_inicial = time();
        
        $log = array();
        $log["timestamp"] = $timestamp_inicial;
        $log["files"] = array();
        
        $error_log = array();

        
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin
            1 => array("pipe", "w"),  // stdout
            2 => array("pipe", "w"),  // stderr
         );
        
        $cantErroneos = 0;
        $cantOk = 0;
        $saveJob = false;         
        
        $jobPath = (isset(self::$jobfilevalue)) ?  self::$jobfilevalue : 
        self::$config["jobs_dir"]."job_". $timestamp_inicial .".job";
        
        foreach (self::$filePaths as $key => $currentInputPath) {   
            $saveJob = true;
            
            $currentInputPath = str_replace(" ", "\ ", $currentInputPath);
            $currentInputPath = str_replace("\\\\", "\\", $currentInputPath);//si los paths ya llegasen "escapeados", remuevo el escape anterior
            $currentOutputFile = str_replace(self::$input_folder_path, self::$output_path, $currentInputPath);
            $currentOutputFile = str_replace(" ", "\\ ", $currentOutputFile);
            $currentOutputFile = str_replace("\\\\", "\\", $currentOutputFile);
              
            $currentInputPath_withoutBackslashes = str_replace("\\", "", $currentInputPath);
            $currentOutputFile_withoutBackslashes = str_replace("\\", "", $currentOutputFile);
            
            if (file_exists($currentOutputFile_withoutBackslashes)) {
                //$output_len = strlen($currentOutputFile_withoutBackslashes);
                $extension_pos = strrpos($currentOutputFile, ".");
                $path_without_extension  = substr($currentOutputFile, 0, $extension_pos);
                $extension = substr($currentOutputFile, $extension_pos);
                $uniqID = uniqid(time());
                $currentOutputFile = $path_without_extension . "_" . $uniqID . $extension;  
                $currentOutputFile_withoutBackslashes = str_replace("\\", "", $currentOutputFile);
            }
            
            $command = self::$config["ruta_ffmpeg"] . ' -i ' . $currentInputPath . ' ' . self::$config["command_template"] . ' ' . $currentOutputFile . ' -y';
            
            $input_output = array("input" => $currentInputPath_withoutBackslashes,
                                  "output" => $currentOutputFile_withoutBackslashes);
                        
            self::$fileManager->createDirectoryForFile($currentOutputFile);
            $process = proc_open($command, $descriptorspec, $pipes, dirname(__FILE__), null);
            
            print_r("Convirtiendo: " . $currentInputPath . "...");
            
            
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            
            $input_output_with_results = $input_output;
            if (proc_close($process) == 1) {
                print_r("ERROR\n");
                $cantErroneos++;                
                $input_and_stdErr = array("input" => realpath($currentInputPath_withoutBackslashes), "error" => $stderr);
                $log["error"][] = $input_and_stdErr;
                
                if (file_exists($currentOutputFile_withoutBackslashes)) {                    
                    shell_exec("rm -f " . $currentOutputFile);   
                }
            } else {
                $input_output['output']=realpath($input_output['output']);
                $log["files"][] = $input_output;
                print_r("ok\n");
                $cantOk++;
            }                      

        }
        
        print_r("\nTotal: " . count(self::$filePaths) . " archivos  (Ok: " . $cantOk . " Error: " . $cantErroneos . ")\n");
        
        $timestamp_final = time();
        
        $log["command"] = self::$full_command;
        $log["runtime"] = $timestamp_final - $timestamp_inicial;
        
        if ($saveJob) {
            self::$fileManager->saveFile($log, $jobPath, true);
            print_r("Resumen job: " . $jobPath . "\n");
        }
              
        self::$fileManager->saveFile($log, self::$config["error_log_dir"] . "videoconverter.log", false);

        $mins = floor($log["runtime"] / 60);
        $secs = $log["runtime"] % 60;
        print_r("Total runtime: " . $mins . "m" . $secs . "s \n");
    }
    
        
    /*
    * Parseo de argumentos desde línea de comandos a variables estáticas de clase 
    *
    * @param (null) 
    * @return (null) 
    */
    private function parseArgs() {
        self::$path_filePaths = self::VALOR_NULO;
        self::$regexvalue = self::VALOR_NULO;
        $shortopts  = "h" . self::INPUT . ":" . self::OUTPUT . ":";
        $longopts  = array(self::PARSE_FILES, self::REGEX .":",self::JOBFILE.':');
        $options = getopt($shortopts, $longopts);

        if (isset($options['h'])) {
            print_r("-i  Ruta absoluta hacia un archivo o directorio. No puede terminar en / en ningun caso. Si es una ruta hacia un directorio, se filtraran todos sus archivos en base a la regex correspondiente.\n
                     -o  Ruta absoluta. Indica en donde se escribiran los videos convertidos.\n
                     --parseFiles  Indica que el parametro -i hace referencia a una ruta absoluta hacia un archivo de texto plano que contiene una ruta relativa a un archivo de video por linea.\n
                     --regex  Expresion regular que se usara, descartando a la existente en el archivo de configuracion (.ini).");        
            exit();
        }
        else {
                if (isset($options[self::INPUT])) 
                    self::$input_folder_path = $options[self::INPUT];   
              
                if (isset($options[self::OUTPUT])) 
                    self::$output_path = rtrim($options[self::OUTPUT],DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR; //jparedes - debe ser siempre una carpeta
                
                if (isset($options[self::PARSE_FILES]))
                    self::$path_filePaths = self::$input_folder_path;

                if (isset($options[self::REGEX]))
                    self::$regexvalue = $options[self::REGEX];

                if (isset($options[self::JOBFILE]))
                    self::$jobfilevalue = $options[self::JOBFILE];
            }                                 
    }
}
?>