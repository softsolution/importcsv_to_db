<?php

$csv_file = "
| Дагестан республика| Карабудахкентский район| Уллубийаул село|Ял-устю улица|368537|
| Дагестан республика| Каякентский район|Гаша село|368555|
| Карачаево-Черкесская республика| Зеленчукский район| Лесо-Кяфарь хутор|Полевой переулок|369160|
| Карачаево-Черкесская республика| Зеленчукский район| Лесо-Кяфарь хутор|Дружбы улица|369160|1, 10, 11, 12, 12а, 13, 13а, 14, 14а, 15, 18, 2, 20|
";

//import from csv file
@set_time_limit(0);

$encoding       = 'UTF-8';//кодировка файла CP1251|UTF-8

$separator = '|';//разделитель ячеек
$current_row    = 0;
$rows_loaded    = 0;

$rows_start     = 1;//начинать импорт со строки
$rows_count     = 0;//количество записей для импорта
        
$file       = $_SERVER['DOCUMENT_ROOT'].'/import.csv';
$csv_file   = @fopen($file, 'r');
if (!$csv_file) { $error = 'Ошибка открытия файла'; }

if ($csv_file){
    //Импорт объектов в цикле
    while(!feof($csv_file)){

        //увеличим номер текущей строки
        $current_row++;
        //читаем строку
        $row = fgets($csv_file); if (!$row) { continue; }
        //если не достигли начала, пропускаем импорт и в начало
        if ($current_row < $rows_start){ continue; }
        
        //увеличим счетчик строк
        $rows_loaded++;
        //проверяем превышение лимита
        if ($rows_loaded > $rows_count && $rows_count > 0) { break; }

        //конвертим кодировку
        if ($encoding != 'UTF-8') { $row = iconv($encoding, 'UTF-8', $row); }
        if ($separator == 't') { $separator = "\t"; }

        //разбиваем строку на поля
        $row_struct = explode($separator, $row);
        
        //очищаем поля от кавычек
        foreach($row_struct as $key=>$val){
            $val = trim($val);
            $val = ltrim($val, $quote);
            $val = rtrim($val, $quote);
            $row_struct[$key] = $val;
        }

        //формируем удобный для переваривания массив
        //обрабатываем массив - в каждой строке несколько категорий, один индекс, может быть одна статья, однин адрес 
        $obj = array();
        $is_index = false;//флаг использования индекса
        $is_article = false;//флаг использования статьи
        foreach($row_struct as $cell_id => $cell){
            $cell = trim($cell);
            if($cell){
                //определяем это категория, индекс, или дома
                if(!$is_index){
                    if(is_numeric($cell)){
                        //это индекс
                        $obj['index'] = $cell;
                        $is_index = true;
                    } else {
                        //категория или статья
                        //если есть текст - переулок, проезд, улицы, проспект - статья
                        //$lover_cell = mb_strtolower($cell);
                        $lover_cell = $cell;
                        if(mb_strstr($lover_cell, 'переулок') || 
                           mb_strstr($lover_cell, 'проезд') || 
                           mb_strstr($lover_cell, 'улица') || 
                           mb_strstr($lover_cell, 'проспект') || 
                           mb_strstr($lover_cell, 'шоссе') ){
                            $obj['article'] = $cell;
                            
                        } else {
                            //это категория добавляем в массив категорий
                            $obj['cats'][] = $cell;
                        }
                    }
                    continue;
                } else {
                    //если предыдущая ячейка индес - то текущая - здания
                    $obj['buildings'] = $cell;
                    continue;
                }
            }
        }
        
        //разбираем массив
        //1.Обрабатываем массив категорий
        if($obj['cats']){
            $parent_cat_id = 1;//корневая категория
            foreach($obj['cats'] as $cat_key => $cat_title){
                //проверяем есть ли такая категория в дереве
                $cat_id = $inDB->get_field('cms_category', "LOWER(title) = '".mb_strtolower($cat_title)."' AND parent_id = ".$parent_cat_id, 'id');
                if($cat_id){
                    $parent_cat_id = $cat_id;
                } else {
                    
                    //формируем массив новой категории
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
        
        //2.Если есть заголовок статья - добавляем / обновляем статью
        if($obj['article']){
            
            //проверяем есть ли такая статья
            $article = $inDB->get_fields('cms_content', "LOWER(title) = '".mb_strtolower($obj['article'])."' AND category_id = ".$parent_cat_id, 'id, indexes, buildings');
                    
            //если нет такой статьи - добавляем
            if(!$article){
                //формируем массив статьи и добавляем ее в базу
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
                
                //обновялем статью
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
        //3.Если нет заголовка статьи - добавляем / обновляем индекс к категории
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