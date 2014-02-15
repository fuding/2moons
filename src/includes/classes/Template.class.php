<?php

/**
 *  2Moons
 *  Copyright (C) 2011  Slaver
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package 2Moons
 * @author Slaver <slaver7@gmail.com>
 * @copyright 2009 Lucky <lucky@xgproyect.net> (XGProyecto)
 * @copyright 2011 Slaver <slaver7@gmail.com> (Fork/2Moons)
 * @license http://www.gnu.org/licenses/gpl.html GNU GPLv3 License
 * @version 1.6.1 (2011-11-19)
 * @info $Id: Template.class.php 2803 2013-10-06 22:23:27Z slaver7 $
 * @link http://code.google.com/p/2moons/
 */

require('includes/libs/Smarty/Smarty.class.php');
		
class Template
{
	protected $window	= 'full';
	public $jsscript	= array();
	public $script		= array();


	/**
	 * reference of the Smarty object
	 * @var Smarty
	 */
	private $smarty;

	function __construct()
	{
		$this->smarty	= new Smarty();
		$this->smartySettings();
	}

	public function getSmartyObj()
	{
		return $this->smarty;
	}

	private function smartySettings()
	{
        global $THEME;
		$this->smarty->caching 					= false;
		$this->smarty->merge_compiled_includes	= true;
		$this->smarty->compile_check			= true; #Set false for production!
		$this->smarty->php_handling				= Smarty::PHP_REMOVE;

		$this->smarty->setPluginsDir(array(
			'includes/libs/Smarty/plugins/',
			'includes/classes/smarty-plugins/',
		));

		$baseCachePath	= is_writable(CACHE_PATH.'templates/') ? CACHE_PATH.'templates/' : $this->getTempPath();

		$this->smarty->setCompileDir($baseCachePath.'compile/');
		$this->smarty->setCacheDir($baseCachePath.'cache/');

		$this->smarty->setTemplateDir(array(
            $THEME->getTemplatePath().strtolower(MODE),
            TEMPLATE_PATH.strtolower(MODE),
            TEMPLATE_PATH.'basic'
        ));
	}

	private function getTempPath()
	{
		$this->smarty->force_compile 		= true;
		return sys_get_temp_dir();
	}

	public function loadscript($script)
	{
		$this->jsscript[]			= substr($script, 0, -3);
	}

	public function execscript($script)
	{
		$this->script[]				= $script;
	}

	public function display($file)
	{
        global $LNG, $THEME;

        $this->smarty->assign(array(
            'scripts'		=> $this->jsscript,
            'execscript'	=> implode("\n", $this->script),
        ));

		$this->smarty->compile_id	= $LNG->getLanguage().'_'.$THEME->getThemeName();
		$this->smarty->display($file);
	}
}
