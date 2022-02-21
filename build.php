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

	case Fast;
	case Lite;
	case Idle;

	public function ns(): string {
		return match($this) {
			self::Fast => __NAMESPACE__.'\\fast',
			self::Lite => __NAMESPACE__.'\\lite',
			self::Idle => __NAMESPACE__.'\\idle',
		};
	}

	public function builder(): string {
		return match($this) {
			self::Fast => __NAMESPACE__.'\\fast\\Builder',
			self::Lite => __NAMESPACE__.'\\lite\\Builder',
			self::Idle => __NAMESPACE__.'\\idle\\Builder',
		};
	}
}
