<?php
/******************************************************************************\
    ______  _                                    ____ _____  _  ____  ______
    | ___ \| |                                  / _  | ___ \| |/ __ \/ ____/
    | |  \ \ |          Dmitry Lebedev         / /_| | |  \ \ | /  \ \____ \
    | |__/ / |____      <dl@adios.ru>         / ___  | |__/ / | \__/ /___/ /
    |_____/|_____/                           /_/   |_|_____/|_|\____/_____/

    ------------------------------------------------------------------------

    class dl\tt\Widget

    ------------------------------------------------------------------------

    PHP 8.1                                                         (C) 2022

\******************************************************************************/
declare(strict_types=1);
namespace dl\tt;

final class Widget
{
	private $stack = [];

	public function __construct($branch)
	{
		$page = Page::open();
		if ('dl\\Error' == \get_class($page)) return;

		if (\str_contains($branch, '.'))
		{
			$comp = \explode('.', $branch);
			$size = \sizeof($comp);

			if (!$page->isComponent($comp[0])) return;

			$this->stack[0] = $page->{$comp[0]};

			for ($i = 1; $i < $size; $i++)
			{
				if (!$this->stack[0]->isComponent($comp[$i]))
				{
					$this->stack = [];
					return;
				}
				else
				{
					\array_unshift($this->stack, $this->stack[0]->{$comp[$i]});
				}
			}
		}
		elseif ($page->isComponent($branch))
		{
			$this->stack[0] = $page->$branch;
		}
	}

	public function ready($branch='')
	{
		if ('' == $branch)
		{
			foreach ($this->stack as $comp) $comp->ready();
		}
		else
		{
			if (\str_contains($branch, '.'))
			{
				$comp = \array_values(\array_reverse(\explode('.', $branch)));

				foreach ($comp as $id => $name)
				{
					if (!isset($this->stack[$id]) || $this->stack[$id]->getName() != $name) return;
				}

				foreach (\array_keys($comp) as $id)
				{
					$this->stack[$id]->ready();
				}
			}
			elseif ($this->stack[0]->getName() == $branch)
			{
				$this->stack[0]->ready();
			}
		}
	}

	public function getStack()
	{
		$this->stack;
	}

	public function getSnippet()
	{
		if (isset($this->stack[0])) return $this->stack[0];
		return false;
	}

	public static function stack($branch)
	{
		$page = Page::open();
		if ('dl\\Error' == \get_class($page)) return false;

		$comp = \explode('.', $branch);
		$size = \sizeof($comp);

		if (!$page->isComponent($comp[0])) return false;

		$open[0] = $page->{$comp[0]};

		for ($i = 1; $i < $size; $i++)
		{
			if (!$open[0]->isComponent($comp[$i]))
			{
				$open = [];
				return false;
			}
			else
			{
				\array_unshift($open, $open[0]->{$comp[$i]});
			}
		}

		return $open[0];
	}
}
