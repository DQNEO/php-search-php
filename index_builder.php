<?php
/** 
 * 
 * 
 */

namespace pudding;

ini_set("memory_limit", -1);

const VERSION = "0.1.0";

$version = VERSION;
echo <<<EEE
** ---------------------------------------------- **
 Hyper Pudding - a rapid PHP source search -
 pudding\index_builder

 @license   The MIT License
 @author    sotarok <sotaro.k /at/ gmail.com>
 @version   {$version}
** ---------------------------------------------- **

EEE;

class index_builder
{
    public $is_debug = true;
    protected $_basedir = "";
    protected $_source_list = array();

    protected $_token_index = 1;
    protected $_token_list = array();
    protected $_tokenize_tokens = array(
        T_STRING_CAST,
        T_STRING_VARNAME,
        T_STRING,
        T_VARIABLE,
    );
    protected $_inverted_index = array();

    public function __construct($basedir, Array $tokenize_tokens = array(), $is_debug = false)
    {
        $this->_basedir = $basedir;
        if (!empty($tokenize_tokens)) {
            $this->_tokenize_tokens = $tokenize_tokens;
        }

        $this->is_debug = $is_debug;
    }

    public function build_index()
    {
        $this->_source_list = self::crawl_recursive($this->_basedir);

        $sec =  microtime(true);
        $this->tokenizer();
        $esec =  microtime(true);
        echo "Index Built: ", $esec - $sec, PHP_EOL;
    }

    public function search($keyword)
    {
        if (isset($this->_inverted_index[$keyword])) {
            return $this->_inverted_index[$keyword];
        }
        return false;
    }

    public function tokenizer()
    {
        foreach ($this->_source_list as $source) {
            $this->info($source, PHP_EOL);
            foreach (token_get_all(file_get_contents($source)) as $token) {
                if (in_array($token[0], $this->_tokenize_tokens)) {
                    $this->_token_list[] = array($source, $token[1], $token[2]);
                    if (!isset($this->_inverted_index[$token[1]])) {
                        $this->_inverted_index[$token[1]] = array();
                    }
                    $this->_inverted_index[$token[1]][] = array($source, $token[2]);
                }
            }
            $this->info("\t", memory_get_usage()/1024/1024, " MB ", PHP_EOL);
        }
    }

    public function info()
    {
        if ($this->is_debug) {
            fprintf(STDERR, join(" ", func_get_args()));
        }
    }

    public static function crawl_recursive ($dirname)
    {
        $files = array();
        foreach (glob($dirname . "/*") as $file) {
            if (is_dir($file)) {
                $files = array_merge($files, static::crawl_recursive($file));
            }
            else {
                if (preg_match('/.+\.php$/', $file)) {
                    $files[] = $file;
                }
            }
        }
        return $files;
    }
}

if ($argc != 2) {
    fprintf(STDERR, "

Invalid arguments:
  usege: php %s base_dir

     base_dir       -   searching source file basedir. script searcing under this directory.

", $argv[0]);
    exit(1);
}

$builder = new index_builder(rtrim($argv[1], "/"));
$builder->build_index();

echo " mem: ", printf("%.5f", memory_get_usage()/1024/1024), " MB used.", PHP_EOL;

echo <<<EEE

Input Search Keyword:
    (if you want to end this script, input empty string)

EEE;

echo "> ";
$key = trim(fgets(STDIN));
while(!empty($key)) {
    echo "  searching $key", PHP_EOL;
    $sec = microtime(true);
    $res = $builder->search($key);
    $esec = microtime(true);
    if ($res) {
        foreach ($res as $r) {
            echo "\t", str_replace($argv[1], "", $r[0]), " on line ", $r[1], PHP_EOL;
        }
    }
    else {
        echo "Not Found.", PHP_EOL;
    }

    echo " sec: ", printf("%.5f", $esec - $sec), PHP_EOL;

    echo "> ";
    $key = trim(fgets(STDIN));
}