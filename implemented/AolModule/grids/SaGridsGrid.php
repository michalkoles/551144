<?php

namespace App\AolModule\Presenters;

use Nette\Http\Session as NSession;
use Nette\Database\Context as NdbContext;
use Nette\Security\User as NUser;
use Nette\Forms\Form;
use NiftyGrid\Grid;
use NiftyGrid\NDataSource;

class saGridsGrid extends Grid
{

    protected $gridname;
    protected $user;
    protected $data;

    public function __construct($pgridname,$puser,$pdata)
    {
        parent::__construct();
        $this->gridname = $pgridname;
        $this->user = $puser;
        $this->data = $pdata;
    }

    protected function configure($presenter)
    {
        // set user
        $user = $this->user;

        // set source data
        $source = new \NiftyGrid\Datasource\NDataSource($this->data->select('*'));
        $this->setDataSource($source);

        // build colums
        $gridcols = $presenter->database->query("select gc.* from mikis.sa_grid_columns gc, mikis.sa_grids g where g.gridname = ? and gc.grid_id = g.id ",$this->gridname)->fetchAll();
        foreach ($gridcols as $rowdata) {
            if($rowdata->grid_visible_flag == 'Y'){
                  $fc = $this->addColumn($rowdata->db_column_name, $rowdata->column_description, $rowdata->column_size.$rowdata->column_size_uom);
                  if($rowdata->export_format_function == 'date'){
                      $fc->setRenderer(function($row){return ($row[$rowdata->db_column_name]==null)? '':date($rowdata->export_format_mask, strtotime($row[$rowdata->db_column_name]));});
                  }
                  if($rowdata->grid_filter_type == 'N'){
                      $fc->setNumericFilter();
                  }
                  else if ($rowdata->grid_filter_type == 'T'){
                      $fc->setTextFilter();
                  }
                  if($rowdata->grid_no_escape_flag == 'Y'){
                      $fc->setNoescape(TRUE);
                  }
            }

        }

        if ($this->gridname=='componentsGrid') {
              $this->addButton('componentGenerate', 'Generate')
                  ->setClass('componentGenerate fa fa-refresh')
                  ->setLink(function($row) use ($presenter){return $presenter->link('componentgenerate', $row['id']);})
                  ->setShow(function($row) use ($presenter,$user){return $user->isAllowed('sa_components','create');})
                  ->setAjax(FALSE);
        }

        $grids = $presenter->database->query("select g.* from mikis.sa_grids g where g.gridname = ? ",$this->gridname)->fetchAll();
        foreach ($grids as $rowdata) {
            if(isset($rowdata->ins_action)){
                $this->addGlobalButton($rowdata->ins_action, $rowdata->ins_action)
                  ->setLink(function() use ($presenter,$rowdata){return $presenter->link($rowdata->ins_action);})
                  ->setClass('fa fa-plus')
                  ->setAjax(FALSE);
            }
        }


        //pro vypnutí stránkování a zobrazení všech záznamů
        $this->paginate = FALSE;
        //zruší řazení všech sloupců
        $this->enableSorting = FALSE;
        //nastavení šířky celého gridu
        //$this->setWidth('1000px');
        //defaultní řazení
        //$this->setDefaultOrder('rok , desc');
        //počet záznamů v rozbalovacím seznamu
        //$this->setPerPageValues(array(10, 20, 50, 100));
    }


}
                