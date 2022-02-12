<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

	namespace dl\tt

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt\std;

use \dl\tt\Wrapped;
use \dl\tt\Derivative;

use \dl\tt\Childless;
use \dl\tt\DependentComponent;
use \dl\tt\IndependentComponent;
use \dl\tt\ReadyComposite;
use \dl\tt\ReadyLeaf;
use \dl\tt\ReadyText;
use \dl\tt\ReadyVariant;
use \dl\tt\WrappedComponent;
use \dl\tt\RootComponent;
use \dl\tt\InsertionStub;
use \dl\tt\RawResult;
use \dl\tt\Result;
use \dl\tt\WrappedResult;
use \dl\tt\DependentInsert;
use \dl\tt\DependentCompositeResult;
use \dl\tt\DependentLeafResult;
use \dl\tt\DependentTextResult;
use \dl\tt\DependentResult;
use \dl\tt\WrappedDependentResult;

use \dl\tt\Component;
use \dl\tt\Composite;
use \dl\tt\Variant;
use \dl\tt\Text;
use \dl\tt\DependentText;

trait Sequence {
    public function __invoke(array $data, array $order=[]): void {
        if (empty($order)) {
            foreach ($data as $name => $value) {
                $this->_var[$name] = $value;
            }
        }
        else {
            if (!\array_is_list($data)) {
                $data = \array_values($data);
            }

            foreach ($order as $id => $name) {
                $this->_var[$name] = $data[$id];
            }
        }

        $this->ready();
    }
}

abstract class Leaf extends Component {
	use Sequence;
	use Childless;

    protected array $_var;
	protected array $_ref;
	protected array $_chain;

	public function __construct(array $state) {
		parent::__construct($state);
        $this->_var   = $state['_var'];
        $this->_ref   = $state['_ref'];
        $this->_chain = $state['_chain'];

		foreach ($this->_ref as $i => $name) {
			$this->_chain[$i] =&$this->_var[$name];
		}
	}

	public function __clone(): void {
		$clone = [];

		foreach ($this->_var as $name => $value) {
			$clone[$name] = $value;
		}

		$this->_var = $clone;

		foreach ($this->_ref as $i => $name) {
			$this->_chain[$i] =&$this->_var[$name];
		}
	}

	final public function common(string $name, int|float|string $value): void {
		$this->_var[$name] = $value;
	}
}

abstract class Performer extends Composite {
	use Sequence;

	protected array $_var;
	protected array $_ref;
	protected array $_child;
	protected array $_chain;

	public function __construct(array $state) {
		parent::__construct($state);
        $this->_var   = $state['_var'];
        $this->_ref   = $state['_ref'];
        $this->_child = $state['_child'];
        $this->_chain = $state['_chain'];

		foreach ($this->_ref as $i => $name) {
			$this->_chain[$i] =&$this->_var[$name];
		}

		foreach ($this->_child as $k => $v) {
			$this->_chain[$k] =&$this->_chain[$v];
		}
	}

	public function __clone(): void {
		foreach (\array_keys($this->_component) as $name) {
			$this->_component[$name] = clone $this->_component[$name];
		}

		$clone = [];

		foreach ($this->_var as $name => $value) {
			$clone[$name] = $value;
		}

		$this->_var = $clone;

		foreach ($this->_ref as $i => $name) {
			$this->_chain[$i] =&$this->_var[$name];
		}
	}

	final public function __call(string $name, array $data): bool {
        if (!isset($this->_component[$name])) {
    		Component::error(Info::message('e_no_child', $name), Code::Component);
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

	final public function __get(string $name): Component {
		if (isset($this->_component[$name])) {
			return $this->_component[$name];
		}

		Component::error(Info::message('e_no_child', $name), Code::Component);
		return Component::emulate();
	}

	final public function __unset(string $name): void {
		if (isset($this->_component[$name])) {
			$this->_chain[Component::NS.$name] = $this->_component[$name]->getResult();
			unset($this->_component[$name]);
		}
	}

	final public function common(string $name, int|float|string $value): void {
		$this->_var[$name] = $value;

		foreach ($this->_component as $component) {
			$component->common($name, $value);
		}
	}

	final protected function notify(): void {
		foreach ($this->_component as $component) {
			$name = Component::NS.$component->getName();
			$this->_chain[$name] = $component->getResult();
			$component->update();
		}
	}
}

abstract class DependentLeaf extends Leaf {
	use DependentComponent;

	public function isReady(): bool {
		return $this->_exert;
	}
}

abstract class DependentPerformer extends Performer {
    use DependentComponent;

	public function isReady(): bool {
		foreach ($this->_component as $component) {
			if ($component->isReady()) return true;
		}

		return false;
	}
}

trait Insertion {
	final public function __set(string $name, int|float|string $value): void {
		$this->_var[$name] = $value;
	}
}

trait InsertionMap {
	final public function __set(string $name, int|float|string|array $value): void {
		if (\is_array($value)) {
			foreach ($value as $key => $val) {
				$this->_var[$name.Component::NS.$key] = $value;
			}
		}
		else {
			$this->_var[$name] = $value;
		}
	}
}

trait PerformerMaster {
	public function getOriginal(): Performer {
		$component = [];

		foreach (\array_keys($this->_component) as $name) {
			$component[$name] = clone $this->_component[$name];
		}

		$var = [];

		foreach ($this->_var as $name => $value) {
			$var[$name] = $value;
		}

		if (\str_ends_with(__CLASS__, 'Map')) {
			$class = OriginalCompositeMap::class;
		}
		else {
			$class = OriginalComposite::class;
		}

		return new $class([
			'_chain'     => $this->_chain,
			'_var'       => $var,
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
		$var = [];

		foreach($this->_var as $name => $value) {
			$var[$name] = $value;
		}

		if (\str_ends_with(__CLASS__, 'Map')) {
			$class = OriginalLeafMap::class;
		}
		else {
			$class = OriginalLeaf::class;
		}

		return new $class([
			'_chain'  => $this->_chain,
			'_var'    => $var,
			'_ref'    => $this->_ref,
			'_class'  => $this->_class,
			'_name'   => $this->_name,
		]);
	}
}

final class OriginalComposite extends Performer {
	use Insertion;
	use ReadyComposite;
	use Result;
}

final class OriginalCompositeMap extends Performer {
	use InsertionMap;
	use ReadyComposite;
	use Result;
}

final class FixedComposite extends DependentPerformer implements Derivative {
	use Insertion;
	use DependentResult;
	use DependentCompositeResult;
	use PerformerMaster;
}

final class FixedCompositeMap extends DependentPerformer implements Derivative {
	use InsertionMap;
	use DependentResult;
	use DependentCompositeResult;
	use PerformerMaster;
}

final class WrappedOriginalComposite extends Performer implements Derivative, Wrapped {
	use WrappedComponent;
	use ReadyComposite;
	use Insertion;
	use WrappedResult;
	use PerformerMaster;
}

final class WrappedOriginalCompositeMap extends Performer implements Derivative, Wrapped {
	use WrappedComponent;
	use ReadyComposite;
	use InsertionMap;
	use WrappedResult;
	use PerformerMaster;
}

final class WrappedFixedComposite extends DependentPerformer implements Derivative, Wrapped {
	use WrappedComponent;
	use Insertion;
	use WrappedDependentResult;
	use DependentCompositeResult;
	use PerformerMaster;
}

final class WrappedFixedCompositeMap extends DependentPerformer implements Derivative, Wrapped {
	use WrappedComponent;
	use InsertionMap;
	use WrappedDependentResult;
	use DependentCompositeResult;
	use PerformerMaster;
}

final class OriginalLeaf extends Leaf {
	use Insertion;
	use ReadyLeaf;
	use Result;
}

final class OriginalLeafMap extends Leaf {
	use InsertionMap;
	use ReadyLeaf;
	use Result;
}

final class FixedLeaf extends DependentLeaf implements Derivative {
	use Insertion;
	use DependentResult;
	use DependentLeafResult;
	use LeafMaster;
}

final class FixedLeafMap extends DependentLeaf implements Derivative {
	use InsertionMap;
	use DependentResult;
	use DependentLeafResult;
	use LeafMaster;
}

final class WrappedOriginalLeaf extends Leaf implements Derivative, Wrapped {
	use WrappedComponent;
	use ReadyLeaf;
	use Insertion;
	use WrappedResult;
	use LeafMaster;
}

final class WrappedOriginalLeafMap extends Leaf implements Derivative, Wrapped {
	use WrappedComponent;
	use ReadyLeaf;
	use InsertionMap;
	use WrappedResult;
	use LeafMaster;
}

final class WrappedFixedLeaf extends DependentLeaf implements Derivative, Wrapped {
	use WrappedComponent;
	use Insertion;
	use WrappedDependentResult;
	use DependentLeafResult;
	use LeafMaster;
}

final class WrappedFixedLeafMap extends DependentLeaf implements Derivative, Wrapped {
	use WrappedComponent;
	use InsertionMap;
	use WrappedDependentResult;
	use DependentLeafResult;
	use LeafMaster;
}

final class Complex extends Performer {
	use RootComponent;
	use IndependentComponent;

	protected array  $_global;
	protected string $_first;
	protected string $_last;

	public function __construct(array $state) {
		parent::__construct($state);
		$this->_global = $state['_global'];
		$this->_first  = $state['_first'];
		$this->_last   = $state['_last'];
	}

	public function __set(string $name, int|float|string|array $value): void {
		if (\is_array($value)) {
			foreach ($value as $key => $val) {
				$this->_var[$name.Component::NS.$key] = $value;
			}
		}
		elseif (isset($this->_var[$name])) {
			$this->_var[$name] = $value;
		}
		else {
			$this->_global[$this->_first.$name.$this->_last] = $value;
		}
	}

	public function ready(): void {
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
	use RootComponent;
	use IndependentComponent;

	public function ready(): void {
		$this->_result = \implode('', $this->_chain);
	}
}
