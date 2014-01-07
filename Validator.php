<?php

/**
* @file Validator.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Classes for validating form data
*/

namespace dophp;

/**
* Parse and validate form data
*
* @version 0.3
* @see __construct
*/
class Validator {

    private $__post;
    private $__files;
    private $__rules;

    /**
    * Main costructor
    *
    * @param array $_POST data, passed byRef
    * @param array $_FILES data, passed byRef
    * @param array The rules, associative array with format:
    *              'field_name' => array('field_type', array('options'))
    * @see <type>_validator
    */
    public function __construct( &$post, &$files, $rules) {
        $this->__post = & $post;
        $this->__files = & $files;
        $this->__rules = $rules;
    }

    /**
    * Do the validation.
    *
    * @return array ($data, $error). $data cointains same fields on post
    *         formatted according to 'field_type' (when given). $errors
    *         contains validation errors. All fields are automatically trimmed.
    */
    public function validate() {
        $data = array();
        $errors = array();
        foreach( $this->__rules as $k => $v ) {
            $vname = $v[0] . '_validator';
            if( substr($v[0],0,4) == 'file' )
                $validator = new $vname($this->__files[$k], $v[1], $this->__files);
            else
                $validator = new $vname(trim($this->__post[$k]), $v[1], $this->__post);
            $data[$k] = $validator->clean();
            if( $err = $validator->validate() )
                $errors[$k] = $err;
        }
        return array( $data, $errors );
    }

}

interface field_validator {
    /**
    * The validator constructor
    *
    * @param mixed value: The field value
    * @param array options: The options for this field
    * @param array values: The raw POST data (byRef, MUST not be modified)
    */
    public function __construct($value, $options, & $values);
    /** Returns error string or false */
    public function validate();
    /** Returns cleaned value */
    public function clean();
}

/**
* Base abstract validator class.
*
* Common rules: 'required'=>boolean|lambda($field_values, $all_values).
*               When true (or when the lambda returns true, check that field
*               is not empty.
*               'custom'=>lambda($field_values, $all_values).
*               validates using custom function. Must return string error or
*               null on success.
*/
abstract class base_validator implements field_validator {

    private $__value;
    private $__values;
    private $__options;
    private $__cleaned;

    public function __construct($value, $options, & $values) {
        $this->__value = $value;
        $this->__values = & $values;
        $this->__options = $options;
        $this->__cleaned = $this->do_clean($value);
    }
    public function clean() {
        return $this->__cleaned;
    }
    public function validate() {
        $v = & $this->__cleaned;
        $o = & $this->__options;

        // Perform common validation tasks
        if( $o['required'] ) {
            // Convert lambda
            $req = is_callable($o['required']) ? $o['required']($v, $this->__values) : $o['required'];
            if( $req ) {
                $err = $this->check_required($v);
                if( $err )
                    return $err;
            }
        }
        if( $o['custom'] )
            $err = $o['custom']($v, $this->__values);
            if( $err )
                return $err;

        // Perform specific validation tasks
        $err = $this->do_validate($v, $o);
        if( $err )
            return $err;

        return null;
    }

    protected function do_clean($val) {
        $val = trim($val);
        if( $val === '' )
            return null;
        return $val;
    }

    protected function check_required($val) {
        if( ! $val )
            return "Campo obbligatorio.";
        return false;
    }

    protected function do_validate(& $v, & $o) {
        return null;
    }
}

/**
* Validate as string
*
* Custom validation rules: 'email'=>true, Validates as e-mail address
*
* @return string
*/
class string_validator extends base_validator {

    protected function do_validate( &$v, &$o ) {
        if( $o['email'] ) {
            if( $err = $this->check_email($v) )
                return $err;
        }
    }
    protected function check_email($val) {
        if( strlen($val) == 0 )
            return null;
        if( ! preg_match('/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i', $val) )
            return "Email non valida.";
    }
}

/**
* Validate as integer
*
* @return int
*/
class int_validator extends base_validator {

    protected function do_clean($val) {
        return (int)trim($val);
    }
}

/**
* Validate as double
*
* @return double
*/
class double_validator extends base_validator {

    protected function do_clean($val) {
        $val = str_replace(',', '.', trim($val));
        return (double)$val;
    }
}

/**
* Validate as boolean
*
* @return bool
*/
class bool_validator extends base_validator {

    protected function do_clean($val) {
        return (bool)trim($val);
    }
    protected function check_required($val) {
        if( $val === null || $val === '' )
            return "Campo obbligatorio.";
        return false;
    }
}

/**
* Validate as DateTime
*
* @return object DateTime
*/
class date_validator extends base_validator {

    protected function do_clean($val) {
        try {
            $date = new DateTime($val);
        } catch( Exception $e ) {
            return null;
        }
        return $date;
    }
}

/**
* Validate a file
*
* Custom validation rules: 'type': array(<types>) Validate against a list of mime types
*
* @return array $file array, like $_FILE
*/
class file_validator extends base_validator {

    protected function do_clean($val) {
        return $val;
    }
    protected function check_required($val) {
        if( ! $val )
            return "Campo obbligatorio.";
        if( ! (int)$val['size'] )
            return "Dimensione immagine ({$val['size']}) non valida.";
        return false;
    }
    protected function do_validate( &$v, &$o ) {
        if( $o['type'] ) {
            $err = $this->check_type($v['type'], $o['type']);
            if( $err )
                return $err;
        }
    }
    protected function check_type($type, $types) {
        if( ! in_array( $type, $types ) )
            return "Formato file $type non supportato.";
    }
}
