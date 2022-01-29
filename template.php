<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    class dl\tt\Template

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt;

class Template {
	private string $db;
	private array  $dir;
	private string $tpl;
	private array  $pattern;

	public function __construct(string $tpl, array $pattern, array $dir) {
		$this->db      = '';
		$this->dir     = $dir;
		$this->pattern = $pattern;

		// Удаление комментариев из шаблона
		// и левых символов (в основном пробельных) из начала строки шаблона
		$this->tpl     = \rtrim(\preg_replace(
			['/\s*<!--\s*\/\*.*\*\/\s*-->/Uis', '/^[^<\w\#\/\\\[\{]+/uis'],
			['', ''],
			$tpl
		));
	}

	/**
	* Получение результирующего шаблона
	*/
	public function getTemplate(): string {
		return $this->tpl;
	}

	/**
	* Получение базы данных активных сниппетов
	*/
	public function getDB(): string {
		// Удаление комментариев из базв данных активных сниппетов
		return \preg_replace('/\s*<!--\s*\/\*.*\*\/\s*-->/Uis', '', $this->db);
	}

	/**
	* Поиск и подключение подшаблонов
	*/
	public function collectSub(): void {
		if (\preg_match_all($this->pattern['sub'], $this->tpl, $match, PREG_SET_ORDER) > 0) {
			$search  = [];
			$replace = [];

			foreach ($match as $val) {
				switch ($val[3]) {
				case './': case '':
					$file = $this->dir['tpl'].$val[4];
					break;

				case $this->pattern['rel']:
					$file = $this->dir['rel'].$val[4];
					break;

				case '/':
					$file = $val[2];
					break;

				default:
					if (\str_contains($val[3], '../')) {
						$file = \strtr(\realpath($this->dir['tpl'].$val[3]),'\\', '/').'/'.$val[4];
					}
				}

				if (\is_readable($file)) {
					$sub = \file_get_contents($file);
					$tpl = new Template($sub, $this->pattern, $this->dir);
					$tpl->collectSub();
					$replace[$val[4]] = $tpl->getTemplate();

					if ('' != $val[1]) {
						$replace[$val[4]] = \preg_replace('/^(\s*)(\S)/m', '\\1'.$val[1].'\\2', $replace[$val[4]]);
					}
				}
				else {
					$replace[$val[4]] = $val[1].'<!-- include error: subtemplate '.$val[4].' not found -->';
				}

				$search[$val[4]] = $val[0];
			}

			$this->tpl = \str_replace($search, $replace, $this->tpl);
		}
	}

	/**
	* Составление шаблона и базы даннвх сниппетов
	*/
	public function collect(): bool {
		$this->collectSub(); // поиск и подключение подшаблонов

		// поиск в шаблоне файлов сниппетов и заполнение базы данных сниппетов для текущего шаблона
		if (\preg_match_all($this->pattern['db'], $this->tpl, $match, PREG_SET_ORDER) > 0) {
			$search = [];
			$replace = [];

			foreach ($match as $val) {
				switch ($val[2]) {
				case './': case '':
					$file = $this->dir['tpl'].$val[3];
					break;

				case $this->pattern['rel']:
					$file = $this->dir['rel'].$val[3];
					break;

				case '/':
					$file = $val[1];
					break;

				default:
					if (\str_contains($val[2], '../')) {
						$file = \strtr(\realpath($this->dir['tpl'].$val[2]),'\\', '/').'/'.$val[3];
					}
				}

				if (\is_readable($file)) {
					$this->db.= \file_get_contents($file);
				}

				$search[] = $val[0];
				$replace[] = '';
			}

			$this->tpl = \str_replace($search, $replace, $this->tpl);
			$this->tpl = \preg_replace('/^[^<\w\#\/\\\[\{]+/uis', '', $this->tpl);
		}

		if ('' == $this->db) {
			return false;
		}

		return true;
	}
}
