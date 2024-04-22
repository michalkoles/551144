<?php

namespace App\AolModule\Presenters;

use Nette,
	App\Model;
use Nette\Forms\Form;
use RadekDostal\NetteComponents\DateTimePicker;
use RadekDostal\NetteComponents\TbDateTimePicker;
use Tracy\Debugger; 

//require __DIR__ . '/../kalk_func.php'; 

class GridsPresenter extends \App\Presenters\BasePresenter
{
    //use GridsTrait;
    use aol_gridsTrait;
    
    private function actionSecurity($p_action)
    {
        //error_log(date("Y-m-d H:i:s:u").' '.$p_action);
        
        if (!$this->user->isLoggedIn()) {
          $this->flashMessage('Byli jste odhlášeni.', 'error');
          $this->redirect(':Homepage:');
        }
        
        if ($p_action=='actionDefault') {
            if (!$this->user->isAllowed('ad_lookups','view')) {
             $this->flashMessage('Nemáte příslušná oprávnění.', 'error');
             $this->redirect(':Homepage:');
            }          
        } 
        elseif ($p_action=='actionDefault') {
            if (!$this->user->isAllowed('ad_lookups','create')) {
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
        $this->actionSecurity('actionDefault');         
    }


}

