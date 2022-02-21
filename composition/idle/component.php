<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

	namespace dl\tt\idle

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt\idle;

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

trait Insertion {
	final public function __set(string $name, int|float|string $value): void {
		$this->_var[$name] = $value;
	}
}

trait InsertionMap {
	final public function __set(string $name, int|float|string|array $value): void {
		if (\is_array($value)) {
			foreach ($value as $key => $val) {
				$this->__set($name.\dl\tt\Component::NS.$key, $val);
			}
		}
		else {
			$this->_var[$name] = $value;
		}
	}
}

trait ReadyComposite {
	use \dl\tt\IndependentComponent;

	public function ready(): void {
		$this->notify();
		$this->_result.= \str_replace($this->_ref, $this->_var, $this->_text);
	}
}

trait ReadyLeaf {
	use \dl\tt\IndependentComponent;

	public function ready(): void {
		$this->_result.= \str_replace($this->_ref, $this->_var, $this->_text);
	}
}

trait DependentCompositeResult {
	final public function getRawResult(): string {
		if ($this->_exert) {
			$this->_exert = false;
			$this->notify();
			$this->_result = \str_replace($this->_ref, $this->_var, $this->_text);
		}

		return $this->_result;
	}
}

trait DependentLeafResult {
	final public function getRawResult(): string {
		if ($this->_exert) {
			$this->_exert = false;
			$this->_result = \str_replace($this->_ref, $this->_var, $this->_text);
		}

		return $this->_result;
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
			'_text'      => $this->_text,
			'_var'       => $this->_var,
			'_ref'       => $this->_ref,
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
			'_text'  => $this->_text,
			'_var'   => $this->_var,
			'_ref'   => $this->_ref,
			'_class' => $this->_class,
			'_name'  => $this->_name,
		]);
	}
}

abstract class Leaf extends \dl\tt\Component {
	use Sequence;
	use \dl\tt\Childless;

	protected string $_text;
    protected array  $_var;
	protected array  $_ref;

	public function __construct(array $state) {
		parent::__construct($state);
        $this->_var  = $state['_var'];
        $this->_ref  = $state['_ref'];
        $this->_text = $state['_text'];
	}

	final public function common(string $name, int|float|string $value): void {
		$this->_var[$name] = $value;
	}
}

abstract class Performer extends \dl\tt\Composite {
	use Sequence;
	use \dl\tt\Performance;

	protected string $_text;
	protected array  $_var;
	protected array  $_ref;

	public function __construct(array $state) {
		parent::__construct($state);
        $this->_text = $state['_text'];
        $this->_var  = $state['_var'];
        $this->_ref  = $state['_ref'];
	}

	public function __clone(): void {
		foreach (\array_keys($this->_component) as $name) {
			$this->_component[$name] = clone $this->_component[$name];
		}
	}

	final public function __unset(string $name): void {
		if (isset($this->_component[$name])) {
			$this->_var[\dl\tt\Component::NS.$name] = $this->_component[$name]->getResult();
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
			$name = \dl\tt\Component::NS.$component->getName();
			$this->_var[$name] = $component->getResult();
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
	use ReadyComposite;
	use \dl\tt\Result;
}

final class OriginalCompositeMap extends Performer {
	use InsertionMap;
	use ReadyComposite;
	use \dl\tt\Result;
}

final class FixedComposite extends DependentPerformer implements \dl\tt\Derivative {
	use Insertion;
	use DependentCompositeResult;
	use PerformerMaster;
	use \dl\tt\DependentResult;
}

final class FixedCompositeMap extends DependentPerformer implements \dl\tt\Derivative {
	use InsertionMap;
	use DependentCompositeResult;
	use PerformerMaster;
	use \dl\tt\DependentResult;
}

final class WrappedOriginalComposite extends Performer implements \dl\tt\Derivative, \dl\tt\Wrapped {
	use Insertion;
	use ReadyComposite;
	use PerformerMaster;
	use \dl\tt\WrappedComponent;
	use \dl\tt\WrappedResult;
}

final class WrappedOriginalCompositeMap extends Performer implements \dl\tt\Derivative, \dl\tt\Wrapped {
	use InsertionMap;
	use ReadyComposite;
	use PerformerMaster;
	use \dl\tt\WrappedComponent;
	use \dl\tt\WrappedResult;
}

final class WrappedFixedComposite extends DependentPerformer implements \dl\tt\Derivative, \dl\tt\Wrapped {
	use Insertion;
	use DependentCompositeResult;
	use PerformerMaster;
	use \dl\tt\WrappedComponent;
	use \dl\tt\WrappedDependentResult;
}

final class WrappedFixedCompositeMap extends DependentPerformer implements \dl\tt\Derivative, \dl\tt\Wrapped {
	use InsertionMap;
	use DependentCompositeResult;
	use PerformerMaster;
	use \dl\tt\WrappedComponent;
	use \dl\tt\WrappedDependentResult;
}

final class OriginalLeaf extends Leaf {
	use Insertion;
	use ReadyLeaf;
	use \dl\tt\Result;
}

final class OriginalLeafMap extends Leaf {
	use InsertionMap;
	use ReadyLeaf;
	use \dl\tt\Result;
}

final class FixedLeaf extends DependentLeaf implements \dl\tt\Derivative {
	use Insertion;
	use DependentLeafResult;
	use LeafMaster;
	use \dl\tt\DependentResult;
}

final class FixedLeafMap extends DependentLeaf implements \dl\tt\Derivative {
	use InsertionMap;
	use DependentLeafResult;
	use LeafMaster;
	use \dl\tt\DependentResult;
}

final class WrappedOriginalLeaf extends Leaf implements \dl\tt\Derivative, \dl\tt\Wrapped {
	use Insertion;
	use ReadyLeaf;
	use LeafMaster;
	use \dl\tt\WrappedComponent;
	use \dl\tt\WrappedResult;
}

final class WrappedOriginalLeafMap extends Leaf implements \dl\tt\Derivative, \dl\tt\Wrapped {
	use InsertionMap;
	use ReadyLeaf;
	use LeafMaster;
	use \dl\tt\WrappedComponent;
	use \dl\tt\WrappedResult;
}

final class WrappedFixedLeaf extends DependentLeaf implements \dl\tt\Derivative, \dl\tt\Wrapped {
	use Insertion;
	use DependentLeafResult;
	use LeafMaster;
	use \dl\tt\WrappedComponent;
	use \dl\tt\WrappedDependentResult;
}

final class WrappedFixedLeafMap extends DependentLeaf implements \dl\tt\Derivative, \dl\tt\Wrapped {
	use InsertionMap;
	use DependentLeafResult;
	use LeafMaster;
	use \dl\tt\WrappedComponent;
	use \dl\tt\WrappedDependentResult;
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

	public function __set(string $name, int|float|string|array $value): void {
		if (\is_array($value)) {
			foreach ($value as $key => $val) {
				$this->_var[$name.\dl\tt\Component::NS.$key] = $value;
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
			$this->_result = \str_replace($this->_ref, $this->_var, $this->_text);
			
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
		$this->_result = \str_replace($this->_ref, $this->_var, $this->_text);
	}
}
