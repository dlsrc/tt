<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    class dl\tt\lite\Builder

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt\lite;

final class Builder extends \dl\tt\Builder {
	protected array $stack;

	protected function __construct(\dl\tt\Build $build) {
		parent::__construct($build);
		$this->stack  = [];
	}

	protected function prepareStacks(): void {
		$refns  = \dl\tt\Config::get()->refns;

		foreach (\array_keys($this->block) as $i) {
			$key = 0;
			$this->ref[$i] = [];

			if (0 == \preg_match_all($this->pattern['variable'], $this->block[$i], $matches, \PREG_SET_ORDER)) {
//				$this->stack[$i] = [$this->block[$i]];
				continue;
			}

			$split = \preg_split($this->pattern['variable'], $this->block[$i]);

			foreach ($matches as $id => $match) {
				if ('' != \trim($split[$id])) {
					$this->stack[$i][$key] = $split[$id];
					$key++;
				}

				if (isset($match[4])) {
					$match[3] = $match[4];
				}

				if (\str_starts_with($match[1], $refns)) {
					$match[1] = \dl\tt\Component::NS.\substr($match[1], 1);
				}

				if (!isset($this->stack[$i][$match[1]])) {
					$this->stack[$i][$match[1]] = $match[3]??'';
				}
				else {
					if (isset($match[3]) && '' == $this->stack[$i][$match[1]]) {
						$this->stack[$i][$match[1]] = $match[3];
					}

					$this->stack[$i][$key] = $this->stack[$i][$match[1]];
					$this->ref[$i][$key] = $match[1];
					$key++;
				}
			}

			$id++;

			if ('' != \trim($split[$id])) {
				$this->stack[$i][$key] = $split[$id];
			}
		}
	}

	protected function isTextComponent(int $id): bool {
		return !isset($this->stack[$id]);
	}

	protected function isMapComponent(int $id, string $prefix, bool $leaf): bool {
		foreach (\array_keys($this->stack[$id]) as $var) {
			if (\is_string($var) && \str_contains($var, \dl\tt\Component::NS) && \dl\tt\Component::NS != $var[0]) {
				if ($leaf) {
					$comp = $prefix.'_leaf_map';
				}
				else {
					$comp = $prefix.'_comp_map';
				}

				$this->types[$id] = $this->component[$comp];
				return true;
			}
		}

		return false;
	}

	protected function buildOriginalComposite(int $i): void {
		$this->block[$i] = new $this->types[$i]([
			'_chain'     => $this->stack[$i],
			'_ref'       => $this->ref[$i],
			'_class'     => $this->id[$i],
			'_name'      => $this->names[$i],
			'_component' => $this->getComposition($i),
		]);
	}

	protected function buildWrappedOriginalComposite(int $i): void {
		$this->block[$i] = new $this->types[$i]([
			'_chain'     => $this->stack[$i],
			'_ref'       => $this->ref[$i],
			'_class'     => $this->id[$i],
			'_name'      => $this->names[$i],
			'_before'    => $this->before[$i],
			'_after'     => $this->after[$i],
			'_component' => $this->getComposition($i),
		]);
	}

	protected function buildFixedComposite(int $i): void {
		$this->block[$i] = new $this->types[$i]([
			'_chain'     => $this->stack[$i],
			'_ref'       => $this->ref[$i],
			'_class'     => $this->id[$i],
			'_name'      => $this->names[$i],
			'_component' => $this->getComposition($i),
			'_exert'     => false,
		]);
	}

	protected function buildWrappedFixedComposite(int $i): void {
		$this->block[$i] = new $this->types[$i]([
			'_chain'     => $this->stack[$i],
			'_ref'       => $this->ref[$i],
			'_class'     => $this->id[$i],
			'_name'      => $this->names[$i],
			'_before'    => $this->before[$i],
			'_after'     => $this->after[$i],
			'_component' => $this->getComposition($i),
			'_exert'     => false,
		]);
	}

	protected function buildOriginalLeaf(int $i): void {
		$this->block[$i] = new $this->types[$i]([
			'_chain'  => $this->stack[$i],
			'_ref'    => $this->ref[$i],
			'_class'  => $this->id[$i],
			'_name'   => $this->names[$i],
		]);
	}

	protected function buildWrappedOriginalLeaf(int $i): void {
		$this->block[$i] = new $this->types[$i]([
			'_chain'  => $this->stack[$i],
			'_ref'    => $this->ref[$i],
			'_class'  => $this->id[$i],
			'_name'   => $this->names[$i],
			'_before' => $this->before[$i],
			'_after'  => $this->after[$i],
		]);
	}

	protected function buildFixedLeaf(int $i): void {
		$this->block[$i] = new $this->types[$i]([
			'_chain'  => $this->stack[$i],
			'_ref'    => $this->ref[$i],
			'_class'  => $this->id[$i],
			'_name'   => $this->names[$i],
			'_exert'  => false,
		]);
	}

	protected function buildWrappedFixedLeaf(int $i): void {
		$this->block[$i] = new $this->types[$i]([
			'_chain'  => $this->stack[$i],
			'_ref'    => $this->ref[$i],
			'_class'  => $this->id[$i],
			'_name'   => $this->names[$i],
			'_before' => $this->before[$i],
			'_after'  => $this->after[$i],
			'_exert'  => false,
		]);
	}

	protected function buildComplex(int $i): void {
		$cfg = \dl\tt\Config::get();
		$this->block[$i] = new $this->types[$i]([
			'_chain'     => $this->stack[$i],
			'_ref'       => $this->ref[$i],
			'_class'     => $this->id[$i],
			'_name'      => $this->names[$i],
			'_component' => $this->getComposition($i),
			'_global'    => $this->globs,
			'_first'     => $cfg->global_begin,
			'_last'      => $cfg->global_end,
		]);
	}

	protected function buildDocument(int $i): void {
		$this->block[$i] = new $this->types[$i]([
			'_chain'  => $this->stack[$i],
			'_ref'    => $this->ref[$i],
			'_class'  => $this->id[$i],
			'_name'   => $this->names[$i],
		]);
	}
}
