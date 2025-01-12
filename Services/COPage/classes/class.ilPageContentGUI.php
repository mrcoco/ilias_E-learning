<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once("./Services/COPage/classes/class.ilPageContent.php");

/**
* User Interface for Editing of Page Content Objects (Paragraphs, Tables, ...)
*
* @author Alex Killing <alex.killing@gmx.de>
* @version $Id: class.ilPageContentGUI.php 36765 2012-09-09 16:03:16Z akill $
*
* @ingroup ServicesCOPage
*/
class ilPageContentGUI
{
	var $content_obj;
	var $ilias;
	var $tpl;
	var $lng;
	var $ctrl;
	var $pg_obj;
	var $hier_id;
	var $dom;
	var $updated;
	var $target_script;
	var $return_location;

	// common bb buttons (special ones are iln and wln)
	protected static $common_bb_buttons = array(
		"str" => "Strong", "emp" => "Emph", "imp" => "Important", "com" => "Comment",
		"quot" => "Quotation", "acc" => "Accent", "code" => "Code", "tex" => "Tex",
		"fn" => "Footnote", "xln" => "ExternalLink"
		);

	/**
	* Constructor
	* @access	public
	*/
	function ilPageContentGUI(&$a_pg_obj, &$a_content_obj, $a_hier_id = 0, $a_pc_id = "")
	{
		global $ilias, $tpl, $lng, $ilCtrl;
		$this->ilias =& $ilias;
		$this->tpl =& $tpl;
		$this->lng =& $lng;
		$this->pg_obj =& $a_pg_obj;
		$this->ctrl =& $ilCtrl;

		$this->content_obj =& $a_content_obj;
		if($a_hier_id !== 0)
		{
			$this->hier_id = $a_hier_id;
			$this->pc_id = $a_pc_id;
//echo "-".$this->pc_id."-";
			$this->dom =& $a_pg_obj->getDom();
		}
	}

	/**
	* Get common bb buttons
	*/
	static function _getCommonBBButtons()
	{
		return self::$common_bb_buttons;
	}

	// scorm2004-start
	/**
	* Set Style Id.
	*
	* @param	int	$a_styleid	Style Id
	*/
	function setStyleId($a_styleid)
	{
		$this->styleid = $a_styleid;
	}

	/**
	* Get Style Id.
	*
	* @return	int	Style Id
	*/
	function getStyleId()
	{
		return $this->styleid;
	}

	/**
	 * Set enable internal links
	 *
	 * @param	boolean	enable internal links
	 */
	function setEnableInternalLinks($a_val)
	{
		$this->enable_internal_links = $a_val;
	}
	
	/**
	 * Get enable internal links
	 *
	 * @return	boolean	enable internal links
	 */
	function getEnableInternalLinks()
	{
		return $this->enable_internal_links;
	}

	/**
	 * Set enable keywords handling
	 *
	 * @param	boolean	keywords handling
	 */
	function setEnableKeywords($a_val)
	{
		$this->enable_keywords = $a_val;
	}
	
	/**
	 * Get enable keywords handling
	 *
	 * @return	boolean	keywords handling
	 */
	function getEnableKeywords()
	{
		return $this->enable_keywords;
	}

	/**
	 * Set enable anchors
	 *
	 * @param	boolean	anchors
	 */
	function setEnableAnchors($a_val)
	{
		$this->enable_anchors = $a_val;
	}
	
	/**
	 * Get enable anchors
	 *
	 * @return	boolean	anchors
	 */
	function getEnableAnchors()
	{
		return $this->enable_anchors;
	}
	
	/**
	* Get style object
	*/
	function getStyle()
	{
		if ((!is_object($this->style) || $this->getStyleId() != $this->style->getId()) && $this->getStyleId() > 0)
		{
			if (ilObject::_lookupType($this->getStyleId()) == "sty")
			{
				include_once("./Services/Style/classes/class.ilObjStyleSheet.php");
				$this->style = new ilObjStyleSheet($this->getStyleId());
			}
		}
		
		return $this->style;
	}
	
	/**
	* Get characteristics of current style
	*/
	protected function getCharacteristicsOfCurrentStyle($a_type)
	{
		if ($this->getStyleId() > 0 &&
			ilObject::_lookupType($this->getStyleId()) == "sty")
		{
			include_once("./Services/Style/classes/class.ilObjStyleSheet.php");
			$style = new ilObjStyleSheet($this->getStyleId());
			$chars = array();
			if (!is_array($a_type))
			{
				$a_type = array($a_type);
			}
			foreach ($a_type as $at)
			{
				$chars = array_merge($chars, $style->getCharacteristics($at, true));
			}
			$new_chars = array();
			if (is_array($chars))
			{
				foreach ($chars as $char)
				{
					if ($this->chars[$char] != "")	// keep lang vars for standard chars
					{
						$new_chars[$char] = $this->chars[$char];
					}
					else
					{
						$new_chars[$char] = $char;
					}
					asort($new_chars);
				}
			}
			$this->setCharacteristics($new_chars);
		}
	}

	/**
	* Set Characteristics
	*/
	function setCharacteristics($a_chars)
	{
		$this->chars = $a_chars;
	}

	/**
	* Get characteristics
	*/
	function getCharacteristics()
	{
		return $this->chars ? $this->chars : array();
	}
	// scorm2004-end

	/*
	function setReturnLocation($a_location)
	{
		$this->return_location = $a_location;
	}

	function getReturnLocation()
	{
		return $this->return_location;
	}*/

	/**
	* get hierarchical id in dom object
	*/
	function getHierId()
	{
		return $this->hier_id;
	}

	/**
	* get hierarchical id in dom object
	*/
	function setHierId($a_hier_id)
	{
		$this->hier_id = $a_hier_id;
	}

	/**
	* Get the bb menu incl. script
	*/
	function getBBMenu($a_ta_name = "par_content")
	{
		global $lng, $ilCtrl;
		
		include_once("./Services/COPage/classes/class.ilPageEditorSettings.php");
		
		$btpl = new ilTemplate("tpl.bb_menu.html", true, true, "Services/COPage");

		// not nice, should be set by context per method
		//if ($this->pg_obj->getParentType() == "gdf" ||
		//	$this->pg_obj->getParentType() == "lm" ||
		//	$this->pg_obj->getParentType() == "dbk")
		if ($this->getEnableInternalLinks())
		{
			$btpl->setCurrentBlock("bb_ilink_button");
			$btpl->setVariable("BB_LINK_ILINK",
				$this->ctrl->getLinkTargetByClass("ilInternalLinkGUI", "showLinkHelp"));
			$btpl->parseCurrentBlock();
			
			// add int link parts
			include_once("./Modules/LearningModule/classes/class.ilInternalLinkGUI.php");
			$btpl->setCurrentBlock("int_link_prep");
			$btpl->setVariable("INT_LINK_PREP", ilInternalLinkGUI::getInitHTML(
				$ilCtrl->getLinkTargetByClass(array("ilpageeditorgui", "ilinternallinkgui"),
						"", false, true, false)));
			$btpl->parseCurrentBlock();

		}
		
		if ($this->getEnableKeywords())
		{
			$btpl->touchBlock("bb_kw_button");
			$btpl->setVariable("TXT_KW", $this->lng->txt("cont_text_keyword"));
		}
		if ($this->pg_obj->getParentType() == "wpg")
		{
			$btpl->setCurrentBlock("bb_wikilink_button");
			$btpl->setVariable("TXT_WLN2", $lng->txt("wiki_wiki_page"));
			$btpl->parseCurrentBlock();
		}
		$mathJaxSetting = new ilSetting("MathJax");
		$style = $this->getStyle();
//echo URL_TO_LATEX;
		foreach (self::$common_bb_buttons as $c => $st)
		{
			if (ilPageEditorSettings::lookupSettingByParentType($this->pg_obj->getParentType(), "active_".$c, true))
			{
				if ($c != "tex" || $mathJaxSetting->get("enable") || defined("URL_TO_LATEX"))
				{
					$btpl->touchBlock("bb_".$c."_button");
					$btpl->setVariable("TXT_".strtoupper($c), $this->lng->txt("cont_text_".$c));
				}
			}
		}
		
		if ($this->getEnableAnchors())
		{
			$btpl->touchBlock("bb_anc_button");
			$btpl->setVariable("TXT_ANC", $lng->txt("cont_anchor").":");
		}
		
		// footnote
//		$btpl->setVariable("TXT_FN", $this->lng->txt("cont_text_fn"));
		
//		$btpl->setVariable("TXT_CODE", $this->lng->txt("cont_text_code"));
		$btpl->setVariable("TXT_ILN", $this->lng->txt("cont_text_iln"));
//		$btpl->setVariable("TXT_XLN", $this->lng->txt("cont_text_xln"));
//		$btpl->setVariable("TXT_TEX", $this->lng->txt("cont_text_tex"));
		$btpl->setVariable("TXT_BB_TIP", $this->lng->txt("cont_bb_tip"));
		$btpl->setVariable("TXT_WLN", $lng->txt("wiki_wiki_page"));
		
		$btpl->setVariable("PAR_TA_NAME", $a_ta_name);
		
		return $btpl->get();
	}

	/**
	* delete content element
	*/
	function delete()
	{
		$updated = $this->pg_obj->deleteContent($this->hier_id);
		if($updated !== true)
		{
			$_SESSION["il_pg_error"] = $updated;
		}
		else
		{
			unset($_SESSION["il_pg_error"]);
		}
		$this->ctrl->returnToParent($this, "jump".$this->hier_id);
	}

	/**
	* move content element after another element
	*/
	function moveAfter()
	{
		// check if a target is selected
		if(!isset($_POST["target"]))
		{
			$this->ilias->raiseError($this->lng->txt("no_checkbox"),$this->ilias->error_obj->MESSAGE);
		}

		// check if only one target is selected
		if(count($_POST["target"]) > 1)
		{
			$this->ilias->raiseError($this->lng->txt("only_one_target"),$this->ilias->error_obj->MESSAGE);
		}

		$a_hid = explode(":", $_POST["target"][0]);
//echo "-".$a_hid[0]."-".$a_hid[1]."-";

		// check if target is within source
		if($this->hier_id == substr($a_hid[0], 0, strlen($this->hier_id)))
		{
			$this->ilias->raiseError($this->lng->txt("cont_target_within_source"),$this->ilias->error_obj->MESSAGE);
		}

		// check whether target is allowed
		$curr_node =& $this->pg_obj->getContentNode($a_hid[0], $a_hid[1]);
		if (is_object($curr_node) && $curr_node->node_name() == "FileItem")
		{
			$this->ilias->raiseError($this->lng->txt("cont_operation_not_allowed"),$this->ilias->error_obj->MESSAGE);
		}

		// strip "c" "r" of table ids from hierarchical id
		$first_hier_character = substr($a_hid[0], 0, 1);
		if ($first_hier_character == "c" ||
			$first_hier_character == "r" ||
			$first_hier_character == "i")
		{
			$a_hid[0] = substr($a_hid[0], 1);
		}

		// move
		$updated = $this->pg_obj->moveContentAfter($this->hier_id, $a_hid[0],
			$this->content_obj->getPcId(), $a_hid[1]);
		if($updated !== true)
		{
			$_SESSION["il_pg_error"] = $updated;
		}
		else
		{
			unset($_SESSION["il_pg_error"]);
		}

		$this->ctrl->returnToParent($this, "jump".$this->hier_id);
	}

	/**
	* move content element before another element
	*/
	function moveBefore()
	{
		// check if a target is selected
		if(!isset($_POST["target"]))
		{
			$this->ilias->raiseError($this->lng->txt("no_checkbox"),$this->ilias->error_obj->MESSAGE);
		}

		// check if target is within source
		if(count($_POST["target"]) > 1)
		{
			$this->ilias->raiseError($this->lng->txt("only_one_target"),$this->ilias->error_obj->MESSAGE);
		}

		$a_hid = explode(":", $_POST["target"][0]);
		
		// check if target is within source
		if($this->hier_id == substr($a_hid[0], 0, strlen($this->hier_id)))
		{
			$this->ilias->raiseError($this->lng->txt("cont_target_within_source"),$this->ilias->error_obj->MESSAGE);
		}

		// check whether target is allowed
		$curr_node =& $this->pg_obj->getContentNode($a_hid[0], $a_hid[1]);
		if (is_object($curr_node) && $curr_node->node_name() == "FileItem")
		{
			$this->ilias->raiseError($this->lng->txt("cont_operation_not_allowed"),$this->ilias->error_obj->MESSAGE);
		}

		// strip "c" "r" of table ids from hierarchical id
		$first_hier_character = substr($a_hid[0], 0, 1);
		if ($first_hier_character == "c" ||
			$first_hier_character == "r" ||
			$first_hier_character == "i")
		{
			$a_hid[0] = substr($a_hid[0], 1);
		}

		// move
		$updated = $this->pg_obj->moveContentBefore($this->hier_id, $a_hid[0],
			$this->content_obj->getPcId(), $a_hid[1]);
		if($updated !== true)
		{
			$_SESSION["il_pg_error"] = $updated;
		}
		else
		{
			unset($_SESSION["il_pg_error"]);
		}
		$this->ctrl->returnToParent($this, "jump".$this->hier_id);
	}
	
	
	/**
	* split page to new page at specified position
	*/
	function splitPage()
	{
		global $ilErr;
		
		if ($this->pg_obj->getParentType() != "lm" &&
			$this->pg_obj->getParentType() != "dbk")
		{
			$ilErr->raiseError("Split method called for wrong parent type (".
			$this->pg_obj->getParentType().")", $ilErr->FATAL);
		}
		else
		{
			$lm_page =& ilLMPageObject::_splitPage($this->pg_obj->getId(),
				$this->pg_obj->getParentType(), $this->hier_id);
				
			// jump to new page
			$this->ctrl->setParameterByClass("illmpageobjectgui", "obj_id", $lm_page->getId());
			$this->ctrl->redirectByClass("illmpageobjectgui", "edit");
		}
		
		$this->ctrl->returnToParent($this, "jump".($this->hier_id - 1));
	}

	/**
	* split page to next page at specified position
	*/
	function splitPageNext()
	{
		global $ilErr;
		
		if ($this->pg_obj->getParentType() != "lm" &&
			$this->pg_obj->getParentType() != "dbk")
		{
			$ilErr->raiseError("Split method called for wrong parent type (".
			$this->pg_obj->getParentType().")", $ilErr->FATAL);
		}
		else
		{
			$succ_id = ilLMPageObject::_splitPageNext($this->pg_obj->getId(),
				$this->pg_obj->getParentType(), $this->hier_id);
			
			// jump to successor page
			if ($succ_id > 0)
			{
				$this->ctrl->setParameterByClass("illmpageobjectgui", "obj_id", $succ_id);
				$this->ctrl->redirectByClass("illmpageobjectgui", "edit");
			}

		}
		$this->ctrl->returnToParent($this, "jump".($this->hier_id - 1));
	}

	/**
	* display validation errors
	*/
	function displayValidationError()
	{
		if(is_array($this->updated))
		{
			$error_str = "<b>Validation Error(s):</b><br>";
			foreach ($this->updated as $error)
			{
				$err_mess = implode($error, " - ");
				if (!is_int(strpos($err_mess, ":0:")))
				{
					$error_str .= htmlentities($err_mess)."<br />";
				}
			}
			$this->tpl->setVariable("MESSAGE", $error_str);
		}
		else if($this->updated != "" && $this->updated !== true)
		{
			$this->tpl->setVariable("MESSAGE", "<b>Validation Error(s):</b><br />".
				$this->updated."<br />");
		}
	}
	
	/**
	* cancel creating page content
	*/
	function cancelCreate()
	{
		$this->ctrl->returnToParent($this, "jump".$this->hier_id);
	}

	/**
	* cancel update
	*/
	function cancelUpdate()
	{
		$this->ctrl->returnToParent($this, "jump".$this->hier_id);
	}

	/**
	 * Cancel
	 */
	function cancel()
	{ 
		$this->ctrl->returnToParent($this, "jump".$this->hier_id);
	}

	/**
	 * gui function
	 * set enabled if is not enabled and vice versa
	 */
	function deactivate() 
	{		
		$obj = & $this->content_obj;
		
	 	if ($obj->isEnabled ()) 
	 		$obj->disable ();
	 	else
	 		$obj->enable ();
	 	
	 	$updated = $this->pg_obj->update($this->hier_id);
		if($updated !== true)
		{
			$_SESSION["il_pg_error"] = $updated;
		}
		else
		{
			unset($_SESSION["il_pg_error"]);
		}
	
	 	$this->ctrl->returnToParent($this, "jump".$this->hier_id);	 	
	}

	/**
	 * Cut single element
	 */
	function cut() 
	{
		global $lng;
		
		$obj = $this->content_obj;
		
	 	$updated = $this->pg_obj->cutContents(array($this->hier_id.":".$this->pc_id));
		if($updated !== true)
		{
			$_SESSION["il_pg_error"] = $updated;
		}
		else
		{
			unset($_SESSION["il_pg_error"]);
		}
	
		ilUtil::sendSuccess($lng->txt("cont_sel_el_cut_use_paste"), true);
	 	$this->ctrl->returnToParent($this, "jump".$this->hier_id);	 	
	}

	/**
	 * Copy single element
	 */
	function copy() 
	{
		global $lng;
		
		$obj = $this->content_obj;
		
		ilUtil::sendSuccess($lng->txt("cont_sel_el_copied_use_paste"), true);
  		$this->pg_obj->copyContents(array($this->hier_id.":".$this->pc_id));
  
	 	$this->ctrl->returnToParent($this, "jump".$this->hier_id);	 	
	}


	/**
	* Get table templates
	*/
	function getTemplateOptions($a_type)
	{
		$style = $this->getStyle();

		if (is_object($style))
		{
			$ts = $style->getTemplates($a_type);
			$options = array();
			foreach ($ts as $t)
			{
				$options["t:".$t["id"].":".$t["name"]] = $t["name"];
			}
			return $options;
		}
		return array();
	}

}
?>
