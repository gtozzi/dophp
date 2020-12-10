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
* @version 0.5
* @see __construct
*/
class Validator {

	private $__post;
	private $__files;
	private $__rules;

	/**
	* Main costructor
	*
	* @param $post array: $_POST data (usually)
	* @param $files array: $_FILES data (usually)
	* @param $rules array: The rules, associative array with format:
	*               'field_name' => array('field_type', array('options'))
	*               - If field_type is 'array', handles it as a
	*                 sub-validator: expects data to be an array too.
	*               - If field_name is (int)0 and 'multiple' option is true,
	*                 that rule will be applied to any other numerical index
	*                 of $post
	* @see <type>_validator
	*/
	public function __construct( $post, $files, $rules) {
		$this->__post = $post;
		$this->__files = $files;
		\DoPhp::lang()->dophpDomain();

		// Handle multiple validator: copy 0 validator over all numeric data
		// Also checks for rule consistency
		$multi = array();
		foreach( $rules as $f => $r ) {
			if( ! is_array($r) || array_keys($r) !== array(0,1) )
				throw new \UnexpectedValueException("Invalid rule format for $f:\n" . print_r($r,true));

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
				throw new \UnexpectedValueException('Deprecated old sub-validator syntax');

			$vname = 'dophp\\' . $type . '_validator';

			if( substr($type,0,4) == 'file' )
				$validator = new $vname(isset($this->__files[$k])?$this->__files[$k]:null, $options, $this->__files, $k);
			elseif( substr($type,0,5) == 'array' )
				$validator = new $vname(isset($this->__post[$k])?$this->__post[$k]:null, $options, $this->__post, $k);
			else
				$validator = new $vname(isset($this->__post[$k])?$this->__post[$k]:null, $options, $this->__post, $k);
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
	* @param array values: The raw POST data
	* @param string name: This field's name (may be null)
	*/
	public function __construct($value, $options, $values, $name=null);
	/** Returns error string or false */
	public function validate();
	/** Returns cleaned value */
	public function clean();
	/** Returns field's name */
	public function name();
}

/**
* Base abstract validator class.
*
* Common rules: 'required'=>boolean|field_name|lambda($field_values, $all_values, $field_validator_object).
*                   When true (or when the lambda returns true, check that field
*                   is not empty. If a string field_name is given, this field is
*                   required when the other field is false
*               'choices'=>array()
*                   When specified, the validated value MUST be in_array(<choice>)
*               'custom'=>lambda($field_values, $all_values, $field_validator_object).
*                   validates using custom function. Must return string error or
*                   null on success.
*               'default'=>specify a default value to be used in place of null
*               'process'=>lambda($value)
*                   Lambda function to post-proces the final value after validation
* Custom validation options MUST start with '_'
*/
abstract class base_validator implements field_validator {

	private $__name;
	private $__value;
	private $__values;
	private $__options;
	private $__cleaned;

	public function __construct($value, $options, $values, $name=null) {
		$this->__name = $name;
		$this->__value = $value;
		$this->__values = $values;
		$this->__options = $options;
		$nullified = $this->nullify($value);
		// Null doesn't need to be cleaned
		if( $nullified === null ) {
			if( array_key_exists('default',$this->__options) )
				$this->__cleaned = $this->__options['default'];
			else
				$this->__cleaned = $nullified;
		}else
			$this->__cleaned = $this->do_clean($nullified, $this->__options);
	}

	public function name() {
		return $this->__name;
	}

	public function clean() {
		return $this->__cleaned;
	}

	public function options() {
		return $this->__options;
	}

	public function validate() {
		$v = & $this->__cleaned;
		$o = & $this->__options;

		// Perform common validation tasks
		if( array_key_exists('required',$o) && $o['required'] ) {
			if( is_callable($o['required']) )
				$req = $o['required']($v, $this->__values, $this);
			elseif( is_string($o['required']) )
				$req = ! isset($this->__values[$o['required']]) || ! $this->__values[$o['required']];
			else
				$req = $o['required'];

			if( $req ) {
				$err = $this->check_required($v);
				if( $err )
					return $err;
			}
		}
		if( array_key_exists('custom',$o) && $o['custom'] ) {
			$err = $o['custom']($v, $this->__values, $this);
			if( $err )
				return $err;
		}
		if( isset($o['choices']) && ! in_array($v, $o['choices']) )
			return _('Field must be one of') . ' "' . implode(',',$o['choices']) . '".';

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
	protected function do_clean($val, $opt) {
		if( is_string($val) )
			$val = trim($val);
		return $this->nullify($val);
	}

	protected function check_required($val) {
		if( $val === null )
			return _("Field can't be empty") . '.';
		return false;
	}

	protected function do_validate($v, $o) {
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

	protected function do_validate($v, $o) {
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
*                          'step'=>val, Number must be a multiple of value
*                          'decsep'=>char, Decimal separator used
*                                          The default (null) accepts both '.' and ','
*                          'thosep'=>char, Thousands separator used
*                                          The default (null) means none
*/
abstract class number_validator extends base_validator {

	protected function do_validate($v, $o) {
		if( isset($o['min']) )
			if( $err = $this->check_min($v, $o['min']) )
				return $err;
		if( isset($o['max']) )
			if( $err = $this->check_max($v, $o['max']) )
				return $err;
		if( isset($o['step']) )
			if( $err = $this->check_step($v, $o['step']) )
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
	protected function check_step($val, $step) {
		if( $val === null || $val == 0 )
			return false;
		$ratio = $val / $step;
		$diff = $ratio - round($ratio);
		// 1.19e-7f is FLT_EPSILON in C, should be the maximun relative float error
		if( $diff < .000000119 )
			return false;
		return str_replace('{number}', $this->format_number($step), _('Number must be a multiple of {number}')) . '.';
	}

	/**
	* Utility function to convert a number (double or int) to string
	*/
	protected function format_number($num) {
		if( floor($num) == $num )
			return sprintf('%u', $num);
		return sprintf('%f', $num);
	}

	/**
	 * Utility function for subclass usage
	 */
	protected function do_number_clean($val, $opt, $type) {
		if( gettype($val) == $type )
			return $val;

		$val = trim($val);

		if( isset($opt['thosep']) )
			$val = str_replace($opt['thosep'], '', $val);

		$decsep = isset($opt['decsep']) ? $opt['decsep'] : ',';
		$val = str_replace($decsep, '.', $val);

		return $val;
	}

}

/**
* Validate as integer
*
* @return int
*/
class int_validator extends number_validator {

	protected function do_clean($val, $opt) {
		return (int)$this->do_number_clean($val, $opt, 'integer');
	}
}

/**
* Validate as double
*
* @return double
*/
class double_validator extends number_validator {

	protected function do_clean($val, $opt) {
		return (double)$this->do_number_clean($val, $opt, 'double');
	}
}

/**
* Validate as boolean
*
* @return bool
*/
class bool_validator extends base_validator {

	protected function do_clean($val, $opt) {
		if( gettype($val) == 'boolean' )
			return $val;
		return (bool)trim($val);
	}

}

/**
* Validate as DateTime
*
* Custom validation rules: 'min'=>val, Date must be greater or equal than value
*                                 (string or valid DateTime constructor arg)
*                          'max'=>val, Date must be lesser or equal than value
*                                 (string or valid DateTime constructor arg)
*
* @return object DateTime
*/
class date_validator extends base_validator {

	protected function do_validate($v, $o) {
		if( isset($o['min']) ) {
			if( ! $o['min'] instanceof \DateTime )
				$o['min'] = new \DateTime($o['min']);
			if( $err = $this->check_min($v, $o['min']) )
				return $err;
		}
		if( isset($o['max']) ) {
			if( ! $o['max'] instanceof \DateTime )
				$o['max'] = new \DateTime($o['max']);
			if( $err = $this->check_max($v, $o['max']) )
				return $err;
		}
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

	protected function do_clean($val, $opt) {
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
		if( $date instanceof Date )
			$fmt = '%x';
		else
			$fmt = '%c';
		return strftime('%x', $date->format('U'));
	}
}

/**
* Validate as time only
*
* @return object dophp\Time
*/
class time_validator extends base_validator {

	protected function do_clean($val, $opt) {
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
 * Validate as duration
 *
 * @return int: number of seconds
 */
class duration_validator extends base_validator {

	protected function do_clean($val, $opt) {
		if( gettype($val) == 'int')
			return $val;

		$vals = preg_split('/(\\.|:)/', trim($val));
		if( count($vals) < 1 || count($vals) > 3 )
			return null;

		foreach( $vals as & $v ) {
			$v = trim($v);
			if( ! is_numeric($v) )
				return null;
			$v = (int) $v;
		}
		unset($v);

		for( $i = 0; $i < 3; $i++ )
			if( ! isset($vals[$i]) )
				$vals[$i] = 0;

		$sec = $vals[2] + ($vals[1] * 60) + ($vals[0] * 60 * 60); // Seconds, minutes, hours
		return $sec;
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

	protected function do_clean($val, $opt) {
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
			return _("Invalid file size") . " ({$val['size']}).";
		if( ! isset($val['tmp_name']) || ! file_exists($val['tmp_name']) )
			return _("Could not read local copy of file");
		if( filesize($val['tmp_name']) != $val['size'] )
			return _("File size mismatch");
		return false;
	}
	protected function do_validate($v, $o) {
		if( isset($v['error']) && $v['error'] )
			return _("Error") . " {$v['error']} ";
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
*                          'required': boolean|lambda($field_values, $all_values, $field_validator_object)
*                                      if true (or when lambda returns true),
*                                      an array must be present
*                          'errarray': boolean
*                                      if true, returns errors as array instead of string
*                                      (this may become default behavior in future)
*/
class array_validator implements field_validator {

	private $__name;
	private $__value = null;
	private $__error = null;
	private $__options;

	public function __construct($value, $options, $values, $name=null) {
		$this->__name = $name;
		$this->__options = $options;

		if( ! $value ) {
			if( array_key_exists('required',$options) && $options['required'] )
				if( ! is_callable($options['required']) || $options['required']($value, $values, $this) )
					$this->__error = _("Field can't be empty") . '.';

		}elseif( ! is_array($value) ) {
			$this->__error = _("Must be an array") . '.';

		}elseif( array_key_exists('rules',$options) && $options['rules'] ) {
			$validator = new Validator($value, $_FILES, $options['rules']);
			list($this->__value, $this->__error) = $validator->validate();

		}else
			$this->__value = $value;

	}

	public function name() {
		return $this->__name;
	}

	public function clean() {
		return $this->__value;
	}

	public function validate() {
		if( isset($this->__options['errarray']) && $this->__options['errarray'] )
			return $this->__error;

		return $this->__error ? print_r($this->__error, true) : $this->__error;
	}
}
