<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    class dl\tt\Collector

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt;

class Collector {
	private array  $block;
	private string $dirtpl;
	private array  $markup;
	private string $page;
	private array  $pattern;
	private array  $snip;

	public static function make(string $file): Collector|null {
		if (!$tpl = \file_get_contents($file)) {
			return null;
		}

		return new Collector($tpl, $file);
	}

	public function collect(): string {
		if (!$this->makeTemplate()) {
			return $this->page;
		}

		$cfg = Config::get();
		$auto = $cfg->auto_class;
		$asis = $cfg->asis;
		$wrap = $cfg->wrap;
		$open = $cfg->open;
		
		$copy  = [];
		$clone = [];

		foreach ($this->markup as $name => $markup) {
			if (!isset($this->snip[$markup[3]])) {
				if ($markup[3] != $markup[2]) {
					$copy[$name] = $markup[3];
				}

				continue;
			}

			$tpl = [];

			foreach ($this->snip[$markup[3]] as $block) {
				$tpl[$block] = $this->block[$block][4];
				$path = $this->block[$block][5];

				if (isset($markup[4][$path])) {
					foreach ($markup[4][$path] as $class => $attrs) {
						$tpl[$block] = \str_replace('{:'.$class.':}', $attrs, $tpl[$block]);
					}
				}

				if (isset($this->block[$block][6])) {
					foreach ($this->block[$block][6] as $key => $val) {
						$tpl[$block] = \str_replace($key, $tpl[$val], $tpl[$block]);
					}
				}

				if ($this->block[$block][5]) { // leaf
					if (isset($markup[6]) && isset($markup[6][$path])) {// CUT block
						$tpl[$block] = '';
					}
					elseif ($this->block[$block][3]) {
						$tpl[$block] =
						'<!-- '.$this->block[$block][1].$this->block[$block][2].' : '.$this->block[$block][3].' -->'.
						$tpl[$block].
						'<!-- ~'.$this->block[$block][2].' -->';
					}
					else {
						$tpl[$block] =
						'<!-- '.$this->block[$block][1].$this->block[$block][2].' -->'.
						$tpl[$block].
						'<!-- ~'.$this->block[$block][2].' -->';
					}
				}		
			}

			$block = $markup[3]; // root
			
			if (isset($markup[6]) && !empty($markup[6])) {// CUT class
				if (isset($markup[7])) {
					$blk_cls = ' : '.$markup[7];
				}
				else {
					$blk_cls = ' : '.$markup[2];
				}
			}
			elseif ($this->block[$block][3]) {
				$blk_cls = ' : '.$this->block[$block][3];
			}
			elseif ($auto) {
				$blk_cls = ' : '.$this->block[$block][2];
			}
			else {
				$blk_cls = '';
			}

			if (empty($markup[4])) {
				$begin = '<!-- '.$this->block[$block][1].$markup[2].$blk_cls.' -->';
				$tpl[$block] = \preg_replace($this->pattern['clean'], '', $tpl[$block]);
			}
			else {
				$begin =
				'<!-- '.
				$markup[4][$open].
				$this->block[$block][1].
				$markup[2].
				$markup[4][$wrap].
				$blk_cls.
				' -->';

				if ($markup[4][$asis]) {
					$tpl[$block] = \preg_replace($this->pattern['clean'], ' class="\\1"', $tpl[$block]);
				}
				else {
					$tpl[$block] = \preg_replace($this->pattern['clean'], '', $tpl[$block]);
				}
			}

			if ($block != $markup[2]) {
				$clone[$markup[2]] = $tpl[$block]; // copy
			}

			$tpl[$block] = $begin.$tpl[$block].'<!-- ~'.$markup[2].' -->';

			if ($markup[1]) {
				$tpl[$block] = \preg_replace('[^(.+)$]m', $markup[1].'\\1', $tpl[$block]);
			}

			$this->page = \str_replace($markup[0], $tpl[$block], $this->page);
		}

		foreach ($copy as $name => $src) { // clones
			if (!isset($this->markup[$src]) || !isset($clone[$src])) {
				continue;
			}

			$markup = $this->markup[$name];
			$source = $this->markup[$src];
			$block  = $source[3];
			$tmp    = $clone[$src];

			if (isset($source[6]) && !empty($source[6])) {// CUT class
				if (isset($source[7])) {
					$blk_cls = ' : '.$source[7];
				}
				else {
					$blk_cls = ' : '.$source[2];
				}
			}
			elseif ($this->block[$block][3]) {
				$blk_cls = ' : '.$this->block[$block][3];
			}
			elseif ($auto) {
				$blk_cls = ' : '.$this->block[$block][2];
			}
			else {
				$blk_cls = '';
			}

			if (empty($source[4])) {
				$begin = '<!-- '.$this->block[$block][1].$markup[2].$blk_cls.' -->';
			}
			else {
				$begin = '<!-- '.$source[4]['OPEN'].$this->block[$block][1].$markup[2].$source[4]['WRAP'].$blk_cls.' -->';
			}

			$tmp = $begin.$tmp.'<!-- ~'.$markup[2].' -->';

			if ($markup[1]) {
				$tmp = \preg_replace('[^(.+)$]m', $markup[1].'\\1', $tmp);
			}

			$this->page = \str_replace($markup[0], $tmp, $this->page);
		}

		return $this->page;
	}

	private function __construct(string $page, string $file) {
		$this->page    = $page;
		$this->dirtpl  = \strtr(\dirname(\realpath($file)),'\\', '/').'/';
		$this->block   = [];
		$this->markup  = [];
		$this->snip    = [];
		$this->pattern = [];

		$cfg = Config::get();

		$this->pattern = [
			'sub'      => '/'.
				'^([\040\011]*)'.
				'<!--\s+'.
					'include\s+('.
						'('.\preg_quote($cfg->relative, '/').'|\/|\.\/|(?:\.\.\/)*)?'.
						'([^\s+]+)'.
					')'.
				'\s+-->'.
			'/ms',

			'database' => '/'.
				'<!--\s+'.
					'\[('.
						'('.\preg_quote($cfg->relative, '/').'|\/|\.\/|(?:\.\.\/)*)?'.
						'([^\]\[\=\?]+)'.
					')\]'.
				'\s+-->'.
			'/s',

			'markup'   => '/'.
				'^([\040\011]*)'.
				'\[\s*'.
					'([A-Z_a-z][A-Za-z_0-9]*)'.
					'(?:\s*\=\s*([A-Z_a-z][A-Za-z_0-9]*))?'.
					'(?:\s*\{\s*(.+)\s*\})?'.
				'\s*\]'.
			'/Ums',

			'begin'    => '/(?:\n|\r|\A)<!--\s+('.\preg_quote($cfg->variant, '/').'?)(',

			'end'      => ')(?:\s*\:\s*([a-zA-Z_][a-zA-Z_0-9]*))?\s+-->(.+)<!--\s+\~\\2\s+-->/Us',

			'template' => '/'.
				'<!--\s+'.
					'('.\preg_quote($cfg->variant, '/').'|)'.
					'([a-zA-Z_][a-zA-Z_0-9]*)(?:\s*\:\s*([a-zA-Z_][a-zA-Z_0-9]*))?'.
				'\s+-->'.
				'(.+)'.
				'<!--\s+\~\\2\s+-->'.
			'/Us',

			'class'    => '/class=\"([\w\-]+)\"/Us',

			'clean'    => '/\s*{\:([\w\-]+)\:\}/Us',

			'attrs'    => '/^([a-z]+|)(?:\s*\=\s*(.*)(?:\s*(\\?)\s*(\.|_|\!|[a-z_][a-z_0-9]*|)?)?)?$/Uis',
		];
	}

	private function getAttr(Attribute $default, string $directive, bool $flat, string $class=''): array {
		$attrs = \explode(',', $directive);
		$out = [];

		foreach ($attrs as $attr) {
			$attr = \trim($attr);

			if ('' == $attr) {
				continue;
			}

			if (!\preg_match($this->pattern['attrs'], $attr, $match)) {
				continue;
			}

			$match[1] = \trim($match[1]);

			if (!isset($match[2])) {
				$out[$match[1]] = $match[1];
				continue;
			}

			$match[2] = \trim($match[2]);

			if ('' == $match[1]) {
				$match[1] = 'class';
			}

			if (!isset($match[3]) || '' == $class) {
				if ('' == $match[2]) {
					continue;
				}

				$out[$match[1]] = $match[1].'="'.$match[2].'"';
				continue;
			}

			if ('' == $match[2]) {
				if (isset($default->{$match[1]})) {
					$match[2] = $default->{$match[1]};
				}
			}

			if (!isset($match[4])) {
				if ($flat) {
					if ('' == $match[2]) {
						$out[$match[1]] = $match[1].'="{'.$class.'_'.$match[1].'}"';
					}
					else {
						$out[$match[1]] = $match[1].'="{'.$class.'_'.$match[1].'='.$match[2].'}"';
					}
				}
				else {
					if ('' == $match[2]) {
						$out[$match[1]] = $match[1].'="{'.$class.'.'.$match[1].'}"';
					}
					else {
						$out[$match[1]] = $match[1].'="{'.$class.'.'.$match[1].'='.$match[2].'}"';
					}
				}

				continue;
			}

			switch ($match[4]) {
			case '!':
				if ('' == $match[2]) {
					$out[$match[1]] = $match[1].'="{'.$match[1].'}"';
				}
				else {
					$out[$match[1]] = $match[1].'="{'.$match[1].'='.$match[2].'}"';
				}

				break;

			case '_':
				if ('' == $match[2]) {
					$out[$match[1]] = $match[1].'="{'.$class.'_'.$match[1].'}"';
				}
				else {
					$out[$match[1]] = $match[1].'="{'.$class.'_'.$match[1].'='.$match[2].'}"';
				}

				break;

			case '.':
				if ('' == $match[2]) {
					$out[$match[1]] = $match[1].'="{'.$class.'.'.$match[1].'}"';
				}
				else {
					$out[$match[1]] = $match[1].'="{'.$class.'.'.$match[1].'='.$match[2].'}"';
				}

				break;

			default:
				if ('' == $match[2]) {
					$out[$match[1]] = $match[1].'="{'.$match[4].'}"';
				}
				else {
					$out[$match[1]] = $match[1].'="{'.$match[4].'='.$match[2].'}"';
				}
			}
		}

		return $out;
	}

	private function identifySyntax(Attribute $default, array &$markup, array $attr, array $syntax): void {
		$string = \explode($syntax['end'], $markup[4]);
		$ns = '';
		$vars = [];

		foreach (\array_keys($string) as $key) {
			$string[$key] = \trim($string[$key]);

			if ('' == $string[$key]) {
				unset($string[$key]);
				continue;
			}

			if (!isset($attr[$string[$key]])) {
				continue;
			}

			switch ($string[$key]) {
			case 'ASIS':
				$attr['ASIS'] = true;
				unset($string[$key]);
				break;

			case 'FLAT':
				$attr['FLAT'] = true;
				unset($string[$key]);
				break;

			case 'OPEN':
				$attr['OPEN'] = '!';
				unset($string[$key]);
				break;

			case 'WRAP':
				//$attr['WRAP'] = '[#'.$syntax['tag'].' class="'.$syntax['class'].'"]';
				//$attr['WRAP'] = '[class="'.$syntax['class'].'"]';
				$attr['WRAP'] = '[]';
				unset($string[$key]);
				break;
			}
		}

		foreach ($string as $comand) {
			$comand = \explode(':', $comand);
			$comand[0] = \trim($comand[0]);

			if ('' == $comand[0]) {
				continue;
			}

			// $cfg->syntax_div - "/"
			if (\str_contains($comand[0], $syntax['div'])) {
				if ($syntax['div'] == $comand[0][0]) {
					// строка начинается с "/"
					$ns = '';
					$comand[0] = \trim(\substr($comand[0], 1));
				}
				else {
					$pos = \strpos($comand[0], $syntax['div']);
					$ns  = \trim(\substr($comand[0], 0, $pos));
					$comand[0] = \trim(\substr($comand[0], $pos + 1));
				}
			}

			if (!isset($comand[1])) {
				if (\str_starts_with($comand[0], 'WRAP#')) {
					$tag = \mb_substr($comand[0], 5);

					if ($tag == $syntax['tag']) {
						//$attr['WRAP'] = '[class="'.$syntax['class'].'"]';
						$attr['WRAP'] = '[]';
					}
					else {
						//$attr['WRAP'] = '[#'.$tag.' class="'.$syntax['class'].'"]';
						$attr['WRAP'] = '[#'.$tag.']';
					}
				}
			
				continue;
			}

			$comand[1] = \trim($comand[1]);

			if ('WRAP' == $comand[0]) {
				//$attr['WRAP'] = '[#'.$syntax['tag'].' '.\implode(' ', $this->getAttr($default, $comand[1], $attr['FLAT'])).']';
				$attr['WRAP'] = '['.\implode(' ', $this->getAttr($default, $comand[1], $attr['FLAT'])).']';
				continue;
			}

			if (\str_starts_with($comand[0], 'WRAP#')) {
				$tag = \mb_substr($comand[0], 5);

				if ($tag == $syntax['tag']) {
					$attr['WRAP'] = '['.\implode(' ', $this->getAttr($default, $comand[1], $attr['FLAT'])).']';
				}
				else {
					$attr['WRAP'] = '[#'.$tag.' '.\implode(' ', $this->getAttr($default, $comand[1], $attr['FLAT'])).']';
				}

				continue;
			}

			if (\str_starts_with($comand[0], 'CUT#')) {
				$cut = \explode('#', $comand[0]);
				$comand[0] = 'CUT';
				$markup[7] = \trim($cut[1]);
			}

			if ('CUT' == $comand[0]) {
				$markup[6] = \explode(',', $comand[1]);

				foreach (\array_keys($markup[6]) as $key) {
					$markup[6][$key] = \trim($markup[6][$key]);

					if ('' == $markup[6][$key]) {
						unset($markup[6][$key]);
					}
				}

				$markup[6] = \array_flip($markup[6]);
				continue;
			}

			$comand[0] = \explode(',', $comand[0]);

			foreach ($comand[0] as $class) {
				$class = \trim($class);

				if ('' == $class) {
					continue;
				}

				if ('' != $ns) {
					$class = $ns.'.'.$class;
				}

				if (\str_contains($class, '.')) {
					$pos  = \strrpos($class, '.');
					$path = \substr($class, 0, $pos);
					$name = \substr($class, $pos+1);
				}
				else {
					$path = '';
					$name = $class;
				}

				if (isset($vars[$path][$name])) {
					$vars[$path][$name] = $vars[$path][$name] + $this->getAttr($default, $comand[1], $attr['FLAT'], $name);
				}
				else {
					$vars[$path][$name] = $this->getAttr($default, $comand[1], $attr['FLAT'], $name);
				}
			}
		}

		if (!empty($vars)) {
			foreach (\array_keys($vars) as $path) {
				foreach (\array_keys($vars[$path]) as $name) {
					if ($attr['ASIS'] && !isset($vars[$path][$name]['class'])) {
						$vars[$path][$name]['class'] = 'class="'.$name.'"';
					}

					$vars[$path][$name] = \implode(' ', $vars[$path][$name]);

					if (!empty($markup[5])) {
						$vars[$path][$name] = \str_replace(\array_keys($markup[5]), \array_values($markup[5]), $vars[$path][$name]);
					}
				}
			}
		}

		if (\str_contains($attr['WRAP'], '%')) {
			$attr['WRAP'] = \str_replace(\array_keys($markup[5]), \array_values($markup[5]), $attr['WRAP']);
		}

		$markup[4] = $attr + $vars;
	}

	private function makeTemplate(): bool {
		$tpl = new Template(
			$this->page, [
				'db'  => $this->pattern['database'],
				'rel' => Config::get()->relative,
				'sub' => $this->pattern['sub'],
			], [
				'rel' => \strtr(\dirname(\realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['PHP_SELF'])),'\\', '/').'/',
				'tpl' => $this->dirtpl,
			]
		);

		if (!$tpl->collect()) {
			$this->page = $tpl->getTemplate();
			return false;
		}

		$this->page = $tpl->getTemplate();
		$this->selectSnippets($tpl);
		return true;
	}

	private function cutStrings(array &$markup): void {
		$markup[5] = [];

		for (;;) {
			$pos1 = \mb_strpos($markup[4], '\'');
			$pos2 = \mb_strpos($markup[4], '"');

			if (!$pos1 && !$pos2) {
				break;
			}

			if ((!$pos1 && $pos2) || ($pos1 && $pos2 && $pos1 > $pos2)) {
				$start = $pos2;
				$end   = '"';
			}
			elseif (($pos1 && !$pos2) || ($pos1 && $pos2 && $pos1 < $pos2)) {
				$start = $pos1;
				$end   = '\'';
			}

			$count = '%'.\count($markup[5]).'%';
			$posend =  \mb_strpos($markup[4], $end, $start+1);

			if (!$posend) {
				if (\is_bool($posend)) {
					break;	
				}
				else {
					$markup[5][$count] = '';
					$markup[4] = \mb_substr($markup[4], 0, $start).$count.\mb_substr($markup[4], 1);
				}
			}
			else {
				while ('\\' == $markup[4][$posend-1]) {
					$posend = \mb_strpos($markup[4], $end, $posend+1);
				}

				$markup[5][$count] = \mb_substr($markup[4], $start+1, $posend-$start-1);
				$markup[4]  = \mb_substr($markup[4], 0, $start).$count.\mb_substr($markup[4], $posend+1);
			}
		}
	}

	private function selectSnippets(Template $tpl): void {
		if (\preg_match_all($this->pattern['markup'], $this->page, $markup, PREG_SET_ORDER)) {
			$cfg = Config::get();

			$attr = [
				$cfg->asis => false,
				$cfg->open => '',
				$cfg->cut  => [],
				$cfg->flat => false,
				$cfg->wrap => '',
			];

			$syntax = [
				'end' => $cfg->syntax_end,
				'div' => $cfg->syntax_div,
				'tag' => $cfg->wrap_tag,
				'class' => $cfg->wrap_class,
			];

			$default = Attribute::get();

			foreach ($markup as $val) {
				if (isset($val[3]) && ('' != $val[3])) {
					$this->snip[$val[2]] = $val[3];
				}
				else {
					$this->snip[$val[2]] = $val[2];
					$val[3] = $val[2];
				}

				if (!isset($val[4])) {
					$val[4] = [];
				}
				else {
					$this->cutStrings($val);
					$this->identifySyntax($default, $val, $attr, $syntax);
				}
				
				$this->markup[$val[2]] = $val;
			}
		}

		$p = $this->pattern['begin'].\implode('|', \array_unique($this->snip)).$this->pattern['end'];

		// Получение списка задействованых сниппетов
		if (\preg_match_all($p, $tpl->getDB(), $block, PREG_SET_ORDER)) {
			foreach ($block as $v) {
				$v[5] = '';
				$v[4] = \preg_replace($this->pattern['class'], '{:\\1:}', $v[4]);
				$this->block[$v[2]] = $v;
			}

			$this->makeStack();
		}
	}

	private function makeStack(): void {
		$this->snip = \array_flip(\array_keys($this->block));

		for ($block = \reset($this->block); $block; $block = \next($this->block)) {
			$key = \key($this->block);
			
			if (\preg_match_all($this->pattern['template'], $block[4], $match, PREG_SET_ORDER)) {
				foreach ($match as $v) {
					$name = $key.'.'.$v[2];
					$v[5] = $block[5] ? $block[5].'.'.$v[2] : $v[2];
					$this->block[$name] = $v;
					$this->block[$key][4] = \str_replace($v[0],'[:'.$v[5].':]', $this->block[$key][4]);
					$this->block[$key][6]['[:'.$v[5].':]'] = $name;
				}
			}
		}

		foreach (\array_keys($this->block) as $key) {
			if (isset($this->snip[$key])) {
				$this->snip[$key] = [$key];
			}
			else {
				$snip = \substr($key, 0, \strpos($key, '.'));
				\array_unshift($this->snip[$snip], $key);
			}
		}
	}
}
