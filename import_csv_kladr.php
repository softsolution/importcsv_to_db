<?php

$csv_file = "
| �������� ����������| ����������������� �����| ���������� ����|��-���� �����|368537|
| �������� ����������| ����������� �����|���� ����|368555|
| ���������-���������� ����������| ������������ �����| ����-������ �����|������� ��������|369160|
| ���������-���������� ����������| ������������ �����| ����-������ �����|������ �����|369160|1, 10, 11, 12, 12�, 13, 13�, 14, 14�, 15, 18, 2, 20|
";

//import from csv file
@set_time_limit(0);

$encoding       = 'UTF-8';//��������� ����� CP1251|UTF-8

$separator = '|';//����������� �����
$current_row    = 0;
$rows_loaded    = 0;

$rows_start     = 1;//�������� ������ �� ������
$rows_count     = 0;//���������� ������� ��� �������
        
$file       = $_SERVER['DOCUMENT_ROOT'].'/import.csv';
$csv_file   = @fopen($file, 'r');
if (!$csv_file) { $error = '������ �������� �����'; }

if ($csv_file){
    //������ �������� � �����
    while(!feof($csv_file)){

        //�������� ����� ������� ������
        $current_row++;
        //������ ������
        $row = fgets($csv_file); if (!$row) { continue; }
        //���� �� �������� ������, ���������� ������ � � ������
        if ($current_row < $rows_start){ continue; }
        
        //�������� ������� �����
        $rows_loaded++;
        //��������� ���������� ������
        if ($rows_loaded > $rows_count && $rows_count > 0) { break; }

        //��������� ���������
        if ($encoding != 'UTF-8') { $row = iconv($encoding, 'UTF-8', $row); }
        if ($separator == 't') { $separator = "\t"; }

        //��������� ������ �� ����
        $row_struct = explode($separator, $row);
        
        //������� ���� �� �������
        foreach($row_struct as $key=>$val){
            $val = trim($val);
            $val = ltrim($val, $quote);
            $val = rtrim($val, $quote);
            $row_struct[$key] = $val;
        }

        //��������� ������� ��� ������������� ������
        //������������ ������ - � ������ ������ ��������� ���������, ���� ������, ����� ���� ���� ������, ����� ����� 
        $obj = array();
        $is_index = false;//���� ������������� �������
        $is_article = false;//���� ������������� ������
        foreach($row_struct as $cell_id => $cell){
            $cell = trim($cell);
            if($cell){
                //���������� ��� ���������, ������, ��� ����
                if(!$is_index){
                    if(is_numeric($cell)){
                        //��� ������
                        $obj['index'] = $cell;
                        $is_index = true;
                    } else {
                        //��������� ��� ������
                        //���� ���� ����� - ��������, ������, �����, �������� - ������
                        //$lover_cell = mb_strtolower($cell);
                        $lover_cell = $cell;
                        if(mb_strstr($lover_cell, '��������') || 
                           mb_strstr($lover_cell, '������') || 
                           mb_strstr($lover_cell, '�����') || 
                           mb_strstr($lover_cell, '��������') || 
                           mb_strstr($lover_cell, '�����') ){
                            $obj['article'] = $cell;
                            
                        } else {
                            //��� ��������� ��������� � ������ ���������
                            $obj['cats'][] = $cell;
                        }
                    }
                    continue;
                } else {
                    //���� ���������� ������ ����� - �� ������� - ������
                    $obj['buildings'] = $cell;
                    continue;
                }
            }
        }
        
        //��������� ������
        //1.������������ ������ ���������
        if($obj['cats']){
            $parent_cat_id = 1;//�������� ���������
            foreach($obj['cats'] as $cat_key => $cat_title){
                //��������� ���� �� ����� ��������� � ������
                $cat_id = $inDB->get_field('cms_category', "LOWER(title) = '".mb_strtolower($cat_title)."' AND parent_id = ".$parent_cat_id, 'id');
                if($cat_id){
                    $parent_cat_id = $cat_id;
                } else {
                    
                    //��������� ������ ����� ���������
                    $category = array();
                    $category['title'] = $cat_title ? $cat_title : $_LANG['AD_CATEGORY_UNTITLED'];
                    $category['parent_id']   = $parent_cat_id;
                    $category['published']   = 1;
                    $category['showdate']    = 0;
                    $category['showcomm']    = 0;
                    $category['showrss']     = 0;
                    $category['tpl']         = 'com_content_view.tpl';

                    $ns = $inCore->nestedSetsInit('cms_category');
                    $category['id'] = $ns->AddNode($category['parent_id']);
                    $category['seolink'] = cmsCore::generateCatSeoLink($category, 'cms_category', $model->config['is_url_cyrillic']);
                    if ($category['id']){
                        $inDB->update('cms_category', $category, $category['id']);
                        cmsCore::clearAccess($category['id'], 'category');
                    }
                    $cat_id = $category['id'];
                    $parent_cat_id = $cat_id;
                }
            }
        }
        
        //2.���� ���� ��������� ������ - ��������� / ��������� ������
        if($obj['article']){
            
            //��������� ���� �� ����� ������
            $article = $inDB->get_fields('cms_content', "LOWER(title) = '".mb_strtolower($obj['article'])."' AND category_id = ".$parent_cat_id, 'id, indexes, buildings');
                    
            //���� ��� ����� ������ - ���������
            if(!$article){
                //��������� ������ ������ � ��������� �� � ����
                $article = array();
                $article['category_id'] = $parent_cat_id ? $parent_cat_id : 1;
                $article['title']       = $obj['article'];
                $article['showtitle']   = 1;
                $article['published']   = 1;
                $article['showdate']    = 0;
                $article['showlatest']  = 0;
                $article['showpath']    = 1;
                $article['comments']    = 0;
                $article['canrate']     = 0;
                $article['user_id']     = 1;
                $article['tpl']         = 'com_content_read.tpl';
                
                $indexes   = array();
                $buildings = array();
                $indexes[$obj['index']]   = $obj['index'];
                if($obj['buildings']){
                    $buildings[$obj['index']] = $obj['buildings'];
                } else {
                    $buildings[$obj['index']] = '';
                }
                
                $article['indexes']   = $inDB->escape_string(cmsCore::arrayToYaml($indexes));
                $article['buildings'] = $inDB->escape_string(cmsCore::arrayToYaml($buildings));

                $article['id'] = $model->addArticle($article);
                
            } else {
                
                //��������� ������
                $indexes   = cmsCore::yamlToArray($article['indexes']);
                $buildings = cmsCore::yamlToArray($article['buildings']);
                if($obj['buildings']){
                    if($buildings[$obj['index']]){
                        $buildings[$obj['index']] .= '. '.$obj['buildings'];
                    } else {
                        $buildings[$obj['index']] = $obj['buildings'];
                    }
                }
                $indexes[$obj['index']] = $obj['index'];

                $indexes   = $inDB->escape_string(cmsCore::arrayToYaml($indexes));
                $buildings = $inDB->escape_string(cmsCore::arrayToYaml($buildings));
                
                $inDB->update('cms_content', array('indexes'=>$indexes, 'buildings'=>$buildings), $article['id']);
                
            }//end if article
            
        } else {
        //3.���� ��� ��������� ������ - ��������� / ��������� ������ � ���������
            if($obj['index']){
               
                $cat_data = $inDB->get_fields('cms_category', "id = ".$parent_cat_id, 'indexes, buildings');
                if($cat_data){
                    $indexes   = cmsCore::yamlToArray($cat_data['indexes']);
                    $buildings = cmsCore::yamlToArray($cat_data['buildings']);
                    if($obj['buildings']){
                        if($buildings[$obj['index']]){
                            $buildings[$obj['index']] .= '. '.$obj['buildings'];
                        } else {
                            $buildings[$obj['index']] = $obj['buildings'];
                        }
                    }
                    $indexes[$obj['index']] = $obj['index'];
                } else {
                    $indexes = array();
                    $buildings = array();
                    $indexes[$obj['index']] = $obj['index'];
                    if($obj['buildings']){
                        $buildings[$obj['index']] = $obj['buildings'];
                    }
                }

                $indexes   = $inDB->escape_string(cmsCore::arrayToYaml($indexes));
                $buildings = $inDB->escape_string(cmsCore::arrayToYaml($buildings));
                
                $inDB->update('cms_category', array('indexes'=>$indexes, 'buildings'=>$buildings), $parent_cat_id);
                
            }//end if index

        }//end if article

    }//while
}//if csv file