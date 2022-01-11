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
namespace dl\tt;

trait Parentness {
	final public function __clone(): void {
		foreach (\array_keys($this->_child) as $name) {
			$this->_child[$name] = clone $this->_child[$name];
		}
	}

	final public function attach(Component $c): void {
		$name = $c->getName();
		$this->_child[$name] = $c;
	}

	final public function drop(): void {
		foreach ($this->_child as $child) {
			$child->drop();
		}

		$this->update();
	}

	final public function isComponent(string $name): bool {
		if (isset($this->_child[$name])) {
			return true;
		}

		if (\str_contains($name, Component::NS)) {
			$branch = \explode(Component::NS, $name);
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

	final public function getChild(string $class): Component {
		foreach ($this->_child as $child) {
			if ($child->isClass($class)) {
				return $child;
			}
		}

		return Component::emulate();
	}

	final public function getChildName(string $class): string|null {
		foreach ($this->_child as $name => $child) {
			if ($child->isClass($class)) {
				return $name;
			}
		}

		return null;
	}

	final public function getChildNames(string $class): array {
		$names = [];

		foreach ($this->_child as $name => $child) {
			if ($child->isClass($class)) {
				$names[] = $name;
			}
		}

		return $names;
	}
}

trait Childless {
	final public function attach(Component $c): void {}

	final public function drop(): void {
		$this->_result = '';
	}

	final public function isComponent(string $name): bool {
		return false;
	}

	final public function getChild(string $class): Component {
		return Component::emulate();
	}

	final public function getChildName(string $class): string|null {
		return null;
	}

	final public function getChildNames(string $class): array {
		return [];
	}
}

trait CompositeGetter {
	public function __get(string $name): Component {
		if (isset($this->_child[$name])) {
			return $this->_child[$name];
		}

		Component::error('e_no_child', Code::Component);
		return Component::emulate();
	}
}

trait LeafGetter {
	public function __get(string $name): Component {
		Component::error('e_no_child', Code::Component);
		return Component::emulate();
	}

}

trait VariantGetter {
	public function __get(string $name): Component {
		if (isset($this->_child[$name])) {
			$this->_variant = $name;
			return $this->_child[$name];
		}

		Component::error('e_no_child', Code::Component);
		return Component::emulate();
	}

}

trait Result {
	public function getResult(): string {
		return $this->_result;
	}
}

trait Wrapper {
	protected string $_before;
	protected string $_after;

	public function __construct(array $state) {
		parent::__construct($state);
		$this->_before = $state['_before'];
		$this->_after  = $state['_after'];
	}
/*
	public function getResult(): string {
		if ('' != $this->_result) {
			return $this->_before.$this->_result.$this->_after;
		}

		return '';
	}
*/
	public function unwrap(): void {
		$this->_before  = '';
		$this->_after = '';
	}
}

abstract class Component implements \dl\DirectCallable {
	final public const NS = '.';

	abstract public function attach(Component $c): void;
	abstract public function drop(): void;
	abstract public function isComponent(string $name): bool;
	abstract public function getChild(string $class): Component;
	abstract public function getChildName(string $class): string|null;
	abstract public function getChildNames(string $class): array;

	protected string $_name;   // Snippet name
	protected string $_class;  // Snippet class
	protected string $_result; // Component result string

	public function __construct(array $state) {
		$this->_name   = $state['_name'];
		$this->_class  = $state['_class'];
		$this->_result = '';
	}

	public function getName(): string {
		return $this->_name;
	}

	public function getClass(): string {
		return $this->_class;
	}

	public function isClass(string $class): bool {
		return $this->_class == $class;
	}

	public function update(): void {
		$this->_result = '';
	}

	public static function emulate(): Emulator {
		return new Emulator([
			'_class'   => 'EMULATOR',
			'_name'    => 'EMULATOR',
			'_result'  => '',
		]);
	}

	protected static function error(string $info, Code $code): void {
		if (!Mode::Product->current()) {
			if (Mode::Develop->current()) {
				throw new Failure(\dl\Error::log(Info::message($info), $code));
			}
			else {
				\dl\Error::log(Info::message($info), $code);
			}
		}
	}
}

abstract class Leaf extends Component {
	// Childless нужно подключать в потомках;

	protected array $_var;
	protected array $_ref;
	protected array $_stack;
	protected array $_order;
	protected int   $_size;

	public function __construct(array $state) {
		parent::__construct($state);

		$this->_var   = $state['_var'];
		$this->_ref   = $state['_ref'];
		$this->_stack = $state['_stack'];
		$this->_order = $state['_order'];
		$this->_size  = $state['_size'];

		foreach ($this->_ref as $i => $name) {
			$this->_stack[$i] =&$this->_var[$name];
		}
	}
}

abstract class LineLeaf extends Leaf {
	protected bool $_exert;

	public function __construct(array $state) {
		parent::__construct($state);
		$this->_exert = $state['_exert'];
	}
}

abstract class Composite extends Leaf {
	use Parentness;
	use CompositeGetter;

	protected array $_child;

	public function __construct(array $state) {
		parent::__construct($state);
		$this->_child = $state['_child'];
	}
}

abstract class LineComposite extends Composite {
	protected bool $_exert;

	public function __construct(array $state) {
		parent::__construct($state);
		$this->_exert = $state['_exert'];
	}
}

class Variant extends Component {
	use Parentness;
	use VariantGetter;

	protected array  $_child;
	protected string $_variant;

	public function __construct(array $state) {
		parent::__construct($state);
		$this->_child   = $state['_child'];
		$this->_variant = $state['_variant'];
	}
}

/**
* 
*/
class ActiveComposite extends Composite {
	
}

class ActiveCompositeList extends Composite {

}

class ActiveCompositeMap extends Composite {

}

class FixedComposite extends LineComposite {

}

class FixedCompositeList extends LineComposite {

}

class FixedCompositeMap extends LineComposite {

}

class WrappedActiveComposite extends Composite {
	use Wrapper;
}

class WrappedActiveCompositeList extends Composite {
	use Wrapper;
}

class WrappedActiveCompositeMap extends Composite {
	use Wrapper;
}

class WrappedFixedComposite extends LineComposite {
	use Wrapper;
}

class WrappedFixedCompositeList extends LineComposite {
	use Wrapper;
}

class WrappedFixedCompositeMap extends LineComposite {
	use Wrapper;
}

class Document extends Composite {

}

class ActiveLeaf extends Leaf {
	use Childless;
}

class ActiveLeafList extends Leaf {
	use Childless;
}

class ActiveLeafMap extends Leaf {
	use Childless;
}

class FixedLeaf extends LineLeaf {
	use Childless;
}

class FixedLeafList extends LineLeaf {
	use Childless;
}

class FixedLeafMap extends LineLeaf {
	use Childless;
}

class WrappedActiveLeaf extends Leaf {
	use Wrapper;
	use Childless;
}

class WrappedActiveLeafList extends Leaf {
	use Wrapper;
	use Childless;
}

class WrappedActiveLeafMap extends Leaf {
	use Wrapper;
	use Childless;
}

class WrappedFixedLeaf extends LineLeaf {
	use Wrapper;
	use Childless;
}

class WrappedFixedLeafList extends LineLeaf {
	use Wrapper;
	use Childless;
}

class WrappedFixedLeafMap extends LineLeaf {
	use Wrapper;
	use Childless;
}

class DocumentLeaf extends Leaf {
	use Childless;
}

class Variator extends Variant {
	
}

class WrappedVariator extends Variant {
	use Wrapper;
}

final class Emulator extends Component implements \Stringable {
	use Childless;

	public function __call(string $name, array $value): bool {
		return true;
	}

	public function __get(string $name): Emulator {
		return $this;
	}

	public function __set(string $name, mixed $value): void {
		return;
	}

	public function __toString(): string {
		return '';
	}
}
