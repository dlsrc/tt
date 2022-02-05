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

final class Builder {
	private array $component;
	private array $pattern;
	private array $block;
	private array $names;
	private array $id;
	private array $types;
	private array $stack;
	private array $ref;
	private array $var;
	private array $child;
	private array $globs;
	private array $before;
	private array $after;

	public function build(string $tpl): Component {
		$this->block[0] = $tpl;

		if ('' == $this->block[0]) {
			return Component::emulate();
		}

		$this->types[]  = Config::get()->root;
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

	public function __construct() {
		$cfg = Config::get();

		$this->component = [
			'a_comp'      => __NAMESPACE__.'\\ActiveComposite',
			'a_comp_map'  => __NAMESPACE__.'\\ActiveCompositeMap',
			'f_comp'      => __NAMESPACE__.'\\FixedComposite',
			'f_comp_map'  => __NAMESPACE__.'\\FixedCompositeMap',
			'wa_comp'     => __NAMESPACE__.'\\WrappedActiveComposite',
			'wa_comp_map' => __NAMESPACE__.'\\WrappedActiveCompositeMap',
			'wf_comp'     => __NAMESPACE__.'\\WrappedFixedComposite',
			'wf_comp_map' => __NAMESPACE__.'\\WrappedFixedCompositeMap',
			'a_leaf'      => __NAMESPACE__.'\\ActiveLeaf',
			'a_leaf_map'  => __NAMESPACE__.'\\ActiveLeafMap',
			'f_leaf'      => __NAMESPACE__.'\\FixedLeaf',
			'f_leaf_map'  => __NAMESPACE__.'\\FixedLeafMap',
			'wa_leaf'     => __NAMESPACE__.'\\WrappedActiveLeaf',
			'wa_leaf_map' => __NAMESPACE__.'\\WrappedActiveLeafMap',
			'wf_leaf'     => __NAMESPACE__.'\\WrappedFixedLeaf',
			'wf_leaf_map' => __NAMESPACE__.'\\WrappedFixedLeafMap',
			'a_text'      => __NAMESPACE__.'\\ActiveText',
			'f_text'      => __NAMESPACE__.'\\FixedText',
			'wa_text'     => __NAMESPACE__.'\\WrappedActiveText',
			'wf_text'     => __NAMESPACE__.'\\WrappedFixedText',
			'variator'    => __NAMESPACE__.'\\Variator',
			'w_variator'  => __NAMESPACE__.'\\WrappedVariator',
			'document'    => __NAMESPACE__.'\\Document',
			'complex'     => __NAMESPACE__.'\\Complex',
		];

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
		$this->var    = [];
		$this->child  = [];
		$this->globs  = [];
		$this->before = [];
		$this->after  = [];
	}

	private function prepareGlobalVars(): void {
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

	private function prepareDependencies(): void {
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

	private function prepareStacks(): void {
		$refns  = Config::get()->refns;

		foreach (\array_keys($this->block) as $i) {
			$key = 0;
			$this->ref[$i] = [];
			$this->ref[$i]['var'] = [];
			$this->ref[$i]['com'] = [];
			$this->var[$i] = [];

			if (0 == \preg_match_all($this->pattern['variable'], $this->block[$i], $matches, \PREG_SET_ORDER)) {
				$this->stack[$i] = [$this->block[$i]];
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
					$match[1] = Component::NS.\substr($match[1], 1);
					$var = false;
				}
				elseif (\str_starts_with($match[1], Component::NS)) {
					$var = false;
				}
				else {
					$var = true;
				}

				if ($var) {
					if (!isset($this->var[$i][$match[1]])) {
						$this->var[$i][$match[1]] = $match[3]??'';
						$this->stack[$i][$key] = '';
						$this->ref[$i]['var'][$key] = $match[1];
					}
					else {
						if (isset($match[3]) && '' == $this->var[$i][$match[1]]) {
							$this->var[$i][$match[1]] = $match[3];
						}

						$this->stack[$i][$key] = '';
						$this->ref[$i]['var'][$key] = $match[1];
					}

					$key++;
				}
				else {
					if (!isset($this->stack[$i][$match[1]])) {
						$this->stack[$i][$match[1]] = '';
					}
					else {
						$this->stack[$i][$key] = $this->stack[$i][$match[1]];
						$this->ref[$i]['com'][$key] = $match[1];
						$key++;
					}
				}
			}

			$id++;

			if ('' != \trim($split[$id])) {
				$this->stack[$i][$key] = $split[$id];
			}
		}
	}

	private function identifyType(int $id, string $leaf, string $fragment): void {
		if (!isset($this->child[$id][0])) {
			if (empty($this->ref[$id]['var']) && empty($this->ref[$id]['com']) && isset($this->stack[$id][0]) && 1 == \count($this->stack[$id])) {
				$this->types[$id] = $this->component[$fragment];
				return;
			}

			$this->types[$id] = $this->component[$leaf];
		}

		foreach (\array_keys($this->stack[$id]) as $var) {
			if (\is_string($var) && \str_contains($var, Component::NS) && Component::NS != $var[0]) {
				$this->types[$id] = $this->types[$id].'Map';
				break;
			}
		}
	}

	private function getComposition(int $i): array {
		$component = [];

		foreach ($this->child[$i] as $id) {
			$name = $this->block[$id]->getName();
			$component[$name] = $this->block[$id];
		}

		return $component;
	}

	private function prepareComponents(): void {
		$cfg = Config::get();

		for ($i = \array_key_last($this->types); $i >= 0; $i--) {
			switch ($this->types[$i]) {
			case $this->component['a_comp']:
				$this->identifyType($i, 'a_leaf', 'a_text');
				break;

			case $this->component['wa_comp']:
				$this->identifyType($i, 'wa_leaf', 'wa_text');
				break;

			case $this->component['f_comp']:
				$this->identifyType($i, 'f_leaf', 'f_text');
				break;

			case $this->component['wf_comp']:
				$this->identifyType($i, 'wf_leaf', 'wf_text');
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

				$this->block[$i] = new $this->types[$i]([
					'_chain'     => $this->stack[$i],
					'_var'       => $this->var[$i],
					'_ref'       => $this->ref[$i]['var'],
					'_child'     => $this->ref[$i]['com'],
					'_class'     => $this->id[$i],
					'_name'      => $this->names[$i],
					'_component' => $this->getComposition($i),
				]);
				break;

			case $this->component['wa_comp']:
			case $this->component['wa_comp_map']:

				$this->block[$i] = new $this->types[$i]([
					'_chain'     => $this->stack[$i],
					'_var'       => $this->var[$i],
					'_ref'       => $this->ref[$i]['var'],
					'_child'     => $this->ref[$i]['com'],
					'_class'     => $this->id[$i],
					'_name'      => $this->names[$i],
					'_before'    => $this->before[$i],
					'_after'     => $this->after[$i],
					'_component' => $this->getComposition($i),
				]);
				break;

			case $this->component['f_comp']:
			case $this->component['f_comp_map']:

				$this->block[$i] = new $this->types[$i]([
					'_chain'     => $this->stack[$i],
					'_var'       => $this->var[$i],
					'_ref'       => $this->ref[$i]['var'],
					'_child'     => $this->ref[$i]['com'],
					'_class'     => $this->id[$i],
					'_name'      => $this->names[$i],
					'_component' => $this->getComposition($i),
					'_exert'     => false,
				]);
				break;

			case $this->component['wf_comp']:
			case $this->component['wf_comp_map']:

				$this->block[$i] = new $this->types[$i]([
					'_chain'     => $this->stack[$i],
					'_var'       => $this->var[$i],
					'_ref'       => $this->ref[$i]['var'],
					'_child'     => $this->ref[$i]['com'],
					'_class'     => $this->id[$i],
					'_name'      => $this->names[$i],
					'_before'    => $this->before[$i],
					'_after'     => $this->after[$i],
					'_component' => $this->getComposition($i),
					'_exert'     => false,
				]);
				break;

			case $this->component['a_leaf']:
			case $this->component['a_leaf_map']:

				$this->block[$i] = new $this->types[$i]([
					'_chain' => $this->stack[$i],
					'_var'   => $this->var[$i],
					'_ref'   => $this->ref[$i]['var'],
					'_class' => $this->id[$i],
					'_name'  => $this->names[$i],
				]);
				break;

			case $this->component['wa_leaf']:
			case $this->component['wa_leaf_map']:

				$this->block[$i] = new $this->types[$i]([
					'_chain'  => $this->stack[$i],
					'_var'    => $this->var[$i],
					'_ref'    => $this->ref[$i]['var'],
					'_class'  => $this->id[$i],
					'_name'   => $this->names[$i],
					'_before' => $this->before[$i],
					'_after'  => $this->after[$i],
				]);
				break;

			case $this->component['f_leaf']:
			case $this->component['f_leaf_map']:

				$this->block[$i] = new $this->types[$i]([
					'_chain' => $this->stack[$i],
					'_var'   => $this->var[$i],
					'_ref'   => $this->ref[$i]['var'],
					'_class' => $this->id[$i],
					'_name'  => $this->names[$i],
					'_exert' => false,
				]);
				break;

			case $this->component['wf_leaf']:
			case $this->component['wf_leaf_map']:

				$this->block[$i] = new $this->types[$i]([
					'_chain'  => $this->stack[$i],
					'_var'    => $this->var[$i],
					'_ref'    => $this->ref[$i]['var'],
					'_class'  => $this->id[$i],
					'_name'   => $this->names[$i],
					'_before' => $this->before[$i],
					'_after'  => $this->after[$i],
					'_exert'  => false,
				]);
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
				$this->block[$i] = new $this->types[$i]([
					'_chain'     => $this->stack[$i],
					'_var'       => $this->var[$i],
					'_ref'       => $this->ref[$i]['var'],
					'_child'     => $this->ref[$i]['com'],
					'_class'     => $this->id[$i],
					'_name'      => $this->names[$i],
					'_component' => $this->getComposition($i),
					'_global'    => $this->globs,
					'_first'     => $cfg->global_begin,
					'_last'      => $cfg->global_end,
				]);

				break;

			case $this->component['document']:
				$this->block[$i] = new $this->types[$i]([
					'_chain'  => $this->stack[$i],
					'_var'    => $this->var[$i],
					'_ref'    => $this->ref[$i]['var'],
					'_child'  => $this->ref[$i]['com'],
					'_class'  => $this->id[$i],
					'_name'   => $this->names[$i],
					'_global' => $this->globs,
					'_first'  => $cfg->global_begin,
					'_last'   => $cfg->global_end,
				]);

				break;

			default:
				$this->block[$i] = new Emulator([
					'_class' => 'Emulator',
					'_name'  => 'Emulator',
				]);
			}
		}
	}
}
