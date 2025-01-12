<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once "./Services/Container/classes/class.ilContainerGUI.php";

/**
* Class ilObjCategoryGUI
*
* @author Stefan Meyer <meyer@leifos.com>
* @author Sascha Hofmann <saschahofmann@gmx.de>
* @version $Id: class.ilObjCategoryGUI.php 41691 2013-04-23 14:19:25Z jluetzen $
*
* @ilCtrl_Calls ilObjCategoryGUI: ilPermissionGUI, ilPageObjectGUI, ilContainerLinkListGUI, ilObjUserGUI, ilObjUserFolderGUI
* @ilCtrl_Calls ilObjCategoryGUI: ilInfoScreenGUI, ilObjStyleSheetGUI, ilCommonActionDispatcherGUI
* @ilCtrl_Calls ilObjCategoryGUI: ilColumnGUI, ilObjectCopyGUI, ilUserTableGUI, ilDidacticTemplateGUI, ilExportGUI
* 
* @ingroup ModulesCategory
*/
class ilObjCategoryGUI extends ilContainerGUI
{
	var $ctrl;

	/**
	* Constructor
	* @access public
	*/
	function ilObjCategoryGUI($a_data, $a_id, $a_call_by_reference = true, $a_prepare_output = true)
	{
		//global $ilCtrl;

		// CONTROL OPTIONS
		//$this->ctrl =& $ilCtrl;
		//$this->ctrl->saveParameter($this,array("ref_id","cmdClass"));
		$GLOBALS['lng']->loadLanguageModule('cat');

		$this->type = "cat";
		$this->ilContainerGUI($a_data,(int) $a_id,$a_call_by_reference,false);
	}

	function &executeCommand()
	{
		global $rbacsystem, $ilNavigationHistory, $ilAccess, $ilCtrl,$ilTabs;

		$next_class = $this->ctrl->getNextClass($this);
		$cmd = $this->ctrl->getCmd();
		switch($next_class)
		{
			case "ilobjusergui":
				include_once('./Services/User/classes/class.ilObjUserGUI.php');
				
				$this->tabs_gui->setTabActive('administrate_users');
				if(!$_GET['obj_id'])
				{
					$this->gui_obj = new ilObjUserGUI("",$_GET['ref_id'],true, false);
					$this->gui_obj->setCreationMode($this->creation_mode);
					$ret =& $this->ctrl->forwardCommand($this->gui_obj);
				}
				else
				{
					$this->gui_obj = new ilObjUserGUI("", $_GET['obj_id'],false, false);
					$this->gui_obj->setCreationMode($this->creation_mode);
					$ret =& $this->ctrl->forwardCommand($this->gui_obj);
				}
				
				$ilTabs->clearTargets();
				$ilTabs->setBackTarget($this->lng->txt('backto_lua'), $this->ctrl->getLinkTarget($this,'listUsers'));
				break;

			case "ilobjuserfoldergui":
				include_once('./Services/User/classes/class.ilObjUserFolderGUI.php');

				$this->tabs_gui->setTabActive('administrate_users');
				$this->gui_obj = new ilObjUserFolderGUI("",(int) $_GET['ref_id'],true, false);
				$this->gui_obj->setUserOwnerId((int) $_GET['ref_id']);
				$this->gui_obj->setCreationMode($this->creation_mode);
				$ret =& $this->ctrl->forwardCommand($this->gui_obj);
				break;
				
			case "ilcolumngui":
				$this->checkPermission("read");
				$this->prepareOutput();
				include_once("./Services/Style/classes/class.ilObjStyleSheet.php");
				$this->tpl->setVariable("LOCATION_CONTENT_STYLESHEET",
					ilObjStyleSheet::getContentStylePath($this->object->getStyleSheetId()));
				$this->renderObject();
				break;

			case 'ilpermissiongui':
				$this->prepareOutput();
				$this->tabs_gui->setTabActive('perm_settings');
				include_once("Services/AccessControl/classes/class.ilPermissionGUI.php");
				$perm_gui =& new ilPermissionGUI($this);
				$ret =& $this->ctrl->forwardCommand($perm_gui);
				break;
				
			case 'ilinfoscreengui':
				$this->prepareOutput();
				$this->infoScreen();
				break;
				
			case 'ilcontainerlinklistgui':
				include_once("Services/Container/classes/class.ilContainerLinkListGUI.php");
				$link_list_gui =& new ilContainerLinkListGUI();
				$ret =& $this->ctrl->forwardCommand($link_list_gui);
				break;

			// container page editing
			case "ilpageobjectgui":
				$this->prepareOutput(false);
				$ret = $this->forwardToPageObject();
				if ($ret != "")
				{
					$this->tpl->setContent($ret);
				}
				break;
				
			case 'ilobjectcopygui':
				$this->prepareOutput();

				include_once './Services/Object/classes/class.ilObjectCopyGUI.php';
				$cp = new ilObjectCopyGUI($this);
				$cp->setType('cat');
				$this->ctrl->forwardCommand($cp);
				break;
				
			case "ilobjstylesheetgui":
				$this->forwardToStyleSheet();
				break;
				
			case 'ilusertablegui':
				include_once './Services/User/classes/class.ilUserTableGUI.php';
				$u_table = new ilUserTableGUI($this, "listUsers");
				$u_table->initFilter();
				$this->ctrl->setReturn($this,'listUsers');
				$this->ctrl->forwardCommand($u_table);
				break;
			
			case "ilcommonactiondispatchergui":
				$this->prepareOutput();
				include_once("Services/Object/classes/class.ilCommonActionDispatcherGUI.php");
				$gui = ilCommonActionDispatcherGUI::getInstanceFromAjaxCall();
				$this->ctrl->forwardCommand($gui);
				break;

			case 'ildidactictemplategui':
				$this->ctrl->setReturn($this,'edit');
				include_once './Services/DidacticTemplate/classes/class.ilDidacticTemplateGUI.php';
				$did = new ilDidacticTemplateGUI($this);
				$this->ctrl->forwardCommand($did);
				break;

			case 'ilexportgui':
				$this->prepareOutput();
				$this->tabs_gui->setTabActive('export');
				include_once './Services/Export/classes/class.ilExportGUI.php';
				$exp = new ilExportGUI($this);
				$exp->addFormat('xml');
				$this->ctrl->forwardCommand($exp);
				break;

			default:
				if ($cmd == "infoScreen")
				{
					$this->checkPermission("visible");
				}
				else
				{
					$this->checkPermission("read");
				}

				// add entry to navigation history
				if (!$this->getCreationMode() &&
					$ilAccess->checkAccess("read", "", $_GET["ref_id"]))
				{
					$ilNavigationHistory->addItem($_GET["ref_id"],
						$ilCtrl->getLinkTargetByClass("ilrepositorygui", "frameset"), "cat");
				}

				$this->prepareOutput();
				include_once("./Services/Style/classes/class.ilObjStyleSheet.php");
				if (is_object($this->object))
				{
					$this->tpl->setVariable("LOCATION_CONTENT_STYLESHEET",
						ilObjStyleSheet::getContentStylePath($this->object->getStyleSheetId()));
				}

				if(!$cmd)
				{
					$cmd = "render";
				}
				$cmd .= "Object";
				$this->$cmd();

				break;
		}
		
		$this->addHeaderAction();
		
		return true;
	}

	/**
	* Get tabs
	*/
	function getTabs(&$tabs_gui)
	{
		global $rbacsystem, $lng, $ilHelp, $ilAccess;

		if ($this->ctrl->getCmd() == "editPageContent")
		{
			return;
		}
		#$this->ctrl->setParameter($this,"ref_id",$this->ref_id);

		$ilHelp->setScreenIdComponent("cat");
		
		if ($rbacsystem->checkAccess('read',$this->ref_id))
		{
			$force_active = ($_GET["cmd"] == "" || $_GET["cmd"] == "render")
				? true
				: false;
			$tabs_gui->addTab("view_content", $lng->txt("content"),
				$this->ctrl->getLinkTarget($this, ""));

			//BEGIN ChangeEvent add info tab to category object
			$force_active = ($this->ctrl->getNextClass() == "ilinfoscreengui"
				|| strtolower($_GET["cmdClass"]) == "ilnotegui")
				? true
				: false;
			$tabs_gui->addTarget("info_short",
				 $this->ctrl->getLinkTargetByClass(
				 array("ilobjcategorygui", "ilinfoscreengui"), "showSummary"),
				 array("showSummary","", "infoScreen"),
				 "", "", $force_active);
			//END ChangeEvent add info tab to category object
		}
		
		if ($rbacsystem->checkAccess('write',$this->ref_id))
		{
			$force_active = ($_GET["cmd"] == "edit")
				? true
				: false;
			$tabs_gui->addTarget("settings",
				$this->ctrl->getLinkTarget($this, "edit"), "edit", get_class($this)
				, "", $force_active);
		}

		include_once './Services/User/classes/class.ilUserAccountSettings.php';
		if(
			ilUserAccountSettings::getInstance()->isLocalUserAdministrationEnabled() and 
			$rbacsystem->checkAccess('cat_administrate_users',$this->ref_id))
		{
			$tabs_gui->addTarget("administrate_users",
				$this->ctrl->getLinkTarget($this, "listUsers"), "listUsers", get_class($this));
		}

		if($ilAccess->checkAccess('write','',$this->object->getRefId()))
		{
			$tabs_gui->addTarget(
				'export',
				$this->ctrl->getLinkTargetByClass('ilexportgui',''),
				'export',
				'ilexportgui'
			);
		}
		
		// parent tabs (all container: edit_permission, clipboard, trash
		parent::getTabs($tabs_gui);

	}

	/**
	* Render category
	*/
	function renderObject()
	{
		global $ilTabs;
		
		$ilTabs->activateTab("view_content");
		$ret =  parent::renderObject();
		return $ret;

	}

	protected function initCreationForms($a_new_type)
	{
		$forms = parent::initCreationForms($a_new_type);
		//unset($forms[self::CFORM_IMPORT]);
		return $forms;
	}

	protected function afterSave(ilObject $a_new_object)
	{
		global $ilUser, $tree;
		
		// add default translation
		$a_new_object->addTranslation($a_new_object->getTitle(),
			$a_new_object->getDescription(), $ilUser->getPref("language"), true);

		// default: sort by title
		include_once('Services/Container/classes/class.ilContainerSortingSettings.php');
		$settings = new ilContainerSortingSettings($a_new_object->getId());
		$settings->setSortMode(ilContainer::SORT_TITLE);
		$settings->save();
		
		// inherit parents content style, if not individual
		$parent_ref_id = $tree->getParentId($a_new_object->getRefId());
		$parent_id = ilObject::_lookupObjId($parent_ref_id);
		include_once("./Services/Style/classes/class.ilObjStyleSheet.php");
		$style_id = ilObjStyleSheet::lookupObjectStyle($parent_id);
		if ($style_id > 0)
		{
			if (ilObjStyleSheet::_lookupStandard($style_id))
			{
				ilObjStyleSheet::writeStyleUsage($a_new_object->getId(), $style_id);
			}
		}

		// always send a message
		ilUtil::sendSuccess($this->lng->txt("cat_added"),true);
		$this->ctrl->setParameter($this, "ref_id", $a_new_object->getRefId());
		$this->redirectToRefId($a_new_object->getRefId(), "");
	}
	
	/**
	* this one is called from the info button in the repository
	* not very nice to set cmdClass/Cmd manually, if everything
	* works through ilCtrl in the future this may be changed
	*/
	function infoScreenObject()
	{
		$this->ctrl->setCmd("showSummary");
		$this->ctrl->setCmdClass("ilinfoscreengui");
		$this->infoScreen();
	}

	/**
	* show information screen
	*/
	function infoScreen()
	{
		global $ilAccess, $ilCtrl;

		if (!$ilAccess->checkAccess("visible", "", $this->ref_id))
		{
			$this->ilias->raiseError($this->lng->txt("msg_no_perm_read"),$this->ilias->error_obj->MESSAGE);
		}
		
		// #10986
		$this->tabs_gui->setTabActive('info_short');

		include_once("./Services/InfoScreen/classes/class.ilInfoScreenGUI.php");
		$info = new ilInfoScreenGUI($this);

		$info->enablePrivateNotes();
		
		if ($ilAccess->checkAccess("read", "", $_GET["ref_id"]))
		{
			$info->enableNews();
		}

		// no news editing for files, just notifications
		$info->enableNewsEditing(false);
		if ($ilAccess->checkAccess("write", "", $_GET["ref_id"]))
		{
			$news_set = new ilSetting("news");
			$enable_internal_rss = $news_set->get("enable_rss_for_internal");
			
			if ($enable_internal_rss)
			{
				$info->setBlockProperty("news", "settings", true);
				$info->setBlockProperty("news", "public_notifications_option", true);
			}
		}
		
		include_once('Services/AdvancedMetaData/classes/class.ilAdvancedMDRecordGUI.php');
		$record_gui = new ilAdvancedMDRecordGUI(ilAdvancedMDRecordGUI::MODE_INFO,'cat',$this->object->getId());
		$record_gui->setInfoObject($info);
		$record_gui->parse();
		

		// standard meta data
		$info->addMetaDataSections($this->object->getId(),0, $this->object->getType());
		
		// forward the command
		if ($ilCtrl->getNextClass() == "ilinfoscreengui")
		{
			$ilCtrl->forwardCommand($info);
		}
		else
		{
			return $ilCtrl->getHTML($info);
		}
	}
	
	/**
	 * Edit extended category settings
	 *
	 * @access protected
	 */
	protected function editInfoObject()
	{
		$this->checkPermission("write");
		$this->setEditTabs();
		$this->tabs_gui->activateTab('settings');
		$this->tabs_gui->setSubTabActive('edit_cat_settings');
		
		$this->initExtendedSettings();
		$this->tpl->setContent($this->form->getHTML());
	}
	
	/**
	 * Update info (extended meta data) 
	 * 
	 * @access protected
	 */
	protected function updateInfoObject()
	{
		$this->checkPermission("write");
		include_once('Services/AdvancedMetaData/classes/class.ilAdvancedMDRecordGUI.php');
		$record_gui = new ilAdvancedMDRecordGUI(ilAdvancedMDRecordGUI::MODE_EDITOR,
			'crs',$this->object->getId());
		$record_gui->loadFromPost();
		$record_gui->saveValues();

		ilUtil::sendSuccess($this->lng->txt("settings_saved"));
		$this->editInfoObject();
		return true;
	}
	
	
	/**
	 * build property form for extended category settings
	 *
	 * @access protected
	 */
	protected function initExtendedSettings()
	{
		if(is_object($this->form))
		{
			return true;
		}
		
		include_once('Services/Form/classes/class.ilPropertyFormGUI.php');
		$this->form = new ilPropertyFormGUI();
		$this->form->setFormAction($this->ctrl->getFormAction($this));
		$this->form->setTitle($this->lng->txt('ext_cat_settings'));
		$this->form->addCommandButton('updateInfo',$this->lng->txt('save'));
		$this->form->addCommandButton('editInfo',$this->lng->txt('cancel'));

		include_once('Services/AdvancedMetaData/classes/class.ilAdvancedMDRecordGUI.php');
		$record_gui = new ilAdvancedMDRecordGUI(ilAdvancedMDRecordGUI::MODE_EDITOR,'cat',$this->object->getId());
		$record_gui->setPropertyForm($this->form);
		$record_gui->parse();
		
		return true;
	}

	protected function setEditTabs($active_tab = "settings_misc")
	{
		$this->tabs_gui->addSubTab("settings_misc",
			$this->lng->txt("settings"),
			$this->ctrl->getLinkTarget($this, "edit"));

		$this->tabs_gui->addSubTab("settings_trans",
			$this->lng->txt("title_and_translations"),
			$this->ctrl->getLinkTarget($this, "editTranslations"));

		$this->tabs_gui->activateTab("settings");
		$this->tabs_gui->activateSubTab($active_tab);
		
		include_once('Services/AdvancedMetaData/classes/class.ilAdvancedMDRecord.php');
		if(in_array('cat',ilAdvancedMDRecord::_getActivatedObjTypes()))
		{
			$this->tabs_gui->addSubTabTarget("edit_cat_settings",
				 $this->ctrl->getLinkTarget($this,'editInfo'),
				 "editInfo", get_class($this));
		}
	}

	function initEditForm()
	{
		$this->lng->loadLanguageModule($this->object->getType());
		$this->setEditTabs();

		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setTitle($this->lng->txt($this->object->getType()."_edit"));


		// Show didactic template type
		$this->initDidacticTemplate($form);
		
		// sorting
		include_once('Services/Container/classes/class.ilContainerSortingSettings.php');
		$settings = new ilContainerSortingSettings($this->object->getId());

		$sort = new ilRadioGroupInputGUI($this->lng->txt('sorting_header'), "sorting");
		$sort_title = new ilRadioOption($this->lng->txt('sorting_title_header'),
			ilContainer::SORT_TITLE);
		$sort_title->setInfo($this->lng->txt('sorting_info_title'));
		$sort->addOption($sort_title);

		$sort_manual = new ilRadioOption($this->lng->txt('sorting_manual_header'),
			ilContainer::SORT_MANUAL);
		$sort_manual->setInfo($this->lng->txt('sorting_info_manual'));
		$sort->addOption($sort_manual);

		$sort->setValue($settings->getSortMode());
		$form->addItem($sort);


		$this->showCustomIconsEditing(1, $form, false);
		
		
		// Edit ecs export settings
		include_once 'Modules/Category/classes/class.ilECSCategorySettings.php';
		$ecs = new ilECSCategorySettings($this->object);		
		$ecs->addSettingsToForm($form, 'cat');
		

		$form->addCommandButton("update", $this->lng->txt("save"));
		$form->addCommandButton("addTranslation", $this->lng->txt("add_translation"));		

		return $form;
	}

	function getEditFormValues()
	{
		// values are set in initEditForm()
	}
	
	/**
	* updates object entry in object_data
	*
	* @access	public
	*/
	function updateObject()
	{
		if (!$this->checkPermissionBool("write"))
		{
			$this->ilias->raiseError($this->lng->txt("msg_no_perm_write"),$this->ilias->error_obj->MESSAGE);
		}
		else
		{
			$form = $this->initEditForm();
			if($form->checkInput())
			{
				include_once('Services/Container/classes/class.ilContainerSortingSettings.php');
				$settings = new ilContainerSortingSettings($this->object->getId());
				$settings->setSortMode($form->getInput("sorting"));
				$settings->update();

				// save custom icons
				if ($this->ilias->getSetting("custom_icons"))
				{
					if($form->getItemByPostVar("cont_big_icon")->getDeletionFlag())
					{
						$this->object->removeBigIcon();
					}
					if($form->getItemByPostVar("cont_small_icon")->getDeletionFlag())
					{
						$this->object->removeSmallIcon();
					}
					if($form->getItemByPostVar("cont_tiny_icon")->getDeletionFlag())
					{
						$this->object->removeTinyIcon();
					}

					$this->object->saveIcons($_FILES["cont_big_icon"]['tmp_name'],
						$_FILES["cont_small_icon"]['tmp_name'],
						$_FILES["cont_tiny_icon"]['tmp_name']);
				}

				// BEGIN ChangeEvent: Record update
				global $ilUser;
				require_once('Services/Tracking/classes/class.ilChangeEvent.php');
				ilChangeEvent::_recordWriteEvent($this->object->getId(), $ilUser->getId(), 'update');
				ilChangeEvent::_catchupWriteEvents($this->object->getId(), $ilUser->getId());				
				// END ChangeEvent: Record update
				
				// Update ecs export settings
				include_once 'Modules/Category/classes/class.ilECSCategorySettings.php';	
				$ecs = new ilECSCategorySettings($this->object);			
				if($ecs->handleSettingsUpdate())
				{
					return $this->afterUpdate();
				}						
			}

			// display form to correct errors
			$this->setEditTabs();
			$form->setValuesByPost();
			$this->tpl->setContent($form->getHTML());
		}
	}

	/**
	 * Edit title and translations
	 */
	function editTranslationsObject($a_get_post_values = false, $a_add = false)
	{
		global $tpl;

		$this->lng->loadLanguageModule($this->object->getType());
		$this->setEditTabs("settings_trans");

		include_once("./Services/Object/classes/class.ilObjectTranslationTableGUI.php");
		$table = new ilObjectTranslationTableGUI($this, "editTranslations", true,
			"Translation");
		if ($a_get_post_values)
		{
			$vals = array();
			foreach($_POST["title"] as $k => $v)
			{
				$vals[] = array("title" => $v,
					"desc" => $_POST["desc"][$k],
					"lang" => $_POST["lang"][$k],
					"default" => ($_POST["default"] == $k));
			}
			$table->setData($vals);
		}
		else
		{
			$data = $this->object->getTranslations();
			foreach($data["Fobject"] as $k => $v)
			{
				$data["Fobject"][$k]["default"] = ($k == $data["default_language"]);
			}
			if($a_add)
			{
				$data["Fobject"][++$k]["title"] = "";
			}
			$table->setData($data["Fobject"]);
		}
		$tpl->setContent($table->getHTML());
	}

	/**
	 * Save title and translations
	 */
	function saveTranslationsObject()
	{
		if (!$this->checkPermissionBool("write"))
		{
			$this->ilias->raiseError($this->lng->txt("permission_denied"),$this->ilias->error_obj->MESSAGE);
		}

		// default language set?
		if (!isset($_POST["default"]))
		{
			ilUtil::sendFailure($this->lng->txt("msg_no_default_language"));
			return $this->editTranslationsObject(true);
		}

		// all languages set?
		if (array_key_exists("",$_POST["lang"]))
		{
			ilUtil::sendFailure($this->lng->txt("msg_no_language_selected"));
			return $this->editTranslationsObject(true);
		}

		// no single language is selected more than once?
		if (count(array_unique($_POST["lang"])) < count($_POST["lang"]))
		{
			ilUtil::sendFailure($this->lng->txt("msg_multi_language_selected"));
			return $this->editTranslationsObject(true);
		}

		// save the stuff
		$this->object->removeTranslations();
		foreach($_POST["title"] as $k => $v)
		{
			// update object data if default
			$is_default = ($_POST["default"] == $k);
			if($is_default)
			{
				$this->object->setTitle(ilUtil::stripSlashes($v));
				$this->object->setDescription(ilUtil::stripSlashes($_POST["desc"][$k]));
				$this->object->update();
			}

			$this->object->addTranslation(
				ilUtil::stripSlashes($v),
				ilUtil::stripSlashes($_POST["desc"][$k]),
				ilUtil::stripSlashes($_POST["lang"][$k]),
				$is_default);
		}

		ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), true);
		$this->ctrl->redirect($this, "editTranslations");
	}

	/**
	 * Add a translation
	 */
	function addTranslationObject()
	{
		if($_POST["title"])
		{
			$k = max(array_keys($_POST["title"]));
			$k++;
			$_POST["title"][$k] = "";
			$this->editTranslationsObject(true);
		}
		else
		{
			$this->editTranslationsObject(false, true);
		}
	}

	/**
	 * Remove translation
	 */
	function deleteTranslationsObject()
	{
		foreach($_POST["title"] as $k => $v)
		{			
			if ($_POST["check"][$k])
			{
				// default translation cannot be deleted
				if($k != $_POST["default"])
				{
					unset($_POST["title"][$k]);
					unset($_POST["desc"][$k]);
					unset($_POST["lang"][$k]);
				}
				else
				{
					ilUtil::sendFailure($this->lng->txt("msg_no_default_language"));
					return $this->editTranslationsObject();
				}
			}
		}
		$this->saveTranslationsObject();
	}

	/**
	* display form for category import
	*/
	function importCategoriesFormObject ()
	{
		ilObjCategoryGUI::_importCategoriesForm($this->ref_id, $this->tpl);
	}

	/**
	* display form for category import (static, also called by RootFolderGUI)
	*/
	function _importCategoriesForm ($a_ref_id, &$a_tpl)
	{
		global $lng, $rbacreview;

		$a_tpl->addBlockfile("ADM_CONTENT", "adm_content", "tpl.cat_import_form.html",
			"Modules/Category");

		$a_tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));

		$a_tpl->setVariable("TXT_IMPORT_CATEGORIES", $lng->txt("import_categories"));
		$a_tpl->setVariable("TXT_HIERARCHY_OPTION", $lng->txt("import_cat_localrol"));
		$a_tpl->setVariable("TXT_IMPORT_FILE", $lng->txt("import_file"));
		$a_tpl->setVariable("TXT_IMPORT_TABLE", $lng->txt("import_cat_table"));

		$a_tpl->setVariable("BTN_IMPORT", $lng->txt("import"));
		$a_tpl->setVariable("BTN_CANCEL", $lng->txt("cancel"));

		// NEED TO FILL ADOPT_PERMISSIONS HTML FORM....
		$parent_role_ids = $rbacreview->getParentRoleIds($a_ref_id,true);
		
		// sort output for correct color changing
		ksort($parent_role_ids);
		
		foreach ($parent_role_ids as $key => $par)
		  {
		    if ($par["obj_id"] != SYSTEM_ROLE_ID)
		      {
			$check = ilUtil::formCheckbox(0,"adopt[]",$par["obj_id"],1);
			$output["adopt"][$key]["css_row_adopt"] = ilUtil::switchColor($key, "tblrow1", "tblrow2");
			$output["adopt"][$key]["check_adopt"] = $check;
			$output["adopt"][$key]["role_id"] = $par["obj_id"];
			$output["adopt"][$key]["type"] = ($par["type"] == 'role' ? 'Role' : 'Template');
			$output["adopt"][$key]["role_name"] = $par["title"];
		      }
		  }
		
		//var_dump($output);

		// BEGIN ADOPT PERMISSIONS
		foreach ($output["adopt"] as $key => $value)
		  {
		    $a_tpl->setCurrentBlock("ADOPT_PERM_ROW");
		    $a_tpl->setVariable("CSS_ROW_ADOPT",$value["css_row_adopt"]);
		    $a_tpl->setVariable("CHECK_ADOPT",$value["check_adopt"]);
		    $a_tpl->setVariable("LABEL_ID",$value["role_id"]);
		    $a_tpl->setVariable("TYPE",$value["type"]);
		    $a_tpl->setVariable("ROLE_NAME",$value["role_name"]);
		    $a_tpl->parseCurrentBlock();
		  }
	}


	/**
	* import cancelled
	*
	* @access private
	*/
	function importCancelledObject()
	{
		$this->ctrl->redirect($this);
	}

	/**
	* get user import directory name
	*/
	function _getImportDir()
	{
		return ilUtil::getDataDir()."/cat_import";
	}

	/**
	* import categories
	*/
	function importCategoriesObject()
	{
		ilObjCategoryGUI::_importCategories($_GET["ref_id"]);
		// call to importCategories with $withrol = 0
		ilObjCategoryGUI::_importCategories($_GET["ref_id"], 0);
	}
	
        /**
	 * import categories with local rol
	 */
	function importCategoriesWithRolObject()
	{
	
	  //echo "entra aqui";
	  // call to importCategories with $withrol = 1
	  ilObjCategoryGUI::_importCategories($_GET["ref_id"], 1);
	}

	/**
	* import categories (static, also called by RootFolderGUI)
	*/
	
	function _importCategories($a_ref_id, $withrol_tmp)	
	{
		global $lng;

		require_once("./Modules/Category/classes/class.ilCategoryImportParser.php");

		$import_dir = ilObjCategoryGUI::_getImportDir();

		// create user import directory if necessary
		if (!@is_dir($import_dir))
		{
			ilUtil::createDirectory($import_dir);
		}

		// move uploaded file to user import directory

		$file_name = $_FILES["importFile"]["name"];

		// added to prevent empty file names
		if (!strcmp($file_name,"")) {
		  ilUtil::sendFailure($lng->txt("no_import_file_found"), true);
		  $this->ctrl->redirect($this);
		}

		$parts = pathinfo($file_name);
		$full_path = $import_dir."/".$file_name;
		//move_uploaded_file($_FILES["importFile"]["tmp_name"], $full_path);
		ilUtil::moveUploadedFile($_FILES["importFile"]["tmp_name"], $file_name, $full_path);

		// unzip file
		ilUtil::unzip($full_path);

		$subdir = basename($parts["basename"],".".$parts["extension"]);
		$xml_file = $import_dir."/".$subdir."/".$subdir.".xml";
		// CategoryImportParser
		//var_dump($_POST);
		$importParser = new ilCategoryImportParser($xml_file, $a_ref_id, $withrol_tmp);
		$importParser->startParsing();

		ilUtil::sendSuccess($lng->txt("categories_imported"), true);
		$this->ctrl->redirect($this);
	}
	
	/**
	* Reset filter
	* (note: this function existed before data table filter has been introduced
	*/
	protected function resetFilterObject()
	{
		include_once("./Services/User/classes/class.ilUserTableGUI.php");
		$utab = new ilUserTableGUI($this, "listUsers",ilUserTableGUI::MODE_LOCAL_USER);
		$utab->resetOffset();
		$utab->resetFilter();

		// from "old" implementation
		$this->listUsersObject();
	}
	
	/**
	 * Apply filter
	 * @return 
	 */
	protected function applyFilterObject()
	{
		global $ilTabs;
		
		include_once("./Services/User/classes/class.ilUserTableGUI.php");
		$utab = new ilUserTableGUI($this, "listUsers", ilUserTableGUI::MODE_LOCAL_USER);
		$utab->resetOffset();
		$utab->writeFilterToSession();
		$this->listUsersObject();
	}

	// METHODS for local user administration
	function listUsersObject($show_delete = false)
	{
		global $ilUser,$rbacreview, $ilToolbar;

		include_once './Services/User/classes/class.ilLocalUser.php';
		include_once './Services/User/classes/class.ilObjUserGUI.php';

		global $rbacsystem,$rbacreview;

		if(!$rbacsystem->checkAccess("cat_administrate_users",$this->object->getRefId()))
		{
			$this->ilias->raiseError($this->lng->txt("msg_no_perm_admin_users"),$this->ilias->error_obj->MESSAGE);
		}
		$this->tabs_gui->setTabActive('administrate_users');



		$this->tpl->addBlockfile('ADM_CONTENT','adm_content','tpl.cat_admin_users.html',
			"Modules/Category");

		if(count($rbacreview->getGlobalAssignableRoles()) or in_array(SYSTEM_ROLE_ID,$rbacreview->assignedRoles($ilUser->getId())))
		{
			$ilToolbar->addButton(
				$this->lng->txt('add_user'),
				$this->ctrl->getLinkTargetByClass('ilobjusergui','create')
			);
	
			$ilToolbar->addButton(
				$this->lng->txt('import_users'),
				$this->ctrl->getLinkTargetByClass('ilobjuserfoldergui','importUserForm')
			);
		}
		else
		{
			ilUtil::sendInfo($this->lng->txt('no_roles_user_can_be_assigned_to'));
		}

		if($show_delete)
		{
			$this->tpl->setCurrentBlock("confirm_delete");
			$this->tpl->setVariable("CONFIRM_FORMACTION",$this->ctrl->getFormAction($this));
			$this->tpl->setVariable("TXT_CANCEL",$this->lng->txt('cancel'));
			$this->tpl->setVariable("CONFIRM_CMD",'performDeleteUsers');
			$this->tpl->setVariable("TXT_CONFIRM",$this->lng->txt('delete'));
			$this->tpl->parseCurrentBlock();
		}
		
		$this->lng->loadLanguageModule('user');
		
		include_once("./Services/User/classes/class.ilUserTableGUI.php");
		$utab = new ilUserTableGUI($this, 'listUsers',ilUserTableGUI::MODE_LOCAL_USER);
		$this->tpl->setVariable('USERS_TABLE',$utab->getHTML());

		return true;
	}

	/**
	 * Show auto complete results
	 */
	protected function addUserAutoCompleteObject()
	{
		include_once './Services/User/classes/class.ilUserAutoComplete.php';
		$auto = new ilUserAutoComplete();
		$auto->setSearchFields(array('login','firstname','lastname','email'));
		$auto->enableFieldSearchableCheck(true);
		echo $auto->getList($_REQUEST['query']);
		exit();
	}


	function performDeleteUsersObject()
	{
		include_once './Services/User/classes/class.ilLocalUser.php';
		$this->checkPermission("cat_administrate_users");

		foreach($_POST['user_ids'] as $user_id)
		{
			if(!in_array($user_id,ilLocalUser::_getAllUserIds($this->object->getRefId())))
			{
				die('user id not valid');
			}
			if(!$tmp_obj =& ilObjectFactory::getInstanceByObjId($user_id,false))
			{
				continue;
			}
			$tmp_obj->delete();
		}
		ilUtil::sendSuccess($this->lng->txt('deleted_users'));
		$this->listUsersObject();

		return true;
	}
			
	function deleteUsersObject()
	{
		$this->checkPermission("cat_administrate_users");
		if(!count($_POST['id']))
		{
			ilUtil::sendFailure($this->lng->txt('no_users_selected'));
			$this->listUsersObject();
			
			return true;
		}
		
		include_once './Services/Utilities/classes/class.ilConfirmationGUI.php';
		$confirm = new ilConfirmationGUI();
		$confirm->setFormAction($this->ctrl->getFormAction($this));
		$confirm->setHeaderText($this->lng->txt('sure_delete_selected_users'));
		$confirm->setConfirm($this->lng->txt('delete'), 'performDeleteUsers');
		$confirm->setCancel($this->lng->txt('cancel'), 'listUsers');
		
		foreach($_POST['id'] as $user)
		{
			$name = ilObjUser::_lookupName($user);
			
			$confirm->addItem(
				'user_ids[]',
				$user,
				$name['lastname'].', '.$name['firstname'].' ['.$name['login'].']'
			);
		}		
		$this->tpl->setContent($confirm->getHTML());
	}

	function assignRolesObject()
	{
		global $rbacreview,$ilTabs;
		
		$this->checkPermission("cat_administrate_users");

		include_once './Services/User/classes/class.ilLocalUser.php';

		if(!isset($_GET['obj_id']))
		{
			ilUtil::sendFailure('no_user_selected');
			$this->listUsersObject();

			return true;
		}

		$ilTabs->clearTargets();
		$ilTabs->setBackTarget($this->lng->txt('backto_lua'), $this->ctrl->getLinkTarget($this,'listUsers'));

		$roles = $this->__getAssignableRoles();
		
		if(!count($roles))
		{
			#ilUtil::sendInfo($this->lng->txt('no_roles_user_can_be_assigned_to'));
			#$this->listUsersObject();

			#return true;
		}
		
		$this->tpl->addBlockfile('ADM_CONTENT','adm_content','tpl.cat_role_assignment.html',
			"Modules/Category");

		$ass_roles = $rbacreview->assignedRoles($_GET['obj_id']);

		$counter = 0;
		foreach($roles as $role)
		{
			$role_obj =& ilObjectFactory::getInstanceByObjId($role['obj_id']);
			
			$disabled = false;
			$f_result[$counter][] = ilUtil::formCheckbox(in_array($role['obj_id'],$ass_roles) ? 1 : 0,
														 'role_ids[]',
														 $role['obj_id'],
														 $disabled);
			$f_result[$counter][] = $role_obj->getTitle();
			$f_result[$counter][] = $role_obj->getDescription();
			$f_result[$counter][] = $role['role_type'] == 'global' ? 
				$this->lng->txt('global') :
				$this->lng->txt('local');
			
			unset($role_obj);
			++$counter;
		}
		$this->__showRolesTable($f_result,"assignRolesObject");
	}

	function assignSaveObject()
	{
		global $rbacreview,$rbacadmin;
		$this->checkPermission("cat_administrate_users");

		include_once './Services/User/classes/class.ilLocalUser.php';
		// check hack
		if(!isset($_GET['obj_id']) or !in_array($_REQUEST['obj_id'],ilLocalUser::_getAllUserIds()))
		{
			ilUtil::sendFailure('no_user_selected');
			$this->listUsersObject();

			return true;
		}
		$roles = $this->__getAssignableRoles();

		// check minimum one global role
		if(!$this->__checkGlobalRoles($_POST['role_ids']))
		{
			ilUtil::sendFailure($this->lng->txt('no_global_role_left'));
			$this->assignRolesObject();

			return false;
		}
		
		$new_role_ids = $_POST['role_ids'] ? $_POST['role_ids'] : array();
		$assigned_roles = $rbacreview->assignedRoles((int) $_REQUEST['obj_id']);
		foreach($roles as $role)
		{
			if(in_array($role['obj_id'],$new_role_ids) and !in_array($role['obj_id'],$assigned_roles))
			{
				$rbacadmin->assignUser($role['obj_id'],(int) $_REQUEST['obj_id']);
			}
			if(in_array($role['obj_id'],$assigned_roles) and !in_array($role['obj_id'],$new_role_ids))
			{
				$rbacadmin->deassignUser($role['obj_id'],(int) $_REQUEST['obj_id']);
			}
		}
		ilUtil::sendSuccess($this->lng->txt('role_assignment_updated'));
		$this->assignRolesObject();
		
		return true;
	}

	// PRIVATE
	function __getAssignableRoles()
	{
		global $rbacreview,$ilUser;

		// check local user
		$tmp_obj =& ilObjectFactory::getInstanceByObjId($_REQUEST['obj_id']);
		// Admin => all roles
		if(in_array(SYSTEM_ROLE_ID,$rbacreview->assignedRoles($ilUser->getId())))
		{
			$global_roles = $rbacreview->getGlobalRolesArray();
		}
		elseif($tmp_obj->getTimeLimitOwner() == $this->object->getRefId())
		{
			$global_roles = $rbacreview->getGlobalAssignableRoles();
		}			
		else
		{
			$global_roles = array();
		}
		return $roles = array_merge($global_roles,
									$rbacreview->getAssignableChildRoles($this->object->getRefId()));
	}

	function __checkGlobalRoles($new_assigned)
	{
		global $rbacreview,$ilUser;

		$this->checkPermission("cat_administrate_users");

		// return true if it's not a local user
		$tmp_obj =& ilObjectFactory::getInstanceByObjId($_REQUEST['obj_id']);
		if($tmp_obj->getTimeLimitOwner() != $this->object->getRefId() and
		   !in_array(SYSTEM_ROLE_ID,$rbacreview->assignedRoles($ilUser->getId())))
		{
			return true;
		}

		// new assignment by form
		$new_assigned = $new_assigned ? $new_assigned : array();
		$assigned = $rbacreview->assignedRoles((int) $_GET['obj_id']);

		// all assignable globals
		if(!in_array(SYSTEM_ROLE_ID,$rbacreview->assignedRoles($ilUser->getId())))
		{
			$ga = $rbacreview->getGlobalAssignableRoles();
		}
		else
		{
			$ga = $rbacreview->getGlobalRolesArray();
		}
		$global_assignable = array();
		foreach($ga as $role)
		{
			$global_assignable[] = $role['obj_id'];
		}

		$new_visible_assigned_roles = array_intersect($new_assigned,$global_assignable);
		$all_assigned_roles = array_intersect($assigned,$rbacreview->getGlobalRoles());
		$main_assigned_roles = array_diff($all_assigned_roles,$global_assignable);

		if(!count($new_visible_assigned_roles) and !count($main_assigned_roles))
		{
			return false;
		}
		return true;
	}


	function __showRolesTable($a_result_set,$a_from = "")
	{
		$this->checkPermission("cat_administrate_users");

		$tbl =& $this->__initTableGUI();
		$tpl =& $tbl->getTemplateObject();

		// SET FORMAACTION
		$tpl->setCurrentBlock("tbl_form_header");

		$this->ctrl->setParameter($this,'obj_id',$_GET['obj_id']);
		$tpl->setVariable("FORMACTION",$this->ctrl->getFormAction($this));
		$tpl->parseCurrentBlock();

		// SET FOOTER BUTTONS
		$tpl->setVariable("COLUMN_COUNTS",4);
		$tpl->setVariable("IMG_ARROW", ilUtil::getImagePath("arrow_downright.png"));
		
		$tpl->setCurrentBlock("tbl_action_button");
		$tpl->setVariable("BTN_NAME","assignSave");
		$tpl->setVariable("BTN_VALUE",$this->lng->txt("change_assignment"));
		$tpl->parseCurrentBlock();
		
		$tpl->setCurrentBlock("tbl_action_row");
		$tpl->setVariable("TPLPATH",$this->tpl->tplPath);
		$tpl->parseCurrentBlock();

		$tmp_obj =& ilObjectFactory::getInstanceByObjId($_GET['obj_id']);
		$title = $this->lng->txt('role_assignment').' ('.$tmp_obj->getFullname().')';

		$tbl->setTitle($title,"icon_role.png",$this->lng->txt("role_assignment"));
		$tbl->setHeaderNames(array('',
								   $this->lng->txt("title"),
								   $this->lng->txt('description'),
								   $this->lng->txt("type")));
		$tbl->setHeaderVars(array("",
								  "title",
								  "description",
								  "type"),
							array("ref_id" => $this->object->getRefId(),
								  "cmd" => "assignRoles",
								  "obj_id" => $_GET['obj_id'],
								  "cmdClass" => "ilobjcategorygui",
								  "cmdNode" => $_GET["cmdNode"]));
		$tbl->setColumnWidth(array("4%","35%","45%","16%"));

		$this->set_unlimited = true;
		$this->__setTableGUIBasicData($tbl,$a_result_set,$a_from,true);
		$tbl->render();

		$this->tpl->setVariable("ROLES_TABLE",$tbl->tpl->get());

		return true;
	}		

	function __showUsersTable($a_result_set,$a_from = "",$a_footer = true)
	{
		$this->checkPermission("cat_administrate_users");
		
		$tbl =& $this->__initTableGUI();
		$tpl =& $tbl->getTemplateObject();

		// SET FORMAACTION
		$tpl->setCurrentBlock("tbl_form_header");

		$this->ctrl->setParameter($this,'sort_by',$_GET['sort_by']);
		$this->ctrl->setParameter($this,'sort_order',$_GET['sort_order']);
		$this->ctrl->setParameter($this,'offset',$_GET['offset']);
		$tpl->setVariable("FORMACTION",$this->ctrl->getFormAction($this));
		$tpl->parseCurrentBlock();


		if($a_footer)
		{
			// SET FOOTER BUTTONS
			$tpl->setVariable("COLUMN_COUNTS",6);
			$tpl->setVariable("IMG_ARROW", ilUtil::getImagePath("arrow_downright.png"));

			$tpl->setCurrentBlock("tbl_action_button");
			$tpl->setVariable("BTN_NAME","deleteUser");
			$tpl->setVariable("BTN_VALUE",$this->lng->txt("delete"));
			$tpl->parseCurrentBlock();
			
			$tpl->setCurrentBlock("tbl_action_row");
			$tpl->setVariable("TPLPATH",$this->tpl->tplPath);
			$tpl->parseCurrentBlock();
			
			$tbl->setFormName('cmd');
			$tbl->enable('select_all');
		}

		$tbl->setTitle($this->lng->txt("users"),"icon_usr.png",$this->lng->txt("users"));
		$tbl->setHeaderNames(array('',
								   $this->lng->txt("username"),
								   $this->lng->txt("firstname"),
								   $this->lng->txt("lastname"),
								   $this->lng->txt('context'),
								   $this->lng->txt('role_assignment')));
		$tbl->setHeaderVars(array("",
								  "login",
								  "firstname",
								  "lastname",
								  "context",
								  "role_assignment"),
							array("ref_id" => $this->object->getRefId(),
								  "cmd" => "listUsers",
								  "cmdClass" => "ilobjcategorygui",
								  "cmdNode" => $_GET["cmdNode"]));
		$tbl->setColumnWidth(array("1px","20%","20%","20%","20%","20%"));
		$tbl->setSelectAllCheckbox('user_ids');

		$this->__setTableGUIBasicData($tbl,$a_result_set,$a_from,true);
		$tbl->render();

		$this->tpl->setVariable("USERS_TABLE",$tbl->tpl->get());

		return true;
	}		

	function __setTableGUIBasicData(&$tbl,&$result_set,$a_from = "",$a_footer = true)
	{
		global $ilUser;

		switch ($a_from)
		{
			case "listUsersObject":
				$tbl->setOrderColumn($_GET["sort_by"]);
				$tbl->setOrderDirection($_GET["sort_order"]);
				$tbl->setOffset($_GET["offset"]);
				$tbl->setMaxCount($this->all_users_count);
				$tbl->setLimit($ilUser->getPref('hits_per_page'));
				$tbl->setFooter("tblfooter",$this->lng->txt("previous"),$this->lng->txt("next"));
				$tbl->setData($result_set);
				$tbl->disable('auto_sort');

				return true;


			case "assignRolesObject":
				$offset = $_GET["offset"];
				// init sort_by (unfortunatly sort_by is preset with 'title'
				if ($_GET["sort_by"] == "title" or empty($_GET["sort_by"]))
				{
					$_GET["sort_by"] = "login";
				}
				$order = $_GET["sort_by"];
				$direction = $_GET["sort_order"];
				break;
			
			case "clipboardObject":
				$offset = $_GET["offset"];
				$order = $_GET["sort_by"];
				$direction = $_GET["sort_order"];
				$tbl->disable("footer");
				break;
				
			default:
				$offset = $_GET["offset"];
				$order = $_GET["sort_by"];
				$direction = $_GET["sort_order"];
				break;
		}

		$tbl->setOrderColumn($order);
		$tbl->setOrderDirection($direction);
		$tbl->setOffset($offset);
		if($this->set_unlimited)
		{
			$tbl->setLimit($_GET["limit"]*100);
		}
		else
		{
			$tbl->setLimit($_GET['limit']);
		}
		$tbl->setMaxCount(count($result_set));

		if($a_footer)
		{
			$tbl->setFooter("tblfooter",$this->lng->txt("previous"),$this->lng->txt("next"));
		}
		else
		{
			$tbl->disable('footer');
		}
		$tbl->setData($result_set);
	}

	function &__initTableGUI()
	{
		include_once "./Services/Table/classes/class.ilTableGUI.php";

		return new ilTableGUI(0,false);
	}

	function __buildFilterSelect($a_parent_ids)
	{
		$action[0] = $this->lng->txt('all_users');
		$action[$this->object->getRefId()] = $this->lng->txt('users').
			' ('.ilObject::_lookupTitle(ilObject::_lookupObjId($this->object->getRefId())).')';

		foreach($a_parent_ids as $parent)
		{
			if($parent == $this->object->getRefId())
			{
				continue;
			}
			switch($parent)
			{
				case ilLocalUser::_getUserFolderId():
					$action[ilLocalUser::_getUserFolderId()] = $this->lng->txt('global_user'); 
					
					break;

				default:
					$action[$parent] = $this->lng->txt('users').' ('.ilObject::_lookupTitle(ilObject::_lookupObjId($parent)).')';

					break;
			}
		}
		return ilUtil::formSelect($_SESSION['filtered_users'][$this->object->getRefId()],"filter",$action,false,true);
	}
	
	function _goto($a_target)
	{
		global $ilAccess, $ilErr, $lng;

		if ($ilAccess->checkAccess("read", "", $a_target))
		{
			ilObjectGUI::_gotoRepositoryNode($a_target);
		}
		else if ($ilAccess->checkAccess("read", "", ROOT_FOLDER_ID))
		{
			ilUtil::sendFailure(sprintf($lng->txt("msg_no_perm_read_item"),
				ilObject::_lookupTitle(ilObject::_lookupObjId($a_target))), true);
			ilObjectGUI::_gotoRepositoryRoot();
		}

		$ilErr->raiseError($lng->txt("msg_no_perm_read"), $ilErr->FATAL);

	}


} // END class.ilObjCategoryGUI
?>
