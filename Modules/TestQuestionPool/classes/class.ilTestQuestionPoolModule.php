<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2008 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/


include_once("./Services/Component/classes/class.ilModule.php");

/**
* TestQuestionPool Module.
*
* @author Alex Killing <alex.killing@gmx.de>
* @version $Id: class.ilTestQuestionPoolModule.php 34181 2012-04-16 07:56:29Z bheyser $
*
* @ingroup ModulesTestQuestionPool
*/
class ilTestQuestionPoolModule extends ilModule
{
	
	/**
	* Constructor: read information on component
	*/
	function __construct()
	{
		parent::__construct();
	}
	
	/**
	* Core modules vs. plugged in modules
	*/
	function isCore()
	{
		return true;
	}

	/**
	* Get version of module. This is especially important for
	* non-core modules.
	*/
	function getVersion()
	{
		return "-";
	}

}
?>
