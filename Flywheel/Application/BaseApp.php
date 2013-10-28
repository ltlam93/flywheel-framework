<?php
namespace Flywheel\Application;

use Flywheel\Base;
use Flywheel\Config\ConfigHandler;
use Flywheel\Controller\BaseController;
use Flywheel\Exception;
use Flywheel\Loader;
use Flywheel\Object;
use Symfony\Component\Translation\Translator;

abstract class BaseApp extends Object
{
    const TYPE_WEB = 1;
    const TYPE_CONSOLE = 2;
    const TYPE_API = 3;
    protected $_translator;

    /**
     * Controller
     *
     * @var BaseController
     */
    protected $_controller;

    /**
     * application type (Web|Console|API)
     * @var int
     */
    protected $_type;

    protected $_basePath;

    public function __construct($config, $type) {
        if (is_string($config)) {
            $config = require $config;
        }

        if (isset($config['app_path'])) {
            $this->setBasePath($config['app_path']);
            Base::setAppPath($config['app_path']);
            Loader::setPathOfAlias('app', Base::getAppPath());
            Loader::setPathOfAlias('public', dirname($_SERVER['SCRIPT_FILENAME']));
            unset($config['app_path']);
        } else {
            throw new Exception('Application: missing application\'s config "app_path"');
        }

        if (isset($config['import'])) {
            $this->_import($config['import']);
            unset($config['import']);
        }

        $this->_type = $type;

        $this->preInit();
        $this->configuration($config);
        $this->_init();
        $this->afterInit();

        set_error_handler(array($this,'_handleError'),error_reporting());
    }

    public function getClientIp() {
        $ipAddress = '';
        if (getenv('HTTP_CLIENT_IP'))
            $ipAddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ipAddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
            $ipAddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ipAddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
            $ipAddress = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ipAddress = getenv('REMOTE_ADDR');
        else
            $ipAddress = 'UNKNOWN';

        return $ipAddress;
    }

    private function _handleError($code, $message, $file, $line) {
        if($code & error_reporting()) {
            // disable error capturing to avoid recursive errors
            restore_error_handler();

            $label = null;
            $time = date("D d/M/Y H:i:s");
            $client = $this->getClientIp();
            switch($code) {
                case E_USER_ERROR:
                    $label = 'ERROR';
                    break;
                case E_USER_WARNING:
                    $label = 'WARNING';
                    break;
                case E_USER_NOTICE:
                    $label = 'NOTICE';
                    break;
                default:
                    $label = 'UNKNOWN';
            }

            $log="[{$time}] [client {$client}]\n{$label}: {$message} in {$file} at {$line}\nStack trace:\n";

            $trace = array();
            $traceData = debug_backtrace();
            $count = count($traceData);

            $limit = ($count >= 8)? $count : 8;

            for ($i = 0; $i < $limit; ++$i) {

                if(!isset($traceData[$i]['file'])) {
                    $traceData[$i]['file']='unknown';
                }
                if(!isset($traceData[$i]['line'])) {
                    $traceData[$i]['line']=0;
                }
                if(!isset($traceData[$i]['function'])) {
                    $traceData[$i]['function']='unknown';
                }

                $log.="\t#$i {$traceData[$i]['file']}({$traceData[$i]['line']}): ";
                if(isset($t['object']) && is_object($t['object']))
                    $log.=get_class($t['object']).'->';
                $log.="{$traceData[$i]['function']}()\n";
            }

            error_log($log);
        }
    }

    private function _import($aliases) {
        if (is_array($aliases) && ($size = sizeof($aliases)) > 0) {
            for ($i = 0; $i < $size; ++$i)
                Loader::import($aliases[$i]);
        }
    }

    public function preInit() {}

    public function configuration($config, $value = null) {
        if (is_array($config)) {
            foreach ($config as $name => $value) {
                $this->setParameter($name, $value);
            }
        } else if(null != $value) {
            $this->setParameter($config, $value);
        }
    }

    public function setParameter($name, $value) {
        $setter = 'set' .ucfirst($name);
        if (method_exists($this, $setter))
            $this->$setter($value);
        else
            ConfigHandler::set($name, $value);
    }

    /**
     * set controllers
     *
     * @param BaseController $controller
     * @throws Exception
     */
    public function setController($controller) {
        if (!$controller instanceof BaseController) {
            throw new Exception("Application: Controller was assigned is not instance of '\\Flywheel\\Controller\\'");
        }

        $this->_controller = $controller;
    }

    /**
     * @return BaseController
     */
    public function getController() {
        return $this->_controller;
    }

    protected function _init() {}

    public function afterInit() {
    }

    public function beforeRun() {}

    public abstract function run();

    public function afterRun() {}

    /**
     * get type of this application
     * @see BaseApp::TYPE_WEB, BaseApp::TYPE_CONSOLE, BaseApp::TYPE_API
     * @return int
     */
    public function getType() {
        return $this->_type;
    }

    public function setBasePath($path)
    {
        if(($this->_basePath=realpath($path))===false || !is_dir($this->_basePath))
            throw new Exception("Application: Base path \"{$path}\" is not a valid directory.");
    }

    public function getTranslator() {
        if (null == $this->_translator) {
            $this->_translator = new Translator($this->getLocale());
        }

        return $this->_translator;
    }

    /**
     * @return null|string
     */
    public function getLocale() {
        $locale = ConfigHandler::get('locale');
        if (!$locale) $locale = 'en-Us';
        return $locale;
    }
}
