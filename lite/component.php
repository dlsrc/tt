<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

	namespace dl\tt\lite

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt\lite;

trait Sequence {
	protected array $_ref;
	protected array $_chain;

	public function __construct(array $state) {
		parent::__construct($state);
        $this->_ref   = $state['_ref'];
        $this->_chain = $state['_chain'];

		foreach ($this->_ref as $k => $v) {
			$this->_chain[$k] =&$this->_chain[$v];
		}
	}
}

trait Invoke {
    final public function __invoke(array $data, array $order=[]): void {
        if (empty($order)) {
            foreach ($data as $name => $value) {
				if (isset($this->_chain[$name])) {
					$this->_chain[$name] = $value;
				}
            }
        }
        else {
            if (!\array_is_list($data)) {
                $data = \array_values($data);
            }

            foreach ($order as $id => $name) {
                if (isset($this->_chain[$name])) {
					$this->_chain[$name] = $data[$id];
				}
            }
        }

        $this->ready();
    }
}

trait Insertion {
	final public function __set(string $name, int|float|string $value): void {
		if (isset($this->_chain[$name])) {
			$this->_chain[$name] = $value;
		}
	}
}

trait InsertionMap {
	final public function __set(string $name, int|float|string|array $value): void {
		if (\is_array($value)) {
			foreach ($value as $key => $val) {
				$this->__set($name.\dl\tt\Component::NS.$key, $val);
			}
		}
		elseif (isset($this->_chain[$name])) {
			$this->_chain[$name] = $value;
		}
	}
}

trait PerformerMaster {
	public function getOriginal(): Performer {
		$component = [];

		foreach (\array_keys($this->_component) as $name) {
			$component[$name] = clone $this->_component[$name];
		}

		if (\str_ends_with(__CLASS__, 'Map')) {
			$class = OriginalCompositeMap::class;
		}
		else {
			$class = OriginalComposite::class;
		}

		return new $class([
			'_chain'     => $this->_chain,
			'_var'       => $this->_var,
			'_ref'       => $this->_ref,
			'_child'     => $this->_child,
			'_class'     => $this->_class,
			'_name'      => $this->_name,
			'_component' => $component,
		]);
	}
}

trait LeafMaster {
	public function getOriginal(): Leaf {
		if (\str_ends_with(__CLASS__, 'Map')) {
			$class = OriginalLeafMap::class;
		}
		else {
			$class = OriginalLeaf::class;
		}

		return new $class([
			'_chain'  => $this->_chain,
			'_var'    => $this->_var,
			'_ref'    => $this->_ref,
			'_class'  => $this->_class,
			'_name'   => $this->_name,
		]);
	}
}

abstract class Composite extends \dl\tt\Component {
    protected array $_component;

	public function __construct(array $state) {
		parent::__construct($state);
        $this->_component = $state['_component'];
	}

	final public function __clone(): void {
		foreach (\array_keys($this->_component) as $name) {
			$this->_component[$name] = clone $this->_component[$name];
		}
	}

	final public function __isset(string $name): bool {
		return isset($this->_component[$name]);
	}

	final public function drop(): void {
		foreach ($this->_component as $component) {
			$component->drop();
		}

		$this->update();
	}

	final public function isComponent(string $name): bool {
		if (isset($this->_component[$name])) {
			return true;
		}

		if (\str_contains($name, \dl\tt\Component::NS)) {
			$branch = \explode(\dl\tt\Component::NS, $name);
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

	final public function getChild(string $class): \dl\tt\Component {
		foreach ($this->_component as $component) {
			if ($component->isClass($class)) {
				return $component;
			}
		}

		\dl\tt\Component::error(Info::message('e_no_class', $class), Code::Type);
		return \dl\tt\Component::emulate();
	}

	final public function getChildName(string $class): string|null {
		foreach ($this->_component as $name => $component) {
			if ($component->isClass($class)) {
				return $name;
			}
		}

		return null;
	}

	final public function getChildNames(string $class): array {
		$names = [];

		foreach ($this->_component as $name => $component) {
			if ($component->isClass($class)) {
				$names[] = $name;
			}
		}

		return $names;
	}
}

abstract class Variant extends Composite {
    protected string $_variant;

	public function __construct(array $state) {
		parent::__construct($state);
        $this->_variant = $state['_variant'];
	}

    public function __invoke(array $data, array $order=[]): void {
		$this->_component[$this->_variant]($data, $order);
    }

	final public function __call(string $name, array $data): bool {
        if (!isset($this->_component[$name])) {
    		\dl\tt\Component::error(Info::message('e_no_child', $name), Code::Component);
	    	return false;
        }

		$this->_variant = $name;

        if (isset($data[1])) {
            $this->_component[$name]($data[0], $data[1]);
        }
        elseif (isset($data[0])) {
            $this->_component[$name]($data[0]);
        }

        return true;
	}

	final public function __get(string $name): \dl\tt\Component {
		if (isset($this->_component[$name])) {
			$this->_variant = $name;
			return $this->_component[$name];
		}

		\dl\tt\Component::error(Info::message('e_no_child', $name), Code::Component);
		return \dl\tt\Component::emulate();
	}

	final public function __unset(string $name): void {
		unset($this->_component[$name]);
	}

	final public function __set(string $name, int|float|string|array $value): void {
		$this->_component[$this->_variant]->$name = $value;
	}

	final public function common(string $name, int|float|string $value): void {
		foreach ($this->_component as $component) {
			$component->common($name, $value);
		}
	}
}

abstract class Leaf extends \dl\tt\Component {
    use Sequence;
	use Invoke;
	use \dl\tt\Childless;

	final public function common(string $name, int|float|string $value): void {
		if (isset($this->_chain[$name])) {
			$this->_chain[$name] = $value;
		}
	}
}

abstract class Performer extends Composite {
    use Sequence;
	use Invoke;

	final public function __call(string $name, array $data): bool {
        if (!isset($this->_component[$name])) {
    		\dl\tt\Component::error(Info::message('e_no_child', $name), Code::Component);
	    	return false;
        }

        if (isset($data[1])) {
            $this->_component[$name]($data[0], $data[1]);
        }
        elseif (isset($data[0])) {
            $this->_component[$name]($data[0]);
        }

        return true;
	}

	final public function __get(string $name): \dl\tt\Component {
		if (isset($this->_component[$name])) {
			return $this->_component[$name];
		}

		\dl\tt\Component::error(Info::message('e_no_child', $name), Code::Component);
		return \dl\tt\Component::emulate();
	}

	final public function __unset(string $name): void {
		if (isset($this->_component[$name])) {
			$this->_chain[\dl\tt\Component::NS.$name] = $this->_component[$name]->getResult();
			unset($this->_component[$name]);
		}
	}

	final public function common(string $name, int|float|string $value): void {
		if (isset($this->_chain[$name])) {
			$this->_chain[$name] = $value;
		}

		foreach ($this->_component as $component) {
			$component->common($name, $value);
		}
	}

	final protected function notify(): void {
		foreach ($this->_component as $component) {
			$name = \dl\tt\Component::NS.$component->getName();
			$this->_chain[$name] = $component->getResult();
			$component->update();
		}
	}
}

abstract class DependentLeaf extends Leaf {
	use \dl\tt\DependentComponent;

	public function isReady(): bool {
		return $this->_exert;
	}
}

abstract class DependentPerformer extends Performer {
    use \dl\tt\DependentComponent;

	public function isReady(): bool {
		foreach ($this->_component as $component) {
			if ($component->isReady()) return true;
		}

		return false;
	}
}

final class OriginalComposite extends Performer {
	use Insertion;
	use \dl\tt\ReadyComposite;
	use \dl\tt\RootComponent;
}

final class OriginalCompositeMap extends Performer {
	use InsertionMap;
	use \dl\tt\ReadyComposite;
	use \dl\tt\RootComponent;
}

final class FixedComposite extends DependentPerformer implements \dl\tt\Derivative {
	use Insertion;
	use PerformerMaster;
	use \dl\tt\DependentResult;
	use \dl\tt\DependentCompositeResult;
}

final class FixedCompositeMap extends DependentPerformer implements \dl\tt\Derivative {
	use InsertionMap;
	use PerformerMaster;
	use \dl\tt\DependentResult;
	use \dl\tt\DependentCompositeResult;
}

final class WrappedOriginalComposite extends Performer implements \dl\tt\Derivative, \dl\tt\Wrapped {
	use Insertion;
	use PerformerMaster;
	use \dl\tt\WrappedComponent;
	use \dl\tt\ReadyComposite;
	use \dl\tt\WrappedResult;
}

final class WrappedOriginalCompositeMap extends Performer implements \dl\tt\Derivative, \dl\tt\Wrapped {
	use InsertionMap;
	use PerformerMaster;
	use \dl\tt\WrappedComponent;
	use \dl\tt\ReadyComposite;
	use \dl\tt\WrappedResult;
}

final class WrappedFixedComposite extends DependentPerformer implements \dl\tt\Derivative, \dl\tt\Wrapped {
	use Insertion;
	use PerformerMaster;
	use \dl\tt\WrappedComponent;
	use \dl\tt\DependentCompositeResult;
	use \dl\tt\WrappedDependentResult;
}

final class WrappedFixedCompositeMap extends DependentPerformer implements \dl\tt\Derivative, \dl\tt\Wrapped {
	use InsertionMap;
	use PerformerMaster;
	use \dl\tt\WrappedComponent;
	use \dl\tt\DependentCompositeResult;
	use \dl\tt\WrappedDependentResult;
}

final class OriginalLeaf extends Leaf {
	use Insertion;
	use \dl\tt\ReadyLeaf;
	use \dl\tt\RootComponent;
}

final class OriginalLeafMap extends Leaf {
	use InsertionMap;
	use \dl\tt\ReadyLeaf;
	use \dl\tt\RootComponent;
}

final class FixedLeaf extends DependentLeaf implements \dl\tt\Derivative {
	use Insertion;
	use LeafMaster;
	use \dl\tt\DependentResult;
	use \dl\tt\DependentLeafResult;
}

final class FixedLeafMap extends DependentLeaf implements \dl\tt\Derivative {
	use InsertionMap;
	use LeafMaster;
	use \dl\tt\DependentResult;
	use \dl\tt\DependentLeafResult;
}

final class WrappedOriginalLeaf extends Leaf implements \dl\tt\Derivative, \dl\tt\Wrapped {
	use Insertion;
	use LeafMaster;
	use \dl\tt\WrappedComponent;
	use \dl\tt\ReadyLeaf;
	use \dl\tt\WrappedResult;
}

final class WrappedOriginalLeafMap extends Leaf implements \dl\tt\Derivative, \dl\tt\Wrapped {
	use InsertionMap;
	use LeafMaster;
	use \dl\tt\WrappedComponent;
	use \dl\tt\ReadyLeaf;
	use \dl\tt\WrappedResult;
}

final class WrappedFixedLeaf extends DependentLeaf implements \dl\tt\Derivative, \dl\tt\Wrapped {
	use Insertion;
	use LeafMaster;
	use \dl\tt\WrappedComponent;
	use \dl\tt\DependentLeafResult;
	use \dl\tt\WrappedDependentResult;
}

final class WrappedFixedLeafMap extends DependentLeaf implements \dl\tt\Derivative, \dl\tt\Wrapped {
	use InsertionMap;
	use LeafMaster;
	use \dl\tt\WrappedComponent;
	use \dl\tt\DependentLeafResult;
	use \dl\tt\WrappedDependentResult;
}

final class Variator extends Variant {
	use \dl\tt\ReadyVariant;
	use \dl\tt\RootComponent;
}

final class WrappedVariator extends Variant implements \dl\tt\Derivative, \dl\tt\Wrapped {
	use \dl\tt\WrappedComponent;
	use \dl\tt\ReadyVariant;
	use \dl\tt\WrappedResult;

	public function getOriginal(): Variator {
		$component = [];
		
		foreach (\array_keys($this->_component) as $name) {
			$component[$name] = clone $this->_component[$name];
		}

		return new Variator([
			'_class'     => $this->_class,
			'_name'      => $this->_name,
			'_component' => $component,
			'_variant'   => $this->_variant,
		]);
	}
}

final class Complex extends Performer {
	use \dl\tt\RootComponent;
	use \dl\tt\IndependentComponent;

	protected array  $_global;
	protected string $_first;
	protected string $_last;

	public function __construct(array $state) {
		parent::__construct($state);
		$this->_global = $state['_global'];
		$this->_first  = $state['_first'];
		$this->_last   = $state['_last'];
	}

	final public function __set(string $name, int|float|string|array $value): void {
		if (\is_array($value)) {
			foreach ($value as $key => $val) {
				$this->__set($name.\dl\tt\Component::NS.$key, $val);
			}
		}
		elseif (isset($this->_chain[$name])) {
			$this->_chain[$name] = $value;
		}
		else {
			$this->_global[$this->_first.$name.$this->_last] = $value;
		}
	}

	final public function ready(): void {
		if ('' == $this->_result) {
			$this->notify();
			$this->_result = \implode('', $this->_chain);
			
			if (!empty($this->_global)) {
				$this->_result = \str_replace(
					\array_keys($this->_global),
					$this->_global,
					$this->_result
				);
			}
		}
	}

	final public function force(string $name, string $text): bool {
		if (!isset($this->_component[$name])) {
			return false;
		}

		$this->_component[$name]->insert($text);
		return true;
	}
}

final class Document extends Leaf {
	use InsertionMap;
	use \dl\tt\RootComponent;
	use \dl\tt\IndependentComponent;

	public function ready(): void {
		$this->_result = \implode('', $this->_chain);
	}
}
