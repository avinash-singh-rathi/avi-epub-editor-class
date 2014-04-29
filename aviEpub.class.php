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

define('Java_Path', '/usr/bin/java');
define('Epub_check_Java', __SITE_PATH_ . '/editor/classes/library/epubcheck.jar');

class aviEbook {
    /*
     * These variable store the information regarding the project.
     */

    private $zip = NULL;                      // ZIPArchieve object used in various methods
    private $processdirectory = NULL;         // this is the working directory
    private $workingdirectory = NULL;         // this is the process directory under working directory
    private $opf_file_path = NULL;            // this is the path of the file
    private $ncx_file_path = NULL;            // this is the path of the file
    private $java = Java_Path;                  // path of the java
    private $epubcheck = NULL;                  // this includes the location of java as well as the location of the epub check jar file
    private $epubcheck_string = 'No errors or warnings detected.';
    private $file_list = array();             // this is the list of the files of processing directory
    private $dom = NULL;
    private $xhtml11doctype = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">';
    private $htmltag = '<html xmlns="http://www.w3.org/1999/xhtml">';
    private $opfNS = "http://www.idpf.org/2007/opf"; // NO trailing slash
    private $dcNS = "http://purl.org/dc/elements/1.1/"; // NEEDS trailing slash to validate
    private $fun = NULL;
    private $comment_file = NULL;

    /*
     * @author Avinash Singh Rathi
     */

    function __construct($workingdirectory, $processdirectory) {
        $this->zip = new ZipArchive();
        $this->dom = new DOMDocument('1.0', 'utf-8');
        $this->epubcheck = $this->java . ' -jar ' . Epub_check_Java;
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

    public function add_chapter($title) {
        $res = $this->_add_chapter($title);
        return $res;
    }

    private function _add_chapter($title) {
        //$format="xml";
        $format = $this->_get_chp_format();
        $file_name = "domb-chapter-" . uniqid();
        $opf_dir = dirname($this->opf_file_path);
        $css_res = $this->_get_css();
        $source = '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
                    <head>
                    <meta name="generator" content="Dombive" />
                    <title>' . $title . '</title>';
        if ($css_res['result'] == TRUE) {
            foreach ($css_res['data'] as $css) {
                $source.='<link rel="stylesheet" href="' . $css['href'] . '" type="text/css" />
                    ';
            }
        }
        $source.='<meta http-equiv="Content-Type" content=
                    "application/xhtml+xml; charset=utf-8" />
                    </head>
                    <body>
                    <h1>' . $title . '</h1>
                     </body>
                    </html>';

        $xmlDoc = new DOMDocument();
        @$xmlDoc->loadHTML($source);
        $head = $xmlDoc->getElementsByTagName("head");
        $body = $xmlDoc->getElementsByTagName("body");

        $xml = new DOMDocument('1.0', "utf-8");
        $xml->lookupPrefix("http://www.w3.org/1999/xhtml");
        $xml->preserveWhiteSpace = FALSE;
        $xml->formatOutput = TRUE;

        $xml2Doc = new DOMDocument('1.0', "utf-8");
        $xml2Doc->lookupPrefix("http://www.w3.org/1999/xhtml");
        $xml2Doc->loadXML("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\"\n	\"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\n</html>\n");
        $html = $xml2Doc->getElementsByTagName("html")->item(0);
        $html->appendChild($xml2Doc->importNode($head->item(0), TRUE));
        $html->appendChild($xml2Doc->importNode($body->item(0), TRUE));

        // force pretty printing and correct formatting, should not be needed, but it is.
        $xml->loadXML($xml2Doc->saveXML());
        $doc = $xml->saveXML();
        $xml->save($opf_dir . "/" . $file_name . "." . $format);
        $this->_add_chap_ncx($file_name . "." . $format, $file_name, $title);
        $this->_add_chap_opf_mani($file_name . "." . $format, "application/xhtml+xml", $file_name);
        $this->_add_chap_opf_spine($file_name);
        return $file_name;
    }

    private function _add_chap_opf_mani($filename, $media, $id) {
        $this->get_opf_file();
        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = FALSE;
        $xml->recover = true;
        $xml->formatOutput = true;
        $xml->load($this->opf_file_path);
        $root = $xml->getElementsByTagName('manifest')->item(0);
        $div = $xml->createElement("item");
        $div->setAttribute("id", $id);
        $div->setAttribute("href", $filename);
        $div->setAttribute("media-type", $media);
        $root->appendChild($div);
        $xml->saveXML();
        $xml->save($this->opf_file_path);
    }

    private function _add_chap_opf_spine($id) {
        $this->get_opf_file();
        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = FALSE;
        $xml->recover = true;
        $xml->formatOutput = true;
        $xml->load($this->opf_file_path);
        $root = $xml->getElementsByTagName('spine')->item(0);
        $div = $xml->createElement("itemref");
        $div->setAttribute("idref", $id);
        $root->appendChild($div);
        $xml->saveXML();
        $xml->save($this->opf_file_path);
    }

    private function _add_chap_ncx($filename, $id, $chapter) {
        $order = $this->_ncx_order();
        $this->get_ncx_file();
        if($this->ncx_file_path==NULL ||$this->ncx_file_path==""){
            $this->ncx_file_create();
            $this->get_ncx_file();
        }
        $this->ncx_file_path;
        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = FALSE;
        $xml->recover = true;
        $xml->formatOutput = true;
        $xml->load($this->ncx_file_path);
        $root = $xml->getElementsByTagName('navMap')->item(0);
        $div = $xml->createElement("navPoint");
        $div->setAttribute("id", $id);
        $div->setAttribute("playOrder", $order + 1);
        $div1 = $xml->createElement('navLabel');
        $text = $xml->createElement('text', $chapter);
        $div1->appendChild($text);
        $div->appendChild($div1);
        $cont = $xml->createElement('content');
        $cont->setAttribute("src", $filename);
        $div->appendChild($cont);
        $root->appendChild($div);
        $xml->saveXML();
        $xml->save($this->ncx_file_path);
    }

    private function _get_chp_format() {
        $this->get_opf_file();
        $dat = $this->_get_chapters_details();
        if ($dat['result'] == TRUE) {
            $data = $dat['data'][0][href];
            $info = new SplFileInfo($data);
            return $info->getExtension();
        } else {
            return ".xml";
        }
    }

    public function ncx_file_create() {
        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = FALSE;
        $xml->recover = true;
        $xml->formatOutput = true;
        $source = '<?xml version="1.0" encoding="utf-8"?>
        <ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
        <head>
        <meta name="cover" content="cover"/>
        <meta name="dtb:depth" content="1"/>
        <meta name="dtb:totalPageCount" content="0"/>
        <meta name="dtb:maxPageNumber" content="0"/>
        </head>
        <docTitle>
        <text>Dombive Book</text>
        </docTitle>
        <navMap>
        </navMap>
        </ncx>
        ';
        $xml->loadXML($source);
        $xml->saveXML();
        $this->get_opf_file();
        $dir=  dirname($this->opf_file_path);
        $xml->save($dir."/toc.ncx");
        $this->_add_manifest_ncx("toc.ncx","application/x-dtbncx+xml","ncx");
        $this->_add_spine_ncx("ncx");
    }
    
    /*
     * This function is used to add the table of content attribute to spine
     * 
     */
    
    private function _add_spine_ncx($id){
        $this->get_opf_file();
        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = FALSE;
        $xml->recover = true;
        $xml->formatOutput = true;
        $xml->load($this->opf_file_path);
        $root = $xml->getElementsByTagName('manifest')->item(0);
        $root->setAttribute("toc",$id);
        echo $xml->saveXML();
        $xml->save($this->opf_file_path);
    }
    
    /*
     * 
     * This function is used when ncx is added
     * 
     */
    
    private function _add_manifest_ncx($filename, $media, $id)
    {
        $this->get_opf_file();
        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = FALSE;
        $xml->recover = true;
        $xml->formatOutput = true;
        $xml->load($this->opf_file_path);
        $root = $xml->getElementsByTagName('manifest')->item(0);
        $div = $xml->createElement("item");
        $div->setAttribute("id", $id);
        $div->setAttribute("href", $filename);
        $div->setAttribute("media-type", $media);
        $root->appendChild($div);
        $xml->saveXML();
        $xml->save($this->opf_file_path);
    }
    
    /*
     * This function sets all the chapters at the ncx file
     * 
     */
    function set_all_chapters_ncx(){
        if($this->ncx_file_path==NULL ||$this->ncx_file_path==""){
            $this->ncx_file_create();
            $this->get_ncx_file();
        }
    }
    
    
    /*
     * This function fetch the last order of the chapter from the ncx file
     */

    private function _ncx_order() {
        $order = 0;
        $r = $this->get_ncx_file();
        if ($r['result'] == TRUE) {
            $xml = new DOMDocument();
            $xml->load($this->ncx_file_path);
            $dt = $xml->getElementsByTagName('navPoint');
            foreach ($dt as $dt1) {
                if ($dt1->getAttribute('playOrder') > $order) {
                    $order = $dt1->getAttribute('playOrder');
                }
            }
            return $order;
        } else {
            return FALSE;
        }
    }

    /*
     * Delete the chapter using the id
     * 
     */

    public function delete_chapter($chapter) {
        $this->_delete_chapter($chapter);
    }

    private function _delete_chapter($chapter) {
        $res = $this->get_file_path_id($chapter);
        if ($res['result'] == TRUE) {
            foreach ($res['data'] as $tt) {
                $filename = $tt;
            }
            if (file_exists($filename)) {
                unlink($filename);
            }
            $this->_delete_chap_mani($chapter);
            $this->_delete_chap_spine($chapter);
            $this->_delete_chap_ncx($chapter);
        }
    }

    private function _delete_chap_ncx($chapter) {
        $this->get_ncx_file();
        $this->ncx_file_path;
        if($this->get_ncx_file==NULL ||$this->get_ncx_file==""){
            $this->ncx_file_create();
        }
        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = FALSE;
        $xml->recover = true;
        $xml->formatOutput = true;
        $xml->load($this->ncx_file_path);
        $root = $xml->getElementsByTagName('navMap');
        $xpath = new DOMXpath($xml);
        $nodeList = $xpath->query('////*[@id="' . $chapter . '"]');
        if ($nodeList->length) {
            $node = $nodeList->item(0);
            $node->parentNode->removeChild($node);
        }
        $xml->saveXML();
        $xml->save($this->ncx_file_path);
    }

    private function _delete_chap_mani($chapter) {
        $this->get_opf_file();
        $this->opf_file_path;
        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = FALSE;
        $xml->recover = true;
        $xml->formatOutput = true;
        $xml->load($this->opf_file_path);
        $xpath = new DOMXpath($xml);
        $nodeList = $xpath->query('////*[@id="' . $chapter . '"]');
        if ($nodeList->length) {
            $node = $nodeList->item(0);
            $node->parentNode->removeChild($node);
        }
        $xml->saveXML();
        $xml->save($this->opf_file_path);
    }

    private function _delete_chap_spine($chapter) {
        $this->get_opf_file();
        $this->opf_file_path;
        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = FALSE;
        $xml->recover = true;
        $xml->formatOutput = true;
        $xml->load($this->opf_file_path);
        $xpath = new DOMXpath($xml);
        $nodeList = $xpath->query('////*[@idref="' . $chapter . '"]');
        if ($nodeList->length) {
            $node = $nodeList->item(0);
            $node->parentNode->removeChild($node);
        }
        $xml->saveXML();
        $xml->save($this->opf_file_path);
    }

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

    public function get_list_all_files() {
        $res = $this->find_all_files($this->workingdirectory . $this->processdirectory);
        if ($res['result'] == TRUE) {
            $this->file_list = $res['data'];
        }
    }

    public function get_opf_file() {
        $this->get_list_all_files();
        $files = array_filter($this->file_list, function($a) {
                    return preg_match("/(\.opf)$/i", $a);
                });
        if (!empty($files)) {
            foreach ($files as $result) {
                $filename = $result;
            }
            $this->opf_file_path = $filename;
            return array("result" => TRUE, "data" => $filename, "message" => "OPf file found");
        } else {
            return array("result" => FALSE, "error" => "OPF file not found", "errorcode" => 1);
        }
    }

    public function get_content_xml_file($directory) {
        
    }

    public function get_chapter_detail() {
        
    }

    public function get_file_path($pattern, $directory) {
        
    }

    public function get_file_path_href($href) {
        return $this->_get_file_path_href($href);
    }

    private function _get_file_path_href($href) {
        $href = trim($href);
        if ($href != NULL) {
            $this->get_list_all_files();
            $arr = $this->file_list;
            $array1[1] = $href;
            $href = str_replace('/', '\/', $href);
            $files = array_filter($arr, function($a) use ($href) {
                        return preg_match("/($href)$/i", $a);
                    });
            if (!empty($files)) {
                return array("result" => TRUE, "data" => $files, "message" => "Data found");
            } else {
                return array("result" => FALSE, "error" => "Data not found", "errorcode" => 2);
            }
        } else {
            return array("result" => FALSE, "error" => "Href is blank", "errorcode" => 1);
        }
    }

    public function get_file_path_id($id) {
        return $this->_get_file_path_id($id);
    }

    private function _get_file_path_id($id) {
        $id = trim($id);
        if ($id != NULL) {
            $arr = $this->_get_manifest_item();
            if ($arr['result'] == TRUE) {
                $arr = $arr['data'];
                $res = $this->_search_array($arr, 'id', $id);
                if (!empty($res)) {
                    $href = $res[0]['href'];
                    return $this->_get_file_path_href($href);
                }
            } else {
                return array("result" => FALSE, "error" => "Data not found", "errorcode" => 2);
            }
        } else {
            return array("result" => FALSE, "error" => "ID is blank", "errorcode" => 1);
        }
    }

    public function get_chapters_details() {
        return $this->_get_chapters_details();
    }

    private function _get_chapters_details() {
        $this->get_opf_file();
        $contents = file_get_contents($this->opf_file_path);
        if (preg_match('/<!DOCTYPE[^>]+?xhtml 1.1[^>]+?>/im', $contents)) {
            $contents = preg_replace('/<!DOCTYPE[^>]+?>/m', $this->xhtml11doctype, $contents);
        }

        $contents = preg_replace('/<html[^>]+?>/m', $this->htmltag, $contents);
        $this->dom->loadXML($contents);
        $test = $this->dom->getElementsByTagName('spine')->item(0);
        $ch = array();
        $test1 = $test->getElementsByTagName('itemref');
        foreach ($test1 as $test2) {
            $ch[] = $test2->getAttribute('idref');
        }
        $res = $this->_get_manifest_item();
        if ($res['result'] == TRUE) {
            $elements = $res['data'];
            if (!empty($elements) AND !empty($ch)) {
                foreach ($ch as $chap) {
                    $tm = $this->_search_array($elements, 'id', $chap);
                    $tk[] = $tm[0];
                }
                if (!empty($tk)) {
                    return array("result" => TRUE, "data" => $tk, "message" => "Retrieved the chapters successfully");
                } else {
                    return array("result" => FALSE, "error" => "Unable to get the chapters property", "errorcode" => 2);
                }
            }
        } else {
            return array("result" => FALSE, "error" => "chapters not found!", "errorcode" => 1);
        }
    }

    private function _get_element_by_id($id) {
        $res = $this->_get_manifest_item();
        if ($res['result'] == TRUE) {
            $arr = $res['data'];
            if (!empty($arr)) {
                $arrt = $this->_search_array($arr, 'id', $id);
                if (!empty($arrt)) {
                    return array("result" => TRUE, "data" => $arrt, "message" => "Data fetched");
                } else {
                    return array("result" => FALSE, "error" => "Data not found", "errorcode" => 3);
                }
            } else {
                return array("result" => FALSE, "error" => "Element not found", "errorcode" => 2);
            }
        } else {
            return array("result" => FALSE, "error" => "Element not found", "error" => 1);
        }
    }

    public function get_element_by_id($id) {
        return $this->_get_element_by_id($id);
    }

    public function get_css() {
        return $this->_get_css();
    }

    private function _get_css() {
        $res = $this->_get_manifest_item();
        if ($res['result'] == TRUE) {
            $ar = $res['data'];
            $css_files = $this->_search_array($ar, 'media-type', 'text/css');
            if (!empty($css_files)) {
                return array("result" => TRUE, "data" => $css_files, "message" => "CSS files found");
            } else {
                return array("result" => FALSE, "error" => "CSS file not found", "errorcode" => 2);
            }
        } else {
            return array("result" => FALSE, "error" => "CSS not found", "errorcode" => 1);
        }
    }

    function search_array($array, $key, $value) {
        $results = array();

        if (is_array($array)) {
            if (isset($array[$key]) && $array[$key] == $value)
                $results[] = $array;

            foreach ($array as $subarray)
                $results = array_merge($results, $this->search_array($subarray, $key, $value));
        }

        return $results;
    }

    function _search_array($arr, $key, $value) {
        $arrIt = new RecursiveIteratorIterator(new RecursiveArrayIterator($arr));
        foreach ($arrIt as $sub) {
            $subArray = $arrIt->getSubIterator();
            if ($subArray[$key] === $value) {
                $outputArray[] = iterator_to_array($subArray);
            }
        }
        return $outputArray;
    }

    private function _get_manifest_item() {
        $this->get_opf_file();
        $contents = file_get_contents($this->opf_file_path);
        if (preg_match('/<!DOCTYPE[^>]+?xhtml 1.1[^>]+?>/im', $contents)) {
            $contents = preg_replace('/<!DOCTYPE[^>]+?>/m', $this->xhtml11doctype, $contents);
        }
        $arr = array();
        $contents = preg_replace('/<html[^>]+?>/m', $this->htmltag, $contents);
        $this->dom->loadXML($contents);
        $manifest = $this->dom->getElementsByTagName('manifest')->item(0)->getElementsByTagName('item');
        if ($manifest->length > 0) {
            for ($i = 0; $i < $manifest->length; $i++) {
                $attributes = $manifest->item($i)->attributes;
                if ($attributes->length > 0) {
                    $arrt = array();
                    foreach ($attributes as $attr) {
                        $arrt[$attr->name] = $attr->value;
                        //echo $attr->name."=".$attr->value." - ";
                    }
                    $arr[$i] = $arrt;
                }
            }
        }
        if (!empty($arr)) {
            return array("result" => TRUE, "data" => $arr, "message" => "Items found");
        } else {
            return array("result" => FALSE, "error" => "Not find any item", "errorcode" => 1);
        }
    }

    public function get_ncx_file() {
        $this->get_list_all_files();
        $files = array_filter($this->file_list, function($a) {
                    return preg_match("/(\.ncx)$/i", $a);
                });
        if (!empty($files)) {
            foreach ($files as $result) {
                $filename = $result;
            }
            $this->ncx_file_path = $filename;
            return array("result" => TRUE, "data" => $filename, "message" => "NCX file found");
        } else {
            return array("result" => FALSE, "error" => "File not found", "errorcode" => 1);
        }
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
        $iterator1 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        $full_path = array();
        $full_path = array_keys(iterator_to_array($iterator1));
        if (!empty($full_path)) {
            return array("result" => TRUE, "data" => $full_path, "message" => "File found");
        } else {
            return array("result" => FALSE, "error" => "Files not found", "errorcode" => 2);
        }
    }

    function find_specific_pattern_file($file_path_array, $pattern = "") {
        if (!empty($file_path_array)) {
            $files = array();
            $pattern1 = $pattern;
            $files = array_filter($file_path_array, function($a) use($pattern) {
                        return preg_match(".$pattern.", $a);
                    });
            //print_r($files).'\n';
            //print_r($file_path_array).'\n';
            if (!empty($files)) {
                return array("result" => TRUE, "data" => $files, "message" => "Files found");
            } else {
                return array("result" => FALSE, "error" => "File not found", "errorcode" => 2);
            }
        } else {
            return array("result" => FALSE, "error" => "File list is empty", "errorcode" => 1);
        }
    }

    function get_list_file($dir, $pattern = "*") {
        $iterator = new GlobIterator($dir . "*.xml");
        $filelist = array();
        foreach ($iterator as $entry) {
            $filelist[] = $entry->getFilename();
        }
        print_r($filelist);
    }

    /*
     * Get the id of the last chapter
     * 
     */

    public function get_last_chapter_id() {
        $res = $this->_get_chapters_details();
        if ($res['result'] == TRUE) {
            $tt = end($res['data']);
            return $tt['id'];
        } else {
            return FALSE;
        }
    }

    function epub_check_java($epubfile) {
        $epubfile = trim($epubfile);
        if ($epubfile != NULL) {
            exec($this->epubcheck . ' ' . $epubfile . ' > /dev/stdout 2>&1', $output, $result);
            if (in_array($this->epubcheck_string, $output)) {
                return array("result" => TRUE, "data" => $output, "message" => "Valid Epub file");
            } else {
                return array("result" => FALSE, "error" => "Not a valid epub file", "errorcode" => 2, "data" => $output);
            }
        } else {
            return array("result" => FALSE, "error" => "Please give check file", "errorcode" => 1);
        }
    }

    public function save_chapter($chapter_id, $chapter_data) {
        $chapter_id = trim($chapter_id);
        $chapter_data = trim($chapter_data);
        if ($chapter_id != NULL) {
            $dk = $this->get_file_path_id($chapter_id);
            foreach ($dk['data']as $tt) {
                $file_path = $tt;
            }
            if ($chapter_data != NULL) {
                $xmlDoc = new DOMDocument();
                @$xmlDoc->loadHTML($chapter_data);
                $head = $xmlDoc->getElementsByTagName("head");
                $body = $xmlDoc->getElementsByTagName("body");

                $xml = new DOMDocument('1.0', "utf-8");
                $xml->lookupPrefix("http://www.w3.org/1999/xhtml");
                $xml->preserveWhiteSpace = FALSE;
                $xml->formatOutput = TRUE;

                $xml2Doc = new DOMDocument('1.0', "utf-8");
                $xml2Doc->lookupPrefix("http://www.w3.org/1999/xhtml");
                $xml2Doc->loadXML("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\"\n	\"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\n</html>\n");
                $html = $xml2Doc->getElementsByTagName("html")->item(0);
                $html->appendChild($xml2Doc->importNode($head->item(0), TRUE));
                $html->appendChild($xml2Doc->importNode($body->item(0), TRUE));

                // force pretty printing and correct formatting, should not be needed, but it is.
                $xml->loadXML($xml2Doc->saveXML());
                $doc = $xml->saveXML();
                $xml->save($file_path);
            }
        } else {
            return false;
        }
    }

    function get_filtered_content_view($filepath) {
        return $this->_get_filtered_content_view($filepath);
    }

    private function _get_filtered_content_view($filepath) {
        $filepath = trim($filepath);
        $content = file_get_contents($filepath);
        return $content;
    }

    public function create_element($file, $parent, $element) {
        return $this->_create_element($file, $parent, $element);
    }

    private function _create_element($file = NULL, $parent, $element) {
        $file = $this->opf_file_path;
        $this->dom->load($file);
        $this->dom->formatOutput = true;
        $dt = $this->dom->getElementsByTagName($parent)->item(0);
        $fragment = $this->dom->createDocumentFragment();
        $bar = $fragment->appendXML($element);
        $dt->appendChild($fragment);
        $this->dom->saveXML();
        $this->dom->save($this->opf_file_path);
    }

    /*
     * @author: Avinash Singh Rathi
     * This function is developed to add the comments for the author while creating the ebook.
     * 
     */

    public function add_comment($chapter_id = NULL, $comment = NULL, $time = NULL) {
        $this->_create_comment_file();
        $this->_set_comment_file_path();
        $this->comment_file;
        $this->dom->preserveWhiteSpace = FALSE;
        $this->dom->recover = true;
        $this->dom->formatOutput = true;
//        $element='<avicomment id="'.rand(1,9999999)."-".  uniqid().'" chapter="'.$chapter_id.'" time="'.$time.'">'.  htmlentities($comment).'</avicomment>';
//        file_put_contents($this->comment_file, $element, FILE_APPEND);
        $this->dom->load($this->comment_file);
        $root = $this->dom->getElementsByTagName('root')->item(0);
        $div = $this->dom->createElement("avicomment", $comment);
        $div->setAttribute("id", rand(1, 9999999) . "-" . uniqid());
        $div->setAttribute("chapter", $chapter_id);
        $div->setAttribute("time", $time);
        $root->appendChild($div);
        $this->dom->saveXML();
        $this->dom->save($this->comment_file);
    }

    public function load_comment($chapter_id) {
        $this->_set_comment_file_path();
        $source = file_get_contents($this->comment_file);
        $this->dom->loadHTML($source);
        $xpath = new DomXpath($this->dom);
        $rowNode = $xpath->query('//avicomment[@chapter="' . $chapter_id . '"]');
        return $rowNode;
    }

    public function delete_comment($comment_id) {
        $comment_id = trim($comment_id);
        $this->_set_comment_file_path();
        $source = file_get_contents($this->comment_file);
        $source = $this->_commentRemove($source, $comment_id);
        $this->dom->preserveWhiteSpace = FALSE;
        $this->dom->recover = true;
        $this->dom->formatOutput = true;
        $this->dom->loadXML($source);
        $this->dom->save($this->comment_file);
    }

    private function _commentRemove($myXML, $id) {
        $xmlDoc = new DOMDocument();
        $xmlDoc->preserveWhiteSpace = FALSE;
        $xmlDoc->recover = true;
        $xmlDoc->formatOutput = true;
        $xmlDoc->loadXML($myXML);
        $xpath = new DOMXpath($xmlDoc);
        $nodeList = $xpath->query('//avicomment[@id="' . $id . '"]');
        if ($nodeList->length) {
            $node = $nodeList->item(0);
            $node->parentNode->removeChild($node);
        }
        return $xmlDoc->saveXML();
    }

    public function formatXmlString($xml) {
        $xml = preg_replace('/(>)(<)(\/*)/', "$1\n$2$3", $xml);
        $token = strtok($xml, "\n");
        $result = '';
        $pad = 0;
        $matches = array();
        while ($token !== false) :
            if (preg_match('/.+<\/\w[^>]*>$/', $token, $matches)) :
                $indent = 0;
            elseif (preg_match('/^<\/\w/', $token, $matches)) :
                $pad--;
                $indent = 0;
            elseif (preg_match('/^<\w[^>]*[^\/]>.*$/', $token, $matches)) :
                $indent = 1;
            else :
                $indent = 0;
            endif;
            $line = str_pad($token, strlen($token) + $pad, ' ', STR_PAD_LEFT);
            $result .= $line . "\n";
            $token = strtok("\n");
            $pad += $indent;
        endwhile;
        return $result;
    }

    private function _set_comment_file_path() {
        $this->_create_comment_file();
        $path = dirname($this->opf_file_path);
        $this->comment_file = $path . '/dombcomment.apd';
    }

    /*
     * @author: Avinash Singh Rathi
     * This function is created to create the comment file if file not exist
     * 
     */

    private function _create_comment_file() {
        $path = dirname($this->opf_file_path);
        if (!file_exists($path . '/dombcomment.apd')) {
            $data = '<?xml version="1.0" encoding="UTF-8"><root></root>';
            $res = file_put_contents($path . '/dombcomment.apd', $data);
            if ($res) {
                $this->comment_file = $path . '/dombcomment.apd';
                $res = TRUE;
            } else {
                $res = FALSE;
            }
            return $res;
        } else {
            return FALSE;
        }
    }

    /*
     * @author Avinash Singh Rathi
     * This function is developed to filter the external images and store it locally on the epub directory
     */

    private function _filter_image_content($source) {
        $this->dom->loadHTML($source);
        $tr = $this->dom->getElementsByTagName('img')->item(0);
        foreach ($tr as $tr1) {
            
        }
    }

    public function test_OPS($epubpath, $destipath) {
        $tk = $this->extract_epub($epubpath, $destipath);
        $path = $tk['data'];
        $metapath = $path . '/META-INF';
        $result = array();
        $result[0] = 'fail';
        $result[1] = 'error message';
        if (is_dir($metapath)) { // dir exists
            if ($container = file_get_contents($metapath . '/container.xml')) { // found container file
                $contdoc = new DomDocument();
                if ($cd = $contdoc->loadXML($container)) { // loaded container xml       
                    $fp = $contdoc->getElementsByTagName('rootfiles')->item(0)->getElementsByTagName('rootfile')->item(0)->getAttribute('full-path');
                    if ($fullp = $this->_getRelPathWithOpf($fp)) { // found full path to opf file
                        $p = (strlen($fullp[0]) > 0) ? "$path/$fullp[0]" : "$path";
                        $op = "$p/$fullp[1]";
                        $this->logerr($op, 2);
                        if (file_exists($op)) { // found opf file
                            $opf = new DomDocument('1.0', 'utf-8');
                            $opf->preserveWhiteSpace = FALSE;
                            $opf->loadXML(file_get_contents($op));
                            $package = $opf->getElementsByTagName('package')->item(0);
                            $meta = $package->getElementsByTagName('metadata')->item(0);
                            $mani = $package->getElementsByTagName('manifest')->item(0);
                            $spine = $package->getElementsByTagName('spine')->item(0);
                            if ($meta && $mani && $spine) {
                                $tocatt = $spine->getAttribute('toc');
                                $opfXP = new DomXpath($opf);
                                $opfXP->registerNamespace("opfns", $this->opfNS);
                                $opfXP->registerNamespace("dc", $this->dcNS);
                                $tocitem = $opfXP->evaluate('//*[@id="' . $tocatt . '"]');
                                if ($tocitem->length > 0) {
                                    if (file_exists($p . '/' . $tocitem->item(0)->getAttribute('href'))) {
                                        $result[0] = 'pass';
                                        $result[1] = array('opf' => $fp);
                                        //foreach($mani->getElementsByTagName('item') as $item) {
                                        //  $href = $item->getAttribute('href');
                                        //}
                                    } else {
                                        $result[1] = "ncx file not found:" . $p . '/' . $tocitem->item(0)->getAttribute('href') . "\n";
                                    }
                                } else {
                                    $result[1] = "no item with ncx id found\n";
                                }
                            } else {
                                $result[1] = "one of the three required opf nodes is missing\n";
                            }
                        } else {
                            $result[1] = "opf not found\n";
                        }
                    } else {
                        $result[1] = "full path to opf not found\n";
                    }
                } else {
                    $result[1] = "container file not loaded\n";
                }
            } else {
                $result[1] = "container file not found\n";
            }
        } else {
            $result[1] = "meta-inf dir no exist\n";
        }
        return $result;
    }

}

?>
