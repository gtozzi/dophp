<?php declare(strict_types=1);

/**
 * @file Fields.php
 * @brief Form-field widgets
 * @author Gabriele Tozzi <gabriele@tozzi.eu>
 */

namespace dophp\widgets;

require_once 'FormWidget.php';


/**
 * Interface for a Field
 */
interface Field extends FormWidget {
	const V_NOTNEEDED = -1;
	const V_ERROR = 0;
	const V_SUCCESS = 1;

	/** Character to be used as separator in names */
	const NAME_SEPARATOR = '.';

	/** Sets this field's group */
	public function setGroup(FieldGroup $group);

	/** Returns this field's group */
	public function getGroup() /** TODO PHP 7.2 : ?FieldGroup */;

	/** Returns field's HTML name including namespace */
	public function getHtmlName(): string;

	/** Returns field's display value */
	public function getDisplayValue(): string;

	/**
	 * Sets the new field's value and status from display value
	 * If the field is read-only, it should do nothing
	 *
	 * @param $value string: The display value (usually a string as obtained from user)
	 * @param $all array: Array of all the other values, used during validation
	 */
	public function setDisplayValue($value, array $all);

	/** Returns field's internal value */
	public function getInternalValue();

	/** Sets the new field's value from display value, resets error status */
	public function setInternalValue($value);

	/** Sets field's soft required status */
	public function setSoftRequired(bool $value);

	/** Returns field's smarty template name */
	public function getTemplate(): string;

	/** Returns field's type */
	public function getType(): string;

	/** Gets the current validation status */
	public function getVStatus();

	/** Gets the current validation feedback message */
	public function getVFeedback();

	/** Tells whether this field has valid data */
	public function isValid(): bool;

	/** Tells whether this field is read only */
	public function isReadOnly(): bool;

	/** Tells whether this field is required (may return null if unknown) */
	public function isRequired(): ?bool;

	/** Tells whether this field is required in special cases (may return null if unknown ) */
	public function isSoftRequired(): ?bool;

	/**
	 * Sets/unsets the field's readonly status
	 *
	 * @param $status boool: The new readonly status
	 */
	public function setReadOnly(bool $status);

	/** Returns display options for the field */
	public function getDisplayOptions(): array;
}


/**
 * Base class for a Field
 */
abstract class BaseField extends BaseFormWidget implements Field {

	/** The field's group */
	protected $_group = null;

	/** The fields' starting internal value */
	protected $_iv = null;
	/** The field's starting display value */
	protected $_dv = '';
	/** The field's template name, may be overridden in child */
	protected $_template = 'widgets/input.tpl';
	/** The field's returned type, may be overridden in child */
	protected $_type = 'text';
	/** The field's readonly status */
	protected $_readonly = false;
	/** Marks this field as required in special cases */
	protected $_softrequired = false;

	/** Validation options */
	protected $_vopts = [];
	/** Validator type */
	protected $_vtype = 'string';
	/** Validation status */
	protected $_vstatus = Field::V_NOTNEEDED;
	/** The field's validtaion feedback text */
	protected $_vfeedback = null;

	/** Display options */
	protected $_dopts = [];

	public function setGroup(FieldGroup $group) {
		$this->_group = $group;
	}

	public function getGroup() {
		return $this->_group;
	}

	public function getHtmlName(): string {
		$es = array_merge($this->_namespace, explode(self::NAME_SEPARATOR, $this->_name));

		foreach( $es as $k => &$v )
			if( $k > 0 )
				$v = "[$v]";
		unset($v);

		return implode($es);
	}

	public function getDisplayValue(): string {
		return $this->_dv;
	}

	public function setDisplayValue($value, array $all) {
		if( $this->isReadOnly() )
			return;
		return $this->_setDisplayValue($value, $all);
	}

	/**
	 * Just like setDisplayValue(), but only called after read-only check
	 */
	protected function _setDisplayValue($value, array $all) {
		if( $value === null )
			$value = '';
		elseif( gettype($value) != 'string' )
			throw new \Exception(get_class($this) . '[' . $this->_name
				. ']: String expected, got ' . gettype($value));

		$this->_dv = $value;
		list($this->_iv, $this->_vfeedback) = $this->_validate($value, $all);
		$this->_vstatus = $this->_vfeedback ? Field::V_ERROR : Field::V_SUCCESS;
	}

	/**
	 * Runs the validtaion and sets the status accordingly
	 *
	 * @param $value mixed: The user submitted value
	 *                      (most of the time a string, array for files)
	 * @param $all array: Associative array of all values
	 * @return array [ internal value, error message ]
	 */
	protected function _validate($value, array $all) {
		$vclass = '\\dophp\\' . $this->_vtype . '_validator';
		$validator = new $vclass($value, $this->_getValidationOptions(), $all, $this->getName());
		return [ $validator->clean(), $validator->validate() ];
	}

	/**
	 * Returns validation options, may be overridden in child
	 */
	protected function _getValidationOptions(): array {
		return $this->_vopts;
	}

	public function getInternalValue() {
		return $this->_iv;
	}

	public function setInternalValue($value) {
		$this->_iv = $value;
		$this->_dv = $this->format($this->_iv);
		$this->_vstatus = Field::V_NOTNEEDED;
		$this->_vfeedback = null;
	}

	/**
	 * Returns a formatted display version of given internal value
	 */
	public function format($value) {
		if( is_array($value) )
			throw new \InvalidArgumentException('Value is array ' . print_r($value, true));
		return (string)$value;
	}

	public function getTemplate(): string {
		return $this->_template;
	}

	public function getType(): string {
		return $this->_type;
	}

	public function getVStatus() {
		return $this->_vstatus;
	}

	public function getVFeedback() {
		return $this->_vfeedback;
	}

	public function isValid(): bool {
		switch( $this->_vstatus ) {
		case Field::V_SUCCESS:
		case Field::V_NOTNEEDED:
			return true;
		case Field::V_ERROR:
			return false;
		}
		throw new \Exception("Not implemented validation status \"{$this->_vstatus}\"");
	}

	public function isReadOnly(): bool {
		return $this->_readonly;
	}

	public function setReadOnly(bool $status) {
		$this->_readonly = $status;
	}

	public function isRequired(): ?bool {
		if( ! isset($this->_vopts['required']) || ! $this->_vopts['required'] )
			return false;

		if( $this->_vopts['required'] === true )
			return true;

		// Conditional require
		// TODO: support it better
		if( is_string($this->_vopts['required']) )
			return true;

		return null;
	}

	public function isSoftRequired(): ?bool {
		return $this->_softrequired;
	}

	public function setSoftRequired(bool $value) {
		$this->_softrequired = $value;
	}

	public function getDisplayOptions(): array {
		return $this->_dopts;
	}
}


/**
 * An hidden field
 */
class HiddenField extends BaseField {

	protected $_type = 'hidden';

	public function __construct($name, array $namespace = []) {
		parent::__construct($name, $namespace);
	}
}


/**
 * Defines a field to be displayed in an "edit" page
 */
abstract class InputField extends BaseField {

	/** The field's suggested placeholder (if given) */
	protected $_placeholder = null;

	/** The field's link button url, if any */
	protected $_linkurl = null;

	/**
	 * Constructs the field
	 *
	 * @param $name string: The field's name
	 * @param $namespace array: The field's namespace
	 * @param $opt array: Optional attributes to set
	 */
	public function __construct(string $name, array $namespace = [], array $opts = []) {
		parent::__construct($name, $namespace);

		foreach( $opts as $name => $value ) {
			$on = "_$name";
			if( ! property_exists($this, $on) )
				throw new \Exception("Invalid InputField attribute \"$name\"");

			$this->_noClosures("{$this->_name}/$name", $value);

			$this->$on = $value;
		}

		$this->_afterConstruct();
	}

	/**
	 * May be overridden in child, doe snothing by default
	 */
	protected function _afterConstruct() {
	}

	/**
	 * Throws an exception if given value contains closures
	 */
	protected function _noClosures(string $name, $val) {
		if ( is_object($val) && $val instanceof \Closure )
			throw new \Exception("Closure in \"$name\" will prevent serialization");

		if ( is_array($val) )
			foreach( $val as $k => $v )
				$this->_noClosures("$name/$k", $v);
	}

	public function getPlaceholder() {
		return $this->_placeholder;
	}

	public function getLinkUrl() {
		return $this->_linkurl;
	}

	public function setLinkUrl(string $url) {
		$this->_linkurl = $url;
	}
}


/**
 * A Text input field
 */
class TextField extends InputField {

	/** The max allowed text length */
	protected $_maxlen = null;

	public function getMaxLen() {
		return $this->_maxlen;
	}

	protected function _getValidationOptions(): array {
		$vo = parent::_getValidationOptions();
		if( $this->_maxlen )
			$vo['length'] = [0,$this->_maxlen];
		return $vo;
	}
}


/**
 * A bigger text input field
 */
class TextAreaField extends TextField {

	protected $_type = 'textarea';

	/** Number of rows size */
	protected $_rows = 4;

	public function getRows(): int {
		return $this->_rows;
	}

}


/**
 * A slightly different text field
 */
class PasswordField extends TextField {
	protected $_type = 'password';
}


/**
 * A Date input field
 */
class DateField extends TextField {

	protected $_type = 'date';
	protected $_maxlen = 10;
	protected $_vtype = 'date';

	public function format($value) {
		if( $value === null )
			return '';
		return $value->format('d.m.Y');
	}

	protected function _getValidationOptions(): array {
		$vo = parent::_getValidationOptions();
		if( isset($vo['length']) )
			unset($vo['length']);
		return $vo;
	}

}


/**
 * A Time input field
 */
class TimeField extends TextField {

	protected $_type = 'time';
	protected $_maxlen = 8;
	protected $_vtype = 'time';

	public function format($value) {
		if( $value === null )
			return '';
		return $value->format('H:i');
	}

	protected function _getValidationOptions(): array {
		$vo = parent::_getValidationOptions();
		if( isset($vo['length']) )
			unset($vo['length']);
		return $vo;
	}

}


/**
 * A numeric input field
 */
class NumberField extends TextField {

	/** The allowed minimum value */
	protected $_min = null;
	/** The allowed maximum value */
	protected $_max = null;
	/** The allowed step (null = any) */
	protected $_step = null;

	protected $_type = 'number';
	/** @see self::_afterConstruct */
	protected $_vtype = 'int';

	public function getMin() {
		return $this->_min;
	}

	public function getMax() {
		return $this->_max;
	}

	public function getStep() {
		return $this->_step;
	}

	protected function _afterConstruct() {
		if( is_float($this->_min) || is_float($this->_max) || is_float($this->_step) )
			$this->_vtype = 'double';

		parent::_afterConstruct();
	}

	protected function _getValidationOptions(): array {
		$vo = parent::_getValidationOptions();
		if( $this->_min !== null )
			$vo['min'] = $this->_min;
		if( $this->_max !== null )
			$vo['max'] = $this->_max;
		if( $this->_step !== null )
			$vo['step'] = $this->_step;
		return $vo;
	}
}


/**
 * A numeric field specifically for handling a currency amount
 */
class CurrencyField extends NumberField {

	protected $_type = 'currency';
	protected $_vtype = 'double';
	protected $_step = 0.01;

	/** The used currency symbol */
	protected $_curSymbol = '€';
	/** How many decimal digits */
	protected $_decDigits = 2;
	/** The decimal separator */
	protected $_decSep = ',';
	/** The thousands separator */
	protected $_thoSep = '.';

	public function __construct(string $name, array $namespace = [], array $opts = []) {
		parent::__construct($name, $namespace, $opts);

		$this->_vopts['decsep'] = & $this->_decSep;
		$this->_vopts['thosep'] = & $this->_thoSep;
	}

	public function getCurSymbol(): string {
		return $this->_curSymbol;
	}
	public function getDecDigits(): int {
		return $this->_decDigits;
	}
	public function getDecSep(): string {
		return $this->_decSep;
	}
	public function getThoSep(): string {
		return $this->_thoSep;
	}

	public function format($value) {
		if ($value === null)
			return '';
		elseif ($value instanceof \dophp\Decimal)
			$value = $value->toDouble();
		else
			$value = (float)$value;
		return number_format($value, $this->_decDigits, $this->_decSep, $this->_thoSep);
	}
}



/**
 * A file upload field
 */
class FileField extends TextField {
	protected $_type = 'file';
	protected $_vtype = 'file';

	protected function _setDisplayValue($value, array $all) {
		if( $value !== null && ! is_array($value) )
			throw new \Exception(get_class($this) . '[' . $this->_name
				. ']: Array expected, got ' . gettype($value));

		$this->_dv = '';
		list($this->_iv, $this->_vfeedback) = $this->_validate($value, $all);
		$this->_vstatus = $this->_vfeedback ? Field::V_ERROR : Field::V_SUCCESS;
	}
}


/**
 * Asyncronous file upload field
 */
class AsyncFileField extends InputField {
	protected $_type = 'asyncFile';
	protected $_vtype = 'int';

	//TODO: make sure file is created by user, maybe do not retransmit ID,
	//      just store it in session
}


/**
 * A checkbox field
 */
class BoolField extends InputField {

	const DEFAULT = false;

	protected $_type = 'checkbox';
	protected $_vtype = 'bool';

	protected $_iv = false;

	public function getInternalValue() {
		return $this->_iv;
	}

	protected function _getValidationOptions(): array {
		$vo = parent::_getValidationOptions();
		if( ! isset($vo['default']) )
			$vo['default'] = static::DEFAULT;
		return $vo;
	}
}


/**
 * A select input with a single option
 */
class SelectField extends InputField {

	/** Limit number of ajax results */
	const AJAX_LIMIT = 25;

	protected $_type = 'select';
	protected $_vtype = 'string';

	/** Array list or SelectQuery of currently selectable options */
	protected $_options;
	/**
	 * Array list or SelectQuery to recover the description of a selected but
	 * no longer selectable option
	 * @see self::_addCurOpt
	 */
	protected $_wideOptions = null;
	/** If true, will add an empty option */
	protected $_addNullOpt = true;
	/**
	 * If true, will add an option for the currently selected element if not available
	 * @see self::_wideOptions
	 */
	protected $_addCurOpt = true;
	/** Parameters for the options query */
	protected $_optParams = [];
	/** Enables AJAX (with select2)
	 *
	 * May simply be true or an array of options:
	 * - filter: callable filter(SelectOption $option, string $term, array $extra)
	 *           @param $option SelectOption The Select option
	 *           @param $term string: The provided search term
	 *           @param $extra array: Extra provided options
	 *           @return true To keep it, False to discard it
	 */
	protected $_ajax = false;

	/** Optional overridden custom validator */
	private $__customValidator = null;

	protected function _afterConstruct() {
		// String options are parsed as query
		if( is_string($this->_options) )
			$this->_options = new \dophp\SelectQuery($this->_options);

		if( isset($this->_vopts['custom']) )
			$this->__customValidator = $this->_vopts['custom'];
		$this->_vopts['custom'] = [ $this, 'validateOption' ];
	}

	/**
	 * Returns a list of options
	 *
	 * @param $ajax array: associative array of ajax options when invoked in ajax mode:
	 *              - term: the search term
	 *              - params: the base option params
	 *              - extra: Extra params
	 *
	 * @yield SelectOption
	 */
	public function getOptions(array $ajax=null): \Generator {
		if( ! isset($this->_options) )
			throw new \Exception('Options not defined');

		if( $this->_addNullOpt )
			yield new SelectOption('', false, '');

		// Returns raw options from builtin array
		if( is_array($this->_options) ) {
			$yieldCount = 0;
			$foundSelected = false;
			foreach( $this->_options as $id => $descr ) {
				$selected = ( $id == $this->_iv );
				$foundSelected = $foundSelected || $selected;

				$option = new SelectOption($id, $selected, $descr);

				if( $ajax ) {
					// Base ajax filter
					if( ! $selected ) {
						if( $yieldCount >= self::AJAX_LIMIT )
							continue;

						if( strlen($ajax['term'])
								&& strpos(strtolower($descr), strtolower($ajax['term'])) === false )
							continue;
					}

					// Custom ajax filter
					if( is_array($this->_ajax) && isset($this->_ajax['filter'])
							&& ! $this->_ajax['filter']($option, $ajax['term'], $ajax['extra']) )
						continue;
				} else {
					// Small trick: when readonly or ajax, only send the selected one
					// in the static request
					if( $selected || ! $this->isReadonly() || ! $this->isAjax() ) {
						$yieldCount++;
						yield $option;
					}
					continue;
				}
			}

			if( $this->_iv && ! $foundSelected && $this->_addCurOpt && ! $this->isAjax() )
				yield $this->__genSelectedOption();
			return;
		}

		// Returns options from query
		if( $this->_options instanceof \dophp\SelectQuery ) {
			$query = clone $this->_options;
			if( count($query->cols()) != 2 )
				throw new \Exception("Query must return exactly 2 cols. Query: '{$this->_options}'");

			list($idk, $desck) = array_keys($query->cols());
			$params = $ajax ? $ajax['params'] : $this->_optParams;

			if( $ajax ) {
				$query->setLimit(self::AJAX_LIMIT);

				if( strlen($ajax['term']) ) {
					$pt = "%{$ajax['term']}%";
					$ajaxparam = self::__addParam($params, 'term', $pt);
					$qcol = $query->col($desck)['qname'];
					$where = "$qcol LIKE :$ajaxparam";
					$query->addWhere($where);

					// Show exact match first
					$query->prependOrderBy("LENGTH($qcol) ASC");
				}
			} else {
				// Small trick: when readonly or ajax, only send the selected one
				if( $this->isReadonly() || $this->isAjax() ) {
					$selparam = self::__addParam($params, 'selected', $this->_iv);
					$where = $query->col($idk)['qname'] . " = :$selparam";
					$query->addWhere($where);
				}
			}

			$foundSelected = false;
			foreach( \DoPhp::db()->xrun($query, $params) as $r ) {
				$id = $r[$idk];
				$descr = (string)$r[$desck];
				$selected = ( $id == $this->_iv );
				$foundSelected = $foundSelected || $selected;
				$option = new SelectOption($id, $selected, $descr);

				// Custom ajax filter
				if( is_array($this->_ajax) && isset($this->_ajax['filter'])
						&& ! $this->_ajax['filter']($option, $ajax['term'], $ajax['extra']) )
					continue;

				yield $option;
			}

			if( $this->_iv && ! $foundSelected && $this->_addCurOpt && ! $this->isAjax() )
				yield $this->__genSelectedOption();
			return;
		}

		throw new \Exception('Should not reach this point');
	}

	private function __genSelectedOption(): SelectOption {
		$descr = "({$this->_iv})";

		if( $this->_wideOptions ) {
			if( ! is_array($this->_wideOptions) )
				throw new \dophp\NotImplementedException('Only array wideoptions are supported so far');

			if( isset($this->_wideOptions[$this->_iv]) )
				$descr = $this->_wideOptions[$this->_iv];
		}

		return new SelectOption($this->_iv, true, $descr);
	}

	/** Adds an unique param, internal usage */
	private static function __addParam(array &$params, string $desiredName, $value): string {
		if( ! array_key_exists($desiredName, $params) ) {
			$params[$desiredName] = $value;
			return $desiredName;
		}

		$desiredName = $desiredName . random_int(0, 9);
		return self::__addParam($params, $desiredName, $value);
	}

	/**
	 * Passed to validator
	 */
	public function validateOption($v, $a) {
		if( $this->__customValidator !== null ) {
			$ret = ($this->__customValidator)($v, $a);
			if( $ret )
				return $ret;
		}

		if( ! $v )
			return;

		$errMsg = 'selezione non valida';

		if( is_array($this->_options) ) {
			if( array_key_exists($v, $this->_options) )
				return;
			return $errMsg;
		}

		if( $this->_options instanceof \dophp\SelectQuery ) {
			$query = clone $this->_options;
			if( count($query->cols()) != 2 )
				throw new \Exception("Query must return exactly 2 cols. Query: '{$this->_options}'");

			list($idk, $desck) = array_keys($query->cols());
			$params = $this->_optParams;
			$vparam = self::__addParam($params, 'myvalue', $v);
			$where = $query->col($idk)['qname'] . " = :$vparam";
			$query->addWhere($where);

			$query->setLimit('1');

			if( \DoPhp::db()->xrun($query, $params)->fetch() )
				return;
			return $errMsg;
		}

		return 'errore interno: not implemented';
	}

	public function isAjax(): bool {
		return (bool)$this->_ajax;
	}

	/**
	 * Serves an ajax query
	 *
	 * @param $params array Parameters array:
	 *                - _type: The query type, must be 'query'
	 *                - term: The search term
	 *                - ajaxParams: If given, may override optParams
	 * @see https://select2.org/data-sources/formats
	 * @return array of data in select2 data format
	 */
	public function ajaxQuery($params) {
		if( ! isset($params['_type']) )
			throw new \Exception('Missing type');
		if( $params['_type'] != 'query' )
			throw new \Exception("Unsupported type {$params['_type']}");

		$term = $params['term'] ?? '';
		$extra = $params['ajaxParams'] ?? [];

		// Calculate the new options params
		$params = $this->_optParams;
		foreach( $extra as $k => $v )
			if( $v )
				$params[$k] = $v;

		$options = $this->getOptions([
			'term' => $term,
			'params' => $params,
			'extra' => $extra,
		]);

		$data = [];
		foreach( $options as $o ) {
			/// @see https://select2.org/data-sources/formats
			$r = [
				'id' => $o->getId(),
				'text' => $o->getDescr(),
			];

			if( $o->isSelected() )
				$r['selected'] = true;

			$data[] = $r;
		}

		return [
			'results' => $data,
		];
	}

}

/**
 * An option for a select object
 */
class SelectOption {

	protected $_id;
	protected $_descr;
	/** True if selected */
	protected $_selected;

	/**
	 * Creates the option
	 *
	 * @param $id mixed: The option ID
	 * @param $selected bool: Whether this option is selected or not
	 * @param $descr string: The option description
	 */
	public function __construct($id, bool $selected, string $descr=null) {
		$this->_id = $id;
		$this->_selected = $selected;
		$this->_descr = $descr;
	}

	/**
	 * Returns the unique ID
	 */
	public function getId() {
		return $this->_id;
	}

	public function getDescr() {
		return $this->_descr;
	}

	public function isSelected(): bool {
		return $this->_selected;
	}

	public function setSelected(bool $status) {
		$this->_selected = $status;
	}

}


/**
 * A multiple selection widget, with support for extra features
 *
 * This is basically a container for options and attrs. Options can also contain
 * more option-specific attrs itself
 */
class MultiSelectField extends BaseField {

	const SELECTED_KEY = 'selected';
	const ATTRS_KEY = 'attrs';

	/** List of MultiSelectFieldOption */
	protected $_options = [];
	/** List of MultiSelectFieldAttr */
	protected $_attrs = [];

	/**
	 * Creates the widget object
	 *
	 * @param $name string: The input name
	 * @param $opt array: Array of rendering/customization options, keys:
	 *             - attrs: associative array of global attrs (by unique name),
	 *                      only MultiSelectFieldAttr::TYPE_NUMBER allowed
	 *                      every attr contains the following keys:
	 *                      - type: the attribute type, one of
	 *                              MultiSelectFieldAttr::TYPE_* consts
	 *                      - descr: the short attribute description
	 *                      - label: the long/overlay attribute description
	 *             - options: associative array of options (by unique id)
	 *                        evey option contains the following keys
	 *                      - attrs: associative array of per-option attrs,
	 *                               in the same form as global attrs
	 *                      - query: the query used to get options list, must
	 *                               return two columns (id, description)
	 */
	public function __construct(string $name, array $namespace, array $opt) {
		parent::__construct($name, $namespace);

		// Parse global attributes
		if( isset($opt['attrs']) ) {
			if( ! is_array($opt['attrs']) )
				throw new \Exception('Attrs must be array');

			foreach( $opt['attrs'] as $name => $ao )
				$this->_attrs[$name] = new MultiSelectFieldAttr($name, $ao);
		}

		// Parse options
		if( ! isset($opt['options']) )
			throw new \Exception('Missing options');
		$oattrs = $opt['options']['attrs'] ?? [];
		if( ! isset($opt['options']['query']) )
			throw new \Exception('Missing options query');
		$oquery = $opt['options']['query'];

		foreach( \DoPhp::db()->xrun($oquery) as $r ) {
			if( count($r) != 2 )
				throw new \Exception('Query must return exactly 2 rows');
			$id = array_shift($r);
			$descr = array_shift($r);
			$attrs = [];
			foreach( $oattrs as $name => $ao ) {
				if( isset($ao['type']) ) {
					$ocls = "\\dophp\\widgets\\MultiSelectFieldOption{$ao['type']}Attr";
					unset($ao['type']);
				} else
					$ocls = '\dophp\widgets\MultiSelectFieldOptionAttr';
				$attrs[$name] = new $ocls($name, $ao);
			}
			$this->_options[$id] = new MultiSelectFieldOption($id, false, $descr, $attrs);
		}
	}

	protected function _setDisplayValue($value, array $all) {
		if( ! is_array($value) ) {
			$this->_vstatus = Field::V_ERROR;
			return;
		}

		$this->setInternalValue($value);
		$this->_vstatus = Field::V_SUCCESS;
	}

	/**
	 * Sets internal values, $value MUST come as associative array
	 *
	 * @param $value array, associative, keys:
	 *               - attrs: associative array of global attrs, by unique name,
	 *                        the value is the new attribute's value
	 *               - options: associative array of options to set data for,
	 *                          by id, with keys:
	 *                          - selected: boolean yes/no
	 *                          - attrs: associative array of attrs (see above)
	 */
	public function setInternalValue($value) {
		if( $value === null )
			$value = [];

		if( ! is_array($value) )
			throw new \Exception('Value must be array');

		if( isset($value['attrs']) )
			foreach( $value['attrs'] as $name => $val ) {
				if( ! isset($this->_attrs[$name]) )
					throw new \Exception("Unknown global attr $name");
				$this->_attrs[$name]->setValue($val);
			}

		if( isset($value['options']) )
			foreach( $value['options'] as $id => $val ) {
				if( ! isset($this->_options[$id]) )
					throw new \Exception("Unknown option $name");

				if( isset($val['selected']) )
					$this->_options[$id]->setSelected( (bool)$val['selected'] );

				if( isset($val['attrs']) )
					foreach( $val['attrs'] as $name => $val )
						$this->_options[$id]->setAttr($name, $val);
			}

		$this->_vstatus = Field::V_NOTNEEDED;
		$this->_vfeedback = null;
	}

	public function getInternalValue() {
		$ret = [
			'attrs' => [],
			'options' => [],
		];
		foreach( $this->_attrs as $n => $a )
			$ret['attrs'][$n] = $a->getValue();
		foreach( $this->_options as $id => $o ) {
			$ret['options'][$id] = [
				'selected' => $o->isSelected(),
				'attrs' => [],
			];
			foreach( $o->getAttrs() as $n => $a )
				$ret['options'][$id]['attrs'][$n] = $a->getValue();
		}
		return $ret;
	}

	/**
	 * Yields list of selected items
	 *
	 * @yield MultiSelectFieldOption
	 */
	public function getSelected(): \Generator {
		foreach( $this->_options as $o )
			if( $o->isSelected() )
				yield $o;
	}

	/**
	 * Alias for getInternalValue()
	 *
	 * @see getInternalValue()
	 */
	public function getOptions(): array {
		return $this->_options;
	}

	/**
	 * Returns global attributes
	 *
	 * @return array: The attruibutes
	 */
	public function getAttrs(): array {
		return $this->_attrs;
	}

	protected function _getValidationOptions(): array {
		$vo = [];
		foreach( $this->_options as $o ) {
			$vo[$o->getId()] = [ 'array', [
				self::SELECTED_KEY => [ 'bool', [ 'required' => true ] ],
				self::ATTRS_KEY => [ 'array', [] ],
			]];
			foreach( $o->getAttrs() as $a )
				$vo[$o->getId()][1][self::ATTRS_KEY][1][$a->getName()] = [ $a->getVType(), $a->getVOpts() ];
		}
		return $vo;
	}

	public function format($value) {
		return '<MultiSelectFieldOptionsArray>';
	}

}


/**
 * Represents a single option in a multiselect widget
 */
class MultiSelectFieldOption extends SelectOption {

	/** Associative array pf attributes */
	protected $_attrs = [];

	/**
	 * Creates the option
	 *
	 * @param $id mixed: The option ID
	 * @param $selected bool: Whether this option is selected or not
	 * @param $descr string: The option description
	 * @param $attrs array: Array of possible attributes
	 */
	public function __construct($id, bool $selected, string $descr, array $attrs=[]) {
		parent::__construct($id, $selected, $descr);

		// Make sure attrs key matches name
		foreach( $attrs as $a ) {
			if( ! $a instanceof BaseMultiSelectFieldOptionAttr )
				throw new \Exception('Wrong attribute type ' . gettype($a));
			$this->_attrs[$a->getName()] = $a;
		}
	}

	/**
	 * Returns the description for the "selected" list
	 * by default, just returns the data row, may be overridden
	 *
	 * @param $key mixed: The element key
	 * @param $dataRow mixed: The data row
	 * @return string: The description
	 */
	public function getSelectedDescr(): string {
		return $this->_descr;
	}

	/**
	 * Returns the description for the select box
	 * by default, just returns the data row, may be overridden
	 *
	 * @param $key mixed: The element key
	 * @param $dataRow mixed: The data row
	 * @return string: The description
	 */
	public function getListDescr(): string {
		return $this->_descr;
	}

	/**
	 * Returns all the attributes
	 *
	 * @return array: The attruibutes
	 */
	public function getAttrs(): array {
		return $this->_attrs;
	}

	/**
	 * Returns a single attribute
	 *
	 * @param $name string: The attribute name
	 * @return MultiSelectFieldAttr
	 */
	public function getAttr(string $name): MultiSelectFieldOptionAttr {
		if( ! isset($this->_attrs[$name]) )
			throw new \Exception("Attribute \"$name\" not found");
		return $this->_attrs[$name];
	}

	/**
	 * Sets an attribute's value
	 *
	 * @param $name string: The attribute name
	 * @param $value mixed: The attribute value
	 */
	public function setAttr(string $name, $value) {
		if( ! isset($this->_attrs[$name]) )
			throw new \Exception("Attribute \"$name\" not found");

		$this->_attrs[$name]->setValue($value);
	}

}


/**
 * Base classe for representing a multiselect widget attribute
 */
abstract class BaseMultiSelectFieldAttr {
	/** This attribute's unique name */
	protected $_name;
	/** This attribute's short description */
	protected $_descr;
	/** This attribute's label */
	protected $_label = null;
	/** This attribute's value */
	protected $_value = null;
	/** The rendering type */
	protected $_rtype = 'radio';

	/**
	 * Constructs the object from array
	 *
	 * @param $name string: The internal name
	 * @param $opt array: associative array of options
	 *             - value mixed: The initial value
	 *             - descr string: The description shown to user
	 *             - label string: The attribute label
	 */
	public function __construct(string $name, array $opt) {
		$this->_name = $name;

		if( isset($opt['value']) )
			$this->_value = $opt['value'];

		if( isset($opt['descr']) )
			$this->_descr = $opt['descr'];
		else
			throw new \Exception('Missing descr');

		if( isset($opt['label']) )
			$this->_label = $opt['label'];
	}

	public function getName(): string {
		return $this->_name;
	}

	public function getDescr(): string {
		return $this->_descr;
	}

	public function getLabel(): string {
		return $this->_label;
	}

	/**
	 * Returns render type, what kind if input to use for rendering
	 */
	public function getRType(): string {
		return $this->_rtype;
	}

	public function setValue($value) {
		$this->_value = $value;
	}

	public function getValue() {
		return $this->_value;
	}
}


/**
 * Represents an attribute for a multiselect widget
 */
class MultiSelectFieldAttr extends BaseMultiSelectFieldAttr {

	public function isCheckedFor($id) {
		return $this->_value == $id;
	}
}


/**
 * Base class for an option attribute
 */
abstract class BaseMultiSelectFieldOptionAttr extends BaseMultiSelectFieldAttr {
}


/**
 * Represents a boolean attribute for an option in a multiselect widget
 */
class MultiSelectFieldOptionAttr extends BaseMultiSelectFieldOptionAttr {
	protected $_value = false;
	protected $_rtype = 'checkbox';

	public function isChecked(): bool {
		return $this->_value ? true : false;
	}

	public function setValue($value) {
		$this->_value = $value ? true : false;
	}
}


/**
 * Represents a numeric attribute for an option in a multiselect widget
 */
class MultiSelectFieldOptionNumberAttr extends BaseMultiSelectFieldOptionAttr {
	protected $_value = 0;
	protected $_rtype = 'number';

	public function getValue(): int {
		return $this->_value;
	}

	public function setValue($value) {
		$this->_value = (int)$value;
	}
}


/**
 * Represents an editable tabular array of fields
 */
class TableField extends BaseField {

	protected $_template = 'widgets/table.tpl';

	/** The template form */
	protected $_tpl;
	/** The cols definition */
	protected $_cols;

	/** Name of the key used for ID */
	const ID_KEY = 'id';

	/**
	 * Constructs the field
	 *
	 * @param $name string: The field's name
	 * @param $namespace array: The field's namespace
	 * @param $opt array: Optional attributes to set
	 *             - cols: array definition of columns (Fields)
	 */
	public function __construct(string $name, array $namespace = [], array $opts = []) {
		parent::__construct($name, $namespace);

		if( ! array_key_exists(self::ID_KEY, $opts['cols']) )
			throw new \Exception('ID field "'.self::ID_KEY.'" not defined');

		$this->_cols = $opts['cols'];

		// Create template form
		$cols = [];
		foreach( $this->_cols as $k => $o ) {
			if( $k == self::ID_KEY )
				continue;
			$cols[$this->_name . ".\${idx}.$k"] = $o;
		}
		$this->_tpl = new Form($cols, $this->_namespace);

		$this->_iv = [];
	}

	public function format($value) {
		return null;
	}

	protected function _setDisplayValue($value, array $all) {
		$this->_iv = [];
		$this->_vstatus = Field::V_SUCCESS;
		$this->_vfeedback = null;
		if( $value )
			foreach( $value as $k => $rowData ) {
				$row = new Form($this->_cols, $this->_namespace);
				$row->setDisplayValues($rowData);
				$this->_iv[$k] = $row;

				if( ! $row->isValid() ) {
					$this->_vstatus = Field::V_ERROR;
					$this->_vfeedback = 'Dati non validi';
				}
			}
	}

	public function setInternalValue($value) {
		$this->_iv = [];
		foreach( $value as $k => $rowData ) {
			if( ! array_key_exists(self::ID_KEY, $rowData) )
				throw new \Exception('Missing ID in row data');

			// Adjust names
			$cols = [];
			$data = [];
			foreach( $this->_cols as $x => $o ) {
				$fname = $this->_name . ".$k.$x";
				$cols[$fname] = $o;
				$data[$fname] = $rowData[$x];
			}

			$row = new Form($cols, $this->_namespace);
			$row->setInternalValues($data);
			$this->_iv[$k] = $row;
		}

		$this->_vstatus = Field::V_NOTNEEDED;
		$this->_vfeedback = null;
	}

	/**
	 * Returns template fields
	 *
	 * @return array of Field objects
	 */
	public function getTplFields(): array {
		return $this->_tpl->fields();
	}

}
