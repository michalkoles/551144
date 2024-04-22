delete from mikis.sa_grids where id = 301002
; 
delete from mikis.sa_grid_columns where grid_id = 301002
;

insert into mikis.sa_grids
(
  id
, gridname
, source_view
, exportable_flag  
, exportable_resource  
, exportable_action  
, creation_date
, created_by
, last_update_date
, last_updated_by
)
values
  (301002,'saGridsGrid','mikis.sa_grids','Y','sa_grids','gridexport',now(),0,now(),0)
;

insert into mikis.sa_grid_columns
(
  id
, grid_id
, column_name
, column_description
, column_size
, column_size_uom
, grid_visible_flag  
, db_column_name
, grid_filter_type
, grid_no_escape
, exportable_flag  
, export_renderer  
, export_format_function
, export_format_mask
, creation_date
, created_by
, last_update_date
, last_updated_by
)
values
  (0,301002,'id','ID',60,'px','Y','id','N',null,'Y',null,null,null,now(),0,now(),0)
, (0,301002,'gridname','Name',140,'px','Y','gridname','T',null,'Y',null,null,null,now(),0,now(),0)
, (0,301002,'source_view','Source View',100,'px','Y','source_view','T',null,'Y',null,null,null,now(),0,now(),0)
, (0,301002,'ins_action','Ins Action',100,'px','Y','ins_action','T',null,'Y',null,null,null,now(),0,now(),0) 
, (0,301002,'exportable_flag','Exportable Flag',100,'px','Y','exportable_flag','T',null,'Y',null,null,null,now(),0,now(),0) 
, (0,301002,'exportable_resource','Exportable Resource',100,'px','Y','exportable_resource','T',null,'Y',null,null,null,now(),0,now(),0) 
, (0,301002,'exportable_action','Exportable Action',100,'px','Y','exportable_action','T',null,'Y',null,null,null,now(),0,now(),0) 
, (0,301002,'export_template','Export Template Name',100,'px','Y','export_template','T',null,'Y',null,null,null,now(),0,now(),0) 
;
