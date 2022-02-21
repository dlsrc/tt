<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    class dl\tt\idle\Builder

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt\idle;

final class Builder extends \dl\tt\Builder {
	protected function prepareStacks(): void {
		$cfg   = \dl\tt\Config::get();
		$begin = $cfg->var_begin;
		$end   = $cfg->var_end;
		$refns = $cfg->refns;

		foreach (\array_keys($this->block) as $i) {
			if (0 == \preg_match_all($this->pattern['variable'], $this->block[$i], $matches, \PREG_SET_ORDER)) {
				continue;
			}

			$this->ref[$i] = [];
			$this->var[$i] = [];
			$search  = [];
			$replace = [];

			foreach ($matches as $match) {
				if (isset($match[4])) {
					$match[3] = $match[4];
				}

				if (!isset($this->var[$i][$match[1]])) {
					$this->var[$i][$match[1]] = $match[3]??'';
					$this->ref[$i][$match[1]] = $begin.$match[1].$end;
				}
				elseif (isset($match[3]) && '' == $this->var[$i][$match[1]]) {
					$this->var[$i][$match[1]] = $match[3];
				}

				if ($match[0] != $this->ref[$i][$match[1]]) {
					$search[]  = $match[0];
					$replace[] = $this->ref[$i][$match[1]];
				}
			}

			if (!empty($search)) {
				$this->block[$i] = \str_replace($search, $replace, $this->block[$i]);
			}
		}
	}

	protected function isTextComponent(int $id): bool {
		return !isset($this->ref[$id]);
	}

	protected function isMapComponent(int $id, string $prefix, bool $leaf): bool {
		foreach (\array_keys($this->var[$id]) as $name) {
			if (\str_contains($name, \dl\tt\Component::NS) && !\str_starts_with($name, \dl\tt\Component::NS)) {
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
			'_text'      => $this->block[$i],
			'_var'       => $this->var[$i],
			'_ref'       => $this->ref[$i],
			'_class'     => $this->id[$i],
			'_name'      => $this->names[$i],
			'_component' => $this->getComposition($i),
		]);
	}

	protected function buildWrappedOriginalComposite(int $i): void {
		$this->block[$i] = new $this->types[$i]([
			'_text'      => $this->block[$i],
			'_var'       => $this->var[$i],
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
			'_text'      => $this->block[$i],
			'_var'       => $this->var[$i],
			'_ref'       => $this->ref[$i],
			'_class'     => $this->id[$i],
			'_name'      => $this->names[$i],
			'_component' => $this->getComposition($i),
			'_exert'     => false,
		]);
	}

	protected function buildWrappedFixedComposite(int $i): void {
		$this->block[$i] = new $this->types[$i]([
			'_text'      => $this->block[$i],
			'_var'       => $this->var[$i],
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
			'_text'  => $this->block[$i],
			'_var'   => $this->var[$i],
			'_ref'   => $this->ref[$i],
			'_class' => $this->id[$i],
			'_name'  => $this->names[$i],
		]);
	}

	protected function buildWrappedOriginalLeaf(int $i): void {
		$this->block[$i] = new $this->types[$i]([
			'_text'   => $this->block[$i],
			'_var'    => $this->var[$i],
			'_ref'    => $this->ref[$i],
			'_class'  => $this->id[$i],
			'_name'   => $this->names[$i],
			'_before' => $this->before[$i],
			'_after'  => $this->after[$i],
		]);
	}

	protected function buildFixedLeaf(int $i): void {
		$this->block[$i] = new $this->types[$i]([
			'_text'  => $this->block[$i],
			'_var'   => $this->var[$i],
			'_ref'   => $this->ref[$i],
			'_class' => $this->id[$i],
			'_name'  => $this->names[$i],
			'_exert' => false,
		]);
	}

	protected function buildWrappedFixedLeaf(int $i): void {
		$this->block[$i] = new $this->types[$i]([
			'_text'   => $this->block[$i],
			'_var'    => $this->var[$i],
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
			'_text'      => $this->block[$i],
			'_var'       => $this->var[$i],
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
			'_text'   => $this->block[$i],
			'_var'    => $this->var[$i],
			'_ref'    => $this->ref[$i],
			'_class'  => $this->id[$i],
			'_name'   => $this->names[$i],
		]);
	}
}
