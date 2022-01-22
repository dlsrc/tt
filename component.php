<?php
/*******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    abstract class dl\tt\Component

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\markup;

abstract class Composite implements \dl\DirectCallable {
	public const NS = '.';

	protected string $_name;   // Snippet name
	protected string $_class;  // Snippet class
	protected array  $_child;  // Children list
	protected int    $_size;   // Children list size
	protected string $_before; // Wrapper text before result string
	protected string $_after;  // Wrapper text after result string
	protected string $_result; // Component result string

	public static function emulate(): Emulator {
		return new Emulator([
			'_stack'   => [],
			'_ref'     => [],
			'_child'   => [],
			'_class'   => 'EMULATOR',
			'_name'    => 'EMULATOR',
			'_before'  => '',
			'_after'   => '',
			'_result'  => '',
			'_size'    => 0,
		]);
	}

	public function __construct(array $state) {
		$this->_name   = $state['_name'];
		$this->_class  = $state['_class'];
		$this->_child  = $state['_child'];
		$this->_size   = $state['_size'];
		$this->_before = $state['_before'];
		$this->_after  = $state['_after'];
		$this->_result = '';
	}

	public function __clone(): void {
		foreach (\array_keys($this->_child) as $name) {
			$this->_child[$name] = clone $this->_child[$name];
		}
	}

	public function getName(): string {
		return $this->_name;
	}

	public function getClass(): string {
		return $this->_class;
	}

	public function isClass(string $class): bool {
		if ($this->_class == $class) {
			return true;
		}

		return false;
	}

	public function isComponent(string $name): bool {
		if (isset($this->_child[$name])) {
			return true;
		}

		if (\str_contains($name, self::NS)) {
			$branch = \explode(self::NS, $name);
			$com = $this;

			foreach ($branch as $n) {
				if (!$com->isComponent($n)) {
					return false;
				}

				$com = $com->{$n};
			}

			return true;

		}

		return false;
	}

	public function getChild(string $class): Composite|null {
		foreach ($this->_child as $child) {
			if ($child->isClass($class)) {
				return $child;
			}
		}

		return null;
	}

	public function getChildName(string $class): string|null {
		foreach ($this->_child as $name => $child) {
			if ($child->isClass($class)) {
				return $name;
			}
		}

		return null;
	}

	public function getChildNames(string $class): array {
		$names = [];

		foreach ($this->_child as $name => $child) {
			if ($child->isClass($class)) {
				$names[] = $name;
			}
		}

		return $names;
	}

	public function update(): void {
		$this->_result = '';
	}

	public function getResult(): string {
		if ('' != $this->_result) {
			return $this->_before.$this->_result.$this->_after;
		}

		return '';
	}

	public function getRawResult(): string {
		return $this->_result;
	}

	public function isReady(): bool {
		if ('' == $this->_result) {
			return false;
		}

		return true;
	}

	public function insert(string $text): void {
		$this->_result.= $text;
	}

	public function unwrap(): void {
		$this->_before  = '';
		$this->_after = '';
	}

	public function drop(): void {
		foreach ($this->_child as $child) {
			$child->drop();
		}

		$this->update();
	}
}

class Component extends Composite {
	protected array $_ref;
	protected array $_stack;

	public function __construct(array $state) {
		parent::__construct($state);

		$this->_ref   = $state['_ref'];
		$this->_stack = $state['_stack'];

		foreach ($this->_ref as $k => $v) {
			$this->_stack[$k] =&$this->_stack[$v];
		}
	}

	public function __call(string $name, array $value): bool {
		if (!isset($this->_child[$name])) {
			return false;
		}

		if (isset($value[0])) {
			foreach ($value[0] as $key => $val) {
				$this->_child[$name]->$key = $val;
			}

			$this->_child[$name]->ready();
		}

		return true;
	}

	public function __get(string $name): Composite {
		if (isset($this->_child[$name])) {
			return $this->_child[$name];
		}

		return Composite::emulate();
	}

	public function __isset(string $name): bool {
		return isset($this->_child[$name]);
	}

	public function __unset(string $name): void {
		if (isset($this->_child[$name])) {
			$this->_stack[Composite::NS.$name] = $this->_child[$name]->getResult();
			unset($this->_child[$name]);
		}
	}

	public function __set(string $name, int|float|string $value): void {
		if (isset($this->_stack[$name])) {
			$this->_stack[$name] = $value;
		}
	}

	public function ready(): void {
		$this->notify();
		$this->_result.= \implode('', $this->_stack);
	}

	public function attach(Composite $component): void {
		$name = $component->getName();
		$this->_child[$name] = $component;
		$this->_size++;
	}

	public function notify(): void {
		foreach ($this->_child as $ob) {
			$name = Composite::NS.$ob->getName();
			$this->_stack[$name] = $ob->getResult();
			$ob->update();
		}
	}

	public function common(string $name, int|float|string $value): void {
		if (isset($this->_stack[$name])) {
			$this->_stack[$name] = $value;
		}

		foreach ($this->_child as $child) {
			$child->common($name, $value);
		}
	}
}

class Drive extends Component {
	protected bool $_exert;

	public function __construct(array $state) {
		parent::__construct($state);
		$this->_exert = $state['_exert'];
	}

	public function ready(): void {
		$this->_exert = true;
	}

	public function isReady(): bool {
		foreach ($this->_child as $ob) {
			if ($ob->isReady()) return true;
		}

		return false;
	}

	public function getResult(): string {
		if ($result = $this->getRawResult()) {
			$this->_result = '';
			return $this->_before.$result.$this->_after;
		}

		return '';
	}

	public function getRawResult(): string {
		if ($this->_exert) {
			$this->_exert = false;

			if ($this->_size) {
				$this->notify();
			}

			$this->_result = \implode('', $this->_stack);
		}

		return $this->_result;
	}

	public function insert(string $text): void {
		$this->_result = $text;
		$this->_exert = false;
	}
}

final class Document extends Component implements \Stringable {
	protected array  $_general;
	protected string $_first;
	protected string $_last;

	public function __construct(array $state) {
		parent::__construct($state);
		$this->_general = $state['_general'];
		$this->_first   = $state['_first'];
		$this->_last    = $state['_last'];
	}

	public function __set(string $name, int|float|string $value): void {
		if (\is_array($value)) {
			foreach ($value as $key => $val) {
				$this->__set($name.Composite::NS.$key, $val);
			}
		}
		elseif (isset($this->_general[$this->_first.$name.$this->_last])) {
			$this->_general[$this->_first.$name.$this->_last] = $value;
		}
		else {
			parent::__set($name, $value);
		}
	}

	public function add(string $name, int|float|string $value=''): void {
		$this->_general[$this->_first.$name.$this->_last] = $value;
	}

	public function ready(): void {
		if ('' == $this->_result) {
			$this->notify();
			$this->_result = \implode('', $this->_stack);
			
			if (!empty($this->_general)) {
				$this->_result = \str_replace(
					\array_keys($this->_general),
					$this->_general,
					$this->_result
				);
			}
		}
	}

	public function getResult(): string {
		$this->ready();
		return $this->_result;
	}

	public function getRawResult(): string {
		return $this->getResult();
	}

	public function insert(string $text): void {
		return;
	}

	public function __toString(): string {
		$this->ready();
		return $this->_result;
	}

	public function force(string $name, string $text): bool {
		if (!isset($this->_child[$name])) {
			return false;
		}

		$this->_child[$name]->insert($text);
		return true;
	}
}

class Variator extends Composite {
	protected string $_variant;

	public function __construct(array $state) {
		parent::__construct($state);
		$this->_variant = $state['_variant'];
	}

	public function __call(string $name, array $value): bool {
		if (isset($this->_child[$name])) {
			$this->_variant = $name;

			if (isset($value[0])) {
				foreach ($value[0] as $key => $val) {
					$this->_child[$name]->$key = $val;
				}

				$this->ready();
			}

			return true;
		}

		return false;
	}

	public function __get(string $name): Composite {
		if (isset($this->_child[$name])) {
			$this->_variant = $name;
			return $this->_child[$name];
		}

		return Composite::emulate();
	}

	public function __isset(string $name): bool {
		if (isset($this->_child[$name])) {
			$this->_variant = $name;
			return true;
		}

		return false;
	}

	public function __unset(string $name): void {
		unset($this->_child[$name]);
	}

	public function __set(string $name, int|float|string $value): void {
		$this->_child[$this->_variant]->$name = $value;
	}

	public function attach(Composite $component): void {
		$name = $component->getName();
		$this->_child[$name] = $component;

		if ('' == $this->_variant) {
			$this->_variant = $name;
		}

		$this->_size++;
	}

	public function ready(): void {
		$this->_child[$this->_variant]->ready();
		$this->_result.= $this->_child[$this->_variant]->getResult();
		$this->_child[$this->_variant]->update();
	}

	public function common(string $name, int|float|string $value): void {
		foreach ($this->_child as $child) {
			$child->common($name, $value);
		}
	}
}

class ComponentM extends Component {
	public function __set(string $name, int|float|string $value): void {
		if (\is_array($value)) {
			foreach ($value as $key => $val) {
				$this->__set($name.Composite::NS.$key, $val);
			}
		}
		elseif (isset($this->_stack[$name])) {
			$this->_stack[$name] = $value;
		}
	}
}

class DriveM extends Drive {
	public function __set(string $name, int|float|string $value): void {
		if (\is_array($value)) {
			foreach ($value as $key => $val) {
				$this->__set($name.Composite::NS.$key, $val);
			}
		}
		elseif (isset($this->_stack[$name])) {
			$this->_stack[$name] = $value;
		}
	}
}

final class Emulator extends Component implements \Stringable {
	public function __call(string $name, array $value): bool {
		return true;
	}

	public function __get(string $name): Emulator {
		return $this;
	}

	public function __set(string $name, int|float|string $value): void {
		return;
	}

	public function __toString(): string {
		return '';
	}
}
