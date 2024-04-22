<?php

namespace App\AdevelModule\Presenters;

use Nette,
	App\Model;
use Nette\Forms\Form;
use RadekDostal\NetteComponents\DateTimePicker;
use RadekDostal\NetteComponents\TbDateTimePicker;
use Tracy\Debugger; 


//require __DIR__ . '/../kalk_func.php'; 

class ComponentsPresenter extends \App\Presenters\BasePresenter
{
    use ComponentsTrait; //Trait

    public $request_id;
    public $requestdos_id;
    
    private function actionSecurity($p_action)
    {
        //error_log(date("Y-m-d H:i:s:u").' '.$p_action);
        
        if (!$this->user->isLoggedIn()) {
          $this->flashMessage('Byli jste odhlášeni.', 'error');
          $this->redirect(':Homepage:');
        }
        
        if ($p_action=='actionDefault') {
            if (!$this->user->isAllowed('sa_requests','view')) {
             $this->flashMessage('Nemáte příslušná oprávnění.', 'error');
             $this->redirect(':Homepage:');
            }          
        } 
        elseif ($p_action=='actionComponentGenerate') {
            if (!$this->user->isAllowed('sa_requests','create')) {
             $this->flashMessage('Nemáte příslušná oprávnění.', 'error');
             $this->redirect('default');
            }
        } 
        else {
             $this->flashMessage('Nemáte příslušná oprávnění.', 'error');
             $this->redirect('default');
        }    
    }
    
    public function actionDefault()
    {
        $this->redirect('componentlst');
    }

    public function actionComponentGenerate($id)
    {
        $this->actionSecurity('actionComponentGenerate');
        
        //error_log("DDDD");
        
        $comps = $this->database->query("select * from mikis.sa_components where id = ?",$id)->fetchAll();
        
        
        
        foreach ($comps as $comp) {
            // set default path
            $defpath = TEMP_DIR.'generated/'.$comp->module_name.'/'.$comp->presenter_name;
            if (!is_dir($defpath)) {
                mkdir($defpath, 0777, true);
            }
            if (!is_dir($defpath.'/templates')) {
                mkdir($defpath.'/templates', 0777, true);
            }
            if (!is_dir($defpath.'/grids')) {
                mkdir($defpath.'/grids', 0777, true);
            }
        
            // load grid
            if (isset($comp->lst_grid_name)){
                $grid = $this->database->query("select * from mikis.sa_grids where gridname = ?",$comp->lst_grid_name)->fetch();
            }
        
            // Create Trait
            $traitfile = "<?php\n";
            $traitfile .= "//This file is auto-generated.\n";
            $traitfile .= "\n";
            $traitfile .= "namespace"." App\\".$comp->module_name."Module\Presenters;\n";            
            $traitfile .= "\n";
            $traitfile .= "use Nette\Forms\Form;\n";
            $traitfile .= "\n";
            
            $traitfile .= "trait"." ".$comp->component_name."Trait {\n";            
            $traitfile .= "\n";
            if (isset($comp->lst_grid_name)){
                $traitfile .= "public function createComponent".\Nette\Utils\Strings::firstUpper($comp->lst_grid_name)."() {\n";
                $traitfile .= "    return new $comp->lst_grid_name('$comp->lst_grid_name',\$this->user,\$this->database->table('$grid->source_view'));\n";
                $traitfile .= "}\n";
            }
            if (isset($comp->ins_form_name)){
                $traitfile .= "public function createComponent".\Nette\Utils\Strings::firstUpper($comp->ins_form_name)."() {\n";
                $traitfile .= "    \$form = new \Nette\Application\UI\Form;\n";
                $traitfile .= "    \$renderer = \$form->getRenderer();\n";
                $traitfile .= "    \$renderer->wrappers['label']['container'] = 'th class=\"thlabel\"';\n";
                $traitfile .= "    \$sql = \"select ff.* from mikis.sa_form_fields ff, mikis.sa_forms f where f.formname = ? and f.id = ff.form_id\"; \n";
                $traitfile .= "    \$frmcols = \$this->database->query(\$sql,'$comp->ins_form_name')->fetchAll();\n";
                $traitfile .= "
                    \$submit = 0;
                    \$last_group = '';
                    foreach (\$frmcols as \$row) {
                        if(\$row->form_group != \$last_group){
                            \$form->addGroup(\$row->form_group);
                        }
                        \$last_group = \$row->form_group;
                        if(\$row->field_type == 'T'){
                            \$ff = \$form->addText(\$row->field_name, \$row->field_description,\$row->field_size);
                        }
                        else if(\$row->field_type == 'S'){
                            \$f_sql = \"select * from \".\$row->lov_table;
                            if (isset(\$row->lov_table_where)) {
                                \$f_sql .= \" where \".\$row->lov_table_where;
                            }
                            \$f_data = \$this->database->query(\$f_sql)->fetchPairs(\$row->lov_table_id_col,\$row->lov_table_desc_col);
                            \$ff = \$form->addSelect(\$row->field_name, \$row->field_description,\$f_data);
                        }
                        else if(\$row->field_type == 'B'){
                            \$submit = \$submit  + 1;
                            \$ff = \$form->addSubmit(\$row->field_name, \$row->field_description);
                            if(\$row->field_name == 'back'){
                                \$ff->setValidationScope(false);
                            }
                        }
                        if(\$row->field_required == 'Y'){
                            \$ff->setRequiredCZ();
                        }
                        if(\$row->field_integer == 'Y'){
                            \$ff->addCondition(Form::FILLED)->addRule(Form::XXMKINTEGER, 'Musí být celé číslo');
                        }
                        if(\$row->field_integer == 'F'){
                            \$ff->addCondition(Form::FILLED)->addRule(Form::XXMKFLOAT,'Hodnota musí být číslo!');   
                        }
                    }
                    
                    if (\$submit>0) {
                        \$form->onSubmit[] = [\$this,'".$comp->ins_form_name."Succeeded'];
                    }
                ";
                
                $traitfile .= "    return \$form;\n";
                $traitfile .= "}\n";
                $traitfile .= "\n";
                
                $traitfile .= "public function ".$comp->ins_form_name.'Succeeded'."(\$form) {\n";
                $traitfile .= "    \$this->actionSecurity('$comp->ins_action');\n";
                $traitfile .= "    \$values = \$form->getValues();\n";

                $traitfile .= "    \$sql = \"select ff.* from mikis.sa_form_fields ff, mikis.sa_forms f where f.formname = ? and f.id = ff.form_id and ff.field_type='B' \"; \n";
                $traitfile .= "    \$frmcols = \$this->database->query(\$sql,'$comp->ins_form_name')->fetchAll();\n";
                $traitfile .= "    \$sql = \"select f.* from mikis.sa_forms f where f.formname = ? \"; \n";
                $traitfile .= "    \$frm = \$this->database->query(\$sql,'$comp->ins_form_name')->fetch();\n";
                $traitfile .= "
                    foreach (\$frmcols as \$frmcol) {
                        if (isset(\$form[\$frmcol->field_name]) && \$form[\$frmcol->field_name]->isSubmittedBy() && \$frmcol->field_name=='save') {
                            try {
                                \$insarr = array();
                                foreach (\$values as \$key=>\$value) {
                                    \$insarr[\$key]=(\$value=='')?null:\$value;
                                }
                                \$insarr['creation_date']=date('Y-m-d H:i:s');
                                \$insarr['created_by']=\$this->user->id;
                                \$insarr['last_update_date']=date('Y-m-d H:i:s');
                                \$insarr['last_updated_by']=\$this->user->id;
                                \$datarow=\$this->database->table(\$frm->source_table)->insert(\$insarr);
                                \$this->flashMessage('Success.', 'success');
                             } catch(\Exception \$e) {
                                 error_log(\$e);
                                 \$this->flashMessage('Error: '.substr(\$e,0,100), 'error');
                             }
                            \$this->redirect(\$frmcol->ret_action);                
                        }
                        if (isset(\$form[\$frmcol->field_name]) && \$form[\$frmcol->field_name]->isSubmittedBy() && \$frmcol->field_name=='back') {
                            \$this->redirect(\$frmcol->ret_action);                
                        }
                    }
                ";
                $traitfile .= "}\n";
                $traitfile .= "\n";
                
            }
            if (isset($comp->upd_form_name)){
                $traitfile .= "public function createComponent".\Nette\Utils\Strings::firstUpper($comp->upd_form_name)."() {\n";
                $traitfile .= "    return new $comp->upd_form_name;\n";
                $traitfile .= "}\n";
            }    
            $traitfile .= "}\n";
            $path = $defpath.'/'.$comp->component_name.'.php';
            $myfile = fopen($path, "w");
            fwrite($myfile,$traitfile);
            fclose($myfile);
            
            // Create Menu Template
            if (isset($comp->lst_action)){
                $menufile = "";
                $menufile .= "<div id=\"header2\" class=\"miko_hideable w3-hide-small\">\n";
                $menufile .= "    <div id=\"submenu\" n:if=\"\$presenter->getAction()=='$comp->lst_action'\">\n";
                $menufile .= "        <a id=\"donflst\" class=\"selected\" n:if=\"\$user->isAllowed('sa_config','view')\" n:href=\"$comp->lst_action\">$comp->lst_action</a>\n";
                $menufile .= "        </div>\n";
    
                $menufile .= "</div>\n";
                $path = $path = $defpath.'/templates/'.'menu.latte';
                $myfile = fopen($path, "w");
                fwrite($myfile,$menufile);
                fclose($myfile);
            }
            
            // Create List Template
            if (isset($comp->lst_grid_name) && isset($comp->lst_action) ){
                $lstfile = "";
                $lstfile .= "{block menu}{include menu.latte}{/block menu}\n";
                $lstfile .= "{block content1}\n";
                $lstfile .= "<div id=\"dbody\">\n";
                $lstfile .= "\n";
                $lstfile .= "{control $comp->lst_grid_name}\n";
                $lstfile .= "\n";
                $lstfile .= "</div>\n";
                $path = $defpath.'/templates/'.$comp->lst_action.'.latte';
                $myfile = fopen($path, "w");
                fwrite($myfile,$lstfile);
                fclose($myfile);
            }
            
            // Create Grid
            if (isset($comp->lst_grid_name)){
                $gridfile =
"<?php

namespace App\\".$comp->module_name."Module\Presenters;

use Nette\Http\Session as NSession;
use Nette\Database\Context as NdbContext;
use Nette\Security\User as NUser;
use Nette\Forms\Form;
use NiftyGrid\Grid;
use NiftyGrid\NDataSource;

class $comp->lst_grid_name extends Grid
{

    protected \$gridname;
    protected \$user;
    protected \$data;

    public function __construct(\$pgridname,\$puser,\$pdata)
    {
        parent::__construct();
        \$this->gridname = \$pgridname;
        \$this->user = \$puser;
        \$this->data = \$pdata;
    }

    protected function configure(\$presenter)
    {
        // set user
        \$user = \$this->user;

        // set source data
        \$source = new \NiftyGrid\Datasource\NDataSource(\$this->data->select('*'));
        \$this->setDataSource(\$source);

        // build colums
        \$gridcols = \$presenter->database->query(\"select gc.* from mikis.sa_grid_columns gc, mikis.sa_grids g where g.gridname = ? and gc.grid_id = g.id \",\$this->gridname)->fetchAll();
        foreach (\$gridcols as \$rowdata) {
            if(\$rowdata->grid_visible_flag == 'Y'){
                  \$fc = \$this->addColumn(\$rowdata->db_column_name, \$rowdata->column_description, \$rowdata->column_size.\$rowdata->column_size_uom);
                  if(\$rowdata->export_format_function == 'date'){
                      \$fc->setRenderer(function(\$row){return (\$row[\$rowdata->db_column_name]==null)? '':date(\$rowdata->export_format_mask, strtotime(\$row[\$rowdata->db_column_name]));});
                  }
                  if(\$rowdata->grid_filter_type == 'N'){
                      \$fc->setNumericFilter();
                  }
                  else if (\$rowdata->grid_filter_type == 'T'){
                      \$fc->setTextFilter();
                  }
                  if(\$rowdata->grid_no_escape_flag == 'Y'){
                      \$fc->setNoescape(TRUE);
                  }
            }

        }

        if (\$this->gridname=='componentsGrid') {
              \$this->addButton('componentGenerate', 'Generate')
                  ->setClass('componentGenerate fa fa-refresh')
                  ->setLink(function(\$row) use (\$presenter){return \$presenter->link('componentgenerate', \$row['id']);})
                  ->setShow(function(\$row) use (\$presenter,\$user){return \$user->isAllowed('sa_components','create');})
                  ->setAjax(FALSE);
        }

        \$grids = \$presenter->database->query(\"select g.* from mikis.sa_grids g where g.gridname = ? \",\$this->gridname)->fetchAll();
        foreach (\$grids as \$rowdata) {
            if(isset(\$rowdata->ins_action)){
                \$this->addGlobalButton(\$rowdata->ins_action, \$rowdata->ins_action)
                  ->setLink(function() use (\$presenter,\$rowdata){return \$presenter->link(\$rowdata->ins_action);})
                  ->setClass('fa fa-plus')
                  ->setAjax(FALSE);
            }
        }


        //pro vypnutí stránkování a zobrazení všech záznamů
        \$this->paginate = FALSE;
        //zruší řazení všech sloupců
        \$this->enableSorting = FALSE;
        //nastavení šířky celého gridu
        //\$this->setWidth('1000px');
        //defaultní řazení
        //\$this->setDefaultOrder('rok , desc');
        //počet záznamů v rozbalovacím seznamu
        //\$this->setPerPageValues(array(10, 20, 50, 100));
    }


}
                ";
                $path = $defpath.'/grids/'.\Nette\Utils\Strings::firstUpper($comp->lst_grid_name).'.php';
                $myfile = fopen($path, "w");
                fwrite($myfile,$gridfile);
                fclose($myfile);
            }
            
            // Create Insert Template
            if (isset($comp->ins_action) ){
                $insfile = "";
                $insfile .= "{block menu}{include menu.latte}{/block menu}\n";
                $insfile .= "{block content1}\n";
                $insfile .= "<div id=\"dbody\">\n";
                $insfile .= "\n";
                $insfile .= "{control $comp->ins_form_name}\n";
                $insfile .= "\n";
                $insfile .= "</div>\n";
                $path = $defpath.'/templates/'.$comp->ins_action.'.latte';
                $myfile = fopen($path, "w");
                fwrite($myfile,$insfile);
                fclose($myfile);
            }


            
        }

        $this->redirect('componentlst');
    }


}

