<?php


/**
 * Description of aviEpub
 * This if the class for editing the existing epub files as well as developing
 * the new epub files. This class will perform many functions realated to editing files, save files
 * create chapter, edit chapter, save chapter, building of table of contents etc.
 *
 * @author Avinash Singh Rathi
 * @date 22 Dec 2013
 * @company Avi IT Solutions
 * @contact support@aviitsolutions.com
 * @licence MIT licence
 * 
 */

class aviEbook {
    /*
     * These variable store the information regarding the project.
     */

    private $zip = NULL;                      // ZIPArchieve object used in various methods
    private $processdirectory = NULL;         // this is the working directory
    private $workingdirectory = NULL;         // this is the process directory under working directory
    private $opf_file_path = NULL;            // this is the path of the file
    private $ncx_file_path = NULL;            // this is the path of the file

    /*
     * @author Avinash Singh Rathi
     */

    function __construct($workingdirectory, $processdirectory) {
        $this->zip = new ZipArchive();
        $workingdirectory = trim($workingdirectory);
        $processdirectory = trim($processdirectory);
        if ($workingdirectory != NULL AND $processdirectory) {
            $this->processdirectory = $processdirectory;
            $this->workingdirectory = $workingdirectory;
        } else {
            return array("result" => FALSE, "error" => "Directories not set", "errorcode" => 1);
        }
    }

    /*
     * This function is used to open the epub file and extract it to the destination folder
     */

    public function extract_epub($epubpath, $destipath) {
        $epubpath = trim($epubpath);
        $destipath = trim($destipath);
        if (!file_exists($epubpath)) {
            return array("result" => FALSE, "error" => "Epub not found", "errorcode" => 1);
        }

        if (!file_exists($destipath)) {
            if (!$this->recursive_mkdir($destipath, 0777)) {
                return array("result" => FASLE, "error" => "Unable to create directory", "errorcode" => 2);
            }
        }
        $this->zip->open($epubpath);
        if (!$this->zip->extractTo($destipath)) {
            return array("result" => FALSE, "error" => "", "errorcode" => "Extracted succesfully");
        } else {
            return array("result" => TRUE, "data" => $destipath, "message" => "Extracted succesfully");
        }
    }

    /*
     * This function is used to create the recursive directories
     */

    public function recursive_mkdir($path, $mode = 0777) {
        $dirs = explode(DIRECTORY_SEPARATOR, $path);
        $count = count($dirs);
        $path = '.';
        for ($i = 0; $i < $count; ++$i) {
            $path .= DIRECTORY_SEPARATOR . $dirs[$i];
            if (!is_dir($path) && !mkdir($path, $mode, true)) {
                return false;
            }
        }
        return true;
    }

    public function get_opf_file() {
        
    }

    public function get_content_xml_file($directory) {
        
    }

    public function get_chapter_detail() {
        
    }

    public function get_file_path($pattern, $directory) {
        
    }

    /**
     * Calls a function for every file in a folder.
     *
     * @author Avinash Singh Rathi
     */
    public function find_all_files($dir) {
        $dir = trim($dir);
        if (!is_dir($dir)) {
            return array("result" => FALSE, "error" => "Not a valid directory", "errorcode" => 1);
        }
        $iterator1=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        $full_path=array();
        $full_path=array_keys(iterator_to_array($iterator1));
        $filenames=array();
        $arr = $this->objectToArray($iterator1);
        print_r($arr);
        //print_r($full_path);
        }

    function find_specific_pattern_file($dir, $pattern = "*") {
        
    }

    function get_list_file($dir, $pattern = "*") {
        $iterator = new GlobIterator($dir."*.xml");
        $filelist = array();
        foreach ($iterator as $entry) {
            $filelist[] = $entry->getFilename();
        }
        print_r($filelist);
    }
    
    function objectToArray($d) {
    if (is_object($d)) {
        // Gets the properties of the given object
        // with get_object_vars function
        $d = get_object_vars($d);
    }

    if (is_array($d)) {
        /*
        * Return array converted to object
        * Using __FUNCTION__ (Magic constant)
        * for recursive call
        */
        return array_map(__FUNCTION__, $d);
    }
    else {
        // Return array
        return $d;
    }
}

}

?>
