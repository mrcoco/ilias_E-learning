<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2006 ILIAS open source, University of Cologne            |
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

/** 
* @defgroup 
* 
* @author Stefan Meyer <meyer@leifos.com>
* @version $Id$
* 
* 
* @ingroup Services/PrivacySecurity
*/
class ilExportFieldsInfo
{
	private static $instance = null;
	
	private $settings;
	private $db;
	private $lng;
	
	private $obj_type = '';
	
	private $possible_fields = array();
	
	/**
	 * Private Singleton Constructor. Use getInstance
	 *
	 * @access private
	 * 
	 */
	private function __construct($a_type)
	{
	 	global $ilDB,$ilSetting,$lng;
	 	
	 	$this->db = $ilDB;
	 	$this->lng = $lng;
	 	$this->settings = $ilSetting;
	 	
		$this->obj_type = $a_type;
		
	 	$this->read();
	}
	
	/**
	 * Get Singleton Instance
	 *
	 * @access public
	 * 
	 */
	public static function _getInstanceByType($a_type)
	{
	 	if(is_object(self::$instance[$a_type]))
	 	{
	 		return self::$instance[$a_type];
	 	}
	 	return self::$instance[$a_type] = new ilExportFieldsInfo($a_type);
	}
	
	/**
	 * Get object type
	 * @return 
	 */
	public function getType()
	{
		return $this->obj_type;
	}
	
	/**
	 * Check if field is exportable
	 *
	 * @access public
	 * @param string field name
	 * @return bool
	 * 
	 */
	public function isExportable($a_field_name)
	{
		return array_key_exists($a_field_name,$this->possible_fields);
	}
	
	/**
	 * Get informations (exportable) about user data profile fields
	 *
	 * @access public
	 * 
	 */
	public function getFieldsInfo()
	{
	 	return $this->possible_fields;
	}
	
	/**
	 * Get Exportable Fields
	 *
	 * @access public
	 */
	public function getExportableFields()
	{
	 	foreach($this->possible_fields as $field => $exportable)
	 	{
	 		if($exportable)
	 		{
	 			$fields[] = $field;
	 		}
	 	}
	 	return $fields ? $fields : array();
	}
	
	/**
	 * Get selectable fields
	 * @return 
	 */
	public function getSelectableFieldsInfo($a_obj_id)
	{
		global $lng;
		
		$fields = array();
		foreach($this->getExportableFields() as $field)
		{
			switch($field)
			{
				case 'lastname':
				case 'firstname':
					break;
	
				case 'username':
					$fields['login']['txt'] = $lng->txt('username');
					$fields['login']['default'] = 1;
					break;
				default:
					$fields[$field]['txt'] = $lng->txt($field);
					$fields[$field]['default'] = 0;
					break;
			}
		}
		
		include_once './Services/User/classes/class.ilUserDefinedFields.php';
		if($this->getType() == 'crs')
		{
			$udf = ilUserDefinedFields::_getInstance()->getCourseExportableFields();
		}
		elseif($this->getType() == 'grp')
		{
			$udf = ilUserDefinedFields::_getInstance()->getGroupExportableFields();
		}		
		if($udf)
		{
			foreach($udf as $field_id => $field)
			{
				$fields['udf_'.$field_id]['txt'] = $field['field_name'];
				$fields['udf_'.$field_id]['default'] = 0;
			}
		}
		
		include_once './Modules/Course/classes/Export/class.ilCourseDefinedFieldDefinition.php';
		$cdf = ilCourseDefinedFieldDefinition::_getFields($a_obj_id);
		foreach($cdf as $def)
		{
			$fields['odf_'.$def->getId()]['txt'] = $def->getName();
			$fields['odf_'.$def->getId()]['default'] = 0;
		}
		
		return $fields;
	}
	
	/**
	 * Get exportable fields as info string 
	 *
	 * @access public
	 * @return string info page string
	 */
	public function exportableFieldsToInfoString()
	{
		$fields = array();
		foreach($this->getExportableFields() as $field)
		{
			$fields[] = $this->lng->txt($field);
		}
		return implode('<br />',$fields);
	}
	
	/**
	 * Read info about exportable fields
	 *
	 * @access private
	 * 
	 */
	private function read()
	{
		include_once './Services/User/classes/class.ilUserProfile.php';
		
		$profile = new ilUserProfile();
		$profile->skipGroup('settings');
		
		foreach($profile->getStandardFields() as $key => $data)
		{
			if($this->getType() == 'crs')
			{
				if(!$data['course_export_hide'])
				{
					if(isset($data['course_export_fix_value']) and $data['course_export_fix_value'])
					{
						$this->possible_fields[$key] = $data['course_export_fix_value'];
					}
					else
					{
						$this->possible_fields[$key] = 0;
					}
				}
			}
			elseif($this->getType() == 'grp')
			{
				if(!$data['group_export_hide'])
				{
					if(isset($data['group_export_fix_value']) and $data['group_export_fix_value'])
					{
						$this->possible_fields[$key] = $data['group_export_fix_value'];
					}
					else
					{
						$this->possible_fields[$key] = 0;
					}
				}
			}
		}
		$settings_all = $this->settings->getAll();

		switch($this->getType())
		{
			case 'crs':
				$field_prefix = 'usr_settings_course_export_';
				break;
				
			case 'grp':
				$field_prefix = 'usr_settings_group_export_';
				break;
		}
		
		foreach($settings_all as $key => $value)
		{
			if(stristr($key,$field_prefix) and $value)
			{
				$field_parts = explode('_',$key);
				$field = $field_parts[count($field_parts) - 1];
				if(array_key_exists($field,$this->possible_fields))
				{
					$this->possible_fields[$field] = 1;
				}
			}
		}
		
		
		return true;
	}
}
?>