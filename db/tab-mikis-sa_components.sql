create table mikis.sa_components
(
  id INT NOT NULL
, component_name VARCHAR(240) NOT NULL
, module_name VARCHAR(240) NOT NULL
, presenter_name VARCHAR(240) NOT NULL
, lst_action VARCHAR(240)
, lst_view VARCHAR(240)
, ins_action VARCHAR(240)
, upd_action VARCHAR(240)
, del_action VARCHAR(240)
, lst_grid_name VARCHAR(240)
, ins_form_name VARCHAR(240)
, upd_form_name VARCHAR(240)
, creation_date DATETIME NOT NULL
, created_by INT NOT NULL
, last_update_date DATETIME NOT NULL
, last_updated_by INT NOT NULL
, PRIMARY KEY (id)
);

