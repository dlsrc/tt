<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    class dl\tt\Config

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt;

final class Config extends \dl\Setter {
	protected function initialize(): void	{
		$this->_property['root']          = __NAMESPACE__.'\\Complex';

		$this->_property['auto_class']    = true;

		$this->_property['var_begin']     = '{';
		$this->_property['var_end']       = '}';
		$this->_property['global_begin']  = '[@';
		$this->_property['global_end']    = ']';
		$this->_property['block_end']     = '~';
		$this->_property['variant']       = '^';
		$this->_property['driver']        = '!';
		$this->_property['comment_begin'] = '/*';
		$this->_property['comment_end']   = '*/';

		$this->_property['refer']         = '*';
		$this->_property['refns']         = '~';

		$this->_property['relative']      = '~';

		$this->_property['syntax_div']    = '/';
		$this->_property['syntax_end']    = ';';

		$this->_property['open']          = 'OPEN';
		$this->_property['asis']          = 'ASIS';
		$this->_property['cut']           = 'CUT';
		$this->_property['flat']          = 'FLAT';
		$this->_property['wrap']          = 'WRAP';
		$this->_property['wrap_tag']      = 'div';
		$this->_property['wrap_class']    = 'wrap';
		$this->_property['wrap_open']     = '[';
		$this->_property['wrap_close']    = ']';

		$this->_property['keep_spaces']   = false;
	}
}
