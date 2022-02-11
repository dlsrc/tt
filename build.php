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

enum Build {
    case Lite;
    case Main;

	public function ns(): string {
		return match($this) {
			self::Lite => __NAMESPACE__.'\\lite',
			self::Main => __NAMESPACE__.'\\main',
		};
	}

	public function builder(): string {
		return match($this) {
			self::Lite => __NAMESPACE__.'\\lite\\Builder',
			self::Main => __NAMESPACE__.'\\main\\Builder',
		};
	}
}
