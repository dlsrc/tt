<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    class dl\tt\Page

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt;

final class Page {
	use Develop;

	private static Component|null $_page = null;

	public static function make(string $template): Component {
		if (null == self::$_page) {
			$page = Info::build($template);

			if (\is_readable($page) && Mode::Product->current()) {
				self::$_page = include $page;
			}
			else {
				if (Mode::Develop->current()) {
					$tpl = self::develop($template);
				}
				else {
					$tpl = self::build($template);
				}

				if (!$tpl) {
					// Ошибку регистрирует метод Page::develop (см. trait Develop)
					return Component::emulate();
				}

				self::$_page = (new Build())->build($tpl);
				(new \dl\Exporter($page))->save(self::$_page);
			}
		}

		return self::$_page;
	}

	public static function drop(): void {
		self::$_page = null;
	}

	public static function exists(): bool {
		return \is_object(self::$_page);
	}

	public static function child(string $name): bool {
		if (self::$_page && self::$_page->isComponent($name)) {
			return true;
		}

		return false;
	}

	public static function open(): Component {
		if (self::$_page) {
			return self::$_page;
		}

		Component::error(Info::message('e_no_page'), Code::Open, true);
		return Component::emulate();
	}
}
