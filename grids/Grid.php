<?php
/**
 * NiftyGrid - DataGrid for Nette
 *
 * @author	Jakub Holub
 * @copyright	Copyright (c) 2012 Jakub Holub
 * @license     New BSD Licence
 * @link        http://addons.nette.org/cs/niftygrid
 */
namespace NiftyGrid;

use Nette;
use Nette\Application\UI\Presenter;
use Tracy\Debugger;

abstract class Grid extends \Nette\Application\UI\Control
{
	const GRID_GRIDS_TABLE_NAME = "mikis.sa_grids";
	const GRID_COLUMNS_TABLE_NAME = "mikis.sa_grid_columns";
	const CONFIG_TABLE_NAME = "mikis.config";

	const ROW_FORM = "rowForm";

	const ADD_ROW = "addRow";

  /** @persistent array */
	public $filter = array();

	/** @persistent string */
	public $order;

	/** @persistent int */
	public $perPage;

	/** @persistent int */
	public $activeSubGridId;

	/** @persistent string */
	public $activeSubGridName;

	/** @var array */
	public $perPageValues = array(20 => 20, 50 => 50, 100 => 100);

	/** @var bool */
	public $paginate = TRUE;

	/** @var bool */
	public $gridControl = TRUE;

	/** @var string */
	protected $defaultOrder;

	/** @var DataSource\IDataSource */
	protected $dataSource;

	/** @var string */
	protected $primaryKey;

	/** @var string */
	public $gridName;

	/** @var string */
	public $width;

	/** @var bool */
	public $enableSorting = TRUE;

	/** @var int */
	public $activeRowForm;

	/** @var callback */
	public $rowFormCallback;

	/** @var bool */
	public $showAddRow = FALSE;

	/** @var bool */
	public $isSubGrid = FALSE;

	/** @var array */
	public $subGrids = array();

	/** @var callback */
	public $afterConfigureSettings;

	/** @var string */
	protected $templatePath;

	/** @var string */
	public $messageNoRecords = 'ui_grid_message_nodata';

	/** @var \Nette\Localization\ITranslator */
	protected $translator;

  	/** @var bool */
	public $wolink = TRUE;

  	/** @var bool */
	public $wocounter = FALSE;

  	/** @var bool */
	public $wouinfo = FALSE;

  	/** @var bool */
	public $wosinfo = FALSE;

  	/** @var bool */
	public $wobinfo = TRUE;

  	/** @var bool */
	public $wobcounter = TRUE;

  	/** @var bool */
	public $wogridname = TRUE;

  	/** @var bool */
	public $wocolumnames = TRUE;

  	/** @var string */
	public $columnactionlabel = 'ui_grid_columnaction_label';

  	/** @var bool */
	public $woupperlefttext = TRUE;

	/** @var string */
	public $gridUpperLeftText;

   	/** @var bool */
	public $gbexcel = FALSE;

	/** @var bool */
	public $headers2 = FALSE;

	/** @var bool */
	public $headers3 = FALSE;

	/** @var bool */
	public $nogetcount = FALSE;

	/** @var int */
	public $refreshTime = 0;

	/** @var string */
	public $refreshURL = '';

	/** @var array */
	public $frmctrls = array();

	/** @var string */
	public $gridSelector;

  //MKOLES
	public $noredownload_flag = FALSE;

	//MKOLES
	public $movablehead_flag;

  //MKOLES
	//public $offset_height;

  //MKOLES
	public $log_flag = FALSE;

  //MKOLES
	public $oracle_flag = FALSE;

  /**
	 * @param \Nette\Application\UI\Presenter $presenter
	 */
	protected function attached($presenter)
	{
		parent::attached($presenter);
		if(!$presenter instanceof Presenter) return;

    $this->setTranslator($presenter->translator);

		$this->addComponent(New \Nette\ComponentModel\Container(), "columns");
		$this->addComponent(New \Nette\ComponentModel\Container(), "buttons");
		$this->addComponent(New \Nette\ComponentModel\Container(), "globalButtons");
		$this->addComponent(New \Nette\ComponentModel\Container(), "actions");
		$this->addComponent(New \Nette\ComponentModel\Container(), "subGrids");
		$this->addComponent(New \Nette\ComponentModel\Container(), "frmctrls");

		if($presenter->isAjax()){
			$this->invalidateControl();
		}

    $gbexcel = $this->presenter->getDatabase()->table('mikis.config')->select('value')->where('module','SA')->where('code','SA_GRIDS_EXCEL_EXPORT_FLAG')->fetch()['value'];
    if (isset($gbexcel) && $gbexcel=='Y') {
       $this->gbexcel = TRUE;
    }

		$this->configure($presenter);

    if ($this->log_flag) {
        error_log($this->name.(isset($this->gridSelector)?$this->gridSelector:''));
    }

    if(strpos($this->name,'aclGrid') !== false){
      $grid_condition_name = $this->gridName.(isset($this->gridSelector)?$this->gridSelector:'');
    }
    else {
      $grid_condition_name = $this->name.(isset($this->gridSelector)?$this->gridSelector:'');
    }

    if($this->gbexcel){
      $grid_def = $this->presenter->getDatabase()->table(self::GRID_GRIDS_TABLE_NAME)->where('gridname',$grid_condition_name)->fetch();
      if ($grid_def) {
        if ($grid_def->exportable_flag=='Y' && $this->presenter->user->isAllowed($grid_def->exportable_resource,$grid_def->exportable_action)) {
          $this->setGlobalButtonExcel();
        }
      }
		}

		if($this->isSubGrid && !empty($this->afterConfigureSettings)){
			call_user_func($this->afterConfigureSettings, $this);
		}

		if($this->hasActiveSubGrid()){
			$subGrid = $this->addComponent($this['subGrids']->components[$this->activeSubGridName]->getGrid(), "subGrid".$this->activeSubGridName);
			$subGrid->registerSubGrid("subGrid".$this->activeSubGridName);
		}

		if($this->hasActionForm()){
			$actions = array();
			foreach($this['actions']->components as $name => $action){
				$actions[$name] = $action->getAction();
			}
			$this['gridForm'][$this->name]['action']['action_name']->setItems($actions);
		}
		if($this->paginate){
			if($this->hasActiveItemPerPage()){
				if(in_array($this->perPage, $this['gridForm'][$this->name]['perPage']['perPage']->items)){
					$this['gridForm'][$this->name]['perPage']->setDefaults(array("perPage" => $this->perPage));
				}else{
					$items = $this['gridForm'][$this->name]['perPage']['perPage']->getItems();
					$this->perPage = reset($items);
				}
			}else{
				$items = $this['gridForm'][$this->name]['perPage']['perPage']->getItems();
				$this->perPage = reset($items);
			}
			$this->getPaginator()->itemsPerPage = $this->perPage;
		}

    if ($this->log_flag) {
        error_log(print_r('before active Filter',TRUE));
    }
		if($this->hasActiveFilter()){
        if ($this->log_flag) {
            error_log(print_r('before filter data',TRUE));
        }
  			$this->filterData();
	  		$this['gridForm'][$this->name]['filter']->setDefaults($this->filter);
		}
		if($this->hasActiveOrder() && $this->hasEnabledSorting()){
			$this->orderData($this->order);
		}
		if(!$this->hasActiveOrder() && $this->hasDefaultOrder() && $this->hasEnabledSorting()){
			$order = explode(" ", $this->defaultOrder);
			$this->dataSource->orderData($order[0], $order[1]);
		}
	}

	abstract protected function configure($presenter);

	/**
	 * @param string $subGrid
	 */
	public function registerSubGrid($subGrid)
	{
		if(!$this->isSubGrid){
			$this->subGrids[] = $subGrid;
		}else{
			$this->parent->registerSubGrid($this->name."-".$subGrid);
		}
	}

	/**
	 * @return array
	 */
	public function getSubGrids()
	{
		if($this->isSubGrid){
			return $this->parent->getSubGrids();
		}else{
			return $this->subGrids;
		}
	}

	/**
	 * @param null|string $gridName
	 * @return string
	 */
	public function getGridPath($gridName = NULL)
	{
		if(empty($gridName)){
			$gridName = $this->name;
		}else{
			$gridName = $this->name."-".$gridName;
		}
		if($this->isSubGrid){
			return $this->parent->getGridPath($gridName);
		}else{
			return $gridName;
		}
	}

	public function findSubGridPath($gridName)
	{
		foreach($this->subGrids as $subGrid){
			$path = explode("-", $subGrid);
			if(end($path) == $gridName){
				return $subGrid;
			}
		}
	}

	/**
	 * @param string $columnName
	 * @return \Nette\Forms\IControl
	 * @throws UnknownColumnException
	 */
	public function getColumnInput($columnName)
	{
		if(!$this->columnExists($columnName)){
			throw new UnknownColumnException("Column $columnName doesn't exists.");
		}

		return $this['gridForm'][$this->name]['rowForm'][$columnName];
	}

	/**
	 * @param string $name
	 * @param null|string $label
	 * @param null|string $width
	 * @param null|int $truncate
	 * @return Components\Column
	 * @throws DuplicateColumnException
	 * @return \Nifty\Grid\Column
	 */
	protected function addColumn($name, $label = NULL, $width = NULL, $truncate = NULL)
	{
		if(!empty($this['columns']->components[$name])){
			throw new DuplicateColumnException("Column $name already exists.");
		}
		$column = new Components\Column($this['columns'], $name);
		$column->setName($name)
			->setLabel($label)
			->setWidth($width)
			->setTruncate($truncate)
			->injectParent($this);

		return $column;
	}

	/**
	 * @param string $name
	 * @param null|string $label
	 * @return Components\Button
	 * @throws DuplicateButtonException
	 */
	protected function addButton($name, $label = NULL)
	{
		if(!empty($this['buttons']->components[$name])){
			throw new DuplicateButtonException("Button $name already exists.");
		}
		$button = new Components\Button($this['buttons'], $name);
		if($name == self::ROW_FORM){
			$self = $this;
			$primaryKey = $this->primaryKey;
			$button->setLink(function($row) use($self, $primaryKey){
				return $self->link("showRowForm!", $row[$primaryKey]);
			});
		}
		$button->setLabel($label);
		return $button;
	}


	/**
	 * @param string $name
	 * @param null|string $label
	 * @throws DuplicateGlobalButtonException
	 * @return Components\GlobalButton
	 */
	public function addGlobalButton($name, $label = NULL)
	{
		if(!empty($this['globalButtons']->components[$name])){
			throw new DuplicateGlobalButtonException("Global button $name already exists.");
		}
		$globalButton = new Components\GlobalButton($this['globalButtons'], $name);
		if($name == self::ADD_ROW){
			$globalButton->setLink($this->link("addRow!"));
		}
		$globalButton->setLabel($label);
		return $globalButton;
	}

	/**
	 * @param string $name
	 * @param null|string $label
	 * @return Components\Action
	 * @throws DuplicateActionException
	 */
	public function addAction($name, $label = NULL)
	{
		if(!empty($this['actions']->components[$name])){
			throw new DuplicateActionException("Action $name already exists.");
		}
		$action = new Components\Action($this['actions'], $name);
		$action->setName($name)
			->setLabel($label);

		return $action;
	}

	/**
	 * @param string $name
	 * @param null|string $label
	 * @return Components\Action
	 * @throws DuplicateActionException
	 */
   /*
	public function addFrmCtrl($name, $type, $label = NULL)
	{
		if(!empty($this['frmctrls']->components[$name])){
			throw new DuplicateActionException("FrmCtrl $name already exists.");
		}
		$frmctrl = new Component\FrmCtrl($this['frmctrls'], $name);
		$frmctrl->setName($name)->setLabel($label);

		return $frmctrl;
	}

  */

	/**
	 * @param string $name
	 * @param null|string $label
	 * @return Components\SubGrid
	 * @throws DuplicateSubGridException
	 */
	public function addSubGrid($name, $label = NULL)
	{
		if(!empty($this['subGrids']->components[$name]) || in_array($name, $this->getSubGrids())){
			throw new DuplicateSubGridException("SubGrid $name already exists.");
		}
		$self = $this;
		$primaryKey = $this->primaryKey;
		$subGrid = new Components\SubGrid($this['subGrids'], $name);
		$subGrid->setName($name)
			->setLabel($label);
		if($this->activeSubGridName == $name){
			$subGrid->setClass("grid-subgrid-close");
			$subGrid->setClass(function($row) use ($self, $primaryKey){
				return $row[$primaryKey] == $self->activeSubGridId ? "grid-subgrid-close" : "grid-subgrid-open";
			});
			$subGrid->setLink(function($row) use ($self, $name, $primaryKey){
				$link = $row[$primaryKey] == $self->activeSubGridId ? array("activeSubGridId" => NULL, "activeSubGridName" => $name) : array("activeSubGridId" => $row[$primaryKey], "activeSubGridName" => $name);
				return $self->link("this", $link);
			});
		}
		else{
			$subGrid->setClass("grid-subgrid-open")
			->setLink(function($row) use ($self, $name, $primaryKey){
				return $self->link("this", array("activeSubGridId" => $row[$primaryKey], "activeSubGridName" => $name));
			});
		}
		return $subGrid;
	}

	/**
	 * @return array
	 */
	public function getColumnNames()
	{
		$columns = array();
		foreach($this['columns']->components as $column){
			$columns[] = $column->name;
		}
		return $columns;
	}

	/**
	 * @return int $count
	 */
	public function getColsCount()
	{
		$count = count($this['columns']->components);
		if ($this->hasActionForm()) $count++;
		if ($this->hasButtons() || $this->hasFilterForm()) $count++;
		$count += count($this['subGrids']->components);

		return $count;
	}

	/**
	 * @param DataSource\IDataSource $dataSource
	 */
	protected function setDataSource(DataSource\IDataSource $dataSource)
	{
		$this->dataSource = $dataSource;
		$this->primaryKey = $this->dataSource->getPrimaryKey();
	}

	/**
	 * @param string $gridName
	 */
	public function setGridName($gridName)
	{
		$this->gridName = $gridName;
	}

	/**
	 * @param string $width
	 */
	public function setWidth($width)
	{
		$this->width = $width;
	}

	/**
	 * @param string $messageNoRecords
	 */
	public function setMessageNoRecords($messageNoRecords)
	{
		$this->messageNoRecords = $messageNoRecords;
	}

	/**
	 * @param string $order
	 */
	public function setDefaultOrder($order)
	{
		$this->defaultOrder = $order;
	}

	/**
	 * @param array $values
	 * @return array
	 */
	protected function setPerPageValues(array $values)
	{
		$perPageValues = array();
		foreach($values as $value){
			$perPageValues[$value] = $value;
		}
		$this->perPageValues = $perPageValues;
	}

	/**
	 * @return bool
	 */
	public function hasHeaders2()
	{
		return $this->headers2;
	}

	/**
	 * @return bool
	 */
	public function hasHeaders3()
	{
		return $this->headers3;
	}

	/**
	 * @return bool
	 */
	public function hasButtons()
	{
		return count($this['buttons']->components) ? TRUE : FALSE;
	}

	/**
	 * @return bool
	 */
	public function hasGlobalButtons()
	{
		return count($this['globalButtons']->components) ? TRUE : FALSE;
	}

	/**
	 * @return bool
	 */
	public function hasFilterForm()
	{
		foreach($this['columns']->components as $column){
			if(!empty($column->filterType))
				return TRUE;
		}
		return FALSE;
	}

	/**
	 * @return bool
	 */
	public function hasActionForm()
	{
		return count($this['actions']->components) ? TRUE : FALSE;
	}

	/**
	 * @return bool
	 */
	public function hasActiveFilter()
	{
		return count($this->filter) ? TRUE : FALSE;
	}

	/**
	 * @param string $filter
	 * @return bool
	 */
	public function isSpecificFilterActive($filter)
	{
		if(isset($this->filter[$filter])){
			return ($this->filter[$filter] != '') ? TRUE : FALSE;
		}
		return false;
	}

	/**
	 * @return bool
	 */
	public function hasActiveOrder()
	{
		return !empty($this->order) ? TRUE: FALSE;
	}

	/**
	 * @return bool
	 */
	public function hasDefaultOrder()
	{
		return !empty($this->defaultOrder) ? TRUE : FALSE;
	}

	/**
	 * @return bool
	 */
	public function hasEnabledSorting()
	{
		return $this->enableSorting;
	}

	/**
	 * @return bool
	 */
	public function hasActiveItemPerPage()
	{
		return !empty($this->perPage) ? TRUE : FALSE;
	}

	public function hasActiveRowForm()
	{
		return !empty($this->activeRowForm) ? TRUE : FALSE;
	}


  /**
	 * @return bool
	 */
	public function hasGridPanel()
	{
		return !empty($this->gridPanel) ? TRUE : FALSE;
	}


	/**
	 * @param string $column
	 * @return bool
	 */
	public function columnExists($column)
	{
		return isset($this['columns']->components[$column]);
	}

	/**
	 * @param string $subGrid
	 * @return bool
	 */
	public function subGridExists($subGrid)
	{
		return isset($this['subGrids']->components[$subGrid]);
	}

	/**
	 * @return bool
	 */
	public function isEditable()
	{
		foreach($this['columns']->components as $component){
			if($component->editable)
				return TRUE;
		}
		return FALSE;
	}

	/**
	 * @return bool
	 */
	public function hasActiveSubGrid()
	{
		return (!empty($this->activeSubGridId) && !empty($this->activeSubGridName) && $this->subGridExists($this->activeSubGridName)) ? TRUE : FALSE;
	}

	/**
	 * @return mixed
	 * @throws InvalidFilterException
	 * @throws UnknownColumnException
	 * @throws UnknownFilterException
	 */
	protected function filterData()
	{
    // MKOLES
    if ($this->log_flag) {
        error_log('start filter data');
    }

		try{
			$filters = array();
			foreach($this->filter as $name => $value){
				if(!$this->columnExists($name)){
					throw new UnknownColumnException("Neexistující sloupec $name");

				}
				if(!$this['columns-'.$name]->hasFilter()){
					throw new UnknownFilterException("Neexistující filtr pro sloupec $name");
				}

				$type = $this['columns-'.$name]->getFilterType();
				$filter = FilterCondition::prepareFilter($value, $type);

				if(method_exists("\\NiftyGrid\\FilterCondition", $filter["condition"])){
          if ($filter["condition"]==\NiftyGrid\FilterCondition::DATE_INTERVAL) {
  					$filter = call_user_func("\\NiftyGrid\\FilterCondition::".$filter["condition"], $filter["value1"],$filter["value2"]);
          } else {
  					$filter = call_user_func("\\NiftyGrid\\FilterCondition::".$filter["condition"], $filter["value"]);
          }
					if(!empty($this['gridForm'][$this->name]['filter'][$name])){
						$filter["column"] = $name;
						if(!empty($this['columns-'.$filter["column"]]->tableName)){
							$filter["column"] = $this['columns-'.$filter["column"]]->tableName;
						}
						$filters[] = $filter;
					}else{
						throw new InvalidFilterException("Neplatný filtr");
					}
				}else{
					throw new InvalidFilterException("Neplatný filtr");
				}
			}

      //MKOLES error_log filter data
      if ($this->log_flag) {
           error_log(print_r($filters,TRUE));
      }
      //END MKOLES

			return $this->dataSource->filterData($filters);
		}
		catch(UnknownColumnException $e){
			$this->flashMessage($e->getMessage(), "grid-error");
			$this->redirect("this", array("filter" => NULL));
		}
		catch(UnknownFilterException $e){
			$this->flashMessage($e->getMessage(), "grid-error");
			$this->redirect("this", array("filter" => NULL));
		}
	}

	/**
	 * @param string $order
	 * @throws InvalidOrderException
	 */
	protected function orderData($order)
	{
		try{
			$order = explode(" ", $order);
			if(in_array($order[0], $this->getColumnNames()) && in_array($order[1], array("ASC", "DESC")) && $this['columns-'.$order[0]]->isSortable()){
				if(!empty($this['columns-'.$order[0]]->tableName)){
					$order[0] = $this['columns-'.$order[0]]->tableName;
				}
				$this->dataSource->orderData($order[0], $order[1]);
			}else{
				throw new InvalidOrderException("Neplatné seřazení.");
			}
		}
		catch(InvalidOrderException $e){
			$this->flashMessage($e->getMessage(), "grid-error");
			$this->redirect("this", array("order" => NULL));
		}
	}

	/**
	 * @return int
	 */
	protected function getCount()
	{
		if(!$this->dataSource) throw new GridException("DataSource not yet set");
		if($this->paginate){
			$count = $this->dataSource->getCount();
			$this->getPaginator()->itemCount = $count;
			$this->dataSource->limitData($this->getPaginator()->itemsPerPage, $this->getPaginator()->offset);
			return $count;
		}else{
      if ($this->nogetcount) {
    			$count = 0;
    			$this->getPaginator()->itemCount = 0;
      } else {
    			$count = $this->dataSource->getCount();
    			$this->getPaginator()->itemCount = $count;
      }
			return $count;
		}
	}

	/**
	 * @return GridPaginator
	 */
	protected function createComponentPaginator()
	{
		return new GridPaginator;
	}

	/**
	 * @return \Nette\Utils\Paginator
	 */
	public function getPaginator()
	{
		return $this['paginator']->paginator;
	}

	/**
	 * @param int $page
	 */
	public function handleChangeCurrentPage($page)
	{
		if($this->presenter->isAjax()){
			$this->redirect("this", array("paginator-page" => $page));
		}
	}

	/**
	 * @param int $perPage
	 */
	public function handleChangePerPage($perPage)
	{
		if($this->presenter->isAjax()){
			$this->redirect("this", array("perPage" => $perPage));
		}
	}

	/**
	 * @param string $column
	 * @param string $term
	 */
	public function handleAutocomplete($column, $term)
	{
		if($this->presenter->isAjax()){
			if(!empty($this['columns']->components[$column]) && $this['columns']->components[$column]->autocomplete){
				$this->filter[$column] = $term."%";
				$this->filterData();
				$this->dataSource->limitData($this['columns']->components[$column]->getAutocompleteResults(), NULL);
				$data = $this->dataSource->getData();
				$results = array();
				foreach($data as $row){
					$value = $row[$column];
					if(!in_array($value, $results)){
						$results[] = $row[$column];
					}
				}
				$this->presenter->payload->payload = $results;
				$this->presenter->sendPayload();
			}
		}
	}

	public function handleAddRow()
	{
		$this->showAddRow = TRUE;
	}

	/**
	 * @param int $id
	 */
	public function handleShowRowForm($id)
	{
		$this->activeRowForm = $id;
	}

	/**
	 * @param $callback
	 */
	public function setRowFormCallback($callback)
	{
		$this->rowFormCallback = $callback;
	}

	/**
	 * @param int $id
	 * @return \Nette\Forms\Controls\Checkbox
	 */
	public function assignCheckboxToRow($id)
	{
		$this['gridForm'][$this->name]['action']->addCheckbox("row_".$id);
		$this['gridForm'][$this->name]['action']["row_".$id]->getControlPrototype()->class[] = "grid-action-checkbox";
		return $this['gridForm'][$this->name]['action']["row_".$id]->getControl();
	}

	protected function createComponentGridForm()
	{
    if ($this->log_flag) {
        error_log('enter createComponentGridForm');
    }
    \Nette\Forms\Container::extensionMethod('addDatePicker', function($form, $name, $label, $cols = NULL, $maxLength = NULL)
    {
      return $form[$name] = new \RadekDostal\NetteComponents\DateTimePicker\DatePicker($label, $cols, $maxLength);
    });
    \Nette\Forms\Container::extensionMethod('addDateTimePicker', function($form, $name, $label, $cols = NULL, $maxLength = NULL)
    {
      return $form[$name] = new \RadekDostal\NetteComponents\DateTimePicker\DateTimePicker($label, $cols, $maxLength);
    });

    $form = new \Nette\Application\UI\Form;
		$form->method = "POST";
		$form->getElementPrototype()->class[] = "grid-gridForm";

		$form->addContainer($this->name);

		$form[$this->name]->addContainer("rowForm");
		$form[$this->name]['rowForm']->addSubmit("send","Uložit");
		$form[$this->name]['rowForm']['send']->getControlPrototype()->addClass("grid-editable");

		$form[$this->name]->addContainer("filter");
		$form[$this->name]['filter']->addHidden("x_source_download_id");
		$form[$this->name]['filter']->addSubmit("send","ui_grid_filter_button")
			->setValidationScope(FALSE);

		$form[$this->name]->addContainer("action");
		$form[$this->name]['action']->addSelect("action_name","ui_grid_action_label");
        //MKOLES
		$form[$this->name]['action']->addHidden("download_id");
		$form[$this->name]['action']->addHidden("params_id");
		$form[$this->name]['action']->addSubmit("send","ui_grid_action_button")
			->setValidationScope(FALSE)
			->getControlPrototype()
			->addData("select", $form[$this->name]["action"]["action_name"]->getControl()->name);
		$form[$this->name]->addContainer("frmctrls");

		$form[$this->name]->addContainer('perPage');
		$form[$this->name]['perPage']->addSelect("perPage","ui_grid_paginator_label", $this->perPageValues)
			->getControlPrototype()
			->addClass("grid-changeperpage")
			->addData("gridname", $this->getGridPath())
			->addData("link", $this->link("changePerPage!"));
		$form[$this->name]['perPage']->addSubmit("send","Ok")
			->setValidationScope(FALSE)
			->getControlPrototype()
			->addClass("grid-perpagesubmit");
		$form[$this->name]->addContainer('excelExport');
		$form[$this->name]['excelExport']->addHidden("isExcelExport",1)
			->getControlPrototype()
			->addClass("grid-isExcelExport")
      ->setDefaultValue('N')
      ;

		$form->setTranslator($this->getTranslator());

		$form->onSuccess[] = [$this, 'processGridForm'];

		return $form;
	}

	/**
	 * @param array $values
	 */
	public function processGridForm($values)
	{
    // MKOLES
    if ($this->log_flag) {
         error_log(print_r('start processGridForm',TRUE));
    }
		$values_orig = $values;
		$values_data = $values->getHttpData();
    $values = $values->getHttpData();
    // MKOLES only in debug
    if ($this->log_flag) {
         error_log('variable values');
         error_log(print_r($values,TRUE));
    }
    // MKOLES END only in debug
		foreach($values as $gridName => $grid){
      // MKOLES only in debug
      if ($this->log_flag) {
          error_log('variable grid');
          error_log(print_r($grid,TRUE));
      }
      // MKOLES END only in debug
      if (is_array($grid)) {
    			foreach($grid as $section => $container){
        		if($section == "excelExport"){
                if ($container['isExcelExport']=='Y') {
               	 	  $this->exportFormSubmitted($values);
                    break 2;
                }
          	}

    				foreach($container as $key => $value){
    					if($key == "send"){
    						unset($container[$key]);
    						$subGrids = $this->subGrids;
    						foreach($subGrids as $subGrid){
    							$path = explode("-", $subGrid);
    							if(end($path) == $gridName){
    								$gridName = $subGrid;
    								break;
    							}
    						}
    						if($section == "filter"){
                  // MKOLES only in debug
                  if ($this->log_flag) {
                       error_log('before filterFormSubmitted');
                       error_log(print_r($values,TRUE));
                  }
                  // MKOLES END only in debug
    							$this->filterFormSubmitted($values);
                  // MKOLES only in debug
                  if ($this->log_flag) {
                      error_log('after filterFormSubmitted');
                  }
                  // MKOLES END only in debug
    						}
    						$section = ($section == "rowForm") ? "row" : $section;
    						if(method_exists($this, $section."FormSubmitted")){
                  if ($section."FormSubmitted"=='actionFormSubmitted') {
        							call_user_func("self::".$section."FormSubmitted", $container, $gridName, $values_data);
                  }
                  elseif ($section."FormSubmitted"=='rowFormSubmitted') {
        							call_user_func("self::".$section."FormSubmitted", $container, $gridName, $values_orig);
                  } else {
        							call_user_func("self::".$section."FormSubmitted", $container, $gridName);
                  }
    						}else{
    							$this->redirect("this");
    						}
    						break 3;
    					}
    				}
    			}
      }
		}
	}

	/**
	 * @param array $values
	 * @param string $gridName
	 */
	public function rowFormSubmitted($values, $gridName, $values_orig)
	{
		$subGrid = ($gridName == $this->name) ? FALSE : TRUE;
		if($subGrid){
			call_user_func($this[$gridName]->rowFormCallback, (array) $values);
		}else{
			call_user_func($this->rowFormCallback, (array) $values, $this, $values_orig);
		}
    //$this->presenter->redirect("this");
		//$this->redirect("this");
	}

	/**
	 * @param array $values
	 * @param string $gridName
	 */
	public function perPageFormSubmitted($values, $gridName)
	{
		$perPage = ($gridName == $this->name) ? "perPage" : $gridName."-perPage";

		$this->redirect("this", array($perPage => $values["perPage"]));
	}

	/**
	 * @param array $values
	 * @param string $gridName
	 * @throws NoRowSelectedException
	 */
	public function actionFormSubmitted($values, $gridName, $values_orig)
	{
		try{
			$rows = array();
			foreach($values as $name => $value){
				if(\Nette\Utils\Strings::startsWith($name, "row")){
					$vals = explode("_", $name);
					if((boolean) $value){
						$rows[] = $vals[1];
					}
				}
			}
			$subGrid = ($gridName == $this->name) ? FALSE : TRUE;
			if(!count($rows)){
				throw new NoRowSelectedException("Nebyl vybrán žádný záznam.");
			}
			if($subGrid){
        if ($this[$gridName]['actions']->components[$values['action_name']]->getDiValues()) {
    				call_user_func($this[$gridName]['actions']->components[$values['action_name']]->getCallback(), $rows, $values_orig);
        } else {
    				call_user_func($this[$gridName]['actions']->components[$values['action_name']]->getCallback(), $rows);
        }
			}else{
        if ($this['actions']->components[$values['action_name']]->getDiValues()) {
            // MKOLES
            if ($this->log_flag) {
                 error_log(print_r($values_orig,TRUE));
            }
     				call_user_func($this['actions']->components[$values['action_name']]->getCallback(), $rows, $values_orig);
        } else {
            if ($this->log_flag) {
                 error_log('DiValues FALSE');
            }
     				call_user_func($this['actions']->components[$values['action_name']]->getCallback(), $rows);
        }
			}
			$this->redirect("this");
		}
		catch(NoRowSelectedException $e){
			if($subGrid){
				$this[$gridName]->flashMessage("Nebyl vybrán žádný záznam.","grid-error");
			}else{
				$this->flashMessage("Nebyl vybrán žádný záznam.","grid-error");
			}
			$this->redirect("this");
		}
	}

	/**
	 * @param array $values_orig
	 */
	public function exportFormSubmitted($values_orig)
	{
    $export_excel_flag = FALSE;
    $export_type = 'EXCEL';
    $export_excel_template = "/../../templates/gridexcelexport.xlsx";
    $export_excel_template_flag = FALSE;

    if(strpos($this->name,'aclGrid') !== false){
      $grid_condition_name = $this->gridName.(isset($this->gridSelector)?$this->gridSelector:'');
    }
    else {
      $grid_condition_name = $this->name.(isset($this->gridSelector)?$this->gridSelector:'');
    }

    $grid_def = $this->presenter->getDatabase()->table(self::GRID_GRIDS_TABLE_NAME)->where('gridname',$grid_condition_name)->fetch();
    if ($grid_def) {
      if ($grid_def->exportable_flag=='Y' && $this->presenter->user->isAllowed($grid_def->exportable_resource,$grid_def->exportable_action)) {
        $export_excel_flag = TRUE;
        if (isset($grid_def->export_template) && $grid_def->export_template<>'') {
            $export_excel_template = "/../../../../app/".$grid_def->export_template;
            $export_excel_template_flag = TRUE;
        }
      }
    }

    if (!$export_excel_flag) {
	    $this->presenter->flashMessage("Nemáte oprávnění k exportu do excelu","error");
      $this->redirect("this");
    }

    require_once EXCEL_LIBS_DIR."Zip/ZipStream.php";
    require_once EXCEL_LIBS_DIR."PHPExcel.php";

    if ($export_excel_flag) {
        //CSV
        if ($export_type=='CSV') {
            //$this->dataSource->filterData($filters);
            $this->filterData();
            $rows = $this->dataSource->getData();

            $data = '';
            $rc = 0;
            foreach($rows as $row) {
                $rc = $rc + 1;
                if ($rc>1) {
                    $data = $data.chr(10);
                }

                $cc = 0;
                foreach($this['columns']->components as $component){
                    if ($component->isExcelExportable) {
                       $cc = $cc + 1;
                       if ($cc==1) {
                          $data=$data.$row[$component->name];
                       } else {
                          $data=$data.';'.$row[$component->name];
                       }
                    }
                }
            }

            $name = 'mikoxxxxx.csv';
            //$data = 'sdsss;ssss';
            $size = strlen($data);
            $type = 'text/csv';  // csv

            header('Cache-Control: no-cache');
            header("Last-Modified: " . gmdate("D, d M Y H:i:s T"));
            header('Expires: 0');
            header("Accept-Ranges: bytes");
            Header("Content-type: ".$type);
            header("Content-Disposition: attachment; filename=\"".$name."\"");
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: ' . $size);

            $this->presenter->sendResponse(new Nette\Application\Responses\TextResponse($data));
            $this->presenter->terminate();
        }
        if ($export_type=='EXCEL') {
            $reportfile = __DIR__.$export_excel_template;
            $inputFileType = \PHPExcel_IOFactory::identify($reportfile);
            $objReader = \PHPExcel_IOFactory::createReader($inputFileType);
            try {$objPHPExcel = $objReader->load($reportfile);}
            catch(\PHPExcel_Reader_Exception $e)
               {
                    $this->flashMessage($e->getMessage(), 'error');
                    $this->redirect("this");
               }
            $datumfile = date("YmdHis");
            $grid_id = $grid_def->id;
            $grid_id = str_pad($grid_id,6,'0',STR_PAD_LEFT);
            $name = 'MIKOS_gridexport_'.$grid_id.'_'.$this->presenter->user->id.'_'.$datumfile.'.xlsx';
            $outputfile = TEMP_DIR.'Files/'.$name;

            $this->filterData();
            $rows = $this->dataSource->getData();

            // export header
            $cc = 0;

            $columns_def = $this->presenter->getDatabase()->table(self::GRID_COLUMNS_TABLE_NAME)->where('grid_id',$grid_def->id)->order('column_order')->fetchAll();
            if (!$export_excel_template_flag) {
                foreach($columns_def as $column){
                    if ($column->exportable_flag=='Y') {
                       $cc = $cc + 1;
                       $objPHPExcel->getSheet(0)->getCellByColumnAndRow(-1+$cc,1)->setValue($column->column_description);
                    }
                }
            }

            // export data
            $rc = 0;
            foreach($rows as $row) {
                $rc = $rc + 1;
                $cc = 0;
                foreach($columns_def as $column){
                    if ($column->exportable_flag=='Y') {
                       $cc = $cc + 1;
                       //if ($row[$column->db_column_name] instanceof Nette\Utils\DateTime) {
                       //   $objPHPExcel->getSheet(0)->getCellByColumnAndRow(-1+$cc,1+$rc)->setValue(''.$row[$column->db_column_name]);
                       //} else {
//                              $objPHPExcel->getSheet(0)->getCellByColumnAndRow(-1+$cc,1+$rc)->setValue(''.$row[$column->db_column_name]);

                       //}
                        if ($column->export_format_function=='date') {
                            if (!isset($row[$column->db_column_name])) {
                                $objPHPExcel->getSheet(0)->getCellByColumnAndRow(-1+$cc,1+$rc)->setValue('');
                            } else {
                                //$objPHPExcel->getSheet(0)->getCellByColumnAndRow(-1+$cc,1+$rc)->setValue(''.date($column->export_format_mask, strtotime($row[$column->db_column_name])));
                                $objPHPExcel->getSheet(0)->getCellByColumnAndRow(-1+$cc,1+$rc)->setValue(\PHPExcel_Shared_Date::PHPToExcel($row[$column->db_column_name]));
                                $objPHPExcel->getSheet(0)->getStyle(chr(ord('A')-1+$cc).(1+$rc))->getNumberFormat()->setFormatCode(str_replace(':i:',':m:',$column->export_format_mask));
                                //->setCellValueExplicit('A1', $val,PHPExcel_Cell_DataType::TYPE_NUMERIC);

                            }
                        } elseif ($column->export_format_function=='number') {
                            $objPHPExcel->getSheet(0)->getCellByColumnAndRow(-1+$cc,1+$rc)->setValue($row[$column->db_column_name]);
                            $objPHPExcel->getSheet(0)->getStyle(chr(ord('A')-1+$cc).(1+$rc))->getNumberFormat()->setFormatCode($column->export_format_mask);
                        } elseif ($column->export_format_function=='text') {
                            $objPHPExcel->getSheet(0)->getCellByColumnAndRow(-1+$cc,1+$rc)->setValueExplicit($row[$column->db_column_name],\PHPExcel_Cell_DataType::TYPE_STRING);
                        } else {
                            $mes = $row[$column->db_column_name];
                            $mes = preg_replace('/^=/', "'=", $mes, 1);
                            $objPHPExcel->getSheet(0)->getCellByColumnAndRow(-1+$cc,1+$rc)->setValue(''.$mes);
                        }
                    }
                }
            }
            // save excel
            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
            $objWriter->save($outputfile);

            // read excel
            $File = $outputfile;
            $Handle = fopen($File, 'rb');
            $data = fread($Handle, filesize($File));
            fclose($Handle);

            // send excel
            $size = strlen($data);
            $type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';  // xlsx

            header('Cache-Control: no-cache');
            header("Last-Modified: " . gmdate("D, d M Y H:i:s T"));
            header('Expires: 0');
            header("Accept-Ranges: bytes");
            Header("Content-type: ".$type);
            header("Content-Disposition: attachment; filename=\"".$name."\"");
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: ' . $size);
            $this->presenter->sendResponse(new Nette\Application\Responses\TextResponse($data));
            $this->presenter->terminate();
        }
    }

		$this->presenter->redirect("this");
	}

	/**
	 * @param array $values_orig
	 */
	public function filterFormSubmitted($values_orig)
	{
    /* added by MKOLES 2014.10.26 */
    $values = $values_orig;
    foreach($values as $gridName => $grid){
       if ($gridName=='do') {
          unset($values[$gridName]);
       }
    }
    /* end by MKOLES 2014.10.26 */

   // MKOLES only in debug
   if ($this->log_flag) {
       error_log('start filterFormSubmitted');
       error_log(print_r($values_orig,TRUE));
   }
   // MKOLES END only in debug


		$download = array();
		$filters = array();
		$paginators = array();
		foreach($values as $gridName => $grid){
			$isSubGrid = ($gridName == $this->name) ? FALSE : TRUE;
      if (is_array($grid)){
  			foreach($grid['filter'] as $name => $value){
  				if($value != ''){
  					if($name == "send"){
  						continue;
  					}
  					if($name == "x_source_download_id"){
              $download['x_source_download_id'] = $value;
  						continue;
  					}
  					if($isSubGrid){
  						$gridName = $this->findSubGridPath($gridName);
  						$filters[$this->name."-".$gridName."-filter"][$name] = $value;
  					}else{
  						$filters[$this->name."-filter"][$name] = $value;
  					}
  				}
  			}
      }
			if($isSubGrid){
				$paginators[$this->name."-".$gridName."-paginator-page"] = NULL;
				if(empty($filters[$this->name."-".$gridName."-filter"])) $filters[$this->name."-".$gridName."-filter"] = array();
			}else{
				$paginators[$this->name."-paginator-page"] = NULL;
				if(empty($filters[$this->name."-filter"])) $filters[$this->name."-filter"] = array();
			}
		}

   // MKOLES only in debug
   if ($this->log_flag) {
       error_log('before redirect');
       error_log('download');
       error_log(print_r($download,TRUE));
       error_log('filters');
       error_log(print_r($filters,TRUE));
       error_log('paginators');
       error_log(print_r($paginators,TRUE));
   }
   // MKOLES END only in debug
		$this->presenter->redirect("this", array_merge($download,$filters, $paginators));
	}

	/**
	 * @param string $templatePath
	 */
	protected function setTemplate($templatePath)
	{
		$this->templatePath = $templatePath;
	}

	/**
	 * @return bool
	 */
	public function hasSumsRow()
	{
		foreach($this['columns']->components as $column){
			if($column->hasSums) return TRUE;
		}
		return FALSE;
	}

	/**
	 * @return bool or Table
	 */
	protected function getSums()
	{
		if(!$this->dataSource) throw new GridException("DataSource not yet set");
    if($this->hasSumsRow()) {
        $columnswithsum = array();
    		foreach($this['columns']->components as $column){
    			if($column->hasSums) { $columnswithsum[]=$column->name; }
    		}
        return $this->dataSource->getSums($columnswithsum);
    }
    return FALSE;
	}

	public function render()
	{
    $sums = $this->getSums();
		$count = $this->getCount();
		$this->getPaginator()->itemCount = $count;
		$this->template->sums = $sums;
		$this->template->oracle_flag = $this->oracle_flag;
 		$this->template->results = $count;
		$this->template->columns = $this['columns']->components;
		$this->template->buttons = $this['buttons']->components;
		$this->template->globalButtons = $this['globalButtons']->components;
		$this->template->subGrids = $this['subGrids']->components;
		$this->template->paginate = $this->paginate;
		$this->template->colsCount = $this->getColsCount();
		$this->template->refreshTime = $this->refreshTime;
		//$this->refreshURL = $this->presenter->link("this",array_merge($this->filter));//   $this->getPresenter()->getHttpRequest()->getUrl()->getRelativeUrl();
    $this->refreshURL = $this->presenter->link("this");//   $this->getPresenter()->getHttpRequest()->getUrl()->getRelativeUrl();

    if ($this->presenter->log_flag=='Y') { error_log('grid.render - before getData'); }
		$rows = $this->dataSource->getData();
    if ($this->presenter->log_flag=='Y') { error_log('grid.render - before rows'); }
		$this->template->rows = $rows;
    if ($this->presenter->log_flag=='Y') { error_log('grid.render - before primaryKey'); }
		$this->template->primaryKey = $this->primaryKey;
    if ($this->presenter->log_flag=='Y') { error_log('grid.render - before hasActiveRowForm'); }
		if($this->hasActiveRowForm()){
			$row = $rows[$this->activeRowForm];
			foreach($row as $name => $value){
				if($this->columnExists($name) && !empty($this['columns']->components[$name]->formRenderer)){
					$row[$name] = call_user_func($this['columns']->components[$name]->formRenderer, $row);
				}
				if(isset($this['gridForm'][$this->name]['rowForm'][$name])){
					$input = $this['gridForm'][$this->name]['rowForm'][$name];
					if($input instanceof \Nette\Forms\Controls\SelectBox){
						$items = $this['gridForm'][$this->name]['rowForm'][$name]->getItems();
						if(in_array($row[$name], $items)){
							$row[$name] = array_search($row[$name], $items);
						}
					}
				}
			}
      //error_log('rowform');
      //error_log(print_r(iterator_to_array($row),TRUE));
			$this['gridForm'][$this->name]['rowForm']->setDefaults($row);
			$this['gridForm'][$this->name]['rowForm']->addHidden($this->primaryKey, $this->activeRowForm);
		}
    if ($this->presenter->log_flag=='Y') { error_log('grid.render - before paginate'); }
		if($this->paginate){
			$this->template->viewedFrom = ((($this->getPaginator()->getPage()-1)*$this->perPage)+1);
			$this->template->viewedTo = ($this->getPaginator()->getLength()+(($this->getPaginator()->getPage()-1)*$this->perPage));
		}

    // remove oracle and mssql templates, move into one template
    $templatePath = !empty($this->templatePath) ? $this->templatePath : __DIR__."/../../templates/grid.latte";

		if ($this->getTranslator() instanceof \Nette\Localization\ITranslator) {
			$this->template->setTranslator($this->getTranslator());
		}

		$this->template->setFile($templatePath);
    if ($this->presenter->log_flag=='Y') { error_log('grid.render - before render'); }
		$this->template->render();
	}

	/**
	 * @param \Nette\Localization\ITranslator $translator
	 * @return Grid
	 */
	public function setTranslator(\Nette\Localization\ITranslator $translator)
	{
		$this->translator = $translator;

		return $this;
	}

	/**
	 * @return \Nette\Localization\ITranslator|null
	 */
	public function getTranslator()
	{
		if($this->translator instanceof \Nette\Localization\ITranslator)
			return $this->translator;

		return null;
	}

	/**
	 */
	public function setGlobalButtonExcel($name='excelexp',$label='Export to excel')
	{
    //$this->gbexcel = FALSE;
    $link = $this->presenter->link("excelexp");
    return
      $this->addGlobalButton($name, $label)
         ->setForm(TRUE)
         ->setAjax(FALSE)
         ->setClass('fa fa-file-excel-o fa-file-excel')
         ->setLink($link)
         ;
  }

  //MKOLES
	public function hasDownloadID()
	{
        return $this->noredownload_flag;
	}

    //MKOLES
	public function hasMovableHead()
	{
        $movable = $this->presenter->getDatabase()->table(self::CONFIG_TABLE_NAME)->where('code','MOVABLE_HEAD_FLAG')->fetch();
        if ($movable && $movable->value == 'Y'){
            if (isset($this->movablehead_flag)) {
                return $this->movablehead_flag;
            }
            return false;
        }
        elseif ($movable && $movable->value == 'A') {
            if (isset($this->movablehead_flag)) {
                return $this->movablehead_flag;
            }
            return true;
        }
        else {
            return false;
        }
	}

}
