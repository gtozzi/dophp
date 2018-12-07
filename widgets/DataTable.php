<?php declare(strict_types=1);

/**
 * @file DataTable.php
 * @brief Datatable-related widgets
 * @author Gabriele Tozzi <gabriele@tozzi.eu>
 */


namespace dophp\widgets;

require_once(__DIR__ . '/../Page.php');


/**
 * A data table, uses the datatable javascript library
 */
class DataTable extends BaseWidget {
	use \dophp\SmartyFunctionalities;

	/** Useful for debugging, set it back to off */
	const DEBUG_QUERY = true;

	/** Special data key for buttons */
	const BTN_KEY = '~btns~';

	/** asc keyword for order */
	const ORDER_ASC = 'asc';
	/** desc keyword for order */
	const ORDER_DESC = 'desc';

	/** Divider for date range in date filter **/
	const DFILTER_DIVIDER = "||";

	/** Available data types list **/
	const DTYPE_LIST = array(
		"d" => "d",
		"m" => "m",
		"y" => "y",
		"my" => "my",
		"my_sql" => "my_sql",
		"ud" => "ud"
	);

	const CUSTOM_DATE_FILT = "custom-date";

	/**
	 * The FROM part of the query to be executed to get data for the table,
	 * without the "FROM" keyword, must be defined in child
	 */
	protected $_from = null;

	/** Associative array of fixed query params and button params */
	public $params = [];

	/** Fixed where clause, if any, without "WHERE" keyword */
	protected $_where = null;

	/** Fixed group by, if any, without "GROUP BY" keyword */
	protected $_groupBy = null;

	/**
	 * The columns definitions, used as DataTableColumn constructors ($id=>$opt)
	 * Must be specified in child, also used to build the "SELECT" part of query
	 */
	protected $_cols = null;

	/**
	 * The global buttons definitions, will add some defaults,
	 * inited only when in smarty mode
	 */
	protected $_btns = [
		'add' => [
			'label' => 'Aggiungi',
			'url' => '?do={{base}}.mod',
			'icon' => 'fa-plus'
		],
	];

	/**
	 * The per-row button definitions, may be overridden in child
	 * inited only when in smarty mode
	 */
	protected $_rbtns = [
		'edit' => [
			'label' => 'Modifica',
			'url' => '?do={{base}}.mod&id={{id}}',
			'icon' => 'fa fa-pencil'
		],
	];

	/**
	 * The superfilter definition, associative array of checkboxes
	 * 'name' => [
	 * - default: true/false
	 * - label: string
	 * ]
	 */
	protected $_sfilter = [];

	/** The parent page, populated at init time */
	protected $_page;

	/** The database instance, populated at init time */
	protected $_db;

	/** The dophp's config */
	protected $_config;

	/** The dophp's user object */
	protected $_user;

	/** Tells whether elements in this table can be selected */
	public $selectable = false;

	/** Name of the preferences table (es. prefs_table) */
	protected $_prefsTable = 'datatable_prefs';

	/**
	 * Inits some props at runtime, overridable in child
	 */
	protected function _initProps() {
	}

	/**
	 * Constructs the table object
	 *
	 * @param $page PageInterface: the parent page
	 */
	public function __construct(\dophp\PageInterface $page) {
		parent::__construct();

		$this->_initProps();

		$this->_page = $page;
		$this->_db = $page->db();
		$this->_config = $page->config();
		$this->_user = $page->user();

		// Deprecation checks
		if( isset($this->_pk) )
			throw new \Exception('Deprecated PK specification');
		if( isset($this->_query) )
			throw new \Exception('Deprecated Query specification');
		if( isset($this->_cntquery) )
			throw new \Exception('Deprecated CntQuery specification');

		// Checks for data validity
		if( ! isset($this->_from) || ! is_string($this->_from) )
			throw new \Exception('Missing or invalid From definition');
		if( ! isset($this->_cols) || ! is_array($this->_cols) )
			throw new \Exception('Missing or invalid Cols definition');

		// Builds the super filter
		foreach( $this->_sfilter as $name => &$field ) {
			$val = isset($field['default']) && $field['default'];
			$opts = [];
			if( isset($field['label']) )
				$opts['label'] = $field['label'];

			if( ! isset($field['filter']) )
				throw new \Exception("Missing filter query in filter field \"$name\"");
			$filter = $field['filter'];

			$field = new BoolField($name, $opts);
			$field->setInternalValue( $val );
			$field->filter = $filter;
		}
		unset($field);

		// Prepares admin column definitions
		foreach( $this->_cols as $k => &$col ) {
			if( $col instanceof DataTableBaseColumn ) {
				// Nothing to do
			} elseif( is_array($col) )
				$col = new DataTableColumn($k, $col);
			else
				throw new \Exception('Invalid column definition: ' . gettype($col));
		}
		unset($col);

		// Makes sure PK is valid
		$this->_getPkName();
	}

	/**
	 * Gets default order
	 *
	 * @param $asIdx bool: If true, returns colidx instead of colid
	 * @return [ colid, ascdesc ] or [ colidx, ascdesc ]
	 */
	protected function _getDefaultOrder(bool $asIdx = false): array {
		foreach( $this->_cols as $c )
			if( $c->visible )
				return [ $asIdx ? $this->colIdToIdx($c->id) : $c->id, self::ORDER_ASC ];

		throw new \Exception('No visible column');
	}

	/**
	 * Retrieve saved order from DB
	 *
	 * @param $asIdx bool: If true, returns colidx instead of colid
	 * @return [ colid, ascdesc ] or [ colidx, ascdesc ] or null if not found
	 */
	protected function _getSavedOrder(bool $asIdx = false) /** TODO:PHP 7.2 :?array */ {
		if( ! $this->_prefsTable )
			return null;

		$q = "
			SELECT `sort_col`, `sort_ord`
			FROM `" . $this->_prefsTable . "`
			WHERE `collaboratore` = ? AND `table` = ?
		";
		$p = [ $this->_user->getUid(), $this->getClsId() ];
		$r = $this->_db->xrun($q, $p)->fetch();
		if( ! $r || ! $r['sort_col'] || ! $r['sort_ord'] )
			return null;

		if( $r['sort_ord'] != self::ORDER_ASC && $r['sort_ord'] != self::ORDER_DESC )
			return null;

		if( ! array_key_exists($r['sort_col'], $this->_cols) )
			return null;

		return [ $asIdx ? $this->colIdToIdx($r['sort_col']) : $r['sort_col'], $r['sort_ord'] ];
	}

	/**
	 * Save given order
	 *
	 * @param $colId string: sort column id
	 * @param $ascDesc string: asc or desc
	 */
	protected function _saveOrder(string $colId, string $ascDesc) {
		$this->_db->insertOrUpdate('datatable_prefs', [
			'collaboratore' => $this->_user->getUid(),
			'table' => $this->getClsId(),
			'sort_col' => $colId,
			'sort_ord' => $ascDesc,
		]);
	}

	/**
	 * Returns col ID from idx
	 *
	 * @param int: $idx column index, 1-based (0 is the button column)
	 * @return string: Column's unique ID
	 */
	public function colIdxToId( int $idx ): string {
		if( $idx < 1 )
			throw new \Exception('Min idx is 1');
		$idx--;

		$ids = array_keys($this->_cols);
		if( ! array_key_exists($idx, $ids) )
			throw new \Exception("IDX $idx out of range");
		return $ids[$idx];
	}

	/**
	 * Returns col IDX from ID
	 *
	 * @param string: $id column's unique id
	 * @return int $idx: column's 1-based index (0 is the button column)
	 */
	public function colIdToIdx( string $id ): int {
		$idx = array_search($id, array_keys($this->_cols), true);
		if( $idx === false )
			throw new \Exception("ID $id not found");

		return $idx + 1;
	}

	/**
	 * Sets the super filter from array
	 *
	 * @param $sfilter array: Associative array of field->status, unknown keys
	 *                         are ignored
	 */
	public function setSFilter(array $params) {
		foreach( $this->_sfilter as $field )
			$field->setInternalValue( isset($params[$field->getName()]) && $params[$field->getName()] );
	}

	/**
	 * Returns the containing page
	 */
	public function getPage(): \dophp\PageInterface {
		return $this->_page;
	}

	/**
	 * Returns the table HTML structure
	 */
	public function getHTMLStructure(): string {
		// Sets this prop for smarty compatibility
		$this->_name = $this->_page->name();
		$this->_initSmarty();

		$this->_ajaxURL = '';
		foreach( $_GET as $n => $v ) {
			$this->_ajaxURL .= $this->_ajaxURL ? '&' : '?';
			$this->_ajaxURL .= urlencode($n) . '=' . urlencode($v);
		}

		// By default, use the generic "admin" template
		$this->_template = 'widgets/dataTable.tpl';

		// Init buttons
		foreach( $this->_btns as $k => &$button ) {
			if( $button instanceof DataTableButton )
				continue;
			$button = new DataTableButton($this, $k, $button, $this->params);
		}
		unset($button);

		// Init row buttons
		foreach( $this->_rbtns as $k => &$button ) {
			if( $button instanceof DataTableRowButton )
				continue;
			$button = new DataTableRowButton($this, $k, $button, $this->params);
		}
		unset($button);

		$this->_smarty->assign('id', $this->_id);
		$this->_smarty->assign('cols', $this->_cols);
		$this->_smarty->assign('order', $this->_getSavedOrder(true) ?? $this->_getDefaultOrder(true));

		$this->_smarty->assign("getColClass",$this->getColClass());
		$this->_smarty->assign("monthYearList",$this->getMonthYearList());
		$this->_smarty->assign("yearList",$this->getYearList());
		$this->_smarty->assign("dFilterDivider",self::DFILTER_DIVIDER);
		$this->_smarty->assign("customDateFilt",self::CUSTOM_DATE_FILT);

		$this->_smarty->assign('btns', $this->_btns);
		$this->_smarty->assign('rbtns', $this->_rbtns);
		$this->_smarty->assign('btnKey', self::BTN_KEY);
		$this->_smarty->assign('action', '?'.\DoPhp::BASE_KEY."={$this->_name}");
		$this->_smarty->assign('sfilter', $this->_sfilter);
		$this->_smarty->assign('ajaxURL', $this->_ajaxURL);
		$this->_smarty->assign('selectable', $this->selectable);

		return $this->_smarty->fetch($this->_template);
	}

	/**
	 * Returns the access where condition, may be overridden in child
	 *
	 * @see $this->_user
	 * @return array[ string $where, array $params ] or null,
	 *         where condition must not include WHERE keyword
	 */
	protected function _getAccessFilter() {
		return null;
	}

	/**
	 * Returns the name (idx) of the PK column
	 */
	protected function _getPkName() {
		$pk = [];
		foreach( $this->_cols as $k => $c ) {
			if( $k != $c->id )
				throw new \Exception('K and ID mismatch');
			if( $c->pk )
				$pk[] = $c->id;
		}

		if( count($pk) < 1 )
			throw new \Exception('PK is not defined');
		if( count($pk) > 2 )
			throw new \Exception('Composite PK is not supported');

		return $pk[0];
	}

	/**
	 * Returns the base query to be executed, with the super filter and
	 * the access filter, no limit, no order
	 *
	 * @param $cnt boolean: id true, build the COUNT(*) query
	 * @todo Caching
	 * @return [ $query, $where and array, $params array, $groupBy condition (may be null) ]
	 */
	protected function _buildBaseQuery($cnt=false) {
		if( $cnt )
			$q = "SELECT COUNT(*) AS `cnt`\n";
		else {
			$q = "SELECT SQL_CALC_FOUND_ROWS\n";

			$cols = [];
			foreach( $this->_cols as $c )
				if( $c->qname )
					$cols[] = "\t{$c->qname} AS `{$c->id}`";
				else
					$cols[] = "\t`{$c->id}`";
			$q .= implode(",\n", $cols) . "\n";
		}

		$q .= "FROM {$this->_from}\n";
		$where = [];
		if( $this->_where )
			$where[] = "( {$this->_where} )";
		$pars = $this->params;

		// Calculate the super filter where clause
		$sfilter = [];
		foreach( $this->_sfilter as $f ) {
			if( ! $f->getInternalValue() )
				continue;

			$sfilter[] = '(' . $f->filter . ')';
		}
		if( $sfilter )
			$where[] = '( ' . implode(' OR ', $sfilter) . ' )';

		// Get the access where clause
		$afilter = $this->_getAccessFilter();
		if( $afilter !== null ) {
			if( ! is_array($afilter) )
				throw new \Exception('Invalid access filter');
			list($awhere, $apars) = $afilter;
			if( ! is_string($awhere) || ! is_array($apars) )
				throw new \Exception('Invalid access filter');

			$where[] = '( ' . $awhere . ' )';
			$pars = array_merge($pars, $apars);
		}

		return [ $q, $where, $pars, $this->_groupBy ];
	}

	/**
	 * Parses the request data and returns result
	 *
	 * @param $pars: array of parameters, associative
	 * @see https://datatables.net/manual/server-side
	 */
	public function getData( $pars=[] ): array {
		// Parses the super filter
		foreach( $this->_sfilter as $field )
			$field->setInternalValue( (bool)$pars['filter'][$field->getName()] );

		$trx = $this->_db->beginTransaction(true);

		// First count total
		$tot = $this->_count();

		// Base query
		list($q, $where, $p, $groupBy) = $this->_buildBaseQuery();
		$having = [];

		// Calculate explicit types array
		$types = [];
		foreach( $this->_cols as $k => $c )
			if( isset($c->type) )
				$types[$k] = $c->type;

		// Calculate filter having clause
		$filter = [];
		if( isset($pars['columns']) ) {
			$idx = -1;
			foreach( $this->_cols as $c ) {
				$idx++;

				if( ! isset($pars['columns'][$idx]) )
					continue;
				if( ! isset($pars['columns'][$idx]['search']) )
					continue;
				if( ! isset($pars['columns'][$idx]['search']['value']) )
					continue;

				$search = trim($pars['columns'][$idx]['search']['value']);
				if( ! strlen($search) )
					continue;


				// checks if filter is a date filter and calculate where clause
				if($c->type==self::CUSTOM_DATE_FILT){

					$firstElem = $search;
					$isDateRange = false;
					// check if search string is a range of dates,
					// if yes retrieve first element to parse its type
					if(strpos($search,self::DFILTER_DIVIDER)){
						$firstElem = $this->agGetFirstDateInRange($search);
						$isDateRange = true;
					}
					$agDateType = $this->agGetDateType($firstElem);

					// proceed only if date_type has been identified
					if($agDateType){
						$search = $this->getDateFilter($search,$agDateType,$isDateRange,$c->qname);
						$filter[] = $search;
					}
					// if data type is unknown return no results
					else{
						$filter[] = " FALSE ";
					}
				}
				else{
					$filter[] = "{$c->qname} LIKE :f$idx";
					$p[":f$idx"] = "%$search%";
				}


			}
		}

		if( $filter )
			$having[] = '( ' . implode(' AND ', $filter) . ' )';

		// Apply where clause
		if( $where )
			$q .= "\nWHERE " . implode(' AND ', $where);

		// Apply Group By
		if( $groupBy )
			$q .= "\nGROUP BY $groupBy";

		// Apply having clause
		if( $having )
			$q .= "\nHAVING " . implode(' AND ', $having);

		// Apply order, if given
		if( isset($pars['order']) && isset($pars['order'][0]) && $pars['order'][0] ) {
			$order = $pars['order'][0];
			$orderc = (int)$order['column']; // Indexes start at 1 because column 0 is the buttons column
			if( $orderc < 1 )
				$orderc = 1;

			$colId = $this->colIdxToId($orderc);
			$q .= "\nORDER BY $colId ";
			$ord = strtolower($order['dir'])==self::ORDER_DESC ? self::ORDER_DESC : self::ORDER_ASC;
			$q .= strtoupper($ord);

			// Saves the new order preference
			$this->_saveOrder($colId, $ord);
		}

		// Filter by limit, if given
		if( isset($pars['length']) && $pars['length'] > 0 )
			$q .= "\nLIMIT " . ( (int)$pars['start'] ) . ',' . $pars['length'];


		// If no where, do not calculate found rows
		if ( ! $where )
			$q = str_replace('SQL_CALC_FOUND_ROWS', '', $q);

		// Retrieve data
		$rbtnsl = array_keys($this->_rbtns);
		$data = $this->_db->xrun($q, $p, $types)->fetchAll();
		foreach( $data as &$d )
			$d[self::BTN_KEY] = $rbtnsl;
		unset($d);

		// If no where, found must be = tot
		$found = $where ? $this->_db->foundRows() : $tot;

		if( $trx )
			$this->_db->commit();

		$ret = [
			'draw' => $pars['draw'] ?? 0,
			'recordsTotal' => $tot,
			'recordsFiltered' => $found,
			'data' => $this->_encodeData($data),
		];
		if( static::DEBUG_QUERY )
			$ret['query'] = $q;

		return $ret;
	}

	/**
	 * Encodes data into internal format (before being json_encoded)
	 *
	 * Mostly converts known objects to associative arrays to be identified
	 * later in javascript
	 *
	 * @param $data array: array of data to be encoded
	 * @return array: The encoded version of data
	 */
	protected function _encodeData( $data ) {
		foreach( $data as &$col )
			foreach( $col as &$val ) {
				if( $val instanceof \DateTime ) {
					$arr = [
						'class' => get_class($val),
						'date' => $val->format('c'),
					];
					// Using (int) to work around some wird PHP 7.0 behavior
					if( $val instanceof \dophp\Time )
						$arr['repr'] = strftime('%H:%M:%S', (int)$val->getTimestamp());
					elseif( $val instanceof \dophp\Date )
						$arr['repr'] = strftime('%e %b %Y', (int)$val->getTimestamp());
					else
						$arr['repr'] = strftime('%e %b %Y %H:%M:%S', (int)$val->getTimestamp());
					$val = $arr;
				}

				if( $val instanceof \dophp\Decimal ) {
					$val = $val->toDouble();
				}
			}
		unset($col);
		unset($val);
		return $data;
	}

	/**
	 * Counts unfiltered results
	 *
	 * @TODO: Use a (mem)cache
	 */
	protected function _count() {
		list($query, $where, $params, $groupBy) = $this->_buildBaseQuery(true);
		if( $where )
			$query .= "\nWHERE " . implode(' AND ', $where);
		if( $groupBy )
			$query .= "\nGROUP BY $groupBy";
		return (int)$this->_db->run($query, $params)->fetch()['cnt'];
	}

	/**
	 * Returns the params structure
	 *
	 * @see \dophp\Validator
	 * @todo Use some caching
	 */
	public function getParamStructure(): array {
		$params = [
			/** See: https://datatables.net/manual/server-side */
			'draw' => ['int', []],
			'start' => ['int', ['min'=>0]],
			'length' => ['int', ['min'=>1]],
			'order' => ['array', [
				0 => [ [ 'array', [
					'column' => [ 'int', ['min'=>0, 'max'=>count($this->_cols)-1] ],
					'dir' => ['string', [] ],
				]]],
			]],
			'columns' => ['array', [] ],
			'filter' => ['array', [] ],
		];

		$idx = -1;
		foreach( $this->_cols as $c ) {
			$idx++;

			$params['columns'][1][$idx] = [ [ 'array', [
				'search' => [ [ 'array', [
					'value' => [ 'string', [] ],
					'regex' => [ 'bool', [] ],
				]]],
			]]];
		}

		foreach( $this->_sfilter as $f )
			$params['filter'][1][$f->getName()] = [ 'bool', ['required' => true] ];

		return $params;
	}

	/**
	 * Generate and return the ajax handler
	 */
	public function ajaxMethod(): \dophp\PageInterface {
		$method = new DataTableMethod($this->_config, $this->_db, $this->_page->user(), $this->_page->name(), $this->_page->path());
		$method->setTable($this);
		return $method;
	}

	public function getColClass($type=""){
		$class="";
		if((trim($type))!=""){
			if($type==self::CUSTOM_DATE_FILT){
				$class="ag-date-flt";
			}
		}
		return $class;
	}

	/**
	 * Returns month number from month name
	 */
	public function agStrToMonthNumb($month=""){

		$month_number = false;

		if((trim($month))!=""){

			$months_list = array(
				"gen" => 1,
				"feb" => 2,
				"mar" => 3,
				"apr" => 4,
				"mag" => 5,
				"giu" => 6,
				"lug" => 7,
				"ago" => 8,
				"set" => 9,
				"ott" => 10,
				"nov" => 11,
				"dic" => 12
			);
			$month = trim($month);
			if(array_key_exists(strtolower($month),$months_list)){
				$month = strtolower($month);
				$month = $months_list[$month]."";
				$month = str_pad($month,2,"0",STR_PAD_LEFT);
				$month_number = $month;
			}
		}

		return $month_number;
	}


	/**
	 * Returns month name from month number
	 */
	public function agMonthNumbToStr($month=""){

		$month_name = false;

		if((intval($month))>0){

			$months_list = array(
				1 => "gen",
				2 => "feb",
				3 => "mar",
				4 => "apr",
				5 => "mag",
				6 => "giu",
				7 => "lug",
				8 => "ago",
				9 => "set",
				10 => "ott",
				11 => "nov",
				12 => "dic"
			);
			if(array_key_exists(intval($month),$months_list)){
				$month_name = $months_list[intval($month)];
			}
		}

		return $month_name;
	}


	/**
	 * Returns data filter type by parsing the search string
	 */
	public function agGetDateType($string=""){

		$type=false;


		if((trim($string))!=""){

			switch(true){
				// 2018
				case(preg_match("/^[0-9]{4}$/",$string)):
					$type=self::DTYPE_LIST["y"];
				break;

				// specific month of this year
				// e.g.:
				// gen
				case(preg_match("/^[a-zA-Z]{3}$/",$string)):

					// check if inserted string is an existing month
					if($this->agStrToMonthNumb($string)){
						$type=self::DTYPE_LIST["m"];
					}
				break;

				// month of a given year
				// e.g.:
				// gen2018 gen 2018
				case(preg_match("/^[a-zA-Z]{3}[ ]*[0-9]{4}$/",$string)):

					// check if inserted string is an existing month
					if($this->agStrToMonthNumb(substr($string,0,3))){
						$type=self::DTYPE_LIST["my"];
					}

				break;

				// already sql friendly string
				// 2018-01
				case(preg_match("/^[0-9]{4}\-[0-9]{2}$/",$string)):
					$type=self::DTYPE_LIST["my_sql"];
				break;

				// specific day
				// e.g.:
				// 01/01/2018
				// 01-01-2018
				// 01.01.2018
				// 1/1/2018
				// 1-1-2018
				// 1.1.2018
				case(preg_match("/^[0-9]{1,2}\/[0-9]{1,2}\/[0-9]{4}$/",$string)):
				case(preg_match("/^[0-9]{1,2}\-[0-9]{1,2}\-[0-9]{4}$/",$string)):
				case(preg_match("/^[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{4}$/",$string)):
					$type=self::DTYPE_LIST["d"];
				break;


				// until_day case
				// e.g.:
				// 01/01/2018
				// 01-01-2018
				// 01.01.2018
				// 1/1/2018
				// 1-1-2018
				// 1.1.2018
				case(preg_match("/||^[0-9]{1,2}\/[0-9]{1,2}\/[0-9]{4}$/",$string)):
				case(preg_match("/||^[0-9]{1,2}\-[0-9]{1,2}\-[0-9]{4}$/",$string)):
				case(preg_match("/||^[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{4}$/",$string)):
					$type=self::DTYPE_LIST["ud"];
				break;
			}
		}

		return $type;
	}


	/**
	 * Returns the first element from a range of dates
	 */
	public function agGetFirstDateInRange($search=""){
		$el="";
		if((trim($search))!=""){
			$el= explode(self::DFILTER_DIVIDER,$search);
			$el= $el[0];
		}
		return $el;
	}


	/**
	 * Returns the where condition based on date filter type
	 */
	public function getDateFilter($string="",$type="",$range=null,$columnName=""){

		$search="";

		if(isset($columnName)&&(trim($columnName)!="")){

			$search=" FALSE ";

			if(!is_null($range)){
				if(!$range){
					switch($type){
						// 2018-01-01
						case self::DTYPE_LIST["d"]:
							$date = $this->formatDateIt2En($string);
							if($date){
								$search=$columnName.">='".$date."'";
							}
						break;
						// 2018-01-01
						case self::DTYPE_LIST["ud"]:
							$string= substr($string,-10);
							$date = $this->formatDateIt2En($string);
							if($date){
								$search=$columnName."<='".$date."'";
							}
						break;
						// gen
						case self::DTYPE_LIST["m"]:
							if($date=$this->makeYearMonthString(date("Y"),$string)){
								$search=$columnName.">='".$date."'";
							}
						break;
						// gen 2018
						// gen2018
						case self::DTYPE_LIST["my"]:
							$monthName = substr($string,0,3);
							$year = substr($string,-4);
							if($date=$this->makeYearMonthString($year,$monthName)){
								//$search=$columnName.">='".$date."'";
								$search= " YEAR(".$columnName.") = '".intval(substr($date,0,4))."' AND MONTH(".$columnName.") = '".intval(substr($date,5,7))."' ";
							}
						break;
						// 2018-01
						case self::DTYPE_LIST["my_sql"]:
							//$search=$columnName.">='".$string."'";
							$search= " YEAR(".$columnName.") = '".intval(substr($string,0,4))."' AND MONTH(".$columnName.") = '".intval(substr($string,5,7))."' ";
						break;

						// 2018
						case self::DTYPE_LIST["y"]:
							$search=" YEAR(".$columnName.") = '".$string."'";
						break;

						default:
							$search=" FALSE ";
						break;
					}
				}
				else{

					$items= explode(self::DFILTER_DIVIDER,$string);
					if( (strlen(trim($items[0]))!="")&&
						 (strlen(trim($items[1]))!="") ){
						switch($type){
							// 2018-01-01||2018-02-02
							case self::DTYPE_LIST["d"]:

								if(count($items)>=2){
									$start = $this->formatDateIt2En($items[0]);
									$end = $this->formatDateIt2En($items[1]);

									if($start&&$end){
										$search=" ( ".$columnName.">='".$start."' && ".$columnName."<='".$end."' ) ";
									}
								}

							break;
							// gen||feb
							case self::DTYPE_LIST["m"]:

								if(count($items)>=2){
									$start = $this->makeYearMonthString(date("Y"),$items[0]);
									$end = $this->makeYearMonthString(date("Y"),$items[1]);

									if($start&&$end){
										$search=" ( ".$columnName.">='".$start."' && ".$columnName."<='".$end."' ) ";
									}
								}
							break;
							// gen 2018||feb 2018
							// gen2018||feb2018
							case self::DTYPE_LIST["my"]:

								if(count($items)>=2){

									$startMonth = substr($items[0],0,3);
									$startYear = substr($items[0],-4);

									$endMonth = substr($items[1],0,3);
									$endYear = substr($items[1],-4);

									if($this->agStrToMonthNumb($startMonth)&&
										$this->agStrToMonthNumb($endMonth) ){
										$start = $this->makeYearMonthString($startYear,$startMonth);
										$end = $this->makeYearMonthString($endYear,$endMonth);

										if($start&&$end){

											$end=date("Y-m-t",strtotime($end));

											$search=" ( ".$columnName.">='".$start."' && ".$columnName."<='".$end."' ) ";
										}
									}
								}
							break;
							// 2018-01||2018-02
							case self::DTYPE_LIST["my_sql"]:

								if(count($items)>=2){

									$start = $items[0];
									$end = $items[1];

									$startTmp=explode("-",$start);
									$endTmp=explode("-",$end);

									if($start&&$end
										&&(count($startTmp)==2)
										&&(count($endTmp)==2)
										&&checkdate(intval($startTmp[1]),1,intval($startTmp[0]))
										&&checkdate((intval($endTmp[1])) ,1,intval($endTmp[0]))  ){

										$startSec=strtotime($start);
										$endSec=strtotime($end);

										if($startSec>$endSec){
											$endTmp=$end;
											$end=$start;
											$start=$endTmp;
										}

										$end=date("Y-m-t",strtotime($end));

										$search=" ( ".$columnName.">='".$start."' && ".$columnName."<='".$end."' ) ";
									}
								}

							break;

							// 2018||2019
							case self::DTYPE_LIST["y"]:

								if(count($items)>=2){

									$start = $items[1]."-01-01";
									$end = $items[0]."-12-31";

									$startSec=strtotime($start);
									$endSec=strtotime($end);

									if($startSec>$endSec){
										$endTmp=$end;
										$end=$start;
										$start=$endTmp;
									}

									if($start&&$end){
										$search=" ( ".$columnName.">='".$start."' && ".$columnName."<='".$end."' ) ";
									}
								}

							break;

							default:
								$search=" FALSE ";
							break;
						}
					}
					else{
						$search=" FALSE ";
					}
				}
			}
		}

		return $search;
	}


	/**
	 * Accept various it date format and
	 * returns the date in english format
	 */
	public function formatDateIt2En($string=""){

		$date=false;
		if((trim($string))!=""){
			if( preg_match("/^[0-9]{1,2}\/[0-9]{1,2}\/[0-9]{4}$/",$string) ||
				preg_match("/^[0-9]{1,2}\-[0-9]{1,2}\-[0-9]{4}$/",$string) ||
				preg_match("/^[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{4}$/",$string) ){

				$string = str_replace(array("/",".","."),"-",$string);
				$string = explode("-",$string);
				if(checkdate(intval($string[1]),intval($string[0]),intval($string[2]))){
					$date= $string[2]."-".$string[1]."-".$string[0];
				}

			}

		}

		return $date;
	}


	/**
	 * Return the first day of month in english
	 * format for the last 12 months
	 */
	public function getLast12MonthsDate(){

		$last12Month= array();

		$firstDayOfMonth = date("Y-m-01");
		$firstDayOfMonth_int = strtotime($firstDayOfMonth);

		for($m=0;$m<12;$m++){
		    $month = strtotime("-".$m." month",$firstDayOfMonth_int);
		    $last12Months[]= date("Y-m-d",$month);
		}
		return $last12Months;
	}


	/**
	 * Return a list of month-year string
	 */
	public function getMonthYearList(){

		$count=0;
		$count2=0;
		$list=array();

		for($currY=date("Y");$currY>1970&&$count<150;$currY--){

			$list[$currY]=array();
			for($m=12;$m>0&&$count2<1500;$m--){
				$list[$currY][$m]["number"]=str_pad((string)($m),2,"0",STR_PAD_LEFT);
				$list[$currY][$m]["name"]=$this->agMonthNumbToStr($m);

				$count2++;
			}
			$count++;
		}
		return $list;
	}


	/**
	 * Return a list of years string grouped by
	 * the number given from the step variable
	 */
	public function getYearList(){

		$count=0;
		$count2=0;
		$list=array();
		$step = 10;
		$currY = date("Y");


		// calculate the nearest year to the fixed step
		// rounded to the highest value
		// e.g. 2018 with step 10 ==> 2020
		$maxRange= $currY;
		$remainder = $currY%$step;
		if($remainder>0){
		    $missingToStep = $step-$remainder;
		    $maxRange=$currY+$missingToStep;
		}


		$yearMax = $maxRange;
		$yearMin = $yearMax - $step;
		$currBlockLabel="";
		// cycle for year block labels
		for($yearMax=$yearMax;$yearMax>1970&&$count<150;$yearMax=$yearMax-$step){

			// label to be shown as years_block title
			$currBlockLabel=$yearMax." - ".($yearMin+1);

			// label e.g. 2020-2011
			$list[$currBlockLabel]=array();

			// cycle for year calculation
			for($y=0;$y<$step&&$count2<1500;$y++){
				$list[$currBlockLabel][$y]=$currY;

				$count2++;

				// if current year is divisible by $step
				// go to the next block of years
				if(($currY%$step)==1){
					$currY--;
					break;
				}

				// decrease year of the list by one
				$currY--;
			}
			// set new year_labels range
			$yearMin= $yearMin - $step;

			$count++;

		}

		return $list;
	}


	/**
	 * Return false on error or a string in numeric year-month format
	 * e.g.:
	 * 2018-01
	 */
	public function makeYearMonthString($year=0,$monthName=""){

		$date= false;
		if((intval($year)>0)&&(trim($monthName)!="")){

			// if string is a known month return a
			// a string with given year and month number
			// e.g.:
			// 2018-01
			if($this->agStrToMonthNumb($monthName)){
				$monthNumb=$this->agStrToMonthNumb($monthName);
				$date=$year."-".$monthNumb;
			}

		}
		return $date;

	}



}


/**
 * Defines a column to be displayed in an "admin" table
 */
class DataTableBaseColumn {

	/** The column's unique ID */
	public $id;
	/** Whether this column can be used in filtering */
	public $filter = true;
	/** Whether this column can be used for sorting */
	public $sort = true;
	/** Whether this column is visible */
	public $visible = true;

	/**
	 * Creates the column definition
	 *
	 * @param $id string: This column's unique id
	 */
	public function __construct(string $id) {
		$this->id = $id;
	}

}


/**
 * A standard DataTable columns that carries data
 */
class DataTableColumn extends DataTableBaseColumn {

	/** The user-friendly description */
	public $descr;
	/** The name of the column inside the query */
	public $qname;
	/** The column explicit type, if given */
	public $type;
	/** Whether this column is part of the PK */
	public $pk = false;

	/**
	 * Creates the column definition
	 *
	 * @param $id string: This column's unique id
	 * @param $opt array: Associative array of options, possible keys:
	 *             - descr: User-friendly description, will use ID with first
	 *                      uppercase letter by default
	 *             - qname: Name to be used iside DB queries, by default use the
	 *                      quoted version of the ID. If provided, shoudl contain
	 *                      quotes
	 *             - type:  Explicit data type, one of \dophp\Table::DATA_TYPE_*
	 *                      constants. May be omitted.
	 *             - visible: Default visibility, boolean.
	 *             - pk:    Tells if this column is part of the PK,
	 *                      default: false
	 */
	public function __construct(string $id, array $opt) {
		parent::__construct($id);
		$this->descr = isset($opt['descr']) ? $opt['descr'] : str_replace('_',' ',ucfirst($this->id));
		$this->qname = isset($opt['qname']) ? $opt['qname'] : \Dophp::db()->quoteObj($this->id);
		$this->type = isset($opt['type']) ? $opt['type'] : null;
		if( isset($opt['visible']) )
			$this->visible = (bool)$opt['visible'];
		if( isset($opt['pk']) )
			$this->pk = (bool)$opt['pk'];
	}

}


/**
 * Defines a button in an admin table
 */
class DataTableButton {

	const PARAM_START = '{{';
	const PARAM_END = '}}';

	/** The button's unique id */
	protected $_id;
	/** The containing table */
	protected $_table;
	/** The button's label */
	public $label;
	/** The buttons' partial url */
	public $url;
	/** The buttons' icon */
	public $icon;
	/** The button url's params */
	protected $_params;

	/**
	 * Creates the button oci_fetch_object
	 *
	 * @param $table DataTable: The containing table
	 * @param $id string: The unique button's ID
	 * @param $opt array of options, associative
	 *        - label string: The button's description
	 *        - url string; The button's URL (see geturl())
	 *        - icon string: The button's icon name
	 * @param $params array of replaceable url params, associative, some are
	 *        include dby default:
	 *        - base: base url for the page
	 */
	public function __construct(DataTable $table, string $id, array $opt = [], array $params = []) {
		$this->_table = $table;
		$this->_id = $id;

		foreach( $opt as $k => $v ) {
			if( ! property_exists($this, $k) )
				throw new Exception("Invalid property $k");
			$this->$k = $v;
		}

		$this->_params = $params;
		$this->_params['base'] = $this->_table->getPage()->getBase();
	}

	/**
	 * Returns the parsed url
	 *
	 * Allowed tokens are:
	 * - {{base}}: replaces with name of current table
	 */
	public function getUrl(): string {
		$searches = [];
		$replaces = [];
		foreach( $this->_params as $name => $val ) {
			$searches[] = self::PARAM_START . $name . self::PARAM_END;
			$replaces[] = $val;
		}
		return str_replace($searches, $replaces, $this->url);
	}
}


/**
 * Like ad admin button, but relative to a single row
 */
class DataTableRowButton extends DataTableButton {

}