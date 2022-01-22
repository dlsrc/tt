<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    class dl\markup\Builder

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
	private array $child;
	private int   $size;
	private array $globs;
	private array $before;
	private array $after;

	public function build(string $tpl): Component {
		$this->block[0] = $tpl;

		if ('' == $this->block[0]) {
			return Composite::emulate();
		}

		$this->types[]  = 'ROOT';
		$this->names[]  = 'ROOT';
		$this->id[]     = 'ROOT';
		$this->before[] = '';
		$this->after[]  = '';

		$this->prepareGlobalVars();
		$this->prepareDependencies();
		$this->prepareStacks();
		$this->prepareComponents();
		$this->buildComposition();

		return $this->block[0];
	}

	private function __construct() {
		$cfg = Config::get();

		$this->component = [
			'component'  => __NAMESPACE__.'\\Component',
			'componentm' => __NAMESPACE__.'\\ComponentM',
			'drive'      => __NAMESPACE__.'\\Drive',
			'drivem'     => __NAMESPACE__.'\\DriveM',
			'document'   => __NAMESPACE__.'\\Document',
			'variator'   => __NAMESPACE__.'\\Variator'
		];

		$this->pattern = [
			'variable' => '/'.
				\preg_quote($cfg->var_begin, '/').
				'\s*(['.\preg_quote($cfg->refns, '/').'\.A-Za-z][\.\w]*)(?:\s*\=(?-U)\s*(?U)(?:(\'|\"|\`)(.+)\\2|(.+)))?\s*'.
				\preg_quote($cfg->var_end, '/').
			'/U',

			'global' => '/'.
				\preg_quote($cfg->global_begin, '/').
				'\s*([A-Za-z]\w*)(?:\s*\=(?-U)\s*(?U)(?:(\'|\"|\`)(.+)\\2|(.+)))?\s*'.
				\preg_quote($cfg->global_end, '/').
			'/U',

			'block' => '/'.
				'<!--\s+('.\preg_quote($cfg->driver, '/').'|'.\preg_quote($cfg->variant, '/').'|)'.
					'([A-Za-z]\w*)(<>|<([a-z1-6]+)>|<([a-z1-6]+)\s+[^>]+>|)(?:\s*\:\s*([A-Za-z_]\w*))?'.
				'\s+-->(.+)<!--\s+'.\preg_quote($cfg->block_end, '/').'\\2\s+-->'.
			'/Us',
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
		$this->size   = 0;
	}

	private function prepareGlobalVars(): void {
		if (0 == \preg_match_all($this->pattern['global'], $this->block[0], $matches, \PREG_SET_ORDER)) {
			return;
		}

		$cfg   = Config::get();
		$begin = $cfg->global_begin;
		$end   = $cfg->global_end;

		foreach ($matches as $key => $match) {
			if (isset($match[4])) {
				$match[3] = $match[4];
			}

			if (!isset($this->globs[$match[1]])) {
				$this->globs[$match[1]] = $match[3]??'';
			}
			elseif (isset($match[3]) && '' == $this->globs[$match[1]]) {
				$this->globs[$match[1]] = $match[3];
			}

			if ($match[0] != $begin.$match[1].$end) {
				$this->block[0] = \str_replace(
					$match[0],
					$begin.$match[1].$end,
					$this->block[0]
				);
			}
		}
	}

	private function prepareDependencies(): void {
		$cfg = Config::get();
		$trim = !$cfg->keep_spaces;
		$variant = $cfg->variant;
		$driver = $cfg->driver;
		$tag    = $cfg->wrap_tag;
		$class  = $cfg->wrap_class;

		$k = 1;

		for ($i = 0; isset($this->block[$i]); $i++) {
			if (0 == \preg_match_all($this->pattern['block'], $this->block[$i], $matches, \PREG_SET_ORDER)) {
				continue;
			}

			foreach ($matches as $key => $match) {
				if ($trim) {
					$this->block[$k] = \rtrim($match[7]);
				}
				else {
					$this->block[$k] = $match[7];
				}

				$this->names[$k] = $match[2];

				if ('' == $match[3]) {
					$this->before[$k] = '';
					$this->after[$k] = '';
				}
				elseif ('<>' == $match[3]) {
					$this->before[$k] = '<'.$tag.' class="'.$class.'">';
					$this->after[$k] = '</'.$tag.'>';
				}
				elseif ('' == $match[4]) {
					$this->before[$k] = $match[3];
					$this->after[$k] = '</'.$match[5].'>';
				}
				else {
					$this->before[$k] = '<'.$match[4].' class="'.$class.'">';
					$this->after[$k] = '</'.$match[4].'>';
				}

				if ('' == $match[6]) {
					$this->id[$k] = $match[2];
				}
				else {
					$this->id[$k] = $match[6];
				}

				if ($variant == $match[1]) {
					$this->types[$k] = $this->component['variator'];
				}
				elseif ($driver == $match[1]) {
					$this->types[$k] = $this->component['drive'];
				}
				else {
					$this->types[$k] = $this->component['component'];
				}

				$this->block[$i] = \str_replace($match[0], '{'.Composite::NS.$match[2].'}', $this->block[$i]);
				$this->child[$i][] = $k;
				$k++;
			}
		}

		$this->size = \sizeof($this->block);
	}

	private function prepareStacks(): void {
		//$cfg = Config::get();
		//$notrim = $cfg->keep_spaces;
		//$begin  = $cfg->var_begin;
		//$end    = $cfg->var_end;
		//$refer  = $cfg->refer;
		//$refns  = $cfg->refns;
		$refns  = Config::get()->refns;

		for ($i = 0; $i < $this->size; $i++) {
			$key = 0;
			$this->ref[$i] = [];

			if (0 == \preg_match_all($this->pattern['variable'], $this->block[$i], $matches, \PREG_SET_ORDER)) {
				$this->stack[$i] = [$this->block[$i]];
				continue;
			}

			$split = \preg_split($this->pattern['variable'], $this->block[$i]);

			foreach ($matches as $id => $match) {
				if ('' != $split[$id]) {
					$this->stack[$i][$key] = $split[$id];
					$key++;
				}

				if (isset($match[4])) {
					$match[3] = $match[4];
				}

				if (\str_starts_with($match[1], $refns)) {
					$match[1] = Composite::NS.\substr($match[1], 1);
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

			if ('' != $split[$id]) {
				$this->stack[$i][$key] = $split[$id];
			}
		}
	}

	private function prepareComponents(): void {
		$cfg = Config::get();

		for ($i = 0; $i < $this->size; $i++) {
			switch ($this->types[$i]) {

			case $this->component['component']:
				foreach (\array_keys($this->stack[$i]) as $var) {
					if (\is_string($var) && \str_contains($var, Composite::NS) && Composite::NS != $var[0]) {
						$this->types[$i] = $this->component['componentm'];
						break; // foreach break
					}
				}

				$this->block[$i] = new $this->types[$i]([
					'_stack'  => $this->stack[$i],
					'_ref'    => $this->ref[$i],
					'_class'  => $this->id[$i],
					'_name'   => $this->names[$i],
					'_before' => $this->before[$i],
					'_after'  => $this->after[$i],
					'_child'  => [],
					'_size'   => 0,
					'_result' => ''
				]);
				break;

			case $this->component['drive']:
				foreach (\array_keys($this->stack[$i]) as $var) {
					if (\is_string($var) && \str_contains($var, Composite::NS) && Composite::NS != $var[0]) {
						$this->types[$i] = $this->component['drivem'];
						break;
					}
				}

				$this->block[$i] = new $this->types[$i]([
					'_stack'  => $this->stack[$i],
					'_ref'    => $this->ref[$i],
					'_class'  => $this->id[$i],
					'_name'   => $this->names[$i],
					'_before' => $this->before[$i],
					'_after'  => $this->after[$i],
					'_child'  => [],
					'_size'   => 0,
					'_exert'  => false,
					'_result' => ''
				]);
				break;

			case $this->component['variator']:
				if (isset($this->child[$i][0])) {
					$this->block[$i] = new $this->types[$i]([
						'_class'   => $this->id[$i],
						'_name'    => $this->names[$i],
						'_before'  => $this->before[$i],
						'_after'   => $this->after[$i],
						'_child'   => [],
						'_size'    => 0,
						'_variant' => $this->names[$this->child[$i][0]],
						'_result'  => ''
					]);
				}
				else {
					$this->block[$i] = Component::emulate();
				}
				break;

			case $this->component['document']:
				$this->block[$i] = new $this->types[$i]([
					'_stack'   => $this->stack[$i],
					'_ref'     => $this->ref[$i],
					'_class'   => $this->id[$i],
					'_name'    => $this->names[$i],
					'_before'  => '',
					'_after'   => '',
					'_child'   => [],
					'_size'    => 0,
					'_general' => $this->globs,
					'_first'   => $cfg->global_begin,
					'_last'    => $cfg->global_end,
					'_pat'     => [],
					'_rep'     => [],
					'_str'     => [],
					'_result'  => ''
				]);
				break;

			default:
				$this->block[$i] = new Emulator([
					'_stack'   => [],
					'_refer'   => [],
					'_child'   => [],
					'_csid'    => 'EMULATOR',
					'_name'    => 'EMULATOR',
					'_open'    => '',
					'_close'   => '',
					'_result'  => ''
				]);
			}
		}
	}

	private function buildComposition(): void {
		for ($i = 0; $i < $this->size; $i++) {
			if (isset($this->child[$i])) {
				foreach ($this->child[$i] as $ob) {
					$this->block[$i]->attach($this->block[$ob]);
				}
			}
		}
	}
}
