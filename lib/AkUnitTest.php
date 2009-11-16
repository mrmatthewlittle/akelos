<?php

// +----------------------------------------------------------------------+
// | Akelos Framework - http://www.akelos.org                             |
// +----------------------------------------------------------------------+

/**
 * @package ActiveSupport
 * @subpackage Testing
 * @author Bermi Ferrer <bermi a.t bermilabs c.om>
 */

require_once(AK_VENDOR_DIR.DS.'simpletest'.DS.'unit_tester.php');
require_once(AK_VENDOR_DIR.DS.'simpletest'.DS.'mock_objects.php');
require_once(AK_VENDOR_DIR.DS.'simpletest'.DS.'reporter.php');
require_once(AK_VENDOR_DIR.DS.'simpletest'.DS.'web_tester.php');
require_once(AK_VENDOR_DIR.DS.'simpletest'.DS.'extensions'.DS.'junit_xml_reporter.php');
//require_once(AK_VENDOR_DIR.DS.'simpletest'.DS.'code_coverage.php');


class AkUnitTest extends UnitTestCase
{
    public
    $app_dir,
    $module = '',
    $insert_models_data = false,
    $instantiate_models = false,
    $rebase = false;

    private
    $_original_paths = array(),
    $_path_rebased = false;

    public function __construct($label = false)
    {
        $this->_logOriginalPaths();
        $this->app_dir = AkConfig::getDir('app');
        parent::__construct($label);
        $this->_configure();
        if($this->rebase){
            $this->rebaseAppPaths($this->rebase);
        }
    }

    public function __destruct()
    {
        if($this->_path_rebased){
            $this->restoreAppPaths();
        }
    }

    public function rebaseAppPaths($base_path = null)
    {
        $base_path = !is_dir($base_path) ? AkConfig::getDir('fixtures') : $base_path;
        AkConfig::setDir('app',             $base_path.DS.'app');
        AkConfig::setDir('app_installers',  $base_path.DS.'app'.DS.'installers');
        AkConfig::setDir('models',          $base_path.DS.'app'.DS.'models');
        AkConfig::setDir('controllers',     $base_path.DS.'app'.DS.'controllers');
        AkConfig::setDir('views',           $base_path.DS.'app'.DS.'views');
        AkConfig::setDir('apis',            $base_path.DS.'app'.DS.'apis');
        $this->_path_rebased = true;
    }
    public function restoreAppPaths()
    {
        foreach ($this->_original_paths as $type => $original_path){
            AkConfig::setDir($type, $original_path);
        }
    }

    private function _logOriginalPaths()
    {
        $this->_original_paths = array(
        'app'               => AkConfig::getDir('app'),
        'models'            => AkConfig::getDir('models'),
        'app_installers'    => AkConfig::getDir('app_installers'),
        'controllers'       => AkConfig::getDir('controllers'),
        'views'             => AkConfig::getDir('views'),
        'apis'              => AkConfig::getDir('apis'),
        );
    }

    /**
     *    Gets a list of test names. Normally that will
     *    be all internal methods that start with the
     *    name "test". This method should be overridden
     *    if you want a different rule.
     *    @return array        List of test names.
     *    @access public
     */
    public function getTests()
    {
        $methods = array();
        if (isset($this->skip) && $this->skip == true) {
            return $methods;
        }
        foreach (get_class_methods(get_class($this)) as $method) {
            if ($this->isTest($method)) {
                $methods[] = $method;
            }
        }
        return $methods;
    }

    protected function _configure()
    {
        $this->skip = !$this->_checkIfEnabled();
        $this->_loadFixtures();
    }

    protected function _checkIfEnabled($file = null)
    {
        if ($file == null) {
            $file = isset($this->check_file)?$this->check_file:null;
        }
        if ($file!=null && file_exists($file)) {
            $val = file_get_contents($file);
            if ($val == '0') {
                return false;
            }
        }
        return true;
    }


    protected function _loadFixtures($loadFixture = null)
    {
        if (isset($this->fixtures)) {
            $this->fixtures = is_array($this->fixtures)?$this->fixtures:Ak::toArray($this->fixtures);
        } else {
            $this->fixtures = array();
        }

        foreach ($this->fixtures as $fixture) {
            $file = AkConfig::getDir('fixtures').DS.'data'.DS.$fixture.'.yaml';
            if(!file_exists($file)){
                continue;
            }
            if ($loadFixture!=null && $fixture!=$loadFixture) {
                continue;
            }
            $setAlias=false;
            if (!isset($this->$fixture)) {
                $this->$fixture = array();
                $setAlias=true;
                $this->{$fixture.'_set'}=true;
            } else if ($this->{$fixture.'_set'}) {
                $setAlias = true;
            }
            $class_name = AkInflector::classify($fixture);
            if($this->instantiateModel($class_name)){
                $contents = Ak::getStaticVar('yaml_fixture_'.$file);
                if (!$contents) {
                    ob_start();
                    require_once($file);
                    $contents = ob_get_clean();
                    Ak::setStaticVar('yaml_fixture_'.$file, $contents);
                }
                $items = Ak::convert('yaml','array',$contents);
                foreach ($items as $alias=>$item){
                    $obj=$this->{$class_name}->create($item);
                    if (isset($item['created_at'])) {
                        $obj->updateAttribute('created_at',$item['created_at']);
                    } else if (isset($item['created_on'])) {
                        $obj->updateAttribute('created_on',$item['created_on']);
                    }
                    if ($setAlias) {
                        $array=$this->$fixture;
                        $array[$alias] = $obj;
                        $this->$fixture = $array;
                    }
                }
            }
        }
    }

    /**
     * Re-installs the table for a given Modelname and includes or even instantiates the Model.
     * Looks in test/fixtures/app/models for the models and in test/fixtures/app/installers for the appropriate installers.
     * If no class-file for Model is found, it generates a dumb one temporarily.
     * For quick and dirty guys, the table can be generated on the fly. see below.
     *
     * examples:
     * installAndIncludeModels('Article');
     * installAndIncludeModels(array('Article','Comment'=>'id,body'));
     *
     * @param mixed $models
     */
    public function installAndIncludeModels($models = array())
    {
        $args = func_get_args();
        $last_arg = count($args)-1;

        if (isset($args[$last_arg]) && is_array($args[$last_arg]) && (isset($args[$last_arg]['instantiate']) || isset($args[$last_arg]['populate']))){
            $options = array_pop($args);
        } else $options = array();
        $default_options = array('instantiate' => true);
        $options = array_merge($default_options, $options);

        $models = !empty($args) ? (is_array($args[0]) ? array_shift($args) : (count($args) > 1 ? $args : Ak::toArray($args[0]))) : array();

        foreach ($models as $key=>$value){                               // handle array('Tag','Article')   <= array
            $model = is_numeric($key) ? $value : $key;                   //  or    array('Tag'=>'id,name'); <= a hash!
            $table_definition = is_numeric($key) ? '' : $value;
            $this->_reinstallModel($model, $table_definition);
            $this->_includeOrGenerateModel($model);
            if($this->insert_models_data || !empty($options['populate'])){
                $this->populateTables(AkInflector::tableize($model));
            }
            if($this->instantiate_models || !empty($options['instantiate'])){
                $this->instantiateModel($model);
            }
        }
    }

    public function log($message)
    {
        if (AK_LOG_EVENTS){
            static $logger;
            if(empty($logger)) {
                $logger = Ak::getLogger();
            }
            $logger->log('unit-test',$message);
        }
    }

    protected function _reinstallModel($model, $table_definition = '')
    {
        $this->log('Reinstalling model:'.$model);
        if (!$this->uninstallAndInstallMigration($model)){
            $table_name = AkInflector::tableize($model);
            if (empty($table_definition)) {
                trigger_error(Ak::t('Could not install the table %tablename for the model %modelname',array('%tablename'=>$table_name, '%modelname'=>$model)),E_USER_ERROR);
                return false;
            }
            $installer = new AkInstaller();
            $installer->dropTable($table_name,array('sequence'=>true));
            $installer->createTable($table_name,$table_definition,array('timestamp'=>false));
        } else {
            $table_name = AkInflector::tableize($model);
        }
        if (isset($this->fixtures) && is_array($this->fixtures) && in_array($table_name,$this->fixtures)) {
            $this->_loadFixtures($table_name);
        }
    }

    public function uninstallAndInstallMigration($installer_name)
    {
        $installer_path = AkConfig::getDir('app_installers').DS.AkInflector::underscore($installer_name).'_installer.php';
        $this->log('Looking for installer:'.$installer_path);
        if (file_exists($installer_path)){
            $this->log('found installer:'.$installer_path);
            require_once($installer_path);
            $installer_class_name = $installer_name.'Installer';
            $Installer = new $installer_class_name();
            $Installer->uninstall();
            $Installer->install();
            return true;
        }

        return false;
    }

    protected function _includeOrGenerateModel($model_name)
    {
        $model_file_name = AkInflector::toModelFilename($model_name);
        if (file_exists($model_file_name)){
            require_once($model_file_name);
        } else {
            if (class_exists($model_name)){
                return true;
            }
            $model_source_code = "class ".$model_name." extends ActiveRecord { }";
            $has_errors = @eval($model_source_code) === false;
            if ($has_errors) trigger_error(Ak::t('Could not declare the model %modelname.',array('%modelname'=>$model_name)),E_USER_ERROR);
        }
    }

    public function populateTables()
    {
        $args = func_get_args();
        $tables = !empty($args) ? (is_array($args[0]) ? $args[0] : (count($args) > 1 ? $args : Ak::toArray($args))) : array();
        foreach ($tables as $table){
            $file = AkConfig::getDir('fixtures').DS.'data'.DS.(empty($this->module)?'':$this->module.DS).Ak::sanitize_include($table).'.yaml';
            if(!file_exists($file)){
                continue;
            }
            $class_name = AkInflector::classify($table);
            if($this->instantiateModel($class_name)){
                $contents = Ak::getStaticVar('yaml_fixture_'.$file);
                if (!$contents) {
                    ob_start();
                    require_once($file);
                    $contents = ob_get_clean();
                    Ak::setStaticVar('yaml_fixture_'.$file, $contents);
                }
                $items = Ak::convert('yaml','array',$contents);
                foreach ($items as $item){

                    $obj=$this->{$class_name}->create($item);
                    if (isset($item['created_at'])) {
                        $obj->updateAttribute('created_at',$item['created_at']);
                    } else if (isset($item['created_on'])) {
                        $obj->updateAttribute('created_on',$item['created_on']);
                    }
                }
            }
        }
    }

    public function instantiateModel($model_name)
    {
        if(class_exists($model_name) || Ak::import($model_name)){
            $this->$model_name = new $model_name();
        } else {
            trigger_error(Ak::t('Could not instantiate %modelname',array('%modelname'=>$model_name)),E_USER_ERROR);
        }
        return !empty($this->$model_name) && is_object($this->$model_name) && strtolower(get_class($this->$model_name)) == strtolower($model_name);
    }

    public function instantiateModels()
    {
        $args = func_get_args();
        $models = (count($args) > 1) ? $args : Ak::stringToArray(@$args[0]);
        call_user_func_array(array($this, 'instantiateModel'), $models);
    }

    /**
     * Includes and instantiates given models
     */
    public function includeAndInstatiateModels()
    {
        $args = func_get_args();
        $models = isset($args[1]) ? (array)$args : Ak::toArray($args[0]);
        foreach ($models as $model){
            $this->_includeOrGenerateModel($model);
            $this->instantiateModel($model);
        }
    }
}

/**
* Unit tester for your mailers.
*
* This tester will copy your application views from the app/views to test/fixtures/app/views
* unless you implicitly set AkMailerTest::avoid_copying_views to true.
*/
class AkMailerTest extends AkUnitTest
{
    public function __construct()
    {
        empty($this->avoid_copying_views) && Ak::copy(AkConfig::getDir('fixtures').DS.'app'.DS.'views', AkConfig::getDir('views'));
    }
}

class AkWebTestCase extends WebTestCase
{
    public function assertWantedText($text, $message = '%s')
    {
        $this->assertPattern('/'.preg_quote($text).'/', $message);
    }

    /**
     * Asserts only if the whole response matches $text
     */
    public function assertTextMatch($text, $message = '%s')
    {
        $this->assertPattern('/^'.preg_quote($text).'$/', $message);
    }
}

