<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    class dl\tt\Snippet

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt;

trait Develop {
	private static function develop(string $template): string {
		if (!\is_readable($template)) {
			Component::error(Info::message('e_no_tpl', $template), Code::Make, true);
            return '';
		}

		if (!$c = Collector::make($template)) {
			Component::error(Info::message('e_collect', $template), Code::Make, true);
			return '';
		}

		$tpl = $c->collect();
		\dl\IO::fw(Info::collect($template), $tpl);
		return $tpl;
	}

	private static function build(string $template): string {
		$tpl = Info::collect($template);

		if (\is_readable($tpl)) {
			return include $tpl;
		}

		return self::develop($template);
	}

	private function __construct() {}
}
