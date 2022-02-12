<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

	namespace dl\tt;

	interface \dl\tt\Wrapped;
	interface \dl\tt\Derivative;

	trait \dl\tt\Childless;
	trait \dl\tt\DependentComponent;
	trait \dl\tt\IndependentComponent;
	trait \dl\tt\ReadyComposite;
	trait \dl\tt\ReadyLeaf;
	trait \dl\tt\ReadyText;
	trait \dl\tt\ReadyVariant;
	trait \dl\tt\WrappedComponent;
	trait \dl\tt\RootComponent;
	trait \dl\tt\InsertionStub;
	trait \dl\tt\RawResult;
	trait \dl\tt\Result;
	trait \dl\tt\WrappedResult;
	trait \dl\tt\DependentInsert;
	trait \dl\tt\DependentCompositeResult;
	trait \dl\tt\DependentLeafResult;
	trait \dl\tt\DependentTextResult;
	trait \dl\tt\DependentResult;
	trait \dl\tt\WrappedDependentResult;
	trait \dl\tt\TextMaster;

	abstract class \dl\tt\Component;
	abstract class \dl\tt\Composite;
	abstract class \dl\tt\Variant;
	abstract class \dl\tt\Text;
	abstract class \dl\tt\DependentText;

	final class \dl\tt\Variator;
	final class \dl\tt\WrappedVariator;
	final class \dl\tt\OriginalText;
	final class \dl\tt\FixedText;
	final class \dl\tt\WrappedOriginalText;
	final class \dl\tt\WrappedFixedText;
	final class \dl\tt\Emulator;

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt;

interface Wrapped {
	public function unwrap(): void;
}

interface Derivative {
	public function getOriginal(): Component;
}

abstract class Component implements \dl\DirectCallable {
	final public const NS = '.';

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
			'_class' => 'Emulator',
			'_name'  => 'Emulator',
		]);
	}

	final public static function error(string $message, Code $code, bool $pro=false): void {
		if (Mode::Develop->current()) {
			throw new \dl\Failure(\dl\Error::log($message, $code));
		}
		elseif (Mode::Rebuild->current() || $pro) {
			\dl\Error::log($message, $code);
		}
	}
}
