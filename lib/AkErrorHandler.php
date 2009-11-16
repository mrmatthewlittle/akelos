<?php

// +----------------------------------------------------------------------+
// | Akelos Framework - http://www.akelos.org                             |
// +----------------------------------------------------------------------+

/**
 * This class will handle errors on production mode
 * @todo Connect with Loggers in order to provide a strong error reporting
 * system on production mode
 * 
 * @package ActiveSupport
 * @subpackage Reporting
 * @author Bermi Ferrer <bermi a.t bermilabs c.om>
 */
class AkErrorHandler {

    /**
    * Constructor
    * @access public
    */
    function AkErrorHandler() {
        return false;
    }

    /**
    * @param int $errNo
    * @param string $errMsg
    * @param string $file
    * @param int $line
    * @return void
    * @access public
    */
    function raiseError($errNo, $errMsg, $file, $line) 
    {
            if (! ($errNo & error_reporting())) {
                return;
            }
            while (ob_get_level()) {
                ob_end_clean();
            }


        //echo nl2br(print_r(get_defined_vars(),true)).'<hr />';
        if (! ($errNo & error_reporting())) {
            return;
        }
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        $errType = array (
        1    => "Php Error",
        2    => "Php Warning",
        4    => "Parsing Error",
        8    => "Php Notice",
        16   => "Core Error",
        32   => "Core Warning",
        64   => "Compile Error",
        128  => "Compile Warning",
        256  => "Php User Error",
        512  => "Php User Warning",
        1024 => "Php User Notice"
        );

        
        if(substr($errMsg,0,9) == 'database|'){
            die('Database connection error');
            header('Location: '.AK_URL.Ak::tourl(array('controller'=>'error','action'=>'database')));
            exit;
        }
            
        $info = array();

        if (($errNo & E_USER_ERROR) && is_array($arr = @unserialize($errMsg))) {
            foreach ($arr as $k => $v) {
                $info[$k] = $v;
            }
        }
    }
}

?>