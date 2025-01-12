<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once 'Services/Object/classes/class.ilObjectListGUI.php';

/**
 * Class ilObjForumListGUI
 * @author  Alex Killing <alex.killing@gmx.de>
 * $Id: class.ilObjForumListGUI.php 43009 2013-06-26 12:17:33Z mjansen $
 * @ingroup ModulesForum
 */
class ilObjForumListGUI extends ilObjectListGUI
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * @param int $a_child_id
	 */
	public function setChildId($a_child_id)
	{
		$this->child_id = $a_child_id;
	}

	/**
	 * @return int
	 */
	public function getChildId()
	{
		return $this->child_id;
	}

	public function init()
	{
		$this->static_link_enabled = true;
		$this->delete_enabled      = true;
		$this->cut_enabled         = true;
		$this->copy_enabled        = true;
		$this->subscribe_enabled   = true;
		$this->link_enabled        = true;
		$this->payment_enabled     = false;
		$this->info_screen_enabled = true;
		$this->type                = 'frm';
		$this->gui_class_name      = 'ilobjforumgui';

		// general commands array
		include_once 'Modules/Forum/classes/class.ilObjForumAccess.php';
		$this->commands = ilObjForumAccess::_getCommands();
	}

	/**
	 * @param int    $a_ref_id
	 * @param int    $a_obj_id
	 * @param string $a_title
	 * @param string $a_description
	 */
	public function initItem($a_ref_id, $a_obj_id, $a_title = "", $a_description = "")
	{
		parent::initItem($a_ref_id, $a_obj_id, $a_title, $a_description);
	}

	/**
	 * @return array
	 */
	public function getProperties()
	{
		/**
		 * @var $lng	  ilLanguage
		 * @var $ilUser   ilObjUser
		 * @var $ilAccess ilAccessHandler
		 */
		global $lng, $ilUser, $ilAccess;

		if(!$ilAccess->checkAccess('read', '', $this->ref_id))
		{
			return array();
		}

		$lng->loadLanguageModule('forum');

		$props = array();

		include_once 'Modules/Forum/classes/class.ilObjForumAccess.php';
		$properties       = ilObjForumAccess::getStatisticsByRefId($this->ref_id);
		$num_posts_total  = $properties['num_posts'];
		$num_unread_total = $properties['num_unread_posts'];
		$num_new_total    = $properties['num_new_posts'];
		$last_post        = ilObjForumAccess::getLastPostByRefId($this->ref_id);

		if(!$ilUser->isAnonymous())
		{
			if($this->getDetailsLevel() == ilObjectListGUI::DETAILS_ALL)
			{
				$alert   = ($num_unread_total > 0) ? true : false;
				$props[] = array(
					'alert'	=> $alert,
					'property' => $lng->txt('forums_articles') . ' (' . $lng->txt('unread') . ')',
					'value'	=> $num_posts_total . ' (' . $num_unread_total . ')'
				);

				// New
				$alert   = ($num_new_total > 0) ? true : false;
				$props[] = array(
					'alert'	=> $alert,
					'property' => $lng->txt('forums_new_articles'),
					'value'	=> $num_new_total
				);
			}
		}
		else
		{
			$props[] = array(
				'alert'	=> false,
				'property' => $lng->txt('forums_articles'),
				'value'	=> $num_posts_total
			);
		}

		include_once 'Modules/Forum/classes/class.ilForumProperties.php';
		if($this->getDetailsLevel() == ilObjectListGUI::DETAILS_ALL)
		{
			if(ilForumProperties::getInstance($this->obj_id)->isAnonymized())
			{
				$props[] = array(
					'alert'	=> false,
					'newline'  => false,
					'property' => $lng->txt('forums_anonymized'),
					'value'	=> $lng->txt('yes')
				);
			}
		}

		// Last Post
		if((int)$last_post['pos_pk'])
		{
			$lpCont = "<a class=\"il_ItemProperty\" target=\"" . ilFrameTargetInfo::_getFrame('MainContent') .
				"\" href=\"ilias.php?baseClass=ilRepositoryGUI&amp;cmd=viewThread&amp;cmdClass=ilobjforumgui&amp;target=true&amp;pos_pk=" .
				$last_post['pos_pk'] . "&amp;thr_pk=" . $last_post['pos_thr_fk'] . "&amp;ref_id=" .
				$this->ref_id . "#" . $last_post["pos_pk"] . "\">" .
				ilObjForumAccess::prepareMessageForLists($last_post['pos_message']) . "</a> " .
				strtolower($lng->txt('from')) . "&nbsp;";

			require_once 'Modules/Forum/classes/class.ilForumAuthorInformation.php';
			$authorinfo = new ilForumAuthorInformation(
				$last_post['pos_usr_id'],
				$last_post['pos_usr_alias'],
				$last_post['import_name'],
				array(
					 'class' => 'il_ItemProperty',
					 'href'  => 'ilias.php?baseClass=ilRepositoryGUI&amp;cmd=showUser&amp;cmdClass=ilobjforumgui&amp;ref_id=' . $this->ref_id . '&amp;user='.$last_post['pos_usr_id'].'&amp;offset=0&amp;backurl=' . urlencode('ilias.php?baseClass=ilRepositoryGUI&amp;ref_id=' . $_GET['ref_id'])
				)
			);

			$lpCont .= $authorinfo->getLinkedAuthorName();
			$lpCont .= ', ' . ilDatePresentation::formatDate(new ilDateTime($last_post['pos_date'], IL_CAL_DATETIME));

			$props[] = array(
				'alert'	=> false,
				'newline'  => true,
				'property' => $lng->txt('forums_last_post'),
				'value'	=> $lpCont
			);
		}

		return $props;
	}

	/**
	 * @param string $a_cmd
	 * @return string
	 */
	public function getCommandFrame($a_cmd)
	{
		return ilFrameTargetInfo::_getFrame('MainContent');
	}

	/**
	 * @param string $a_cmd
	 * @return string
	 */
	public function getCommandLink($a_cmd)
	{
		switch($a_cmd)
		{
			case 'thread':
				return 'ilias.php?baseClass=ilRepositoryGUI&amp;cmd=viewThread&amp;cmdClass=ilobjforumgui&amp;ref_id=' . $this->ref_id . '&amp;thr_pk=' . $this->getChildId();

			case 'posting':
				$thread_post = $this->getChildId();
				return 'ilias.php?baseClass=ilRepositoryGUI&amp;cmd=viewThread&amp;cmdClass=ilobjforumgui&amp;target=1&amp;ref_id=' . $this->ref_id . '&amp;thr_pk=' . $thread_post[0] . '&amp;pos_pk=' . $thread_post[1] . '#' . $thread_post[1];

			default:
				return parent::getCommandLink($a_cmd);
		}
	}
}