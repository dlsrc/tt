<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    final class dl\tt\en\Info

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt\en;

final class Info extends \dl\Getter {
	protected function initialize(): void {
		$this->_property['e_no_page']    = 'The page template composition object does not exist.';
		$this->_property['e_no_tpl']     = 'Template file "{0}" does not exist or access to it is restricted.';
        $this->_property['e_no_child']   = 'Дочерний компонент "{0}" не существует.';
        $this->_property['e_no_class']   = 'Дочерний компонент с типом "{0}" не существует.';
		$this->_property['e_collect']    = 'В результате компиляции шаблона "{0}" сборщик вернул пустую строку.';
		$this->_property['e_no_snippet'] = 'Сниппет "{0}" не обнаружен.';
	}
}
