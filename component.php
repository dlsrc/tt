<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

	namespace dl\tt

	interface Wrapped

    abstract class Component          implements \dl\DirectCallable

	abstract class Composite          extends Component
	abstract class Variant            extends Composite
	abstract class Leaf               extends Component use Sequence
	abstract class Performer          extends Composite use Sequence
	abstract class DependentLeaf      extends Performer use DependentComponent
	abstract class DependentPerformer extends Performer use DependentComponent

	trait Sequence
	trait DependentComponent
	trait IndependentComponent
	trait ReadyComponent         use IndependentComponent
	trait ReadyVariant           use IndependentComponent
	trait WrappedComponent
	trait RootComponent
	trait Insertion
	trait InsertionMap
	trait RawResult
	trait Result                 use RawResult
	trait WrappedResult          use RawResult
	trait DependentRawResult
	trait DependentResult        use DependentRawResult
	trait WrappedDependentResult use DependentRawResult

	final class ActiveComposite           extends Performer          use Insertion, ReadyComponent, Result
	final class ActiveCompositeMap        extends Performer          use InsertionMap, ReadyComponent, Result
	final class FixedComposite            extends DependentPerformer use Insertion, DependentResult
	final class FixedCompositeMap         extends DependentPerformer use InsertionMap, DependentResult
	final class Document                  extends Performer          use RootComponent, IndependentComponent
	final class WrappedActiveComposite    extends Performer          use WrappedComponent, ReadyComponent, Insertion, WrappedResult
	final class WrappedActiveCompositeMap extends Performer          use WrappedComponent, ReadyComponent, InsertionMap, WrappedResult
	final class WrappedFixedComposite     extends DependentPerformer use WrappedComponent, Insertion, WrappedDependentResult
	final class WrappedFixedCompositeMap  extends DependentPerformer use WrappedComponent, InsertionMap, WrappedDependentResult
	final class ActiveLeaf                extends Leaf               use Insertion, ReadyComponent, Result
	final class ActiveLeafMap             extends Leaf               use InsertionMap, ReadyComponent Result
	final class FixedLeaf                 extends DependentLeaf      use Insertion, DependentResult
	final class FixedLeafMap              extends DependentLeaf      use InsertionMap, DependentResult
	final class Text                      extends Leaf               use RootComponent, IndependentComponent
	final class WrappedActiveLeaf         extends Leaf               use WrappedComponent, ReadyComponent, Insertion, WrappedResult
	final class WrappedActiveLeafMap      extends Leaf               use WrappedComponent, ReadyComponent, InsertionMap, WrappedResult
	final class WrappedFixedLeaf          extends DependentLeaf      use WrappedComponent, Insertion, WrappedDependentResult
	final class WrappedFixedLeafMap       extends DependentLeaf      use WrappedComponent, InsertionMap, WrappedDependentResult
	final class Variator                  extends Variant            use ReadyVariant, Result
	final class WrappedVariator           extends Variant            use WrappedComponent, ReadyVariant, WrappedResult
	final class Emulator                  extends Component

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt;

interface Wrapped {
	public function unwrap(): void;
}

abstract class Component implements \dl\DirectCallable {
	final public const NS = '.';

	abstract public function attach(Component $c): void;
	abstract public function drop(): void;
	abstract public function isComponent(string $name): bool;
	abstract public function getChild(string $class): Component;
	abstract public function getChildName(string $class): string|null;
	abstract public function getChildNames(string $class): array;
	abstract public function __call(string $name, array $value): bool;
	abstract public function __get(string $name): Component;
	abstract public function __isset(string $name): bool;
	abstract public function __unset(string $name): void;
	abstract public function __set(string $name, string|int|float $value): void;
	abstract public function getResult(): string;
	abstract public function getRawResult(): string;
	abstract public function isReady(): bool;
	abstract public function insert(string $text): void;
	abstract public function common(string $name, string|int|float $value): void;
	abstract public function ready(): void;
	abstract protected function notify(): void;

    protected string $_name;
    protected string $_class;
    protected string $_result;

	public function __construct(array $state) {
		$this->_name   = $state['_name'];
		$this->_class  = $state['_class'];
		$this->_result = '';
	}

	final public function getName(): string {
		return $this->_name;
	}

	final public function getClass(): string {
		return $this->_class;
	}

	final public function isClass(string $class): bool {
		return $this->_class == $class;
	}

	final protected function update(): void {
		$this->_result = '';
	}

	final public static function emulate(): Emulator {
		return new Emulator([
			'_class'  => 'Emulator',
			'_name'   => 'Emulator',
			'_result' => '',
		]);
	}

	final public static function error(string $message, Code $code, bool $pro=false): void {
		if (Mode::Develop->current()) {
			throw new Failure(\dl\Error::log($message, $code));
		}
		elseif (Mode::Rebuild->current() || $pro) {
			\dl\Error::log($message, $code);
		}
	}
}

abstract class Composite extends Component {
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

	final public function attach(Component $c): void {
		$name = $c->getName();
		$this->_component[$name] = $c;
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
		foreach ($this->_component as $component) {
			if ($component->isClass($class)) {
				return $component;
			}
		}

		Component::error(Info::message('e_no_class', $class), Code::Type);
		return Component::emulate();
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

	final protected function notify(): void {
		foreach ($this->_component as $component) {
			$name = Component::NS.$component->getName();
			$this->_chain[$name] = $component->getResult();
			$component->update();
		}
	}
}

abstract class Variant extends Composite {
    protected string $_variant;

	public function __construct(array $state) {
		parent::__construct($state);
        $this->_variant = $state['_variant'];
	}

	final public function __call(string $name, array $value): bool {
		if (isset($this->_component[$name])) {
			$this->_variant = $name;

			if (isset($value[0])) {
				foreach ($value[0] as $key => $val) {
					$this->_component[$name]->$key = $val;
				}

				$this->ready();
			}

			return true;
		}

		return false;
	}

	final public function __get(string $name): Component {
		if (isset($this->_component[$name])) {
			$this->_variant = $name;
			return $this->_component[$name];
		}

		Component::error(Info::message('e_no_child', $name), Code::Component);
		return Component::emulate();
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

trait Sequence {
    protected array $_var;
	protected array $_ref;
	protected array $_chain;

	public function __construct(array $state) {
		parent::__construct($state);
        $this->_var   = $state['_var'];
        $this->_ref   = $state['_ref'];
        $this->_chain = $state['_chain'];

		if (isset($this->_ref['var'])) {
			foreach ($this->_ref['var'] as $i => $name) {
				$this->_chain[$i] =&$this->_var[$name];
			}
		}

		if (isset($this->_ref['com'])) {
			foreach ($this->_ref['com'] as $k => $v) {
				$this->_chain[$k] =&$this->_chain[$v];
			}
		}
	}
}

trait Childless {
	final public function __call(string $name, array $value): bool {
		return false;
	}

	final public function __get(string $name): Component {
		Component::error(Info::message('e_no_child', $name), Code::Component);
		return Component::emulate();
	}

	final public function __isset(string $name): bool {
		return false;
	}

	final public function __unset(string $name): void {}

	final public function attach(Component $c): void {}

	final public function drop(): void {
		$this->_result = '';
	}

	final public function isComponent(string $name): bool {
		return false;
	}

	final public function getChild(string $class): Component {
		Component::error(Info::message('e_no_class', $class), Code::Type);
		return Component::emulate();
	}

	final public function getChildName(string $class): string|null {
		return null;
	}

	final public function getChildNames(string $class): array {
		return [];
	}

	final protected function notify(): void {}
}

abstract class Leaf extends Component {
    use Sequence;
	use Childless;

	final public function common(string $name, int|float|string $value): void {
		$this->_var[$name] = $value;
	}
}

abstract class Fragment extends Component {
	use Childless;

	protected string $_text;

	public function __construct(array $state) {
		parent::__construct($state);
        $this->_text = $state['_text'];
	}

	final public function common(string $name, int|float|string $value): void {}
}

abstract class Performer extends Composite {
    use Sequence;

	final public function __call(string $name, array $value): bool {
		if (!isset($this->_component[$name])) {
			return false;
		}

		if (isset($value[0])) {
			foreach ($value[0] as $key => $val) {
				$this->_component[$name]->$key = $val;
			}

			$this->_component[$name]->ready();
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
}

trait DependentComponent {
	protected bool $_exert;

	public function __construct(array $state) {
		parent::__construct($state);
		$this->_exert = false;
	}

	public function ready(): void {
		$this->_exert = true;
	}
}

trait IndependentComponent {
	public function isReady(): bool {
		return '' != $this->_result;
	}
}

trait ReadyComponent {
	use IndependentComponent;

	public function ready(): void {
		$this->notify();
		$this->_result.= \implode('', $this->_chain);
	}
}

trait ReadyFragment {
	use IndependentComponent;

	public function ready(): void {
		$this->_result.= $this->_text;
	}
}

trait ReadyVariant {
	use IndependentComponent;

	public function ready(): void {
		$this->_component[$this->_variant]->ready();
		$this->_result.= $this->_component[$this->_variant]->getResult();
		$this->_component[$this->_variant]->update();
	}
}

abstract class DependentLeaf extends Leaf {
	use DependentComponent;

	public function isReady(): bool {
		return $this->_exert;
	}
}

abstract class DependentFragment extends Fragment {
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



trait WrappedComponent {
	protected string $_before;
	protected string $_after;

	public function __construct(array $state) {
		parent::__construct($state);
		$this->_before = $state['_before'];
		$this->_after  = $state['_after'];
	}

	final public function unwrap(): void {
		$this->_before  = '';
		$this->_after = '';
	}
}

trait RootComponent {
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

	final public function __toString(): string {
		$this->ready();
		return $this->_result;
	}

	final public function getResult(): string {
		$this->ready();
		return $this->_result;
	}

	final public function getRawResult(): string {
		return $this->getResult();
	}

	final public function insert(string $text): void {
		return;
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

trait InsertionStub {
	final public function __set(string $name, int|float|string $value): void {}
}

trait RawResult {
	final public function getRawResult(): string {
		return $this->_result;
	}

	final public function insert(string $text): void {
		$this->_result.= $text;
	}
}

trait Result {
	use RawResult;

	final public function getResult(): string {
		return $this->_result;
	}
}

trait WrappedResult {
	use RawResult;

	final public function getResult(): string {
		if ('' != $this->_result) {
			return $this->_before.$this->_result.$this->_after;
		}

		return '';
	}
}

trait DependentInsert {
	final public function insert(string $text): void {
		$this->_result = $text;
		$this->_exert = false;
	}
}

trait DependentCompositeResult {
	final public function getRawResult(): string {
		if ($this->_exert) {
			$this->_exert = false;
			$this->notify();
			$this->_result = \implode('', $this->_chain);
		}

		return $this->_result;
	}
}

trait DependentLeafResult {
	final public function getRawResult(): string {
		if ($this->_exert) {
			$this->_exert = false;
			$this->_result = \implode('', $this->_chain);
		}

		return $this->_result;
	}
}

trait DependentFragmentResult {
	final public function getRawResult(): string {
		if ($this->_exert) {
			$this->_exert = false;
			$this->_result = $this->_text;
		}

		return $this->_result;
	}
}

trait DependentResult {
	use DependentInsert;

	final public function getResult(): string {
		if ($result = $this->getRawResult()) {
			$this->_result = '';
			return $result;
		}

		return '';
	}
}

trait WrappedDependentResult {
	use DependentInsert;

	final public function getResult(): string {
		if ($result = $this->getRawResult()) {
			$this->_result = '';
			return $this->_before.$result.$this->_after;
		}

		return '';
	}
}

final class ActiveComposite extends Performer {
	use Insertion;
	use ReadyComponent;
	use Result;
}

final class ActiveCompositeMap extends Performer {
	use InsertionMap;
	use ReadyComponent;
	use Result;
}

final class FixedComposite extends DependentPerformer {
	use Insertion;
	use DependentResult;
	use DependentCompositeResult;
}

final class FixedCompositeMap extends DependentPerformer {
	use InsertionMap;
	use DependentResult;
	use DependentCompositeResult;
}

final class Document extends Performer {
	use RootComponent;
	use IndependentComponent;

	final public function force(string $name, string $text): bool {
		if (!isset($this->_component[$name])) {
			return false;
		}

		$this->_component[$name]->insert($text);
		return true;
	}
}

final class WrappedActiveComposite extends Performer implements Wrapped {
	use WrappedComponent;
	use ReadyComponent;
	use Insertion;
	use WrappedResult;
}

final class WrappedActiveCompositeMap extends Performer implements Wrapped {
	use WrappedComponent;
	use ReadyComponent;
	use InsertionMap;
	use WrappedResult;
}

final class WrappedFixedComposite extends DependentPerformer implements Wrapped {
	use WrappedComponent;
	use Insertion;
	use WrappedDependentResult;
	use DependentCompositeResult;
}

final class WrappedFixedCompositeMap extends DependentPerformer implements Wrapped {
	use WrappedComponent;
	use InsertionMap;
	use WrappedDependentResult;
	use DependentCompositeResult;
}

final class ActiveLeaf extends Leaf {
	use Insertion;
	use ReadyComponent;
	use Result;
}

final class ActiveLeafMap extends Leaf {
	use InsertionMap;
	use ReadyComponent;
	use Result;
}

final class FixedLeaf extends DependentLeaf {
	use Insertion;
	use DependentResult;
	use DependentLeafResult;
}

final class FixedLeafMap extends DependentLeaf {
	use InsertionMap;
	use DependentResult;
	use DependentLeafResult;
}

final class Text extends Leaf {
	use RootComponent;
	use IndependentComponent;
}

final class WrappedActiveLeaf extends Leaf implements Wrapped {
	use WrappedComponent;
	use ReadyComponent;
	use Insertion;
	use WrappedResult;
}

final class WrappedActiveLeafMap extends Leaf implements Wrapped {
	use WrappedComponent;
	use ReadyComponent;
	use InsertionMap;
	use WrappedResult;
}

final class WrappedFixedLeaf extends DependentLeaf implements Wrapped {
	use WrappedComponent;
	use Insertion;
	use WrappedDependentResult;
	use DependentLeafResult;
}

final class WrappedFixedLeafMap extends DependentLeaf implements Wrapped {
	use WrappedComponent;
	use InsertionMap;
	use WrappedDependentResult;
	use DependentLeafResult;
}

final class ActiveFragment extends Fragment {
	use InsertionStub;
	use ReadyFragment;
	use Result;
}

final class FixedFragment extends DependentFragment {
	use InsertionStub;
	use DependentResult;
	use DependentFragmentResult;
}

final class WrappedActiveFragment extends Fragment implements Wrapped {
	use WrappedComponent;
	use ReadyFragment;
	use InsertionStub;
	use WrappedResult;
}

final class WrappedFixedFragment extends DependentFragment implements Wrapped {
	use WrappedComponent;
	use InsertionStub;
	use WrappedDependentResult;
	use DependentFragmentResult;
}

final class Variator extends Variant {
	use ReadyVariant;
	use Result;
}

final class WrappedVariator extends Variant implements Wrapped {
	use WrappedComponent;
	use ReadyVariant;
	use WrappedResult;
}

final class Emulator extends Component implements Wrapped {
	public function attach(Component $c): void {}
	public function drop(): void {}
	public function isComponent(string $name): bool {return false;}
	public function getChild(string $class): Component {return $this;}
	public function getChildName(string $class): string|null {return null;}
	public function getChildNames(string $class): array {return [];}
	public function __call(string $name, array $value): bool {return false;}
	public function __get(string $name): Component {return $this;}
	public function __isset(string $name): bool {return false;}
	public function __unset(string $name): void {}
	public function __set(string $name, string|int|float $value): void {}
	public function getResult(): string {return '';}
	public function getRawResult(): string {return '';}
	public function isReady(): bool {return true;}
	public function insert(string $text): void {}
	public function unwrap(): void {}
	public function common(string $name, array|string|int|float $value): void {}
	public function ready(): void {}
	protected function notify(): void {}
	public function __toString(): string {return '';}
	public function force(string $name, string $text): bool {return true;}
}
