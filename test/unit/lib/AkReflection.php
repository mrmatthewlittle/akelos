<?php

require_once(dirname(__FILE__).'/../../fixtures/config/config.php');

class AkReflection_TestCase extends  AkUnitTest 
{
    public function setUp()
    {
        /**
         * AK_TEST_DIR.DS.'fixtures'.DS.'data'.DS.'reflection_test_class.php'
         */
        $this->Reflection = new AkReflection();
    }

    public function test_parse_function()
    {
        $function = '   function testFunction($param) { testbody(); }';
        $this->Reflection->parse($function);
        $functionStructure = $this->Reflection->getDefinitions();
        $this->assertEqual('function',$functionStructure[0]['type']);
        $this->assertEqual(false,$functionStructure[0]['returnByReference']);
        $this->assertEqual('testFunction',$functionStructure[0]['name']);
        $this->assertEqual(array('$param'),$functionStructure[0]['params']);
        $this->assertEqual(trim($function),$functionStructure[0]['toString']);

        $function = '
        /**
         * comment
         *
         * @return boolean
         */
        public function &_getPriorizedPlugins()
        {
            if(!empty($this->_plugin_instances) && empty($this->_priorized_plugins)){
                ksort($this->_plugin_instances);
                foreach (array_keys($this->_plugin_instances) as $priority){
                    foreach (array_keys($this->_plugin_instances[$priority]) as $k){
                        $this->_priorized_plugins[] =& $this->_plugin_instances[$priority][$k];
                    }
                }
            }
            return $this->_priorized_plugins;
        }';

        $this->Reflection->parse($function);
        $functionStructure = $this->Reflection->getDefinitions();
        $this->assertEqual(
        '/**
 * comment
 *
 * @return boolean
 */', trim($functionStructure[0]['docBlock']));
        $this->assertEqual('function',$functionStructure[0]['type']);
        $this->assertEqual(true,$functionStructure[0]['returnByReference']);
        $this->assertEqual('_getPriorizedPlugins',$functionStructure[0]['name']);

    }
    public function test_parse_require_include()
    {
        $function = '
        require_once(AK_APP_DIR.DS.\'require_once.php\');
        require(AK_APP_DIR.DS.\'require.php\');
        include_once(AK_APP_DIR.DS.\'include_once.php\');
        include(AK_APP_DIR.DS.\'include.php\');';
        $this->Reflection->parse($function);
        $structure = $this->Reflection->getDefinitions();
        $this->assertEqual(array("AK_APP_DIR.DS.'require_once.php'"),$structure['require_once']);
        $this->assertEqual(array("AK_APP_DIR.DS.'require.php'"),$structure['require']);
        $this->assertEqual(array("AK_APP_DIR.DS.'include_once.php'"),$structure['include_once']);
        $this->assertEqual(array("AK_APP_DIR.DS.'include.php'"),$structure['include']);

    }
    public function test_parse_class()
    {
        $function = '
        require_once(AK_APP_DIR.DS.\'test.php\');
        /**
         * comment
         */
         class testClass extends base {}
         
         class secondClass {}';
        $this->Reflection->parse($function);
        $classStructure = $this->Reflection->getDefinitions();
        $this->assertTrue(isset($classStructure[1]));
        $this->assertEqual('class',$classStructure[0]['type']);
        $this->assertEqual(false,$classStructure[0]['returnByReference']);
        $this->assertEqual('testClass',$classStructure[0]['name']);
        $this->assertEqual('secondClass',$classStructure[1]['name']);
        $this->assertEqual(
        trim('
/**
 * comment
 */
class testClass extends base {}'),trim($classStructure[0]['toString']));
        $this->assertEqual(array("AK_APP_DIR.DS.'test.php'"),$classStructure['require_once']);


    }
}

ak_test_run_case_if_executed('AkReflection_TestCase');

