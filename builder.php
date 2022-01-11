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
namespace dl\markup;

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

	public static function make(string $file): Composite {
		if (!$c = Collector::make($file)) {
			return Composite::emulate();
		}

		$builder = new Builder($c);
		return $builder->build($file);
	}

	public function build(string $file): Composite {
		if ('' == $this->block[0]) {
			return Composite::emulate();
		}

		\dl\IO::fw($file.'.'.Config::RELEASE.'.'.Config::COLLECT, $this->block[0]);

		$this->selectGlobalVariables();
		$this->selectNamespaces();
		$this->buildStack();
		$this->selectReference();
		$this->prepareComposition();
		$this->buildComposition();
		return $this->block[0];
	}

	private function __construct(Collector $c) {
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
				'('.
					'([\.a-zA-Z_][\.a-zA-Z_0-9]*)'.
					'(?:=([^\}]+))?'.
				')'.
				\preg_quote($cfg->var_end, '/').
			'/s',

			'global' => '/'.
				\preg_quote($cfg->global_begin, '/').
				'([a-zA-Z_][a-zA-Z_0-9]*)'.
				'(?:=([^\]]+))?'.
				\preg_quote($cfg->global_end, '/').
			'/s',

			'block' => '/'.
				'<!--\s+'.
					'('.
						\preg_quote($cfg->driver, '/').
						'|'.
						\preg_quote($cfg->variant, '/').
						'|'.
					')'.
					'([a-zA-Z_][a-zA-Z_0-9]*)'.
					'(\\[(?:\#([a-z]+)\s+)?([^\#][^>]*|)\\])?'.
					'(?:\s*\:\s*([a-zA-Z_][a-zA-Z_0-9]*))?'.
				'\s+-->'.
				'(.+)'.
				'<!--\s+'.
					\preg_quote($cfg->block_end, '/').
				'\\2\s+-->'.
			'/Us',
		];

		$this->block    = [];
		$this->names    = [];
		$this->id       = [];
		$this->types    = [];
		$this->stack    = [];
		$this->ref      = [];
		$this->child    = [];
		$this->globs    = [];
		$this->before   = [];
		$this->after    = [];
		$this->size     = 0;
		$this->block[]  = $c->collect();
		$this->types[]  = $cfg->root;
		$this->names[]  = 'ROOT';
		$this->id[]     = 'ROOT';
		$this->before[] = '';
		$this->after[]  = '';
	}

	private function selectGlobalVariables(): void {
		$cfg   = Config::get();
		$begin = $cfg->global_begin;
		$end   = $cfg->global_end;

		if (\preg_match_all($this->pattern['global'], $this->block[0], $matches) > 0) {
			$matches[1] = \array_unique($matches[1]);

			foreach ($matches[1] as $key => &$match) {
				$match = $begin.$match.$end;
				$this->globs[$match] = $matches[2][$key];

				$this->block[0] = \str_replace(
					$matches[0][$key],
					$matches[1][$key],
					$this->block[0]
				);
			}
		}
	}

	private function selectNamespaces(): void {
		$cfg = Config::get();
		$trim = !$cfg->keep_spaces;
		$variant = $cfg->variant;
		$driver = $cfg->driver;
		$tag    = $cfg->wrap_tag;
		$class  = $cfg->wrap_class;

		$k = 1;

		for ($i = 0; isset($this->block[$i]); $i++) {
			if (\preg_match_all($this->pattern['block'], $this->block[$i], $matches, PREG_SET_ORDER) > 0) {
				foreach ($matches as $match) {
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
					elseif ('[]' == $match[3]) {
						$this->before[$k] = '<'.$tag.' class="'.$class.'">';
						$this->after[$k] = '</'.$tag.'>';
					}
					elseif ('' == $match[4]) {
						$this->before[$k] = '<'.$tag.' '.$match[5].'>';
						$this->after[$k] = '</'.$tag.'>';
					}
					else {
						$this->before[$k] = '<'.$match[4].' '.$match[5].'>';
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
		}

		$this->size = \sizeof($this->block);
	}

	private function buildStack(): void {
		$cfg = Config::get();
		$notrim = $cfg->keep_spaces;
		$begin  = $cfg->var_begin;
		$end    = $cfg->var_end;
		$refer  = $cfg->refer;
		$refns  = $cfg->refns;

		for ($i = 0; $i < $this->size; $i++) {
			if (\preg_match_all($this->pattern['variable'], $this->block[$i], $matches) > 0) {
				$this->stack[$i] = [];
				$html = '';
				$split = \explode($begin, $this->block[$i]);

				foreach ($split as $id => $str) {
					if (\str_contains($str, $end)) {
						$sub = \explode($end, $str);

						if (\in_array($sub[0], $matches[1])) {
							$key = \array_search($sub[0], $matches[1]);
			
							if ($notrim) {
								$this->stack[$i][] = $html;
							}
							elseif ('' != \trim($html)) {
								$this->stack[$i][] = $html;
							}

							unset($sub[0]);
							$html = \implode($end, $sub);

							if (isset($this->stack[$i][$matches[2][$key]])) {
								if ('' == $this->stack[$i][$matches[2][$key]]) {
									$this->stack[$i][$matches[2][$key]] = $matches[3][$key];
								}

								$this->stack[$i][] = $refer.$matches[2][$key];
							}
							else {
								$this->stack[$i][$matches[2][$key]] = $matches[3][$key];
							}
						}
						elseif (\strlen($sub[0]) > 1) {
							if (($refer == $sub[0][0]) || ($refns == $sub[0][0])) {
								if ($notrim) {
									$this->stack[$i][] = $html;
								}
								elseif ('' != \trim($html)) {
									$this->stack[$i][] = $html;
								}

								$this->stack[$i][] = \array_shift($sub);
								$html = \implode($end, $sub);
							}
							else {
								$html.= $begin.$str;
							}
						}
						else {
							$html.= $begin.$str;
						}
					}
					elseif (0 == $id) {
						$html.= $str;
					}
					else {
						$html.= $begin.$str;
					}
				}

				if ($notrim) {
					$this->stack[$i][] = $html;
				}
				elseif ('' != \trim($html)) {
					$this->stack[$i][] = $html;
				}
			}
			else {
				$this->stack[$i] = [$this->block[$i]];
			}
		}
	}

	private function selectReference(): void {
		$cfg = Config::get();
		$refer = $cfg->refer;
		$refns = $cfg->refns;

		for ($i = 0; $i < $this->size; $i++) {
			$this->ref[$i] = [];

			foreach ($this->stack[$i] as $key => $val) {
				if ('' == $val) {
					continue;
				}
				elseif ($refer == $val[0]) {
					$ref = \substr($val, 1);
				}
				elseif ($refns == $val[0]) {
					$ref = Composite::NS.\substr($val, 1);
				}
				else {
					continue;
				}

				$this->ref[$i][$key] = $ref;
			}
		}
	}

	private function prepareComposition(): void {
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
