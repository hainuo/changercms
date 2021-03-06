<?php
/**
 * The control class file of ZenTaoPHP framework.
 *
 * The author disclaims copyright to this source code.  In place of
 * a legal notice, here is a blessing:
 *
 *  May you do good and not evil.
 *  May you find forgiveness for yourself and forgive others.
 *  May you share freely, never taking more than you give.
 */

include FRAME_ROOT . '/base/control.class.php';

/**
 * The base class of control.
 *
 * @package framework
 */
class control extends baseControl
{
    /**
     * The construct function.
     *
     * 1. global the global vars, refer them by the class member such as $this->app.
     * 2. set the pathes of current module, and load it's mode class.
     * 3. auto assign the $lang and $config to the view.
     * 
     * @access public
     * @return void
     */
    public function __construct($moduleName = '', $methodName = '')
    {
        parent::__construct();

        $this->setClientDevice();
        $this->setTplRoot();
        $this->setHttpReferer();

        if(RUN_MODE == 'front') $this->view->layouts = $this->loadModel('block')->getPageBlocks($this->moduleName, $this->methodName);
    }

    /**
     * Set referer.
     * 
     * @access public
     * @return void
     */
    public function setHttpReferer()
    {
        if($this->session->http_referer) return true;

        if(!empty($_SERVER['HTTP_REFERER']))
        {
            $refererInfo = parse_url($_SERVER['HTTP_REFERER']);
            $referer     = $_SERVER['HTTP_REFERER'];
            if($this->server->http_host == $refererInfo['host']) $referer = '';
            $this->session->set('http_referer', $_SERVER['HTTP_REFERER']);
        }
        return true;
    }

    /**
     * Set TPL_ROOT used in template files.
     * 
     * @access public
     * @return void
     */
    public function setTplRoot()
    {
        if(!defined('TPL_ROOT')) define('TPL_ROOT', $this->app->getWwwRoot() . 'template' . DS . $this->config->template->{$this->app->clientDevice}->name . DS);
    }

    /**
     * Set the view file, thus can use fetch other module's page.
     * 
     * @param  string   $moduleName    module name
     * @param  string   $methodName    method name
     * @access private
     * @return string  the view file
     */
    public function setViewFile($moduleName, $methodName)
    {
        $moduleName = strtolower(trim($moduleName));
        $methodName = strtolower(trim($methodName));

        $modulePath  = $this->app->getModulePath($this->appName, $moduleName);
        $viewExtPath = $this->app->getModuleExtPath($this->appName, $moduleName, 'view');

        $viewType     = $this->viewType == 'mhtml' ? 'html' : $this->viewType;
        $mainViewFile = $modulePath . 'view' . DS . $this->devicePrefix . $methodName . '.' . $viewType . '.php';
        $viewFile     = $mainViewFile;

        if(RUN_MODE == 'front')
        {
            $templatePath = $this->app->getWwwRoot() . 'template' . DS . $this->config->template->{$this->app->clientDevice}->name . DS . $moduleName;
            $viewFile = str_replace(($this->app->getModulePath('', $moduleName) . 'view'), $templatePath, $viewFile);
            $mainViewFile = $viewFile;
        }

        if(!empty($viewExtPath))
        {
            $commonExtViewFile = $viewExtPath['common'] . $this->devicePrefix . $methodName . ".{$viewType}.php";
            $siteExtViewFile   = empty($viewExtPath['site']) ? '' : $viewExtPath['site'] . $this->devicePrefix . $methodName . ".{$viewType}.php";

            $viewFile = file_exists($commonExtViewFile) ? $commonExtViewFile : $mainViewFile;
            $viewFile = (!empty($siteExtViewFile) and file_exists($siteExtViewFile)) ? $siteExtViewFile : $viewFile;
            if(!is_file($viewFile)) $this->app->triggerError("the view file $viewFile not found", __FILE__, __LINE__, $exit = true);

            $commonExtHookFiles = glob($viewExtPath['common'] . $this->devicePrefix . $methodName . ".*.{$viewType}.hook.php");
            $siteExtHookFiles   = empty($viewExtPath['site']) ? '' : glob($viewExtPath['site'] . $this->devicePrefix . $methodName . ".*.{$viewType}.hook.php");
            $extHookFiles       = array_merge((array) $commonExtHookFiles, (array) $siteExtHookFiles);
        }

        if(!empty($extHookFiles)) return array('viewFile' => $viewFile, 'hookFiles' => $extHookFiles);
        return $viewFile;
    }

    /**
     * Get the extension file of an view.
     * 
     * @param  string $viewFile 
     * @access public
     * @return string|bool  If extension view file exists, return the path. Else return fasle.
     */
    public function getExtViewFile($viewFile)
    {
        $extPath     = dirname(realpath($viewFile)) . "/ext/_{$this->app->siteCode}/";
        $extViewFile = $extPath . basename($viewFile);

        if(file_exists($extViewFile))
        {
            helper::cd($extPath);
            return $extViewFile;
        }

        $extPath = RUN_MODE == 'front' ? dirname(realpath($viewFile)) . '/ext/' : dirname(dirname(realpath($viewFile))) . '/ext/view/';
        $extViewFile = $extPath . basename($viewFile);

        if(file_exists($extViewFile))
        {
            helper::cd($extPath);
            return $extViewFile;
        }

        return false;
    }

    /**
     * Get css code for a method. 
     * 
     * @param  string    $moduleName 
     * @param  string    $methodName 
     * @access private
     * @return string
     */
    public function getCSS($moduleName, $methodName)
    {
        $moduleName = strtolower(trim($moduleName));
        $methodName = strtolower(trim($methodName));

        $modulePath = $this->app->getModulePath('', $moduleName);
        $cssExtPath = $this->app->getModuleExtPath('', $moduleName, 'css') ;

        $css = '';
        if((RUN_MODE != 'front') or (strpos($modulePath, 'module' . DS . 'ext') !== false))
        {
            $mainCssFile   = $modulePath . 'css' . DS . $this->devicePrefix . 'common.css';
            $methodCssFile = $modulePath . 'css' . DS . $this->devicePrefix . $methodName . '.css';

            if(file_exists($mainCssFile))   $css .= file_get_contents($mainCssFile);
            if(file_exists($methodCssFile)) $css .= file_get_contents($methodCssFile);
        }
        else
        {
            $defaultMainCssFile   = TPL_ROOT . $moduleName . DS . 'css' . DS . $this->devicePrefix . "common.css";
            $defaultMethodCssFile = TPL_ROOT . $moduleName . DS . 'css' . DS . $this->devicePrefix . "{$methodName}.css";
            $themeMainCssFile     = TPL_ROOT . $moduleName . DS . 'css' . DS . $this->devicePrefix . "common.{$this->config->site->theme}.css";
            $themeMethodCssFile   = TPL_ROOT . $moduleName . DS . 'css' . DS . $this->devicePrefix . "{$methodName}.{$this->config->site->theme}.css";

            if(file_exists($defaultMainCssFile))   $css .= file_get_contents($defaultMainCssFile);
            if(file_exists($defaultMethodCssFile)) $css .= file_get_contents($defaultMethodCssFile);
            if(file_exists($themeMainCssFile))     $css .= file_get_contents($themeMainCssFile);
            if(file_exists($themeMethodCssFile))   $css .= file_get_contents($themeMethodCssFile);
        }

        if(!empty($cssExtPath))
        {
            $commonExtCssFiles = glob($cssExtPath['common'] . $methodName . DS . $this->devicePrefix . '*.css');
            if(!empty($commonExtCssFiles)) foreach($commonExtCssFiles as $cssFile) $css .= file_get_contents($cssFile);

            $methodExtCssFiles = glob($cssExtPath['site'] . $methodName . DS . $this->devicePrefix . '*.css');
            if(!empty($methodExtCssFiles)) foreach($methodExtCssFiles as $cssFile) $css .= file_get_contents($cssFile);
        }
        return $css;
    }

    /**
     * Get js code for a method. 
     * 
     * @param  string    $moduleName 
     * @param  string    $methodName 
     * @access private
     * @return string
     */
    public function getJS($moduleName, $methodName)
    {
        $moduleName = strtolower(trim($moduleName));
        $methodName = strtolower(trim($methodName));
        
        $modulePath = $this->app->getModulePath('', $moduleName);
        $jsExtPath  = $this->app->getModuleExtPath('', $moduleName, 'js');

        $js = '';
        if((RUN_MODE !== 'front') or (strpos($modulePath, 'module' . DS . 'ext') !== false))
        {
            $mainJsFile   = $modulePath . 'js' . DS . $this->devicePrefix . 'common.js';
            $methodJsFile = $modulePath . 'js' . DS . $this->devicePrefix . $methodName . '.js';

            if(file_exists($mainJsFile))   $js .= file_get_contents($mainJsFile);
            if(file_exists($methodJsFile)) $js .= file_get_contents($methodJsFile);
        }
        else
        {
            $defaultMainJsFile   = TPL_ROOT . $moduleName . DS . 'js' . DS . $this->devicePrefix . "common.js";
            $defaultMethodJsFile = TPL_ROOT . $moduleName . DS . 'js' . DS . $this->devicePrefix . "{$methodName}.js";
            $themeMainJsFile     = TPL_ROOT . $moduleName . DS . 'js' . DS . $this->devicePrefix . "common.{$this->config->site->theme}.js";
            $themeMethodJsFile   = TPL_ROOT . $moduleName . DS . 'js' . DS . $this->devicePrefix . "{$methodName}.{$this->config->site->theme}.js";

            if(file_exists($defaultMainJsFile))   $js .= file_get_contents($defaultMainJsFile);
            if(file_exists($defaultMethodJsFile)) $js .= file_get_contents($defaultMethodJsFile);
            if(file_exists($themeMainJsFile))     $js .= file_get_contents($themeMainJsFile);
            if(file_exists($themeMethodJsFile))   $js .= file_get_contents($themeMethodJsFile);
        }

        if(!empty($jsExtPath))
        {
            $commonExtJsFiles = glob($jsExtPath['common'] . $methodName . DS . $this->devicePrefix . '*.js');
            if(!empty($commonExtJsFiles))
            {
                foreach($commonExtJsFiles as $jsFile) $js .= file_get_contents($jsFile);
            }

            $methodExtJsFiles = glob($jsExtPath['site'] . $methodName . DS  . $this->devicePrefix . '*.js');
            if(!empty($methodExtJsFiles))
            {
                foreach($methodExtJsFiles as $jsFile) $js .= file_get_contents($jsFile);
            }
        }

        return $js;
    }

    /**
     * Parse view file. 
     *
     * @param  string $moduleName    module name, if empty, use current module.
     * @param  string $methodName    method name, if empty, use current method.
     * @access public
     * @return string the parsed result.
     */
    public function parse($moduleName = '', $methodName = '')
    {
        if(empty($moduleName)) $moduleName = $this->moduleName;
        if(empty($methodName)) $methodName = $this->methodName;

        if($this->viewType == 'json') return $this->parseJSON($moduleName, $methodName);

        /* If the parser is default or run mode is admin, install, upgrade, call default parser.  */
        if(RUN_MODE != 'front' or $this->config->template->parser == 'default')
        {
            $this->parseDefault($moduleName, $methodName);
            return $this->output;
        }

        /* Call the extened parser. */
        $parserClassName = $this->config->template->parser . 'Parser';
        $parserClassFile = 'parser.' . $this->config->template->parser . '.class.php';
        $parserClassFile = dirname(__FILE__) . DS . $parserClassFile;
        if(!is_file($parserClassFile)) $this->app->triggerError(" The parser file  $parserClassFile not found", __FILE__, __LINE__, $exit = true);

        helper::import($parserClassFile);
        if(!class_exists($parserClassName)) $this->app->triggerError(" Can not find class : $parserClassName not found in $parserClassFile <br/>", __FILE__, __LINE__, $exit = true);

        $parser = new $parserClassName($this);
        return $parser->parse($moduleName, $methodName);
    }

    /**
     * Parse default html format.
     *
     * @param string $moduleName    module name
     * @param string $methodName    method name
     * @access private
     * @return void
     */
    public function parseDefault($moduleName, $methodName)
    {
        /* Set the view file. */
        $results  = $this->setViewFile($moduleName, $methodName);
        $viewFile = $results;
        if(is_array($results)) extract($results);

        /* Get css and js. */
        $css = $this->getCSS($moduleName, $methodName);
        $js  = $this->getJS($moduleName, $methodName);

        if(RUN_MODE == 'front')
        {
            $template    = $this->config->template->{$this->app->clientDevice}->name;
            $theme       = $this->config->template->{$this->app->clientDevice}->theme;
            $customParam = $this->loadModel('ui')->getCustomParams($template, $theme);
            $themeHooks  = $this->loadThemeHooks();

            $importedCSS = array();
            $importedJS  = array();

            if(!empty($themeHooks))
            {
                $jsFun  = "get{$theme}JS";
                $cssFun = "get{$theme}CSS";
                if(function_exists($jsFun))  $importedJS = $jsFun();
                if(function_exists($cssFun)) $importedCSS = $cssFun();

                if(!empty($importedJS))  $importedJS  = $this->processImportedCodes($template, $theme, $importedJS);
                if(!empty($importedCSS)) $importedCSS = $this->processImportedCodes($template, $theme, $importedCSS);

                $jsFun  = "getJS";
                $cssFun = "getCSS";

                if(function_exists($jsFun))  $importedJS = $jsFun($theme);
                if(function_exists($cssFun)) $importedCSS = $cssFun($theme);
            }

            $js .= zget($importedJS, 'all', '');
            $js .= zget($this->config->js, "{$template}_{$theme}_all", '');
            $js .= zget($importedJS, "{$moduleName}_{$methodName}", '');
            $js .= zget($this->config->js,"{$template}_{$theme}_{$moduleName}_{$methodName}", '');

            $allPageCSS  = zget($importedCSS, 'all', '');
            $allPageCSS .= zget($this->config->css, "{$template}_{$theme}_all", '');

            $currentPageCSS  = zget($importedCSS, "{$moduleName}_{$methodName}", '');
            $currentPageCSS .= zget($this->config->css, "{$template}_{$theme}_{$moduleName}_{$methodName}", '');
            $css .= $this->ui->compileCSS($customParam, $allPageCSS . $currentPageCSS);
        }

        if($css) $this->view->pageCSS = $css;
        if($js)  $this->view->pageJS  = $js;
        
        /* Change the dir to the view file to keep the relative pathes work. */
        $currentPWD = getcwd();
        chdir(dirname($viewFile));

        extract((array)$this->view);

        ob_start();
        include $viewFile;
        if(isset($hookFiles)) foreach($hookFiles as $hookFile) if(file_exists($hookFile)) include $hookFile;
        $this->output .= ob_get_contents();
        ob_end_clean();

        /* At the end, chang the dir to the previous. */
        chdir($currentPWD);
    }

    /**
     * Print the content of the view. 
     * 
     * @param   string  $moduleName    module name
     * @param   string  $methodName    method name
     * @access  public
     * @return  void
     */
    public function display($moduleName = '', $methodName = '')
    {
        if(empty($this->output)) $this->parse($moduleName, $methodName);
        if(isset($this->config->cn2tw) and $this->config->cn2tw and $this->app->getClientLang() == 'zh-tw')
        {
            $this->app->loadClass('cn2tw', true);
            $this->output = cn2tw::translate($this->output);
        }

        if(RUN_MODE == 'front') 
        {
            $this->mergeCSS();
            $this->mergeJS();
        }

        if(RUN_MODE == 'front')
        {
            if($this->config->cache->type != 'close' and $this->config->cache->cachePage == 'open')
            {
                if(strpos($this->config->cache->cachedPages, "$moduleName.$methodName") !== false)
                {
                    $key = 'page' . DS . $this->app->clientDevice . DS . md5($_SERVER['REQUEST_URI']);
                    $this->app->cache->set($key, $this->output);
                }
            }

            $siteNav = commonModel::printTopBar() . commonModel::printLanguageBar();

            $this->output = str_replace($this->config->siteNavHolder, $siteNav, $this->output);

            /* Hide execinfo if output has no powerby btn. */
            if($this->config->site->execInfo == 'show') $this->output = str_replace($this->config->execPlaceholder, helper::getExecInfo(), $this->output);
        }

        echo $this->output;
    }

    /**
     * Send data directly, for ajax requests.
     * 
     * @param  misc    $data 
     * @param  string  $type 
     * @access public
     * @return void
     */
    public function send($data, $type = 'json')
    {
        $data = (array) $data;
        if($type == 'json')
        {
            if(!helper::isAjaxRequest())
            {
                if(isset($data['result']) and $data['result'] == 'success')
                {
                    if(!empty($data['message'])) echo js::alert($data['message']);
                    $locate = isset($data['locate']) ? $data['locate'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
                    if(!empty($locate)) die(js::locate($locate));
                    die(isset($data['message']) ? $data['message'] : 'success');
                }

                if(isset($data['result']) and $data['result'] == 'fail')
                {
                    if(!empty($data['message']))
                    {
                        $message = json_decode(json_encode((array)$data['message']));
                        foreach((array)$message as $item => $errors)
                        {
                            $message->$item = implode(',', $errors);
                        }
                        echo js::alert(strip_tags(implode(" ", (array) $message)));
                        die(js::locate('back'));
                    }
                }
            }

            header("Content-type: text/html; charset=utf-8");
            header("Content-type: application/json");
            echo json_encode($data);
        }
        die(helper::removeUTF8Bom(ob_get_clean()));
    }

    /**
     * Create a link to one method of one module.
     * 
     * @param   string         $moduleName    module name
     * @param   string         $methodName    method name
     * @param   string|array   $vars          the params passed, can be array(key=>value) or key1=value1&key2=value2
     * @param   string         $viewType      the view type
     * @access  public
     * @return  string the link string.
     */
    public function createLink($moduleName, $methodName = 'index', $vars = array(), $alias = array(), $viewType = '')
    {
        if(empty($moduleName)) $moduleName = $this->moduleName;
        return helper::createLink($moduleName, $methodName, $vars, $alias, $viewType);
    }

    /**
     * Create a link to the inner method of current module.
     * 
     * @param   string         $methodName    method name
     * @param   string|array   $vars          the params passed, can be array(key=>value) or key1=value1&key2=value2
     * @param   string         $viewType      the view type
     * @access  public
     * @return  string  the link string.
     */
    public function inlink($methodName = 'index', $vars = array(), $alias = array(), $viewType = '')
    {
        return helper::createLink($this->moduleName, $methodName, $vars, $alias, $viewType);
    }

    /**
     * Load theme hooks.
     * 
     * @access public
     * @return array
     */
    public function loadThemeHooks()
    {
        $theme     = $this->config->template->{$this->app->clientDevice}->theme;
        $hookPath  = $this->app->getWwwRoot() . 'theme' . DS . $this->config->template->{$this->app->clientDevice}->name. DS . $theme . DS;
        $hookFiles = glob("{$hookPath}*.php");

        if(empty($hookFiles)) return array();
        foreach($hookFiles as $file) include $file;
        return $hookFiles;
    }

    /**
     * Merge all css codes of one page. 
     * 
     * @access public
     * @return void
     */
    public function mergeCSS()
    {
        $pageCSS = '';
        preg_match_all('/<style>([\s\S]*?)<\/style>/', $this->output, $styles);
        if(!empty($styles[1])) $pageCSS = join('', $styles[1]);
        if(!empty($pageCSS))
        {
            $this->output = str_replace("</style>\n", '</style>', $this->output);
            $this->output = preg_replace('/<style>([\s\S]*?)<\/style>/', '', $this->output);
            if(strpos($this->output, '</head>') != false) $this->output = str_replace('</head>', "<style>{$pageCSS}</style></head>", $this->output);
            if(strpos($this->output, '</head>') == false) $this->output = "<style>{$pageCSS}</style>" . $this->output;
        }
    }

    /**
     * Merge all js codes of one page, 
     * 
     * @access public
     * @return void
     */
    public function mergeJS()
    {
        $pageJS = '';
        preg_match_all('/<script>([\s\S]*?)<\/script>/', $this->output, $scripts);
        if(empty($scripts[1][1])) return true;
        $configCode = $scripts[1][0] . $scripts[1][1];
        unset($scripts[1][1]);
        unset($scripts[1][0]);
        
        if(!empty($scripts[1])) $pageJS = join(';', $scripts[1]);
        if(!empty($pageJS))
        {
            $this->output = str_replace("</script>\n", '</script>', $this->output);
            $this->output = preg_replace('/<script>([\s\S]*?)<\/script>/', '', $this->output);
            if(strpos($this->output, '</body>') != false) $this->output = str_replace('</body>', "<script>{$pageJS}</script></body>", $this->output);
            if(strpos($this->output, '</body>') == false) $this->output .= "<script>$pageJS</script>";
        }
        $pos = strpos($this->output, '<script src=');
        $this->output = substr_replace($this->output, '<script>' . $configCode . '</script>', $pos) . substr($this->output, $pos);
        return true;
    }

    /**
     * Process imported codes encrypted.
     * 
     * @param  string    $template 
     * @param  string    $theme 
     * @param  array     $codes 
     * @access public
     * @return void
     */
    public function processImportedCodes($template, $theme, $codes)
    {
        $sources[] = "{$template}_default_";
        $sources[] = "{$template}_clean_";
        $sources[] = "{$template}_wide_";
        $sources[] = "{$template}_tartan_";
        $sources[] = "{$template}_colorful_";
        $sources[] = "{$template}_blank_";

        foreach($sources as $source) $replace[] = '';

        foreach($codes as $page => $code)
        {
            $page = str_replace($sources, $replace, $page);
            $codes->$page = $code;
        }
        return $codes;
    }
}
