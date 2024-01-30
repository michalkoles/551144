<?php

namespace App\Presenters;

use Nette\Http\Session as NSession;
use Nette\Database\Context as NdbContext;
use Nette\Security\User as NUser;
use Nette\Forms\Form;
use NiftyGrid\Grid;
use NiftyGrid\NDataSource;

class AclLovGrid extends Grid
{

    protected $user;
    protected $data;
    protected $id_column;
    protected $desc_column;

    public function __construct($p_user,$p_data,$p_id_column,$p_desc_column)
    {
        parent::__construct();
        $this->user = $p_user;
        $this->data = $p_data;
        $this->id_column = $p_id_column;
        $this->desc_column = $p_desc_column;
    }

    protected function configure($presenter)
    {
        $user = $this->user;

        //Get source data
        $query = $this->data->select('*');

        $source = new \NiftyGrid\Datasource\NDataSource($query);
        //Set Source
        $this->setDataSource($source);
        // Load Grid Structure

        $this->addColumn($this->id_column,$this->id_column, '60px')
            ;
        $this->addColumn($this->desc_column,$this->desc_column, '200px')
             ->setTextFilter()
            ;


        $this->addColumn('creation_date', 'Select', '80px')
            ->setRenderer(function($row) use ($presenter) {return \Nette\Utils\Html::el('a',array('onClick'=>"select_select_click('".$row['id']."');"))->Title('Vyber id')->setText('')->setClass("fa fa-arrow-right")
               ->setAttribute('x','x');})
            ;


        //nastavení šířky celého gridu
        $this->setWidth('500px');
        //defaultní řazení
        //$this->setDefaultOrder("id, id");
        //počet záznamů v rozbalovacím seznamu
        //$this->setPerPageValues(array(10, 20, 50, 100));
    }



}
