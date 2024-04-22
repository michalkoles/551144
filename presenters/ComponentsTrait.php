<?php
//This file is auto-generated.

namespace App\AdevelModule\Presenters;

trait ComponentsTrait {

public function createComponentComponentsGrid() {
    return new componentsGrid('componentsGrid',$this->user,$this->database->table('mikis.sa_components'));
}
public function createComponentComponentInsForm() {
    return new componentInsForm;
}
public function createComponentComponentUpdForm() {
    return new componentUpdForm;
}
}
