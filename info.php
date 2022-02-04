<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    class dl\tt\Info

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt;

final class Info implements \dl\Sociable {
	use \dl\Informer;
	private const VERSION  = '1.0.0';
	private const RELEASE  = 'dev5';

	public static function build(string $template, string|null $markup=null): string {
		if (self::RELEASE) {
			$release = '-'.self::VERSION.'-'.self::RELEASE;
		}
		else {
			$release = '-'.self::VERSION;
		}

		if ($markup && 'ROOT' != $markup) {
			return \substr($template, 0, \strrpos($template, '.'))
			.'-'.$markup.$release.'.php';
		}

		return \substr($template, 0, \strrpos($template, '.'))
		.$release.'.php';
	}

	public static function collect(string $template): string {
		return \substr($template, 0, \strrpos($template, '.')).
			'-'.self::VERSION.
			\substr($template, \strrpos($template, '.'));
	}
}
