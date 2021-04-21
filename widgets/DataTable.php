<?php declare(strict_types=1);

/**
 * @file DataTable.php
 * @brief Datatable-related widgets
 * @author Gabriele Tozzi <gabriele@tozzi.eu>
 */


namespace dophp\widgets;

require_once(__DIR__ . '/../Page.php');
require_once(__DIR__ . '/../DateFilter.php');


/**
 * Datatable base interface
 */
interface DataTableInterface {

	/**
	 * Returns the table HTML structure
	 */
	public function getHTMLStructure(): string;

	/**
	 * Parses the request data and returns raw data
	 *
	 * @param $pars: array of parameters, associative
	 * @param $save: if true, save search filter and order
	 * @param $useSaved: boolean, if true, will use saved params as defaults
	 * @see https://datatables.net/manual/server-side
	 * @return \dophp\Result Query result object
	 */
	public function getRawData( $pars=[], $save=false, $useSaved=false ): array;

	/**
	 * Parses the request data and returns result
	 *
	 * @param $pars: array of parameters, associative
	 * @param $save: boolean; if true, will save requested data
	 * @param $useSaved: boolean, if true, will use saved params as defaults
	 * @see https://datatables.net/manual/server-side
	 * @see self::_encodeData
	 */
	public function getData( array $pars=[], bool $save=true, bool $useSaved=false ): array;

}

/**
 * Base class for a datatable, inspired to the datatable javascript library
 */
abstract class BaseDataTable extends BaseWidget implements DataTableInterface {
	use \dophp\SmartyFunctionalities;

	/** Special data key for buttons */
	const BTN_KEY = '~btns~';

	/** Special data key for identifying totals row */
	const TOT_KEY = '~istotals~';

	// Special datatable consts, see: https://datatables.net/manual/server-side
	const DT_ROWID_KEY = 'DT_RowId';
	const DT_ROWCLASS_KEY = 'DT_RowClass';
	const DT_ROWDATA_KEY = 'DT_RowData';
	const DT_ROWATTR_KEY = 'DT_RowAttr';

	/** asc keyword for order */
	const ORDER_ASC = 'asc';
	/** desc keyword for order */
	const ORDER_DESC = 'desc';

	/** Divider for date range in date filter **/
	const DFILTER_DIVIDER = ",";

	/** Available data types list **/
	const DTYPE_LIST = array(
		"d" => "d",
		"m" => "m",
		"y" => "y",
		"my" => "my",
		"m.y" => "m.y",
		"ud" => "ud"
	);

	const MEMCACHE_KEY_BASE = 'DoPhp::DataTable::';

	/** Associative array of fixed query params and button params */
	public $params = [];

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

	/** The default order [ id, ORDER_ASC|ORDER_DESC ] or null */
	protected $_defaultOrder = null;

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

	/** Should add a totals row? */
	public $addTotals = false;

	/**
	 * Provides the query for the cache freshness check default implementation
	 * must be defined in child and return only a row and col
	 *
	 * Example: SELECT MAX(last_updated) FROM table
	 *
	 * @see self::_getCountCacheFreshnessCheckVal()
	 */
	protected $_countCacheFreshQuery = null;

	/**
	 * Enables the caching of _count results
	 *
	 * @see self::_count()
	 */
	protected $_enableCountCache = false;

	/** Local preferences cache variable */
	protected $_prefsCache = null;

	/**
	 * Inits some props at runtime, overridable in child
	 */
	protected function _initProps() {
	}

	/**
	 * Get field types from the DBMS and set the field type for the Date type
	 */
	protected function _fillMissingColumnTypeInfo() {
		$types = null;

		foreach( $this->_cols as $col )
			if( ! isset($col->type) ) {
				// Only retrieve types when needed
				if( $types === null )
					$types = $this->_getColumnTypeInfo();

				$col->type = $types[$col->id];
			}
	}

	/**
	 * Returns a fairly unique table identified based on class an column name hashes
	 *
	 * The identifier changes when the final class name changes or when the
	 * column name changes
	 *
	 * @return string
	 */
	public function getFairlyUniqueIdentifier(): string {
		$cls = $this->getClsId();
		$colHash = sha1(serialize(array_keys($this->_cols)));
		return "{$cls}_{$colHash}";
	}

	/**
	 * Given a result, returns column type info
	 */
	protected function _extractColumnTypesFromRes(\dophp\Result $res): array {
		$i = 0;
		$types = [];
		foreach( $this->_cols as $col ) {
			$info = $res->getColumnInfo($i);
			$types[$col->id] = $info->type;

			$i++;
		}
		return $types;
	}

	/**
	 * Retrieve column type info from cache or live
	 *
	 * @return array associative [ id -> type ]
	 */
	abstract protected function _getColumnTypeInfo(): array;

	/**
	 * Constructs the table object
	 *
	 * @param $page PageInterface: the parent page
	 * @param $params array: Default parameters (see $this->params)
	 */
	public function __construct(\dophp\PageInterface $page, array $params = null) {
		parent::__construct();

		// Sets the default template
		$this->_template = 'widgets/dataTable.tpl';

		$this->_page = $page;
		$this->_db = $page->db();
		$this->_config = $page->config();
		$this->_user = $page->user();

		if( isset($params) )
			$this->params = $params;

		$this->_initProps();

		// Deprecation checks
		if( isset($this->_pk) )
			throw new \LogicException('Deprecated PK specification');
		if( isset($this->_cntquery) )
			throw new \LogicException('Deprecated CntQuery specification');

		// Checks for data validity
		if( ! isset($this->_cols) || ! is_array($this->_cols) )
			throw new \LogicException('Missing or invalid Cols definition');

		// Builds the super filter
		foreach( $this->_sfilter as $name => &$field ) {
			$val = isset($field['default']) && $field['default'];
			$opts = [];
			if( isset($field['label']) )
				$opts['label'] = $field['label'];

			if( ! isset($field['filter']) )
				throw new \LogicException("Missing filter query in filter field \"$name\"");
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
				throw new \LogicException('Invalid column definition: ' . gettype($col));
		}
		unset($col);

		// Early retrieve column type info for the filter to be built correctly
		// only needed if at least one column has filter enabled and missing type
		foreach( $this->_cols as $col )
			if( $col->filter && ! isset($col->type) ) {
				// Trigger retrieve for all columns, then break
				$this->_fillMissingColumnTypeInfo();
				break;
			}

		// Makes sure PK is valid
		$this->_getPkName();

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

		// Retrieve default search filter and apply it
		foreach( $this->_getSavedSearchFilter() as $colid => $search ) {
			$this->_cols[$colid]->search = $search;
			$this->_cols[$colid]->regex = false;
		}
	}

	/**
	 * Gets default order
	 *
	 * @param $asIdx bool: If true, returns colidx instead of colid
	 * @return [ colid, ascdesc ] or [ colidx, ascdesc ]
	 */
	protected function _getDefaultOrder(bool $asIdx = false): array {
		if( $this->_defaultOrder ) {
			list($cid, $ord) = $this->_defaultOrder;
			$c = $this->_cols[$cid];
			return [ $asIdx ? $this->colIdToIdx($c->id) : $c->id, $ord ];
		}

		foreach( $this->_cols as $c )
			if( $c->visible )
				return [ $asIdx ? $this->colIdToIdx($c->id) : $c->id, static::ORDER_ASC ];

		throw new \RuntimeException('No visible column');
	}

	/**
	 * Validate and return Datatable preferences from config
	 *
	 * @return array
	 */
	protected function _getConfigPrefs(): array {
		if( ! isset($this->_config['datatable']['prefs']) )
			return [];

		$prefs = $this->_config['datatable']['prefs'];
		if( ! isset($prefs['table']) )
			return [];
		if( ! isset($prefs['uidcol']) )
			return [];
		if( ! isset($prefs['sortcol']) )
			return [];
		if( ! isset($prefs['ordcol']) )
			return [];
		if( ! isset($prefs['sfcol']) )
			return [];

		return $prefs;
	}

	/**
	 * Retrieve saved preferences from DB
	 *
	 * @return array or null
	 */
	protected function _getSavedPrefs(): ?array {
		if( $this->_prefsCache !== null )
			return $this->_prefsCache;

		$prefs = $this->_getConfigPrefs();
		if( ! $prefs ) {
			error_log('Not reading datatable prefs. See $config[\'datatable\'][\'prefs\']');
			return null;
		}

		$q = "
			SELECT
				" . $this->_db->quoteObj($prefs['sortcol']) . " AS sortcol,
				" . $this->_db->quoteObj($prefs['ordcol']) . " AS sortord,
				" . $this->_db->quoteObj($prefs['sfcol']) . " AS sf
			FROM " . $this->_db->quoteObj($prefs['table']) . "
			WHERE " . $this->_db->quoteObj($prefs['uidcol']) . " = ? AND " . $this->_db->quoteObj($prefs['tablecol']) . " = ?
		";
		$p = [ $this->_user->getUid(), $this->getClsId() ];
		$t = [ 'sf' => \dophp\Table::DATA_TYPE_STRING ];
		$res = $this->_db->xrun($q, $p, $t)->fetch();
		$this->_prefsCache = $res ? $res : null;

		return $this->_prefsCache;
	}

	/**
	 * Retrieve saved order from DB
	 *
	 * @param $asIdx bool: If true, returns colidx instead of colid
	 * @return [ colid, ascdesc ] or [ colidx, ascdesc ] or null if not found
	 */
	protected function _getSavedOrder(bool $asIdx = false): ?array {
		$r = $this->_getSavedPrefs();
		if( ! $r || ! $r['sortcol'] || ! $r['sortord'] )
			return null;

		if( $r['sortord'] != static::ORDER_ASC && $r['sortord'] != static::ORDER_DESC )
			return null;

		if( ! array_key_exists($r['sortcol'], $this->_cols) )
			return null;

		return [ $asIdx ? $this->colIdToIdx($r['sortcol']) : $r['sortcol'], $r['sortord'] ];
	}

	/**
	 * Retrieve saved filter from DB
	 *
	 * @param $asIdx bool: If true, returns colidx instead of colid
	 * @return array associative [ colid => search ] or [ colidx => search ]
	 */
	protected function _getSavedSearchFilter(bool $asIdx = false): array {
		$r = $this->_getSavedPrefs();
		if( ! $r || ! $r['sf'] )
			return [];

		$decoded = json_decode( $r['sf'], true );
		if( $decoded === null ) {
			error_log("Error decoding JSON preferences: {$r['sf']}");
			return [];
		}

		if( ! is_array($decoded) ) {
			error_log("Malformatted JSON preferences: {$r['sf']}");
			return [];
		}

		// Ignore removed or invalid columns
		$filter = [];
		foreach( $decoded as $colid => $search )
			if( array_key_exists($colid, $this->_cols) && is_string($search) && strlen($search) )
				$filter[ $asIdx ? $this->colIdToIdx($colid) : $colid ] = $search;

		return $filter;
	}

	/**
	 * Save given order
	 *
	 * @param $colId string: sort column id
	 * @param $ascDesc string: asc or desc
	 */
	protected function _saveOrder(string $colId, string $ascDesc) {
		$prefs = $this->_getConfigPrefs();
		if( ! $prefs ) {
			error_log('Not saving datatable order. See $config[\'datatable\'][\'prefs\']');
			return;
		}

		$this->_db->insertOrUpdate($prefs['table'], [
			$prefs['uidcol'] => $this->_user->getUid(),
			$prefs['tablecol'] => $this->getClsId(),
			$prefs['sortcol'] => $colId,
			$prefs['ordcol'] => $ascDesc,
		], [$prefs['tablecol'], $prefs['uidcol']]);

		// Invalidate cache
		$this->_prefsCache = null;
	}

	/**
	 * Save given search filter
	 *
	 * @param $filter array associative array [ colid => search ]
	 */
	protected function _saveSearchFilter(array $filter) {
		$prefs = $this->_getConfigPrefs();
		if( ! $prefs ) {
			error_log('Not saving search filter. See $config[\'datatable\'][\'prefs\']');
			return;
		}

		$this->_db->insertOrUpdate($prefs['table'], [
			$prefs['uidcol'] => $this->_user->getUid(),
			$prefs['tablecol'] => $this->getClsId(),
			$prefs['sfcol'] => json_encode($filter, JSON_FORCE_OBJECT),
		], [$prefs['tablecol'], $prefs['uidcol']]);

		// Invalidate cache
		$this->_prefsCache = null;
	}

	/**
	 * Returns col ID from idx
	 *
	 * @param int: $idx column index, 1-based (0 is the button column)
	 * @return string: Column's unique ID
	 */
	public function colIdxToId( int $idx ): string {
		if( $idx < 1 )
			throw new \UnexpectedValueException('Min idx is 1');
		$idx--;

		$ids = array_keys($this->_cols);
		if( ! array_key_exists($idx, $ids) )
			throw new \UnexpectedValueException("IDX $idx out of range");
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
			throw new \UnexpectedValueException("ID $id not found");

		return $idx + 1;
	}

	/**
	 * Sets the super filter from array
	 *
	 * @param $params array: Associative array of field->status, unknown keys
	 *                         are ignored
	 */
	public function setSFilter(array $params) {
		foreach( $this->_sfilter as $field )
			$field->setInternalValue( isset($params[$field->getName()]) && $params[$field->getName()] );
	}

	/**
	 * Sets the initial params (filter/search) from $_GET array
	 *
	 * @param $params array of parameters, associative
	 * @see https://datatables.net/manual/server-side
	 */
	public function setGParams(array $params) {
		if( isset($params['columns']) && is_array($params['columns']) ) {
			foreach( $params['columns'] as $col => $def ) {
				if( ! array_key_exists($col, $this->_cols) )
					continue;

				if( is_array($def) && isset($def['search']) && is_array($def['search']) && $def['search']['value'] ) {
					$this->_cols[$col]->search = $def['search']['value'];
					$this->_cols[$col]->regex = isset($def['search']['regex']) ? (bool)$def['search']['regex'] : false;
				}
			}
		}
	}

	/**
	 * Returns the containing page
	 */
	public function getPage(): \dophp\PageInterface {
		return $this->_page;
	}

	/*
	 * Returns default JS options
	 */
	protected function _getHtmlInitOptions(): array {
		$lang = \DoPhp::lang();
		$langc = $lang->getCountryCode($lang->getCurrentLanguage());

		$options = [
			'processing' => true,
			'serverSide' => true,
			//scrollCollapse: true,
			// Can't set to false or search will be ignored
			//bFilter:        false,
			//stateSave:      true,
			'dom' => 'lrtip<"dtbl-buttons-container">',

			// Scroller extension
			'scroller'    => true,
			'deferRender' => true,
			'scrollY'     => 'calc( 100vh - 300px )',
			'scrollX'     => true,
			'autoWidth'   => true,

			'language' => [
			],

			'ordering' => true,
			//colReorder: true,

			'autoWidth' => true,
		];

		if( $langc == 'it' )
			$options['language']['url'] = "{$this->_config['dophp']['url']}/webcontent/DataTables/Italian.json";

		return $options;
	}

	public function getHTMLStructure(): string {
		// Sets this prop for smarty compatibility
		$this->_name = $this->_page->name();
		$this->_initSmarty();

		$this->_ajaxURL = \dophp\Url::getToStr($_GET);

		$this->_smarty->assign('id', $this->_id);
		$this->_smarty->assign('cols', $this->_cols);
		$this->_smarty->assign('order', $this->_getSavedOrder(true) ?? $this->_getDefaultOrder(true));
		$this->_smarty->assign('initOpts', $this->_getHtmlInitOptions());

		$this->_smarty->assign("getColClass",$this->getColClass());
		$this->_smarty->assign("monthYearList",$this->getMonthYearList());
		$this->_smarty->assign("yearList",$this->getYearList());
		$this->_smarty->assign("dFilterDivider",static::DFILTER_DIVIDER);

		$this->_smarty->assign('btns', $this->_btns);
		$this->_smarty->assign('rbtns', $this->_rbtns);
		$this->_smarty->assign('btnKey', static::BTN_KEY);
		$this->_smarty->assign('totKey', static::TOT_KEY);
		$this->_smarty->assign('action', '?'.\DoPhp::BASE_KEY."={$this->_name}");
		$this->_smarty->assign('sfilter', $this->_sfilter);
		$this->_smarty->assign('ajaxURL', $this->_ajaxURL);
		$this->_smarty->assign('selectable', $this->selectable);
		$this->_smarty->assign('addTotals', $this->addTotals);

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
				throw new \LogicException('K and ID mismatch');
			if( $c->pk )
				$pk[] = $c->id;
		}

		if( count($pk) < 1 )
			throw new \LogicException('PK is not defined');
		if( count($pk) > 2 )
			throw new \dophp\NotImplementedException('Composite PK is not supported');

		return $pk[0];
	}

	/**
	 * Returns column explicit types, when given
	 *
	 * @return array [ id => type ], only for explicit columns
	 */
	protected function _getColumnExplicitTypes() : array {
		$types = [];

		foreach( $this->_cols as $k => $c )
			if( isset($c->type) )
				$types[$k] = $c->type;

		return $types;
	}

	/**
	 * Retrieves the raw data, internal
	 *
	 * @param $filter DataTableSearchFilter
	 * @param $order DatatableDataOrder
	 * @param $limit DatatableDataLimit
	 * @return \dophp\Result
	 */
	abstract protected function _getRawDataInternal( DataTableDataFilter $filter=null, DatatableDataOrder $order=null, DatatableDataLimit $limit=null ): array;

	public function getRawData( $pars=[], $save=false, $useSaved=false ): array {
		// Parses the super filter
		foreach( $this->_sfilter as $field )
			if( isset($pars['filter'][$field->getName()]) )
				$field->setInternalValue( (bool)$pars['filter'][$field->getName()] );

		// Calculate filter clause
		$filter = [];
		$filterArgs = [];
		$saveFilter = [];
		$idx = -1;
		foreach( $this->_cols as $c ) {
			$idx++;

			// Use given search value but fall back to column's default
			if( isset($pars['columns'][$idx]['search']) )
				$search = isset($pars['columns'][$idx]['search']['value']) ? trim($pars['columns'][$idx]['search']['value']) : '';
			elseif( $useSaved && isset($c->search) )
				$search = $c->search;
			else
				continue;

			if( ! strlen($search) )
				continue;

			$saveFilter[$c->id] = $search;

			// checks if filter is a date filter and calculate where clause
			if($search == '-') {
				$filter[] = "{$c->qname} IS NULL";
			} elseif($c->type == \dophp\Table::DATA_TYPE_DATE){
				$dateFilter = new \dophp\DateFilter($search, self::DFILTER_DIVIDER);
				list($sql, $params) = $dateFilter->getSqlSearchFilter($c->qname, ":f{$idx}_");
				$filter[] = $sql;
				$filterArgs = array_merge($filterArgs, $params);
			} elseif($c->type == \dophp\Table::DATA_TYPE_BOOLEAN) {
				$filter[] = ( $search ? '' : 'NOT ' ) . $c->qname;
			} else {
				$filter[] = "{$c->qname} LIKE :f$idx";
				$filterArgs[":f$idx"] = "%$search%";
			}
		}
		$filter = new DataTableDataFilter($filter, $filterArgs);
		// Save the search filter
		if( $save )
			$this->_saveSearchFilter($saveFilter);


		// Calculate order, if given
		$order = null;
		if( isset($pars['order']) && isset($pars['order'][0]) && $pars['order'][0] ) {
			$order = $pars['order'][0];
			$orderc = (int)$order['column']; // Indexes start at 1 because column 0 is the buttons column
			if( $orderc < 1 )
				$orderc = 1;

			$colId = $this->colIdxToId($orderc);
			$ord = strtolower($order['dir'])==static::ORDER_DESC ? static::ORDER_DESC : static::ORDER_ASC;

			$order = new DataTableDataOrder($colId, $ord);
			// Saves the new order preference
			if( $save )
				$this->_saveOrder($colId, $ord);
		}

		// Calculate limit, if given
		$limit = null;
		if( isset($pars['length']) && $pars['length'] > 0 ) {
			$limit = new DatatableDataLimit($pars['length'], $pars['start']);
		}

		return $this->_getRawDataInternal($filter, $order, $limit);
	}

	public function getData( array $pars=[], bool $save=true, bool $useSaved=false ): array {
		// Retrieve data
		$data = $this->getRawData($pars, $save, $useSaved);

		if( $this->addTotals )
			$totals = new DataTableTotalsUtil($this->_cols);

		// Add buttons / calculate totals
		foreach( $data as &$d ) {
			$d[static::BTN_KEY] = [];

			foreach( $this->_rbtns as $k => $btn )
				if( $btn->showInRow($d) )
					$d[static::BTN_KEY][] = $k;

			if( $this->addTotals )
				$totals->addRow($d);
		}
		unset($d);

		// Add totals row
		if( isset($totals) ) {
			$totrow = $totals->get();
			$totrow[DataTable::TOT_KEY] = true;
			$data[] = $totrow;
		}

		// Add found rows if available
		if( $this->_calcFound )
			$found = $this->_db->foundRows();
		$count = $this->_count();

		// Final processing
		$data = $this->_encodeData($data);
		$data = $this->_processLinks($data);

		// Process links
		$ret = [
			'draw' => $pars['draw'] ?? 0,
			'recordsTotal' => $count,
			'recordsFiltered' => $this->_calcFound ? $found : $count,
			'data' => $data,
		];

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
	 * Process links into data
	 */
	protected function _processLinks( $data ) {
		foreach( $this->_cols as $k => $descr ) {
			if( ! $descr->link )
				continue;

			foreach( $data as &$row ) {
				if( is_callable($descr->link) ) {
					$value = $row[$k] instanceof DataTableCell ? $row[$k]->value : $row[$k];
					$href = $descr->link($value, $row);
				} else {
					$href = $descr->link;
					foreach( $this->_cols as $kk => $dd ) {
						$vv = $row[$kk] instanceof DataTableCell ? $row[$kk]->value : $row[$kk];
						$href = str_replace('{'.$kk.'}', $vv, $href);
					}
				}

				if( $href ) {
					if( $row[$k] instanceof DataTableCell )
						$row[$k]->href = $href;
					else
						$row[$k] = new DataTableCell($row[$k], null, null, $href);
				}
			}
			unset($row);
		}
		return $data;
	}

	/**
	 * Parses the request data and returns result
	 *
	 * @param $pars: array of parameters, associative
	 * @see self::getData
	 * @return \PhpOffice\PhpSpreadsheet\Spreadsheet: A spreadsheet
	 */
	public function getXlsxData( array $pars=[] ): \PhpOffice\PhpSpreadsheet\Spreadsheet {
		$heads = [];
		foreach($this->_cols as $k => $c)
			$heads[] = $c->descr;

		$data = [];
		$colCount = null;
		foreach($this->getRawData($pars, false, true) as $datarow ) {
			$row = [];

			if( $colCount === null )
				$cc = 0;
			foreach( $datarow as $k => $v ) {
				if( $k[0] == '~' )
					continue;

				if( $colCount === null )
					$cc++;

				if( $v instanceof DataTableCell )
					$row[] = $v->value;
				else
					$row[] = $v;
			}
			if( $colCount === null )
				$colCount = $cc;

			if( count($data) % 1000 == 0 ) {
				// Increase memory limit every 1.000 rows
				// Adds ~1.1K per cell to the memory limit
				// Upgrade memory_limit: PhpSpreadsheet uses about ~1k RAM per cell
				// https://phpspreadsheet.readthedocs.io/en/latest/topics/memory_saving/
				$memory_limit = \dophp\Utils::getMemoryLimitMb();
				$memory_limit += 0.0011 * $colCount * 1000;
				$memory_limit = (int)ceil($memory_limit);
				ini_set('memory_limit', "{$memory_limit}M");

				// Also resets the execution timer
				set_time_limit(30);
			}

			$data[] = $row;
		}

		return \dophp\Spreadsheet::fromArray($data, [ $heads ]);
	}

	/**
	 * Counts unfiltered results, uses memcache if possible
	 */
	abstract protected function _count(): int;

	/**
	 * Returns a value to be used to check if _count cache is still fresh
	 *
	 * @see self::_count
	 * @see self::_countCacheFreshQuery
	 */
	protected function _getCountCacheFreshnessCheckVal(): string {
		if( ! $this->_countCacheFreshQuery )
			throw new \LogicException('Cache fresh query is not defined');

		$r = $this->_db->run($this->_countCacheFreshQuery)->fetch();
		return (string)array_shift($r);
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
			'length' => ['int', ['min'=>-1]], // -1 = all
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
		$method = new \dophp\DataTableMethod($this->_config, $this->_db, $this->_page->user(), $this->_page->name(), $this->_page->path());
		$method->setTable($this);
		return $method;
	}

	public function getColClass($type=""){
		$class="";
		if((trim($type))!=""){
			if($type == \dophp\Table::DATA_TYPE_DATE){
				$class="ag-date-flt";
			}
		}
		return $class;
	}

	/**
	 * Returns a list of month-year string for date filter picker (used in the UI)
	 *
	 * @return associative array (ie. [ 2020 => [ 1 => [ 'number' => '…', 'name' => '…' ], … ], … ])
	 */
	public function getMonthYearList(): array {
		$list = [];

		for( $currY = (int)date("Y"); $currY > 1970; $currY-- ) {
			$list[$currY] = [];

			for( $m = 12; $m > 0; $m-- ) {
				$mpadded = str_pad((string)($m),2,"0",STR_PAD_LEFT);
				$list[$currY][$m]["number"] = $mpadded;
				$list[$currY][$m]["name"] = \dophp\Utils::formatDateTimeLocale(new \DateTime("15-$mpadded-$currY"), '%h');
			}
		}

		return $list;
	}


	/**
	 * Return a list of years string grouped by decade (used in the UI)
	 *
	 * @return associative array ( ie. [ '2019-2010' => [ 2020 => '2020', … ], … ])
	 */
	public function getYearList(): array {
		$list = [];

		foreach( $this->getMonthYearList() as $year => $v ) {
			$decade = (int)floor($year / 10);
			$decadeStr = "{$decade}9 - {$decade}0";

			if( ! array_key_exists($decadeStr, $list) )
				$list[$decadeStr] = [];

			$list[$decadeStr][$year] = (string)$year;
		}

		return $list;
	}

}


/**
 * Defines a column to be displayed in an "admin" table
 */
abstract class DataTableBaseColumn {

	/** The column's unique ID */
	public $id;
	/** Whether this column can be used in filtering */
	public $filter = true;
	/** Whether this column can be used for sorting */
	public $sort = true;
	/** Whether this column is visible */
	public $visible = true;
	/** Current search value */
	public $search = null;
	/** Is search regex? */
	public $regex = false;

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

	const FORMAT_STRING = 'string';
	const FORMAT_BOOLEAN = 'boolean';
	const FORMAT_NUMBER = 'number';
	const FORMAT_OBJECT = 'object';

	const FORMAT_CURRENCY = 'currency';


	/** The user-friendly description */
	public $descr;
	/** The user-friendly extended explanation */
	public $tooltip = null;
	/** The name of the column inside the query */
	public $qname;
	/** The column explicit data type, if given */
	public $type = null;
	/** The column explicit display format type, if given */
	public $format = null;
	/** Whether this column is part of the PK */
	public $pk = false;
	/** Whether to allow filtering on this column */
	public $filter = true;
	/** Column link template or callback */
	public $link = null;

	/**
	 * Creates the column definition
	 *
	 * @param $id string: This column's unique id
	 * @param $opt array: Associative array of options, possible keys:
	 *             - descr: User-friendly description, will use ID with first
	 *                      uppercase letter by default
	 *             - tooltip: User-friendly extended tooltip description, optional
	 *             - qname: Name to be used iside DB queries, by default use the
	 *                      quoted version of the ID. If provided, shoudl contain
	 *                      quotes
	 *             - type:  Explicit data type, one of \dophp\Table::DATA_TYPE_*
	 *                      constants. May be omitted.
	 *             - format: Explicit display format, one of self::FORMAT_* consts
	 *             - visible: Default visibility, boolean.
	 *             - pk:    Tells if this column is part of the PK,
	 *                      default: false
	 *             - filter: enable/disable filtering on this column
	 *             - link:  An url to add as href, may be a string
	 *                      ("{columname}" occurrencies will be replaced) or a
	 *                      callback($value, $row)
	 */
	public function __construct(string $id, array $opt) {
		parent::__construct($id);
		$this->descr = isset($opt['descr']) ? $opt['descr'] : str_replace('_',' ',ucfirst($this->id));
		$this->qname = isset($opt['qname']) ? $opt['qname'] : \Dophp::db()->quoteObj($this->id);
		if( isset($opt['type']) && $opt['type'] )
			$this->type = $opt['type'];
		if( isset($opt['format']) && $opt['format'] )
			$this->format = $opt['format'];
		if( isset($opt['visible']) )
			$this->visible = (bool)$opt['visible'];
		if( isset($opt['pk']) )
			$this->pk = (bool)$opt['pk'];
		if( isset($opt['filter']) )
			$this->filter = (bool)$opt['filter'];
		if( isset($opt['tooltip']) && $opt['tooltip'] )
			$this->tooltip = $opt['tooltip'];
		if( isset($opt['link']) && $opt['link'] )
			$this->link = $opt['link'];
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
	/** The button's partial url */
	public $url;
	/** The button's POST data array, also, sets the button as POST is not null */
	public $post = null;
	/** The button's icon */
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
	 *        - url string: The button's URL (see geturl())
	 *        - post array: Post data array, sets the button as POST if not null
	 *                      params are replaced
	 *        - icon string: The button's icon name
	 * @param $params array of replaceable url params, associative, some are
	 *        included by default:
	 *        - base: base url for the page
	 */
	public function __construct(DataTableInterface $table, string $id, array $opt = [], array $params = []) {
		$this->_table = $table;
		$this->_id = $id;

		foreach( $opt as $k => $v ) {
			if( ! property_exists($this, $k) )
				throw new \UnexpectedValueException("Invalid property $k");
			$this->$k = $v;
		}

		$this->_params = $params;

		//TODO: this should probably implemented with an interface or injected
		//      at runtime by a parent class
		if( method_exists($this->_table->getPage(), 'getBase') )
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
		foreach( array_merge($this->_table->params, $this->_params) as $name => $val ) {
			$searches[] = static::PARAM_START . $name . static::PARAM_END;
			// TODO: Proper conversion to machine format?
			$replaces[] = \dophp\Utils::format($val);
		}
		return str_replace($searches, $replaces, $this->url);
	}

	/**
	 * Returns true if button is post
	 */
	public function isPost(): bool {
		return $this->post !== null;
	}

	/**
	 * Returns the parsed post data
	 */
	public function getPost(): array {
		$ret = is_array($this->post) ? $this->post : [];

		foreach( $ret as &$v )
			foreach( $this->_params as $name => $val )
				if( $v === static::PARAM_START . $name . static::PARAM_END ) {
					$v = $val;
					break;
				}
		unset($v);

		return $ret;
	}
}


/**
 * Like ad admin button, but relative to a single row
 */
class DataTableRowButton extends DataTableButton {

	/** Whether to show the button, usually a callable */
	public $show = true;

	/**
	 * Creates the button object
	 *
	 * @see DataTableButton
	 * @param $opt array of options, like DataTableButton, extra options:
	 *        - show mixed: bool or callable($row), tells if the button should be shown
	 */
	public function __construct(DataTableInterface $table, string $id, array $opt = [], array $params = []) {
		parent::__construct($table, $id, $opt, $params);
	}

	/**
	 * Tells whether the button should be shown in row
	 */
	public function showInRow(array $row): bool {
		if( is_callable($this->show) )
			return ($this->show)($row);

		return (bool)$this->show;
	}
}


/**
 * Advanced data container
 */
class DataTableCell implements \JsonSerializable {

	public $value;
	public $repr = null;
	public $class = null;
	public $href = null;

	/**
	 * Constructs the advanced value
	 *
	 * @param $value mixed: The real value
	 * @param $repr string: The string representation (optional)
	 * @param $class string: The data class (optional)
	 * @param $href string: The url to link to (optional)
	 */
	public function __construct($value, string $repr = null, string $class = null, string $href = null) {
		$this->value = $value;
		$this->repr = $repr;
		$this->class = $class;
		$this->href = $href;
	}

	public function jsonSerialize() {
		if( $this->repr === null && $this->class === null && $this->href === null )
			return $this->value;

		$ret = [ 'value' => $this->value ];
		if( $this->repr !== null )
			$ret['repr'] = $this->repr;
		if( $this->class !== null )
			$ret['class'] = $this->class;
		if( $this->href !== null )
			$ret['href'] = $this->href;

		return $ret;
	}

}


/**
 * A simple datatable search filter, used inside the class
 */
class DataTableDataFilter {

	protected $_filter;
	protected $_args;

	/**
	 * Construct the filter
	 *
	 * @param $filter array: Array of SQL AND conditions
	 * @param $args array: Associative array of arguments to bound
	 */
	public function __construct(array $filter = [], array $args = []) {
		$this->_filter = $filter;
		$this->_args = $args;
	}

	public function getFilter(): array {
		return $this->_filter;
	}

	public function getArgs(): array {
		return $this->_args;
	}

	public function empty(): bool {
		return ! $this->_filter;
	}
}


/**
 * The datatable order
 */
class DatatableDataOrder {

	/** The column id */
	protected $_colId;

	/** The ORDER_ASC|ORDER_DESC */
	protected $_ascDesc;

	public function __construct(string $colId, string $ascDesc) {
		$this->_colId = $colId;
		$this->_ascDesc = $ascDesc;
	}

	/**
	 * Returns the filter as array
	 *
	 * @return array [ colid, ORDER_ASC|ORDER_DESC ]
	 */
	public function get(): array {
		return [ $this->_colId, $this->_ascDesc ];
	}

	public function getColId(): string {
		return $this->_colId;
	}

	public function getAscDesc(): string {
		return $this->_ascDesc;
	}
}


/**
 * Utility class for calculating totals
 */
class DataTableTotalsUtil {

	/**
	 * The totals row array
	 */
	protected $_totals = [];

	public function __construct(array $cols) {
		foreach( $cols as $k => $c )
			$this->_totals[$k] = null;
	}

	public function addRow(array $row) {
		foreach( $row as $k => $v )
			if( is_int($v) || is_float($v) )
				$this->_totals[$k] += $v;
	}

	public function get(): array {
		return array_merge($this->_totals, [
			DataTable::DT_ROWCLASS_KEY => 'totals',
		]);
	}

	/**
	 * Returns true when all totals are zero
	 */
	public function allZero(): bool {
		foreach( $this->_totals as $v )
			if( $v )
				return false;

		return true;
	}
}

/**
 * The datatable limit
 */
class DatatableDataLimit {

	protected $_start;
	protected $_length;

	public function __construct(int $length, int $start=0) {
		$this->_start = $start;
		$this->_length = $length;
	}

	/**
	 * Returns the limit as array
	 *
	 * @return array [ start, length ]
	 */
	public function get(): array {
		return [ $this->_start, $this->_length ];
	}

	public function getStart(): int {
		return $this->_start;
	}

	public function getLength(): int {
		return $this->_length;
	}
}


/**
 * A data table, uses the datatable javascript library
 *
 * This is the original data table, builds the query dynamically
 */
class DataTable extends BaseDataTable {

	/** Expire time for _count cache entries */
	const COUNT_CACHE_EXPIRE = 60 * 60;

	/** Expire time for column type cache entries */
	const COLTYPES_CACHE_EXPIRE = 60 * 60;

	/**
	 * The FROM part of the query to be executed to get data for the table,
	 * without the "FROM" keyword, must be defined in child
	 */
	protected $_from = null;

	/** Fixed where clause, if any, without "WHERE" keyword */
	protected $_where = null;

	/** Fixed group by, if any, without "GROUP BY" keyword */
	protected $_groupBy = null;

	/** Should calculate found rows when filtering results? May disable this for performance */
	protected $_calcFound = true;

	/** Fast count query, used for performance on huge tables, must return a 'cnt' column */
	protected $_countQuery = null;

	/** Params array for fast count query */
	protected $_countQueryParams = [];

	/**
	 * Constructs the table object
	 *
	 * @param $page PageInterface: the parent page
	 */
	public function __construct(\dophp\PageInterface $page) {
		// Checks for data validity
		if( isset($this->_query) )
			throw new \LogicException('Deprecated Query specification');
		if( ! isset($this->_from) || ! is_string($this->_from) )
			throw new \LogicException('Missing or invalid From definition');

		parent::__construct($page);
	}

	/**
	 * Returns the base query to be executed, with the super filter and
	 * the access filter, no limit, no order
	 *
	 * @param $cnt boolean: if true, build a query for the COUNT(*)
	 * @param $calcFound boolean: if true, add SQL_CALC_FOUND_ROWS
	 * @todo Caching
	 * @return [ $query, $where and array, $params array, $groupBy condition (may be null) ]
	 */
	protected function _buildBaseQuery($cnt=false, $calcFound=true) {
		if( $cnt || ! $calcFound || $this->_db->type() != $this->_db::TYPE_MYSQL )
			$q = "SELECT\n";
		else
			$q = "SELECT SQL_CALC_FOUND_ROWS\n";

		$cols = [];
		foreach( $this->_cols as $c )
			if( $c->qname )
				$cols[] = "\t{$c->qname} AS " . $this->_db->quoteObj($c->id);
			else
				$cols[] = "\t" . $this->_db->quoteObj($c->id);
		$q .= implode(",\n", $cols) . "\n";

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
				throw new \LogicException('Invalid access filter');
			list($awhere, $apars) = $afilter;
			if( ! is_string($awhere) || ! is_array($apars) )
				throw new \LogicException('Invalid access filter');

			$where[] = '( ' . $awhere . ' )';
			$pars = array_merge($pars, $apars);
		}

		return [ $q, $where, $pars, $this->_groupBy ];
	}

	protected function _getRawDataInternal( DataTableDataFilter $filter=null, DatatableDataOrder $order=null, DatatableDataLimit $limit=null ): array {
		// Base query
		list($q, $where, $p, $groupBy) = $this->_buildBaseQuery(false, $this->_calcFound);
		$having = [];

		// Calculate filter having clause
		if( $filter && ! $filter->empty() ) {
			$having[] = '( ' . implode(' AND ', $filter->getFilter()) . ' )';
			$p = array_merge($p, $filter->getArgs());
		}

		// Apply where clause
		if( $where )
			$q .= "\nWHERE " . implode(' AND ', $where);

		// Apply Group By
		if( $groupBy )
			$q .= "\nGROUP BY $groupBy";

		// Apply having clause (in MS SQL, use where if no group)
		if( $having )
			if( ($this->_db->type() == $this->_db::TYPE_MSSQL || $this->_db->type() == $this->_db::TYPE_PGSQL) && ! $groupBy )
				$q .= ($where ? "\nAND" : "\nWHERE") . ' (' . implode(' AND ', $having) . ')';
			else
				$q .= "\nHAVING " . implode(' AND ', $having);

		// Apply order, if given
		if( $order ) {
			$q .= "\nORDER BY " . $order->getColId() . ' ';
			$ord = strtolower($order->getAscDesc())==static::ORDER_DESC ? static::ORDER_DESC : static::ORDER_ASC;
			$q .= strtoupper($ord);
		}

		// Filter by limit, if given
		if( $limit ) {
			if( $this->_db->type() == $this->_db::TYPE_MSSQL )
				$q .= "\nOFFSET ". ( $limit->getStart() ) . ' ROWS FETCH NEXT ' . $limit->getLength().' ROWS ONLY';
			else if( $this->_db->type() == $this->_db::TYPE_PGSQL )
				$q .= "\nOFFSET " . ( $limit->getStart() ) . ' LIMIT ' . $limit->getLength();
			else
				$q .= "\nLIMIT " . ( $limit->getStart() ) . ',' . $limit->getLength();
		}

		return $this->_db->xrun($q, $p, $this->_getColumnExplicitTypes())->fetchAll();
	}

	/**
	 * Retrieve column type info from cache or live
	 *
	 * @return array associative [ id -> type ]
	 */
	protected function _getColumnTypeInfo(): array {
		$cache = \DoPhp::cache();
		$cacheKey = static::MEMCACHE_KEY_BASE . $this->getFairlyUniqueIdentifier() . "::colTypes";

		// Try cache first
		if( $cache ) {
			$info = $cache->get($cacheKey);
			if( $info )
				return $info;
		}

		// Cache failed, go retrieve it
		list($q, $where, $p, $groupBy) = $this->_buildBaseQuery(false, false);
		if( $groupBy )
			$q .= "\nGROUP BY $groupBy";
		if( $this->_db->type() == $this->_db::TYPE_MSSQL)
			$q = preg_replace( '/^SELECT/', 'SELECT TOP 0', $q, 1 );
		else
			$q .= "\n LIMIT 0 \n";

		$res = $this->_db->xrun($q);
		$types = $this->_extractColumnTypesFromRes($res);

		if( $cache )
			$cache->set($cacheKey, $types, 0, static::COLTYPES_CACHE_EXPIRE);

		return $types;
	}

	/**
	 * Counts unfiltered results, uses memcache if possible
	 */
	protected function _count(): int {
		if( $this->_countQuery ) {
			$query = $this->_countQuery;
			$params = $this->_countQueryParams;
		} else {
			list($query, $where, $params, $groupBy) = $this->_buildBaseQuery(true, false);
			if( $where )
				$query .= "\nWHERE " . implode(' AND ', $where);
			if( $groupBy )
				$query .= "\nGROUP BY $groupBy";

			$query = "SELECT COUNT(*) AS cnt FROM ( $query ) AS q";
		}

		// Try to use the cache
		if( $this->_enableCountCache ) {
			$cache = \DoPhp::cache();
			$ccfcv = $this->_getCountCacheFreshnessCheckVal();
			$ch = sha1(sha1($ccfcv) . sha1($query) . sha1(serialize($params)));
			$cacheKey = static::MEMCACHE_KEY_BASE . 'count::' . $ch;
		}

		if( $this->_enableCountCache && $cache ) {
			$cnt = $cache->get($cacheKey);
			if( $cnt !== false && is_int($cnt) )
				return $cnt;
		}

		// No hit in cache, run the query
		$cnt = (int)$this->_db->run($query, $params)->fetch()['cnt'];

		// Try to save the new value in cache
		if( $this->_enableCountCache && $cache )
			$cache->set($cacheKey, $cnt, 0, static::COUNT_CACHE_EXPIRE);

		return $cnt;
	}

	public static function _getGroupConcat($column, $db): string {
		if ($db->type() == $db::TYPE_PGSQL)
			return "STRING_AGG($column, \', \')";
		else
			return "GROUP_CONCAT($column SEPARATOR \', \')";
	}
}


/**
 * A data table that uses a single static query and caches is
 */
class StaticCachedQueryDataTable extends BaseDataTable {

	public $addTotals = true;

	/**
	 * The full query. Returned cols must match col name
	 */
	protected $_query = null;

	/**
	 * How long the query can be cached, in seconds
	 */
	protected $_queryCacheExpireSecs = 60 * 5;

	/**
	 * Local variable used for basic caching
	 */
	private $__content = null;

	/**
	 * Constructs the table object
	 *
	 * @param $page PageInterface: the parent page
	 */
	public function __construct(\dophp\PageInterface $page) {
		// Checks for data validity
		if( ! isset($this->_query) || ! is_string($this->_query) )
			throw new \LogicException('Missing or invalid Query definition');

		// Filters are not supported, so disable all of them
		foreach( $this->_cols as &$c )
			$c['filter'] = false;
		unset($c);

		parent::__construct($page);
	}

	public function getFairlyUniqueIdentifier(): string {
		$cls = $this->getClsId();
		$hash = sha1(serialize([array_keys($this->_cols), $this->_query, $this->params]));
		return "{$cls}_{$hash}";
	}

	/**
	 * Internal function to run the query, may be overridden in child to run muliple queries
	 *
	 * @return array [ [data], [types] ]
	 */
	protected function _runQuery(): array {
		$res = $this->_db->xrun($this->_query, $this->params, $this->_getColumnExplicitTypes());
		$types = $this->_extractColumnTypesFromRes($res);
		$data = $res->fetchAll();

		return [ $data, $types ];
	}

	/**
	 * Runs the query and updates the cache (if needed)
	 *
	 * @return array [ 'data': the query result, 'foundRows': total count of found rows, 'colTypes': column types data ]
	 */
	protected function _retrieveContent() {
		// Try local cache first
		if( $this->__content )
			return $this->__content;

		$cache = \DoPhp::cache();
		$cacheKey = static::MEMCACHE_KEY_BASE . $this->getFairlyUniqueIdentifier() . "::content";

		// Then try cache
		if( $cache ) {
			$this->__content = $cache->get($cacheKey);
			if( $this->__content )
				return $this->__content;
		}

		// TODO: access filter
		list($data, $types) = $this->_runQuery();

		// No hit in cache, run the query
		$count = count($data);

		$this->__content = [
			'data' => $data,
			'foundRows' => $count,
			'colTypes' => $types,
		];

		if( $cache )
			$cache->set($cacheKey, $this->__content, 0, $this->_queryCacheExpireSecs);

		return $this->__content;
	}

	protected function _getRawDataInternal( DataTableDataFilter $filter=null, DatatableDataOrder $order=null, DatatableDataLimit $limit=null ): array {
		//TODO: support filter, order, limit
		return $this->_retrieveContent()['data'];
	}

	/**
	 * Retrieve column type info from cache or live
	 *
	 * @return array associative [ id -> type ]
	 */
	protected function _getColumnTypeInfo(): array {
		return $this->_retrieveContent()['colTypes'];
	}

	/**
	 * Counts unfiltered results, uses memcache if possible
	 */
	protected function _count(): int {
		return $this->_retrieveContent()['foundRows'];
	}

	protected function _getHtmlInitOptions(): array {
		$opt = parent::_getHtmlInitOptions();

		$opt['scroller'] = false;
		$opt['deferRender'] = false;
		unset($opt['scrollY']);
		unset($opt['scrollX']);

		$opt['ordering'] = false;
		$opt['paging'] = false;
		$opt['info'] = false;

		return $opt;
	}

}
