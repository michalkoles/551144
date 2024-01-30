<?php

namespace App\Presenters;

use Nette\Http\Session as NSession;
use Nette\Database\Context as NdbContext;
use Nette\Security\User as NUser;
use Nette\Forms\Form;
use NiftyGrid\Grid;
use NiftyGrid\NDataSource;

class AclGeneralGrid extends Grid
{

    protected $acl_grid;
    protected $user;
    protected $data;
    protected $sub_id;
    protected $cols;
    protected $buttons;
    protected $globalbuttons;
    protected $globalactions;
    protected $lovs;
    private $sortable_counter;
    public $grid;

    public function __construct($p_grid,$p_user,$p_data,$p_sub_id=null)
    {
        parent::__construct();
        $this->grid = null;
        $this->acl_grid = $p_grid;
        $this->user = $p_user;
        $this->data = $p_data;
        $this->sub_id = $p_sub_id;
        $this->sortable_counter = 0;
    }

    protected function configure($presenter)
    {
        $user = $this->user;

        $this->gridName = $this->acl_grid->grid_name;

        /*
        error_log('master_id:'.$presenter->master_id);
        error_log('sub_id:'.$this->sub_id);
        error_log('sql_where:'.$this->acl_grid->sql_where);
        error_log('source_view_order:'.$this->acl_grid->source_view_order);
        */
        
        //Get source data
        $query = $this->data->select('*');
        if (isset($this->acl_grid->sql_where)) {
            $query=$query->where(str_replace('$master_id',$presenter->master_id,$this->acl_grid->sql_where));
        }
        if(isset($this->sub_id)){
            $query=$query->where($this->acl_grid->reference_to_parent,$this->sub_id);
        }
        if (isset($presenter->master_id) && isset($this->acl_grid->reference_to_parent) && !isset($this->sub_id)) {
            $query=$query->where($this->acl_grid->reference_to_parent,$presenter->master_id);
        }
        if (isset($this->acl_grid->source_view_order)) {
            $query=$query->order($this->acl_grid->source_view_order);
        }
        $source = new \NiftyGrid\Datasource\NDataSource($query);
        //Set Source
        $this->setDataSource($source);
        // Load Grid Structure
        $sql = "select g.*
                from mikis.sa_grids g
                where g.module_name=?
                and g.presenter_name=?
                and g.gridname = ?
                ";
        $this->grid = $presenter->database->query($sql,$presenter->acl_action->module_name, $presenter->acl_action->presenter_name, $this->acl_grid->grid_name)->fetch();
        /*
        if (!$this->grid) {
            $presenter->flashMessage('Grid Layout not found !'.'error');
            $presenter->redirect(':Homepage:');
        }
        */
        $sql = "select gc.*
                from mikis.sa_grid_columns gc, mikis.sa_grids g
                where g.id = gc.grid_id
                and g.module_name=?
                and g.presenter_name=?
                and g.gridname = ?
                order by gc.column_order, g.id, gc.id
                ";
        $this->cols = $presenter->database->query($sql,$presenter->acl_action->module_name, $presenter->acl_action->presenter_name, $this->acl_grid->grid_name)->fetchAll();
        // Load Buttons
        $sql = "select ga.*
                from acl.acl_grid_action ga
                where ga.grid_id = (select g.id from acl.acl_grid g where g.action_id=? and g.grid_name=?)
                and ga.action_type = 'B'
                order by action_order
                ";
        $this->buttons = $presenter->database->query($sql,$presenter->acl_action->id, $this->acl_grid->grid_name)->fetchAll();
        // Load GlobalButtons
        $sql = "select ga.*
                from acl.acl_grid_action ga
                where ga.grid_id = (select g.id from acl.acl_grid g where g.action_id=? and g.grid_name=?)
                and ga.action_type = 'GB'
                order by action_order
                ";
        $this->globalbuttons = $presenter->database->query($sql,$presenter->acl_action->id, $this->acl_grid->grid_name)->fetchAll();
        // Load GlobalActions
        $sql = "select ga.*
                from acl.acl_grid_action ga
                where ga.grid_id = (select g.id from acl.acl_grid g where g.action_id=? and g.grid_name=?)
                and ga.action_type = 'A'
                order by action_order
                ";
        $this->globalactions = $presenter->database->query($sql,$presenter->acl_action->id, $this->acl_grid->grid_name)->fetchAll();

        foreach ($this->cols as $col) {
            if($col->grid_visible_flag == "Y"){
                  $header_cell_renderer = '';
                  $cell_renderer = '';
                  $field_renderer = '';

                  $fc = $this->addColumn($col->db_column_name, $col->column_description, $col->column_size.$col->column_size_uom);
                  // set predefined features
                  $fc->setSortable(FALSE);

                  // set features from definition
                  if($col->export_format_function == "date"){
                      $fc->setRenderer(function($row) use ($col) {return ($row[$col->db_column_name]==null)? '':date($col->export_format_mask, strtotime($row[$col->db_column_name]));});
                  }
                  if(isset($col->column_field_renderer) and ($col->column_field_renderer!='')) {
                      $field_renderer = $col->column_field_renderer;
                      $fc->setRenderer(function($row) use ($field_renderer) {return eval($field_renderer);});
                  }
                  if($col->column_alignment == "R"){
                      $header_cell_renderer = $header_cell_renderer.'text-align: right;';
                  }
                  if($col->column_alignment == "R"){
                      //$fc->setRenderer(function($row,$column){return '<span style="float: right;">'.$row[$column->name].'</span>';});
                      //$fc->setCellRenderer(function($row){return 'text-align: right;';});
                      $cell_renderer = $cell_renderer.'text-align: right;';
                  }
                  if($col->column_alignment == "C"){
                      //$fc->setCellRenderer(function($row){return 'text-align: center;';});
                      $cell_renderer = $cell_renderer.'text-align: center;';
                  }
                  if (isset($col['column_cell_renderer'])) {
                      //$cell_renderer = $cell_renderer.(eval("return \"".addslashes($col["column_cell_renderer"])."\";"));
                      $cell_renderer = $col["column_cell_renderer"];
                  }


                  if($col->grid_filter_type == "N"){
                      $fc->setNumericFilter();
                  }
                  else if ($col->grid_filter_type == "T"){
                      $fc->setTextFilter();
                  }
                  if (isset($col['grid_no_escape'])) {
                      if($col->grid_no_escape == "Y"){
                          $fc->setNoescape(TRUE);
                      }
                  }
                  if (isset($col['grid_no_escape_flag'])) {
                      if($col->grid_no_escape_flag == "Y"){
                          $fc->setNoescape(TRUE);
                      }
                  }
                  if (isset($col['sortable_flag'])) {
                      if($col->sortable_flag == "Y"){
                          $this->sortable_counter = $this->sortable_counter + 1 ;
                          $fc->setSortable(TRUE);
                      }
                  }

                  if ($cell_renderer!='') {
                      $fc->setCellRenderer(function($row) use ($cell_renderer){return $cell_renderer;});
                  }
                  if ($header_cell_renderer!='') {
                      $fc->setHeaderCellRenderer($header_cell_renderer);
                  }

            }

        }

        $bl=$presenter->storeRequest();

        // Buttons
        foreach ($this->buttons as $button) {
            $newbutton=$this->addButton($button->id.'_'.str_replace(':','__',$button->action_link), $button->action_name)
                ->setClass($button->action_icon)
                ->setLink(function($row) use ($presenter,$bl,$button){
                    if (isset($presenter->master_id)) {
                        return $presenter->link($button->action_link,$presenter->master_id,$row['id'],$bl);
                    }
                    return $presenter->link($button->action_link,array($row['id'],"bl"=>$bl));
                    })
                ->setAjax(FALSE);
            if (isset($button->show_where) and $button->show_where!==NULL) {
                $newbutton->setShow(function($row) use ($presenter,$bl,$button){return eval("return ".$button->show_where.";");});
            }

            if (isset($button->show_confirmation_text) and $button->show_confirmation_text!==NULL) {
                $newbutton->setConfirmationDialog(function($row) use ($button) {return eval("return \"".$button->show_confirmation_text."\";");});
            }
            if (isset($button->action_target) and $button->action_target!==NULL) {
                $newbutton->setTarget($button->action_target);
            }
        }
        // Global Buttons
        foreach ($this->globalbuttons as $button) {
            if (isset($button->show_where) and $button->show_where!==NULL) {
                //error_log(print_r($presenter->master_data,TRUE));
                $show_flag=eval("return ".$button->show_where.";");
            } else {
                $show_flag=TRUE;
            }
            if ($show_flag) {
                $newbutton=$this->addGlobalButton($button->id.'_'.$button->action_link, $button->action_name)
                    ->setClass($button->action_icon)
                    ->setLink(function() use ($presenter,$bl,$button){
                        if (isset($button->import_id)) {
                            if (isset($presenter->master_id)) {
                                if (isset($bl)) {
                                    return $presenter->link($button->action_link,$button->import_id,$presenter->acl_action->action_name,$presenter->master_id,$bl);
                                } else {
                                    return $presenter->link($button->action_link,$button->import_id,$presenter->acl_action->action_name,$presenter->master_id);
                                } 
                            } else {
                                return $presenter->link($button->action_link,$button->import_id,$presenter->acl_action->action_name);
                            }
                        }
                        if (isset($presenter->master_id)) {
                            return $presenter->link($button->action_link,$presenter->master_id);
                        }
                        if (isset($button->action_parameters) and $button->action_parameters!='') {
                            return $presenter->link($button->action_link,explode(',',$button->action_parameters));
                        }
                        return $presenter->link($button->action_link);
                        })
                    ->setAjax(FALSE);
            }                
        }

        // Global Actions
        foreach ($this->globalactions as $action) {
            $newbutton=$this->addAction($action->action_link, $presenter->xtranslate($action->action_name))
                ->setCallback(function($row) use ($presenter,$bl,$action){ $presenter->tryMikoRun($action->action_link,$row);})
                ->setAjax(FALSE)
                ;
            if (isset($action->show_confirmation_text) and $action->show_confirmation_text!==NULL) {
                $newbutton->setConfirmationDialog(function($row) use ($action) {return eval("return \"".$action->show_confirmation_text."\";");});
            }
        }

        if (isset($this->acl_grid->upd_action)) {
            $action=$presenter->database->query(
               "SELECT * FROM acl.acl_action
               where module_name=? and presenter_name=? and action_name=?"
                 ,$presenter->acl_action->module_name
                 ,$presenter->acl_action->presenter_name
                 ,$this->acl_grid->upd_action
                 )->fetch();
            if ($action) {
                if (isset($action->sec_resource_code) and isset($action->sec_action_code)) {
                    if ($user->isAllowed($action->sec_resource_code,$action->sec_action_code)) {
                        $this->addButton($this->acl_grid->upd_action, "Modify")
                            ->setClass("fa fa-edit")
                            ->setLink(function($row) use ($presenter,$bl){return $presenter->link($this->acl_grid->upd_action, $row['id'],$bl);})
                            ->setShow(function($row) use ($presenter,$user,$action){return $user->isAllowed($action->sec_resource_code,$action->sec_action_code);})
                            ->setAjax(FALSE);
                    }
                }
            }
        }

        if (isset($this->acl_grid->del_action)) {
            $action=$presenter->database->query(
               "SELECT * FROM acl.acl_action
               where module_name=? and presenter_name=? and action_name=?"
                 ,$presenter->acl_action->module_name
                 ,$presenter->acl_action->presenter_name
                 ,$this->acl_grid->del_action
                 )->fetch();
            if ($action) {
                if (isset($action->sec_resource_code) and isset($action->sec_action_code)) {
                    if ($user->isAllowed($action->sec_resource_code,$action->sec_action_code)) {
                        $this->addButton($this->acl_grid->del_action, "Delete")
                            ->setClass("fa fa-remove")
                            ->setLink(function($row) use ($presenter,$bl){return $presenter->link($this->acl_grid->del_action, $row['id'],$bl);})
                            ->setShow(function($row) use ($presenter,$user,$action){return $user->isAllowed($action->sec_resource_code,$action->sec_action_code);})
                            ->setConfirmationDialog(function($row){return "Confirm the action for ID $row[id] ?";})
                            ->setAjax(FALSE);
                    }
                }
            }
        }

        if (isset($this->acl_grid->ins_action)) {
            $action=$presenter->database->query(
               "SELECT * FROM acl.acl_action
               where module_name=? and presenter_name=? and action_name=?"
                 ,$presenter->acl_action->module_name
                 ,$presenter->acl_action->presenter_name
                 ,$this->acl_grid->ins_action
                 )->fetch();
            if ($action) {
                if (isset($action->sec_resource_code) and isset($action->sec_action_code)) {
                    if ($user->isAllowed($action->sec_resource_code,$action->sec_action_code)) {
                          $this->addGlobalButton($this->acl_grid->ins_action, "Insert")
                               ->setLink(function() use ($presenter){return $presenter->link($this->acl_grid->ins_action);})
                               ->setClass('fa fa-plus')
                               ->setAjax(FALSE);
                    }
                }
            }
        }

        if (isset($this->acl_grid->grid_name)) {
            $sql = "select * from acl.acl_grid where parent_gridname = ?";
            $sub_grid = $presenter->database->query($sql,$this->acl_grid->grid_name)->fetch();

            if($sub_grid){
                $this->addSubGrid($sub_grid->grid_name, "Zobrazit subgrid")
                   ->setGrid(new AclGeneralGrid($sub_grid,$this->user,$presenter->database->table($sub_grid->source_view),$this->activeSubGridId))
                   ->settings(
                        function($grid){
                        }
                  )->setCellStyle("backcolumns-titleground-color:#f6f6f6; padding:5px; padding:5px 50px;");
            }

        }

        // Load form lov valeus Structure
        $sql = "select ff.lov_table_desc_col as desc_column
                from mikis.sa_form_fields ff, mikis.sa_forms f , acl.acl_form af
                where f.id = ff.form_id
                and af.form_name = f.formname
                and f.module_name=?
                and f.presenter_name=?
                and af.action_id = ?
                and ff.field_type ='L'
                ";
        $this->lovs = $presenter->database->query($sql,$presenter->acl_action->module_name, $presenter->acl_action->presenter_name, $presenter->acl_action->id)->fetch();

        //LOV
        if($this->acl_grid->grid_type=="LOV" && $this->lovs){
            $this->addColumn('creation_date', 'Select', '80px')
                ->setRenderer(function($row) use ($presenter) {return \Nette\Utils\Html::el('a',array('onClick'=>"select_set('".$row[$this->lovs->desc_column]."');"))->Title('Vyber id')->setText('')->setClass("fa fa-arrow-right")
                   ->setAttribute('x','x');})
                ;
        }

        //pro vypnutí stránkování a zobrazení všech záznamů
        if ($this->grid->paginate_flag=='N'){
            $this->paginate = FALSE;
        }    
        //zruší řazení všech sloupců
        $this->enableSorting = FALSE;
        if ($this->sortable_counter>0) {
            $this->enableSorting = TRUE;
        }
        //nastavení šířky celého gridu
        //$this->setWidth('1000px');
        //defaultní řazení
        //$this->setDefaultOrder("id, id");
        //počet záznamů v rozbalovacím seznamu
        //$this->setPerPageValues(array(10, 20, 50, 100));
    }



}
