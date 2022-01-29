<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    class dl\tt\Table

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt;

class Table
{
	protected $caption;
	protected $thead;
	protected $tfoot;
	protected $tbody;
	protected $trow;
	protected $section;

	public function __construct($caption='')
	{
		$this->caption = $caption;
		$this->thead = [];
		$this->tfoot = [];
		$this->tbody = [];
		$this->trow  = [];
		$this->section = 'trow';
	}

	public function draw(IPageComponent $table)
	{
		if (!$table->isClass('Table'))
		{
			Error::log(
				Core::message('e_mte_no_type', $table->getName(), 'Table', $table->getClass()),
				Code::Component
			);

			return false;
		}

		if (!empty($this->caption))
		{
			$table->Caption->caption = $this->caption;
			$table->Caption->ready();
		}
	}

	public function item(array $item)
	{
		
	}

	public function row(array $row)
	{
		
	}

	public function head(array $attr=[])
	{
		$this->thead+= $attr;
	}

	public function body(array $attr=[])
	{
		$this->tbody+= $attr;
	}

	public function foot(array $attr=[])
	{
		$this->tfoot+= $attr;
	}
}
