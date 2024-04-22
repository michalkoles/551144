<?php
//This file is auto-generated.

namespace App\AolModule\Presenters;

use Nette\Forms\Form;

trait aol_gridsTrait {

public function createComponentSaGridsGrid() {
    return new saGridsGrid('saGridsGrid',$this->user,$this->database->table('mikis.sa_grids'));
}
}
