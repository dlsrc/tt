<?php
/*******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    final class dl\tt\it\Config

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt\it;

final class Config extends \dl\Getter {
	protected function initialize(): void {
		$this->_property['e_no_page'] = 'L\'oggetto composizione modello di pagina non esiste.';
		$this->_property['e_no_tpl']  = 'Il file modello "{0}" non esiste o l\'accesso è limitato.';
	}
}
