<?php

/**
 * Morfy Engine
 *
 * ---------------------------------------------------------------------------
 *  Morfy - Content Management System
 *  Site: http://morfy.monstra.org
 *  Copyright (c) 2013 Romanenko Sergey / Awilum <awilum@msn.com>
 *
 *  Modified by Taufik Nurrohman <http://latitudu.com>
 *  Licence? See `https://github.com/Awilum/morfy-cms/blob/master/LICENSE.md`
 * ---------------------------------------------------------------------------
 *
 * This source file is part of the Morfy Engine. More information,
 * documentation and tutorials can be found at http://morfy.monstra.org
 *
 * @package     Morfy
 *
 * @author      Romanenko Sergey / Awilum <awilum@msn.com>
 * @copyright   2013 Romanenko Sergey / Awilum <awilum@msn.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Morfy {

    /**
     * The Version of Morfy
     * --------------------
     *
     * @var  string
     */
    const VERSION = 'Custom Build';

    /**
     * The Separator of Morfy
     * ----------------------
     *
     * @var  string
     */
    const SEPARATOR = '----';    

    /**
     * Configuration Array
     * -------------------
     *
     * @var  array
     */
    public static $config;

    /**
     * Plugins
     * -------
     *
     * @var  array
     */
    private static $plugins = array();

    /**
     * Actions
     * -------
     *
     * @var  array
     */
    private static $actions = array();

    /**
     * Filters
     * -------
     *
     * @var  array
     */
    private static $filters = array();

    /**
     * Key Name for Security Token Storage
     * -----------------------------------
     *
     * @var  string
     */
    protected static $security_token_name = 'security_token';

    /**
     * Page Headers
     * ------------
     *
     * @var  array
     */
    private $page_headers = array(
        'title' => 'Title',
        'description' => 'Description',
        'keywords' => 'Keywords',
        'author' => 'Author',
        'date' => 'Date',
        'robots' => 'Robots',
        'tags' => 'Tags',
        'template' => 'Template',
    );

    /**
     * Protected Clone Method to Enforce Singleton Behavior
     * ----------------------------------------------------
     *
     * @access  protected
     */
    protected function __clone() {}

    /**
     * Constructor
     * -----------
     *
     * @access  public
     */
    protected function __construct() {}

    /**
     * Factory Method
     * --------------
     *
     * Making method chaining possible right off the bat.
     *
     *  <code>
     *      $morfy = Morfy::factory();
     *  </code>
     *
     * @access  public
     */
    public static function factory() {
        return new static;
    }

    /**
     * Run Morfy Application
     * ---------------------
     *
     *  <code>
     *      Morfy::factory()->run($path);
     *  </code>
     *
     * @param   string  $path  Config path
     * @access  public
     */
    public function run($path) {

        // Load config file
        $this->loadConfig($path);

        // Set default timezone
        @ini_set('date.timezone', self::$config['site_timezone']);
        if(function_exists('date_default_timezone_set')) {
            date_default_timezone_set(self::$config['site_timezone']);
        } else {
            putenv('TZ=' . self::$config['site_timezone']);
        }

        // Sanitize URL to prevent XSS - Cross-Site Scripting
        $this->runSanitizeURL();

        // Send default header and set internal encoding
        header('Content-Type: text/html; charset=' . self::$config['site_charset']);
        function_exists('mb_language') and mb_language('uni');
        function_exists('mb_regex_encoding') and mb_regex_encoding(self::$config['site_charset']);
        function_exists('mb_internal_encoding') and mb_internal_encoding(self::$config['site_charset']);

        // Get the current configuration setting of `magic_quotes_gpc` and kill magic quotes
        if(get_magic_quotes_gpc()) {
            function stripslashesGPC(&$value) {
                $value = stripslashes($value);
            }
            array_walk_recursive($_GET, 'stripslashesGPC');
            array_walk_recursive($_POST, 'stripslashesGPC');
            array_walk_recursive($_COOKIE, 'stripslashesGPC');
            array_walk_recursive($_REQUEST, 'stripslashesGPC');
        }

        // Start the session
        // session_save_path('/home/latitudu/.cagefs/tmp');
        !session_id() and @session_start();        

        // Load Plugins
        $this->loadPlugins();
        $this->runAction('plugins_loaded');

        // Get page for current requested URL
        $page = $this->getPage($this->getUrl());

        // Overload page title, keywords and description
        empty($page['title']) and $page['title'] = self::$config['site_title'];
        empty($page['keywords']) and $page['keywords'] = self::$config['site_keywords'];
        empty($page['description']) and $page['description'] = self::$config['site_description'];

        $page = $page;
        $config = self::$config;

        // Load site template
        $this->runAction('before_render');
        require THEMES_PATH . '/' . $config['site_theme'] . '/' . ($template = ! empty($page['template']) ? $page['template'] : 'index') . '.html';
        $this->runAction('after_render');
    }

    /**
     * Get URL
     * -------
     *
     *  <code>
     *      $url = Morfy::factory()->getUrl();
     *  </code>
     *
     * @access  public
     * @return  string
     */
    public function getUrl() {

        // Get request URL and script URL
        $url = "";
        $request_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "";
        $script_url = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : "";

        // Get our URL path and trim the `/` on the left and right
        if ($request_url != $script_url) {
            $url = trim(preg_replace('#' . str_replace(array('\/', 'index.php'), array('/', ""), $script_url) .'#', "", $request_url, 1), '/');
        }

        // Strip out query string
        $url = preg_replace('#\?.*#', "", $url);

        return $url;
    }

    /**
     * Get URI Segments
     * ----------------
     *
     *  <code>
     *      $uri_segments = Morfy::factory()->getUriSegments();
     *  </code>
     *
     * @access  public
     * @return  array
     */
    public function getUriSegments() {
        return explode('/', $this->getUrl());
    }

    /**
     * Get Uri Segment
     * ---------------
     *
     *  <code>
     *      $uri_segment = Morfy::factory()->getUriSegment(1);
     *  </code>
     *
     * @access  public
     * @return  string
     */
    public function getUriSegment($segment) {
        $segments = $this->getUriSegments();
        return isset($segments[$segment]) ? $segments[$segment] : null;
    }

    /**
     * URL Sanitizer
     * -------------
     *
     *  <code>
     *      $url = Morfy::factory()->sanitizeURL($url);
     *  </code>
     *
     * @access  public
     * @param   string  $url  URL to be sanitized
     * @return  string
     */
    public function sanitizeURL($url) {
        $url = trim($url);
        $url = rawurldecode($url);
        $url = str_replace(
            array('--', '&quot;', '!', '@', '#', '$', '%', '^', '*', '(', ')', '+', '{', '}', '|', ':', '"', '<', '>', '[', ']', '\\', ';', "'", ',', '*', '+', '~', '`', 'laquo', 'raquo', ']>', '&#8216;', '&#8217;', '&#8220;', '&#8221;', '&#8211;', '&#8212;', '--'),
            array('-', '-', "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", '-'),
        $url);
        $url = rtrim($url, '-');
        $url = preg_replace(array('#\.\.#', '#\/\/#', '#^\/#', '#^\.#'), "", $url);
        return $url;
     }

    /**
     * Sanitize URL to prevent XSS - Cross-Site Scripting
     * --------------------------------------------------
     *
     *  <code>
     *      Morfy::factory()->runSanitizeURL();
     *  </code>
     *
     * @access  public
     * @return  void
     */
    public function runSanitizeURL() {
        $_GET = array_map(array($this, 'sanitizeURL'), $_GET);
    }

   /**
     * Get Pages
     * ---------
     *
     *  <code>
     *      $pages = Morfy::factory()->getPages(CONTENT_PATH . '/blog/');
     *  </code>
     *
     * @access  public
     * @param   string  $url         URL
     * @param   string  $order_by    Order by
     * @param   string  $order_type  Order type
     * @param   array   $ignore      Pages to ignore
     * @param   int     $limit       Limit of pages
     * @return  array
     */
    public function getPages($url, $order_by = 'date', $order_type = 'DESC', $ignore = array('404'), $limit = null) {

        // Page headers
        // $page_headers = $this->page_headers;

        // $pages = $this->getFiles($url, 'md', $ignore);

        // if( ! is_null($limit)) $pages = array_slice($pages, null, $limit);

        // foreach($pages as $key => $page) {

        //     if( ! in_array(basename($page, '.md'), $ignore)) {

        //         $content = file_get_contents($page);

        //         $_page_headers = explode(Morfy::SEPARATOR, $content);

        //         foreach($page_headers as $field => $regex) {
        //             if(preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*?)$/mi', $_page_headers[0], $match) && $match[1]) {
        //                 $_pages[$key][$field] = trim($match[1]);
        //             } else {
        //                 $_pages[$key][$field] = "";
        //             }
        //         }

        //         $url = str_replace(CONTENT_PATH, self::$config['site_url'], $page);
        //         $url = str_replace(
        //             array(
        //                 'index.md',
        //                 '.md',
        //                 '\\'
        //             ),
        //             array(
        //                 "",
        //                 "",
        //                 '/'
        //             ),
        //         $url);
        //         $url = rtrim($url, '/');

        //         $_pages[$key]['url'] = $url;
        //         $_content = $this->parseContent($content);

        //         if(is_array($_content)) {
        //             $_pages[$key]['content_short'] = $_content['content_short'];
        //             $_pages[$key]['content'] = $_content['content_full'];
        //         } else {
        //             $_pages[$key]['content_short'] = $_content;
        //             $_pages[$key]['content'] = $_content;
        //         }

        //         $_pages[$key]['slug'] = basename($page, '.md');

        //     }

        // }

        // $_pages = $this->subvalSort($_pages, $order_by, $order_type);

        // return $_pages;

        $page_headers = $this->page_headers;

        $pages = $this->getFiles($url, 'md', $ignore);

        if( ! $pages) $pages = array();

        if( ! is_null($limit)) $pages = array_slice($pages, null, $limit);

        $_pages = array();

        foreach($pages as $key => $page) {
            if($handle = fopen($page, 'r')) {
                while(($buffer = fgets($handle, 4096)) !== false) {
                    if(trim($buffer) === "" || trim($buffer) == Morfy::SEPARATOR) {
                        fclose($handle);
                        break;
                    }
                    $parts = explode(':', $buffer, 2);
                    $_pages[$key][array_search(trim($parts[0]), $page_headers)] = isset($parts[1]) ? trim($parts[1]) : "";
                }
            }
            $url = str_replace(
                array(
                    CONTENT_PATH,
                    'index.md',
                    '.md',
                    '\\'
                ),
                array(
                    self::$config['site_url'],
                    "",
                    "",
                    '/'
                ),
            $page);
            $url = rtrim($url, '/');

            $_pages[$key]['url'] = $url;
            $_pages[$key]['slug'] = basename($page, '.md');
        }

        $_pages = $this->subvalSort($_pages, $order_by, $order_type);

        return $_pages;
    }

    /**
     * Get Page
     * --------
     *
     *  <code>
     *      $page = Morfy::factory()->getPage('downloads');
     *  </code>
     *
     * @access  public
     * @param   string  $url  URL
     * @return  array
     */
    public function getPage($url) {

        $url = str_replace(CONTENT_PATH, "", $url);
        $url = trim($url, '\\/');
        $url = preg_replace('#\.md$#', "", $url);

        // Page headers
        $page_headers = $this->page_headers;

        // Get the file path
        if($url !== "") {
            $file = CONTENT_PATH . '/' . $url;
        } else {
            $file = CONTENT_PATH . '/' . 'index';
        }

        // Load the file
        if(is_dir($file)) {
            $file = CONTENT_PATH . '/' . $url . '/index.md';
        } else {
            $file .= '.md';
        }

        if(file_exists($file)) {
            $content = file_get_contents($file);
        } else {
            $content = file_get_contents(CONTENT_PATH . '/' . '404.md');
            header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
        }

        $_page_headers = explode(Morfy::SEPARATOR, $content);

        foreach($page_headers as $field => $regex) {
            if(preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*?)$/mi', $_page_headers[0], $match) && $match[1]) {
                $page[$field] = trim($match[1]);
            } else {
                $page[$field] = "";
            }            
        }

        $url = str_replace(CONTENT_PATH, self::$config['site_url'], $file);
        $url = str_replace(
            array(
                'index.md',
                '.md',
                '\\'
            ),
            array(
                "",
                "",
                '/'
            ),
        $url);
        $url = rtrim($url, '/');

        $page['url'] = $url;
        $_content = $this->parseContent($content);
        
        if(is_array($_content)) {
            $page['content_short'] = $_content['content_short'];
            $page['content'] = $_content['content_full'];
        } else {
            $page['content_short'] = $_content;
            $page['content'] = $_content;
        }

        $page['slug'] = basename($file, '.md');

        return $page;
    }

    /**
     * Get List of Files in Directory Recursively
     * ------------------------------------------
     *
     *  <code>
     *      $files = Morfy::factory()->
     ('folder');
     *      $files = Morfy::factory()->getFiles('folder', 'txt');
     *      $files = Morfy::factory()->getFiles('folder', array('txt', 'log'));
     *  </code>
     *
     * @access  public
     * @param   string  $folder  Folder
     * @param   mixed   $type    Files types
     * @param   array   $ignore  Ignore files by name without extension
     * @return  array
     */
    public static function getFiles($folder, $type = null, $ignore = array('404')) {
        $data = array();
        $folder = rtrim($folder, '\\/');
        if( ! is_array($type)) {
            $type = array($type);
        }
        if(is_dir($folder)) {
            $iterator = new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS);
            foreach(new RecursiveIteratorIterator($iterator) as $file) {
                $file_ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                if( ! is_null($type)) {
                    if(in_array($file_ext, $type) && ! in_array(basename($file->getFilename(), '.' . $file_ext), $ignore)) {
                        $data[] = $file->getPathName();
                    }
                } else {
                    if( ! in_array(basename($file->getFilename(), '.' . $file_ext), $ignore)) {
                        $data[] = $file->getPathName();
                    }
                }
            }
            return $data;
        } else {
            return false;
        }
    }

    /**
     * Content Parser
     * --------------
     *
     * @param   string  $content  Content to be parsed
     * @return  string  $content  Formatted content
     */
    protected function parseContent($content) {       
        // Parse contents after headers
        $_content = "";
        $i = 0;
        foreach(explode(Morfy::SEPARATOR, $content) as $c) {
            ($i++ !== 0) and $_content .= $c;
        }

        $content = $_content;

        // Parse `{site_url}`, `{morfy_separator}` and `{morfy_version}`
        $content = str_replace(
            array(
                '{site_url}',
                '{morfy_separator}',
                '{morfy_version}'
            ),
            array(
                self::$config['site_url'],
                self::SEPARATOR,
                self::VERSION
            ),
        $_content);

        // Parse `{cut}`
        if(strpos($content, '{cut}') === false) {
            $content = $this->applyFilter('content', $content);
        } else {
            $content = explode('{cut}', $content);
            $content['content_short'] = $this->applyFilter('content', $content[0]);
            $content['content_full']  = $this->applyFilter('content', $content[0] . $content[1]);
        }

        // Parse PHP
        $content = Morfy::evalPHP($content);

        // Return content
        return $content;
    }

    /**
     * Load Plugins
     * ------------
     */
    protected function loadPlugins() {
        foreach(self::$config['plugins'] as $plugin) {
            include_once PLUGINS_PATH . '/' . $plugin . '/' . $plugin . '.php';
        }
    }

    /**
     * Load Config
     * -----------
     */
    protected function loadConfig($path) {
        if(file_exists($path)) {
            self::$config = require $path;
        } else {
            die('Oopsss... Where is the config file?!');
        }
    }

    /**
     * Hooks a Function on to a Specific Action
     * -----------------------------------------
     *
     *  <code>
     *      // Hooks a function "newLink" on to a "footer" action.
     *      Morfy::factory()->addAction('footer', 'newLink', 10);
     *
     *      function newLink() {
     *          echo '<a href="#">My link</a>';
     *      }
     *  </code>
     *
     * @access  public
     * @param   string   $action_name     Action name
     * @param   mixed    $added_function  Added function
     * @param   integer  $priority        Priority. Default is 10
     * @param   array    $args            Arguments
     */
    public function addAction($action_name, $added_function, $priority = 10, array $args = null) {
        // Hooks a function on to a specific action.
        self::$actions[] = array(
            'action_name' => (string) $action_name,
            'function' => $added_function,
            'priority' => (int) $priority,
            'args' => $args
        );
    }

    /**
     * Run Functions that Hooked on a Specific Action Hook
     * ---------------------------------------------------
     *
     *  <code>
     *      // Run functions hooked on a `footer` action hook
     *      Morfy::factory()->runAction('footer');
     *  </code>
     *
     * @access  public
     * @param   string  $action_name  Action name
     * @param   array   $args         Arguments
     * @param   boolean $return       Return data or not. Default is false
     * @return  mixed
     */
    public function runAction($action_name, $args = array(), $return = false) {

        // Redefine arguments
        $action_name = (string) $action_name;
        $return = (bool) $return;

        // Run action
        if(count(self::$actions) > 0) {

            // Sort actions by priority
            $actions = $this->subvalSort(self::$actions, 'priority');

            // Loop through $actions array
            foreach($actions as $action) {

                // Execute specific action
                if($action['action_name'] == $action_name) {

                    // isset arguments ?
                    if(isset($args)) {

                        // Return or Render specific action results ?
                        if($return) {
                            return call_user_func_array($action['function'], $args);
                        } else {
                            call_user_func_array($action['function'], $args);
                        }

                    } else {

                        if($return) {
                            return call_user_func_array($action['function'], $action['args']);
                        } else {
                            call_user_func_array($action['function'], $action['args']);
                        }

                    }

                }

            }

        }

    }

    /**
     * Apply Filters
     * -------------
     *
     *  <code>
     *      Morfy::factory()->applyFilter('content', $content);
     *  </code>
     *
     * @access  public
     * @param   string  $filter_name  The name of the filter hook
     * @param   mixed   $value        The value on which the filters hooked
     * @return  mixed
     */
    public function applyFilter($filter_name, $value) {

        // Redefine arguments
        $filter_name = (string) $filter_name;

        $args = array_slice(func_get_args(), 2);

        if ( ! isset(self::$filters[$filter_name])) {
            return $value;
        }

        foreach(self::$filters[$filter_name] as $priority => $functions) {
            if( ! is_null($functions)) {
                foreach($functions as $function) {
                    $all_args = array_merge(array($value), $args);
                    $function_name = $function['function'];
                    $accepted_args = $function['accepted_args'];
                    if($accepted_args === 1) {
                        $the_args = array($value);
                    } elseif($accepted_args > 1) {
                        $the_args = array_slice($all_args, 0, $accepted_args);
                    } elseif($accepted_args == 0) {
                        $the_args = null;
                    } else {
                        $the_args = $all_args;
                    }
                    $value = call_user_func_array($function_name, $the_args);
                }
            }
        }

        return $value;
    }

    /**
     * Add Filter
     * ----------
     *
     *  <code>
     *      Morfy::factory()->addFilter('content', 'replacer');
     *
     *      function replacer($content) {
     *          return preg_replace(array('/\[b\](.*?)\[\/b\]/ms'), array('<strong>\1</strong>'), $content);
     *      }
     *  </code>
     *
     * @access  public
     * @param   string  $filter_name      The name of the filter to hook the $function_to_add to
     * @param   string  $function_to_add  The name of the function to be called when the filter is applied
     * @param   integer  $priority        Function to add priority - default is 10
     * @param   integer  $accepted_args   The number of arguments the function accept default is 1
     * @return  boolean
     */
    public function addFilter($filter_name, $function_to_add, $priority = 10, $accepted_args = 1) {

        // Redefine arguments
        $filter_name = (string) $filter_name;
        $function_to_add = $function_to_add;
        $priority = (int) $priority;
        $accepted_args = (int) $accepted_args;

        // Check that we don't already have the same filter at the same priority. Thanks to WP :)
        if(isset(self::$filters[$filter_name][$priority])) {
            foreach(self::$filters[$filter_name][$priority] as $filter) {
                if($filter['function'] == $function_to_add) {
                    return true;
                }
            }
        }

        self::$filters[$filter_name][$priority][] = array(
            'function' => $function_to_add,
            'accepted_args' => $accepted_args
        );

        // Sort
        ksort(self::$filters[$filter_name][$priority]);

        return true;
    }

    /**
     * Security Token Generator
     * ------------------------
     *
     * Generate and store a unique token which can be used to help prevent
     * [CSRF](http://wikipedia.org/wiki/Cross_Site_Request_Forgery) attacks.
     *
     *  <code>
     *      $token = Morfy::factory()->generateToken();
     *  </code>
     *
     * You can insert this token into your forms as a hidden field:
     *
     *  <code>
     *      <input type="hidden" name="token" value="<?php echo Morfy::factory()->generateToken(); ?>">
     *  </code>
     *
     * This provides a basic, but effective, method of preventing CSRF attacks.
     *
     * @param   boolean  $new  Force a new token to be generated?. Default is false
     * @return  string
     */
    public function generateToken($new = false) {

        // Get the current token
        if(isset($_SESSION[(string) self::$security_token_name])) {
            $token = $_SESSION[(string) self::$security_token_name];
        } else {
            $token = null;
        }

        // Create a new unique token
        if($new or ! $token) {

            // Generate a new unique token
            $token = sha1(uniqid(mt_rand(), true));

            // Store the new token
            $_SESSION[(string) self::$security_token_name] = $token;

        }

        // Return token
        return $token;
    }

    /**
     * Security Token Checker
     * ----------------------
     *
     * Check that the given token matches the currently stored security token.
     *
     *  <code>
     *      if(Morfy::factory()->checkToken($token)) {
     *          // Passed!
     *      }
     *  </code>
     *
     * @param   string  $token  Token to check
     * @return  boolean
     */
    public function checkToken($token) {
        return Morfy::factory()->generateToken() === $token;
    }

    /**
     * String Sanitizer
     * ----------------
     *
     * Sanitize data to prevent XSS - Cross-site scripting.
     *
     *  <code>
     *      $str = Morfy::factory()->cleanString($str);
     *  </code>
     *
     * @param   string  $str String
     * @return  string 
     */
    public function cleanString($str) {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Subval Sort
     * -----------
     *
     *  <code>
     *      $new_array = Morfy::factory()->subvalSort($old_array, 'sort');
     *  </code>
     *
     * @access  public
     * @param   array   $a       Array
     * @param   string  $subkey  Key
     * @param   string  $order   Order type, DESC or ASC
     * @return  array
     */
    public function subvalSort($a, $subkey, $order = null) {
        if(count($a) > 0 || ( ! empty($a))) {
            foreach($a as $k => $v) {
                $b[$k] = function_exists('mb_strtolower') ? mb_strtolower($v[$subkey]) : strtolower($v[$subkey]);
            }
            if(is_null($order) || $order == 'ASC') {
                asort($b);
            } elseif($order == 'DESC') {
                arsort($b);
            }
            foreach($b as $key => $val) {
                $c[] = $a[$key];
            }
            return $c;
        }
    }

    /**
     * Evaluate Output Buffers
     * -----------------------
     */
    protected static function obEval($mathes) {
        ob_start();
        eval($mathes[1]);
        $mathes = ob_get_contents();
        ob_end_clean();
        return $mathes;
    }

    /**
     * Evaluate PHP String in Content
     * ------------------------------
     */
    protected static function evalPHP($str) { 
        return preg_replace_callback('#\{php\}([\s\S]+?)\{\/php\}#ms', 'Morfy::obEval', $str); 
    }

}
