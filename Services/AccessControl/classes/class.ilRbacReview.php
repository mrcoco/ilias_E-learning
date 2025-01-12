<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
* class ilRbacReview
*  Contains Review functions of core Rbac.
*  This class offers the possibility to view the contents of the user <-> role (UR) relation and
*  the permission <-> role (PR) relation.
*  For example, from the UA relation the administrator should have the facility to view all user assigned to a given role.
*  
* 
* @author Stefan Meyer <meyer@leifos.com>
* @author Sascha Hofmann <saschahofmann@gmx.de>
* 
* @version $Id: class.ilRbacReview.php 42732 2013-06-14 13:21:56Z smeyer $
* 
* @ingroup ServicesAccessControl
*/
class ilRbacReview
{
	const FILTER_ALL = 1;
	const FILTER_ALL_GLOBAL = 2;
	const FILTER_ALL_LOCAL = 3;
	const FILTER_INTERNAL = 4;
	const FILTER_NOT_INTERNAL = 5;
	const FILTER_TEMPLATES = 6;

	protected $assigned_roles = array();
	var $log = null;

    // Cache operation ids
    private static $_opsCache = null;

	/**
	* Constructor
	* @access	public
	*/
	function ilRbacReview()
	{
		global $ilDB,$ilErr,$ilias,$ilLog;

		$this->log =& $ilLog;

		// set db & error handler
		(isset($ilDB)) ? $this->ilDB =& $ilDB : $this->ilDB =& $ilias->db;
		
		if (!isset($ilErr))
		{
			$ilErr = new ilErrorHandling();
			$ilErr->setErrorHandling(PEAR_ERROR_CALLBACK,array($ilErr,'errorHandler'));
		}
		else
		{
			$this->ilErr =& $ilErr;
		}
	}

	/**
	* Finds all role ids that match the specified user friendly role mailbox address list.
	*
	* The role mailbox name address list is an e-mail address list according to IETF RFC 822:
	*
	* address list  = role mailbox, {"," role mailbox } ;
	* role mailbox  = "#", local part, ["@" domain] ;
	*
	* Examples: The following role mailbox names are all resolved to the role il_crs_member_123:
	*
	*    #Course.A
	*    #member@Course.A
	*    #il_crs_member_123@Course.A
	*    #il_crs_member_123
	*    #il_crs_member_123@ilias
	*
	* Examples: The following role mailbox names are all resolved to the role il_crs_member_345:
	*
	*    #member@[English Course]
	*    #il_crs_member_345@[English Course]
	*    #il_crs_member_345
	*    #il_crs_member_345@ilias
	*
	* If only the local part is specified, or if domain is equal to "ilias", ILIAS compares
	* the title of role objects with local part. Only roles that are not in a trash folder
	* are considered for the comparison.
	*
	* If a domain is specified, and if the domain is not equal to "ilias", ILIAS compares
	* the title of objects with the domain. Only objects that are not in a trash folder are
	* considered for the comparison. Then ILIAS searches for local roles which contain
	* the local part in their title. This allows for abbreviated role names, e.g. instead of
	* having to specify #il_grp_member_345@MyGroup, it is sufficient to specify #member@MyGroup.
	*
	* The address list may contain addresses thate are not role mailboxes. These addresses
	* are ignored.
	*
	* If a role mailbox address is ambiguous, this function returns the ID's of all role
	* objects that are possible recipients for the role mailbox address. 
	*
	* If Pear Mail is not installed, then the mailbox address 
	*
	*
	* @access	public
	* @param	string	IETF RFX 822 address list containing role mailboxes.
	* @return	int[] Array with role ids that were found
	*/
	function searchRolesByMailboxAddressList($a_address_list)
	{
		global $ilDB;
		
		$role_ids = array();
		
		include_once "Services/Mail/classes/class.ilMail.php";
		if(ilMail::_usePearMail())
		{
			require_once './Services/PEAR/lib/Mail/RFC822.php';
			$parser = new Mail_RFC822();
			$parsedList = $parser->parseAddressList($a_address_list, "ilias", false, true);
			foreach ($parsedList as $address)
			{
				$local_part = $address->mailbox;
				if (strpos($local_part,'#') !== 0 &&
				    !($local_part{0} == '"' && $local_part{1} == "#"))
				{
					// A local-part which doesn't start with a '#' doesn't denote a role.
					// Therefore we can skip it.
					continue;
				}

				$local_part = substr($local_part, 1);

				/* If role contains spaces, eg. 'foo role', double quotes are added which have to be
				   removed here.*/
				if( $local_part{0} == '#' && $local_part{strlen($local_part) - 1} == '"' )
				{
					$local_part = substr($local_part, 1);
					$local_part = substr($local_part, 0, strlen($local_part) - 1);
				}

				if (substr($local_part,0,8) == 'il_role_')
				{
					$role_id = substr($local_part,8);
					$query = "SELECT t.tree ".
						"FROM rbac_fa fa ".
						"JOIN tree t ON t.child = fa.parent ".
						"WHERE fa.rol_id = ".$this->ilDB->quote($role_id,'integer')." ".
						"AND fa.assign = 'y' ".
						"AND t.tree = 1";
					$r = $ilDB->query($query);
					if ($r->numRows() > 0)
					{
						$role_ids[] = $role_id;
					}
					continue;
				}


				$domain = $address->host;
				if (strpos($domain,'[') == 0 && strrpos($domain,']'))
				{
					$domain = substr($domain,1,strlen($domain) - 2);
				}
				if (strlen($local_part) == 0)
				{
					$local_part = $domain;
					$address->host = 'ilias';
					$domain = 'ilias';
				}

				if (strtolower($address->host) == 'ilias')
				{
					// Search for roles = local-part in the whole repository
					$query = "SELECT dat.obj_id ".
						"FROM object_data dat ".
						"JOIN rbac_fa fa ON fa.rol_id = dat.obj_id ".
						"JOIN tree t ON t.child = fa.parent ".
						"WHERE dat.title =".$this->ilDB->quote($local_part,'text')." ".
						"AND dat.type = 'role' ".
						"AND fa.assign = 'y' ".
						"AND t.tree = 1";
				}
				else
				{
					// Search for roles like local-part in objects = host
					$query = "SELECT rdat.obj_id ".
						"FROM object_data odat ".
						"JOIN object_reference oref ON oref.obj_id = odat.obj_id ".
						"JOIN tree otree ON otree.child = oref.ref_id ".
						"JOIN tree rtree ON rtree.parent = otree.child ".
						"JOIN rbac_fa rfa ON rfa.parent = rtree.child ".
						"JOIN object_data rdat ON rdat.obj_id = rfa.rol_id ".
						"WHERE odat.title = ".$this->ilDB->quote($domain,'text')." ".
						"AND otree.tree = 1 AND rtree.tree = 1 ".
						"AND rfa.assign = 'y' ".
						"AND rdat.title LIKE ".
							$this->ilDB->quote('%'.preg_replace('/([_%])/','\\\\$1',$local_part).'%','text');
				}
				$r = $ilDB->query($query);

				$count = 0;
				while($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
				{
					$role_ids[] = $row->obj_id;
					$count++;
				}

				// Nothing found?
				// In this case, we search for roles = host.
				if ($count == 0 && strtolower($address->host) == 'ilias')
				{
					$q = "SELECT dat.obj_id ".
						"FROM object_data dat ".
						"JOIN object_reference ref ON ref.obj_id = dat.obj_id ".
						"JOIN tree t ON t.child = ref.ref_id ".
						"WHERE dat.title = ".$this->ilDB->quote($domain ,'text')." ".
						"AND dat.type = 'role' ".
						"AND t.tree = 1 ";
					$r = $this->ilDB->query($q);

					while($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
					{
						$role_ids[] = $row->obj_id;
					}
				}
				//echo '<br>ids='.var_export($role_ids,true);
			}
		} 
		else 
		{
			// the following code is executed, when Pear Mail is
			// not installed

			$titles = explode(',', $a_address_list);
			
			$titleList = '';
			foreach ($titles as $title)
			{
				if (strlen($inList) > 0)
				{
					$titleList .= ',';
				}
				$title = trim($title);
				if (strpos($title,'#') == 0) 
				{
					$titleList .= $this->ilDB->quote(substr($title, 1));
				}
			}	
			if (strlen($titleList) > 0)
			{
				$q = "SELECT obj_id ".
					"FROM object_data ".
					"WHERE title IN (".$titleList.") ".
					"AND type='role'";
				$r = $this->ilDB->query($q);
				while ($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
				{
					$role_ids[] = $row->obj_id;
				}
			}
		}

		return $role_ids;
	}
	
	/**
	 * Returns the mailbox address of a role.
	 *
	 * Example 1: Mailbox address for an ILIAS reserved role name
     * ----------------------------------------------------------
     * The il_crs_member_345 role of the course object "English Course 1" is 
	 * returned as one of the following mailbox addresses:
	 *
	 * a)   Course Member <#member@[English Course 1]>
	 * b)   Course Member <#il_crs_member_345@[English Course 1]>
	 * c)   Course Member <#il_crs_member_345>
	 *
	 * Address a) is returned, if the title of the object is unique, and
 	 * if there is only one local role with the substring "member" defined for
	 * the object.
     *
	 * Address b) is returned, if the title of the object is unique, but 
     * there is more than one local role with the substring "member" in its title.
     *
     * Address c) is returned, if the title of the course object is not unique.
     *
     *
	 * Example 2: Mailbox address for a manually defined role name
     * -----------------------------------------------------------
     * The "Admin" role of the category object "Courses" is 
	 * returned as one of the following mailbox addresses:
	 *
	 * a)   Course Administrator <#Admin@Courses>
	 * b)   Course Administrator <#Admin>
     * c)   Course Adminstrator <#il_role_34211>
	 *
	 * Address a) is returned, if the title of the object is unique, and
 	 * if there is only one local role with the substring "Admin" defined for
	 * the course object.
     *
	 * Address b) is returned, if the title of the object is not unique, but 
     * the role title is unique.
     *
     * Address c) is returned, if neither the role title nor the title of the
     * course object is unique. 
     *
     *
	 * Example 3: Mailbox address for a manually defined role title that can
     *            contains special characters in the local-part of a 
     *            mailbox address
     * --------------------------------------------------------------------
     * The "Author Courses" role of the category object "Courses" is 
	 * returned as one of the following mailbox addresses:
	 *
	 * a)   "#Author Courses"@Courses
     * b)   Author Courses <#il_role_34234>
	 *
	 * Address a) is returned, if the title of the role is unique.
     *
     * Address b) is returned, if neither the role title nor the title of the
     * course object is unique, or if the role title contains a quote or a
	 * backslash.
     *
	 *
	 * @param int a role id
	 * @param boolean is_localize whether mailbox addresses should be localized
	 * @return	String mailbox address or null, if role does not exist.
	 */
	function getRoleMailboxAddress($a_role_id, $is_localize = true)
	{
		global $log, $lng,$ilDB;

		include_once "Services/Mail/classes/class.ilMail.php";
		if (ilMail::_usePearMail())
		{
			// Retrieve the role title and the object title.
			$query = "SELECT rdat.title role_title,odat.title object_title, ".
				" oref.ref_id object_ref ".
				"FROM object_data rdat ".
				"JOIN rbac_fa fa ON fa.rol_id = rdat.obj_id ".
				"JOIN tree rtree ON rtree.child = fa.parent ".
				"JOIN object_reference oref ON oref.ref_id = rtree.parent ".
				"JOIN object_data odat ON odat.obj_id = oref.obj_id ".
				"WHERE rdat.obj_id = ".$this->ilDB->quote($a_role_id,'integer')." ".
				"AND fa.assign = 'y' ";
			$r = $ilDB->query($query);
			if (!$row = $ilDB->fetchObject($r))
			{
				//$log->write('class.ilRbacReview->getMailboxAddress('.$a_role_id.'): error role does not exist');
				return null; // role does not exist
			}
			$object_title = $row->object_title;
			$object_ref = $row->object_ref;
			$role_title = $row->role_title;


			// In a perfect world, we could use the object_title in the 
			// domain part of the mailbox address, and the role title
			// with prefix '#' in the local part of the mailbox address.
			$domain = $object_title;
			$local_part = $role_title;


			// Determine if the object title is unique
			$q = "SELECT COUNT(DISTINCT dat.obj_id) count ".
				"FROM object_data dat ".
				"JOIN object_reference ref ON ref.obj_id = dat.obj_id ".
				"JOIN tree ON tree.child = ref.ref_id ".
				"WHERE title = ".$this->ilDB->quote($object_title,'text')." ".
				"AND tree.tree = 1 ";
			$r = $this->ilDB->query($q);
			$row = $r->fetchRow(DB_FETCHMODE_OBJECT);

			// If the object title is not unique, we get rid of the domain.
			if ($row->count > 1)
			{
				$domain = null;
			}

			// If the domain contains illegal characters, we get rid of it.
			//if (domain != null && preg_match('/[\[\]\\]|[\x00-\x1f]/',$domain))
			// Fix for Mantis Bug: 7429 sending mail fails because of brakets
			// Fix for Mantis Bug: 9978 sending mail fails because of semicolon
			if ($domain != null && preg_match('/[\[\]\\]|[\x00-\x1f]|[\x28-\x29]|[;]/',$domain))
			{
				$domain = null;
			}

			// If the domain contains special characters, we put square
			//   brackets around it.
			if ($domain != null && 
					(preg_match('/[()<>@,;:\\".\[\]]/',$domain) ||
					preg_match('/[^\x21-\x8f]/',$domain))
				)
			{
				$domain = '['.$domain.']';
			}

			// If the role title is one of the ILIAS reserved role titles,
			//     we can use a shorthand version of it for the local part
			//     of the mailbox address.
			if (strpos($role_title, 'il_') === 0 && $domain != null)
			{
				$unambiguous_role_title = $role_title;

				$pos = strpos($role_title, '_', 3) + 1;
				$local_part = substr(
					$role_title, 
					$pos,  
					strrpos($role_title, '_') - $pos
				);
			}
			else
			{
				$unambiguous_role_title = 'il_role_'.$a_role_id;
			}

			// Determine if the local part is unique. If we don't have a
			// domain, the local part must be unique within the whole repositry.
			// If we do have a domain, the local part must be unique for that
			// domain.
			if ($domain == null)
			{
				$q = "SELECT COUNT(DISTINCT dat.obj_id) count ".
					"FROM object_data dat ".
					"JOIN object_reference ref ON ref.obj_id = dat.obj_id ".
					"JOIN tree ON tree.child = ref.ref_id ".
					"WHERE title = ".$this->ilDB->quote($local_part,'text')." ".
					"AND tree.tree = 1 ";
			}
			else
			{
				$q = "SELECT COUNT(rd.obj_id) count ".
					 "FROM object_data rd ".
					 "JOIN rbac_fa fa ON rd.obj_id = fa.rol_id ".
					 "JOIN tree t ON t.child = fa.parent ". 
					 "WHERE fa.assign = 'y' ".
					 "AND t.parent = ".$this->ilDB->quote($object_ref,'integer')." ".
					 "AND rd.title LIKE ".$this->ilDB->quote(
						'%'.preg_replace('/([_%])/','\\\\$1', $local_part).'%','text')." ";
			}

			$r = $this->ilDB->query($q);
			$row = $r->fetchRow(DB_FETCHMODE_OBJECT);

			// if the local_part is not unique, we use the unambiguous role title 
			//   instead for the local part of the mailbox address
			if ($row->count > 1)
			{
				$local_part = $unambiguous_role_title;
			}


			// If the local part contains illegal characters, we use
			//     the unambiguous role title instead.
			if (preg_match('/[\\"\x00-\x1f]/',$local_part)) 
			{
				$local_part = $unambiguous_role_title;
			}


			// Add a "#" prefix to the local part
			$local_part = '#'.$local_part;

			// Put quotes around the role title, if needed
			if (preg_match('/[()<>@,;:.\[\]\x20]/',$local_part))
			{
				$local_part = '"'.$local_part.'"';
			}

			$mailbox = ($domain == null) ?
					$local_part :
					$local_part.'@'.$domain;

			if ($is_localize)
			{
				if (substr($role_title,0,3) == 'il_')
				{
					$phrase = $lng->txt(substr($role_title, 0, strrpos($role_title,'_')));
				}
				else
				{
					$phrase = $role_title;
				}

				// make phrase RFC 822 conformant:
				// - strip excessive whitespace 
				// - strip special characters
				$phrase = preg_replace('/\s\s+/', ' ', $phrase);
				$phrase = preg_replace('/[()<>@,;:\\".\[\]]/', '', $phrase);

				$mailbox = $phrase.' <'.$mailbox.'>';
			}

			return $mailbox;
		}
		else 
		{
			$q = "SELECT title ".
				"FROM object_data ".
				"WHERE obj_id = ".$this->ilDB->quote($a_role_id ,'integer');
			$r = $this->ilDB->query($q);

			if ($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
			{
				return '#'.$row->title;
			}
			else
			{
				return null;
			}
		}
	}

	
	/**
	* Checks if a role already exists. Role title should be unique
	* @access	public
	* @param	string	role title
	* @param	integer	obj_id of role to exclude in the check. Commonly this is the current role you want to edit
	* @return	boolean	true if exists
	*/
	function roleExists($a_title,$a_id = 0)
	{
		global $ilDB;
		
		if (empty($a_title))
		{
			$message = get_class($this)."::roleExists(): No title given!";
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}
		
		$clause = ($a_id) ? " AND obj_id != ".$ilDB->quote($a_id)." " : "";
		
		$q = "SELECT DISTINCT(obj_id) obj_id FROM object_data ".
			 "WHERE title =".$ilDB->quote($a_title)." ".
			 "AND type IN('role','rolt')".
			 $clause." ";
		$r = $this->ilDB->query($q);

		while($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
		{
			return $row->obj_id;
		}
		return false;
	}

	/**
    * Note: This function performs faster than the new getParentRoles function,
    *       because it uses database indexes whereas getParentRoles needs
    *       a full table space scan.
	* 
	* Get parent roles in a path. If last parameter is set 'true'
	* it delivers also all templates in the path
	* @access	private
	* @param	array	array with path_ids
	* @param	boolean	true for role templates (default: false)
	* @return	array	array with all parent roles (obj_ids)
	*/
	function __getParentRoles($a_path,$a_templates,$a_keep_protected)
	{
		global $log,$ilDB;
		
		if (!isset($a_path) or !is_array($a_path))
		{
			$message = get_class($this)."::getParentRoles(): No path given or wrong datatype!";
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}

		$parent_roles = array();
		$role_hierarchy = array();
		
        // Select all role folders on a path using a single SQL-statement.
		// CREATE IN() STATEMENT
        $in = $ilDB->in('t.parent',$a_path,false,'integer');

        $q = "SELECT t.child,t.depth FROM tree t ".
             "JOIN object_reference r ON r.ref_id = t.child ".
             "JOIN object_data o ON o.obj_id = r.obj_id ".
             "WHERE ".$in." ".
             "AND o.type= ".$ilDB->quote('rolf','text')." ".
             "ORDER BY t.depth ASC";

        $r = $this->ilDB->query($q);
		
		
		// Sort by path (Administration -> Rolefolder is first element)
		$role_rows = array();
		while($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
		{
			
			$depth = ($row->child == ROLE_FOLDER_ID ? 0 : $row->depth);
			$role_rows[$depth]['child'] = $row->child;
		}
		ksort($role_rows,SORT_NUMERIC);

		foreach($role_rows as $row)
		{
			$roles = $this->getRoleListByObject($row['child'],$a_templates);
            foreach ($roles as $role)
            {
                $id = $role["obj_id"];
                $role["parent"] = $row['child'];
                $parent_roles[$id] = $role;

                if (!array_key_exists($role['obj_id'],$role_hierarchy))
                {
                    $role_hierarchy[$id] = $row['child'];
                }
            }
        }
		if (!$a_keep_protected)
		{
			return $this->__setProtectedStatus($parent_roles,$role_hierarchy,reset($a_path));
		}
		return $parent_roles;
	}

	/**
	* get an array of parent role ids of all parent roles, if last parameter is set true
	* you get also all parent templates
	* @access	public
	* @param	integer		ref_id of an object which is end node
	* @param	boolean		true for role templates (default: false)
	* @return	array       array(role_ids => role_data)
	*/
	function getParentRoleIds($a_endnode_id,$a_templates = false,$a_keep_protected = false)
	{
		global $tree,$log,$ilDB;

		if (!isset($a_endnode_id))
		{
			$GLOBALS['ilLog']->logStack();
			$message = get_class($this)."::getParentRoleIds(): No node_id (ref_id) given!";
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}
		
		//var_dump($a_endnode_id);exit;
		//$log->write("ilRBACreview::getParentRoleIds(), 0");	
		$pathIds  = $tree->getPathId($a_endnode_id);

		// add system folder since it may not in the path
		$pathIds[0] = SYSTEM_FOLDER_ID;
		//$log->write("ilRBACreview::getParentRoleIds(), 1");
		#return $this->getParentRoles($a_endnode_id,$a_templates,$a_keep_protected);
		return $this->__getParentRoles($pathIds,$a_templates,$a_keep_protected);
	}

	/**
	* Returns a list of roles in an container
	* @access	public
	* @param	integer	ref_id
	* @param	boolean	if true fetch template roles too
	* @return	array	set ids
	*/
	function getRoleListByObject($a_ref_id,$a_templates = false)
	{
		global $ilDB;
		
		if (!isset($a_ref_id) or !isset($a_templates))
		{
			$message = get_class($this)."::getRoleListByObject(): Missing parameter!".
					   "ref_id: ".$a_ref_id.
					   "tpl_flag: ".$a_templates;
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}

		$role_list = array();

		$where = $this->__setTemplateFilter($a_templates);
	
		$query = "SELECT * FROM object_data ".
			 "JOIN rbac_fa ON obj_id = rol_id ".
			 $where.
			 "AND object_data.obj_id = rbac_fa.rol_id ".
			 "AND rbac_fa.parent = ".$ilDB->quote($a_ref_id,'integer')." ";
			 
		$res = $ilDB->query($query);
		while ($row = $ilDB->fetchAssoc($res))
		{
			$row["desc"] = $row["description"];
			$row["user_id"] = $row["owner"];
			$role_list[] = $row;
		}

		$role_list = $this->__setRoleType($role_list);
		
		return $role_list;
	}
	
	/**
	* Returns a list of all assignable roles
	* @access	public
	* @param	boolean	if true fetch template roles too
	* @return	array	set ids
	*/
	function getAssignableRoles($a_templates = false,$a_internal_roles = false, $title_filter = '')
	{
		global $ilDB;

		$role_list = array();

		$where = $this->__setTemplateFilter($a_templates);

		$query = "SELECT * FROM object_data ".
			 "JOIN rbac_fa ON obj_id = rol_id ".
			 $where.
			 "AND rbac_fa.assign = 'y' ";

		if(strlen($title_filter))
		{
			$query .= (' AND '.$ilDB->like(
				'title',
				'text',
				$title_filter.'%'
			));
		}
		$res = $ilDB->query($query);

		while ($row = $ilDB->fetchAssoc($res))
		{
			$row["desc"] = $row["description"];
			$row["user_id"] = $row["owner"];
			$role_list[] = $row;
		}
		
		$role_list = $this->__setRoleType($role_list);

		return $role_list;
	}

	/**
	* Returns a list of assignable roles in a subtree of the repository
	* @access	public
	* @param	ref_id Rfoot node of subtree
	* @return	array	set ids
	*/
	function getAssignableRolesInSubtree($ref_id)
	{
		global $ilDB;
		
		$role_list = array();
		$where = $this->__setTemplateFilter(false);
		
		$query = "SELECT fa.*, dat.* ".
			"FROM tree root ".
			"JOIN tree node ON node.tree = root.tree ".
			"AND node.lft > root.lft AND node.rgt < root.rgt ".
			"JOIN object_reference ref ON ref.ref_id = node.child ".
			"JOIN rbac_fa fa ON fa.parent = ref.ref_id ".
			"JOIN object_data dat ON dat.obj_id = fa.rol_id ".
			"WHERE root.child = ".$this->ilDB->quote($ref_id,'integer')." ".
			"AND root.tree = 1 ".
			"AND fa.assign = 'y' ".
			"ORDER BY dat.title";
		$res = $ilDB->query($query);

		while($row = $ilDB->fetchAssoc($res))
		{
			$role_list[] = $row;
		}
		
		$role_list = $this->__setRoleType($role_list);
		return $role_list;
	}

	/**
	* Get all assignable roles under a specific node
	* @access	public
	* @param ref_id
	* @return	array	set ids
	*/
	function getAssignableChildRoles($a_ref_id)
	{
		global $ilDB;
		global $tree;

		$query = "SELECT fa.*, rd.* ".
			 "FROM object_data rd ".
			 "JOIN rbac_fa fa ON rd.obj_id = fa.rol_id ".
			 "JOIN tree t ON t.child = fa.parent ". 
			 "WHERE fa.assign = 'y' ".
			 "AND t.parent = ".$this->ilDB->quote($a_ref_id,'integer')." "
			;
		$res = $ilDB->query($query);
		while($row = $ilDB->fetchAssoc($res))
		{
			$roles_data[] = $row;
		}
		return $roles_data ? $roles_data : array();
	}
	
	/**
	* get roles and templates or only roles; returns string for where clause
	* @access	private
	* @param	boolean	true: with templates
	* @return	string	where clause
	*/
	function __setTemplateFilter($a_templates)
	{
		global $ilDB;
		
		if ($a_templates === true)
		{
			$where = "WHERE ".$ilDB->in('object_data.type',array('role','rolt'),false,'text')." ";
		}
		else
		{
			$where = "WHERE ".$ilDB->in('object_data.type',array('role'),false,'text')." ";
		}
		
		return $where;
	}

	/**
	* computes role type in role list array:
	* global: roles in ROLE_FOLDER_ID
	* local: assignable roles in other role folders
	* linked: roles with stoppped inheritance
	* template: role templates
	* 
	* @access	private
	* @param	array	role list
	* @return	array	role list with additional entry for role_type
	*/
	function __setRoleType($a_role_list)
	{
		foreach ($a_role_list as $key => $val)
		{
			// determine role type
			if ($val["type"] == "rolt")
			{
				$a_role_list[$key]["role_type"] = "template";
			}
			else
			{
				if ($val["assign"] == "y")
				{
					if ($val["parent"] == ROLE_FOLDER_ID)
					{
						$a_role_list[$key]["role_type"] = "global";
					}
					else
					{
						$a_role_list[$key]["role_type"] = "local";
					}
				}
				else
				{
					$a_role_list[$key]["role_type"] = "linked";
				}
			}
			
			if ($val["protected"] == "y")
			{
				$a_role_list[$key]["protected"] = true;
			}
			else
			{
				$a_role_list[$key]["protected"] = false;
			}
		}
		
		return $a_role_list;
	}

	/**
	 * Get the number of assigned users to roles
	 * @global ilDB $ilDB
	 * @param array $a_roles
	 * @return int
	 */
	public function getNumberOfAssignedUsers(Array $a_roles)
	{
		global $ilDB;

		$query = 'SELECT COUNT(DISTINCT(usr_id)) as num FROM rbac_ua '.
			'WHERE '.$ilDB->in('rol_id', $a_roles, false, 'integer').' ';

		$res = $ilDB->query($query);
		$row = $res->fetchRow(DB_FETCHMODE_OBJECT);
		return $row->num ? $row->num : 0;
	}
	
	/**
	* get all assigned users to a given role
	* @access	public
	* @param	integer	role_id
	* @param    array   columns to get form usr_data table (optional)
	* @return	array	all users (id) assigned to role OR arrays of user datas
	*/
	function assignedUsers($a_rol_id, $a_fields = NULL)
	{
		global $ilBench,$ilDB;
		
		$ilBench->start("RBAC", "review_assignedUsers");
		
		if (!isset($a_rol_id))
		{
			$message = get_class($this)."::assignedUsers(): No role_id given!";
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}
		
        $result_arr = array();

        if ($a_fields !== NULL and is_array($a_fields))
        {
            if (count($a_fields) == 0)
            {
                $select = "*";
            }
            else
            {
                if (($usr_id_field = array_search("usr_id",$a_fields)) !== false)
                    unset($a_fields[$usr_id_field]);

                $select = implode(",",$a_fields).",usr_data.usr_id";
                $select = addslashes($select);
            }

	        $ilDB->enableResultBuffering(false);
			$query = "SELECT ".$select." FROM usr_data ".
                 "LEFT JOIN rbac_ua ON usr_data.usr_id = rbac_ua.usr_id ".
                 "WHERE rbac_ua.rol_id =".$ilDB->quote($a_rol_id,'integer');
            $res = $ilDB->query($query);
            while($row = $ilDB->fetchAssoc($res))
            {
                $result_arr[] = $row;
            }
			$ilDB->enableResultBuffering(true);
        }
        else
        {
		    $ilDB->enableResultBuffering(false);
			$query = "SELECT usr_id FROM rbac_ua WHERE rol_id= ".$ilDB->quote($a_rol_id,'integer');
			
			$res = $ilDB->query($query);
            while($row = $ilDB->fetchAssoc($res))
            {
                array_push($result_arr,$row["usr_id"]);
            }
			$ilDB->enableResultBuffering(true);
        }
		
		$ilBench->stop("RBAC", "review_assignedUsers");

		return $result_arr;
	}

	/**
	* check if a specific user is assigned to specific role
	* @access	public
	* @param	integer		usr_id
	* @param	integer		role_id
	* @return	boolean
	*/
	function isAssigned($a_usr_id,$a_role_id)
	{
        // Quickly determine if user is assigned to a role
		global $ilDB;

	    $ilDB->setLimit(1,0);
	    $query = "SELECT usr_id FROM rbac_ua WHERE ".
                    "rol_id= ".$ilDB->quote($a_role_id,'integer')." ".
                    "AND usr_id= ".$ilDB->quote($a_usr_id);
		$res = $ilDB->query($query);

        return $res->numRows() == 1;
	}
    
	/**
	* check if a specific user is assigned to at least one of the
    * given role ids.
    * This function is used to quickly check whether a user is member
    * of a course or a group.
    *
	* @access	public
	* @param	integer		usr_id
	* @param	array[integer]		role_ids
	* @return	boolean
	*/
	function isAssignedToAtLeastOneGivenRole($a_usr_id,$a_role_ids)
	{
		global $ilDB;

	    $ilDB->setLimit(1,0);
	    $query = "SELECT usr_id FROM rbac_ua WHERE ".
                    $ilDB->in('rol_id',$a_role_ids,false,'integer').
                    " AND usr_id= ".$ilDB->quote($a_usr_id);
		$res = $ilDB->query($query);

        return $ilDB->numRows($res) == 1;
	}
	
	/**
	* get all assigned roles to a given user
	* @access	public
	* @param	integer		usr_id
	* @return	array		all roles (id) the user have
	*/
	function assignedRoles($a_usr_id)
	{
		global $ilDB;
		
		$role_arr = array();
		
		$query = "SELECT rol_id FROM rbac_ua WHERE usr_id = ".$ilDB->quote($a_usr_id,'integer');

		$res = $ilDB->query($query);
		while($row = $ilDB->fetchObject($res))
		{
			$role_arr[] = $row->rol_id;
		}
		return $role_arr ? $role_arr : array();
	}
	
	/**
	 * Get assigned global roles for an user
	 * @param int	$a_usr_id	Id of user account
	 */
	public function assignedGlobalRoles($a_usr_id)
	{
		global $ilDB;
		
		$query = "SELECT ua.rol_id FROM rbac_ua ua ".
			"JOIN rbac_fa fa ON ua.rol_id = fa.rol_id ".
			"WHERE usr_id = ".$ilDB->quote($a_usr_id,'integer').' '.
			"AND parent = ".$ilDB->quote(ROLE_FOLDER_ID)." ".
			"AND assign = 'y' ";
		
		$res = $ilDB->query($query);
		while($row = $ilDB->fetchObject($res))
		{
			$role_arr[] = $row->rol_id;
		}
		return $role_arr ? $role_arr : array();
	}

	/**
	* Check if its possible to assign users
	* @access	public
	* @param	integer	object id of role
	* @param	integer	ref_id of object in question
	* @return	boolean 
	*/
	function isAssignable($a_rol_id, $a_ref_id)
	{
		global $ilBench,$ilDB;

		$ilBench->start("RBAC", "review_isAssignable");

		// exclude system role from rbac
		if ($a_rol_id == SYSTEM_ROLE_ID)
		{
			$ilBench->stop("RBAC", "review_isAssignable");
			return true;
		}

		if (!isset($a_rol_id) or !isset($a_ref_id))
		{
			$message = get_class($this)."::isAssignable(): Missing parameter!".
					   " role_id: ".$a_rol_id." ,ref_id: ".$a_ref_id;
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}
		$query = "SELECT * FROM rbac_fa ".
			 "WHERE rol_id = ".$ilDB->quote($a_rol_id,'integer')." ".
			 "AND parent = ".$ilDB->quote($a_ref_id,'integer')." ";
		$res = $ilDB->query($query);
		$row = $ilDB->fetchObject($res);
	
		$ilBench->stop("RBAC", "review_isAssignable");
		return $row->assign == 'y' ? true : false;
	}
	
	/**
	 * Temporary bugfix
	 */
	public function hasMultipleAssignments($a_role_id)
	{
		global $ilDB;
		
		$query = "SELECT * FROM rbac_fa WHERE rol_id = ".$ilDB->quote($a_role_id,'integer').' '.
			"AND assign = ".$ilDB->quote('y','text');
		$res = $ilDB->query($query);
		return $res->numRows() > 1;
	}

	/**
	* returns an array of role folder ids assigned to a role. A role with stopped inheritance
	* may be assigned to more than one rolefolder.
	* To get only the original location of a role, set the second parameter to true
	*
	* @access	public
	* @param	integer		role id
	* @param	boolean		get only rolefolders where role is assignable (true) 
	* @return	array		reference IDs of role folders
	*/
	function getFoldersAssignedToRole($a_rol_id, $a_assignable = false)
	{
		global $ilDB;
		
		if (!isset($a_rol_id))
		{
			$message = get_class($this)."::getFoldersAssignedToRole(): No role_id given!";
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}
		
		if ($a_assignable)
		{
			$where = " AND assign ='y'";
		}

		$query = "SELECT DISTINCT parent FROM rbac_fa ".
			 "WHERE rol_id = ".$ilDB->quote($a_rol_id,'integer')." ".$where." ";

		$res = $ilDB->query($query);
		while($row = $ilDB->fetchObject($res))
		{
			$folders[] = $row->parent;
		}
		return $folders ? $folders : array();
	}

	/**
	* get all roles of a role folder including linked local roles that are created due to stopped inheritance
	* returns an array with role ids
	* @access	public
	* @param	integer		ref_id of object
	* @param	boolean		if false only get true local roles
	* @return	array		Array with rol_ids
	*/
	function getRolesOfRoleFolder($a_ref_id,$a_nonassignable = true)
	{
		global $ilBench,$ilDB,$ilLog;
		
		$ilBench->start("RBAC", "review_getRolesOfRoleFolder");

		if (!isset($a_ref_id))
		{
			$message = get_class($this)."::getRolesOfRoleFolder(): No ref_id given!";
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
			
		}
		
		if ($a_nonassignable === false)
		{
			$and = " AND assign='y'";
		}

		$query = "SELECT rol_id FROM rbac_fa ".
			 "WHERE parent = ".$ilDB->quote($a_ref_id,'integer')." ".
			 $and;

		$res = $ilDB->query($query);
		while($row = $ilDB->fetchObject($res))
		{
			$rol_id[] = $row->rol_id;
		}

		$ilBench->stop("RBAC", "review_getRolesOfRoleFolder");

		return $rol_id ? $rol_id : array();
	}
	
	/**
	* get only 'global' roles
	* @access	public
	* @return	array		Array with rol_ids
	*/
	function getGlobalRoles()
	{
		return $this->getRolesOfRoleFolder(ROLE_FOLDER_ID,false);
	}

	/**
	 * Get local roles of object
	 * @param int $a_ref_id
	 */
	public function getLocalRoles($a_ref_id)
	{
		global $ilDB;

		// @todo: all this in one query
		$rolf = $this->getRoleFolderIdOfObject($a_ref_id);
		if(!$rolf)
		{
			return array();
		}
		$lroles = array();
		foreach($this->getRolesOfRoleFolder($rolf) as $role_id)
		{
			if($this->isAssignable($role_id, $rolf))
			{
				$lroles[] = $role_id;
			}
		}
		return $lroles;
	}

	/**
	* get only 'global' roles
	* @access	public
	* @return	array		Array with rol_ids
	*/
	function getGlobalRolesArray()
	{
		foreach($this->getRolesOfRoleFolder(ROLE_FOLDER_ID,false) as $role_id)
		{
			$ga[] = array('obj_id'		=> $role_id,
						  'role_type'	=> 'global');
		}
		return $ga ? $ga : array();
	}

	/**
	* get only 'global' roles (with flag 'assign_users')
	* @access	public
	* @return	array		Array with rol_ids
	*/
	function getGlobalAssignableRoles()
	{
		include_once './Services/AccessControl/classes/class.ilObjRole.php';

		foreach($this->getGlobalRoles() as $role_id)
		{
			if(ilObjRole::_getAssignUsersStatus($role_id))
			{
				$ga[] = array('obj_id' => $role_id,
							  'role_type' => 'global');
			}
		}
		return $ga ? $ga : array();
	}

	/**
	* get all role folder ids
	* @access	private
	* @return	array
	*/
	function __getAllRoleFolderIds()
	{
		global $ilDB;
		
		$query = "SELECT DISTINCT parent FROM rbac_fa";
		$res = $ilDB->query($query);

		$parent = array();
		while($row = $ilDB->fetchObject($res))
		{
			$parent[] = $row->parent;
		}
		return $parent;
	}

	/**
	* returns the data of a role folder assigned to an object
	* @access	public
	* @param	integer		ref_id of object with a rolefolder object under it
	* @return	array		empty array if rolefolder not found
	*/
	function getRoleFolderOfObject($a_ref_id)
	{
		global $tree,$ilBench;
		
		$ilBench->start("RBAC", "review_getRoleFolderOfObject");
		
		if (!isset($a_ref_id))
		{
			$GLOBALS['ilLog']->logStack();
			$message = get_class($this)."::getRoleFolderOfObject(): No ref_id given!";
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}
		$childs = $tree->getChildsByType($a_ref_id,"rolf");

		$ilBench->stop("RBAC", "review_getRoleFolderOfObject");

		return $childs[0] ? $childs[0] : array();
	}
	
	function getRoleFolderIdOfObject($a_ref_id)
	{
		$rolf = $this->getRoleFolderOfObject($a_ref_id);
		
		if (!$rolf)
		{
			return false;
		}
		
		return $rolf['ref_id'];
	}

	/**
	 * Check if role
	 */
	public function isRoleAssignedToFolder($a_role_id, $a_parent_id)
	{
		global $rbacreview, $ilDB;

		$query = 'SELECT * FROM rbac_fa '.
			'WHERE rol_id = '.$ilDB->quote($a_role_id,'integer').' '.
			'AND parent = '.$ilDB->quote($a_parent_id,'integer');
		$res = $ilDB->query($query);
		return $res->numRows() ? true : false;
	}

	/**
	* get all possible operations 
	* @access	public
	* @return	array	array of operation_id
	*/
	function getOperations()
	{
		global $ilDB;

		$query = 'SELECT * FROM rbac_operations ORDER BY ops_id ';
		$res = $this->ilDB->query($query);
		while($row = $ilDB->fetchObject($res))
		{
			$ops[] = array('ops_id' => $row->ops_id,
						   'operation' => $row->operation,
						   'description' => $row->description);
		}

		return $ops ? $ops : array();
 	}

	/**
	* get one operation by operation id
	* @access	public
	* @return	array data of operation_id
	*/
	function getOperation($ops_id)
	{
		global $ilDB;

		$query = 'SELECT * FROM rbac_operations WHERE ops_id = '.$ilDB->quote($ops_id,'integer');
		$res = $this->ilDB->query($query);
		while($row = $ilDB->fetchObject($res))
		{
			$ops = array('ops_id' => $row->ops_id,
						 'operation' => $row->operation,
						 'description' => $row->description);
		}

		return $ops ? $ops : array();
	}
	
	/**
	* get all possible operations of a specific role
	* The ref_id of the role folder (parent object) is necessary to distinguish local roles
	* @access	public
	* @param	integer	role_id
	* @param	integer	role folder id
	* @return	array	array of operation_id and types
	*/
	public function getAllOperationsOfRole($a_rol_id, $a_parent = 0)
	{
		global $ilDB;

		if(!$a_parent)
		{
			$a_parent = ROLE_FOLDER_ID;
		}
		
		$query = "SELECT ops_id,type FROM rbac_templates ".
			"WHERE rol_id = ".$ilDB->quote($a_rol_id,'integer')." ".
			"AND parent = ".$ilDB->quote($a_parent,'integer');
		$res = $ilDB->query($query);

		while ($row = $ilDB->fetchObject($res))
		{
			$ops_arr[$row->type][] = $row->ops_id;
		}
		return (array) $ops_arr;
	}
	
	/**
	 * Get active operations for a role
	 * @param object $a_ref_id
	 * @param object $a_role_id
	 * @return 
	 */
	public function getActiveOperationsOfRole($a_ref_id, $a_role_id)
	{
		global $ilDB;
		
		$query = 'SELECT * FROM rbac_pa '.
			'WHERE ref_id = '.$ilDB->quote($a_ref_id,'integer').' '.
			'AND rol_id = '.$ilDB->quote($a_role_id,'integer').' ';
			
		$res = $ilDB->query($query);
		while($row = $res->fetchRow(DB_FETCHMODE_ASSOC))
		{
			return unserialize($row['ops_id']);
		}
		return array();
	}
	

	/**
	* get all possible operations of a specific role
	* The ref_id of the role folder (parent object) is necessary to distinguish local roles
	* @access	public
	* @param	integer	role_id
	* @param	string	object type
	* @param	integer	role folder id
	* @return	array	array of operation_id
	*/
	function getOperationsOfRole($a_rol_id,$a_type,$a_parent = 0)
	{
		global $ilDB,$ilLog;
		
		if (!isset($a_rol_id) or !isset($a_type))
		{
			$message = get_class($this)."::getOperationsOfRole(): Missing Parameter!".
					   "role_id: ".$a_rol_id.
					   "type: ".$a_type.
					   "parent_id: ".$a_parent;
			$ilLog->logStack("Missing parameter! ");
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}

		$ops_arr = array();

		// if no rolefolder id is given, assume global role folder as target
		if ($a_parent == 0)
		{
			$a_parent = ROLE_FOLDER_ID;
		}
		
		$query = "SELECT ops_id FROM rbac_templates ".
			 "WHERE type =".$ilDB->quote($a_type,'text')." ".
			 "AND rol_id = ".$ilDB->quote($a_rol_id,'integer')." ".
			 "AND parent = ".$ilDB->quote($a_parent,'integer');
		$res = $ilDB->query($query);
		while ($row = $ilDB->fetchObject($res))
		{
			$ops_arr[] = $row->ops_id;
		}
		
		return $ops_arr;
	}
	
	function getRoleOperationsOnObject($a_role_id,$a_ref_id)
	{
		global $ilDB;
		
		$query = "SELECT * FROM rbac_pa ".
			"WHERE rol_id = ".$ilDB->quote($a_role_id,'integer')." ".
			"AND ref_id = ".$ilDB->quote($a_ref_id,'integer')." ";

		$res = $ilDB->query($query);
		while($row = $ilDB->fetchObject($res))
		{
			$ops = unserialize($row->ops_id);
		}

		return $ops ? $ops : array();
	}

	/**
	* all possible operations of a type
	* @access	public
	* @param	integer		object_ID of type
	* @return	array		valid operation_IDs
	*/
	function getOperationsOnType($a_typ_id)
	{
		global $ilDB;

		if (!isset($a_typ_id))
		{
			$message = get_class($this)."::getOperationsOnType(): No type_id given!";
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}

		#$query = "SELECT * FROM rbac_ta WHERE typ_id = ".$ilDB->quote($a_typ_id,'integer');
		
		$query = 'SELECT * FROM rbac_ta ta JOIN rbac_operations o ON ta.ops_id = o.ops_id '.
			'WHERE typ_id = '.$ilDB->quote($a_typ_id,'integer').' '.
			'ORDER BY op_order';

		$res = $ilDB->query($query);

		while($row = $ilDB->fetchObject($res))
		{
			$ops_id[] = $row->ops_id;
		}

		return $ops_id ? $ops_id : array();
	}

	/**
	* all possible operations of a type
	* @access	public
	* @param	integer		object_ID of type
	* @return	array		valid operation_IDs
	*/
	function getOperationsOnTypeString($a_type)
	{
		global $ilDB;

		$query = "SELECT * FROM object_data WHERE type = 'typ' AND title = ".$ilDB->quote($a_type ,'text')." ";
			

		$res = $this->ilDB->query($query);
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			return $this->getOperationsOnType($row->obj_id);
		}
		return false;
	}
	
	/**
	 * Get operations by type and class
	 * @param string $a_type Type is "object" or 
	 * @param string $a_class
	 * @return 
	 */
	public function getOperationsByTypeAndClass($a_type,$a_class)
	{
		global $ilDB;
		
		if($a_class != 'create')
		{
			$condition = "AND class != ".$ilDB->quote('create','text');
		}
		else
		{
			$condition = "AND class = ".$ilDB->quote('create','text');
		}
		
		$query = "SELECT ro.ops_id FROM rbac_operations ro ".
			"JOIN rbac_ta rt ON  ro.ops_id = rt.ops_id ".
			"JOIN object_data od ON rt.typ_id = od.obj_id ".
			"WHERE type = ".$ilDB->quote('typ','text')." ".
			"AND title = ".$ilDB->quote($a_type,'text')." ".
			$condition." ".
			"ORDER BY op_order ";
			
		$res = $ilDB->query($query);
		
		$ops = array();
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$ops[] = $row->ops_id;
		}
		return $ops; 
	}

	
	/**
	* get all objects in which the inheritance of role with role_id was stopped
	* the function returns all reference ids of objects containing a role folder.
	* @access	public
	* @param	integer	role_id
	* @param	array   filter ref_ids
	* @return	array	with ref_ids of objects
	*/
	function getObjectsWithStopedInheritance($a_rol_id,$a_filter = array())
	{
		global $ilDB;
		
		$query = 'SELECT t.parent p FROM tree t JOIN rbac_fa fa ON fa.parent = child '.
			'WHERE assign = '.$ilDB->quote('n','text').' '.
			'AND rol_id = '.$ilDB->quote($a_rol_id,'integer').' ';
		
		if($a_filter)
		{
			$query .= ('AND '.$ilDB->in('t.parent',(array) $a_filter,false,'integer'));
		}

		$res = $ilDB->query($query);
		$parent = array();
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$parent[] = $row->p;
		}
		return $parent;
	}

	/**
	* checks if a rolefolder is set as deleted (negative tree_id)
	* @access	public
	* @param	integer	ref_id of rolefolder
	* @return	boolean	true if rolefolder is set as deleted
	*/
	function isDeleted($a_node_id)
	{
		global $ilDB;
		
		$q = "SELECT tree FROM tree WHERE child =".$ilDB->quote($a_node_id)." ";
		$r = $this->ilDB->query($q);
		
		$row = $r->fetchRow(DB_FETCHMODE_OBJECT);
		
		if (!$row)
		{
			$message = sprintf('%s::isDeleted(): Role folder with ref_id %s not found!',
							   get_class($this),
							   $a_node_id);
			$this->log->write($message,$this->log->FATAL);

			return true;
		}

		// rolefolder is deleted
		if ($row->tree < 0)
		{
			return true;
		}
		
		return false;
	}
	
	/**
	 * Check if role is a global role
	 * @param type $a_role_id
	 * @return type
	 */
	public function isGlobalRole($a_role_id)
	{
		return in_array($a_role_id,$this->getGlobalRoles());
	}
	
	function getRolesByFilter($a_filter = 0,$a_user_id = 0, $title_filter = '')
	{
		global $ilDB;
		
        $assign = "y";

		switch($a_filter)
		{
            // all (assignable) roles
            case self::FILTER_ALL:
				return $this->getAssignableRoles(true,true,$title_filter);
				break;

            // all (assignable) global roles
            case self::FILTER_ALL_GLOBAL:
				$where = 'WHERE '.$ilDB->in('rbac_fa.rol_id',$this->getGlobalRoles(),false,'integer').' ';
				break;

            // all (assignable) local roles
            case self::FILTER_ALL_LOCAL:
            case self::FILTER_INTERNAL:
            case self::FILTER_NOT_INTERNAL:
				$where = 'WHERE '.$ilDB->in('rbac_fa.rol_id',$this->getGlobalRoles(),true,'integer');
				break;
				
            // all role templates
            case self::FILTER_TEMPLATES:
				$where = "WHERE object_data.type = 'rolt'";
				$assign = "n";
				break;

            // only assigned roles, handled by ilObjUserGUI::roleassignmentObject()
            case 0:
			default:
                if(!$a_user_id) 
                	return array();

				$where = 'WHERE '.$ilDB->in('rbac_fa.rol_id',$this->assignedRoles($a_user_id),false,'integer').' ';
                break;
		}
		
		$roles = array();

		$query = "SELECT * FROM object_data ".
			 "JOIN rbac_fa ON obj_id = rol_id ".
			 $where.
			 "AND rbac_fa.assign = ".$ilDB->quote($assign,'text')." ";

		if(strlen($title_filter))
		{
			$query .= (' AND '.$ilDB->like(
				'title',
				'text',
				'%'.$title_filter.'%'
			));
		}
		
		$res = $ilDB->query($query);
		while($row = $ilDB->fetchAssoc($res))
		{
            $prefix = (substr($row["title"],0,3) == "il_") ? true : false;

            // all (assignable) internal local roles only
            if ($a_filter == 4 and !$prefix)
			{
                continue;
            }

            // all (assignable) non internal local roles only
			if ($a_filter == 5 and $prefix)
			{
                continue;
            }
            
			$row["desc"] = $row["description"];
			$row["user_id"] = $row["owner"];
			$roles[] = $row;
		}

		$roles = $this->__setRoleType($roles);

		return $roles ? $roles : array();
	}
	
	// get id of a given object type (string)
	function getTypeId($a_type)
	{
		global $ilDB;

		$q = "SELECT obj_id FROM object_data ".
			 "WHERE title=".$ilDB->quote($a_type ,'text')." AND type='typ'";
		$r = $ilDB->query($q);
		
		$row = $r->fetchRow(DB_FETCHMODE_OBJECT);
		return $row->obj_id;
	}

	/**
	* get ops_id's by name.
	*
	* Example usage: $rbacadmin->grantPermission($roles,ilRbacReview::_getOperationIdsByName(array('visible','read'),$ref_id));
	*
	* @access	public
	* @param	array	string name of operation. see rbac_operations
	* @return	array   integer ops_id's
	*/
	public static function _getOperationIdsByName($operations)
	{
		global $ilDB;

		if(!count($operations))
		{
			return array();
		}
		
		$query = 'SELECT ops_id FROM rbac_operations '.
			'WHERE '.$ilDB->in('operation',$operations,false,'text');
		
		$res = $ilDB->query($query);
		while($row = $ilDB->fetchObject($res))
		{
			$ops_ids[] = $row->ops_id;
		}
		return $ops_ids ? $ops_ids : array();
	}
	
	/**
	* get operation id by name of operation
	* @access	public
	* @access	static
	* @param	string	operation name
	* @return	integer	operation id
	*/
	public static function _getOperationIdByName($a_operation)
	{
		global $ilDB,$ilErr;

		if (!isset($a_operation))
		{
			$message = "perm::getOperationId(): No operation given!";
			$ilErr->raiseError($message,$ilErr->WARNING);	
		}

        // Cache operation ids
        if (! is_array(self::$_opsCache)) {
            self::$_opsCache = array();

            $q = "SELECT ops_id, operation FROM rbac_operations";
            $r = $ilDB->query($q);
            while ($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
            {
                self::$_opsCache[$row->operation] = $row->ops_id;
            }
        }

        // Get operation ID by name from cache
        if (array_key_exists($a_operation, self::$_opsCache)) {
            return self::$_opsCache[$a_operation];
        }
        return null;
	}
	
	/**
	 * Lookup operation ids
	 * @param array $a_type_arr e.g array('cat','crs','grp'). The operation name (e.g. 'create_cat') is generated automatically
	 * @return array int Array with operation ids
	 */
	public static function lookupCreateOperationIds($a_type_arr)
	{
		global $ilDB;
		
		$operations = array();
		foreach($a_type_arr as $type)
		{
			$operations[] = ('create_'.$type);
		}
		
		if(!count($operations))
		{
			return array();
		}
		
		$query = 'SELECT ops_id, operation FROM rbac_operations '.
			'WHERE '.$ilDB->in('operation',$operations,false,'text');
			
		$res = $ilDB->query($query);
	
		$ops_ids = array();
		while($row = $ilDB->fetchObject($res))
		{
			$type_arr = explode('_', $row->operation);
			$type = $type_arr[1];
			
			$ops_ids[$type] = $row->ops_id;
		}
		return $ops_ids;
	}


	/**
	* get all linked local roles of a role folder that are created due to stopped inheritance
	* returns an array with role ids
	* @access	public
	* @param	integer		ref_id of object
	* @param	boolean		if false only get true local roles
	* @return	array		Array with rol_ids
	*/
	function getLinkedRolesOfRoleFolder($a_ref_id)
	{
		global $ilDB;
		
		if (!isset($a_ref_id))
		{
			$message = get_class($this)."::getLinkedRolesOfRoleFolder(): No ref_id given!";
			$this->ilErr->raiseError($message,$this->ilErr->WARNING);
		}
		
		$and = " AND assign='n'";

		$query = "SELECT rol_id FROM rbac_fa ".
			 "WHERE parent = ".$ilDB->quote($a_ref_id,'integer')." ".
			 $and;
		$res = $this->ilDB->query($query);
		while($row = $ilDB->fetchObject($res))
		{
			$rol_id[] = $row->rol_id;
		}

		return $rol_id ? $rol_id : array();
	}
	
	// checks if default permission settings of role under current parent (rolefolder) are protected from changes
	function isProtected($a_ref_id,$a_role_id)
	{
		global $ilDB;
		
		$query = "SELECT protected FROM rbac_fa ".
			 "WHERE rol_id = ".$ilDB->quote($a_role_id,'integer')." ".
			 "AND parent = ".$ilDB->quote($a_ref_id,'integer')." ";
		$res = $ilDB->query($query);
		$row = $ilDB->fetchAssoc($res);
		
		return ilUtil::yn2tf($row['protected']);
	}
	
	// this method alters the protected status of role regarding the current user's role assignment
	// and current postion in the hierarchy.
	function __setProtectedStatus($a_parent_roles,$a_role_hierarchy,$a_ref_id)
	{
		//vd('refId',$a_ref_id,'parent roles',$a_parent_roles,'role-hierarchy',$a_role_hierarchy);
		
		global $rbacsystem,$ilUser,$log;
		
		if (in_array(SYSTEM_ROLE_ID,$this->assignedRoles($ilUser->getId())))
		{
			$leveladmin = true;
		}
		else
		{
			$leveladmin = false;
		}
		#vd("RoleHierarchy",$a_role_hierarchy);
		foreach ($a_role_hierarchy as $role_id => $rolf_id)
		{
			//$log->write("ilRBACreview::__setProtectedStatus(), 0");	
			#echo "<br/>ROLF: ".$rolf_id." ROLE_ID: ".$role_id." (".$a_parent_roles[$role_id]['title'].") ";
			//var_dump($leveladmin,$a_parent_roles[$role_id]['protected']);

			if ($leveladmin == true)
			{
				$a_parent_roles[$role_id]['protected'] = false;
				continue;
			}
				
			if ($a_parent_roles[$role_id]['protected'] == true)
			{
				$arr_lvl_roles_user = array_intersect($this->assignedRoles($ilUser->getId()),array_keys($a_role_hierarchy,$rolf_id));
				
				#vd("intersection",$arr_lvl_roles_user);
				
				foreach ($arr_lvl_roles_user as $lvl_role_id)
				{
					#echo "<br/>level_role: ".$lvl_role_id;
					#echo "<br/>a_ref_id: ".$a_ref_id;
					
					//$log->write("ilRBACreview::__setProtectedStatus(), 1");
					// check if role grants 'edit_permission' to parent
					$rolf = $a_parent_roles[$role_id]['parent'];
					$parent_obj = $GLOBALS['tree']->getParentId($rolf);
					if ($rbacsystem->checkPermission($parent_obj,$lvl_role_id,'edit_permission'))
					{
						#echo "<br />Permission granted";
						//$log->write("ilRBACreview::__setProtectedStatus(), 2");
						// user may change permissions of that higher-ranked role
						$a_parent_roles[$role_id]['protected'] = false;
						
						// remember successful check
						//$leveladmin = true;
					}
				}
			}
		}
		
		return $a_parent_roles;
	}
	
	/**
	* get operation list by object type
	* TODO: rename function to: getOperationByType
	* @access	public
	* @access 	static
	* @param	string	object type you want to have the operation list
	* @param	string	order column
	* @param	string	order direction (possible values: ASC or DESC)
	* @return	array	returns array of operations
	*/
	public static function _getOperationList($a_type = null)
	 {
		global $ilDB;
	
		$arr = array();

		if ($a_type)
		{
			$query = sprintf('SELECT * FROM rbac_operations '.
				'JOIN rbac_ta ON rbac_operations.ops_id = rbac_ta.ops_id '.
				'JOIN object_data ON rbac_ta.typ_id = object_data.obj_id '.
				'WHERE object_data.title = %s '.
				'AND object_data.type = %s '.
				'ORDER BY op_order ASC',
				$ilDB->quote($a_type,'text'),
				$ilDB->quote('typ','text'));
		}
		else
		{
			$query = 'SELECT * FROM rbac_operations ORDER BY op_order ASC';
		}
		$res = $ilDB->query($query);
		while ($row = $ilDB->fetchAssoc($res))
		{
			$arr[] = array(
						"ops_id"	=> $row['ops_id'],
						"operation"	=> $row['operation'],
						"desc"		=> $row['description'],
						"class"		=> $row['class'],
						"order"		=> $row['op_order']
						);
		}
		return $arr;
	}
	
	public static function _groupOperationsByClass($a_ops_arr)
	{
		$arr = array();

		foreach ($a_ops_arr as $ops)
		{
			$arr[$ops['class']][] = array ('ops_id'	=> $ops['ops_id'],
										   'name'	=> $ops['operation']
										 );
		}
		return $arr; 
	}

	/**
	 * Get object id of objects a role is assigned to
	 *
	 * @access public
	 * @param int role id
	 * 
	 */
	public function getObjectOfRole($a_role_id)
	{
		// internal cache
		static $obj_cache = array();

		global $ilDB;
		
		
		if(isset($obj_cache[$a_role_id]) and $obj_cache[$a_role_id])
		{
			return $obj_cache[$a_role_id];
		}
		
		$query = "SELECT obr.obj_id FROM rbac_fa rfa ".
			"JOIN tree ON rfa.parent = tree.child ".
			"JOIN object_reference obr ON tree.parent = obr.ref_id ".
			"WHERE tree.tree = 1 ".
			"AND assign = 'y' ".
			"AND rol_id = ".$ilDB->quote($a_role_id,'integer')." ";
		$res = $ilDB->query($query);
		
		$obj_cache[$a_role_id] = 0;
		while($row = $ilDB->fetchObject($res))
		{
			$obj_cache[$a_role_id] = $row->obj_id;
		}
		return $obj_cache[$a_role_id];
	}
	
	/**
	 * Get reference of role
	 * @param object $a_role_id
	 * @return 
	 */
	public function getObjectReferenceOfRole($a_role_id)
	{
		global $ilDB;
		
		$query = "SELECT tree.parent ref FROM rbac_fa fa ".
			"JOIN tree ON fa.parent = tree.child ".
			"WHERE tree.tree = 1 ".
			"AND assign = ".$ilDB->quote('y','text').' '.
			"AND rol_id = ".$ilDB->quote($a_role_id,'integer');

		$res = $ilDB->query($query);
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			return $row->ref;
		}
		return 0;
	}
	
	/**
	 * return if role is only attached to deleted role folders
	 *
	 * @param int $a_role_id
	 * @return boolean
	 */
	public function isRoleDeleted ($a_role_id){
		$rolf_list = $this->getFoldersAssignedToRole($a_role_id, false);
		$deleted = true;
		if (count($rolf_list))
		{
			foreach ($rolf_list as $rolf) {      	        
	    		// only list roles that are not set to status "deleted"
	    		if (!$this->isDeleted($rolf))
				{
	   				$deleted = false;
	   				break;
				}
			}
		}
		return $deleted;	
	}
	
	
	function getRolesForIDs($role_ids, $use_templates)
	{
		global $ilDB;
		
		$role_list = array();

		$where = $this->__setTemplateFilter($use_templates);

		$query = "SELECT * FROM object_data ".
			 "JOIN rbac_fa ON object_data.obj_id = rbac_fa.rol_id ".
			 $where.
			 "AND rbac_fa.assign = 'y' " .
			 'AND '.$ilDB->in('object_data.obj_id',$role_ids,false,'integer');
			 
		$res = $ilDB->query($query);
		while($row = $ilDB->fetchAssoc($res))
		{
			$row["desc"] = $row["description"];
			$row["user_id"] = $row["owner"];
			$role_list[] = $row;
		}
		
		$role_list = $this->__setRoleType($role_list);
		return $role_list;
	}
	
	/**
	 * get operation assignments 
	 * @return array array(array('typ_id' => $typ_id,'title' => $title,'ops_id => '$ops_is,'operation' => $operation),...
	 */
	public function getOperationAssignment()
	{
		global $ilDB;

		$query = 'SELECT ta.typ_id, obj.title, ops.ops_id, ops.operation FROM rbac_ta ta '.
			 'JOIN object_data obj ON obj.obj_id = ta.typ_id '.
			 'JOIN rbac_operations ops ON ops.ops_id = ta.ops_id ';
		$res = $ilDB->query($query);
		
		$counter = 0;
		while($row = $ilDB->fetchObject($res))
		{
			$info[$counter]['typ_id'] = $row->typ_id;
			$info[$counter]['type'] = $row->title;
			$info[$counter]['ops_id'] = $row->ops_id;
			$info[$counter]['operation'] = $row->operation;
			$counter++;
		}
		return $info ? $info : array();
		
	}
	
	/**
	 * Filter empty role folder.
	 * This method is used after deleting
	 * roles, to check which empty role folders have to deleted.
	 *  
	 * @param array	$a_rolf_candidates
	 * @return array
	 */
	public function filterEmptyRoleFolders($a_rolf_candidates)
	{
		global $ilDB;

		$query = 'SELECT DISTINCT(parent) parent FROM rbac_fa '.
			'WHERE '.$ilDB->in('parent',$a_rolf_candidates,false,'integer');
		$res = $ilDB->query($query);
		while($row = $ilDB->fetchObject($res))
		{
			$non_empty[] = $row->parent;
		}
		return $non_empty ? $non_empty : array();
	}
	
	/**
	 * Check if role is deleteableat a specific position
	 * @param object $a_role_id
	 * @param int rolf_id
	 * @return 
	 */
	public function isDeleteable($a_role_id, $a_rolf_id)
	{
		if(!$this->isAssignable($a_role_id, $a_rolf_id))
		{
			return false;
		}
		if($a_role_id == SYSTEM_ROLE_ID or $a_role_id == ANONYMOUS_ROLE_ID)
		{
			return false;
		}
		if(substr(ilObject::_lookupTitle($a_role_id),0,3) == 'il_')
		{
			return false;
		}
		return true;
	}

	/**
	 * Check if the role is system generate role or role template
	 * @param int $a_role_id
	 * @return bool
	 */
	public function isSystemGeneratedRole($a_role_id)
	{
		$title = ilObject::_lookupTitle($a_role_id);
		return substr($title,0,3) == 'il_' ? true : false;
	}


	/**
	 * Get role folder of role
	 * @global ilDB $ilDB
	 * @param int $a_role_id
	 * @return int
	 */
	public function getRoleFolderOfRole($a_role_id)
	{
		global $ilDB;

		$query = 'SELECT * FROM rbac_fa '.
			'WHERE rol_id = '.$ilDB->quote($a_role_id,'integer').' '.
			'AND assign = '.$ilDB->quote('y','text');
		$res = $ilDB->query($query);
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			return $row->parent;
		}
		return 0;
	}
	
	/**
	 * Get all user permissions on an object
	 *
	 * @param int $a_user_id user id
	 * @param int $a_ref_id ref id
	 */
	function getUserPermissionsOnObject($a_user_id, $a_ref_id)
	{
		global $ilDB;
		
		$query = "SELECT ops_id FROM rbac_pa JOIN rbac_ua ".
			"ON (rbac_pa.rol_id = rbac_ua.rol_id) ".
			"WHERE rbac_ua.usr_id = ".$ilDB->quote($a_user_id,'integer')." ".
			"AND rbac_pa.ref_id = ".$ilDB->quote($a_ref_id,'integer')." ";

		$res = $ilDB->query($query);
		$all_ops = array();
		while ($row = $ilDB->fetchObject($res))
		{
			$ops = unserialize($row->ops_id);
			$all_ops = array_merge($all_ops, $ops);
		}
		$all_ops = array_unique($all_ops);
		
		$set = $ilDB->query("SELECT operation FROM rbac_operations ".
			" WHERE ".$ilDB->in("ops_id", $all_ops, false, "integer"));
		$perms = array();
		while ($rec = $ilDB->fetchAssoc($set))
		{
			$perms[] = $rec["operation"];
		}
		
		return $perms;
	}

} // END class.ilRbacReview
?>
