<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    class dl\tt\Sorter

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt;

abstract class Sorter {
	abstract public function draw(\dl\markup\Composite $head): void;

	protected string $sort;
	protected string $order;
	protected int $field;
	protected string $trend;
	protected array $event;
	protected array $header;
	protected int $serial;
	protected string $sql;
	protected string $query;
	protected bool $error;
	protected bool $check; // Флаг завершенного этапа ввода полей и проверки выхода индекса поля из диапазона
	protected array $params;
	protected string $param;
	protected int $encode;

	protected string $class_none;
	protected string $class_asc;
	protected string $class_desc;
	protected string $icon_asc;
	protected string $icon_desc;
	protected string $title_asc;
	protected string $title_desc;

	public function __construct(string $sort = 'sort', string $order = 'order') {
		$this->sort  = $sort;
		$this->order = $order;
		$this->error = false;
		$this->check = false;
		$this->field = 1;
		$this->trend = 'begin';

		if (isset($_REQUEST[$this->sort])) {
			$sort = (string) $_REQUEST[$this->sort];

			if (!\ctype_digit($sort) || \str_starts_with($sort, '0')) {
				$this->error = true;
			}
			else {
				$this->field = (int)$_REQUEST[$this->sort];

				if ($this->field < 1) {
					$this->error = true;
					$this->field = 1;
				}
			}
		}

		if (!$this->error && isset($_REQUEST[$this->order])) {
			if ('desc' == $_REQUEST[$this->order]) {
				$this->trend = 'desc';
			}
			elseif ('asc' == $_REQUEST[$this->order]) {
				$this->trend = 'asc';
			}
			else {
				$this->field = 1;
				$this->error = true;
			}
		}

		$_REQUEST[$this->sort]  = $this->field;
		$_REQUEST[$this->order] = $this->trend;

		$this->event  = [];
		$this->header = [];
		$this->serial = 1;
		$this->sql    = '';
		$this->params = [];
		$this->param  = '';
		$this->query  = '';
		$this->encode = \PHP_QUERY_RFC1738;

		$this->class_none = 'none';
		$this->class_asc  = 'none';
		$this->class_desc = 'none';
		$this->icon_asc   = '';
		$this->icon_desc  = '';
		$this->title_asc  = '';
		$this->title_desc = '';
	}

	public function isError(): bool {
		if ($this->check) {
			return $this->error;
		}

		$this->make();
		return $this->error;
	}

	public function view(array $data): void {
		if (isset($data['class_none'])) $this->class_none = $data['class_none'];
		if (isset($data['class_asc']))  $this->class_asc  = $data['class_asc'];
		if (isset($data['class_desc'])) $this->class_desc = $data['class_desc'];

		if (isset($data['icon_asc']))   $this->icon_asc   = $data['icon_asc'].'&nbsp;';
		if (isset($data['icon_desc']))  $this->icon_desc  = $data['icon_desc'].'&nbsp;';

		if (isset($data['title_asc']))  $this->title_asc  = ' &mdash; '.$data['title_asc'];
		if (isset($data['title_desc'])) $this->title_desc = ' &mdash; '.$data['title_desc'];
	}

	public function encode(int $type): void {
		if (\PHP_QUERY_RFC1738 != $type && \PHP_QUERY_RFC3986 != $type) {
			return;
		}

		$this->encode = $type;
	}

	public function param(string $name): bool {
		if (isset($_REQUEST[$name])) {
			$this->params[$name] = $_REQUEST[$name];
			return true;
		}

		return false;
	}

	public function event(string $name, int $enc_type = 0): bool {
		if (isset($_REQUEST[$name])) {
			if (0 == $enc_type) {
				$enc_type = $this->encode;
			}

			switch ($enc_type) {

			case \PHP_QUERY_RFC1738:
				$this->event[$name] = \urlencode($_REQUEST[$name]);
				break;

			case \PHP_QUERY_RFC3986:
				$this->event[$name] = \rawurlencode($_REQUEST[$name]);
				break;

			default:
				$this->event[$name] = $_REQUEST[$name];
				break;
			}

			return true;
		}

		return false;
	}

	public function target(string $name, string $value, int $enc_type = 0): void {
		if (0 == $enc_type) {
			$enc_type = $this->encode;
		}

		switch ($enc_type) {

		case \PHP_QUERY_RFC1738:
			$this->event[$name] = \urlencode($value);
			return;

		case \PHP_QUERY_RFC3986:
			$this->event[$name] = \rawurlencode($value);
			return;

		default:
			$this->event[$name] = $value;
			return;
		}
	}

	public function field(string $name, string $color = '', $field = NULL, $begin = false): void {
		$i = \count($this->header);

		$this->header[$i]['name']  = $name;
		$this->header[$i]['title'] = $name;
		
		if ('' == $color) {
			$this->header[$i]['class'] = $this->class_none;
		}
		else {
			$this->header[$i]['class'] = $color;
		}

		$this->header[$i]['field'] = $field;
		$this->header[$i]['begin'] = $begin;

		if (!isset($field)) {
			return;
		}

		if (!$begin) {
			$value = ++$this->serial;
		}
	}

	public function make(): void {
		if ($this->check) {
			return;
		}

		if ($this->field > $this->serial) {
			$this->error = true;
			$this->field = 1;
			$this->trend = 'begin';
			$_REQUEST[$this->sort]  = $this->field;
			$_REQUEST[$this->order] = $this->trend;
		}

		$this->check  = true;
		$this->serial = 1;

		if (empty($this->params)) {
			$this->param = '';
		}
		else {
			$this->param = \http_build_query($this->params, '', '', $this->encode);
		}

		foreach (\array_keys($this->header) as $i) {
			if (!$this->header[$i]['field']) {
				continue;
			}

			if ($this->header[$i]['begin']) {
				$value = 1;

				if (\is_string($this->header[$i]['begin'])) {
					$this->header[$i]['begin'] = \mb_strtolower($this->header[$i]['begin']);
				}
				else {
					$this->header[$i]['begin'] = 'asc';
				}

				if ('begin' == $this->trend) {
					$_REQUEST[$this->order] = $this->header[$i]['begin'];
					$this->trend = $this->header[$i]['begin'];
				}
			}
			else {
				$value = ++$this->serial;
			}

			if ($this->field == $value) {
				if (1 == $value) {
					if ('desc' == $this->header[$i]['begin']) {
						if ('asc' == $this->trend) {
							$this->query = $this->sort.'='.$value.'&'.$this->order.'=asc';

							$this->header[$i]['name']  = $this->icon_asc.$this->header[$i]['name'];
							$this->header[$i]['class'] = $this->class_asc;

							$this->header[$i]['sort']  = $this->param;
							$this->header[$i]['title'] = $this->header[$i]['title'].$this->title_desc;
						}
						else {
							$this->query = '';

							$this->header[$i]['name']  = $this->icon_desc.$this->header[$i]['name'];
							$this->header[$i]['class'] = $this->class_desc;

							if ('' == $this->param) {
								$this->header[$i]['sort'] = $this->sort.'='.$value.'&'.$this->order.'=asc';
							}
							else {
								$this->header[$i]['sort'] = $this->param.'&'.$this->sort.'='.$value.'&'.$this->order.'=asc';
							}

							$this->header[$i]['title'] = $this->header[$i]['title'].$this->title_asc;
						}
					}
					else {
						if ('desc' == $this->trend) {
							$this->query = $this->sort.'='.$value.'&'.$this->order.'=desc';

							$this->header[$i]['name']  = $this->icon_desc.$this->header[$i]['name'];
							$this->header[$i]['class'] = $this->class_desc;

							$this->header[$i]['sort']  = $this->param;
							$this->header[$i]['title'] = $this->header[$i]['title'].$this->title_asc;
						}
						else {
							$this->query = '';

							$this->header[$i]['name']  = $this->icon_asc.$this->header[$i]['name'];
							$this->header[$i]['class'] = $this->class_asc;

							if ('' == $this->param) {
								$this->header[$i]['sort'] = $this->sort.'='.$value.'&'.$this->order.'=desc';
							}
							else {
								$this->header[$i]['sort'] = $this->param.'&'.$this->sort.'='.$value.'&'.$this->order.'=desc';
							}

							$this->header[$i]['title'] = $this->header[$i]['title'].$this->title_desc;
						}
					}
				}
				else {
					if ('desc' == $this->trend) {
						$this->query = $this->sort.'='.$value.'&'.$this->order.'=desc';
						$this->header[$i]['name']  = $this->icon_desc.$this->header[$i]['name'];
						$this->header[$i]['class'] = $this->class_desc;

						if ('' == $this->param) {
							$this->header[$i]['sort'] = $this->sort.'='.$value.'&'.$this->order.'=asc';
						}
						else {
							$this->header[$i]['sort'] = $this->param.'&'.$this->sort.'='.$value.'&'.$this->order.'=asc';
						}

						$this->header[$i]['title'] = $this->header[$i]['title'].$this->title_asc;
					}
					else {
						$this->query = $this->sort.'='.$value.'&'.$this->order.'=asc';

						$this->header[$i]['name']  = $this->icon_asc.$this->header[$i]['name'];
						$this->header[$i]['class'] = $this->class_asc;

						if ('' == $this->param) {
							$this->header[$i]['sort'] = $this->sort.'='.$value.'&'.$this->order.'=desc';
						}
						else {
							$this->header[$i]['sort'] = $this->param.'&'.$this->sort.'='.$value.'&'.$this->order.'=desc';
						}

						$this->header[$i]['title'] = $this->header[$i]['title'].$this->title_desc;
					}
				}
			}
			else {
				if (1 == $value && 'desc' != $this->header[$i]['begin']) {
					$this->header[$i]['sort']  = $this->param;
					$this->header[$i]['title'] = $this->header[$i]['title'].$this->title_asc;
				}
				else {
					if ('' == $this->param) {
						$this->header[$i]['sort'] = $this->sort.'='.$value.'&'.$this->order.'=asc';
					}
					else {
						$this->header[$i]['sort'] = $this->param.'&'.$this->sort.'='.$value.'&'.$this->order.'=asc';
					}

					$this->header[$i]['title'] = $this->header[$i]['title'].$this->title_asc;
				}
			}

			$order = \strtoupper($this->trend);
			$asc   = 'ASC';
			$desc  = 'DESC';

			if ($this->field == $value || (1 == $value && '' == $this->sql)) {
				if (\is_array($this->header[$i]['field'])) {
					foreach (\array_keys($this->header[$i]['field']) as $key) {
						$f = \preg_split('/\s+/', \trim($this->header[$i]['field'][$key]));

						switch (\count($f)) {
						case 1:
							$this->header[$i]['field'][$key] = '`'.$f[0].'` '.$order;	
							break;
						case 2:
							if ('BINARY' == $f[0]) $this->header[$i]['field'][$key] = 'BINARY `'.$f[1].'` '.$order;
							else $this->header[$i]['field'][$key] = '`'.$f[0].'` '.$f[1];
							break;
						case 3:
							$this->header[$i]['field'][$key] = 'BINARY `'.$f[1].'` '.$f[2];
							break;
						default:
							unset($this->header[$i]['field'][$key]);
						}
					}

					if (empty($this->header[$i]['field'])) {
						$this->sql = '';
					}
					else {
						$this->sql = ' ORDER BY '.\implode(', ', $this->header[$i]['field']).' ';
					}
				}
				else {
					$f = \preg_split('/\s+/', \trim($this->header[$i]['field']));

					switch (\count($f)) {
					case 1:
						$this->sql = ' ORDER BY `'.$f[0].'` '.$order.' ';	
						break;
					case 2:
						if ('BINARY' == $f[0]) $this->sql = ' ORDER BY BINARY `'.$f[1].'` '.$order.' ';
						else $this->sql = ' ORDER BY `'.$f[0].'` '.$f[1].' ';
						break;
					case 3:
						$this->sql = ' ORDER BY BINARY `'.$f[1].'` '.$f[2].' ';
						break;
					default:
						$this->sql = '';
					}
				}
			}
		}
	}

	public function order(): string {
		$this->make();
		return $this->sql;
	}

	public function query(): string {
		$this->make();
		return $this->query;
	}

	public function sort(): string {
		$this->make();
		return (string)$this->field;
	}

	public function trend(): string {
		$this->make();
		return $this->trend;
	}
}
