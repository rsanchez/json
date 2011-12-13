<?php

/**
 * Courtesy of Erik Pettersson
 *
 * https://gist.github.com/1217080
 * https://github.com/ptz0n
 * http://ptz0n.se/
 */

/**
 * Validate JSONP Callback
 *
 * https://github.com/tav/scripts/blob/master/validate_jsonp.py
 * https://github.com/talis/jsonp-validator/blob/master/src/main/java/com/talis/jsonp/JsonpCallbackValidator.java
 * http://tav.espians.com/sanitising-jsonp-callback-identifiers-for-security.html
 * http://news.ycombinator.com/item?id=809291
 *
 * ^[a-zA-Z_$][0-9a-zA-Z_$]*(?:\[(?:".+"|\'.+\'|\d+)\])*?$
 *
 */
class Jsonp {

    /**
     * Validation tests
     *
     * @var array
     *
     * @access private
     */
    private $_tests = array(
        ''          => false,
        'hello'     => true,
        'alert()'   => false,
        'test()'    => false,
        'a-b'       => false,
        '23foo'     => false,
        'foo23'     => true,
        '$210'      => true,
        '_bar'      => true,
        'some_var'  => true,
        '$'         => true,
        'somevar'   => true,
        'function'  => false,
        ' somevar'  => false,
        '$.ajaxHandler' => true,
        '$.23'          => false,
        'array_of_functions[42]'        => true,
        'array_of_functions[42][1]'     => true,
        '$.ajaxHandler[42][1].foo'      => true,
        'array_of_functions[42]foo[1]'  => false,
        'array_of_functions[]'          => false,
        'array_of_functions["key"]'     => true,
        'myFunction[123].false'         => false,
        'myFunction .tester'            => false,
        '_function'                     => true,
        'petersCallback1412331422[12]'  => true,
        ':myFunction'                   => false
    );

    /**
     * Is valid callback
     *
     * @param string $callback
     *
     * @return boolean
     */
    function isValidCallback($callback)
    {
        $reserved = array(
            'break',
            'do',
            'instanceof',
            'typeof',
            'case',
            'else',
            'new',
            'var',
            'catch',
            'finally',
            'return',
            'void',
            'continue', 
            'for',
            'switch',
            'while',
            'debugger',
            'function',
            'this',
            'with', 
            'default',
            'if',
            'throw',
            'delete',
            'in',
            'try',
            'class',
            'enum', 
            'extends',
            'super',
            'const',
            'export',
            'import',
            'implements',
            'let', 
            'private',
            'public',
            'yield',
            'interface',
            'package',
            'protected', 
            'static',
            'null',
            'true',
            'false'
        );

        foreach(explode('.', $callback) as $identifier) {
            if(!preg_match('/^[a-zA-Z_$][0-9a-zA-Z_$]*(?:\[(?:".+"|\'.+\'|\d+)\])*?$/', $identifier)) {
                return false;
            }
            if(in_array($identifier, $reserved)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Test callback strings
     * 
     * @param string $callback
     *
     * @return void
     *
     * @access private
     */
    private function _test($callback, $valid)
    {
        $vocal = $valid ? 'valid' : 'invalid';
        if($this->isValidCallback($callback) === $valid) {
            echo '"'.$callback.'" <span style="color:green">passed as '.$vocal.'</span>.', "\n";
            return true;
        }
        else {
            echo '"'.$callback.'" <span style="color:red;font-weight:700;">failed as '.$vocal.'</span>.', "\n";
            return false;
        }
    }

    /**
     * Run all tests
     *
     * @return void
     *
     * @access public
     */
    function runTests()
    {
        echo '<strong>Testing ', count($this->_tests), ' callback methods:</strong>', "\n\n";
        $passed = 0;
        foreach($this->_tests as $callback => $valid) {
            $passed = self::_test($callback, $valid) ? $passed+1 : $passed;
        }
        echo "\n", $passed, ' of ', count($this->_tests), ' tests passed.';
    }
}