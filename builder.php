<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    class dl\tt\Builder

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt;

abstract class Builder {
	abstract protected function prepareStacks(): void;
	abstract protected function findMap(int $id, string $prefix, bool $leaf): bool;
	abstract protected function buildOriginalComposite(int $i): void;
	abstract protected function buildWrappedOriginalComposite(int $i): void;
	abstract protected function buildFixedComposite(int $i): void;
	abstract protected function buildWrappedFixedComposite(int $i): void;
	abstract protected function buildOriginalLeaf(int $i): void;
	abstract protected function buildWrappedOriginalLeaf(int $i): void;
	abstract protected function buildFixedLeaf(int $i): void;
	abstract protected function buildWrappedFixedLeaf(int $i): void;
	abstract protected function buildComplex(int $i): void;
	abstract protected function buildDocument(int $i): void;

	protected Build $build;
	protected array $component;
	protected array $pattern;
	protected array $block;
	protected array $names;
	protected array $id;
	protected array $types;
	protected array $stack;
	protected array $ref;
	protected array $child;
	protected array $globs;
	protected array $before;
	protected array $after;

	public static function get(): Builder {
		$build = Build::now();
		$class = $build->builder();
		return new $class($build);
	}

	protected function __construct(Build $build) {
		$this->build = $build;
		$namespace = $this->build->ns();

		$this->component = [
			'complex'     => $namespace.'\\Complex',
			'document'    => $namespace.'\\Document',
			'a_comp'      => $namespace.'\\OriginalComposite',
			'a_comp_map'  => $namespace.'\\OriginalCompositeMap',
			'f_comp'      => $namespace.'\\FixedComposite',
			'f_comp_map'  => $namespace.'\\FixedCompositeMap',
			'wa_comp'     => $namespace.'\\WrappedOriginalComposite',
			'wa_comp_map' => $namespace.'\\WrappedOriginalCompositeMap',
			'wf_comp'     => $namespace.'\\WrappedFixedComposite',
			'wf_comp_map' => $namespace.'\\WrappedFixedCompositeMap',
			'a_leaf'      => $namespace.'\\OriginalLeaf',
			'a_leaf_map'  => $namespace.'\\OriginalLeafMap',
			'f_leaf'      => $namespace.'\\FixedLeaf',
			'f_leaf_map'  => $namespace.'\\FixedLeafMap',
			'wa_leaf'     => $namespace.'\\WrappedOriginalLeaf',
			'wa_leaf_map' => $namespace.'\\WrappedOriginalLeafMap',
			'wf_leaf'     => $namespace.'\\WrappedFixedLeaf',
			'wf_leaf_map' => $namespace.'\\WrappedFixedLeafMap',
			'a_text'      => __NAMESPACE__.'\\OriginalText',
			'f_text'      => __NAMESPACE__.'\\FixedText',
			'wa_text'     => __NAMESPACE__.'\\WrappedOriginalText',
			'wf_text'     => __NAMESPACE__.'\\WrappedFixedText',
			'variator'    => __NAMESPACE__.'\\Variator',
			'w_variator'  => __NAMESPACE__.'\\WrappedVariator',
		];

		$cfg = Config::get();

		$open  = \preg_quote($cfg->wrap_open, '/');
		$close = \preg_quote($cfg->wrap_close, '/');

		$this->pattern = [
			'variable' => '/'.
				\preg_quote($cfg->var_begin, '/').
				'\s*(['.\preg_quote($cfg->refns, '/').
				'\.A-Za-z][\.\w]*)(?:\s*\=(?-U)\s*(?U)(?:(\'|\"|\`)(.+)\\2|(.+)))?\s*'.
				\preg_quote($cfg->var_end, '/').
			'/U',

			'global' => '/'.
				\preg_quote($cfg->global_begin, '/').
				'\s*([A-Za-z]\w*)(?:\s*\=(?-U)\s*(?U)(?:(\'|\"|\`)(.+)\\2|(.+)))?\s*'.
				\preg_quote($cfg->global_end, '/').
			'/U',

			'block' => '/'.
				'<!--\s+('.\preg_quote($cfg->driver, '/').'|'.\preg_quote($cfg->variant, '/').'|)'.
					'([A-Za-z]\w*)'.
					'('.$open.'\s*'.$close.'|'.$open.'([^'.$close.']+)'.$close.'|)'.
					'(?:\s*\:\s*([A-Za-z_]\w*))?'.
				'\s+-->(.+)<!--\s+'.\preg_quote($cfg->block_end, '/').'\\2\s+-->'.
			'/Us',

			'wrap' => '/^([A-Za-z0-9]+)(\=\"|\s+|)/',
		];

		$this->block  = [];
		$this->names  = [];
		$this->id     = [];
		$this->types  = [];
		$this->stack  = [];
		$this->ref    = [];
		$this->child  = [];
		$this->globs  = [];
		$this->before = [];
		$this->after  = [];
	}

	public function build(string $tpl): Component {
		$this->block[0] = $tpl;

		if ('' == $this->block[0]) {
			return Component::emulate();
		}

		$this->types[]  = $this->build->ns().'\\'.Config::get()->root;
		$this->names[]  = 'ROOT';
		$this->id[]     = 'ROOT';
		$this->before[] = '';
		$this->after[]  = '';

		$this->prepareGlobalVars();
		$this->prepareDependencies();
		$this->prepareStacks();
		$this->prepareComponents();

		return $this->block[0];
	}

	protected function prepareGlobalVars(): void {
		if (0 == \preg_match_all($this->pattern['global'], $this->block[0], $matches, \PREG_SET_ORDER)) {
			return;
		}

		$cfg   = Config::get();
		$begin = $cfg->global_begin;
		$end   = $cfg->global_end;

		foreach ($matches as $match) {
			if (isset($match[4])) {
				$match[3] = $match[4];
			}

			$match[1] = $begin.$match[1].$end;

			if (!isset($this->globs[$match[1]])) {
				$this->globs[$match[1]] = $match[3]??'';
			}
			elseif (isset($match[3]) && '' == $this->globs[$match[1]]) {
				$this->globs[$match[1]] = $match[3];
			}

			if ($match[0] != $match[1]) {
				$this->block[0] = \str_replace(
					$match[0],
					$match[1],
					$this->block[0]
				);
			}
		}
	}

	protected function prepareDependencies(): void {
		$cfg     = Config::get();
		$trim    = !$cfg->keep_spaces;
		$variant = $cfg->variant;
		$driver  = $cfg->driver;
		$tag     = $cfg->wrap_tag;
		$class   = $cfg->wrap_class;
		$woc     = $cfg->wrap_open.$cfg->wrap_close;

		$k = 1;

		for ($i = 0; isset($this->block[$i]); $i++) {
			if (0 == \preg_match_all($this->pattern['block'], $this->block[$i], $matches, \PREG_SET_ORDER)) {
				continue;
			}

			foreach ($matches as $match) {
				if ($trim) {
					$this->block[$k] = \rtrim($match[6]);
				}
				else {
					$this->block[$k] = $match[6];
				}

				$this->names[$k] = $match[2];

				if (isset($match[4]) && '' != $match[4]) {
    				$match[4] = \trim($match[4]);

				    if (0 == \preg_match($this->pattern['wrap'], $match[4], $m)) {
						$this->before[$k] = '<'.$tag.' class="'.$class.'">';
						$this->after[$k]  = '</'.$tag.'>';
    				}
    				elseif ('="' == $m[2]) {
        				$this->before[$k] = '<'.$tag.' '.$match[4].'>';
        				$this->after[$k]  = '</'.$tag.'>';
    				}
    				elseif ('' == $m[2]) {
        				$this->before[$k] = '<'.$m[1].' class="'.$class.'">';
        				$this->after[$k]  = '</'.$m[1].'>';
    				}
    				else {
        				$this->before[$k] = '<'.$match[4].'>';
        				$this->after[$k]  = '</'.$m[1].'>';
    				}
				}
				elseif ('' == $match[3]) {
					$this->before[$k] = '';
					$this->after[$k]  = '';
				}
				else {
					$this->before[$k] = '<'.$tag.' class="'.$class.'">';
					$this->after[$k]  = '</'.$tag.'>';
				}

				if ('' == $match[5]) {
					$this->id[$k] = $match[2];
				}
				else {
					$this->id[$k] = $match[5];
				}
				
				if ('' == $this->before[$k]) {
					if ($variant == $match[1]) {
						$this->types[$k] = $this->component['variator'];
					}
					elseif ($driver == $match[1]) {
						$this->types[$k] = $this->component['f_comp'];
					}
					else {
						$this->types[$k] = $this->component['a_comp'];
					}
				}
				else {
					if ($variant == $match[1]) {
						$this->types[$k] = $this->component['w_variator'];
					}
					elseif ($driver == $match[1]) {
						$this->types[$k] = $this->component['wf_comp'];
					}
					else {
						$this->types[$k] = $this->component['wa_comp'];
					}
				}

				$this->block[$i] = \str_replace($match[0], '{'.Component::NS.$match[2].'}', $this->block[$i]);
				$this->child[$i][] = $k;
				$k++;
			}
		}
	}

	protected function identifyType(int $id, string $prefix): void {
		if ($leaf = !isset($this->child[$id][0])) {
			if (empty($this->ref[$id]['var']) && empty($this->ref[$id]['com']) && isset($this->stack[$id][0]) && 1 == \count($this->stack[$id])) {
				$comp = $prefix.'_text';
				$this->types[$id] = $this->component[$comp];
				return;
			}
		}

		if (!$this->findMap($id, $prefix, $leaf) && $leaf) {
			$comp = $prefix.'_leaf';
			$this->types[$id] = $this->component[$comp];
		}
	}

	protected function getComposition(int $i): array {
		$component = [];

		foreach ($this->child[$i] as $id) {
			$name = $this->block[$id]->getName();
			$component[$name] = $this->block[$id];
		}

		return $component;
	}

	protected function prepareComponents(): void {
		for ($i = \array_key_last($this->types); $i >= 0; $i--) {
			switch ($this->types[$i]) {
			case $this->component['a_comp']:
				$this->identifyType($i, 'a');
				break;

			case $this->component['wa_comp']:
				$this->identifyType($i, 'wa');
				break;

			case $this->component['f_comp']:
				$this->identifyType($i, 'f');
				break;

			case $this->component['wf_comp']:
				$this->identifyType($i, 'wf');
				break;

			case $this->component['complex']:
				if (!isset($this->child[$i][0])) {
					$this->types[$i] = $this->component['document'];
				}

				break;
			}

			switch ($this->types[$i]) {
			case $this->component['a_comp']:
			case $this->component['a_comp_map']:
				$this->buildOriginalComposite($i);
				break;

			case $this->component['wa_comp']:
			case $this->component['wa_comp_map']:
				$this->buildWrappedOriginalComposite($i);
				break;

			case $this->component['f_comp']:
			case $this->component['f_comp_map']:
				$this->buildFixedComposite($i);
				break;

			case $this->component['wf_comp']:
			case $this->component['wf_comp_map']:
				$this->buildWrappedFixedComposite($i);
				break;

			case $this->component['a_leaf']:
			case $this->component['a_leaf_map']:
				$this->buildOriginalLeaf($i);
				break;

			case $this->component['wa_leaf']:
			case $this->component['wa_leaf_map']:
				$this->buildWrappedOriginalLeaf($i);
				break;

			case $this->component['f_leaf']:
			case $this->component['f_leaf_map']:
				$this->buildFixedLeaf($i);
				break;

			case $this->component['wf_leaf']:
			case $this->component['wf_leaf_map']:
				$this->buildWrappedFixedLeaf($i);
				break;

			case $this->component['a_text']:
				$this->block[$i] = new $this->types[$i]([
					'_text'  => $this->stack[$i][0],
					'_class' => $this->id[$i],
					'_name'  => $this->names[$i],
				]);
				break;

			case $this->component['wa_text']:
				$this->block[$i] = new $this->types[$i]([
					'_text'   => $this->stack[$i][0],
					'_class'  => $this->id[$i],
					'_name'   => $this->names[$i],
					'_before' => $this->before[$i],
					'_after'  => $this->after[$i],
				]);
				break;

			case $this->component['f_text']:
				$this->block[$i] = new $this->types[$i]([
					'_text'  => $this->stack[$i][0],
					'_class' => $this->id[$i],
					'_name'  => $this->names[$i],
					'_exert' => false,
				]);
				break;

			case $this->component['wf_text']:
				$this->block[$i] = new $this->types[$i]([
					'_text'   => $this->stack[$i][0],
					'_class'  => $this->id[$i],
					'_name'   => $this->names[$i],
					'_before' => $this->before[$i],
					'_after'  => $this->after[$i],
					'_exert'  => false,
				]);
				break;

			case $this->component['variator']:
				if (isset($this->child[$i][0])) {
					$this->block[$i] = new $this->types[$i]([
						'_class'     => $this->id[$i],
						'_name'      => $this->names[$i],
						'_component' => $this->getComposition($i),
						'_variant'   => $this->names[$this->child[$i][0]],
					]);
				}
				else {
					$this->block[$i] = Component::emulate();
				}
				break;

			case $this->component['w_variator']:
				if (isset($this->child[$i][0])) {
					$this->block[$i] = new $this->types[$i]([
						'_class'     => $this->id[$i],
						'_name'      => $this->names[$i],
						'_before'    => $this->before[$i],
						'_after'     => $this->after[$i],
						'_component' => $this->getComposition($i),
						'_variant'   => $this->names[$this->child[$i][0]],
					]);
				}
				else {
					$this->block[$i] = Component::emulate();
				}
				break;

			case $this->component['complex']:
				$this->buildComplex($i);
				break;

			case $this->component['document']:
				$this->buildDocument($i);
				break;

			default:
				$this->block[$i] = Component::emulate();
			}
		}
	}
}
