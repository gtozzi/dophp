<?php declare(strict_types=1);


namespace dophp\buttons;

require_once 'Widgets.php';


/**
 * Defines the button bar, a button collection
 */
class ButtonBar {

	protected $_buttons = [];

	/**
	 * Appends a button to this bar
	 */
	public function add(Button $btn) {
		if (array_key_exists($btn->id, $this->_buttons))
			throw new \InvalidArgumentException('Duplicate button ' . $btn->id);

		$this->_buttons[$btn->id] = $btn;
	}

	/**
	 * Insert a button or an array of buttons at the beginning of this bar
	 *
	 * @param $btns Button/array: a single button or an array of Button
	 */
	public function insert($btns) {
		if ($btns instanceof Button)
			$btns = [ $btns ];
		elseif (is_array($btns)) {
			// Nothing
		} else
			throw new \InvalidArgumentException('Invalid $btns type');

		$barr = [];
		foreach ($btns as $b) {
			if (array_key_exists($b->id, $this->_buttons))
				throw new \InvalidArgumentException('Duplicate button ' . $b->id);
			$barr[$b->id] = $b;
		}

		$this->_buttons = $barr + $this->_buttons;
	}

	/**
	 * Removes a button from bar
	 *
	 * @param $id string: ID of the button to be deleted
	 * @param $ignoreMissing bool: If true, do not trigger error when missing
	 * @return True if button has been found and removed
	 */
	public function del(string $id, bool $ignoreMissing=true) {
		if( ! array_key_exists($id, $this->_buttons) ) {
			if( $ignoreMissing )
				return false;
			throw new \Exception("Trying to remove missing button \"$id\"");
		}

		unset( $this->_buttons[$id] );
		return true;
	}

	/**
	 * Returns buttons array
	 */
	public function buttons(): array {
		return $this->_buttons;
	}
	/**
	 * Returns a signle button by ID
	 */
	public function button(string $id): Button {
		return $this->_buttons[$id];
	}
	/**
	 * Enables the given button by ID
	 */
	public function enable(string $id) {
		$this->_buttons[$id]->enable();
	}
	/**
	 * Disables the given button by ID
	 */
	public function disable(string $id) {
		$this->_buttons[$id]->disable();
	}

	/**
	 * Shows the given button by ID
	 */
	public function show(string $id) {
		$this->_buttons[$id]->show();
	}
	/**
	 * Hides the given button by ID
	 */
	public function hide(string $id) {
		$this->_buttons[$id]->hide();
	}

}


/**
 * Defines a button for a ButtonBar
 */
abstract class Button extends \dophp\widgets\BaseWidget {

	const DEFAULT_TYPE = 'button';
	const DEFAULT_CLASS = 'btn-secondary';
	/** True if this is a dropdown button */
	const DROPDOWN = false;

	public $id;
	public $type;
	public $class;
	public $confirm = '';
	public $icon;
	public $label;
	public $enabled = false;
	public $hidden = false;

	/**
	 * Constructs the button
	 *
	 * @param $id string: Unique button ID
	 * @param $label string: Button description for user
	 * @param $icon string: FA-icon
	 * @param $options array: array of optional options:
	 *        - type: string button type, default 'button'
	 *        - class: string button class, default 'btn-secondary'
	 *        - confirm: A string to ask confirmation before proceeding
	 */
	public function __construct(string $id, string $label, string $icon, array $options=[]) {
		assert(! isset($options['class']) || is_string($options['class']));
		assert(! isset($options['type']) || is_string($options['type']));

		parent::__construct();

		$this->id = $id;
		$this->label = $label;
		$this->icon = $icon;
		$this->type = $options['type'] ?? self::DEFAULT_TYPE;
		$this->class = $options['class'] ?? self::DEFAULT_CLASS;
	}

	public function enable() {
		$this->enabled = true;
	}
	public function disable() {
		$this->enabled = false;
	}

	public function hide() {
		$this->hidden = true;
	}
	public function show() {
		$this->hidden = false;
	}

	/**
	 * Returns true if this button is a dropdown
	 */
	public function isDropdown(): bool {
		return static::DROPDOWN;
	}

	/**
	 * Returns button's PHP class name, all lowercase
	 */
	public function phpclass(): string {
		$pts = explode('\\', strtolower(get_class($this)));
		return end($pts);
	}

	/**
	 * Returns html data- attributes
	 */
	public function htmldata(): array {
		return [
			'confirm' => $this->confirm,
		];
	}
}


/**
 * A button containing dropdown links
 */
class DropdownButton extends Button {

	const DROPDOWN = true;

	protected $_childs = [];


	/**
	 * Appends a child link to this button
	 */
	public function add(DropdownButtonChild $child) {
		if (array_key_exists($child->id, $this->_childs))
			throw new \InvalidArgumentException('Duplicate child ' . $child->id);

		$this->_childs[$child->id] = $child;
	}

	/**
	 * Returns childs array
	 */
	public function childs(): array {
		return $this->_childs;
	}
}


/**
 * A child for a dropdown button
 */
class DropdownButtonChild extends \dophp\widgets\BaseWidget {

	public $id;
	public $label;
	public $icon = null;
	public $url = null;
	public $post = null;

	/**
	 * Constructs the child
	 *
	 * @param $id string: Unique button ID
	 * @param $label string: Button description for user
	 * @param $icon string: FA-icon
	 * @param $url string: The link URL
	 * @param $post array: If given, will be a POST button
	 */
	public function __construct(string $id, string $label, string $icon=null, string $url=null, $post=null) {
		parent::__construct();

		$this->id = $id;
		$this->label = $label;
		$this->icon = $icon;
		$this->url = $url;
		$this->post = $post;
	}

}


/**
 * The sandard submit button
 */
class SaveButton extends Button {
	const DEFAULT_ID = 'save';

	public function __construct(string $id=self::DEFAULT_ID, string $label='Salva',
			string $icon='fa-floppy-o', array $options=['type'=>'submit']) {
		parent::__construct($id, $label, $icon, $options);
	}
}

/**
 * The standard form reset button
 */
class CancelButton extends Button {
	const DEFAULT_ID = 'cancel';

	public function __construct(string $id=self::DEFAULT_ID, string $label='Annulla',
			string $icon='fa-undo', array $options=[]) {
		parent::__construct($id, $label, $icon, $options);
	}
}

/**
 * The standard record delete button
 */
class DeleteButton extends Button {
	const DEFAULT_ID = 'delete';

	public function __construct(string $id=self::DEFAULT_ID, string $label='Elimina',
			string $icon='fa-trash', array $options=[]) {

		if( ! isset($options['class']) )
			$options['class'] = 'btn-danger';

		parent::__construct($id, $label, $icon, $options);
	}
}

/**
 * A button to open a link
 */
class LinkButton extends Button {

	public $url;
	public $newtab;

	/**
	 * @param $url string: The url to send the user to
	 * @param $options array: Array of options:
	 *        - newtab: If true, opens the url in new tab
	 */
	public function __construct(string $id, string $label, string $icon, string $url, array $options=[]) {
		parent::__construct($id, $label, $icon, $options);

		$this->url = $url;
		$this->newtab = isset($options['newtab']) ? (bool)$options['newtab'] : false;
	}

	public function htmldata(): array {
		$data = parent::htmldata();

		$data['url'] = $this->url;
		$data['newtab'] = $this->newtab ? '1' : '';

		return $data;
	}
}


/**
 * A button to open a link and POST to it
 */
class PostButton extends LinkButton {

	/** POST data array */
	public $post;

	/**
	 * @param $url string: The url to send the user to
	 * @param $post array: Post data array
	 * @param $options array: Array of options:
	 *        - newtab: If true, opens the url in new tab
	 */
	public function __construct(string $id, string $label, string $icon, string $url, array $post=[], array $options=[]) {
		parent::__construct($id, $label, $icon, $url, $options);

		$this->post = $post;
	}

	public function htmldata(): array {
		$data = parent::htmldata();

		$data['post'] = json_encode($this->post);

		return $data;
	}
}


/**
 * A button handled by custom javascript in template code
 */
class JsButton extends Button {
}
