<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    enum dl\tt\Build

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt;

enum Build implements \dl\PreferredCase {
	use \dl\DefaultCase;
	use \dl\CurrentCase;

	case Std;
	case Lite;

	public function ns(): string {
		return match($this) {
			self::Std  => __NAMESPACE__.'\\std',
			self::Lite => __NAMESPACE__.'\\lite',
		};
	}

	public function builder(): string {
		return match($this) {
			self::Std  => __NAMESPACE__.'\\std\\Builder',
			self::Lite => __NAMESPACE__.'\\lite\\Builder',
		};
	}
}
