<?php

namespace App\Presenters;

use Nette,
	App\Model;

use Tracy;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    /** @persistent */
    public $lang;

    public $log_flag='N';

    public $acl_grids;
    public $acl_forms;
    public $acl_form_data_id;
    public $acl_form_action_link;
    public $acl_actions;
    public $acl_form_lov=array();
    public $acl_lov_grids='MIKo';
    public $acl_import_file_id;
    public $no_acl_actions;

    public $url_query;

	  public $translator;

    public $refresh_time;

    public $title;
    public $company;
    public $miko_logo_css_class;
    public $mikos_user_id;
    public $presenter_action;
    public $presenter_name;
    public $presenter_module;
    public $presenter_menu;
    public $presenter_menu_items;
    public $menu_actions;
    public $box_actions;
    public $fav_actions;
    public $presenter_controls;

    public $customer_code;

    public $css_font_awesome_version;
    public $css_customer_file_flag;

    public $css_custom_files;

    protected $smart_link_code;
    protected $smart_links_enabled_flag;
    protected $smart_link_username;

    protected $audit_id;

    public $master_id;
    public $master_data;

    /** @var Nette\Database\Context */
    public $database;

    public $backlink;

    public $profiles;

    public $mikos_args;


    public function __construct(Nette\Database\Context $database)
    {
       parent::__construct();

       Nette\Forms\Controls\BaseControl::extensionMethod('setRequiredCz', function (Nette\Forms\Controls\BaseControl $_this) {
            return $_this->setRequired("Pole $_this->caption musí být vyplněno !");
       });
       Nette\Forms\Controls\BaseControl::extensionMethod('setRequiredEn', function (Nette\Forms\Controls\BaseControl $_this) {
            return $_this->setRequired("The field $_this->caption must be filled !");
       });
       Nette\Forms\Controls\BaseControl::extensionMethod('setRequiredXx', function (Nette\Forms\Controls\BaseControl $_this) {
            if ($this->lang=='cs') {
                return $_this->setRequired("Pole $_this->caption musí být vyplněno !");
            }
            return $_this->setRequired("The field $_this->caption must be filled !");
       });

       //$this->lang = 'cs';

       $this->database = $database;

       $this->database->query('call mikis.check_mikis_privs();');
       // COMPANY
       $conf_company = $this->database->table('CONFIG')->select('value')->where('module = ?','MIKIS')->where('code = ?','COMPANY')->fetch();
       $this->company = $conf_company['value'];
       // CUSTOMER_CODE
       $this->customer_code = null;
       $conf_sustomer_code = $this->database->table('CONFIG')->select('value')->where('module','MIKIS')->where('code','CUSTOMER_CODE')->fetch();
       if ($conf_sustomer_code) {
           $this->customer_code = $conf_sustomer_code['value'];
       }

       $conf_miko_logo_css_class = $this->database->table('CONFIG')->select('value')->where('module','MIKIS')->where('code','CSS_MIKO_LOGO_CLASS')->fetch();
       if ($conf_miko_logo_css_class) {
           $this->miko_logo_css_class = $conf_miko_logo_css_class['value'];
       }

       $cssver = $this->database->table('CONFIG')->select('value')->where('module','MIKIS')->where('code','CSS_FONT_AWESOME_VERSION')->fetch();
       if ($cssver) {
          $this->css_font_awesome_version = $cssver->value;
       }
       if (!isset($this->css_font_awesome_version) or $this->css_font_awesome_version=='') {
           $this->css_font_awesome_version='4';
       }
       $this->title = "MiKoš ({$this->company})";
       $csscus = $this->database->table('CONFIG')->select('value')->where('module','MIKIS')->where('code','CSS_CUSTOMER_FILE_FLAG')->fetch();
       if ($csscus) {
           $this->css_customer_file_flag = $csscus->value;
       }
       if (!isset($this->css_customer_file_flag) or $this->css_customer_file_flag=='') {$this->css_customer_file_flag='N';}
       $this->css_custom_files = $this->database->table('mikis.sa_styles_v')->order('seeded_flag DESC')->order('order_number')->fetchAll();

    }

    public function xtranslate($p_message,$p_count=null)
    {
       return $this->translator->translate($p_message,$p_count);
    }

    public function startup()
    {
      parent::startup();

      /*
      error_log('startup getParameters');
      error_log(print_r($this->getRequest()->getParameters(),true));
      error_log('startup REQUEST');
      error_log(print_r($_REQUEST,true));
      error_log('parse URL');
      $mikourl = parse_url($this->getHttpRequest()->getUrl()->getQuery());
      if ($mikourl!==false) {
          error_log(print_r($mikourl,true));
      }
      */

      if($this->user->isLoggedIn()){

         $data=$this->database->table('mikis.mikis_users')->where('id',$this->user->id)->fetch();
         if($data) {
              if($data->invalid_password_flag =='Y' && ('User:Config:changepass' != ($this->getRequest()->getPresenterName().':'.$this->getAction())) && 'Sign:out' != ($this->getRequest()->getPresenterName().':'.$this->getAction()))
              {
                  $this->flashMessage('Přednastavené heslo není povoleno, změňtě si heslo.', 'warning');
                  $this->redirect(':User:Config:changepass');
              }
         } else {
              $this->getUser()->logout();
              $this->flashMessage('Byli jste odhlášeni!');
		          $this->redirect(':Homepage:default');
         }
       }

       $identity = $this->getUser()->getIdentity();

       if ($this->lang==null) {
           if($this->user->isLoggedIn() && isset($identity) && isset($identity->default_lang) && $identity->getData()['default_lang']!==NULL) {
               $this->redirect(":Homepage:default",array("lang"=>$identity->getData()['default_lang'],));
           }
       } else {
           if($this->user->isLoggedIn() && isset($identity) && isset($identity->default_lang) && $identity->getData()['default_lang']!=$this->lang) {
               $this->redirect(":Homepage:default",array("lang"=>$identity->getData()['default_lang'],));
           }
       }

       $this->translator = new \App\MikosTranslator($this->lang);
       $this->template->setTranslator($this->translator);

       // try to authenticate over authoriyation header
       $posapiext = strpos($this->getRequest()->getPresenterName(), 'Apiext');
       if(!$this->user->isLoggedIn() && $posapiext===false)
       {
           $ab = $this->getHttpRequest()->getHeader('Authorization');
           if(isset($ab)){
               $ab = explode(' ',$ab)[1];
               $this->getUser()->login(explode(':',$ab)[0],explode(':',$ab)[1]);
           }
       }
       if (!($posapiext===false)) {
            if ($this->user->isLoggedIn()) {
                $this->user->logout();
            }
            $ab = $this->getHttpRequest()->getHeader('Authorization');
            //error_log('ab: '.$ab);
            if(isset($ab)){
                $ab = explode(' ',$ab)[1];
                $ab = base64_decode($ab);
                try {
                  $this->getUser()->login(explode(':',$ab)[0],explode(':',$ab)[1]);
                  $abidentity = $this->getUser()->getIdentity();
                  //error_log('identity:'.print_r($abidentity,TRUE));
                  if (isset($abidentity) && $abidentity->getData()['proxy_user_id']>=0) {
                      if (defined('__DBDRIVER__')&&(__DBDRIVER__=='pgsql')) {
                          $this->database->query('SET mikos.mikos_user_id=?;',$abidentity->getData()['proxy_user_id']);
                      } else {
                          $this->database->query('SET @mikos_user_id:=?;',$abidentity->getData()['proxy_user_id']);
                      }
                  } else {
                      if (defined('__DBDRIVER__')&&(__DBDRIVER__=='pgsql')) {
                          $this->database->query('SET mikos.mikos_user_id=?;',$this->getUser()->getId()===NULL?-1:$this->getUser()->getId());
                      } else {
                          $this->database->query('SET @mikos_user_id:=?;',$this->getUser()->getId());
                      }
                  }


                } catch (\Exception $e) {
                  null;
                }
            }
       }

       $this->url_query = $this->getHttpRequest()->getUrl()->getQuery();

       //$this->getHttpResponse()->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate'); // HTTP 1.1.
			 //$this->getHttpResponse()->setHeader('Pragma', 'no-cache'); // HTTP 1.0.
 	 		 //$this->getHttpResponse()->setHeader('Expires', '0'); // Proxies.

       $jsonbody = addslashes(json_encode($this->getHttpRequest()->getPost()));

       //$startdt1 = \DateTime::createFromFormat('U.u', Tracy\Debugger::$time);
       //$startdt2 = $startdt1->format("Y-m-d H:i:s.u");

       $sql =
       "
          insert into audit.log_requested_actions
          (
              user_id
            , user_is_logged
            , request_url
            , request_pathinfo
            , request_query
            , request_body
            , request_isajax
            , presenter_name
            , presenter_action
            , presenter_signal
            , creation_date
            , created_by
          ) values (
              ".(isset($this->user->id)?($this->user->id===NULL?'null':$this->user->id):'null')."
            , '".($this->user->isLoggedIn()?'Y':'N')."'
            , '".$this->getHttpRequest()->getUrl()->getRelativeUrl()."'
            , '".$this->getHttpRequest()->getUrl()->getPathInfo()."'
            , '".$this->getHttpRequest()->getUrl()->getQuery()."'
            , '".
           // addslashes(substr((strpos(serialize($this->getHttpRequest()->getPost()),'password')!==FALSE?'':serialize($this->getHttpRequest()->getPost())),0,1990)).''.'\n'
              substr(
                (strpos($jsonbody,'password')!==FALSE?'':$jsonbody)
                ,0,1990).''.'\n'
                ."'
            , '".($this->isAjax()?'Y':'N')."'
            , '".$this->getRequest()->getPresenterName()."'
            , '".$this->getAction()."'
            , '".serialize($this->getSignal())."'
            , now(6)
            , 0
          )
       ";
       if (defined('__DBDRIVER__')&&(__DBDRIVER__=='pgsql')) {
           $sql=str_replace('now(6)','CURRENT_TIMESTAMP',$sql);
       }
       $this->database->query($sql);
       $this->audit_id = $this->database->getInsertId();

       if($this->user->isLoggedIn()){
           $this->getProfileValues();
       }
    }

    public function getProfileValues(){
        $sql ="select mc.code,cp.value from mikis.config_profiles cp, mikis.config mc ,mikis.mikis_user_config muc
               where cp.profile_id = muc.profile_id
               and muc.id = ?
               and mc.id = cp.config_id;";
        $prof_values = $this->getDatabase()->query($sql,$this->user->id)->fetchAll();

        foreach($prof_values as $value){
            $this->profiles[$value->code] = $value->value;
        }

        $sql ="select mc.code,pv.user_profile_value from mikis.mikis_user_profile_values pv, mikis.config mc
               where pv.user_id = ?
               and mc.user_profile_flag = 'Y'
               and mc.id = pv.config_id;";
        $user_prof_values = $this->getDatabase()->query($sql,$this->user->id)->fetchAll();

        foreach($user_prof_values as $value){
            $this->profiles[$value->code] = $value->user_profile_value;
        }

        if (isset($this->profiles['ORG_ID'])) {
            $this->database->query('SET @mikos_org_id:=?;',$this->profiles['ORG_ID']);
        }
        if (isset($this->profiles['ORGANIZATION_ID'])) {
            $this->database->query('SET @mikos_organization_id:=?;',$this->profiles['ORGANIZATION_ID']);
        }

    }

    public function shutdown($response)
    {
       parent::shutdown($response);

       if (isset($this->audit_id) && ($this->audit_id>0)) {
           //$enddt1 = \DateTime::createFromFormat('U.u', microtime(true));
           //$enddt2 = $enddt1->format("Y-m-d H:i:s.u");
           $sql =
           "
              update audit.log_requested_actions
              set termination_date = now(6)
              where id = ?
           ";
           if (defined('__DBDRIVER__')&&(__DBDRIVER__=='pgsql')) {
               $sql=str_replace('now(6)','CURRENT_TIMESTAMP',$sql);
           }
           $this->database->query($sql,$this->audit_id);
       }
    }

    public function getDatabase()
    {
        return $this->database;
    }

    public function getConfigValue($p_config_module_code,$p_config_value_code,$p_default_value=NULL)
    {
        $value=$p_default_value;
        $data=$this->database->table('CONFIG')->select('value')->where('module',$p_config_module_code)->where('code',$p_config_value_code)->fetch();
        if ($data) {
            $value=$data['value'];
        }
        return $value;
    }

    public function getProxyUserId()
    {
        if ($this->user->getIdentity()->getData()['proxy_user_id']>=0) {
            return $this->user->getIdentity()->getData()['proxy_user_id'];
        }
        return $this->user->id;
    }

    protected function beforeRender()
    {
       $this->template->colstyles=array();
       if (isset($this->acl_action->colstyles) && $this->acl_action->colstyles!==NULL and $this->acl_action->colstyles!='') {
           $this->template->colstyles = json_decode($this->acl_action->colstyles, true);
       }

       $this->presenter_action = $this->getAction();
       //error_log($this->getRequest()->getPresenterName());
       $pn = $this->getRequest()->getPresenterName();
       $pos = strpos($pn,':');
       if ($pos===FALSE) {
         $this->presenter_module = null;
         $this->presenter_name = $pn;
         if ($pn=='Invoices' or $pn=='Config') {
            //$this->presenter_menu = $this->database->table('mikis.sa_top_menus')->where('menu_module IS NULL')->fetch();
            $this->presenter_menu = $this->database->table('mikis.sa_top_menus')->where('enabled_flag','Y')->where('menu_code=?','INVOICES_MENU')->fetch();
         } else {
            $this->presenter_menu = $this->database->table('mikis.sa_top_menus')->where('enabled_flag','Y')->where('menu_module IS NULL')->fetch();
         }
         if ($this->presenter_menu && $this->presenter_menu->id) {
           $presenter_menu_items = $this->database->table('mikis.sa_top_menu_items_v')->where('enabled_flag','Y')->where('menu_id',$this->presenter_menu->id)->fetchAll();
           $this->presenter_menu_items = array();
           foreach ($presenter_menu_items as $presenter_menu_item) {
               $item=$presenter_menu_item->toArray();
               if ($this->context->getByType('App\Model\ModuleManager')->isAvailablePresenter($item['item_module'],$item['item_presenter'])) {
                   $item['hover_flag']=true;
                   $item['dds']=null;
                   $item['dd_user_allowed']=false;
                   if ($item['item_type']=='D') {
                      $presenter_menu_item_dds = $this->database->table('mikis.sa_top_menu_dds')->where('enabled_flag','Y')->where('item_id',$item['id'])->fetchAll();
                      foreach ($presenter_menu_item_dds as $presenter_menu_item_dd) {
                        $item['dds'][]=$presenter_menu_item_dd->toArray();
                        if ($this->user->isAllowed($presenter_menu_item_dd->secured_by_resource,$presenter_menu_item_dd->secured_by_action)) {
                          $item['dd_user_allowed']=true;
                        }
                      }
                   }
                   $this->presenter_menu_items[]= $item;
               }
           }
         }
       } else {
         // old top menus
         $module = substr($pn,0,$pos);
         $pn = substr($pn,$pos+1,1000);
         $this->presenter_module = $module;
         $this->presenter_name = $pn;
         //error_log('=========================:');
         //error_log('BP:');
         //error_log('module:'.$module);
         //error_log('pn:'.$pn);
         //error_log('action:'.$this->presenter_action);
         $this->presenter_menu = $this->database->table('mikis.sa_top_menus')->where('enabled_flag','Y')->where('menu_module',$module)->fetch();
         if ($this->presenter_menu && $this->presenter_menu->id) {
           $presenter_menu_items = $this->database->table('mikis.sa_top_menu_items_v')->where('enabled_flag','Y')->where('menu_id',$this->presenter_menu->id)->fetchAll();
           $this->presenter_menu_items = array();
           foreach ($presenter_menu_items as $presenter_menu_item) {
               $item=$presenter_menu_item->toArray();
               if ($this->context->getByType('App\Model\ModuleManager')->isAvailablePresenter($item['item_module'],$item['item_presenter'])) {
                   //error_log('item_code:'.$item['item_code']);
               //error_log('item_module:'.$item['item_module']);
               //error_log('item_presenter:'.$item['item_presenter']);
                   $item['hover_flag']=($this->getRequest()->getPresenterName()==$item['item_module'].':'.$item['item_presenter']);
                   $item['dds']=null;
                   $item['dd_user_allowed']=false;
                   if ($item['item_type']=='D') {
                      $presenter_menu_item_dds = $this->database->table('mikis.sa_top_menu_dds')->where('enabled_flag','Y')->where('item_id',$item['id'])->fetchAll();
                      foreach ($presenter_menu_item_dds as $presenter_menu_item_dd) {
                        $item['dds'][]=$presenter_menu_item_dd->toArray();
                        if ($this->user->isAllowed($presenter_menu_item_dd->secured_by_resource,$presenter_menu_item_dd->secured_by_action)) {
                          $item['dd_user_allowed']=true;
                        }
                      }
                   }
                   if ($item['secured_by_presenter']!==NULL) {
                       $pan=$this->getAction();
                           //error_log('miko');
                           //error_log($item['secured_by_presenter']);
                           //error_log($pn);
                           //error_log($this->getAction());
                       if ($item['not_secured_by_action']===NULL or $item['not_secured_by_action']!==$pan) {
                           $posaction = strpos($item['secured_by_presenter'],':');
                           if ($posaction===FALSE) {
                               if ($item['secured_by_presenter']==$pn) {
                                   $this->presenter_menu_items[]= $item;
                               }
                           } else {
                               if ($item['secured_by_presenter']==($pn.':'.$pan)) {
                                   $this->presenter_menu_items[]= $item;
                               }
                           }
                       }
                   } else {
                       //error_log('miko2');
                       $this->presenter_menu_items[]= $item;
                   }
               }
           }
         }


         // acl top menus
         if ($this->acl_action) {
             //error_log('acl top menus: '.$this->acl_action->id);
             //error_log(print_r($this->presenter_menu_items,TRUE));

             if (!isset($this->presenter_menu_items)) {
                 $this->presenter_menu_items=array();
             }
             if (!($this->presenter_menu && $this->presenter_menu->id)) {
                 $this->presenter_menu = $this->database->query("select id,null menu_code, '".$this->presenter_module."' menu_module, '".$this->presenter_name."' menu_name from mikis.sa_top_menus where enabled_flag='Y' and id=1")->fetch();
             }

             $presenter_menu_items = $this->database->table('mikis.acl_action_topmenu_v')->where('enabled_flag','Y')->where('action_id',$this->acl_action->id)->order('item_order')->fetchAll();
             foreach ($presenter_menu_items as $presenter_menu_item) {
                 $item=$presenter_menu_item->toArray();
                 if ($this->context->getByType('App\Model\ModuleManager')->isAvailablePresenter($item['item_module'],$item['item_presenter'])) {
                     $item['dds']=null;
                     $item['dd_user_allowed']=false;
                     if ($item['item_type']=='D') {
                        $presenter_menu_item_dds = $this->database->table('mikis.sa_top_menu_dds')->where('enabled_flag','Y')->where('item_id',$item['id'])->fetchAll();
                        foreach ($presenter_menu_item_dds as $presenter_menu_item_dd) {
                          $item['dds'][]=$presenter_menu_item_dd->toArray();
                          if ($this->user->isAllowed($presenter_menu_item_dd->secured_by_resource,$presenter_menu_item_dd->secured_by_action)) {
                            $item['dd_user_allowed']=true;
                          }
                        }
                     }
                     $this->presenter_menu_items[]= $item;
                 }
             }

         } else {
             //error_log('try no acl menu:');
             // no_acl_flag=='Y' top menus
             $no_acl_action = $this->database->query("select * from acl.acl_action where module_name=? and presenter_name=? and action_name=? and no_acl_flag='Y'",$this->presenter_module,$this->presenter_name,$this->presenter_action)->fetch();
             if ($no_acl_action) {

                 if (!isset($this->presenter_menu_items)) {
                     $this->presenter_menu_items=array();
                 }
                 if (!($this->presenter_menu && $this->presenter_menu->id)) {
                     $this->presenter_menu = $this->database->query("select id,null menu_code, '".$this->presenter_module."' menu_module, '".$this->presenter_name."' menu_name from mikis.sa_top_menus where enabled_flag='Y' and id=1")->fetch();
                 }

                 $presenter_menu_items = $this->database->table('mikis.acl_action_topmenu_v')->where('no_acl_flag','Y')->where('enabled_flag','Y')->where('action_id',$no_acl_action->id)->order('item_order')->fetchAll();
                 foreach ($presenter_menu_items as $presenter_menu_item) {
                     $item=$presenter_menu_item->toArray();
                     if ($this->context->getByType('App\Model\ModuleManager')->isAvailablePresenter($item['item_module'],$item['item_presenter'])) {
                         $item['dds']=null;
                         $item['dd_user_allowed']=false;
                         if ($item['item_type']=='D') {
                            $presenter_menu_item_dds = $this->database->table('mikis.sa_top_menu_dds')->where('enabled_flag','Y')->where('item_id',$item['id'])->fetchAll();
                            foreach ($presenter_menu_item_dds as $presenter_menu_item_dd) {
                              $item['dds'][]=$presenter_menu_item_dd->toArray();
                              if ($this->user->isAllowed($presenter_menu_item_dd->secured_by_resource,$presenter_menu_item_dd->secured_by_action)) {
                                $item['dd_user_allowed']=true;
                              }
                            }
                         }
                         $this->presenter_menu_items[]= $item;
                     }
                 }
             }

          }
       }




       $this->menu_actions = $this->database->query("
          select pa.*
            , case when pam.secured_by_presenter_action=pam.action_name then 'selected' else 'deselected' end selection_class
            from mikis.sa_presenter_actions pa
          , mikis.sa_presenter_action_menusec pam
          where pa.presenter_name=?
          and pam.presenter_name=pa.presenter_name
          and pam.action_name=pa.action_name
          and pam.secured_by_presenter_action=?
          and pa.menu_enabled_flag='Y'
          and pa.enabled_flag='Y'
          and pa.action_desc!='Tools'
          order by pa.menu_order
          ",$this->getRequest()->getPresenterName(),$this->getAction())
          ->fetchAll();

       if (substr($this->getRequest()->getPresenterName(),0,4)=='Erp:') {
           $this->box_actions = $this->database->query("
              select pa.*
                from mikis.sa_presenter_actions pa
              where pa.presenter_name=?
              and pa.box_enabled_flag='Y'
              and pa.enabled_flag='Y'
              and pa.action_name=?
              and pa.action_desc!='Tools'
              order by pa.box_order
              "
              ,$this->getRequest()->getPresenterName()
              ,$this->getAction()
              )->fetchAll();
       } else {
           $this->box_actions = $this->database->query("
              select pa.*
                from mikis.sa_presenter_actions pa
              where pa.presenter_name=?
              and pa.box_enabled_flag='Y'
              and pa.enabled_flag='Y'
              and pa.action_desc!='Tools'
              order by pa.box_order
              ",$this->getRequest()->getPresenterName())
              ->fetchAll();
       }


       $this->fav_actions = $this->database->query("
          select * from mikis.mikis_user_favourites
          where user_id=?
          order by fav_order
          ",$this->user->id)
          ->fetchAll();

    }

    protected function error_log($p_code, $p_text, $p_file = NULL)
    {
        $flag = $this->database->table('mikis.config')->where('code','XXMK_LOG_ENABLED_FLAG')->fetch();
        if($flag->value == 'Y'){
            $module_code = $this->database->table('mikis.config')->where('code','XXMK_LOG_MODULE_CODES')->fetch();
            if(substr($module_code->value, 0, 3) == 'all' || !!preg_match('#\\b' . preg_quote($p_code, '#') . '\\b#i', $module_code->value)){
                if($p_file == null){
                    error_log($p_text);
                }
                else {
                    error_log("[".udate('Y-m-d H:i:s.u')."] ".$p_text."\n",3,__MIKOS_LOG_DIR__.$p_file.'.log');
                }
            }
        }
    }

/////////////////////////////////////
// ACL
/////////////////////////////////////

    private function getAclMenu() {
       $this->acl_actions =
           $this->database->query(
              "select am.id,am.action_id,am.menu_action_id,
               CASE WHEN am.menu_action_id = action_id THEN 'selected' ELSE 'deselected' END as active,
               CASE WHEN am.menu_action_id is not null THEN ifnull(am.menu_name,ifnull(a.menu_title,a.view_title)) ELSE am.menu_name END as menu_name,
               CASE WHEN am.menu_action_id is not null THEN CONCAT(':',a.module_name,':',a.presenter_name,':',a.action_name) ELSE am.menu_link END as menu_link,
               CASE WHEN am.menu_action_id is not null THEN a.sec_resource_code ELSE am.menu_sec_resource_code END as menu_sec_resource_code,
               CASE WHEN am.menu_action_id is not null THEN a.sec_action_code ELSE am.menu_sec_action_code END as menu_sec_action_code
               from acl.acl_action_menu am
               LEFT OUTER JOIN acl.acl_action a ON (a.id = am.menu_action_id)
               where am.action_id = ?
               order by ifnull(am.menu_card_order,9999999), am.id
               "
              ,$this->acl_action->id)->fetchAll();
        //error_log('aaa');
        //error_log(print_r($this->acl_actions,TRUE));
    }

    protected function getNoAclMenu() {
       $this->presenter_action = $this->getAction();
       $pn = $this->getRequest()->getPresenterName();
       $pos = strpos($pn,':');
       if ($pos===FALSE) {
           $this->presenter_module = null;
           $this->presenter_name = $pn;
       } else {
         // old top menus
         $module = substr($pn,0,$pos);
         $pn = substr($pn,$pos+1,1000);
         $this->presenter_module = $module;
         $this->presenter_name = $pn;
       }

       error_log(print_r($this->presenter_module,TRUE));
       error_log(print_r($this->presenter_name,TRUE));
       error_log(print_r($this->presenter_action,TRUE));

       $no_acl_action = $this->database->query("select * from acl.acl_action where module_name=? and presenter_name=? and action_name=? and no_acl_flag='Y'",$this->presenter_module,$this->presenter_name,$this->presenter_action)->fetch();
       if ($no_acl_action) {
           $this->no_acl_actions =
               $this->database->query(
                  "select am.id,am.action_id,am.menu_action_id,
                   CASE WHEN am.menu_action_id = action_id THEN 'selected' ELSE 'deselected' END as active,
                   CASE WHEN am.menu_action_id is not null THEN ifnull(am.menu_name,ifnull(a.menu_title,a.view_title)) ELSE am.menu_name END as menu_name,
                   CASE WHEN am.menu_action_id is not null THEN CONCAT(':',a.module_name,':',a.presenter_name,':',a.action_name) ELSE am.menu_link END as menu_link,
                   CASE WHEN am.menu_action_id is not null THEN a.sec_resource_code ELSE am.menu_sec_resource_code END as menu_sec_resource_code,
                   CASE WHEN am.menu_action_id is not null THEN a.sec_action_code ELSE am.menu_sec_action_code END as menu_sec_action_code
                   from acl.acl_action_menu am
                   LEFT OUTER JOIN acl.acl_action a ON (a.id = am.menu_action_id)
                   where am.action_id = ?
                   order by ifnull(am.menu_card_order,9999999), am.id
                   "
                  ,$no_acl_action->id)->fetchAll();
            //error_log('aaa');
            //error_log(print_r($this->acl_actions,TRUE));
       }
    }

    protected function generalActionSecurity()
    {
        //error_log('sec');
        if (!$this->user->isLoggedIn()) {
            $this->flashMessage('Byli jste odhlášeni.', 'error');
            $this->redirect(':Homepage:');
        }

        if (!$this->user->isAllowed($this->acl_action->sec_resource_code,$this->acl_action->sec_action_code)) {
             $this->flashMessage('You don\'t have permission !', 'error');
             if (isset($this->acl_action->sec_action_redirect)) {
                 $this->redirect($this->acl_action->sec_action_redirect);
             } else {
                 $this->redirect(':Homepage:');
             }
        }
    }

    protected function createComponentAclGrid01Grid() {
        //error_log(print_r($this->acl_grids,TRUE));
        return new AclGeneralGrid($this->acl_grids[1],$this->user,$this->database->table($this->acl_grids[1]->source_view));
    }

    protected function createComponentAclGrid02Grid() {
        //error_log(print_r($this->acl_grids,TRUE));
        return new AclGeneralGrid($this->acl_grids[2],$this->user,$this->database->table($this->acl_grids[2]->source_view));
    }

    protected function createComponentAclGrid03Grid() {
        //error_log(print_r($this->acl_grids,TRUE));
        return new AclGeneralGrid($this->acl_grids[3],$this->user,$this->database->table($this->acl_grids[3]->source_view));
    }

    /*
    protected function createComponentAclGridLovGrid() {
        return new AclLovGrid($this->user,$this->database->table($this->acl_form_lov["lov_table"]),$this->acl_form_lov["lov_table_id_col"],$this->acl_form_lov["lov_table_desc_col"]);
    }*/

    protected function createComponentAclGridImpGrid() {
        return new AclImportGrid($this->user,$this->database->table('mikis.sa_import_file_columns_v')->where('file_id',$this->acl_import_file_id)->where('excel_column IS NOT NULL')->order('excel_column'));
    }

    protected function createComponentAclForm01Form() {
        //error_log(print_r($this->acl_forms,TRUE));
        \Nette\Forms\Form::extensionMethod('addDatePicker', function(\Nette\Application\UI\Form $_this, $name, $label, $cols = NULL, $maxLength = NULL)
        {
          return $_this[$name] = new \RadekDostal\NetteComponents\DateTimePicker\DatePicker($label, $cols, $maxLength);
        });
        \Nette\Forms\Form::extensionMethod('addDateTimePicker', function(\Nette\Application\UI\Form $_this, $name, $label, $cols = NULL, $maxLength = NULL)
        {
          return $_this[$name] = new \RadekDostal\NetteComponents\DateTimePicker\DateTimePicker($label, $cols, $maxLength);
        });
        return new AclGeneralForm($this->acl_forms[1],$this->user,$this);
    }

    public function aclForm01FormSuccess($form) {
        $this->generalActionSecurity();
        $frmerr=0;
        $values = $form->getValues();

        if (isset($form["back"]) && $form["back"]->isSubmittedBy()) {
            $this->restoreRequest($this->backlink);
            if ($this->master_id!==NULL) {
                $this->redirect($form->acl_form->back_action,$this->master_id);
            } else {
                $this->redirect($form->acl_form->back_action);
            }
        }

        if (isset($form->acl_form->target_table)) {
            if ($form->acl_form->form_type=='Ins') {
                if (isset($form["save"]) && $form["save"]->isSubmittedBy()) {
                    $this->getDatabase()->beginTransaction();
                    try {
                        $sql = "select ff.*
                                from mikis.sa_form_fields ff, mikis.sa_forms f
                                where f.id = ff.form_id
                                and f.module_name=?
                                and f.presenter_name=?
                                and f.formname = ?
                                and ff.field_type='MS'
                                order by ff.field_order, ff.id
                                ";
                        $msfield = $this->database->query($sql,$this->acl_action->module_name, $this->acl_action->presenter_name, $form->acl_form->form_name)->fetch();
                        if ($msfield) {
                           //error_log(print_r($values[$msfield->field_name],TRUE));
                           if (count($values[$msfield->field_name])>0) {
                               $msvalues=$values[$msfield->field_name];
                           } else {
                               $msvalues=array(NULL);
                           }
                        } else {
                           $msvalues=array('dummy');
                        }

                        foreach ($msvalues as $msvalue) {
                            $sql = "select ff.*
                                    from mikis.sa_form_fields ff, mikis.sa_forms f
                                    where f.id = ff.form_id
                                    and f.module_name=?
                                    and f.presenter_name=?
                                    and f.formname = ?
                                    order by ff.field_order, ff.id
                                    ";
                            $fields = $this->database->query($sql,$this->acl_action->module_name, $this->acl_action->presenter_name, $form->acl_form->form_name)->fetchAll();
                            $ins=array();
                            $ins['creation_date'] = date("Y-m-d H:i:s");
                            $ins['created_by'] = $this->user->id;
                            $ins['last_update_date'] = date("Y-m-d H:i:s");
                            $ins['last_updated_by'] = $this->user->id;
                            foreach ($fields as $field) {
                                $old_fields[$field->field_name]=$field;
                                if (isset($field->db_column_name)) {
                                    if ($field->db_column_name=='last_update_date') {
                                        $ins[$field->db_column_name] = date("Y-m-d H:i:s");
                                    } else
                                    if ($field->db_column_name=='last_updated_by') {
                                        $ins[$field->db_column_name] = $this->user->id;
                                    } else
                                    if ($field->db_column_name=='creation_date') {
                                        $ins[$field->db_column_name] = date("Y-m-d H:i:s");
                                    } else
                                    if ($field->db_column_name=='created_by') {
                                        $ins[$field->db_column_name] = $this->user->id;
                                    } else
                                    if ($field->field_insertable_flag=='Y'){
                                        if ($field->visible_flag=='N' && $field->default_type!='P' && $field->default_type!='L' && $field->default_type!='SQ' && $field->default_value!==NULL) {
                                            $ins[$field->db_column_name] = $field->default_value;
                                        } elseif ($field->visible_flag=='N' && $field->default_type=='SQ' && $field->default_value!==NULL) {
                                            $getdefval = $this->database->query($field->default_value,$this->master_id)->fetch();
                                            if ($getdefval) {
                                                $ins[$field->db_column_name] = $getdefval->default_value;
                                            }
                                        } elseif ($field->visible_flag=='N' && $field->default_type=='P' && $field->default_value!==NULL) {
                                            $ins[$field->db_column_name] = $this->profiles[$field->default_value];
                                        } elseif ($field->field_type!=='S' && $field->default_type=='L' && $field->lov_table=='ASPREVIOUS') {
                                            if ($lov_row) {
                                                $ins[$field->db_column_name] = $lov_row[$field->lov_table_id_col];
                                            } else {
                                                if ($field->lov_error_msg!==NULL) {
                                                    $frmerr+=1;
                                                    $form[$field->field_name]->addError($field->lov_error_msg);
                                                } else {
                                                    throw new \Exception('Could not find LOV value !');
                                                }
                                            }
                                        } elseif ($field->field_type!=='S' && $field->field_type!=='MS' && $field->default_type=='L' && $field->lov_table!==NULL) {
                                            $lov_flag = true;
                                            if ($field->lov_table_where!==NULL and $field->lov_table_where!=='') {
                                                $sqlwhere=$field->lov_table_where;
                                                $matches=array();
                                                preg_match_all('/\$ins\[\'[a-zA-Z_]+\'\]/',$sqlwhere,$matches);
                                                //error_log(print_r($matches,TRUE));
                                                foreach ($matches[0] as $singlematch) {
                                                    $matchname=substr($singlematch,6,strlen($singlematch)-8);
                                                    $matchrepl=$ins[$matchname];
                                                    $sqlwhere = str_replace($singlematch,$matchrepl,$sqlwhere);

                                                    if ($matchrepl===NULL or $matchrepl='') {
                                                        if ($old_fields[$matchname]->field_required=='N') {
                                                            $lov_flag = false;
                                                        }
                                                    }
                                                }
                                            } else {
                                                $sqlwhere=$field->lov_table_where;
                                            }
                                            if ($field->field_required=='N' and $values[$field->field_name]=='') {
                                                $lov_flag = false;
                                            }
                                            if ($lov_flag) {
                                                //error_log($sqlwhere);
                                                $sql="SELECT * FROM ".$field->lov_table." where ".$sqlwhere;
                                                if ($field->lov_table_desc_col!==NULL and $field->lov_table_desc_col!=='') {
                                                    if ($sqlwhere!==NULL and $sqlwhere!=='') {
                                                        $sql=$sql." and ".$field->lov_table_desc_col."=?";
                                                    } else {
                                                        $sql=$sql." ".$field->lov_table_desc_col."=?";
                                                    }
                                                }
                                                //error_log($sql);
                                                if (isset($field->lov_db) && $field->lov_db=='O') {
                                                    if ($field->lov_table_desc_col!==NULL and $field->lov_table_desc_col!=='') {
                                                        $lov_row = $this->oracledb->query($sql,$values[$field->field_name])->fetch();
                                                    } else {
                                                        $lov_row = $this->oracledb->query($sql)->fetch();
                                                    }
                                                } else {
                                                    if ($field->lov_table_desc_col!==NULL and $field->lov_table_desc_col!=='') {
                                                        $lov_row = $this->database->query($sql,$values[$field->field_name])->fetch();
                                                    } else {
                                                        $lov_row = $this->database->query($sql)->fetch();
                                                    }
                                                }
                                                if ($lov_row) {
                                                    $ins[$field->db_column_name] = $lov_row[$field->lov_table_id_col];
                                                } else {
                                                    if ($field->lov_error_msg!==NULL) {
                                                        $frmerr+=1;
                                                        $form[$field->field_name]->addError($field->lov_error_msg);
                                                    } else {
                                                        throw new \Exception('Could not find LOV value !');
                                                    }
                                                }
                                            } else {
                                                $ins[$field->db_column_name] = NULL;
                                            }
                                        } elseif ($field->visible_flag=='N' && $field->default_predefined_value=='master_id') {
                                            $ins[$field->db_column_name] = $this->master_id;
                                        } elseif ($field->field_type=='N') {
                                            $ins[$field->db_column_name] = ($values[$field->field_name]=='') ? null : str_replace(',','.',$values[$field->field_name]);
                                        } elseif ($field->field_type=='MS') {
                                            $ins[$field->db_column_name] = $msvalue;
                                        } else {
                                            $ins[$field->db_column_name] = ($values[$field->field_name]=='') ? null : $values[$field->field_name];
                                        }
                                    }
                                }
                            }
                            if ($frmerr==0) {
                                error_log(print_r($ins,TRUE));
                                $datarow=$this->database->table($form->acl_form->target_table)->insert($ins);
                            }
                        }
                      //  error_log(print_r($ins,true));
                      //  error_log(print_r($form->acl_form->target_table,true));
                        if ($frmerr==0) {
                            $this->getDatabase()->commit();
                            $this->flashMessage('Saved.', 'success');
                        } else {
                            $this->getDatabase()->rollback();
                        }

                    } catch (\PDOException $e) {
                        $this->getDatabase()->rollback();
                        error_log($e);
                        $this->flashMessage('Error: '.iconv('windows-1250', 'UTF-8',substr($e,0,100)), 'error');
                    }

                    //$this->restoreRequest($this->backlink);
                    if ($frmerr==0) {
                        if ($this->master_id!==NULL) {
                            $this->redirect($form->acl_form->save_action,$this->master_id);
                        } else {
                            $this->redirect($form->acl_form->save_action);
                        }
                    }
                }
            }
            if ($form->acl_form->form_type=='Upd') {
                if (isset($form["save"]) && $form["save"]->isSubmittedBy()) {
                    $this->getDatabase()->beginTransaction();
                    try {
                        $sql = "select ff.*
                                from mikis.sa_form_fields ff, mikis.sa_forms f
                                where f.id = ff.form_id
                                and f.module_name=?
                                and f.presenter_name=?
                                and f.formname = ?
                                order by ff.field_order, ff.id
                                ";
                        $fields = $this->database->query($sql,$this->acl_action->module_name, $this->acl_action->presenter_name,$form->acl_form->form_name)->fetchAll();
                        $upd=array();
                        $val=array();
                        $datarow=$this->database->table($form->acl_form->target_table)->where('id = ?',$this->acl_form_data_id)->fetch();
                        $upd['last_update_date'] = date("Y-m-d H:i:s");
                        $upd['last_updated_by'] = $this->user->id;
                        foreach ($fields as $field) {
                            $old_fields[$field->field_name]=$field;
                            if (isset($field->db_column_name)) {
                                if ($field->db_column_name=='last_update_date') {
                                    $upd[$field->db_column_name] = date("Y-m-d H:i:s");
                                } else
                                if ($field->db_column_name=='last_updated_by') {
                                    $upd[$field->db_column_name] = $this->user->id;
                                } else
                                if ($field->db_column_name=='id') {
                                    ;
                                } else
                                if ($field->field_updatable_flag=='Y'){

                                    if ($field->field_type!=='S' && $field->default_type=='L' && $field->lov_table=='ASPREVIOUS') {
                                        if ($lov_row) {
                                            $upd[$field->db_column_name] = $lov_row[$field->lov_table_id_col];
                                        } else {
                                            throw new \Exception('Could not find LOV value !');
                                        }
                                    } elseif ($field->field_type!=='S' && $field->default_type=='L' && $field->lov_table!==NULL) {
                                        $lov_flag = true;
                                        if ($field->lov_table_where!==NULL and $field->lov_table_where!=='') {
                                            $sqlwhere=$field->lov_table_where;
                                            $matches=array();
                                            preg_match_all('/\$upd\[\'[a-zA-Z_]+\'\]/',$sqlwhere,$matches);
                                            //error_log(print_r($matches,TRUE));
                                            foreach ($matches[0] as $singlematch) {
                                                $matchname=substr($singlematch,6,strlen($singlematch)-8);
                                                if (isset($val[$matchname])) {
                                                    $matchrepl=$val[$matchname];
                                                } else {
                                                    $matchrepl=$upd[$matchname];
                                                }
                                                $sqlwhere = str_replace($singlematch,$matchrepl,$sqlwhere);

                                                if ($matchrepl===NULL or $matchrepl='') {
                                                    if ($old_fields[$matchname]->field_required=='N') {
                                                        $lov_flag = false;
                                                    }
                                                }
                                            }
                                        } else {
                                            $sqlwhere=$field->lov_table_where;
                                        }
                                        if ($field->field_required=='N' and $values[$field->field_name]=='') {
                                            $lov_flag = false;
                                        }
                                        //error_log($sqlwhere);
                                        if ($lov_flag) {
                                            $sql="SELECT * FROM ".$field->lov_table." where ".$sqlwhere;
                                            if ($field->lov_table_desc_col!==NULL and $field->lov_table_desc_col!=='') {
                                                if ($sqlwhere!==NULL and $sqlwhere!=='') {
                                                    $sql=$sql." and ".$field->lov_table_desc_col."=?";
                                                } else {
                                                    $sql=$sql." ".$field->lov_table_desc_col."=?";
                                                }
                                            }
                                            //error_log($sql);
                                            //error_log($field->field_name);
                                            //error_log($values[$field->field_name]);
                                            //error_log($field->lov_table_desc_col);
                                            if (isset($field->lov_db) && $field->lov_db=='O') {
                                                if ($field->lov_table_desc_col!==NULL and $field->lov_table_desc_col!=='') {
                                                    $lov_row = $this->oracledb->query($sql,$values[$field->field_name])->fetch();
                                                } else {
                                                    $lov_row = $this->oracledb->query($sql)->fetch();
                                                }
                                            } else {
                                                if ($field->lov_table_desc_col!==NULL and $field->lov_table_desc_col!=='') {
                                                    $lov_row = $this->database->query($sql,$values[$field->field_name])->fetch();
                                                } else {
                                                    $lov_row = $this->database->query($sql)->fetch();
                                                }
                                            }
                                            if ($lov_row) {
                                                $upd[$field->db_column_name] = $lov_row[$field->lov_table_id_col];
                                            } else {
                                                throw new \Exception('Could not find LOV value !');
                                            }
                                        } else {
                                            $upd[$field->db_column_name] = NULL;
                                        }
                                    } else {
                                        $upd[$field->db_column_name] = ($values[$field->field_name]=='') ? null : $values[$field->field_name];
                                    }
                                }
                                if ($field->field_updatable_flag=='N'){
                                    $val[$field->db_column_name] = ($values[$field->field_name]=='') ? null : $values[$field->field_name];
                                }
                            }
                        }
                        $datarow->update($upd);
                        $this->getDatabase()->commit();
                        $this->flashMessage('Saved.', 'success');

                    } catch (\PDOException $e) {
                        $this->getDatabase()->rollback();
                        error_log($e);
                        $this->flashMessage('Error: '.substr($e,0,100), 'error');
                    }

                    $this->restoreRequest($this->backlink);
                    if ($this->master_id!==NULL) {
                        $this->redirect($form->acl_form->save_action,$this->master_id);
                    } else {
                        $this->redirect($form->acl_form->save_action);
                    }
                }
            }
            if ($form->acl_form->form_type=='Upd/Ins') {
                if (isset($form["save"]) && $form["save"]->isSubmittedBy()) {
                    $this->getDatabase()->beginTransaction();
                    try {
                        $sql = "select ff.*
                                from mikis.sa_form_fields ff, mikis.sa_forms f
                                where f.id = ff.form_id
                                and f.module_name=?
                                and f.presenter_name=?
                                and f.formname = ?
                                order by ff.id
                                ";
                        $fields = $this->database->query($sql,$this->acl_action->module_name, $this->acl_action->presenter_name,$form->acl_form->form_name)->fetchAll();
                        $upd=array();
                        $datarow=$this->database->table($form->acl_form->target_table)->where('id = ?',$this->acl_form_data_id)->fetch();
                        if (!$datarow) {
                            //error_log('derfrtg');
                            $ins=array();
                            $ins['creation_date'] = date("Y-m-d H:i:s");
                            $ins['created_by'] = $this->user->id;
                            $ins['last_update_date'] = date("Y-m-d H:i:s");
                            $ins['last_updated_by'] = $this->user->id;
                            $ins['id'] = $this->acl_form_data_id;
                            $datarow=$this->database->table($form->acl_form->target_table)->insert($ins);
                            $datarow=$this->database->table($form->acl_form->target_table)->where('id = ?',$this->acl_form_data_id)->fetch();
                        }
                        $upd['last_update_date'] = date("Y-m-d H:i:s");
                        $upd['last_updated_by'] = $this->user->id;
                        foreach ($fields as $field) {
                            if (isset($field->db_column_name)) {
                                if ($field->db_column_name=='last_update_date') {
                                    $upd[$field->db_column_name] = date("Y-m-d H:i:s");
                                } else
                                if ($field->db_column_name=='last_updated_by') {
                                    $upd[$field->db_column_name] = $this->user->id;
                                } else
                                if ($field->db_column_name=='id') {
                                    ;
                                } else if ($field->field_updatable_flag=='Y'){
                                    $upd[$field->db_column_name] = ($values[$field->field_name]=='') ? null : $values[$field->field_name];
                                }
                            }
                        }
                        $datarow->update($upd);
                        $this->getDatabase()->commit();
                        $this->flashMessage('Saved.', 'success');

                    } catch (\PDOException $e) {
                        $this->getDatabase()->rollback();
                        error_log($e);
                        $this->flashMessage('Error: '.substr($e,0,100), 'error');
                    }

                    //$this->restoreRequest($this->backlink);
                    if ($this->master_id!==NULL) {
                        $this->redirect($form->acl_form->save_action,$this->master_id);
                    } else {
                        $this->redirect($form->acl_form->save_action);
                    }
                }
            }
        } else {
            $this->flashMessage('Target table not specified !', 'warning');
        }

        //$this->redirect($form->acl_form->back_action);
    }

    protected function createComponentAclForm02Form() {
        //error_log(print_r($this->acl_forms,TRUE));
        return new AclGeneralForm($this->acl_forms[2],$this->user,$this);
    }

    public function aclForm02FormSuccess($form) {
        $this->aclForm01FormSuccess($form);
    }

    protected function createComponentAclForm03Form() {
        //error_log(print_r($this->acl_forms,TRUE));
        return new AclGeneralForm($this->acl_forms[3],$this->user,$this);
    }

    public function aclForm03FormSuccess($form) {
        $this->aclForm01FormSuccess($form);
    }

    protected function actionGeneralLst() {
        $this->generalActionSecurity();
        $this->getAclMenu();
        //$this->flashMessage('foo2','error');
    }

    protected function actionGeneralIns($bl=NULL,array $args=null) {
        $this->generalActionSecurity();
        $this->backlink = $bl;
        //error_log('in actionGeneralIns');
        //error_log(print_r($args,TRUE));
        $this->mikos_args = $args;
        $this->getAclMenu();
    }

    protected function actionGeneralInsSel($id,$bl=NULL) {
        $this->generalActionSecurity();
        $this->backlink = $bl;
        $this->acl_form_data_id = $id;
        $this->getAclMenu();
    }

    protected function actionGeneralUpd($id,$bl=NULL) {
        $this->generalActionSecurity();
        $this->backlink = $bl;
        $this->acl_form_data_id = $id;
        $this->getAclMenu();
    }

    protected function actionGeneralDel($id,$bl=NULL) {
        $this->generalActionSecurity();
        $this->backlink = $bl;
        $this->acl_form_data_id = $id;
        $this->getAclMenu();

        if (isset($this->acl_action->target_table)) {
            $this->getDatabase()->beginTransaction();
            try {
                $sqldel="delete from ".$this->acl_action->target_table." where id = ?";
                $this->getDatabase()->query($sqldel,$this->acl_form_data_id);
                $this->getDatabase()->commit();

                $this->flashMessage('Delete successful.', 'success');

            } catch (\PDOException $e) {
                  $this->getDatabase()->rollback();
                  error_log($e);
                  $this->flashMessage('Error: '.substr($e,0,100), 'error');
            }
        } else {
            $this->flashMessage('Target table not specified !', 'warning');
        }
        $this->restoreRequest($this->backlink);
        if ($this->master_id!==NULL) {
            $this->redirect($this->acl_action->sec_action_redirect,$this->master_id);
        }
        $this->redirect($this->acl_action->sec_action_redirect);
    }

  	public function tryRun($method, array $params)
  	{
  		$rc = $this->getReflection();
  		if ($rc->hasMethod($method)) {
  			$rm = $rc->getMethod($method);
  			if (!$rm->isAbstract() && !$rm->isStatic()) {
  				$this->checkRequirements($rm);
  				$rm->invokeArgs($this, $rc->combineArgs($rm, $params));
          error_log('trytunresult: true');
  				return true;
  			}
  		}
          error_log('trytunresult: false');
  		return false;
  	}

    protected function actionGeneralExe($bl=NULL) {
        $this->generalActionSecurity();
        $this->backlink = $bl;
        $this->getAclMenu();

        if($this->acl_action->action_type == "MDB"){
            $this->getDatabase()->beginTransaction();
            try {
                $sqlexe=$this->acl_action->execution_command;
                $this->getDatabase()->query($sqlexe);
                $this->getDatabase()->commit();

                $this->flashMessage('Run successful.', 'success');

            } catch (\PDOException $e) {
                  $this->getDatabase()->rollback();
                  error_log($e);
                  $this->flashMessage('Error: '.substr($e,0,100), 'error');
            }
        }
        else if ($this->acl_action->action_type == "ODB"){
            $this->openOracleConnection();
            $this->oracledb->query("begin execute immediate 'alter session set NLS_LANGUAGE = AMERICAN'; end;");

            $this->oracledb->beginTransaction();
            try {
                $sqlexe=$this->acl_action->execution_command;
                $this->oracledb()->query($sqlexe);
                $this->oracledb()->commit();

                $this->flashMessage('Run successful.', 'success');

            } catch (\PDOException $e) {
                  $this->oracledb()->rollback();
                  error_log($e);
                  $this->flashMessage('Error: '.substr($e,0,100), 'error');
            }

        }
        else if ($this->acl_action->action_type == "PHP"){
            if ($this->tryRun($this->acl_action->execution_command, array())===FALSE) {
                  $this->flashMessage('Error: Could not run execution command !', 'error');
            };
        }

        $this->restoreRequest($this->backlink);
        $this->redirect($this->acl_action->sec_action_redirect);

    }

    public function handleGeneralChange($field,$value) {
        error_log("begin in Handle change");
        error_log($field);
        error_log($value);
        error_log("end in Handle change");
    }

    protected function actionGeneralImp($id,$sub_id,$flags=null,$bl=null) {
        $this->generalActionSecurity();
        $this->acl_form_data_id = $id;
        $this->acl_form_action_link = $sub_id;
        $this->master_id = $flags;
        $this->backlink = $bl;
        $this->getAclMenu();
    }

    protected function createComponentAclFormImpForm() {
        return new AclImportForm($this->user,$this);
    }

    public function AclFormImpFormSuccess($form) {
        $this->generalActionSecurity();

        $values = $form->getValues();
        if ($form["save"]->isSubmittedBy()) {
            try {

                $sql = "select table_name,import_form_action from mikis.sa_import_files where id = ?";
                $import = $this->database->query($sql,$values->data_id)->fetch();
                if ($import) {

                    if ($import->import_form_action!==NULL and $import->import_form_action!='') {
                        call_user_func_array(array($this, $import->import_form_action),array($form));
                    } else {

                        $form_data = $_FILES["form_data"]["tmp_name"];
                        $form_data_name = $_FILES["form_data"]["name"];
                        $form_data_size = $_FILES["form_data"]["size"];
                        $form_data_type = $_FILES["form_data"]["type"];
                        //$data = addslashes(fread(fopen($form_data, "r"), filesize($form_data)));

                        if ($form_data_type!='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
                            $this->flashMessage('Import error: Not excel file', 'error');
                        } else {
                            $imp = $form_data;
                            $inputFileType = \PHPExcel_IOFactory::identify($imp);
                            $objReader = \PHPExcel_IOFactory::createReader($inputFileType);

                            try
                            {
                                 $objPHPExcel = $objReader->load($imp);
                            }
                            catch(\PHPExcel_Reader_Exception $e)
                            {
                                  $this->flashMessage(substr($e->getMessage(),0,100), 'error');
                            }

                            $sql = "select table_name from mikis.sa_import_files where id = ?";
                            $import = $this->database->query($sql,$values->data_id)->fetch();

                            $highestRow = $objPHPExcel->getSheet(0)->getHighestRow();

                            $sql1 = " insert into ".$import->table_name;
                            $sql1 .=" (
                            ";

                            $data_file_columns = $this->getDatabase()->table('mikis.sa_import_file_columns')->where('file_id',$values->data_id)->order('id')->fetchAll();
                            $c=0;
                            foreach ($data_file_columns as $data_file_column) {
                                $c+=1;
                                if ($c>1) {$sql1 .= ",";}
                                $sql1 .= $data_file_column->column_name."";
                            }

                            $sql1 .="
                            )";

                            // hodnoty
                            $sql2a ="
                            select
                            ";


                            $sql2c ="
                            from mikis.counter c
                            where c.id = 1
                            ";

                            $sql3a ="
                            ON DUPLICATE KEY UPDATE
                            ";


                            for ($row = 1; $row < $highestRow; ++$row) {
                                $rowx = $row;
                                $sql2b = "";
                                $sql3b = "";
                                $data_file_columns_x = $this->getDatabase()->table('mikis.sa_import_file_columns')->order('id')->where('file_id',$values->data_id)->fetchAll();
                                foreach ($data_file_columns_x as $data_file_column) {
                                    if ($data_file_column->excel_column!==NULL && $data_file_column->fk_table!==NULL) {
                                        $row_value = $objPHPExcel->getSheet(0)->getCell($data_file_column->excel_column.($row+1))->getValue();
                                        $row_value = "'".str_replace(array('select','insert','delete','update','create','call','drop','trun'),'',$row_value)."'";
                                        $row_value = "(select ".$data_file_column->fk_lookup_column." from ".$data_file_column->fk_table." where ".$data_file_column->fk_search_column
                                                     ."=".$row_value.")";
                                    }
                                    elseif ($data_file_column->excel_column==NULL && substr($data_file_column->default_value,0,4)=='XARG') {
                                        $row_value = $this->user->id;
                                    }
                                    elseif ($data_file_column->excel_column==NULL && substr($data_file_column->default_value,0,4)=='XPRO') {
                                        $row_value = $this->profiles[substr($data_file_column->default_value,5,255)];
                                    }
                                    elseif ($data_file_column->excel_column==NULL && substr($data_file_column->default_value,0,4)=='XPRE') {
                                        if ($data_file_column->default_value=='XPRE_MASTER_ID') {
                                            $row_value = $this->master_id;
                                        }
                                    }
                                    elseif ($data_file_column->excel_column==NULL && substr($data_file_column->default_value,0,5)=='now()') {
                                        $row_value = "now()";
                                    }
                                    elseif ($data_file_column->excel_column==NULL
                                             && substr($data_file_column->default_value,0,5)!='now()'
                                             && substr($data_file_column->default_value,0,4)!='XARG'
                                             && substr($data_file_column->default_value,0,4)!='XPRO'
                                             && substr($data_file_column->default_value,0,4)!='XPRE'
                                            ) {
                                        $row_value = $data_file_column->default_value;
                                    }
                                    else {
                                        $row_value = $objPHPExcel->getSheet(0)->getCell($data_file_column->excel_column.($row+1))->getValue();
                                        $row_value = "'".str_replace(array('select','insert','delete','update','create','call','drop','trun'),'',$row_value)."'";
                                    }
                                    if ($data_file_column->column_name=='component_id') {
                                        $component_id = $objPHPExcel->getSheet(0)->getCell($data_file_column->excel_column.($row+1))->getValue();
                                    }
                                    if ($row_value=="''") {$row_value="null";}
                                    $sql2b .=",".  $row_value ."";
                                    if ($data_file_column->column_name!='creation_date' &&
                                        $data_file_column->column_name!='created_by' &&
                                        $data_file_column->column_name!='organization_id' &&
                                        $data_file_column->column_name!='component_id'
                                       ) {
                                    $sql3b .=", ".$data_file_column->column_name."=".$row_value ."";
                                    }

                                }
                                $sql2b = substr($sql2b,1);
                                $sql3b = substr($sql3b,1);
                                $sql = $sql1.$sql2a.$sql2b.$sql2c.$sql3a.$sql3b;
                                error_log($sql);
                                $this->getDatabase()->query($sql);
                            }
                            $this->flashMessage('Import OK', 'success');
                        }
                    }

                } else {
                    $this->flashMessage('Import error. Import setup not found !', 'error');
                }

            } catch (\Exception $e) {
                error_log($e);
                $this->flashMessage('Import error.'.substr($e,0,100), 'error');
            }
        }
        if (isset($this->master_id)) {
            if (isset($this->backlink)) {
                $this->redirect($values->action_link,$this->master_id,$this->backlink);
            }
            $this->redirect($values->action_link,$this->master_id);
        }
        $this->redirect($values->action_link);
    }

    public function actionRedirectWithParams($link,$params=array()) {
        $err = 0;
        $new_id = -1;
        try {
              $datarow=$this->database->table('mikis_form_downloads')->insert(array(
                  'creation_date' => date("Y-m-d H:i:s"),
                  'created_by' => $this->user->getIdentity()->data["id"],
                  'last_update_date' => date("Y-m-d H:i:s"),
                  'last_updated_by' => $this->user->getIdentity()->data["id"],
              ));
              $new_id = $datarow->id;

              foreach ($params as $key => $value) {
                  if ($value != ''){
                      $datarow=$this->database->table('mikis_form_params')->insert(array(
                          'download_id' => $new_id,
                          'param_name' => $key,
                          'param_value' => $value,
                          'creation_date' => date("Y-m-d H:i:s"),
                          'created_by' => $this->user->id,
                          'last_update_date' => date("Y-m-d H:i:s"),
                          'last_updated_by' => $this->user->id,
                      ));
                  }
              }

        } catch(\Exception $e) {
            $err = $err + 1;
            $this->flashMessage('Error: '.substr($e,0,300), 'error');
        }

        if ($err>0 or $new_id==-1) {
            if ($new_id==-1) {
                $this->flashMessage('Error: No parameters download_id !', 'error');
            }
            $this->redirect(':Homepage:');
        }

        $this->redirect($link,$new_id);

    }

  	public function tryMikoRun($method, array $params)
  	{
      error_log('tryMikoRun: start');
      error_log(print_r($params,true));
      $x['id']=$params;
  		$rc = $this->getReflection();
  		if ($rc->hasMethod($method)) {
  			$rm = $rc->getMethod($method);
  			if (!$rm->isAbstract() && !$rm->isStatic()) {
  				$this->checkRequirements($rm);
  				$rm->invokeArgs($this, $rc->combineArgs($rm, $x));
          error_log('tryrunresult: true');
  				return true;
  			}
  		}
      error_log('tryrunresult: false');
  		return false;
  	}
}
