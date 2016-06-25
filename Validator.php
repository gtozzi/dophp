<?php

/**
* @file Validator.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Classes for validating POST data (form or JSON)
*/

namespace dophp;

/**
* Parse and validate form data
*
* @version 0.4
* @see __construct
*/
class Validator {

	private $__post;
	private $__files;
	private $__rules;

	/**
	* Main costructor
	*
	* @param $post array: $_POST data (usually), passed byRef
	* @param $files array: $_FILES data (usually), passed byRef
	* @param $rules The rules, associative array with format:
	*               'field_name' => array('field_type', array('options'))
	*               - If field_type is 'array', handles it as a
	*                 sub-validator: expects data to be an array too.
	*               - If field_name is (int)0 and 'multiple' option is true,
	*                 that rule will be applied to any other numerical index
	*                 of $post
	* @see <type>_validator
	*/
	public function __construct( &$post, &$files, $rules) {
		$this->__post = & $post;
		$this->__files = & $files;
		\DoPhp::lang()->dophpDomain();

		// Handle multiple validator: copy 0 validator over all numeric data
		// Also checks for rule consistency
		$multi = array();
		foreach( $rules as $f => $r ) {
			if( ! is_array($r) || array_keys($r) !== array(0,1) )
				throw new \Exception("Unvalid rule format for $f:\n" . print_r($r,true));

			if( $f===0 && array_key_exists('multiple',$r[1]) && $r[1]['multiple'] )
				foreach($post as $k => $v)
					if( is_int($k) && ! array_key_exists($k, $rules) )
						$multi[$k] = $r;
			break;
		}
		$this->__rules = array_replace($multi, $rules); // array_merge screws up int keys!
		\DoPhp::lang()->restoreDomain();
	}

	/**
	* Do the validation.
	*
	* @return array ($data, $error). $data cointains same fields on post
	*         formatted according to 'field_type' (when given).  All fields are
	*         automatically trimmed. $errors contains validation errors as
	*         associative array of strings, or associative array of arrays if
	*         type is array (recursive validator).
	*/
	public function validate() {
		\DoPhp::lang()->dophpDomain();

		$data = array();
		$errors = array();
		foreach( $this->__rules as $k => $v ) {
			list($type, $options) = $v;

			if( is_array($type) )
				throw new \Exception('Deprecated old sub-validator syntax');

			$vname = 'dophp\\' . $type . '_validator';

			if( substr($type,0,4) == 'file' )
				$validator = new $vname(isset($this->__files[$k])?$this->__files[$k]:null, $options, $this->__files);
			elseif( substr($type,0,5) == 'array' )
				$validator = new $vname(isset($this->__post[$k])?$this->__post[$k]:null, $options, $this->__post);
			else
				$validator = new $vname(isset($this->__post[$k])?$this->__post[$k]:null, $options, $this->__post);
			$data[$k] = $validator->clean();
			if( $err = $validator->validate() )
				$errors[$k] = $err;
		}

		\DoPhp::lang()->restoreDomain();
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
*                   When true (or when the lambda returns true, check that field
*                   is not empty.
*               'choices'=>array()
*                   When specified, the validated value MUST be in_array(<choice>)
*               'custom'=>lambda($field_values, $all_values).
*                   validates using custom function. Must return string error or
*                   null on success.
*               'default'=>specify a default value to be used in place of null
*               'process'=>lambda($value)
*                   Lambda function to post-proces the final value after validation
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
		$nullified = $this->nullify($value);
		// Null doesn't need to be cleaned
		if( $nullified === null ) {
			if( array_key_exists('default',$this->__options) )
				$this->__cleaned = $this->__options['default'];
			else
				$this->__cleaned = $nullified;
		}else
			$this->__cleaned = $this->do_clean($nullified);
	}

	public function clean() {
		return $this->__cleaned;
	}

	public function validate() {
		$v = & $this->__cleaned;
		$o = & $this->__options;

		// Perform common validation tasks
		if( array_key_exists('required',$o) && $o['required'] ) {
			// Convert lambda
			$req = is_callable($o['required']) ? $o['required']($v, $this->__values) : $o['required'];
			if( $req ) {
				$err = $this->check_required($v);
				if( $err )
					return $err;
			}
		}
		if( array_key_exists('custom',$o) && $o['custom'] ) {
			$err = $o['custom']($v, $this->__values);
			if( $err )
				return $err;
		}
		if( isset($o['choices']) && ! in_array($v, $o['choices']) )
			return _('Field must be one of') . ' "' . implode($o['choices'],',') . '".';

		// Perform specific validation tasks
		$err = $this->do_validate($v, $o);
		if( $err )
			return $err;

		// Run post-processor
		if( array_key_exists('process',$o) && $o['process'] )
			$v = $o['process']($v);

		return null;
	}

	/**
	* Sets the value to null if empty, leave null unmolested
	*/
	protected function nullify($val) {
		if( $val === '' || $val === null )
			return null;
		return $val;
	}

	/**
	* Cleans the value and converts it to right type before being validated
	*/
	protected function do_clean($val) {
		return $this->nullify(trim($val));
	}

	protected function check_required($val) {
		if( $val === null )
			return _("Field can't be empty") . '.';
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
*                          'url'=>true, Validates as absolute URL
*                          'len'=>array, Validates length bethween [<min>,<max>]
*                                        (values are inclusive, use null to omit a limit)
*
* @return string
*/
class string_validator extends base_validator {

	protected function do_validate( &$v, &$o ) {
		if( isset($o['email']) && $o['email'] )
			if( $err = $this->check_email($v) )
				return $err;
		if( isset($o['url']) && $o['url'] )
			if( $err = $this->check_url($v) )
				return $err;
		if( isset($o['len']) && $o['len'] )
			if( $err = $this->check_len($v, $o['len'][0], $o['len'][1]) )
				return $err;
	}
	protected function check_email($val) {
		if( $val === null )
			return false;
		if( ! preg_match('/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,63}$/i', $val) )
			return _('Unvalid eMail') . '.';
	}
	protected function check_url($val) {
		if( $val === null )
			return false;
		if( ! preg_match('/^[a-z]+:\/\/[a-z0-9.]+\/?[a-z0-9=\-._~:\/?#[\]@!$&+]*$/i', $val) )
			return _('Unvalid absolute URL') . '.';
	}
	protected function check_len($val, $min, $max) {
		if( $val === null )
			return false;
		if( $min !== null && mb_strlen($val) < $min )
			return str_replace('{number}', $min, _('Text must be at least {number} characters long')) . '.';
		if( $max !== null && mb_strlen($val) > $max )
			return str_replace('{number}', $max, _('Text must be no longer than {number} characters')) . '.';
	}
}

/**
* Common numeric validation class
*
* Custom validation rules: 'min'=>val, Number must be greater or equal than value
*                          'max'=>val, Number must be lesser or equal than value
*/
abstract class number_validator extends base_validator {

	protected function do_validate( &$v, &$o ) {
		if( isset($o['min']) )
			if( $err = $this->check_min($v, $o['min']) )
				return $err;
		if( isset($o['max']) )
			if( $err = $this->check_max($v, $o['max']) )
				return $err;
	}
	protected function check_min($val, $min) {
		if( $val === null || $val >= $min )
			return false;
		return str_replace('{number}', $this->format_number($min), _('Number must be at least {number}')) . '.';
	}
	protected function check_max($val, $max) {
		if( $val === null || $val <= $max )
			return false;
		return str_replace('{number}', $this->format_number($max), _('Number must not be bigger than {number}')) . '.';
	}

	/**
	* Utility function to convert a number (double or int) to string
	*/
	protected function format_number($num) {
		if( floor($num) == $num )
			return sprintf('%u', $num);
		return sprintf('%f', $num);
	}

}

/**
* Validate as integer
*
* @return int
*/
class int_validator extends number_validator {

	protected function do_clean($val) {
		if( gettype($val) == 'integer' )
			return $val;
		return (int)trim($val);
	}
}

/**
* Validate as double
*
* @return double
*/
class double_validator extends number_validator {

	protected function do_clean($val) {
		if( gettype($val) == 'double' )
			return $val;
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
		if( gettype($val) == 'boolean' )
			return $val;
		return (bool)trim($val);
	}

}

/**
* Validate as DateTime
*
* Custom validation rules: 'min'=>val, Date must be greater or equal than value
*                          'max'=>val, Date must be lesser or equal than value
*
* @return object DateTime
*/
class date_validator extends base_validator {

	protected function do_validate( &$v, &$o ) {
		if( isset($o['min']) )
			if( $err = $this->check_min($v, $o['min']) )
				return $err;
		if( isset($o['max']) )
			if( $err = $this->check_max($v, $o['max']) )
				return $err;
	}
	protected function check_min($val, $min) {
		if( $val === null || $val >= $min )
			return false;
		return str_replace('{date}', $this->format_date($min), _('Date must be {date} or after')) . '.';
	}
	protected function check_max($val, $max) {
		if( $val === null || $val <= $max )
			return false;
		return str_replace('{date}', $this->format_date($max), _('Date must be {date} or before')) . '.';
	}

	protected function do_clean($val) {
		if( gettype($val) == 'object' && $val instanceof \DateTime )
			return $val;
		try {
			$date = new \DateTime($val);
		} catch( \Exception $e ) {
			return null;
		}
		return $date;
	}

	/**
	* Utility function to convert a date to string
	*/
	protected function format_date($date) {
		return strftime('%c', $date->format('U'));
	}
}

/**
* Validate as time only
*
* @return object dophp\Time
*/
class time_validator extends base_validator {

	protected function do_clean($val) {
		if( gettype($val) == 'object' && $val instanceof Time )
			return $val;
		$vals = preg_split('/(\\.|:|\\s+)/', trim($val));
		if( count($vals) > 3 )
			return null;
		foreach( $vals as & $v ) {
			if( ! is_numeric($v) )
				return null;
			$v = (int) $v;
		}
		unset($v);
		for( $i = 0; $i < 3; $i++ )
			if( ! isset($vals[$i]) )
				$vals[$i] = 0;
		return new Time(implode(':', array_map(function($i){return str_pad($i,2,'0',STR_PAD_LEFT);}, $vals )));
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
		if( ! $val )
			return null;
		if( ! $val['size'] )
			return null;
		return $val;
	}
	protected function check_required($val) {
		if( ! $val )
			return _("Field can't be empty") . '.';
		if( ! (int)$val['size'] )
			return _("Unvalid file size") . " ({$val['size']}).";
		return false;
	}
	protected function do_validate( &$v, &$o ) {
		if( isset($o['type']) && $v['size'] ) {
			$err = $this->check_type($v['type'], $o['type']);
			if( $err )
				return $err;
		}
	}
	protected function check_type($type, $types) {
		if( ! in_array( $type, $types ) )
			return _("Unsupported file type") . ': ' . $type;
	}
}

/**
* Validate an array as a container of different elements
*
* Custom validation rules: 'rules': array list of array elements to check for,
*                                   like on main rules
*                          'required': if true, an array must be present
*/
class array_validator implements field_validator {

	private $__value = null;
	private $__error = null;

	public function __construct($value, $options, & $values) {
		if( ! $value ) {
			if( array_key_exists('required',$options) && $options['required'] )
				$this->__error = _("Field can't be empty") . '.';

		}elseif( ! is_array($value) ) {
			$this->__error = _("Must be an array") . '.';

		}elseif( array_key_exists('rules',$options) && $options['rules'] ) {
			$validator = new Validator($value, $_FILES, $options['rules']);
			list($this->__value, $this->__error) = $validator->validate();

		}else
			$this->__value = $value;

	}

	public function clean() {
		return $this->__value;
	}

	public function validate() {
		return $this->__error ? print_r($this->__error, true) : $this->__error;
	}
}
