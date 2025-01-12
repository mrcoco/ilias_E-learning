<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/Tracking/classes/class.ilLPTableBaseGUI.php");

/**
* TableGUI class for learning progress
*
* @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
* @version $Id$
*
* @ilCtrl_Calls ilLPObjectStatisticsTypesTableGUI: ilFormPropertyDispatchGUI
* @ingroup ServicesTracking
*/
class ilLPObjectStatisticsTypesTableGUI extends ilLPTableBaseGUI
{
	/**
	* Constructor
	*/
	function __construct($a_parent_obj, $a_parent_cmd, array $a_preselect = null, $a_load_items = true)
	{
		global $ilCtrl, $lng;
		
		$this->preselected = $a_preselect;

		$this->setId("lpobjstattypetbl");
		
		parent::__construct($a_parent_obj, $a_parent_cmd);

		$this->initFilter();
		
		$this->addColumn("", "", "1", true);
		$this->addColumn($lng->txt("type"), "title");
		foreach($this->getMonthsYear() as $num => $caption)
		{
			$this->addColumn($caption, "month_".$num, "", false, "ilRight");
		}
		if($this->filter["year"] == date("Y"))
		{
			$this->addColumn($lng->txt("trac_current"), "month_live", "", false, "ilRight");
		}
	
		$this->setTitle($this->lng->txt("trac_object_stat_types"));

		// $this->setSelectAllCheckbox("item_id");
		$this->addMultiCommand("showTypesGraph", $lng->txt("trac_show_graph"));
		$this->setResetCommand("resetTypesFilter");
		$this->setFilterCommand("applyTypesFilter");
		
		$this->setFormAction($ilCtrl->getFormAction($a_parent_obj, $a_parent_cmd));
		$this->setRowTemplate("tpl.lp_object_statistics_types_row.html", "Services/Tracking");
		$this->setEnableHeader(true);
		$this->setEnableNumInfo(true);
		$this->setEnableTitle(true);
		$this->setDefaultOrderField("title");
		$this->setDefaultOrderDirection("asc");
		
		$this->setLimit(9999);
	
		$this->setExportFormats(array(self::EXPORT_EXCEL, self::EXPORT_CSV));

		include_once("./Services/Tracking/classes/class.ilLPObjSettings.php");

		if($a_load_items)
		{
			$this->getItems();
		}
	}
	
	public function numericOrdering($a_field) 
	{
		if($a_field != "title")
		{
			return true;
		}
		return false;
	}
	
	/**
	* Init filter
	*/
	public function initFilter()
	{
		global $lng;

		$this->setDisableFilterHiding(true);

		// figure
		include_once("./Services/Form/classes/class.ilSelectInputGUI.php");
		$si = new ilSelectInputGUI($lng->txt("trac_figure"), "figure");
		$options = array("objects"=>$lng->txt("objects"),
			"references"=>$lng->txt("trac_reference"),
			"deleted"=>$lng->txt("trac_trash"));
		$si->setOptions($options);
		$this->addFilterItem($si);
		$si->readFromSession();
		if(!$si->getValue())
		{
			$si->setValue("objects");
		}
		$this->filter["measure"] = $si->getValue();
		
		// aggregation
		$si = new ilSelectInputGUI($lng->txt("trac_aggregation"), "aggregation");
		$options = array();		
		$options["max"] = $lng->txt("trac_object_stat_lp_max")." (".$lng->txt("month").")";
		$options["avg"] = "&#216; (".$lng->txt("month").")";
		$options["min"] = $lng->txt("trac_object_stat_lp_min")." (".$lng->txt("month").")";
		$si->setOptions($options);
		$this->addFilterItem($si);
		$si->readFromSession();
		if(!$si->getValue())
		{
			$si->setValue("max");
		}
		$this->filter["aggregation"] = $si->getValue();		
		
		// year/month
		$si = new ilSelectInputGUI($lng->txt("year"), "year");		
		$options = array();
		for($loop = 0; $loop < 4; $loop++)
		{
			$year = date("Y")-$loop;
			$options[$year] = $year;
		}
		$si->setOptions($options);
		$this->addFilterItem($si);
		$si->readFromSession();
		if(!$si->getValue())
		{
			$si->setValue(date("Y"));
		}
		$this->filter["year"] = $si->getValue();
	}

	function getItems()
	{
		include_once "Services/Tracking/classes/class.ilTrQuery.php";	
			$res = ilTrQuery::getObjectTypeStatisticsPerMonth($this->filter["aggregation"], $this->filter["year"]);	
				
		// get plugin titles
		include_once("./Services/Repository/classes/class.ilRepositoryObjectPluginSlot.php");
		$plugins = array();
		$plugins = ilRepositoryObjectPluginSlot::addCreatableSubObjects($plugins);
		
		$data = array();
		foreach($res as $type => $months)
		{
			$data[$type]["type"] = $type;
 			
			// to enable sorting by title
			if(array_key_exists($type, $plugins))
			{
				include_once("./Services/Component/classes/class.ilPlugin.php");
				$data[$type]["title"] = ilPlugin::lookupTxt("rep_robj", $type, "obj_".$type);
				$data[$type]["icon"] = ilObject::_getIcon("", "tiny", $type);
			}			
			else
			{
				$data[$type]["title"] = $this->lng->txt("objs_".$type);
				$data[$type]["icon"] = ilUtil::getTypeIconPath($type, null, "tiny");
			}
			
			foreach($months as $month => $row)
			{
				$value = $row[$this->filter["measure"]];
				$data[$type]["month_".$month] = $value;
			}
		}
		
		
		// add live data
		if($this->filter["year"] == date("Y"))
		{
			$live = ilTrQuery::getObjectTypeStatistics();			
			foreach($live as $type => $item)
			{
				$data[$type]["type"] = $type;

				// to enable sorting by title
				if(array_key_exists($type, $plugins))
				{
					include_once("./Services/Component/classes/class.ilPlugin.php");
					$data[$type]["title"] = ilPlugin::lookupTxt("rep_robj", $type, "obj_".$type);
					$data[$type]["icon"] = ilObject::_getIcon("", "tiny", $type);
				}			
				else
				{
					$data[$type]["title"] = $this->lng->txt("objs_".$type);
					$data[$type]["icon"] = ilUtil::getTypeIconPath($type, null, "tiny");
				}

				$value = $item[$this->filter["measure"]];
				$data[$type]["month_live"] = $value;
			}
		}
		
		$this->setData($data);
	}
	
	/**
	* Fill table row
	*/
	protected function fillRow($a_set)
	{
		$this->tpl->setVariable("ICON_SRC", $a_set["icon"]);
		$this->tpl->setVariable("ICON_ALT", $this->lng->txt("objs_".$a_set["type"]));
		$this->tpl->setVariable("TITLE_TEXT", $a_set["title"]);
		$this->tpl->setVariable("OBJ_TYPE", $a_set["type"]);
		
		if($this->preselected && in_array($a_set["type"], $this->preselected))
		{
			$this->tpl->setVariable("CHECKBOX_STATE", " checked=\"checked\"");
		}
		
		$this->tpl->setCurrentBlock("item");
		foreach(array_keys($this->getMonthsYear()) as $month)
		{
			$this->tpl->setVariable("VALUE_ITEM", $this->anonymizeValue((int)$a_set["month_".$month]));
			$this->tpl->parseCurrentBlock();
		}
		
		if($this->filter["year"] == date("Y"))
		{
			$this->tpl->setVariable("VALUE_ITEM", $this->anonymizeValue((int)$a_set["month_live"]));
			$this->tpl->parseCurrentBlock();
		}
	}

	function getGraph(array $a_graph_items)
	{
		global $lng;
		
		include_once "Services/Chart/classes/class.ilChart.php";
		$chart = new ilChart("objsttp", 700, 500);

		$legend = new ilChartLegend();
		$chart->setLegend($legend);
		
		$types = array();
		foreach($this->getData() as $id => $item)
		{
			$types[$id] = $item["title"];
 		}

		foreach(array_values($this->getMonthsYear(null, true)) as $idx => $caption)
		{
			$labels[$idx+1] = $caption;
		}
		$chart->setTicks($labels, false, true);
				
		foreach($this->getData() as $type => $object)
		{			
			if(in_array($type, $a_graph_items))
			{			
				$series = new ilChartData("lines");
				$series->setLabel($types[$type]);
				
				foreach(array_keys($this->getMonthsYear()) as $idx => $month)
				{					
					$series->addPoint($idx+1, (int)$object["month_".$month]);
				}
			
				$chart->addData($series);
			}
		}
		
		return $chart->getHTML();
	}
	
	protected function fillMetaExcel()
	{
		
	}
	
	protected function fillRowExcel($a_worksheet, &$a_row, $a_set)
	{
		$a_worksheet->write($a_row, 0, $a_set["title"]);	
		
		$cnt = 1;		
		foreach(array_keys($this->getMonthsYear()) as $month)
		{
			$value = $this->anonymizeValue((int)$a_set["month_".$month]);
			$a_worksheet->write($a_row, $cnt, $value);
			
			$cnt++;
		}
		
		$value = $this->anonymizeValue((int)$a_set["month_live"]);
		$a_worksheet->write($a_row, $cnt, $value);
	}
	
	protected function fillMetaCSV()
	{
		
	}
	
	protected function fillRowCSV($a_csv, $a_set)
	{
		$a_csv->addColumn($a_set["title"]);
		
		foreach(array_keys($this->getMonthsYear()) as $month)
		{
			$value = $this->anonymizeValue((int)$a_set["month_".$month]);
			$a_csv->addColumn($value);
		}
		
		$value = $this->anonymizeValue((int)$a_set["month_live"]);
		$a_csv->addColumn($value);
		
		$a_csv->addRow();
	}
}

?>