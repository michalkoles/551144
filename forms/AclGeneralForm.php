<?php

namespace App\Presenters;

use Nette\Http\Session as NSession;
use Nette\Database\Context as NdbContext;
use Nette\Security\User as NUser;
use Nette\Forms\Form;
use NiftyGrid\Grid;
use NiftyGrid\NDataSource;
use Nette\Forms\Rendering\MikoAclFormRenderer;

class AclGeneralForm extends \Nette\Application\UI\Form
{

    public $acl_form;
    protected $user;
    protected $fields;
    protected $form;
    protected $data;
    protected $data_id;
    protected $dependencies;
    protected $form_actions;

    public function __construct($p_form,$p_user,$presenter)
    {
        parent::__construct();
        $this->acl_form = $p_form;
        $this->user = $p_user;
        $this->data_id = $presenter->acl_form_data_id;
        $this->setTranslator($presenter->translator);
        $this->configure($presenter);
    }

    protected function configure($presenter)
    {

        //Load Form Layout
        $sql = "select column_count from mikis.sa_forms f
                where f.module_name=?
                and f.presenter_name=?
                and f.formname = ?
                ";
        $this->form = $presenter->database->query($sql,$presenter->acl_action->module_name, $presenter->acl_action->presenter_name, $this->acl_form->form_name)->fetch();

        if($this->form->column_count == 2){
          $customRenderer = new MikoAclFormRenderer();
          $this->setRenderer(new MikoAclFormRenderer);
        }
        $renderer = $this->getRenderer();
        $renderer->wrappers['label']['container'] = 'th class="thlabel"';

        // Load Form Structure
        $sql = "select ff.*
                from mikis.sa_form_fields ff, mikis.sa_forms f
                where f.id = ff.form_id
                and f.module_name=?
                and f.presenter_name=?
                and f.formname = ?
                order by ff.field_order, f.id, ff.id
                ";
        $this->fields = $presenter->database->query($sql,$presenter->acl_action->module_name, $presenter->acl_action->presenter_name, $this->acl_form->form_name)->fetchAll();

        //error_log('miko3:'.$this->data_id);
        if ($this->acl_form->form_type=='Upd' or $this->acl_form->form_type=='Upd/Ins' or $this->acl_form->form_type=='Ins/Sel' ) {
            if (isset($this->acl_form->source_view)) {
                $this->data = $presenter->database->query("select * from ".$this->acl_form->source_view." where id=?",$this->data_id)->fetch();
            } else {
                $this->data = $presenter->database->query("select * from ".$this->acl_form->target_table." where id=?",$this->data_id)->fetch();
            }
        }
        if ($this->data) {
            $presenter->master_data=$this->data;
        }

        // Load Dependencies
        $sql = "select d.*
                from mikis.acl_form_field_dependencies_v d, mikis.sa_forms f
                where f.id = d.form_id
                and f.module_name=?
                and f.presenter_name=?
                and f.formname = ?
                order by d.id
                ";
        $this->dependencies = $presenter->database->query($sql,$presenter->acl_action->module_name, $presenter->acl_action->presenter_name, $this->acl_form->form_name)->fetchAll();
        if(isset($this->dependencies)){
            $presenter->template->dependencies=array();
            $presenter->template->dependency_names=array();
            $presenter->template->dependency_changes=array();
            foreach ($this->dependencies as $dependency) {
                if($dependency->dependency_type == "lov"){
                    $presenter->template->dependencies[]=$dependency;
                    $presenter->template->dependency_names[]=$dependency->dependent_field;
                    if (!in_array($dependency->dependent_on_field,$presenter->template->dependency_changes)) {
                        $presenter->template->dependency_changes[]=$dependency->dependent_on_field;
                    }
                }
            }
        }

        $form_group='';
        foreach ($this->fields as $field) {
            if ($form_group!=$field->form_group) {
                $form_group=$field->form_group;
                $this->addGroup($field->form_group);
            }
            if ($field->visible_flag=='N') {
                $ff=$this->addHidden($field->field_name, $field->field_description,$field->field_size);
            } else {
                if ($field->field_type == 'L') {
                    $presenter->template->lovfields=array();
                    $presenter->template->lovfields[]=$field->field_name;
                    $presenter->template->lov_table_desc_col=$field->field_description;

                    /*$presenter->acl_form_lov["lov_table"]=$field->lov_table;
                    $presenter->acl_form_lov["lov_table_id_col"]=$field->lov_table_id_col;
                    $presenter->acl_form_lov["lov_table_desc_col"]=$field->lov_table_desc_col;*/

                    $ff=$this->addText($field->field_name, $field->field_description,$field->field_size);
                }
                else if($field->field_type == 'S' && isset($field->lov_table) && isset($field->lov_table_id_col) && isset($field->lov_table_desc_col)){
                    $f_sqllov = "select ".$field->lov_table_id_col.",".$field->lov_table_desc_col." from ".$field->lov_table;
                    if ($field->lov_table_desc_col===$field->lov_table_id_col) {
                        $f_sqllov = "select ".$field->lov_table_id_col." from ".$field->lov_table;
                    } 
                    if ($field->lov_table_where!==NULL && $field->lov_table_where!='') {
                        $f_sqllov = $f_sqllov ." where ".str_replace('$master_id',$presenter->master_id,$field->lov_table_where);
                        $matches=array();
                        preg_match_all('/\$data\[\'[a-zA-Z_]+\'\]/',$f_sqllov,$matches);
                        foreach ($matches[0] as $singlematch) {
                            $matchname=substr($singlematch,7,strlen($singlematch)-9);
                            $matchrepl=$this->data[$matchname];
                            $f_sqllov = str_replace($singlematch,$matchrepl,$f_sqllov);
                        }
                    }
                    if ($field->lov_order_by!==NULL && $field->lov_order_by!='') {
                        $f_sqllov = $f_sqllov ." order by ".$field->lov_order_by;
                    }
                    if (isset($field->lov_db) and $field->lov_db=='O') {
                        $f_lov = $presenter->oracledb->query($f_sqllov)->fetchPairs($field->lov_table_id_col,$field->lov_table_desc_col);
                    } else {
                        $f_lov = $presenter->getDatabase()->query($f_sqllov)->fetchPairs($field->lov_table_id_col,$field->lov_table_desc_col);
                    }
                    $ff=$this->addSelect($field->field_name, $field->field_description,$f_lov);
                    if (isset($field->lov_prompt)) {
                        if ($field->lov_prompt!=='NULL') {
                            $ff=$ff->setPrompt($field->lov_prompt);
                        }  
                    } else {
                        $ff=$ff->setPrompt('Vyberte '.$field->field_name);
                    }
                    //error_log($this->acl_form->target_table);
                } elseif ($field->field_type == 'DT') {
                    $ff=$this->addDateTimePicker($field->field_name, $field->field_description, $field->field_size, $field->field_size)->setAttribute('autocomplete', 'off');
                } elseif ($field->field_type == 'D') {
                    $ff=$this->addDatePicker($field->field_name, $field->field_description, $field->field_size, $field->field_size)->setAttribute('autocomplete', 'off');
                } elseif ($field->field_type == 'A') {
                    $ff=$this->addTextArea($field->field_name, $field->field_description, $field->field_size, $field->field_textarea_columns)->setAttribute('autocomplete', 'off');
                } else if($field->field_type == 'MS' && isset($field->lov_table) && isset($field->lov_table_id_col) && isset($field->lov_table_desc_col)){
                
                    $f_sqllov = "select ".$field->lov_table_id_col.",".$field->lov_table_desc_col." from ".$field->lov_table;
                    if ($field->lov_table_where!==NULL && $field->lov_table_where!='') {
                        $f_sqllov = $f_sqllov ." where ".str_replace('$master_id',$presenter->master_id,$field->lov_table_where);
                        $matches=array();
                        preg_match_all('/\$data\[\'[a-zA-Z_]+\'\]/',$f_sqllov,$matches);
                        foreach ($matches[0] as $singlematch) {
                            $matchname=substr($singlematch,7,strlen($singlematch)-9);
                            $matchrepl=$this->data[$matchname];
                            $f_sqllov = str_replace($singlematch,$matchrepl,$f_sqllov);
                        }
                    }
                    if ($field->lov_order_by!==NULL && $field->lov_order_by!='') {
                        $f_sqllov = $f_sqllov ." order by ".$field->lov_order_by;
                    }
                    $f_lov = $presenter->database->query($f_sqllov)->fetchPairs($field->lov_table_id_col,$field->lov_table_desc_col);
                    $ff=$this->addMultiSelect($field->field_name, $field->field_description, $f_lov, $field->field_textarea_columns)->setAttribute('autocomplete', 'off');
                } else {
                    $ff=$this->addText($field->field_name, $field->field_description,$field->field_size);
                }
                if ($field->field_type=='N') {
                    //$ff=$ff->setAttribute('type','number');
                    $ff=$ff->setType('number');
                    $ff=$ff->setAttribute('step','any');
                }
                if ($field->field_required=='Y' && (isset($field->field_readonly)?$field->field_readonly:'N')=='N') {
                    $ff=$ff->setRequiredEn();
                }
                if ($field->field_readonly=='Y') {
                    $ff=$ff->setAttribute('readonly','readonly');
                }
                if ($field->field_autofocus=='Y') {
                    $ff=$ff->setAttribute('autofocus','true');
                }
                if ($field->visible_flag=='Y' && $field->default_predefined_value=='arg' and $presenter->mikos_args!==NULL and !empty($presenter->mikos_args)) {
                    if (isset($presenter->mikos_args[$field->default_value])) {
                        $ff=$ff->setDefaultValue($presenter->mikos_args[$field->default_value]);
                    }
                }
            }
            if ($this->acl_form->form_type=='Upd' or $this->acl_form->form_type=='Upd/Ins' or $this->acl_form->form_type=='Ins/Sel' ) {
                if ($this->data) {
                    //error_log(print_r($this->data,true));
                    if ($field->field_type!=='S' && $field->default_type=='L' && $field->lov_table!==NULL && isset($field->lov_table_id_col) && isset($field->lov_table_desc_col)) {
                        /*
                        $f_sqllov = "select ".$field->lov_table_id_col.",".$field->lov_table_desc_col." from ".$field->lov_table;
                        if ($field->lov_table_where!==NULL && $field->lov_table_where!='') {
                           $f_sqllov = $f_sqllov ." where ".$field->lov_table_where. ;
                        } else {
                           $f_sqllov = $f_sqllov ." where ";
                        }
                        $field->lov_table_id_col,$field->lov_table_desc_col
                        $f_data = $presenter->database->query($f_sqllov)->fetch();
                        $ff=$this->addSelect($field->field_name, $field->field_description,$f_lov);
                        $ff=$ff->setPrompt('Vyberte '.$field->field_name);
                        */
                        $ff=$ff->setDefaultValue($this->data[$field->field_name]);
                    } elseif ($field->field_type=='L') {
                        $ff=$ff->setDefaultValue($this->data[$field->lov_table_desc_col]);
                        //error_log('miko2a');
                        //error_log('field->lov_table_desc_col'.$field->lov_table_desc_col);
                        //error_log(print_r($this->data,true));
                    } else {
                        if (isset($this->data[$field->db_column_name])) {
                            //error_log(print_r($this->data[$field->db_column_name],TRUE));
                            if ($field->field_type == 'TT') {
                                $ff=$ff->setDefaultValue($this->data[$field->db_column_name]->format('%H:%I'));
                                //$ff=$ff->setDefaultValue($this->data[$field->db_column_name]);
                            } else {
                                $ff=$ff->setDefaultValue($this->data[$field->db_column_name]);
                            }
                        } else {
                            if (isset($field->default_value)) {
                                $ff=$ff->setDefaultValue($field->default_value);
                            }
                        }
                    }
                }
            }
        }

        // Load Validations
        // nette 4.0
        //$f_rule = array("Required"=>"Required","Filled"=>"Filled","Blank"=>"Blank","Equal"=>"Equal","NotEqual"=>"NotEqual","IsIn"=>"IsIn","IsNotIn"=>"IsNotIn","Valid"=>"Valid","MinLength"=>"MinLength","MaxLength"=>"MaxLength","Length"=>"Length","Email"=>"Email","URL"=>"URL","Pattern"=>"Pattern","PatternInsensitive"=>"PatternInsensitive","Integer"=>"Integer","Numeric"=>"Numeric","Float"=>"Float","Min"=>"Min","Max"=>"Max","Range"=>"Range","Length"=>"Length","MaxFileSize"=>"MaxFileSize","MimeType"=>"MimeType","Image"=>"Image");
        // nette 2.x a 3.x
        $f_rule = array("Required"=>$this::REQUIRED
                       ,"Filled"=>$this::FILLED
                       ,"Blank"=>$this::BLANK
                       ,"Equal"=>$this::EQUAL
                       ,"NotEqual"=>$this::NOT_EQUAL
                       ,"IsIn"=>$this::IS_IN
                       ,"IsNotIn"=>$this::IS_NOT_IN
                       ,"Valid"=>$this::VALID
                       ,"MinLength"=>$this::MIN_LENGTH
                       ,"MaxLength"=>$this::MAX_LENGTH
                       ,"Length"=>$this::LENGTH
                       ,"Email"=>$this::EMAIL
                       ,"URL"=>$this::URL
                       ,"Pattern"=>$this::PATTERN
                       ,"PatternInsensitive"=>$this::PATTERN_ICASE
                       ,"Integer"=>$this::INTEGER
                       ,"Numeric"=>$this::NUMERIC
                       ,"Float"=>$this::FLOAT
                       ,"Min"=>$this::MIN
                       ,"Max"=>$this::MAX
                       ,"Range"=>$this::RANGE
                       ,"MaxFileSize"=>$this::MAX_FILE_SIZE
                       ,"MimeType"=>$this::MIME_TYPE
                       ,"Image"=>$this::IMAGE
                       );
        
        $sql = "select ff.field_name, v.*
                from mikis.sa_form_fields ff, mikis.sa_forms f
                , acl.acl_form_validations v
                where f.id = ff.form_id
                and f.module_name=?
                and f.presenter_name=?
                and f.formname = ?
                and v.field_id=ff.id
                order by ff.field_order, f.id, ff.id, v.order_num, v.id 
                ";
        $validations = $presenter->database->query($sql,$presenter->acl_action->module_name, $presenter->acl_action->presenter_name, $this->acl_form->form_name)->fetchAll();
        foreach ($validations as $validation) {
            if ($validation->condition_type=='addConditionOn') {
                //error_log('validation->field_name: '.$validation->field_name);
                //error_log('validation->condition_field: '.$validation->condition_field);
                //error_log('validation->rule_type: '.$validation->rule_type);
                //error_log('validation->validation_params: '.$validation->validation_params);
                $valds[$validation->id]=$this[$validation->field_name]->addConditionOn($this[$validation->condition_field],$f_rule[$validation->rule_type],$validation->validation_params);
            }
            if ($validation->condition_type===NULL) {
                if ($validation->parent_id===NULL) {
                    $valpars = array();
                    $valpars = explode(',',$validation->validation_params);
                    $valds[$validation->id]=$this[$validation->field_name]->addRule($f_rule[$validation->rule_type],$validation->validation_message,$valpars);
                } else {
                    $valpars = array();
                    $valpars = explode(',',$validation->validation_params);
                    $valds[$validation->id]=$valds[$validation->parent_id]->addRule($f_rule[$validation->rule_type],$validation->validation_message,$valpars);
                }
            }
        }            


        // Load form actions
        $sql = "select fa.*
                from acl.acl_form_action fa, mikis.sa_forms f, acl.acl_form af
                where af.id = fa.form_id
                and f.module_name=?
                and f.presenter_name=?
                and f.formname = ?
                and af.form_name = f.formname
                order by fa.id
                ";
        $this->form_actions = $presenter->database->query($sql,$presenter->acl_action->module_name,$presenter->acl_action->presenter_name,$this->acl_form->form_name)->fetchAll();

        if (isset($this->acl_form->save_action) or isset($this->acl_form->back_action) ){
            $this->addGroup('Finish');
            if (isset($this->acl_form->form_submit)) {
                $this->onSubmit[] = [$presenter,$this->acl_form->form_submit];
            }
            if (isset($this->acl_form->form_success)) {
                $this->onSuccess[] = [$presenter,$this->acl_form->form_success];
            } else {
                $this->onSuccess[] = [$presenter,'aclForm01FormSuccess'];
            }
            if (isset($this->acl_form->form_validate)) {
                $this->onValidate[] = [$presenter,$this->acl_form->form_validate];
            }
            if (isset($this->acl_form->save_action)) {
                if (!isset($this->acl_form->save_action_where) || (isset($this->acl_form->save_action_where) && eval('return '.$this->acl_form->save_action_where.';'))){
                    if (isset($this->acl_form->save_action_name)) {
                        $this->addSubmit('save',$this->acl_form->save_action_name);
                    } else {
                        $this->addSubmit('save','Save');
                    }
                }
            }
            if(isset($this->form_actions)){
                foreach ($this->form_actions as $action) {
                    //error_log("in_actions");
                    if (!isset($action->form_action_where) || (isset($action->form_action_where) && eval('return '.$action->form_action_where.';'))){
                        $ff = $this->addSubmit($action->form_action_code,$action->form_action_name);
                        if(isset($action->form_validate_flag) && $action->form_validate_flag == 'N'){
                            $ff = $ff->setValidationScope(false);
                        }
                    }
                }
            }
            if (isset($this->acl_form->back_action)) {
                if (!isset($this->acl_form->back_action_where) || (isset($this->acl_form->back_action_where) && eval('return '.$this->acl_form->back_action_where.';'))){
                    if (isset($this->acl_form->back_action_name)) {
                        $this->addSubmit('back',$this->acl_form->back_action_name)->setValidationScope(false);
                    } else {
                        $this->addSubmit('back','Back')->setValidationScope(false);
                    }
                }
            }
        }

        /*
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
                            ->setLink(function($row) use ($presenter){return $presenter->link($this->acl_grid->upd_action, $row['id']);})
                            ->setShow(function($row) use ($presenter,$user){return $user->isAllowed($action->sec_resource_code,$action->sec_action_code);})
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
                        $this->addButton($this->acl_grid->del_action, "Modify")
                            ->setClass("fa fa-edit")
                            ->setLink(function($row) use ($presenter){return $presenter->link($this->acl_grid->del_action, $row['id']);})
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
        */


    }



}
