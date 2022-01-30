<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    class dl\tt\Pager

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt;

abstract class Pager {
	abstract public function draw(Component $pager, int $total, int $list): void;
	abstract public function drawMax(Component $pager, int $total, int $list): void;

	protected string $name;
	protected int $page;
	protected array $event;
	protected array $query;
	protected string $raw;
	protected int $size;
	protected string $prev;
	protected string $next;
	protected string $text;
	protected bool $error;
	protected string $anchor;
	protected int $encode;

	public function __construct(string $name = 'page', int $size = 5) {
		$this->name  = $name;
		$this->error = false;
		$this->page  = 1;

		if (isset($_REQUEST[$this->name])) {
			if (!\ctype_digit($_REQUEST[$this->name]) || \str_starts_with($_REQUEST[$this->name], '0')) {
				$this->error = true;
			}
			else {
				$this->page = (int)$_REQUEST[$this->name];

				if ($this->page < 1) {
					$this->error = true;
					$this->page  = 1;
				}
			}
		}

		$this->event  = [];
		$this->query  = [];
		$this->raw    = '';
		$this->size   = $size;
		$this->prev   = 'назад';
		$this->next   = 'вперед';
		$this->text   = '...';
		$this->anchor = '';
		$this->encode = \PHP_QUERY_RFC1738;
	}

	/*
	* Если аргумент TRUE, очистится информация об ошибке
	*/
	public function clean(bool $error = false): void {
		if ($error) {
			$this->error = false;
		}

		$_REQUEST[$this->name] = '1';
		$this->page = 1;
		$this->event = false;
		$this->value = false;
		$this->query = [];
		$this->raw   = '';
	}

	public function anchor(string $anchor): string {
		return $this->anchor = '#'.$anchor;
	}

	public function isError(): bool {
		return $this->error;
	}

	public function current(?int $total = NULL, ?int $size = NULL): string {
		if (isset($total) && isset($size) && $size > 0) {
			$max = \ceil($total / $size);

			if ($this->page > $max) {
				$this->error = true;
				$this->page  = 1;
			}
		}

		return (string)$this->page;
	}

	public function size(int $size): void {
		if ($size < 5) {
			$size = 5;
		}
		elseif (!($size % 2)) {
			$size++;
		}

		$this->size = $size;
	}

	public function prev(string $prev): void {
		$this->prev = $prev;
	}

	public function next(string $next): void {
		$this->next = $next;
	}

	public function text(string $text): void {
		$this->text = $text;
	}

	public function encode(int $type): void {
		if (\PHP_QUERY_RFC1738 != $type && \PHP_QUERY_RFC3986 != $type) {
			return;
		}

		$this->encode = $type;
	}

	public function event(string $name, int $enc_type = 0): string {
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

			return $this->event[$name];
		}

		return '';
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

	public function query($name, $stop_value=NULL, $default_value=NULL): void {
		if (isset($_REQUEST[$name])) {
			if ($stop_value != $_REQUEST[$name]) {
				$this->query[$name] = $_REQUEST[$name];
			}
		}
		elseif (isset($default_value)) {
			$this->query[$name] = $default_value;
		}
	}

	public function rawquery(string $raw): void {
		$this->raw = $raw;
	}
}
