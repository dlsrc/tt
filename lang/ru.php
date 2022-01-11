<?php
/*******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    final class dl\tt\ru\Config

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt\ru;

final class Config extends \dl\Getter {
	protected function initialize(): void {
		$this->_property['e_no_page']  = 'Объект композиции шаблона страницы не существует.';
		$this->_property['e_no_tpl']   = 'Файл шаблона "{0}" не существует, либо доступ к нему ограничен.';
        $this->_property['e_no_child'] = 'Дочерний компонент "{0}" не существует.';
	}
}
